@extends('layouts.medforge')

@php
    $currentUser = is_array($currentUser ?? null) ? $currentUser : ['id' => null, 'display_name' => 'Usuario'];
    $selectedFilter = (string) ($selectedFilter ?? 'all');
    $search = (string) ($search ?? '');
    $listData = is_array($listData ?? null) ? $listData : [];
    $tabCounts = is_array($tabCounts ?? null) ? $tabCounts : [];
    $agents = is_array($agents ?? null) ? $agents : [];
    $agentSummary = is_array($agentSummary ?? null) ? $agentSummary : ['agents' => [], 'totals' => []];
    $roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
    $selectedConversation = is_array($selectedConversation ?? null) ? $selectedConversation : null;
    $canOperateConversation = (bool) ($canOperateConversation ?? false);
    $presenceStatus = (string) ($presenceStatus ?? 'available');
    $selectedAgentId = isset($selectedAgentId) && $selectedAgentId !== null ? (int) $selectedAgentId : null;
    $selectedRoleId = isset($selectedRoleId) && $selectedRoleId !== null ? (int) $selectedRoleId : null;
    $quickReplies = is_array($quickReplies ?? null) ? $quickReplies : [];
    $conversationNotes = is_array($conversationNotes ?? null) ? $conversationNotes : [];
    $tabs = [
        'all' => 'Todos',
        'unread' => 'Sin leer',
        'mine' => 'Mis chats',
        'handoff' => 'En cola',
        'resolved' => 'Resueltos',
    ];
@endphp

