<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappMessage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaAccessService
{
    public function __construct(
        private readonly WhatsappConfigService $configService = new WhatsappConfigService(),
    ) {
    }

    /**
     * @return array{content:string,content_type:string,filename:string}
     */
    public function downloadMessageMedia(int $messageId): array
    {
        $message = WhatsappMessage::query()->find($messageId);
        if (!$message instanceof WhatsappMessage) {
            throw new RuntimeException('Mensaje no encontrado.');
        }

        $payload = is_array($message->raw_payload) ? $message->raw_payload : [];
        $type = (string) ($message->message_type ?? '');
        $media = is_array($payload[$type] ?? null) ? $payload[$type] : [];

        $disk = trim((string) ($media['disk'] ?? ''));
        $path = trim((string) ($media['path'] ?? ''));
        if ($disk !== '' && $path !== '' && Storage::disk($disk)->exists($path)) {
            $content = Storage::disk($disk)->get($path);
            $contentType = trim((string) (Storage::disk($disk)->mimeType($path) ?: ($media['mime_type'] ?? 'application/octet-stream')));
            $filename = trim((string) ($media['filename'] ?? basename($path)));

            return [
                'content' => $content,
                'content_type' => $contentType !== '' ? $contentType : 'application/octet-stream',
                'filename' => $filename !== '' ? $filename : basename($path),
            ];
        }

        $directLink = trim((string) ($media['link'] ?? ''));
        if ($directLink !== '') {
            $response = Http::timeout(max(5, (int) config('whatsapp.transport.timeout', 15)))->get($directLink);
            return $this->responseToDownload($response, $media, $message);
        }

        $mediaId = trim((string) ($media['id'] ?? ''));
        if ($mediaId === '') {
            throw new RuntimeException('El mensaje no tiene media descargable.');
        }

        $config = $this->configService->get();
        if (!$config['enabled'] || $config['access_token'] === '') {
            throw new RuntimeException('La integración de WhatsApp Cloud API no está lista para descargar media.');
        }

        $baseUrl = rtrim((string) config('whatsapp.transport.graph_base_url', 'https://graph.facebook.com'), '/');
        $timeout = max(5, (int) config('whatsapp.transport.timeout', 15));
        $metadataEndpoint = sprintf('%s/%s/%s', $baseUrl, trim($config['api_version'], '/'), rawurlencode($mediaId));

        $metadataResponse = Http::timeout($timeout)
            ->withToken($config['access_token'])
            ->acceptJson()
            ->get($metadataEndpoint);

        $metadataPayload = $metadataResponse->json();
        if (!$metadataResponse->successful()) {
            throw new RuntimeException('No fue posible obtener metadata de media desde Meta.');
        }

        $downloadUrl = trim((string) data_get($metadataPayload, 'url', ''));
        if ($downloadUrl === '') {
            throw new RuntimeException('Meta no devolvió URL de descarga para este media.');
        }

        $binaryResponse = Http::timeout($timeout)
            ->withToken($config['access_token'])
            ->withHeaders(['Accept' => '*/*'])
            ->get($downloadUrl);

        return $this->responseToDownload($binaryResponse, array_merge($media, is_array($metadataPayload) ? $metadataPayload : []), $message);
    }

    /**
     * @param array<string, mixed> $media
     * @return array{content:string,content_type:string,filename:string}
     */
    private function responseToDownload(Response $response, array $media, WhatsappMessage $message): array
    {
        if (!$response->successful()) {
            throw new RuntimeException('No fue posible descargar el archivo multimedia.');
        }

        $contentType = trim((string) ($response->header('Content-Type') ?: ($media['mime_type'] ?? 'application/octet-stream')));
        $extension = match (true) {
            str_contains($contentType, 'image/') => 'jpg',
            str_contains($contentType, 'video/') => 'mp4',
            str_contains($contentType, 'audio/') => 'ogg',
            str_contains($contentType, 'pdf') => 'pdf',
            default => 'bin',
        };

        $filename = trim((string) ($media['filename'] ?? ''));
        if ($filename === '') {
            $filename = sprintf('whatsapp-media-%d.%s', $message->id, $extension);
        }

        return [
            'content' => $response->body(),
            'content_type' => $contentType !== '' ? $contentType : 'application/octet-stream',
            'filename' => $filename,
        ];
    }
}
