<?php

namespace App\Support\Brand;

use App\Models\User;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sender of outgoing emails per company: Luxvant people get "Luxvant Properties",
 * OIA people keep the default sender (MAIL_FROM_NAME). Same recipient rule as BrandUrl.
 */
class BrandMailFrom
{
    public static function apply(Email $message): void
    {
        $brandKey = self::brandKeyForRecipients($message) ?? app(BrandContext::class)->key();
        $brand = config("brands.brands.{$brandKey}", []);

        $name = $brand['mail_from_name'] ?? null;
        $address = $brand['mail_from_address'] ?? null;
        if (! $name && ! $address) {
            return; // keep config('mail.from')
        }

        $current = $message->getFrom()[0] ?? null;
        $address = $address ?: ($current?->getAddress() ?: config('mail.from.address'));
        $name = $name ?: ($current?->getName() ?: config('mail.from.name'));

        $message->from(new Address($address, $name));
    }

    /** Company name shown in the email header / "Regards," for a brand; null = keep APP_NAME. */
    public static function companyName(?string $brandKey = null): ?string
    {
        $brandKey ??= app(BrandContext::class)->key();

        return config("brands.brands.{$brandKey}.mail_from_name") ?: null;
    }

    /**
     * Run $render with config('app.name') set to the brand's company name, so the
     * Laravel email layout ("Regards, …", header) shows Luxvant for Luxvant people.
     */
    public static function withAppName(?string $brandKey, callable $render)
    {
        $name = self::companyName($brandKey);
        if (! $name) {
            return $render();
        }

        $original = config('app.name');
        config(['app.name' => $name]);
        try {
            return $render();
        } finally {
            config(['app.name' => $original]);
        }
    }

    /** Brand of the first known recipient; null = use the site currently in use. */
    private static function brandKeyForRecipients(Email $message): ?string
    {
        $emails = array_map(fn (Address $a) => strtolower($a->getAddress()), $message->getTo());
        if (! $emails) {
            return null;
        }

        try {
            $user = User::withoutGlobalScopes()->whereIn('email', $emails)->first();
        } catch (\Throwable) {
            return null;
        }

        return BrandUrl::brandKeyFor($user);
    }
}
