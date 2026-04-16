<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class MediaWriteController
{
    public function __construct(
        private readonly MediaUploadService $service = new MediaUploadService()
    ) {
    }

    public function upload(Request $request): JsonResponse
    {
        $file = $request->file('file');

        try {
            if (!$file instanceof UploadedFile) {
                throw new RuntimeException('Debes seleccionar un archivo antes de cargarlo.');
            }

            return response()->json([
                'ok' => true,
                'data' => $this->service->upload($file),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'No fue posible cargar el archivo multimedia.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }
}
