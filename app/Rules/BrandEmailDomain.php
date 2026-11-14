<?php

namespace App\Rules;

use App\Support\Brand\BrandContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Email must belong to the active company domain
 * (OIA → @oiaproperties.com, Luxvant → @luxvant.com; config/brands.php email_domain).
 *
 * Pass the record's current email on updates so existing addresses on another
 * domain keep validating until someone actually changes them.
 */
class BrandEmailDomain implements ValidationRule
{
    public function __construct(private ?string $currentEmail = null)
    {
    }

    public static function domain(): ?string
    {
        $domain = app(BrandContext::class)->get()['email_domain'] ?? null;

        return is_string($domain) && $domain !== '' ? strtolower($domain) : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domain = self::domain();
        if ($domain === null || ! is_string($value) || $value === '') {
            return;
        }

        $email = strtolower(trim($value));

        if ($this->currentEmail !== null && $email === strtolower(trim($this->currentEmail))) {
            return;
        }

        if (! str_ends_with($email, '@'.$domain)) {
            $fail("The :attribute must be an @{$domain} email address.");
        }
    }
}
