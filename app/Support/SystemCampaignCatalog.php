<?php

namespace App\Support;

use App\Models\User;

/**
 * Audience keys and frequency options for system campaign popups.
 * Add a new target group by extending audiences() and matchers().
 */
class SystemCampaignCatalog
{
    public const SALES_ACTIVE = 'sales_active';

    public const MAX_FREQUENCY_HOURS = 24;

    public static function audiences(): array
    {
        return [
            self::SALES_ACTIVE => 'Sales Active',
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::audiences());
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public static function labels(array $keys): array
    {
        $all = self::audiences();

        return array_values(array_map(
            fn ($key) => $all[$key] ?? (string) $key,
            $keys
        ));
    }

    public static function frequencyHours(): array
    {
        return range(1, self::MAX_FREQUENCY_HOURS);
    }

    public static function frequencyLabel(int $hours): string
    {
        return $hours === 1 ? 'Every 1 hour' : "Every {$hours} hours";
    }

    public static function matches(User $user, array $audiences): bool
    {
        foreach ($audiences as $audience) {
            $matcher = self::matchers()[$audience] ?? null;
            if ($matcher && $matcher($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, callable(User): bool>
     */
    private static function matchers(): array
    {
        return [
            self::SALES_ACTIVE => fn (User $user) => $user->status === 'active' && $user->hasRole('sales'),
        ];
    }
}
