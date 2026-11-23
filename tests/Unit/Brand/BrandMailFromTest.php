<?php

namespace Tests\Unit\Brand;

use App\Support\Brand\BrandContext;
use App\Support\Brand\BrandMailFrom;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/** Email sender name follows the company. */
class BrandMailFromTest extends TestCase
{
    protected function tearDown(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));
        parent::tearDown();
    }

    private function email(): Email
    {
        return (new Email())
            ->from(new Address('crm@oiaproperties.com', 'OIA Properties'))
            ->to('someone-new@example.com')
            ->text('hi');
    }

    public function test_luxvant_sender_name(): void
    {
        config(['brands.brands.luxvant.mail_from_name' => 'Luxvant Properties', 'brands.brands.luxvant.mail_from_address' => null]);
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $mail = $this->email();
        BrandMailFrom::apply($mail);

        $this->assertSame('Luxvant Properties', $mail->getFrom()[0]->getName());
        $this->assertSame('crm@oiaproperties.com', $mail->getFrom()[0]->getAddress());
    }

    public function test_luxvant_sender_address_when_configured(): void
    {
        config(['brands.brands.luxvant.mail_from_name' => 'Luxvant Properties', 'brands.brands.luxvant.mail_from_address' => 'crm@luxvant.com']);
        app(BrandContext::class)->set(config('brands.brands.luxvant'));

        $mail = $this->email();
        BrandMailFrom::apply($mail);

        $this->assertSame('crm@luxvant.com', $mail->getFrom()[0]->getAddress());
    }

    public function test_reset_password_email_says_luxvant_then_restores_app_name(): void
    {
        config(['app.name' => 'oia properties', 'brands.brands.luxvant.mail_from_name' => 'Luxvant Properties']);
        app(BrandContext::class)->set(config('brands.brands.oia'));

        $user = new class extends \App\Models\User {
            public function hasRole($roles, ?string $guard = null): bool
            {
                return false;
            }
        };
        $user->forceFill(['id' => 900, 'brand_key' => 'luxvant', 'email' => 'u900@example.com']);
        $notification = new \Illuminate\Auth\Notifications\ResetPassword('tok');

        event(new \Illuminate\Notifications\Events\NotificationSending($user, $notification, 'mail'));
        $html = (string) $notification->toMail($user)->render();
        event(new \Illuminate\Notifications\Events\NotificationSent($user, $notification, 'mail'));

        $this->assertStringContainsString('Luxvant Properties', $html);
        $this->assertStringNotContainsString('oia properties', $html);
        $this->assertSame('oia properties', config('app.name'));
    }

    public function test_oia_sender_unchanged(): void
    {
        app(BrandContext::class)->set(config('brands.brands.oia'));

        $mail = $this->email();
        BrandMailFrom::apply($mail);

        $this->assertSame('OIA Properties', $mail->getFrom()[0]->getName());
        $this->assertSame('crm@oiaproperties.com', $mail->getFrom()[0]->getAddress());
    }
}