@push('styles')
    <style>
        .wa-v2-shell {
            display: grid;
            grid-template-columns: 340px minmax(0, 1fr);
            gap: 18px;
        }

        .wa-v2-panel {
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            overflow: hidden;
        }

        .wa-v2-panel__header {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(15, 23, 42, .08);
            background: radial-gradient(circle at top left, rgba(14, 165, 233, .10), transparent 45%), #fff;
        }

        .wa-v2-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .wa-v2-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            border: 1px solid #dbe4ee;
            background: #fff;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
        }

        .wa-v2-tab.is-active {
            background: #0f766e;
            border-color: #0f766e;
            color: #fff;
        }

        .wa-v2-counter {
            min-width: 24px;
            height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            background: rgba(15, 118, 110, .12);
            color: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
        }

        .wa-v2-tab.is-active .wa-v2-counter {
            background: rgba(255, 255, 255, .18);
        }

        .wa-v2-list {
            max-height: 72vh;
            overflow: auto;
        }

        .wa-v2-conversation {
            display: block;
            padding: 14px 16px;
            border-bottom: 1px solid rgba(15, 23, 42, .08);
            color: inherit;
            text-decoration: none;
            background: #fff;
        }

        .wa-v2-conversation.is-active {
            background: linear-gradient(90deg, rgba(15, 118, 110, .10), rgba(255, 255, 255, 0));
        }

        .wa-v2-conversation:hover {
            background: #f8fafc;
        }

        .wa-v2-name {
            font-weight: 700;
            color: #0f172a;
        }

        .wa-v2-meta {
            font-size: 12px;
            color: #64748b;
        }

        .wa-v2-preview {
            font-size: 12px;
            color: #475569;
            margin-top: 6px;
        }

        .wa-v2-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: #e2e8f0;
            color: #334155;
        }

        .wa-v2-pill.is-unread {
            background: #dcfce7;
            color: #166534;
        }

        .wa-v2-pill.is-queue {
            background: #fef3c7;
            color: #92400e;
        }

        .wa-v2-chat {
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            min-height: 72vh;
            background: linear-gradient(180deg, #f8fafc 0%, #eef6f5 100%);
        }

        .wa-v2-chat__body {
            padding: 18px;
            overflow: auto;
        }

        .wa-v2-message {
            max-width: 76%;
            padding: 10px 12px;
            border-radius: 16px;
            margin-bottom: 10px;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .06);
            box-shadow: 0 8px 18px rgba(15, 23, 42, .04);
        }

        .wa-v2-message.is-outbound {
            margin-left: auto;
            background: #daf5eb;
            border-color: rgba(15, 118, 110, .18);
        }

        .wa-v2-message__meta {
            margin-top: 6px;
            font-size: 11px;
            color: #64748b;
        }

        .wa-v2-compose {
            padding: 14px 18px 18px;
            border-top: 1px solid rgba(15, 23, 42, .08);
            background: rgba(255, 255, 255, .9);
            backdrop-filter: blur(10px);
        }

        .wa-v2-tools {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
            gap: 14px;
            padding: 14px 18px 0;
        }

        .wa-v2-tool-card {
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 16px;
            background: rgba(255, 255, 255, .88);
            padding: 14px;
        }

        .wa-v2-chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .wa-v2-chip {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .wa-v2-chip:hover {
            border-color: #0f766e;
            color: #0f766e;
        }

        .wa-v2-note-list {
            display: grid;
            gap: 10px;
            max-height: 220px;
            overflow: auto;
        }

        .wa-v2-note {
            border-radius: 12px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 10px 12px;
        }

        .wa-v2-note__meta {
            font-size: 11px;
            color: #9a3412;
            margin-top: 6px;
        }

        .wa-v2-empty {
            display: grid;
            place-items: center;
            min-height: 280px;
            text-align: center;
            color: #64748b;
            padding: 24px;
        }

        .wa-v2-actions {
            display: grid;
            gap: 12px;
            margin-top: 14px;
        }

        .wa-v2-actions__row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: nowrap;
        }

        .wa-v2-actions__row--header {
            justify-content: flex-end;
            margin-left: auto;
        }

        .wa-v2-actions__group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1 1 0;
            min-width: 0;
            flex-wrap: nowrap;
        }

        .wa-v2-actions .form-select,
        .wa-v2-actions .form-control {
            min-width: 0;
        }

        #wa-v2-transfer-user,
        #wa-v2-queue-role {
            flex: 0 0 220px;
            min-width: 220px;
        }

        #wa-v2-transfer-note,
        #wa-v2-queue-note {
            flex: 1 1 auto;
            min-width: 0;
        }

        .wa-v2-actions__group .btn {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        .wa-v2-icon-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            line-height: 1;
        }

        .wa-v2-icon-label i {
            font-size: 14px;
            line-height: 1;
        }

        @media (max-width: 991px) {
            .wa-v2-shell {
                grid-template-columns: 1fr;
            }

            .wa-v2-tools {
                grid-template-columns: 1fr;
            }

            .wa-v2-list,
            .wa-v2-chat {
                max-height: none;
                min-height: auto;
            }

            .wa-v2-actions__row {
                flex-wrap: wrap;
            }
            .wa-v2-actions__row--header {
                width: 100%;
                justify-content: flex-start;
                margin-left: 0;
            }

            .wa-v2-actions__group {
                flex: 1 1 100%;
                flex-wrap: wrap;
            }

            #wa-v2-transfer-user,
            #wa-v2-queue-role,
            #wa-v2-transfer-note,
            #wa-v2-queue-note {
                flex: 1 1 100%;
                min-width: 0;
            }
        }
    </style>
@endpush

