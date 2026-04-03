<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * @deprecated Map coordinates live on areas. Use `php artisan areas:fill-coordinates`.
 */
class GeocodePropertiesCommand extends Command
{
    protected $signature = 'properties:geocode {--chunk=100 : Passed through to areas:fill-coordinates}';

    protected $description = 'Alias: fills area coordinates (see areas:fill-coordinates)';

    public function handle(): int
    {
        $this->warn('properties:geocode is an alias. Prefer: php artisan areas:fill-coordinates');

        return $this->call('areas:fill-coordinates', [
            '--chunk' => $this->option('chunk'),
        ]);
    }
}
