<?php

namespace App\Console\Commands;

use App\Support\Brand\BrandScheduler;
use Illuminate\Console\Command;

/**
 * Execute a scheduled Artisan command under BrandJobPolicy
 * (per-brand / oia_only / global_shared).
 */
class BrandRunCommand extends Command
{
    protected $signature = 'brand:run
                            {target : The artisan command name to run (without options)}
                            {--option=* : Options as key=value (e.g. days=30, scheduled-tick=1)}';

    protected $description = 'Run an artisan command once per brand according to BrandJobPolicy';

    public function handle(): int
    {
        $target = (string) $this->argument('target');
        $parameters = [];
        foreach ((array) $this->option('option') as $pair) {
            if (! is_string($pair) || ! str_contains($pair, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $pair, 2);
            $key = ltrim(trim($key), '-');
            if ($key === '') {
                continue;
            }
            $parameters['--'.$key] = $value === '1' || $value === 'true' ? true : $value;
        }

        $result = BrandScheduler::runCommand($target, $parameters);
        foreach ($result['exit_codes'] as $brand => $code) {
            $this->line("[{$brand}] exit={$code}");
        }

        $failed = array_filter($result['exit_codes'], fn ($c) => (int) $c !== 0);

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
