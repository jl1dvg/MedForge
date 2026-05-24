@extends('layouts.medforge')

@php
    $leadsData = is_array($leads ?? null) ? $leads : ['total' => 0, 'items' => [], 'page' => 1, 'per_page' => 50];
    $items     = is_array($leadsData['items'] ?? null) ? $leadsData['items'] : [];
    $filters   = is_array($filters ?? null) ? $filters : ['status' => '', 'search' => '', 'page' => 1];
    $total     = (int) ($leadsData['total'] ?? 0);

    $statusLabels = [
        'pendiente'  => ['label' => 'Pendiente',   'badge' => 'warning'],
        'contactado' => ['label' => 'Contactado',  'badge' => 'info'],
        'cerrado'    => ['label' => 'Cerrado',     'badge' => 'success'],
    ];
@endphp

@push('styles')
    <style>
        .wa-leads-bar {
            border-radius: 28px;
            padding: 22px 26px;
            background:
                radial-gradient(circle at top left, rgba(16,185,129,.18), transparent 34%),
                radial-gradient(circle at top right, rgba(14,165,233,.10), transparent 28%),
                linear-gradient(145deg, #0f172a 0%, #134e4a 52%, #0f766e 100%);
            color: #f8fafc;
            box-shadow: 0 18px 40px rgba(15,23,42,.16);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .wa-leads-bar__title { font-size: 26px; font-weight: 800; letter-spacing: -.03em; }
        .wa-leads-bar__sub   { font-size: 13px; color: rgba(255,255,255,.72); margin-top: 2px; }
        .wa-leads-bar__meta  { display: flex; gap: 10px; flex-wrap: wrap; }

        .wa-leads-stat {
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 14px;
            padding: 10px 18px;
            text-align: center;
            min-width: 90px;
        }
        .wa-leads-stat__val  { font-size: 22px; font-weight: 800; }
        .wa-leads-stat__lbl  { font-size: 11px; color: rgba(255,255,255,.65); }

        .wa-leads-filters {
            background: #fff;
            border: 1px solid rgba(15,23,42,.08);
            border-radius: 20px;
            padding: 16px 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(15,23,42,.05);
        }

        .wa-leads-table-wrap {
            background: #fff;
            border: 1px solid rgba(15,23,42,.08);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(15,23,42,.05);
        }

        .wa-leads-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .wa-leads-table th {
            background: #f1f5f9;
            padding: 10px 14px;
            text-align: left;
            font-weight: 700;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 1px solid rgba(15,23,42,.08);
        }
        .wa-leads-table td {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(15,23,42,.05);
            vertical-align: middle;
        }
        .wa-leads-table tr:last-child td { border-bottom: none; }
        .wa-leads-table tr:hover td { background: #f8fafc; }

        .wa-leads-number { font-weight: 700; color: #0f172a; }
        .wa-leads-name   { color: #334155; }
        .wa-leads-motivo { color: #64748b; font-size: 12px; max-width: 280px; }
        .wa-leads-ts     { color: #94a3b8; font-size: 11px; white-space: nowrap; }

        .wa-leads-status-sel {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            background: #fff;
        }

        .wa-leads-link {
            color: #0f766e;
            font-weight: 700;
            font-size: 12px;
            text-decoration: none;
        }
        .wa-leads-link:hover { text-decoration: underline; }

        .wa-leads-empty {
            padding: 48px;
            text-align: center;
            color: #94a3b8;
        }
    </style>
@endpush

@section('content')
    <section class="content">
        <div class="container-fluid" style="padding: 20px;">

            {{-- Barra superior --}}
            <div class="wa-leads-bar">
                <div>
                    <div class="wa-leads-bar__title">📋 Leads de seguimiento</div>
                    <div class="wa-leads-bar__sub">Contactos cerrados para seguimiento desde el inbox</div>
                </div>
                <div class="wa-leads-bar__meta">
                    <div class="wa-leads-stat">
                        <div class="wa-leads-stat__val">{{ $total }}</div>
                        <div class="wa-leads-stat__lbl">Total</div>
                    </div>
                    <div class="wa-leads-stat">
                        <div class="wa-leads-stat__val">{{ collect($items)->where('status','pendiente')->count() }}</div>
                        <div class="wa-leads-stat__lbl">Pendientes</div>
                    </div>
                    <div class="wa-leads-stat">
                        <div class="wa-leads-stat__val">{{ collect($items)->where('status','contactado')->count() }}</div>
                        <div class="wa-leads-stat__lbl">Contactados</div>
                    </div>
                    <div class="wa-leads-stat">
                        <div class="wa-leads-stat__val">{{ collect($items)->where('status','cerrado')->count() }}</div>
                        <div class="wa-leads-stat__lbl">Cerrados</div>
                    </div>
                </div>
            </div>

            {{-- Filtros --}}
            <form method="GET" action="/v2/whatsapp/leads" class="wa-leads-filters">
                <div>
                    <label class="form-label fw-600" style="font-size:12px;">Buscar</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                           class="form-control form-control-sm"
                           placeholder="Nombre, número, HC o cédula"
                           style="min-width:240px;">
                </div>
                <div>
                    <label class="form-label fw-600" style="font-size:12px;">Estado</label>
                    <select name="status" class="form-select form-select-sm" style="min-width:140px;">
                        <option value="">Todos</option>
                        @foreach($statusLabels as $val => $meta)
                            <option value="{{ $val }}" @selected($filters['status'] === $val)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="d-flex align-items-end gap-8">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    <a href="/v2/whatsapp/leads" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                </div>
            </form>

            {{-- Tabla --}}
            <div class="wa-leads-table-wrap">
                @if(count($items) > 0)
                    <table class="wa-leads-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Contacto</th>
                                <th>Número WA</th>
                                <th>HC / Cédula</th>
                                <th>Motivo del cierre</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Chat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $lead)
                                @php
                                    $statusMeta = $statusLabels[$lead['status']] ?? ['label' => $lead['status'], 'badge' => 'secondary'];
                                @endphp
                                <tr>
                                    <td class="text-muted" style="font-size:11px;">{{ $lead['id'] }}</td>
                                    <td>
                                        <div class="wa-leads-name fw-600">
                                            {{ $lead['patient_full_name'] ?: ($lead['display_name'] ?: '—') }}
                                        </div>
                                        @if(!empty($lead['display_name']) && $lead['display_name'] !== $lead['patient_full_name'])
                                            <div class="text-muted" style="font-size:11px;">{{ $lead['display_name'] }}</div>
                                        @endif
                                    </td>
                                    <td class="wa-leads-number">{{ $lead['wa_number'] }}</td>
                                    <td>
                                        @if(!empty($lead['hc_number']))
                                            <div style="font-size:12px;">HC {{ $lead['hc_number'] }}</div>
                                        @endif
                                        @if(!empty($lead['cedula']))
                                            <div class="text-muted" style="font-size:11px;">CI {{ $lead['cedula'] }}</div>
                                        @endif
                                        @if(empty($lead['hc_number']) && empty($lead['cedula']))
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="wa-leads-motivo">{{ $lead['motivo_baja'] }}</div>
                                    </td>
                                    <td>
                                        <select class="wa-leads-status-sel"
                                                data-lead-id="{{ $lead['id'] }}"
                                                data-current="{{ $lead['status'] }}">
                                            @foreach($statusLabels as $val => $meta)
                                                <option value="{{ $val }}" @selected($lead['status'] === $val)>
                                                    {{ $meta['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="wa-leads-ts" data-ts="{{ $lead['created_at'] ?? '' }}"></td>
                                    <td>
                                        <a href="/v2/whatsapp/chat?conversation_id={{ $lead['conversation_id'] }}"
                                           class="wa-leads-link" target="_blank">
                                            Ver chat →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="wa-leads-empty">
                        <div style="font-size:32px; margin-bottom:10px;">📋</div>
                        <div class="fw-700 mb-5">Sin leads registrados</div>
                        <div style="font-size:13px;">Los leads se generan desde el chat cuando un agente cierra una conversación para seguimiento.</div>
                    </div>
                @endif
            </div>

            {{-- Paginación simple --}}
            @if($total > $leadsData['per_page'])
                <div class="d-flex justify-content-center gap-8 mt-16">
                    @if($leadsData['page'] > 1)
                        <a href="?{{ http_build_query(array_merge($filters, ['page' => $leadsData['page'] - 1])) }}"
                           class="btn btn-outline-secondary btn-sm">← Anterior</a>
                    @endif
                    <span class="btn btn-light btn-sm disabled">
                        Página {{ $leadsData['page'] }} · {{ $total }} total
                    </span>
                    @if(($leadsData['page'] * $leadsData['per_page']) < $total)
                        <a href="?{{ http_build_query(array_merge($filters, ['page' => $leadsData['page'] + 1])) }}"
                           class="btn btn-outline-secondary btn-sm">Siguiente →</a>
                    @endif
                </div>
            @endif

        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Renderizar timestamps
            document.querySelectorAll('[data-ts]').forEach(function (el) {
                const raw = el.getAttribute('data-ts');
                if (!raw) return;
                const d = new Date(raw);
                if (Number.isNaN(d.getTime())) return;
                el.textContent = new Intl.DateTimeFormat('es-EC', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit', hour12: false,
                }).format(d).replace(',', '');
            });

            // Cambio de estado inline
            document.querySelectorAll('.wa-leads-status-sel').forEach(function (sel) {
                sel.addEventListener('change', async function () {
                    const leadId  = this.getAttribute('data-lead-id');
                    const status  = this.value;
                    const prev    = this.getAttribute('data-current');

                    try {
                        const resp = await fetch(`/v2/whatsapp/api/leads/${leadId}/status`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            },
                            body: JSON.stringify({ status }),
                        });
                        const json = await resp.json();
                        if (json.ok) {
                            this.setAttribute('data-current', status);
                        } else {
                            alert('Error: ' + (json.error ?? 'No se pudo actualizar.'));
                            this.value = prev;
                        }
                    } catch (e) {
                        alert('Error de red al actualizar el estado.');
                        this.value = prev;
                    }
                });
            });
        });
    </script>
@endpush
