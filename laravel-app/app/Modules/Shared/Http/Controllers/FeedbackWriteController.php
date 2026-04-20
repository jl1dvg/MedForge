<?php

namespace App\Modules\Shared\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FeedbackWriteController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'in:suggestion,bug'],
            'module_key' => ['required', 'string', 'max:191'],
            'module_label' => ['required', 'string', 'max:191'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'current_path' => ['nullable', 'string', 'max:255'],
            'page_title' => ['nullable', 'string', 'max:191'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:png,jpg,jpeg,webp,pdf,doc,docx,txt'],
        ]);

        $userId = (int) ($request->session()->get('user_id') ?? auth()->id() ?? 0);

        if ($userId <= 0) {
            return response()->json([
                'ok' => false,
                'error' => 'Sesión expirada.',
            ], 401);
        }

        $attachment = $request->file('attachment');
        $attachmentMeta = $this->storeAttachment($attachment instanceof UploadedFile ? $attachment : null);

        $id = DB::table('user_feedback_reports')->insertGetId([
            'user_id' => $userId,
            'company_id' => $request->session()->get('company_id'),
            'report_type' => $validated['report_type'],
            'module_key' => trim((string) $validated['module_key']),
            'module_label' => trim((string) $validated['module_label']),
            'message' => trim((string) $validated['message']),
            'current_path' => isset($validated['current_path']) ? trim((string) $validated['current_path']) : null,
            'page_title' => isset($validated['page_title']) ? trim((string) $validated['page_title']) : null,
            'attachment_disk' => $attachmentMeta['disk'] ?? null,
            'attachment_path' => $attachmentMeta['path'] ?? null,
            'attachment_original_name' => $attachmentMeta['original_name'] ?? null,
            'attachment_mime_type' => $attachmentMeta['mime_type'] ?? null,
            'attachment_size' => $attachmentMeta['size'] ?? null,
            'metadata_json' => json_encode([
                'user_agent' => (string) $request->userAgent(),
                'ip' => (string) $request->ip(),
                'username' => (string) ($request->session()->get('username') ?? ''),
                'attachment_url' => $attachmentMeta['url'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Gracias. Tu reporte fue registrado.',
            'data' => ['id' => $id],
        ], 201);
    }

    private function storeAttachment(?UploadedFile $file): array
    {
        if (!$file instanceof UploadedFile) {
            return [];
        }

        if (!$file->isValid()) {
            return [];
        }

        $originalName = trim((string) $file->getClientOriginalName());
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $baseName = pathinfo($originalName !== '' ? $originalName : 'adjunto', PATHINFO_FILENAME);
        $normalizedBaseName = Str::of($baseName !== '' ? $baseName : 'adjunto')
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9\-_]+/', '-')
            ->trim('-')
            ->lower()
            ->value();

        if ($normalizedBaseName === '') {
            $normalizedBaseName = 'adjunto';
        }

        $storedName = now()->format('YmdHis') . '-' . Str::random(12) . '-' . $normalizedBaseName;
        if ($extension !== '') {
            $storedName .= '.' . ltrim($extension, '.');
        }

        $directory = 'feedback-attachments/' . now()->format('Y/m');
        $path = Storage::disk('public')->putFileAs($directory, $file, $storedName);

        if (!is_string($path) || trim($path) === '') {
            return [];
        }

        return [
            'disk' => 'public',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'original_name' => $originalName !== '' ? $originalName : basename($storedName),
            'mime_type' => trim((string) $file->getMimeType()) ?: 'application/octet-stream',
            'size' => (int) $file->getSize(),
        ];
    }
}
