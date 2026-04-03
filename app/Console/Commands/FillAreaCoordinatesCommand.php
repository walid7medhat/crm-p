<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Services\AreaCoordinateResolver;
use Illuminate\Console\Command;
class FillAreaCoordinatesCommand extends Command
{
    protected $signature = 'areas:fill-coordinates
                            {--chunk=100 : Chunk size for processing}
                            {--force : Re-resolve and overwrite even when latitude and longitude are already set}';

    protected $description = 'Assign latitude/longitude to areas from config mapping, then cached geocode, then default (skips rows that already have both values unless --force)';

    public function handle(AreaCoordinateResolver $resolver): int
    {
        $chunk = max(10, (int) $this->option('chunk'));
        $force = (bool) $this->option('force');

        $query = Area::query()->orderBy('id');
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }

        $total = $query->count();
        $this->info($force
            ? "Re-resolving all areas (--force): {$total}"
            : "Areas missing latitude or longitude: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $updated = 0;

        $query->chunkById($chunk, function ($areas) use ($resolver, &$updated, $force) {
            foreach ($areas as $area) {
                if ($resolver->fillAreaCoordinatesIfMissing($area, $force)) {
                    $updated++;
                    $this->line("Area #{$area->id} {$area->name}: {$area->latitude}, {$area->longitude}");
                }
            }
        });

        $this->newLine();
        $this->info("Done. Updated: {$updated}, total processed was: {$total}");

        return self::SUCCESS;
    }
}
