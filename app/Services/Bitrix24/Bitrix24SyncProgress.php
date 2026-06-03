<?php

namespace App\Services\Bitrix24;

use App\Models\BitrixSyncShard;
use App\Models\BitrixSyncState;
use App\Support\Bitrix24Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Bitrix24SyncProgress
{
    public const SYNC_KEY = 'global_sync';
    
    // إضافة ثوابت للتحكم في الذاكرة
    private const CACHE_TTL = 5; // 5 ثواني
    private const MAX_BATCH_SIZE = 100; // حد أقصى للدفعة
    private static $operationCounter = 0;
    private static $lastGcTime = 0;

    /**
     * تحسين استخدام الذاكرة مع Cache
     */
    public static function globalState(): BitrixSyncState
    {
        // استخدام Cache لتقليل عدد مرات قراءة قاعدة البيانات
        $cacheKey = 'bitrix_sync_state_' . self::SYNC_KEY;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return BitrixSyncState::firstOrCreate(
                ['key' => self::SYNC_KEY],
                ['status' => 'idle', 'cursor' => 0],
            );
        });
    }
    
    /**
     * تنظيف Cache بعد التحديثات
     */
    private static function clearGlobalStateCache(): void
    {
        Cache::forget('bitrix_sync_state_' . self::SYNC_KEY);
    }
    
    /**
     * إدارة الذاكرة وتنظيفها بشكل تلقائي
     */
    private static function manageMemory(string $context = ''): void
    {
        self::$operationCounter++;
        
        $now = microtime(true);
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        
        // تحذير إذا كان استخدام الذاكرة عالي
        if ($memoryUsage > 400) { // أكثر من 400 ميجابايت
            Log::warning('High memory usage in Bitrix24SyncProgress', [
                'context' => $context,
                'memory_mb' => round($memoryUsage, 2),
                'operations' => self::$operationCounter,
                'last_gc' => round($now - self::$lastGcTime, 2)
            ]);
        }
        
        // تنظيف الذاكرة كل 50 عملية أو كل 10 ثواني
        $shouldCleanup = (self::$operationCounter % 50 === 0) || 
                        (($now - self::$lastGcTime) > 10);
        
        if ($shouldCleanup && function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
            self::$lastGcTime = $now;
            
            if ($collected > 0) {
                Log::debug("Garbage collection collected {$collected} cycles", [
                    'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2)
                ]);
            }
        }
    }

    public static function isCancelled(): bool
    {
        $state = static::globalState();
        return in_array($state->status, ['cancelled', 'paused'], true);
    }

    public static function markRunning(int $userId, bool $skipExisting, string $syncMode = 'sequential'): void
    {
        $state = static::globalState();
        if ($state->status !== 'running') {
            $state->forceFill([
                'status'                  => 'running',
                'started_at'              => now(),
                'finished_at'             => null,
                'last_error'              => null,
                'user_id'                 => $userId,
                'skip_existing'           => $skipExisting,
                'sync_mode'               => $syncMode,
                'last_progress_at'        => now(),
                'last_processed_snapshot' => (int) $state->processed,
                'leads_per_sec'           => 0,
                'eta_seconds'             => null,
            ])->save();
            
            static::clearGlobalStateCache();
        }
        
        static::manageMemory('markRunning');
    }

    /**
     * Push incremental progress during a long importBatch (delta since last flush).
     *
     * @param  array{processed: int, new: int, existing: int, errors: int}  $stats
     * @param  array{processed: int, new: int, existing: int, errors: int}  $lastSnap
     */
    public static function flushImportProgress(
        array $stats,
        array &$lastSnap,
        ?int $cursor = null,
        ?int $total = null,
    ): void {
        $dp = (int) $stats['processed'] - (int) ($lastSnap['processed'] ?? 0);
        $dn = (int) $stats['new'] - (int) ($lastSnap['new'] ?? 0);
        $de = (int) $stats['existing'] - (int) ($lastSnap['existing'] ?? 0);
        $derr = (int) $stats['errors'] - (int) ($lastSnap['errors'] ?? 0);

        if ($dp === 0 && $dn === 0 && $de === 0 && $derr === 0 && $cursor === null && $total === null) {
            return;
        }

        self::recordChunk($dp, $dn, $de, $derr, $cursor, $total);

        $lastSnap = [
            'processed' => (int) $stats['processed'],
            'new'       => (int) $stats['new'],
            'existing'  => (int) $stats['existing'],
            'errors'    => (int) $stats['errors'],
        ];
        
        static::manageMemory('flushImportProgress');
    }

    /**
     * تحسين recordChunk لتقليل استخدام الذاكرة والـ Locks
     */
    public static function recordChunk(
        int $processed,
        int $newCount,
        int $existingCount,
        int $errorCount,
        ?int $cursor = null,
        ?int $total = null,
    ): void {
        if ($processed === 0 && $newCount === 0 && $existingCount === 0 && $errorCount === 0 && $cursor === null && $total === null) {
            return;
        }

        // استخدام update مباشرة بدلاً من load ثم save
        try {
            DB::transaction(function () use ($processed, $newCount, $existingCount, $errorCount, $cursor, $total) {
                // استخدام lockForUpdate مع timeout أقصر
                $locked = BitrixSyncState::where('key', self::SYNC_KEY)
                    ->lockForUpdate()
                    ->first();
                    
                if (!$locked) {
                    return;
                }

                $prevProcessed = (int) $locked->processed;
                $newProcessed = $prevProcessed + $processed;

                // بناء مصفوفة التحديثات
                $updates = [
                    'processed' => $newProcessed,
                    'new_count' => (int) $locked->new_count + $newCount,
                    'existing_count' => (int) $locked->existing_count + $existingCount,
                    'error_count' => (int) $locked->error_count + $errorCount,
                ];

                if ($cursor !== null) {
                    $updates['cursor'] = $cursor;
                }
                if ($total !== null && $total > 0) {
                    $updates['total'] = $total;
                }

                $lastAt = $locked->last_progress_at;
                $lastSnap = (int) $locked->last_processed_snapshot;
                $now = now();
                
                if ($lastAt && $processed > 0) {
                    $elapsed = max(1.0, $now->diffInMilliseconds($lastAt) / 1000);
                    $delta = max(0, $newProcessed - $lastSnap);
                    $instantRate = min(500.0, $delta / $elapsed);
                    $prevRate = (float) ($locked->leads_per_sec ?? 0);
                    $smoothed = $prevRate > 0
                        ? ($prevRate * 0.7) + ($instantRate * 0.3)
                        : $instantRate;
                    $updates['leads_per_sec'] = round(min(500.0, $smoothed), 2);
                    $updates['last_progress_at'] = $now;
                    $updates['last_processed_snapshot'] = $newProcessed;

                    $totalLeads = (int) ($updates['total'] ?? $locked->total);
                    if ($totalLeads > 0 && $smoothed > 0) {
                        $remaining = max(0, $totalLeads - $newProcessed);
                        $updates['eta_seconds'] = (int) ceil($remaining / $smoothed);
                    }
                }

                // تحديث مباشر بدون load مرة أخرى
                BitrixSyncState::where('key', self::SYNC_KEY)
                    ->update($updates);
                    
                // تنظيف المتغيرات الكبيرة
                unset($locked, $updates);
            });
            
            // تنظيف Cache
            static::clearGlobalStateCache();
            
        } catch (\Exception $e) {
            Log::error('Failed to record chunk in Bitrix24SyncProgress', [
                'error' => $e->getMessage(),
                'processed' => $processed,
                'trace' => $e->getTraceAsString()
            ]);
            
            // محاولة تحديث بدون transaction إذا فشل
            try {
                BitrixSyncState::where('key', self::SYNC_KEY)
                    ->increment('processed', $processed);
            } catch (\Exception $fallbackError) {
                Log::error('Fallback update also failed', ['error' => $fallbackError->getMessage()]);
            }
        }
        
        static::manageMemory('recordChunk');
    }

    public static function markFailed(string $message): void
    {
        static::globalState()->forceFill([
            'status'      => 'failed',
            'last_error'  => $message,
            'finished_at' => now(),
        ])->save();
        
        static::clearGlobalStateCache();
        static::manageMemory('markFailed');
    }

    /** Keep sync running; UI shows warning while worker auto-retries. */
    public static function noteTransientError(string $message): void
    {
        $state = static::globalState();
        $state->forceFill([
            'status'      => 'running',
            'last_error'  => $message,
            'finished_at' => null,
        ])->save();
        
        static::clearGlobalStateCache();
    }

    /**
     * Only mark complete when Bitrix24 pagination truly ended.
     */
    public static function shouldMarkComplete(int $total, int $processed, int $pageLeadCount, ?int $pageNext): bool
    {
        if ($pageNext !== null) {
            return false;
        }

        if ($pageLeadCount > 0 && $total > 0 && $processed < max(100, (int) ($total * 0.9))) {
            return false;
        }

        return true;
    }

    public static function markDone(): void
    {
        static::globalState()->forceFill([
            'status'      => 'done',
            'finished_at' => now(),
            'eta_seconds' => 0,
        ])->save();
        
        static::clearGlobalStateCache();
        static::manageMemory('markDone');
    }

    /**
     * تحسين recordShardChunk لمنع تجميع الذاكرة
     */
    public static function recordShardChunk(
        BitrixSyncShard $shard,
        int $processed,
        int $newCount,
        int $existingCount,
        int $errorCount,
        ?int $cursor = null,
    ): void {
        // استخدام update مباشرة
        $shard->forceFill([
            'processed'      => (int) $shard->processed + $processed,
            'new_count'      => (int) $shard->new_count + $newCount,
            'existing_count' => (int) $shard->existing_count + $existingCount,
            'error_count'    => (int) $shard->error_count + $errorCount,
            'cursor'         => $cursor ?? $shard->cursor,
            'status'         => 'running',
            'started_at'     => $shard->started_at ?? now(),
        ])->save();

        static::recordChunk($processed, $newCount, $existingCount, $errorCount, null, null);
        
        // تنظيف المرجع لتوفير الذاكرة
        unset($shard);
        static::manageMemory('recordShardChunk');
    }

    public static function markShardDone(BitrixSyncShard $shard): void
    {
        if (!Bitrix24Schema::shardsTableExists()) {
            static::markDone();
            return;
        }

        $shard->forceFill([
            'status'      => 'done',
            'finished_at' => now(),
        ])->save();

        // استخدام chunk بدلاً من count كبير
        $completed = 0;
        BitrixSyncShard::where('sync_key', self::SYNC_KEY)
            ->where('status', 'done')
            ->chunk(100, function ($chunk) use (&$completed) {
                $completed += count($chunk);
                unset($chunk); // تنظيف الذاكرة
            });

        $state = static::globalState();
        $state->forceFill(['shards_completed' => $completed])->save();

        $pending = 0;
        BitrixSyncShard::where('sync_key', self::SYNC_KEY)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->chunk(100, function ($chunk) use (&$pending) {
                $pending += count($chunk);
                unset($chunk);
            });

        if ($pending === 0 && $state->status === 'running') {
            static::markDone();
        }
        
        unset($shard);
        static::clearGlobalStateCache();
        static::manageMemory('markShardDone');
    }

    /**
     * تحسين aggregateShardTotals لمنع استنزاف الذاكرة
     */
    public static function aggregateShardTotals(): void
    {
        if (!Bitrix24Schema::shardsTableExists()) {
            return;
        }

        // استخدام تجميع مباشر من قاعدة البيانات بدون تحميل كل البيانات
        try {
            $totals = BitrixSyncShard::where('sync_key', self::SYNC_KEY)
                ->selectRaw('
                    COALESCE(SUM(processed), 0) as processed, 
                    COALESCE(SUM(new_count), 0) as new_count, 
                    COALESCE(SUM(existing_count), 0) as existing_count, 
                    COALESCE(SUM(error_count), 0) as error_count
                ')
                ->first();

            if ($totals) {
                static::globalState()->forceFill([
                    'processed'      => (int) $totals->processed,
                    'new_count'      => (int) $totals->new_count,
                    'existing_count' => (int) $totals->existing_count,
                    'error_count'    => (int) $totals->error_count,
                ])->save();
                
                static::clearGlobalStateCache();
            }
            
            unset($totals);
        } catch (\Exception $e) {
            Log::error('Failed to aggregate shard totals', [
                'error' => $e->getMessage()
            ]);
        }
        
        static::manageMemory('aggregateShardTotals');
    }
    
    /**
     * دالة مساعدة لمراقبة استخدام الذاكرة (للتشخيص)
     */
    public static function getMemoryStats(): array
    {
        return [
            'current_memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'operations_count' => self::$operationCounter,
            'last_gc_seconds_ago' => self::$lastGcTime > 0 ? round(microtime(true) - self::$lastGcTime, 2) : 0,
        ];
    }
}