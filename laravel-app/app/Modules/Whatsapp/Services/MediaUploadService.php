<?php

namespace App\Modules\Whatsapp\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaUploadService
{
    /**
     * @return array{type:string,url:string,filename:string,mime_type:string,size:int,disk:string,path:string}
     */
    public function upload(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new RuntimeException('El archivo no se pudo cargar correctamente.');
        }

        $mimeType = trim((string) $file->getMimeType());
        $size = (int) $file->getSize();
        $type = $this->resolveType($mimeType);
        $this->assertAllowed($type, $mimeType, $size);

        $originalName = $this->normalizeFilename($file->getClientOriginalName(), $type, $mimeType);
        $storedName = now()->format('YmdHis') . '-' . Str::random(12) . '-' . $originalName;
        $directory = 'whatsapp-media/' . now()->format('Y/m');

        $path = Storage::disk('public')->putFileAs($directory, $file, $storedName);
        if (!is_string($path) || trim($path) === '') {
            throw new RuntimeException('No fue posible guardar el archivo multimedia.');
        }

        return [
            'type' => $type,
            'url' => Storage::disk('public')->url($path),
            'filename' => $originalName,
            'mime_type' => $mimeType !== '' ? $mimeType : 'application/octet-stream',
            'size' => $size,
            'disk' => 'public',
            'path' => $path,
        ];
    }

    private function resolveType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }

    private function assertAllowed(string $type, string $mimeType, int $size): void
    {
        $config = config('whatsapp.media', []);
        $maxKb = (int) data_get($config, $type . '.max_kb', 0);
        $allowed = data_get($config, $type . '.mime_types', []);
        $allowed = is_array($allowed) ? array_values(array_filter(array_map('strval', $allowed))) : [];

        if ($mimeType === '' || !in_array($mimeType, $allowed, true)) {
            throw new RuntimeException('El tipo de archivo no está permitido para WhatsApp.');
        }

        if ($maxKb > 0 && $size > ($maxKb * 1024)) {
            throw new RuntimeException('El archivo excede el tamaño máximo permitido para WhatsApp.');
        }
    }

    private function normalizeFilename(string $originalName, string $type, string $mimeType): string
    {
        $originalName = trim($originalName);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);

        $baseName = Str::of($baseName !== '' ? $baseName : 'archivo')
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9\-_]+/', '-')
            ->trim('-')
            ->lower()
            ->value();

        if ($baseName === '') {
            $baseName = 'archivo';
        }

        if ($extension === '') {
            $extension = match (true) {
                str_contains($mimeType, 'jpeg') => 'jpg',
                str_contains($mimeType, 'png') => 'png',
                str_contains($mimeType, 'webp') => 'webp',
                str_contains($mimeType, 'mp4') => 'mp4',
                str_contains($mimeType, 'mpeg') => 'mp3',
                str_contains($mimeType, 'ogg') => 'ogg',
                str_contains($mimeType, 'pdf') => 'pdf',
                default => $type,
            };
        }

        return $baseName . '.' . ltrim(strtolower($extension), '.');
    }
}
