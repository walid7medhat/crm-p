<?php

namespace Tests\Unit\Support;

use App\Support\SearchText;
use Tests\TestCase;

class SearchTextTest extends TestCase
{
    public function test_arabic_indic_digits_become_ascii_phone_digits(): void
    {
        $this->assertSame('0501234567', SearchText::phoneDigits('٠٥٠١٢٣٤٥٦٧'));
        $this->assertSame('0501234567', SearchText::phoneDigits('۰۵۰۱۲۳۴۵۶۷'));
    }

    public function test_arabic_letter_variants_fold_the_same_way_as_voice_search(): void
    {
        $this->assertSame('النهارده', SearchText::normalize('النهاردة'));
        $this->assertSame('yas island', SearchText::normalize('  YAS   Island '));
    }
}
