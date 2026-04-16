<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\MediaAccessService;
use Illuminate\Http\Response;
use RuntimeException;

class MediaReadController
{
    public function __construct(
        private readonly MediaAccessService $service = new MediaAccessService()
    ) {
    }

    public function download(int $messageId): Response
    {
        try {
            $file = $this->service->downloadMessageMedia($messageId);

            return response($file['content'], 200, [
                'Content-Type' => $file['content_type'],
                'Content-Disposition' => 'inline; filename="' . addslashes($file['filename']) . '"',
            ]);
        } catch (RuntimeException $e) {
            return response($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return response('No fue posible acceder al media solicitado.', 500);
        }
    }
}
