<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Services\AreaCoordinateResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncAreaReferenceCommand extends Command
{
    protected $signature = 'areas:sync-reference
                            {--dry-run : Show create/update counts only; no writes}
                            {--by-name : After id sync, update rows matched by exact name (only when that name is unique in the reference list)}
                            {--from-mapping : Also apply lat/lng from config/area_coordinates.php mapping by normalized area name}';

    protected $description = 'Insert or update reference areas from config/area_reference_sync.php';

    public function handle(): int
    {
        if (! Schema::hasTable('areas')) {
            $this->error('Table `areas` does not exist. Run migrations first.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('areas', 'latitude') || ! Schema::hasColumn('areas', 'longitude')) {
            $this->error('Columns areas.latitude / areas.longitude are missing. Run migrations (add_coordinates_to_areas_table).');

            return self::FAILURE;
        }

        $ref = config('area_reference_sync');
        if (! is_array($ref)) {
            $this->error('config/area_reference_sync.php is missing or invalid.');

            return self::FAILURE;
        }

        $rows = $this->collectRows($ref);
        if ($rows === []) {
            $this->warn('No reference rows in config.');

            return self::SUCCESS;
        }

        ksort($rows, SORT_NUMERIC);

        $refCount = count($rows);
        $this->info("Reference rows in config: {$refCount}");

        $totalAreas = (int) DB::table('areas')->count();
        $maxId = (int) DB::table('areas')->max('id');
        $this->info("Database before: {$totalAreas} row(s), max id = {$maxId}");

        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $wouldCreate = 0;
            $wouldUpdate = 0;
            foreach ($rows as $id => $_payload) {
                if (Area::query()->whereKey($id)->exists()) {
                    $wouldUpdate++;
                } else {
                    $wouldCreate++;
                }
            }
            $this->newLine();
            $this->info("Dry run: would create {$wouldCreate}, would update {$wouldUpdate}.");

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $id => $payload) {
            try {
                $existing = Area::query()->whereKey($id)->first();
                if ($existing) {
                    $existing->fill($payload);
                    $existing->save();
                    $updated++;
                } else {
                    $model = new Area;
                    $model->id = $id;
                    $model->fill($payload);
                    $model->save();
                    $created++;
                }
            } catch (Throwable $e) {
                $errors[] = "id {$id} ({$payload['name']}): ".$e->getMessage();
            }
        }

        $byNameUpdated = 0;
        if ($this->option('by-name')) {
            $nameCounts = [];
            foreach ($rows as $payload) {
                $n = $payload['name'];
                $nameCounts[$n] = ($nameCounts[$n] ?? 0) + 1;
            }

            foreach ($rows as $id => $payload) {
                if (($nameCounts[$payload['name']] ?? 0) > 1) {
                    continue;
                }

                try {
                    $hit = Area::query()->where('name', $payload['name'])->first();
                    if (! $hit) {
                        continue;
                    }
                    if ((int) $hit->id === (int) $id) {
                        continue;
                    }
                    $hit->fill($payload);
                    $hit->save();
                    $byNameUpdated++;
                    $this->line("By name \"{$payload['name']}\": updated area id {$hit->id} (reference id was {$id}).");
                } catch (Throwable $e) {
                    $errors[] = "by-name {$payload['name']}: ".$e->getMessage();
                }
            }
        }

        $mappingUpdated = 0;
        if ($this->option('from-mapping')) {
            $mapping = config('area_coordinates.mapping', []);
            foreach ($mapping as $key => $coords) {
                $lat = (float) ($coords['latitude'] ?? 0);
                $lng = (float) ($coords['longitude'] ?? 0);
                if ($lat === 0.0 && $lng === 0.0) {
                    continue;
                }
                $normalized = AreaCoordinateResolver::normalizeAreaName($key);
                if ($normalized === '') {
                    continue;
                }
                try {
                    $hits = Area::query()
                        ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
                        ->get();
                    if ($hits->count() !== 1) {
                        continue;
                    }
                    $hit = $hits->first();
                    $hit->latitude = $lat;
                    $hit->longitude = $lng;
                    $hit->save();
                    $mappingUpdated++;
                } catch (Throwable $e) {
                    $errors[] = "mapping \"{$key}\": ".$e->getMessage();
                }
            }
        }

        $this->newLine();
        $this->info("Created: {$created}, updated by id: {$updated}, updated by name: {$byNameUpdated}, from area_coordinates mapping: {$mappingUpdated}");

        if ($errors !== []) {
            $this->newLine();
            $this->warn('Some rows failed (others were still saved):');
            foreach ($errors as $line) {
                $this->line('  '.$line);
            }
        }

        $totalAfter = (int) DB::table('areas')->count();
        $maxIdAfter = (int) DB::table('areas')->max('id');
        $this->newLine();
        $this->info("Database after: {$totalAfter} row(s), max id = {$maxIdAfter}");

        return $errors === [] ? self::SUCCESS : self::FAILURE;
        // Return FAILURE if any error so CI/scripts notice; data may be partially applied.
    }

    /**
     * @return array<int, array{name: string, parent_id: int|null, type: string, latitude: float, longitude: float}>
     */
    private function collectRows(array $ref): array
    {
        $rows = [];

        foreach ($ref['base_areas'] ?? [] as $row) {
            [$id, $name, $parentId, $type, $lat, $lng] = $row;
            $rows[(int) $id] = $this->payload($name, $parentId, $type, $lat, $lng);
        }

        foreach ($ref['full_communities'] ?? [] as $row) {
            [$id, $name, $parentId, $type, $lat, $lng] = $row;
            $rows[(int) $id] = $this->payload($name, $parentId, $type, $lat, $lng);
        }

        foreach ($ref['yas_ad'] ?? [] as $row) {
            [$id, $name, $parentId, $type, $lat, $lng] = $row;
            $rows[(int) $id] = $this->payload($name, $parentId, $type, $lat, $lng);
        }

        foreach ($ref['yas_alt'] ?? [] as $row) {
            [$id, $name, $parentId, $type, $lat, $lng] = $row;
            $rows[(int) $id] = $this->payload($name, $parentId, $type, $lat, $lng);
        }

        return $rows;
    }

    /**
     * @return array{name: string, parent_id: int|null, type: string, latitude: float, longitude: float}
     */
    private function payload(string $name, mixed $parentId, string $type, float $lat, float $lng): array
    {
        return [
            'name' => $name,
            'parent_id' => $parentId === null ? null : (int) $parentId,
            'type' => $type,
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }
}
