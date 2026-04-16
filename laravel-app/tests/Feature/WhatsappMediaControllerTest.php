<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsappMediaControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_messages');

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_message_id', 191)->nullable();
            $table->string('direction', 16);
            $table->string('message_type', 64)->default('text');
            $table->longText('body')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.api.read_enabled', true);
        config()->set('whatsapp.migration.api.write_enabled', true);
    }

    public function test_it_uploads_an_image_and_returns_a_public_media_url(): void
    {
        Storage::fake('public');

        $response = $this
            ->withoutMiddleware()
            ->post('/v2/whatsapp/api/media/upload', [
                'file' => UploadedFile::fake()->image('receta.png', 1200, 800),
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.filename', 'receta.png');

        $url = (string) $response->json('data.url');
        $this->assertStringContainsString('/storage/whatsapp-media/', $url);

        $path = ltrim(str_replace('/storage/', '', parse_url($url, PHP_URL_PATH) ?: ''), '/');
        Storage::disk('public')->assertExists($path);
    }

    public function test_it_rejects_a_disallowed_upload_mime_type(): void
    {
        Storage::fake('public');

        $response = $this
            ->withoutMiddleware()
            ->post('/v2/whatsapp/api/media/upload', [
                'file' => UploadedFile::fake()->create('malware.exe', 20, 'application/x-msdownload'),
            ], [
                'Accept' => 'application/json',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'El tipo de archivo no está permitido para WhatsApp.');
    }

    public function test_it_proxies_media_downloads_from_direct_links(): void
    {
        \DB::table('whatsapp_messages')->insert([
            'id' => 1,
            'conversation_id' => 44,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body' => 'Adjunto',
            'raw_payload' => json_encode([
                'document' => [
                    'link' => 'https://example.test/media/orden.pdf',
                    'filename' => 'orden.pdf',
                    'mime_type' => 'application/pdf',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://example.test/media/orden.pdf' => Http::response('pdf-body', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $response = $this
            ->withoutMiddleware()
            ->get('/v2/whatsapp/api/messages/1/media');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertSame('pdf-body', $response->getContent());
    }

    public function test_it_serves_media_downloads_from_local_storage_without_http_roundtrip(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('whatsapp-media/2026/04/archivo-local.pdf', 'local-pdf-body');

        \DB::table('whatsapp_messages')->insert([
            'id' => 2,
            'conversation_id' => 45,
            'direction' => 'outbound',
            'message_type' => 'document',
            'body' => 'Adjunto local',
            'raw_payload' => json_encode([
                'document' => [
                    'link' => 'https://cive.consulmed.me/storage/whatsapp-media/2026/04/archivo-local.pdf',
                    'disk' => 'public',
                    'path' => 'whatsapp-media/2026/04/archivo-local.pdf',
                    'filename' => 'archivo-local.pdf',
                    'mime_type' => 'application/pdf',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            '*' => Http::response('should-not-be-used', 500),
        ]);

        $response = $this
            ->withoutMiddleware()
            ->get('/v2/whatsapp/api/messages/2/media');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertSame('local-pdf-body', $response->getContent());
    }
}
