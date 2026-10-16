<?php

namespace App\Support;

class UserAnimationCatalog
{
    /** @return list<array{id: string, label: string, kind: string, src: ?string, motion: string}> */
    public static function all(): array
    {
        $items = config('user_animations.items', []);
        $catalog = [];

        foreach ($items as $id => $item) {
            if (! is_string($id) || ! is_array($item)) {
                continue;
            }
            $resolved = self::resolve($id, $item);
            if ($resolved) {
                $catalog[] = $resolved;
            }
        }

        return $catalog;
    }

    /** @return array{id: string, label: string, kind: string, src: ?string, motion: string}|null */
    public static function find(string $id): ?array
    {
        $item = config('user_animations.items.'.$id);
        if (! is_array($item)) {
            return null;
        }

        return self::resolve($id, $item);
    }

    /** @param array<string, mixed> $item */
    private static function resolve(string $id, array $item): ?array
    {
        $src = isset($item['src']) ? (string) $item['src'] : '';
        if ($src === '') {
            foreach (['mp4', 'webm'] as $extension) {
                $relative = 'animations/'.$id.'.'.$extension;
                if (is_file(public_path($relative))) {
                    $src = '/'.$relative;
                    break;
                }
            }
        }

        $relative = ltrim($src, '/');
        if ($src === '' || ! is_file(public_path($relative))) {
            return null;
        }

        return [
            'id' => $id,
            'label' => (string) ($item['label'] ?? $id),
            'kind' => 'video',
            'src' => $src,
            'motion' => $id,
        ];
    }
}
