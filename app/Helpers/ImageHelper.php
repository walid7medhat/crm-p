<?php
// app/Helpers/ImageHelper.php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManagerStatic as Image;

class ImageHelper
{
  public static function compressAndConvertToWebP(
    UploadedFile $file,
    string $storagePath,
    array $options = []
): array {
    // Brand-aware disk: Luxvant leads/listings/deals/users → separate Luxvant storage;
    // sensitive folders (deals/…) → private disk, never public.
    $disk = \App\Support\Brand\BrandStorage::diskForNewUpload($storagePath);
    $storagePath = \App\Support\Brand\BrandIsolation::ensureCompanyStoragePath($storagePath);

    try {
        $image = Image::make($file->getRealPath());

        $quality   = $options['quality'] ?? 80;
        $maxWidth  = $options['max_width'] ?? 1920;
        $maxHeight = $options['max_height'] ?? 1080;

        $filename = uniqid() . '.webp';
        $fullPath = $storagePath . '/' . $filename;

        $originalSize   = $file->getSize();
        $originalName   = $file->getClientOriginalName();
        $originalWidth  = $image->width();
        $originalHeight = $image->height();

        // Resize
        if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
            $image->resize($maxWidth, $maxHeight, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        /**
         * =========================
         * Watermark Section
         * =========================
         */
        if (!empty($options['watermark']['enabled']) && $options['watermark']['enabled']) {

            $watermarkPath = $options['watermark']['path'] ?? null;

            if ($watermarkPath && file_exists(public_path($watermarkPath))) {

                $watermark = Image::make(public_path($watermarkPath));

                // Opacity (0–100)
                if (isset($options['watermark']['opacity'])) {
                    $watermark->opacity($options['watermark']['opacity']);
                }

                $position = $options['watermark']['position'] ?? 'bottom-right';
                $margin   = $options['watermark']['margin'] ?? 20;

                switch ($position) {
                    case 'center':
                        $image->insert($watermark, 'center');
                        break;

                    case 'top-left':
                        $image->insert($watermark, 'top-left', $margin, $margin);
                        break;

                    case 'top-right':
                        $image->insert($watermark, 'top-right', $margin, $margin);
                        break;

                    case 'bottom-left':
                        $image->insert($watermark, 'bottom-left', $margin, $margin);
                        break;

                    default: // bottom-right
                        $image->insert($watermark, 'bottom-right', $margin, $margin);
                        break;
                }
            }
        }

        // Encode to WebP
        $compressedImage = $image->encode('webp', $quality);

        Storage::disk($disk)->put($fullPath, (string) $compressedImage);

        $compressedSize = Storage::disk($disk)->size($fullPath);
        $compressionRatio = round(
            ($originalSize - $compressedSize) / $originalSize * 100,
            2
        );

        $compressedWidth  = $image->width();
        $compressedHeight = $image->height();

        $image->destroy();

        return [
            'path' => $fullPath,
            'disk' => $disk,
            'original_name' => $originalName,
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'compression_ratio' => $compressionRatio,
            'original_dimensions' => [
                'width' => $originalWidth,
                'height' => $originalHeight
            ],
            'compressed_dimensions' => [
                'width' => $compressedWidth,
                'height' => $compressedHeight
            ]
        ];

    } catch (\Exception $e) {
        \Log::warning("Compression failed, storing original file: ".$file->getClientOriginalName());
        $path = $file->store($storagePath, $disk);

        return [
            'path' => $path,
            'disk' => $disk,
            'original_name' => $file->getClientOriginalName()
        ];
    }
}

    public static function compressImage(UploadedFile $file, string $storagePath, array $options = []): array
    {
        $disk = \App\Support\Brand\BrandStorage::diskForNewUpload($storagePath);
        $storagePath = \App\Support\Brand\BrandIsolation::ensureCompanyStoragePath($storagePath);

        try {
            $image = \Intervention\Image\ImageManagerStatic::make($file->getRealPath());

            $quality = $options['quality'] ?? 80;
            $maxWidth = $options['max_width'] ?? 1920;
            $maxHeight = $options['max_height'] ?? 1080;

            $extension = $file->getClientOriginalExtension();
            $filename = uniqid() . '.' . $extension;
            $fullPath = $storagePath . '/' . $filename;

            $originalSize = $file->getSize();
            $originalName = $file->getClientOriginalName();
            $originalWidth = $image->width();
            $originalHeight = $image->height();

            if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
                $image->resize($maxWidth, $maxHeight, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }

            $compressedImage = $image->encode($extension, $quality);

            Storage::disk($disk)->put($fullPath, $compressedImage->__toString());

            $compressedSize = Storage::disk($disk)->size($fullPath);
            $compressionRatio = round(($originalSize - $compressedSize) / $originalSize * 100, 2);

            $compressedWidth = $image->width();
            $compressedHeight = $image->height();

            $image->destroy();

            return [
                'path' => $fullPath,
                'disk' => $disk,
                'original_name' => $originalName,
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'compression_ratio' => $compressionRatio,
                'original_dimensions' => [
                    'width' => $originalWidth,
                    'height' => $originalHeight
                ],
                'compressed_dimensions' => [
                    'width' => $compressedWidth,
                    'height' => $compressedHeight
                ]
            ];

        } catch (\Exception $e) {
            return self::storeWithoutCompression($file, $storagePath, $disk);
        }
    }

    private static function storeWithoutCompression(UploadedFile $file, string $storagePath, string $disk = 'public'): array
    {
        $path = $file->store($storagePath, $disk);
        $originalSize = $file->getSize();
        $compressedSize = Storage::disk($disk)->size($path);

        return [
            'path' => $path,
            'disk' => $disk,
            'original_name' => $file->getClientOriginalName(),
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'compression_ratio' => round(($originalSize - $compressedSize) / $originalSize * 100, 2),
            'original_dimensions' => ['width' => 0, 'height' => 0],
            'compressed_dimensions' => ['width' => 0, 'height' => 0]
        ];
    }

    /**
     * Delete a stored file from whichever disk holds it (Luxvant separate
     * storage, private company disk or main public disk). Refuses paths that
     * belong to the other company.
     */
    public static function deleteImage(string $path): bool
    {
        if (! $path) {
            return false;
        }

        if (! \App\Support\Brand\BrandIsolation::pathAllowedForCurrentBrand($path)) {
            return false;
        }

        $deleted = false;
        foreach (\App\Support\Brand\BrandStorage::allDisks() as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                $deleted = Storage::disk($disk)->delete($path) || $deleted;
            }
        }
        \App\Support\Brand\BrandStorage::forgetExists($path);

        return $deleted;
    }

