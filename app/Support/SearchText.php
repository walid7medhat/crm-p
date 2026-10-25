<?php

namespace App\Support;

/**
 * Shared folding for smart search and listing voice search.
 * Keeps Arabic letter variants, Arabic-Indic digits, and spacing in one place.
 */
class SearchText
{
    public static function digitsToAscii(string $text): string
    {
        return str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $text
        );
    }

    public static function phoneDigits(string $text): string
    {
        $ascii = self::digitsToAscii($text);

        return preg_replace('/\D+/', '', $ascii) ?? '';
    }

    /**
     * Lowercase, fold Arabic letters, convert digits, and collapse punctuation.
     * Matches the historical VoiceSearchService rules, plus digit folding.
     */
    public static function normalize(string $text): string
    {
        $text = self::digitsToAscii(trim($text));
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(
            ['أ', 'إ', 'آ', 'ى', 'ة', 'ؤ', 'ئ', 'ـ'],
            ['ا', 'ا', 'ا', 'ي', 'ه', 'و', 'ي', ''],
            $text
        );
        $text = preg_replace('/[^\p{L}\p{N}\s\.\+]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
