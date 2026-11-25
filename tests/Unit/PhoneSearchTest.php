<?php

namespace Tests\Unit;

use App\Support\PhoneSearch;
use PHPUnit\Framework\TestCase;

class PhoneSearchTest extends TestCase
{
    public function test_uae_examples_are_detected_and_lookalikes_are_not(): void
    {
        $uae = [
            '+971501234567',
            '971501234567',
            '00971501234567',
            '0501234567',
            '050 123 4567',
            '050-123-4567',
            '(050) 1234567',
            '971 50 123 4567',
            '00971 50 123 4567',
            '0521234567',
            '0541234567',
            '0551234567',
            '0561234567',
            '0581234567',
            '041234567',
            '04 123 4567',
            '+971 4 123 4567',
            '0097141234567',
            '021234567',
            '031234567',
            '061234567',
            '071234567',
            '091234567',
            '+9710501234567',
        ];

        foreach ($uae as $phone) {
            $this->assertTrue(PhoneSearch::isUae($phone), "Expected UAE: {$phone}");
        }

        $notUae = [
            '+447911123456',
            '00447911123456',
            '+20 100 123 4567',
            '9715551234',
            '971',
            '00971',
            '050',
            '050123456',
            '05012345678',
            '0412345678',
            '44',
            '52',
            '5512345',
            '',
        ];

        foreach ($notUae as $phone) {
            $this->assertFalse(PhoneSearch::isUae($phone), "Expected not UAE: {$phone}");
        }
    }

    public function test_the_same_uae_number_shares_search_prefixes(): void
    {
        $expected = ['971501234567', '00971501234567', '0501234567'];

        foreach (['+971501234567', '00971501234567', '971501234567', '0501234567', '050 123 4567', '050-123-4567', '(050) 1234567'] as $typed) {
            $variants = PhoneSearch::typedVariants($typed);
            foreach ($expected as $prefix) {
                $this->assertContains($prefix, $variants, "{$typed} missed {$prefix}");
            }
            $this->assertNotContains('44', $variants);
        }
    }

    public function test_partial_and_prefix_searches_stay_specific(): void
    {
        $this->assertTrue(PhoneSearch::isBareUaeCode('+971'));
        $this->assertTrue(PhoneSearch::isBareUaeCode('00971'));
        $this->assertTrue(PhoneSearch::isBareUaeCode('971'));
        $this->assertSame([], PhoneSearch::typedVariants('+971'));

        $dubai = PhoneSearch::typedVariants('04');
        $this->assertContains('04', $dubai);
        $this->assertContains('9714', $dubai);
        $this->assertContains('009714', $dubai);
        $this->assertNotContains('44', $dubai);
        $this->assertNotContains('971', $dubai);

        $mobile = PhoneSearch::typedVariants('050');
        $this->assertContains('050', $mobile);
        $this->assertContains('97150', $mobile);
        $this->assertNotContains('97152', $mobile);

        $partialNational = PhoneSearch::typedVariants('50123');
        $this->assertContains('050123', $partialNational);
        $this->assertContains('97150123', $partialNational);
        $this->assertNotContains('97152', $partialNational);

        $mexico = PhoneSearch::typedVariants('52');
        $this->assertContains('52', $mexico);
        $this->assertNotContains('052', $mexico);
        $this->assertNotContains('97152', $mexico);

        $uk = PhoneSearch::typedVariants('+44 7911 123456');
        $this->assertContains('447911123456', $uk);
        $this->assertNotContains('971447911123456', $uk);
        foreach ($uk as $variant) {
            $this->assertFalse(str_starts_with($variant, '971') || str_starts_with($variant, '04'), $variant);
        }

        $oregon = PhoneSearch::typedVariants('9715551234');
        $this->assertContains('9715551234', $oregon);
        $this->assertNotContains('05551234', $oregon);
        $this->assertNotContains('050', $oregon);
    }

    public function test_selected_country_prefixes_a_national_number(): void
    {
        $uae = PhoneSearch::typedVariants('501234567', '971');
        $this->assertContains('971501234567', $uae);
        $this->assertContains('0501234567', $uae);

        $egypt = PhoneSearch::typedVariants('1001234567', '20');
        $this->assertContains('201001234567', $egypt);
        $this->assertContains('00201001234567', $egypt);
        $this->assertNotContains('01001234567', $egypt);

        $withOwnCode = PhoneSearch::typedVariants('+447911123456', '971');
        $this->assertContains('447911123456', $withOwnCode);
        $this->assertNotContains('971447911123456', $withOwnCode);
    }
}