@section('content')
    <section class="content">
        <div class="row mb-15">
            <div class="col-12">
                <div class="box mb-0">
                    <div class="box-body d-flex flex-wrap justify-content-between align-items-center gap-15">
                        <div>
                            <h2 class="mb-5">Inbox operativo</h2>
                        </div>
                        <div class="text-end">
                            <div class="fw-700">{{ $currentUser['display_name'] ?? 'Usuario' }}</div>
                            <div class="text-muted"
                                 style="font-size:12px;">{{ $canSupervise ? 'Modo supervisor' : 'Vista de agente' }}</div>
                        </div>
                    </div>
                    <div
                        class="box-footer bg-transparent d-flex flex-wrap gap-10 align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-10">
                            <label for="wa-v2-presence" class="mb-0 text-muted"
                                   style="font-size:12px;">Presencia</label>
                            <select id="wa-v2-presence" class="form-select form-select-sm" style="min-width: 160px;">
                                <option value="available" {{ $presenceStatus === 'available' ? 'selected' : '' }}>
                                    Disponible
                                </option>
                                <option value="away" {{ $presenceStatus === 'away' ? 'selected' : '' }}>Ausente</option>
                                <option value="offline" {{ $presenceStatus === 'offline' ? 'selected' : '' }}>Offline
                                </option>
                            </select>
                        </div>
                        @if($canSupervise)
                            <button type="button" class="btn btn-outline-warning btn-sm" id="wa-v2-requeue-expired">
                                Reencolar vencidos
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="wa-v2-shell">
            <div class="wa-v2-panel">
                <div class="wa-v2-panel__header">
                    <form method="GET" action="/v2/whatsapp/chat" class="mb-15">
                        <input type="hidden" name="filter" value="{{ $selectedFilter }}">
                        @if($selectedAgentId !== null)
                            <input type="hidden" name="agent_id" value="{{ $selectedAgentId }}">
                        @endif
                        @if($selectedRoleId !== null)
                            <input type="hidden" name="role_id" value="{{ $selectedRoleId }}">
                        @endif
                        <div class="input-group">
                            <input
                                type="text"
                                class="form-control"
                                name="search"
                                value="{{ $search }}"
                                placeholder="Buscar por nombre, HC, número o mensaje">
                            <button type="submit" class="btn btn-primary">Buscar</button>
                        </div>
                    </form>

                    @if($canSupervise)
                        @php
                            $summaryTotals = is_array($agentSummary['totals'] ?? null) ? $agentSummary['totals'] : [];
                        @endphp
                        <div class="row g-10 mb-15">
                            <div class="col-sm-6 col-xl-3">
                                <div class="wa-v2-panel p-10" style="border-radius:14px;">
                                    <div class="text-muted" style="font-size:11px;">Cola abierta</div>
                                    <div class="fw-700"
                                         style="font-size:22px;">{{ (int) ($summaryTotals['queued_open_count'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="wa-v2-panel p-10" style="border-radius:14px;">
                                    <div class="text-muted" style="font-size:11px;">Chats asignados</div>
                                    <div class="fw-700"
                                         style="font-size:22px;">{{ (int) ($summaryTotals['assigned_open_count'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="wa-v2-panel p-10" style="border-radius:14px;">
                                    <div class="text-muted" style="font-size:11px;">Chats con unread</div>
                                    <div class="fw-700"
                                         style="font-size:22px;">{{ (int) ($summaryTotals['unread_open_count'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="wa-v2-panel p-10" style="border-radius:14px;">
                                    <div class="text-muted" style="font-size:11px;">TTL por vencer</div>
                                    <div class="fw-700"
                                         style="font-size:22px;">{{ (int) ($summaryTotals['expiring_soon_count'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>

                        <form method="GET" action="/v2/whatsapp/chat" class="row g-10 mb-15">
                            <input type="hidden" name="filter" value="{{ $selectedFilter }}">
                            <input type="hidden" name="search" value="{{ $search }}">
                            <div class="col-12">
                                <div class="text-uppercase text-muted mb-5"
                                     style="font-size:11px; letter-spacing:.08em;">Filtros supervisor
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <select name="agent_id" class="form-select form-select-sm">
                                    <option value="">Todos los agentes</option>
                                    <option value="0" {{ $selectedAgentId === 0 ? 'selected' : '' }}>Sin asignar
                                    </option>
                                    @foreach($agents as $agent)
                                        <option
                                            value="{{ $agent['id'] }}" {{ $selectedAgentId === (int) $agent['id'] ? 'selected' : '' }}>
                                            {{ $agent['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <select name="role_id" class="form-select form-select-sm">
                                    <option value="">Todos los roles</option>
                                    @foreach($roleOptions as $role)
                                        <option
                                            value="{{ $role['id'] }}" {{ $selectedRoleId === (int) $role['id'] ? 'selected' : '' }}>
                                            {{ $role['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 d-flex gap-8">
                                <button type="submit" class="btn btn-outline-primary btn-sm">Aplicar</button>
                                <a href="{{ '/v2/whatsapp/chat?' . http_build_query(['filter' => $selectedFilter, 'search' => $search]) }}"
                                   class="btn btn-outline-secondary btn-sm">Limpiar</a>
                            </div>
                        </form>

                        @if(!empty($agentSummary['agents']))
                            <div class="mb-15">
                                <div class="text-uppercase text-muted mb-5"
                                     style="font-size:11px; letter-spacing:.08em;">Carga por agente
                                </div>
                                <div class="d-grid gap-8">
                                    @foreach(array_slice($agentSummary['agents'], 0, 4) as $agent)
                                        <div class="wa-v2-panel p-10" style="border-radius:14px;">
                                            <div class="d-flex justify-content-between align-items-center gap-10">
                                                <div>
                                                    <div class="fw-700">{{ $agent['name'] }}</div>
                                                    <div class="text-muted"
                                                         style="font-size:12px;">{{ $agent['role_name'] ?: 'Sin rol' }}
                                                        · {{ $agent['presence_status'] }}</div>
                                                </div>
                                                <div class="text-end" style="font-size:12px;">
                                                    <div>{{ (int) ($agent['assigned_open_count'] ?? 0) }}asignados
                                                    </div>
                                                    <div>{{ (int) ($agent['unread_open_count'] ?? 0) }} con unread</div>
                                                    <div>{{ (int) ($agent['expiring_soon_count'] ?? 0) }} por vencer
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                    <div class="wa-v2-tabs">
                        @foreach($tabs as $key => $label)
                            @php
                                $tabIcons = [
                                    'all' => 'mdi mdi-message-text-outline',
                                    'unread' => 'mdi mdi-bell-outline',
                                    'mine' => 'mdi mdi-account-outline',
                                    'handoff' => 'mdi mdi-tray-arrow-down',
                                    'resolved' => 'mdi mdi-check-circle-outline',
                                ];
                                $icon = $tabIcons[$key] ?? 'mdi mdi-circle-small';
                            @endphp
                            <a
                                href="{{ '/v2/whatsapp/chat?' . http_build_query(array_filter(['filter' => $key, 'search' => $search, 'agent_id' => $selectedAgentId, 'role_id' => $selectedRoleId], static fn ($value) => $value !== null && $value !== '')) }}"
                                class="wa-v2-tab {{ $selectedFilter === $key ? 'is-active' : '' }}">
                                <span class="wa-v2-icon-label">
                                    <i class="{{ $icon }}"></i>
                                </span>
                                <span class="wa-v2-counter">{{ (int) ($tabCounts[$key] ?? 0) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="wa-v2-list">
                    @forelse($listData as $conversation)
                        @php
                            $isActive = (int) ($selectedConversation['id'] ?? 0) === (int) $conversation['id'];
                        @endphp
                        <a
                            href="{{ '/v2/whatsapp/chat?' . http_build_query(array_filter(['filter' => $selectedFilter, 'search' => $search, 'agent_id' => $selectedAgentId, 'role_id' => $selectedRoleId, 'conversation' => $conversation['id']], static fn ($value) => $value !== null && $value !== '')) }}"
                            class="wa-v2-conversation {{ $isActive ? 'is-active' : '' }}">
                            <div class="d-flex justify-content-between gap-10">
                                <div
                                    class="wa-v2-name">{{ $conversation['display_name'] ?: $conversation['wa_number'] }}</div>
                                <div
                                    class="wa-v2-meta">{{ $conversation['unread_count'] > 0 ? $conversation['unread_count'] . ' nuevos' : '' }}</div>
                            </div>
                            <div class="wa-v2-meta mt-5">
                                {{ $conversation['patient_full_name'] ?: 'Sin paciente vinculado' }}
                                @if(!empty($conversation['patient_hc_number']))
                                    · HC {{ $conversation['patient_hc_number'] }}
                                @endif
                            </div>
                            <div
                                class="wa-v2-preview">{{ $conversation['last_message_preview'] ?: '[' . ($conversation['last_message_type'] ?: 'mensaje') . ']' }}</div>
                            <div class="d-flex flex-wrap gap-8 mt-10">
                                @if((int) $conversation['unread_count'] > 0)
                                    <span class="wa-v2-pill is-unread"><i class="mdi mdi-bell"></i> Sin leer</span>
                                @endif
                                @if(!empty($conversation['needs_human']))
                                    <span class="wa-v2-pill is-queue"><i class="mdi mdi-tray-arrow-down"></i> En cola</span>
                                @else
                                    <span class="wa-v2-pill"><i class="mdi mdi-check"></i> Resuelto</span>
                                @endif
                                <span class="wa-v2-pill"><i class="mdi mdi-tag-outline"></i> {{ $conversation['ownership_label'] ?? 'Sin ownership' }}</span>
                                @if(!empty($conversation['assigned_role_name']))
                                    <span class="wa-v2-pill"><i class="mdi mdi-account-group-outline"></i> {{ $conversation['assigned_role_name'] }}</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="p-20 text-center text-muted">No hay conversaciones para este filtro.</div>
                    @endforelse
                </div>
            </div>

            <div class="wa-v2-panel wa-v2-chat">
                @if($selectedConversation)
                    @php
                        $selectedAssignedUserId = (int) ($selectedConversation['assigned_user_id'] ?? 0);
                        $currentUserId = (int) ($currentUser['id'] ?? 0);
                        $canReplyHere = $selectedAssignedUserId > 0 && $selectedAssignedUserId === $currentUserId;
                        $waLink = 'https://wa.me/' . preg_replace('/\D+/', '', (string) ($selectedConversation['wa_number'] ?? ''));
                    @endphp
                    <div class="wa-v2-panel__header">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-12 flex-grow-1">
                            <div>
                                <h4 class="mb-5">{{ $selectedConversation['display_name'] ?: $selectedConversation['wa_number'] }}</h4>
                                <div class="text-muted">{{ $selectedConversation['wa_number'] }}</div>
                                <div class="text-muted mt-5">
                                    {{ $selectedConversation['patient_full_name'] ?: 'Sin paciente vinculado' }}
                                    @if(!empty($selectedConversation['patient_hc_number']))
                                        · HC {{ $selectedConversation['patient_hc_number'] }}
                                    @endif
                                </div>
                            </div>

                            <div class="wa-v2-actions__row wa-v2-actions__row--header">
                                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn btn-success-light">
                                    <span class="wa-v2-icon-label">
                                        <i class="mdi mdi-whatsapp"></i>
                                    </span>
                                </a>

                                @if($canOperateConversation)
                                    <button
                                        type="button"
                                        class="btn btn-primary"
                                        data-wa-action="assign-self"
                                        data-conversation-id="{{ $selectedConversation['id'] }}">
                                        <span class="wa-v2-icon-label">
                                            <i class="mdi mdi-account-check-outline"></i>
                                        </span>
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-danger"
                                        data-wa-action="close"
                                        data-conversation-id="{{ $selectedConversation['id'] }}">
                                        <span class="wa-v2-icon-label">
                                            <i class="mdi mdi-close-circle-outline"></i>
                                        </span>
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-8 mt-10">
                            @if(!empty($selectedConversation['needs_human']))
                                <span class="wa-v2-pill is-queue"><i class="mdi mdi-tray-arrow-down"></i> En cola</span>
                            @else
                                <span class="wa-v2-pill"><i class="mdi mdi-check"></i> Resuelto</span>
                            @endif
                            <span class="wa-v2-pill"><i class="mdi mdi-tag-outline"></i> {{ $selectedConversation['ownership_label'] ?? 'Sin ownership' }}</span>
                            @if(!empty($selectedConversation['assigned_role_name']))
                                <span class="wa-v2-pill"><i class="mdi mdi-account-group-outline"></i> {{ $selectedConversation['assigned_role_name'] }}</span>
                            @endif
                        </div>

                        <div class="wa-v2-actions">
                            @if($canOperateConversation)
                                <div class="wa-v2-actions__row">
                                    <div class="wa-v2-actions__group">
                                        <select class="form-select" id="wa-v2-transfer-user">
                                            <option value="">Transferir a...</option>
                                            @foreach($agents as $agent)
                                                <option value="{{ $agent['id'] }}">
                                                    {{ $agent['name'] }} · {{ $agent['presence_status'] }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <input type="text" class="form-control" id="wa-v2-transfer-note"
                                               placeholder="Nota de transferencia (opcional)">

                                        <button
                                            type="button"
                                            class="btn btn-outline-primary"
                                            data-wa-action="transfer"
                                            data-conversation-id="{{ $selectedConversation['id'] }}">
                                            <span class="wa-v2-icon-label">
                                                <i class="mdi mdi-swap-horizontal"></i>
                                                <span>Transferir</span>
                                            </span>
                                        </button>
                                    </div>

                                    <div class="wa-v2-actions__group">
                                        <select class="form-select" id="wa-v2-queue-role">
                                            <option value="">Enviar a cola de rol...</option>
                                            @foreach($roleOptions as $role)
                                                <option value="{{ $role['id'] }}">{{ $role['name'] }}</option>
                                            @endforeach
                                        </select>

                                        <input type="text" class="form-control" id="wa-v2-queue-note"
                                               placeholder="Nota para la cola (opcional)">

                                        <button
                                            type="button"
                                            class="btn btn-outline-warning"
                                            data-wa-action="queue-role"
                                            data-conversation-id="{{ $selectedConversation['id'] }}">
                                            <span class="wa-v2-icon-label">
                                                <i class="mdi mdi-tray-arrow-down"></i>
                                                <span>Cola</span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="wa-v2-chat__body">
                        @foreach(($selectedConversation['messages'] ?? []) as $message)
                            <div
                                class="wa-v2-message {{ ($message['direction'] ?? '') === 'outbound' ? 'is-outbound' : '' }}">
                                <div>{{ $message['body'] ?: '[' . ($message['message_type'] ?? 'mensaje') . ']' }}</div>
                                <div class="wa-v2-message__meta">
                                    {{ $message['direction'] === 'outbound' ? 'Salida' : 'Entrada' }}
                                    · {{ $message['message_type'] ?? 'text' }}
                                    @if(!empty($message['status']))
                                        · {{ $message['status'] }}
                                    @endif
                                    @if(!empty($message['message_timestamp']))
                                        · {{ \Illuminate\Support\Carbon::parse($message['message_timestamp'])->format('d/m H:i') }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="wa-v2-tools">
                        <div class="wa-v2-tool-card">
                            <div class="d-flex justify-content-between align-items-center gap-10 mb-10">
                                <div>
                                    <div class="fw-700">Respuestas rápidas</div>
                                    <div class="text-muted" style="font-size:12px;">Pega una base en el composer sin saltarte la revisión manual.</div>
                                </div>
                            </div>
                            @if(!empty($quickReplies))
                                <div class="wa-v2-chip-list mb-12">
                                    @foreach($quickReplies as $reply)
                                        <button
                                            type="button"
                                            class="wa-v2-chip"
                                            data-wa-quick-reply="{{ e($reply['body'] ?? '') }}"
                                            title="{{ $reply['shortcut'] ? '/' . $reply['shortcut'] : ($reply['title'] ?? 'Respuesta rápida') }}">
                                            {{ $reply['title'] ?? 'Respuesta' }}
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted mb-10" style="font-size:12px;">Todavía no hay respuestas rápidas cargadas.</div>
                            @endif

                            <form id="wa-v2-quick-reply-form" class="d-grid gap-8">
                                <div class="row g-8">
                                    <div class="col-md-4">
                                        <input type="text" class="form-control form-control-sm" id="wa-v2-quick-title" placeholder="Título">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control form-control-sm" id="wa-v2-quick-shortcut" placeholder="/atajo">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" class="form-control form-control-sm" id="wa-v2-quick-body" placeholder="Texto reusable">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Guardar respuesta rápida</button>
                                </div>
                            </form>
                        </div>

                        <div class="wa-v2-tool-card">
                            <div class="fw-700 mb-10">Notas internas</div>
                            <div class="wa-v2-note-list mb-10">
                                @forelse($conversationNotes as $note)
                                    <div class="wa-v2-note">
                                        <div>{{ $note['body'] ?? '' }}</div>
                                        <div class="wa-v2-note__meta">
                                            {{ $note['author_name'] ?? 'Usuario' }}
                                            @if(!empty($note['created_at']))
                                                · {{ \Illuminate\Support\Carbon::parse($note['created_at'])->format('d/m H:i') }}
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted" style="font-size:12px;">Sin notas internas en esta conversación.</div>
                                @endforelse
                            </div>
                            <form id="wa-v2-note-form" data-conversation-id="{{ $selectedConversation['id'] }}">
                                <div class="input-group">
                                    <textarea class="form-control" id="wa-v2-note-input" rows="2" placeholder="Agregar nota interna para el equipo"></textarea>
                                    <button type="submit" class="btn btn-outline-warning">Guardar nota</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="wa-v2-compose">
                        <form id="wa-v2-send-form" data-conversation-id="{{ $selectedConversation['id'] }}">
                            <div class="mb-10 text-muted" style="font-size:12px;">
                                Esta fase mantiene reglas legacy: si no hubo mensaje entrante previo se exige plantilla,
                                y si el chat no está tomado no se puede responder.
                            </div>
                            <div class="input-group">
                                <textarea class="form-control" id="wa-v2-message-input" rows="2"
                                          placeholder="Escribe una respuesta manual" {{ $canReplyHere ? '' : 'disabled' }}></textarea>
                                <button type="submit" class="btn btn-primary" {{ $canReplyHere ? '' : 'disabled' }}>
                                    Enviar
                                </button>
                            </div>
                            <div id="wa-v2-send-feedback" class="mt-10 text-muted" style="font-size:12px;"></div>
                        </form>
                    </div>
                @else
                    <div class="wa-v2-empty">
                        <div>
                            <h4 class="mb-10">Selecciona una conversación</h4>
                            <div>La vista v2 ya soporta filtros operativos, detalle y envío manual sobre los endpoints
                                Laravel.
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('wa-v2-send-form');
            const textarea = document.getElementById('wa-v2-message-input');
            const feedback = document.getElementById('wa-v2-send-feedback');
            const transferUser = document.getElementById('wa-v2-transfer-user');
            const transferNote = document.getElementById('wa-v2-transfer-note');
            const queueRole = document.getElementById('wa-v2-queue-role');
            const queueNote = document.getElementById('wa-v2-queue-note');
            const quickReplyForm = document.getElementById('wa-v2-quick-reply-form');
            const quickReplyTitle = document.getElementById('wa-v2-quick-title');
            const quickReplyShortcut = document.getElementById('wa-v2-quick-shortcut');
            const quickReplyBody = document.getElementById('wa-v2-quick-body');
            const noteForm = document.getElementById('wa-v2-note-form');
            const noteInput = document.getElementById('wa-v2-note-input');
            const presenceSelect = document.getElementById('wa-v2-presence');
            const requeueExpiredButton = document.getElementById('wa-v2-requeue-expired');
            let requestInFlight = false;

            const setFeedback = function (message, tone) {
                if (!feedback) {
                    return;
                }

                feedback.textContent = message;
                feedback.className = `mt-10 text-${tone}`;
            };

            if (presenceSelect) {
                presenceSelect.addEventListener('change', async function () {
                    try {
                        await fetch('/v2/whatsapp/api/presence', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({status: presenceSelect.value})
                        });
                    } catch (error) {
                        setFeedback('No fue posible actualizar la presencia.', 'danger');
                    }
                });
            }

            if (requeueExpiredButton) {
                requeueExpiredButton.addEventListener('click', async function () {
                    try {
                        await postAction(
                            '/v2/whatsapp/api/handoffs/requeue-expired',
                            {},
                            'Reencolando handoffs vencidos...',
                            'Handoffs vencidos reencolados. Recargando...'
                        );
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible reencolar handoffs.', 'danger');
                    }
                });
            }

            const postAction = async function (url, payload, pendingMessage, successMessage) {
                requestInFlight = true;
                setFeedback(pendingMessage, 'muted');

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload || {})
                    });

                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                        throw new Error(data.error || 'No fue posible completar la acción.');
                    }

                    setFeedback(successMessage, 'success');
                    window.location.reload();
                } finally {
                    requestInFlight = false;
                }
            };

            const canAutoReload = function () {
                if (document.hidden || requestInFlight) {
                    return false;
                }

                if (!textarea) {
                    return true;
                }

                if (document.activeElement === textarea) {
                    return false;
                }

                return ((textarea.value || '').trim() === '');
            };

            if (document.getElementById('wa-v2-send-form')) {
                window.setInterval(function () {
                    if (!canAutoReload()) {
                        return;
                    }

                    window.location.reload();
                }, 15000);
            }

            if (form) {
                const conversationId = form.getAttribute('data-conversation-id');

                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const message = (textarea.value || '').trim();
                    if (!message) {
                        setFeedback('Escribe un mensaje antes de enviar.', 'danger');
                        return;
                    }

                    try {
                        await postAction(
                            `/v2/whatsapp/api/conversations/${conversationId}/messages`,
                            {message},
                            'Enviando...',
                            'Mensaje enviado. Recargando conversación...'
                        );
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible enviar el mensaje.', 'danger');
                    }
                });
            }

            document.querySelectorAll('[data-wa-quick-reply]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (!textarea) {
                        return;
                    }

                    const snippet = button.getAttribute('data-wa-quick-reply') || '';
                    const current = (textarea.value || '').trim();
                    textarea.value = current ? `${current}\n${snippet}` : snippet;
                    textarea.focus();
                });
            });

            if (quickReplyForm) {
                quickReplyForm.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    try {
                        await postAction(
                            '/v2/whatsapp/api/quick-replies',
                            {
                                title: quickReplyTitle ? quickReplyTitle.value : '',
                                shortcut: quickReplyShortcut ? quickReplyShortcut.value : '',
                                body: quickReplyBody ? quickReplyBody.value : ''
                            },
                            'Guardando respuesta rápida...',
                            'Respuesta rápida guardada. Recargando...'
                        );
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible guardar la respuesta rápida.', 'danger');
                    }
                });
            }

            if (noteForm) {
                const conversationId = noteForm.getAttribute('data-conversation-id');

                noteForm.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    try {
                        await postAction(
                            `/v2/whatsapp/api/conversations/${conversationId}/notes`,
                            {body: noteInput ? noteInput.value : ''},
                            'Guardando nota interna...',
                            'Nota interna guardada. Recargando...'
                        );
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible guardar la nota interna.', 'danger');
                    }
                });
            }

            document.querySelectorAll('[data-wa-action]').forEach(function (button) {
                button.addEventListener('click', async function () {
                    const action = button.getAttribute('data-wa-action');
                    const conversationId = button.getAttribute('data-conversation-id');

                    try {
                        if (action === 'assign-self') {
                            await postAction(
                                `/v2/whatsapp/api/conversations/${conversationId}/assign`,
                                {},
                                'Tomando conversación...',
                                'Conversación tomada. Recargando...'
                            );
                            return;
                        }

                        if (action === 'transfer') {
                            const userId = transferUser ? transferUser.value : '';
                            if (!userId) {
                                throw new Error('Selecciona un agente para transferir.');
                            }

                            await postAction(
                                `/v2/whatsapp/api/conversations/${conversationId}/transfer`,
                                {
                                    user_id: Number(userId),
                                    note: transferNote ? transferNote.value : ''
                                },
                                'Transfiriendo conversación...',
                                'Conversación transferida. Recargando...'
                            );
                            return;
                        }

                        if (action === 'queue-role') {
                            const roleId = queueRole ? queueRole.value : '';
                            if (!roleId) {
                                throw new Error('Selecciona un rol para enviar la conversación a la cola.');
                            }

                            await postAction(
                                `/v2/whatsapp/api/conversations/${conversationId}/queue-by-role`,
                                {
                                    role_id: Number(roleId),
                                    note: queueNote ? queueNote.value : ''
                                },
                                'Enviando conversación a la cola...',
                                'Conversación enviada a la cola. Recargando...'
                            );
                            return;
                        }

                        if (action === 'close') {
                            await postAction(
                                `/v2/whatsapp/api/conversations/${conversationId}/close`,
                                {},
                                'Cerrando conversación...',
                                'Conversación cerrada. Recargando...'
                            );
                        }
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible completar la acción.', 'danger');
                    }
                });
            });
        });
    </script>
@endpush
