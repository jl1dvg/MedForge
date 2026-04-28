<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackUiController
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
            'report_type' => trim((string) $request->query('report_type', '')),
            'module_key' => trim((string) $request->query('module_key', '')),
        ];

        $query = DB::table('user_feedback_reports as f')
            ->leftJoin('users as u', 'u.id', '=', 'f.user_id')
            ->leftJoin('users as ru', 'ru.id', '=', 'f.resolved_by_user_id')
            ->select([
                'f.id',
                'f.report_type',
                'f.module_key',
                'f.module_label',
                'f.message',
                'f.current_path',
                'f.page_title',
                'f.status',
                'f.attachment_disk',
                'f.attachment_path',
                'f.attachment_original_name',
                'f.attachment_mime_type',
                'f.attachment_size',
                'f.created_at',
                'f.updated_at',
                'f.resolved_at',
                'f.resolved_by_user_id',
                'u.nombre as reporter_name',
                'u.username as reporter_username',
                'ru.nombre as resolver_name',
                'ru.username as resolver_username',
            ]);

        if ($filters['search'] !== '') {
            $needle = '%' . $filters['search'] . '%';
            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->where('f.message', 'like', $needle)
                    ->orWhere('f.module_label', 'like', $needle)
                    ->orWhere('f.current_path', 'like', $needle)
                    ->orWhere('u.nombre', 'like', $needle)
                    ->orWhere('u.username', 'like', $needle);
            });
        }

        if ($filters['status'] !== '') {
            $query->where('f.status', $filters['status']);
        }

        if ($filters['report_type'] !== '') {
            $query->where('f.report_type', $filters['report_type']);
        }

        if ($filters['module_key'] !== '') {
            $query->where('f.module_key', $filters['module_key']);
        }

        $items = $query
            ->orderByDesc('f.created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($row) {
                $row->reporter_display = trim((string) ($row->reporter_name ?? $row->reporter_username ?? 'Usuario'));
                $row->resolver_display = trim((string) ($row->resolver_name ?? $row->resolver_username ?? ''));
                $row->attachment_url = null;

                if (($row->attachment_disk ?? null) === 'public' && is_string($row->attachment_path) && $row->attachment_path !== '') {
                    $row->attachment_url = url('/storage/' . ltrim($row->attachment_path, '/'));
                }

                return $row;
            });

        $moduleOptions = DB::table('user_feedback_reports')
            ->select(['module_key', 'module_label'])
            ->distinct()
            ->orderBy('module_label')
            ->get();

        return view('shared.v2-feedback-index', [
            'pageTitle' => 'Sugerencias y errores',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'items' => $items,
            'filters' => $filters,
            'moduleOptions' => $moduleOptions,
            'status' => session('status'),
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:nuevo,resuelto'],
        ]);

        $payload = [
            'status' => $validated['status'],
            'updated_at' => now(),
        ];

        if ($validated['status'] === 'resuelto') {
            $payload['resolved_at'] = now();
            $payload['resolved_by_user_id'] = (int) ($request->session()->get('user_id') ?? auth()->id() ?? 0) ?: null;
        } else {
            $payload['resolved_at'] = null;
            $payload['resolved_by_user_id'] = null;
        }

        DB::table('user_feedback_reports')
            ->where('id', $id)
            ->update($payload);

        return redirect()
            ->back()
            ->with('status', $validated['status'] === 'resuelto' ? 'resolved' : 'reopened');
    }
}
