<?php

namespace App\Support\Brand;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Run scheduled Artisan commands under an explicit brand policy.
 * Never rely on the process default BrandContext (OIA).
 * Unclassified commands fail closed.
 */
class BrandScheduler
{
    /**
     * Execute a command according to BrandJobPolicy.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{ran: list<string>, exit_codes: array<string, int>}
     */
    public static function runCommand(string $command, array $parameters = []): array
    {
        $policy = BrandJobPolicy::requireCommand($command);
        $ran = [];
        $exitCodes = [];

        if ($policy === 'oia_only') {
            $exitCodes['oia'] = self::runOnce('oia', $command, $parameters);
            $ran[] = 'oia';

            return ['ran' => $ran, 'exit_codes' => $exitCodes];
        }

        if ($policy === 'luxvant_only') {
            $exitCodes['luxvant'] = self::runOnce('luxvant', $command, $parameters);
            $ran[] = 'luxvant';

            return ['ran' => $ran, 'exit_codes' => $exitCodes];
        }

        if ($policy === 'global_shared') {
            $key = (string) config('brands.default', 'oia');
            $exitCodes[$key] = self::runOnce($key, $command, $parameters);
            $ran[] = $key;

            return ['ran' => $ran, 'exit_codes' => $exitCodes];
        }

        if ($policy !== 'per_brand') {
            Log::error('brand_scheduler.unclassified_policy', [
                'command' => $command,
                'policy' => $policy,
            ]);
            throw new InvalidArgumentException("Unclassified brand policy [{$policy}] for command [{$command}].");
        }

        foreach (BrandJobPolicy::configuredBrandKeys() as $key) {
            $exitCodes[$key] = self::runOnce($key, $command, $parameters);
            $ran[] = $key;
        }

        return ['ran' => $ran, 'exit_codes' => $exitCodes];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function runOnce(string $brandKey, string $command, array $parameters = []): int
    {
        BrandCrmScope::reset();
        BrandIsolation::clearColumnCache();

        $brand = config('brands.brands.'.$brandKey);
        if (! is_array($brand) || ! isset($brand['key'])) {
            $brand = BrandResolver::defaultBrand();
        }

        app(BrandContext::class)->set($brand);

        try {
            return (int) Artisan::call($command, $parameters);
        } catch (Throwable $e) {
            report($e);

            return 1;
        } finally {
            BrandCrmScope::reset();
            BrandIsolation::clearColumnCache();
            app(BrandContext::class)->set(BrandResolver::defaultBrand());
        }
    }

    /**
     * @template T
     * @param  callable(string):T  $callback
     * @return array<string, T>
     */
    public static function forEachBrand(callable $callback): array
    {
        $results = [];
        foreach (BrandJobPolicy::configuredBrandKeys() as $key) {
            BrandCrmScope::reset();
            BrandIsolation::clearColumnCache();
            $brand = config('brands.brands.'.$key);
            if (! is_array($brand) || ! isset($brand['key'])) {
                continue;
            }
            app(BrandContext::class)->set($brand);
            try {
                $results[$key] = $callback($key);
            } finally {
                BrandCrmScope::reset();
                BrandIsolation::clearColumnCache();
                app(BrandContext::class)->set(BrandResolver::defaultBrand());
            }
        }

        return $results;
    }
}