    public static function processMultipleImages(array $files, string $storagePath, bool $convertToWebP = true): array
    {
        $results = [];

        foreach ($files as $file) {
            if ($convertToWebP) {
                $results[] = self::compressAndConvertToWebP($file, $storagePath);
            } else {
                $results[] = self::compressImage($file, $storagePath);
            }
        }

        return $results;
    }
   public static function getWatermarkedUrl(string $path, array $options = []): string
{
    $options['watermark'] = array_merge([
        'path' => \App\Support\Brand\BrandIsolation::watermarkStorageRelativePath(),
        'opacity' => 80,
        'position' => 'center',
        'margin' => 20,
    ], $options['watermark'] ?? []);

    $fullImagePath = storage_path('app/public/' . $path);

    if (!file_exists($fullImagePath)) {
        return asset('storage/' . $path);
    }

    $image = \Intervention\Image\ImageManagerStatic::make($fullImagePath);

    $watermarkFullPath = storage_path('app/public/' . $options['watermark']['path']);

    if (file_exists($watermarkFullPath)) {

        $watermark = \Intervention\Image\ImageManagerStatic::make($watermarkFullPath);
        $watermark->opacity($options['watermark']['opacity']);

        $margin = $options['watermark']['margin'];
        $position = $options['watermark']['position'];

        switch ($position) {
            case 'top-left':
                $image->insert($watermark, 'top-left', $margin, $margin);
                break;

            case 'top-right':
                $image->insert($watermark, 'top-right', $margin, $margin);
                break;

            case 'bottom-left':
                $image->insert($watermark, 'bottom-left', $margin, $margin);
                break;

            case 'bottom-right':
                $image->insert($watermark, 'bottom-right', $margin, $margin);
                break;

            default:
                $image->insert($watermark, 'center');
                break;
        }
    }

    $watermarkedPath = 'watermark/' . md5($path) . '.webp';

    Storage::disk('public')->put($watermarkedPath, (string) $image->encode('webp', 80));

    return asset('storage/' . $watermarkedPath);
}
}
