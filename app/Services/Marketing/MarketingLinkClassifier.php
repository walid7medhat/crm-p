<?php

namespace App\Services\Marketing;

/**
 * Detects what a pasted link points at so the UI can show the right icon:
 * drive_folder | drive_file | sheet | doc | image | video | other.
 */
class MarketingLinkClassifier
{
    private const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'heic', 'psd', 'ai'];
    private const VIDEO_EXT = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'm4v'];

    public static function kind(string $url, ?string $label = null): string
    {
        $u = strtolower($url);

        if (str_contains($u, 'docs.google.com/spreadsheets')) return 'sheet';
        if (str_contains($u, 'docs.google.com/document') || str_contains($u, 'docs.google.com/presentation')) return 'doc';
        if (str_contains($u, 'drive.google.com') && (str_contains($u, '/folders/') || str_contains($u, 'folderview'))) return 'drive_folder';
        if (str_contains($u, 'youtube.com') || str_contains($u, 'youtu.be') || str_contains($u, 'vimeo.com')) return 'video';

        // Drive file URLs carry no extension — fall back to the label ("T.1.png", "Taraf V1.mp4").
        foreach ([$label, parse_url($url, PHP_URL_PATH)] as $candidate) {
            $ext = strtolower(pathinfo((string) $candidate, PATHINFO_EXTENSION));
            if (in_array($ext, self::IMAGE_EXT, true)) return 'image';
            if (in_array($ext, self::VIDEO_EXT, true)) return 'video';
        }

        if (str_contains($u, 'drive.google.com')) return 'drive_file';

        return 'other';
    }

    private const GOOGLE_LABELS = [
        'drive_folder' => 'Drive folder',
        'drive_file' => 'Drive file',
        'sheet' => 'Google Sheet',
        'doc' => 'Google Doc',
    ];

    public static function defaultLabel(string $url): string
    {
        // Google URLs end in opaque ids — name them by what they are instead.
        $kind = self::kind($url);
        if (isset(self::GOOGLE_LABELS[$kind])) {
            return self::GOOGLE_LABELS[$kind];
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $last = $path !== '' ? basename($path) : '';
        // Drive ids / "view" segments are meaningless as labels.
        if ($last === '' || in_array($last, ['view', 'edit', 'preview'], true) || strlen($last) > 40) {
            return (string) (parse_url($url, PHP_URL_HOST) ?: 'Link');
        }

        return urldecode($last);
    }
}
