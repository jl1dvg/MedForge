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
    $dateFrom = (string) ($dateFrom ?? '');
    $dateTo = (string) ($dateTo ?? '');
    $quickReplies = is_array($quickReplies ?? null) ? $quickReplies : [];
    $conversationNotes = is_array($conversationNotes ?? null) ? $conversationNotes : [];
    $templateOptions = is_array($templateOptions ?? null) ? $templateOptions : [];

    // WhatsApp markdown → HTML formatter (server-side)
    $formatWaBody = static function (string $text): string {
        $safe = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safe = preg_replace('/\*([^*\r\n]+)\*/', '<strong>$1</strong>', $safe) ?? $safe;
        $safe = preg_replace('/_([^_\r\n]+)_/', '<em>$1</em>', $safe) ?? $safe;
        $safe = preg_replace('/~([^~\r\n]+)~/', '<del>$1</del>', $safe) ?? $safe;
        $safe = preg_replace('/`([^`\r\n]+)`/', '<code>$1</code>', $safe) ?? $safe;
        $safe = str_replace(["\r\n", "\r", "\n"], '<br>', $safe);
        return $safe;
    };

    $tabs = [
        'requires_attention' => 'Requieren atención',
        'mine' => 'Mis chats',
        'in_progress' => 'En gestión',
        'waiting_patient' => 'Esperando paciente',
        'scheduled' => 'Agendados',
        'closed' => 'Cerrados',
    ];
    $advancedTabs = [
        'captacion' => 'Captación',
        'operacion' => 'Operación',
        'informacion' => 'Información',
        'critical_backlog' => 'Backlog >24h',
        'unread' => 'Sin leer',
        'window_open' => '24h abierta',
        'needs_template' => 'Plantilla',
        'all' => 'Todos',
        'resolved' => 'Resueltos',
    ];
    $tabDescriptions = [
        'requires_attention' => 'Requieren atención — Conversaciones sin agente o con acción humana pendiente.',
        'mine'             => 'Mis chats — Conversaciones asignadas actualmente a ti.',
        'in_progress'      => 'En gestión — Conversaciones asignadas donde el paciente escribió último.',
        'waiting_patient'  => 'Esperando paciente — Ya respondimos y esperamos respuesta del paciente.',
        'scheduled'        => 'Agendados — Conversaciones con cita registrada.',
        'closed'           => 'Cerrados — Resueltos, seguimientos cerrados y otros cierres.',
        'handoff'          => 'Pendientes — Alias anterior para solicitudes sin agente.',
        'captacion'        => 'Captación — Pacientes nuevos o consultas de primer contacto.',
        'operacion'        => 'Operación — Pacientes en proceso quirúrgico o de seguimiento post-consulta.',
        'informacion'      => 'Información — Consultas generales que no requieren proceso activo.',
        'critical_backlog' => 'Backlog >24h — Sin respuesta humana por más de 24 horas. Atención urgente.',
        'unread'           => 'Sin leer — Mensajes entrantes que aún no has revisado.',
        'window_open'      => '24h abierta — El paciente escribió en las últimas 24h. Puedes responder libremente sin plantilla.',
        'needs_template'   => 'Requiere Plantilla — La ventana de 24h expiró. Solo puedes iniciar con una plantilla aprobada de WhatsApp.',
        'resolved'         => 'Resueltos — Conversaciones cerradas por el agente.',
        'all'              => 'Todos — Todas las conversaciones sin filtro.',
    ];
@endphp

@push('styles')
    <style>
        :root {
            --wa-bg: #edf5f1;
            --wa-surface: #ffffff;
            --wa-surface-soft: #f6fbf8;
            --wa-border: rgba(15, 23, 42, .08);
            --wa-text: #0f172a;
            --wa-muted: #64748b;
            --wa-accent: #0f766e;
            --wa-accent-soft: #ccfbf1;
            --wa-danger: #dc2626;
            --wa-shadow: 0 22px 48px rgba(15, 23, 42, .08);
        }

        .content {
            background: radial-gradient(circle at top left, rgba(16, 185, 129, .08), transparent 26%),
            radial-gradient(circle at top right, rgba(14, 165, 233, .08), transparent 22%),
            linear-gradient(180deg, #eef5f1 0%, #ecf2f7 100%);
        }

        .wa-v2-pagebar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 20px;
            border-radius: 28px;
            background: linear-gradient(135deg, #0f172a 0%, #134e4a 52%, #0f766e 100%);
            color: #fff;
            box-shadow: var(--wa-shadow);
            position: relative;
            overflow: hidden;
        }

        .wa-v2-pagebar::after {
            content: "";
            position: absolute;
            right: -70px;
            bottom: -110px;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(255, 255, 255, .18) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
        }

        .wa-v2-pagebar__title {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -.03em;
            margin-bottom: 4px;
        }

        .wa-v2-pagebar__subtitle {
            max-width: 720px;
            color: rgba(255, 255, 255, .76);
        }

        .wa-v2-pagebar__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        .wa-v2-hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .16);
            backdrop-filter: blur(10px);
            font-size: 12px;
            font-weight: 700;
            color: #fff;
        }

        .wa-v2-pagebar-control {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px 6px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .16);
            backdrop-filter: blur(10px);
            color: #fff;
        }

        .wa-v2-pagebar-control label {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            color: rgba(255, 255, 255, .82);
        }

        .wa-v2-pagebar-control .form-select {
            min-width: 142px;
            height: 30px;
            border: 0;
            border-radius: 999px;
            background-color: rgba(255, 255, 255, .94);
            color: #0f172a;
            font-size: 12px;
            font-weight: 700;
            padding-top: 3px;
            padding-bottom: 3px;
        }

        .wa-v2-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .52);
            backdrop-filter: blur(3px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1200;
        }

        .wa-v2-modal-backdrop.is-open {
            display: flex;
        }

        .wa-v2-modal {
            width: min(720px, 100%);
            max-height: min(88vh, 860px);
            overflow: auto;
            border-radius: 24px;
            background: #fff;
            border: 1px solid var(--wa-border);
            box-shadow: 0 28px 70px rgba(15, 23, 42, .22);
        }

        .wa-v2-modal__header,
        .wa-v2-modal__body,
        .wa-v2-modal__footer {
            padding: 18px 20px;
        }

        .wa-v2-modal__header,
        .wa-v2-modal__footer {
            border-bottom: 1px solid var(--wa-border);
        }

        .wa-v2-modal__footer {
            border-top: 1px solid var(--wa-border);
            border-bottom: 0;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }

        .wa-v2-picker-results {
            display: grid;
            gap: 10px;
            margin-top: 12px;
            max-height: 220px;
            overflow: auto;
        }

        .wa-v2-picker-card {
            border: 1px solid var(--wa-border);
            border-radius: 16px;
            padding: 12px 14px;
            background: var(--wa-surface-soft);
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
        }

        .wa-v2-picker-card.is-active {
            border-color: rgba(15, 118, 110, .55);
            box-shadow: 0 0 0 2px rgba(15, 118, 110, .12);
        }

        .wa-v2-picker-card__source {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(15, 118, 110, .08);
            color: var(--wa-accent);
            font-size: 11px;
            font-weight: 700;
        }

        .wa-v2-template-preview {
            display: grid;
            gap: 8px;
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px solid rgba(15, 118, 110, .16);
            background: linear-gradient(180deg, #f0fdfa 0%, #ffffff 100%);
        }

        .wa-v2-template-preview__label {
            font-size: 11px;
            font-weight: 800;
            color: #0f766e;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .wa-v2-template-preview__body {
            white-space: pre-wrap;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.45;
        }

        .wa-v2-shell {
            display: grid;
            grid-template-columns: 390px minmax(0, 1fr) 320px;
            gap: 20px;
            align-items: stretch;
            height: calc(100vh - 0px);
            max-height: calc(100vh - 0px);
            overflow: hidden;
        }

        .wa-v2-panel {
            border: 1px solid var(--wa-border);
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(255, 255, 255, .96) 0%, rgba(246, 251, 248, .98) 100%);
            overflow: hidden;
            box-shadow: var(--wa-shadow);
            display: flex;
            flex-direction: column;
            min-height: 0;
            height: 100%;
        }

        .wa-v2-panel__header {
            padding: 10px 10px;
            border-bottom: 1px solid var(--wa-border);
            background: radial-gradient(circle at top left, rgba(16, 185, 129, .16), transparent 40%),
            radial-gradient(circle at top right, rgba(14, 165, 233, .10), transparent 42%),
            #fff;
        }

        .wa-v2-sideheading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .wa-v2-sideheading__title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -.03em;
            color: var(--wa-text);
        }

        .wa-v2-sideheading__meta {
            color: var(--wa-muted);
            font-size: 12px;
        }

        .wa-v2-searchbar .input-group {
            overflow: hidden;
            border-radius: 18px;
            border: 1px solid #d7e3dd;
            background: rgba(255, 255, 255, .9);
        }

        .wa-v2-searchbar .form-control,
        .wa-v2-searchbar .btn {
            border: 0;
        }

        .wa-v2-statgrid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .wa-v2-statcard {
            padding: 12px 14px;
            border-radius: 18px;
            background: rgba(255, 255, 255, .82);
            border: 1px solid rgba(15, 23, 42, .06);
        }

        .wa-v2-statcard__value {
            font-size: 24px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -.03em;
            color: var(--wa-text);
        }

        .wa-v2-statcard__label {
            margin-top: 6px;
            font-size: 11px;
            color: var(--wa-muted);
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .wa-v2-tabs-shell {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 8px;
            align-items: center;
        }

        .wa-v2-tabs-nav {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            border: 1px solid #dbe4ee;
            background: rgba(255, 255, 255, .92);
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .06);
            transition: opacity .14s ease, transform .14s ease, box-shadow .14s ease;
        }

        .wa-v2-tabs-nav:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 22px rgba(15, 23, 42, .10);
        }

        .wa-v2-tabs-nav:disabled {
            opacity: .38;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .wa-v2-tabs-viewport {
            min-width: 0;
            overflow: hidden;
        }

        .wa-v2-tabs {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            overflow: auto;
            padding-bottom: 2px;
            scrollbar-width: none;
            scroll-behavior: smooth;
        }

        .wa-v2-tabs::-webkit-scrollbar {
            display: none;
        }

        .wa-v2-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid #dbe4ee;
            background: rgba(255, 255, 255, .86);
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .wa-v2-tab.is-active {
            background: var(--wa-accent);
            border-color: var(--wa-accent);
            color: #fff;
            box-shadow: 0 12px 24px rgba(15, 118, 110, .24);
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

        @media (max-width: 767.98px) {
            .wa-v2-tabs-shell {
                grid-template-columns: minmax(0, 1fr);
            }

            .wa-v2-tabs-nav {
                display: none;
            }

            .wa-v2-tabs-viewport {
                overflow: auto;
            }
        }

        .wa-v2-list {
            flex: 1 1 auto;
            min-height: 0;
            height: 100%;
            overflow: auto;
            padding: 10px;
            background: linear-gradient(180deg, rgba(241, 245, 249, .46) 0%, rgba(255, 255, 255, .24) 100%);
        }

        .wa-v2-conversation {
            display: block;
            padding: 16px;
            margin-bottom: 10px;
            border-radius: 20px;
            border: 1px solid rgba(15, 23, 42, .06);
            color: inherit;
            text-decoration: none;
            background: rgba(255, 255, 255, .94);
            transition: transform .14s ease, box-shadow .14s ease, border-color .14s ease;
            position: relative;
        }

        .wa-v2-conversation::before {
            content: "";
            position: absolute;
            left: 0;
            top: 10px;
            bottom: 10px;
            width: 4px;
            border-radius: 999px;
            background: transparent;
        }

        .wa-v2-conversation.is-active {
            background: linear-gradient(135deg, rgba(15, 118, 110, .13), rgba(255, 255, 255, .98));
            border-color: rgba(15, 118, 110, .28);
            box-shadow: 0 14px 26px rgba(15, 118, 110, .12);
        }

        .wa-v2-conversation:hover {
            background: #fff;
            transform: translateY(-1px);
            box-shadow: 0 14px 24px rgba(15, 23, 42, .08);
        }

        .wa-v2-conversation[data-priority-state="pending"]::before {
            background: #f59e0b;
        }

        .wa-v2-conversation[data-priority-state="mine"]::before {
            background: #0f766e;
        }

        .wa-v2-conversation[data-priority-state="window_open"]::before {
            background: #2563eb;
        }

        .wa-v2-conversation[data-priority-state="needs_template"]::before {
            background: #7c3aed;
        }

        .wa-v2-conversation[data-priority-state="resolved"]::before {
            background: #94a3b8;
        }

        .wa-v2-name {
            font-weight: 800;
            color: var(--wa-text);
            letter-spacing: -.02em;
        }

        .wa-v2-meta {
            font-size: 12px;
            color: var(--wa-muted);
        }

        .wa-v2-preview {
            font-size: 13px;
            color: #334155;
            margin-top: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .wa-v2-row-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .wa-v2-row-title {
            min-width: 0;
            flex: 1 1 auto;
        }

        .wa-v2-unread-dot {
            min-width: 28px;
            height: 28px;
            padding: 0 9px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #dcfce7;
            color: #166534;
            font-size: 11px;
            font-weight: 800;
            box-shadow: inset 0 0 0 1px rgba(34, 197, 94, .15);
        }

        .wa-v2-unread-dot.is-empty {
            min-width: 0;
            height: auto;
            padding: 0;
            background: transparent;
            box-shadow: none;
            color: var(--wa-muted);
        }

        .wa-v2-priority-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 10px;
        }

        .wa-v2-priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
        }

        .wa-v2-priority-badge.is-pending {
            background: #fff7ed;
            color: #9a3412;
        }

        .wa-v2-priority-badge.is-mine {
            background: #ccfbf1;
            color: #115e59;
        }

        .wa-v2-priority-badge.is-window-open {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .wa-v2-priority-badge.is-needs-template {
            background: #ede9fe;
            color: #6d28d9;
        }

        .wa-v2-priority-badge.is-resolved {
            background: #e2e8f0;
            color: #475569;
        }

        .wa-v2-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            background: #edf2f7;
            color: #334155;
            line-height: 1;
        }

        .wa-v2-pill i {
            font-size: 12px;
            line-height: 1;
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
            min-height: 0;
            height: 100%;
            max-height: 100%;
            overflow: hidden;
            background: #f0f4f0;
        }

        .wa-v2-chat__body {
            padding: 16px 18px 10px;
            overflow: auto;
            background:
                radial-gradient(ellipse at top, rgba(220, 248, 198, .18) 0%, transparent 55%),
                #eef2ee;
        }

        /* date divider inside message list */
        .wa-v2-date-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 14px 0 10px;
            font-size: 11px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .wa-v2-date-divider::before,
        .wa-v2-date-divider::after {
            content: '';
            flex: 1 1 auto;
            height: 1px;
            background: rgba(148, 163, 184, .28);
        }

        .wa-v2-chat-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .wa-v2-chat-header__main {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            min-width: 0;
            flex: 1 1 auto;
        }

        .wa-v2-avatar {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
            color: #fff;
            font-size: 18px;
            font-weight: 800;
            box-shadow: 0 12px 18px rgba(15, 118, 110, .22);
            flex: 0 0 auto;
        }

        .wa-v2-chat-title {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.03em;
            color: var(--wa-text);
            margin: 0;
        }

        .wa-v2-chat-subtitle {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            min-width: 0;
            color: var(--wa-muted);
            font-size: 13px;
        }

        .wa-v2-chat-subtitle__item {
            min-width: 0;
            max-width: 320px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .wa-v2-origin-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            white-space: nowrap;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .wa-v2-origin-badge--ad       { background: #ffedd5; color: #c2410c; }
        .wa-v2-origin-badge--organic  { background: #dcfce7; color: #15803d; }
        .wa-v2-origin-badge--campaign { background: #f3e8ff; color: #7e22ce; }
        .wa-v2-origin-badge--support  { background: #e0f2fe; color: #0369a1; }

        .wa-v2-chat-subtitle__item--patient {
            color: #334155;
        }

        .wa-v2-chat-subtitle__dot {
            color: #94a3b8;
        }

        .wa-v2-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: nowrap;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(15, 23, 42, .06);
        }

        .wa-v2-toolbar__badges {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            min-width: 0;
        }

        .wa-v2-toolbar__actions {
            min-width: 0;
            flex: 0 0 auto;
        }

        .wa-v2-ops-menu {
            position: relative;
        }

        .wa-v2-ops-menu summary {
            list-style: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 36px;
            padding: 0 12px;
            border-radius: 12px;
            border: 1px solid #dbe4ee;
            background: #fff;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .wa-v2-ops-menu summary::-webkit-details-marker {
            display: none;
        }

        .wa-v2-ops-menu summary::after {
            content: "\F0140";
            font-family: "Material Design Icons";
            font-size: 16px;
            color: var(--wa-muted);
            transition: transform .16s ease;
        }

        .wa-v2-ops-menu[open] summary::after {
            transform: rotate(180deg);
        }

        .wa-v2-ops-menu__body {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 35;
            width: min(760px, calc(100vw - 48px));
            padding: 12px;
            border: 1px solid rgba(15, 23, 42, .10);
            border-radius: 16px;
            background: rgba(255, 255, 255, .98);
            box-shadow: 0 22px 44px rgba(15, 23, 42, .16);
        }

        .wa-v2-actions {
            display: grid;
            gap: 12px;
            margin-top: 0;
        }

        .wa-v2-actions__row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 12px;
            align-items: start;
        }

        .wa-v2-actions__row--header {
            display: inline-flex;
            justify-content: flex-end;
            align-items: center;
            margin-left: auto;
        }

        .wa-v2-actions__group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1 1 auto;
            min-width: 0;
            flex-wrap: nowrap;
        }

        .wa-v2-actions .form-select,
        .wa-v2-actions .form-control {
            min-width: 0;
        }

        #wa-v2-transfer-user,
        #wa-v2-queue-role {
            flex: 0 0 210px;
            min-width: 210px;
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

        .wa-v2-message-stack {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .wa-v2-message {
            max-width: 74%;
            padding: 8px 14px 5px;
            border-radius: 16px 16px 16px 4px;
            margin-bottom: 2px;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .07);
            box-shadow: 0 1px 4px rgba(15, 23, 42, .07);
            font-size: 14px;
            line-height: 1.55;
            color: #1e293b;
            word-break: break-word;
        }

        /* visual gap when direction switches */
        .wa-v2-message.is-outbound + .wa-v2-message:not(.is-outbound),
        .wa-v2-message:not(.is-outbound) + .wa-v2-message.is-outbound {
            margin-top: 12px;
        }

        /* extra breathing room after the last of each group */
        .wa-v2-message:last-child {
            margin-bottom: 8px;
        }

        .wa-v2-message.is-outbound {
            margin-left: auto;
            background: linear-gradient(155deg, #e2fcd4 0%, #d5f5bf 100%);
            border-color: rgba(74, 171, 66, .22);
            border-radius: 16px 16px 4px 16px;
            box-shadow: 0 1px 4px rgba(74, 171, 66, .14);
            color: #14290f;
        }

        /* message body text */
        .wa-v2-message__body {
            white-space: normal;
            word-break: break-word;
        }

        /* meta: time + delivery tick — always right-aligned, small */
        .wa-v2-message__meta {
            margin-top: 2px;
            margin-bottom: 1px;
            font-size: 10.5px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 3px;
            min-height: 16px;
        }

        .wa-v2-message:not(.is-outbound) .wa-v2-message__meta {
            color: #b0bcc8;
        }

        /* delivery status ticks */
        .wa-v2-msg-tick {
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
            letter-spacing: -.03em;
            display: inline-block;
        }

        .wa-v2-msg-tick--sent      { color: #94a3b8; }
        .wa-v2-msg-tick--delivered { color: #94a3b8; }
        .wa-v2-msg-tick--read      { color: #3b82f6; }
        .wa-v2-msg-tick--failed    { color: #ef4444; font-size: 11px; }
        .wa-v2-msg-tick--pending   { color: #94a3b8; font-size: 11px; }

        .wa-v2-media-card {
            display: grid;
            gap: 8px;
            margin-top: 6px;
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(15, 23, 42, .04);
            border: 1px solid rgba(15, 23, 42, .08);
        }

        .wa-v2-message.is-outbound .wa-v2-media-card {
            background: rgba(15, 23, 42, .05);
            border-color: rgba(74, 171, 66, .18);
        }

        .wa-v2-media-card__title {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }

        .wa-v2-media-card__meta {
            font-size: 12px;
            color: #64748b;
        }

        .wa-v2-live-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 14px;
            margin: 0 0 10px;
            border-radius: 12px;
            border: 1px solid rgba(14, 165, 233, .22);
            background: rgba(224, 242, 254, .95);
            color: #0c4a6e;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(14, 165, 233, .12);
            cursor: pointer;
            transition: background .14s ease;
        }

        .wa-v2-live-banner:hover {
            background: #bae6fd;
        }

        .wa-v2-live-banner[hidden] {
            display: none;
        }

        .wa-v2-tools {
            padding: 0 5px 5px;
            min-height: 0;
        }

        /* ── 3rd column: herramientas panel ─────────────────────────────── */
        .wa-v2-herramientas {
            display: flex;
            flex-direction: column;
            min-height: 0;
            height: 100%;
            max-height: 100%;
            overflow: hidden;
        }

        .wa-v2-herramientas__scroll {
            flex: 1 1 auto;
            min-height: 0;
            max-height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            -webkit-overflow-scrolling: touch;
        }

        /* ── tool-card overrides inside the 3rd column ───────────────────── */
        .wa-v2-herramientas .wa-v2-collapse {
            border-radius: 14px;
            border: 1px solid rgba(15, 23, 42, .08);
            background: rgba(255, 255, 255, .86);
        }

        .wa-v2-herramientas .wa-v2-collapse__body {
            padding: 10px 12px 12px;
        }

        /* Trail scrollable internamente */
        #wa-v2-trail-list {
            max-height: 260px;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
        }

        .wa-v2-tools-drawer {
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 16px;
            background: rgba(255, 255, 255, .88);
            overflow: hidden;
            flex: 0 0 auto;
        }

        .wa-v2-tools-drawer > summary {
            list-style: none;
            cursor: pointer;
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            font-weight: 800;
            color: var(--wa-text);
        }

        .wa-v2-tools-drawer > summary::-webkit-details-marker {
            display: none;
        }

        .wa-v2-tools-drawer > summary::after {
            content: "\F0140";
            font-family: "Material Design Icons";
            font-size: 18px;
            color: var(--wa-muted);
            transition: transform .16s ease;
        }

        .wa-v2-tools-drawer[open] > summary::after {
            transform: rotate(180deg);
        }

        .wa-v2-tools-drawer__body {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 14px;
            padding: 0 12px 12px;
            border-top: 1px solid rgba(148, 163, 184, .12);
        }

        .wa-v2-tool-card {
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 14px;
            background: rgba(255, 255, 255, .86);
            padding: 0;
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

        /* Trail / trazabilidad */
        .wa-v2-trail {
            position: relative;
            padding-left: 20px;
        }
        .wa-v2-trail::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 4px;
            bottom: 4px;
            width: 2px;
            background: #e2e8f0;
            border-radius: 2px;
        }
        .wa-v2-trail-item {
            position: relative;
            margin-bottom: 12px;
        }
        .wa-v2-trail-item::before {
            content: '';
            position: absolute;
            left: -17px;
            top: 5px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: 2px solid #94a3b8;
            background: #fff;
        }
        .wa-v2-trail-item--start::before       { border-color: #0ea5e9; background: #e0f2fe; }
        .wa-v2-trail-item--assigned::before   { border-color: #2563eb; background: #dbeafe; }
        .wa-v2-trail-item--queued::before     { border-color: #d97706; background: #fef3c7; }
        .wa-v2-trail-item--closed::before     { border-color: #16a34a; background: #dcfce7; }
        .wa-v2-trail-item--transferred::before{ border-color: #7c3aed; background: #ede9fe; }
        .wa-v2-trail-item--resolved::before   { border-color: #16a34a; background: #dcfce7; }
        .wa-v2-trail-item--expired::before    { border-color: #dc2626; background: #fee2e2; }
        .wa-v2-trail-item--template::before   { border-color: #0891b2; background: #cffafe; }
        .wa-v2-trail-item--ad::before         { border-color: #ea580c; background: #ffedd5; }
        .wa-v2-trail-item--organic::before    { border-color: #16a34a; background: #dcfce7; }
        .wa-v2-trail-item--campaign::before   { border-color: #9333ea; background: #f3e8ff; }
        .wa-v2-trail-item--support::before    { border-color: #0369a1; background: #e0f2fe; }
        .wa-v2-trail-item--intent::before     { border-color: #64748b; background: #f1f5f9; }

        .wa-v2-trail-notes {
            font-size: .79rem;
            color: #475569;
            margin-top: 3px;
            white-space: pre-line;
            line-height: 1.5;
        }
        .wa-v2-trail-label {
            font-weight: 700;
            font-size: .8rem;
            color: #1e293b;
        }
        .wa-v2-trail-meta {
            font-size: .74rem;
            color: #64748b;
            margin-top: 1px;
        }
        .wa-v2-trail-note {
            font-size: .74rem;
            color: #475569;
            margin-top: 4px;
            background: #f8fafc;
            border-left: 2px solid #cbd5e1;
            padding: 4px 8px;
            border-radius: 0 6px 6px 0;
        }

        .wa-v2-compose {
            padding: 10px 12px 12px;
            border-top: 1px solid rgba(15, 23, 42, .08);
            background: #f0f4f0;
            backdrop-filter: blur(10px);
            position: sticky;
            bottom: 0;
        }

        .wa-v2-compose-grid {
            display: grid;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 20px;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .09);
            box-shadow: 0 2px 10px rgba(15, 23, 42, .07);
        }

        .wa-v2-compose-actions {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .wa-v2-compose-action {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            border: 1px solid #dbe4ee;
            background: #fff;
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color .15s ease, color .15s ease, background .15s ease;
        }

        .wa-v2-compose-action:hover {
            border-color: #0f766e;
            color: #0f766e;
            background: #f0fdfa;
        }

        .wa-v2-compose-action.is-active {
            border-color: #0f766e;
            background: #ccfbf1;
            color: #0f766e;
        }

        .wa-v2-compose-action.is-recording {
            border-color: #dc2626;
            background: #fee2e2;
            color: #dc2626;
            animation: wa-v2-recording-pulse 1.1s ease-in-out infinite;
        }

        .wa-v2-attachment-menu {
            position: relative;
        }

        .wa-v2-attachment-menu.is-disabled {
            opacity: .55;
            pointer-events: none;
        }

        .wa-v2-attachment-menu summary {
            list-style: none;
            width: 42px;
            height: 42px;
            border-radius: 999px;
            border: 1px solid #dbe4ee;
            background: #fff;
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: border-color .15s ease, color .15s ease, background .15s ease;
        }

        .wa-v2-attachment-menu summary::-webkit-details-marker {
            display: none;
        }

        .wa-v2-attachment-menu summary:hover,
        .wa-v2-attachment-menu[open] summary {
            border-color: #0f766e;
            color: #0f766e;
            background: #f0fdfa;
        }

        .wa-v2-attachment-menu__items {
            position: absolute;
            left: 0;
            bottom: calc(100% + 8px);
            z-index: 40;
            display: grid;
            gap: 8px;
            width: min(380px, calc(100vw - 48px));
            padding: 8px;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, .10);
            background: #fff;
            box-shadow: 0 18px 38px rgba(15, 23, 42, .18);
        }

        .wa-v2-attachment-menu__section {
            display: grid;
            gap: 8px;
            padding: 8px;
            border-radius: 12px;
            background: rgba(248, 250, 252, .86);
        }

        .wa-v2-attachment-menu__title {
            font-size: 11px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .wa-v2-attachment-menu__media {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        @keyframes wa-v2-recording-pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(220, 38, 38, .14);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(220, 38, 38, 0);
            }
        }

        .wa-v2-compose-attachment {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 12px;
            background: rgba(248, 250, 252, .94);
            border: 1px solid rgba(15, 23, 42, .08);
        }

        .wa-v2-compose-attachment__meta {
            min-width: 0;
            flex: 1 1 auto;
        }

        .wa-v2-compose-attachment__name {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .wa-v2-compose-attachment__type {
            font-size: 11px;
            color: #64748b;
        }

        .wa-v2-compose-hidden {
            display: none;
        }

        .wa-v2-compose-grid__hidden {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 220px;
            gap: 10px;
        }

        .wa-v2-upload-status {
            font-size: 12px;
            color: #64748b;
        }

        .wa-v2-upload-progress {
            height: 8px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
            display: none;
        }

        .wa-v2-upload-progress.is-visible {
            display: block;
        }

        .wa-v2-upload-progress__bar {
            height: 100%;
            width: 0;
            border-radius: 999px;
            background: linear-gradient(90deg, #14b8a6 0%, #0f766e 100%);
            transition: width .14s linear;
        }

        .wa-v2-compose-grid.is-drop-target {
            border-color: rgba(15, 118, 110, .34);
            box-shadow: 0 0 0 4px rgba(20, 184, 166, .12);
            background: rgba(240, 253, 250, .96);
        }

        .wa-v2-recording-meta {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 12px;
            background: #fff1f2;
            color: #9f1239;
            font-size: 12px;
            font-weight: 700;
        }

        .wa-v2-recording-meta.is-visible {
            display: inline-flex;
        }

        .wa-v2-composer-inputgroup {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
        }

        .wa-v2-composer-inputgroup textarea {
            min-height: 44px;
            max-height: 120px;
            resize: none;
            border-radius: 16px;
            border-color: #d7e3dd;
            padding: 11px 14px;
            font-size: 14px;
            line-height: 1.5;
            background: #f8fbf8;
            transition: border-color .15s ease, background .15s ease;
        }

        .wa-v2-composer-inputgroup textarea:focus {
            border-color: #0f766e;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, .10);
        }

        .wa-v2-composer-inputgroup textarea:disabled {
            background: #f1f5f1;
            color: #94a3b8;
        }

        .btn.wa-v2-composer-send,
        .wa-v2-composer-send {
            min-width: 100px;
            height: 44px;
            border-radius: 16px;
            font-weight: 800;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%) !important;
            border: none !important;
            color: #fff !important;
            box-shadow: 0 4px 12px rgba(15, 118, 110, .28);
            transition: opacity .15s ease, transform .12s ease, box-shadow .15s ease;
        }

        .btn.wa-v2-composer-send:not(:disabled):hover,
        .wa-v2-composer-send:not(:disabled):hover {
            opacity: .92;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(15, 118, 110, .34);
        }

        .btn.wa-v2-composer-send:disabled,
        .wa-v2-composer-send:disabled {
            background: #e2e8f0 !important;
            color: #94a3b8 !important;
            box-shadow: none;
        }

        .wa-v2-empty {
            display: grid;
            place-items: center;
            min-height: 78vh;
            text-align: center;
            color: #64748b;
            padding: 24px;
            background: radial-gradient(circle at top left, rgba(16, 185, 129, .12), transparent 24%),
            linear-gradient(180deg, rgba(255, 255, 255, .96) 0%, rgba(246, 251, 248, .98) 100%);
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

        .wa-v2-collapse {
            margin-bottom: 15px;
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: 18px;
            background: rgba(255, 255, 255, .75);
            overflow: hidden;
        }

        .wa-v2-collapse summary {
            list-style: none;
            cursor: pointer;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            font-weight: 800;
            color: var(--wa-text);
        }

        .wa-v2-collapse summary::-webkit-details-marker {
            display: none;
        }

        .wa-v2-collapse summary::after {
            content: "\F0140";
            font-family: "Material Design Icons";
            font-size: 18px;
            color: var(--wa-muted);
            transition: transform .16s ease;
        }

        .wa-v2-collapse[open] summary::after {
            transform: rotate(180deg);
        }

        .wa-v2-collapse__body {
            padding: 0 14px 14px;
            border-top: 1px solid rgba(148, 163, 184, .12);
        }

        @media (max-width: 991px) {
            .wa-v2-pagebar {
                padding: 18px;
                border-radius: 24px;
                flex-direction: column;
                align-items: flex-start;
            }

            .wa-v2-pagebar__title {
                font-size: 24px;
            }

            .wa-v2-shell {
                grid-template-columns: 1fr;
            }

            .wa-v2-herramientas {
                height: auto;
                max-height: none;
                overflow: visible;
            }

            .wa-v2-herramientas__scroll {
                overflow: visible;
                max-height: none;
                min-height: 0;
            }

            .wa-v2-tools {
                padding-inline: 12px;
            }

            .wa-v2-tools-drawer__body {
                grid-template-columns: 1fr;
            }

            .wa-v2-compose-grid__hidden {
                grid-template-columns: 1fr;
            }

            .wa-v2-list,
            .wa-v2-shell,
            .wa-v2-chat {
                max-height: none;
                min-height: auto;
                height: auto;
                overflow: visible;
            }

            .wa-v2-chat-header,
            .wa-v2-actions__row {
                flex-wrap: wrap;
            }

            .wa-v2-actions__row--header {
                width: 100%;
                justify-content: flex-start;
                margin-left: 0;
            }

            .wa-v2-ops-menu {
                width: 100%;
            }

            .wa-v2-ops-menu summary {
                width: 100%;
                justify-content: center;
            }

            .wa-v2-ops-menu__body {
                position: static;
                width: 100%;
                margin-top: 8px;
                box-shadow: none;
            }

            .wa-v2-actions__row {
                grid-template-columns: 1fr;
            }

            .wa-v2-actions__group {
                flex: 1 1 100%;
                flex-wrap: wrap;
            }

            .wa-v2-toolbar {
                flex-wrap: wrap;
                align-items: stretch;
            }

            .wa-v2-toolbar__actions {
                width: 100%;
            }

            #wa-v2-transfer-user,
            #wa-v2-queue-role,
            #wa-v2-transfer-note,
            #wa-v2-queue-note {
                flex: 1 1 100%;
                min-width: 0;
            }

            .wa-v2-message {
                max-width: 88%;
            }

            .wa-v2-composer-inputgroup {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .wa-v2-composer-send {
                grid-column: 1 / -1;
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    <section class="content">
        <div class="row mb-15">
            <div class="col-12">
                <div class="wa-v2-pagebar">
                    <div>
                        <div class="wa-v2-pagebar__title">Inbox operativo</div>
                        <div class="wa-v2-pagebar__subtitle">Mensajería, media, handoff, quick replies y notificaciones
                            en tiempo real con foco mobile-first.
                        </div>
                    </div>
                    <div class="wa-v2-pagebar__meta">
                        <a
                            href="https://medforge.my.canva.site/manual-de-entrega-operativa-whatsapp"
                            class="btn btn-outline-light btn-sm"
                            target="_blank"
                            rel="noopener">
                            <i class="mdi mdi-school-outline"></i> Manual operativo
                        </a>
                        <button type="button" class="btn btn-light btn-sm" id="wa-v2-open-start-chat">
                            <i class="mdi mdi-message-plus-outline"></i> Nuevo chat
                        </button>
                        <div class="wa-v2-pagebar-control">
                            <label for="wa-v2-presence">Presencia</label>
                            <select id="wa-v2-presence" class="form-select form-select-sm">
                                <option value="available" {{ $presenceStatus === 'available' ? 'selected' : '' }}>
                                    Disponible
                                </option>
                                <option value="away" {{ $presenceStatus === 'away' ? 'selected' : '' }}>Ausente</option>
                                <option value="offline" {{ $presenceStatus === 'offline' ? 'selected' : '' }}>Offline
                                </option>
                            </select>
                        </div>
                        @if($canSupervise)
                            <button type="button" class="btn btn-warning btn-sm" id="wa-v2-requeue-expired">
                                Reencolar vencidos
                            </button>
                        @endif
                        <span class="wa-v2-hero-pill"><i class="mdi mdi-account-circle-outline"></i> {{ $currentUser['display_name'] ?? 'Usuario' }}</span>
                        <span class="wa-v2-hero-pill"><i class="mdi mdi-layers-outline"></i> {{ $canSupervise ? 'Modo supervisor' : 'Vista de agente' }}</span>
                        <span class="wa-v2-hero-pill"><i class="mdi mdi-access-point"></i> Realtime listo</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="wa-v2-modal-backdrop" id="wa-v2-start-chat-modal" aria-hidden="true">
            <div class="wa-v2-modal">
                <div class="wa-v2-modal__header d-flex justify-content-between align-items-start gap-10">
                    <div>
                        <div class="wa-v2-sideheading__title" style="font-size:20px;">Nuevo chat con plantilla</div>
                        <div class="text-muted" style="font-size:13px;">Busca en pacientes, o escribe el número, y abre
                            la conversación con un template aprobado.
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="wa-v2-close-start-chat">
                        Cerrar
                    </button>
                </div>
                <div class="wa-v2-modal__body">
                    <div class="row g-12">
                        <div class="col-lg-7">
                            <label for="wa-v2-start-search" class="form-label">Buscar paciente o número</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="wa-v2-start-search"
                                       placeholder="Celular, HC, nombres o apellidos">
                                <button type="button" class="btn btn-outline-secondary" id="wa-v2-start-search-button">
                                    Buscar
                                </button>
                            </div>
                            <div class="wa-v2-picker-results" id="wa-v2-start-results"></div>
                            <div class="wa-v2-template-preview mt-12" id="wa-v2-start-template-preview">
                                <div class="wa-v2-template-preview__label">Preview del mensaje</div>
                                <div class="wa-v2-template-preview__body" id="wa-v2-start-template-preview-body">
                                    Selecciona un template para revisar el mensaje final.
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="row g-10">
                                <div class="col-12">
                                    <label for="wa-v2-start-number" class="form-label">Número WhatsApp</label>
                                    <input type="text" class="form-control" id="wa-v2-start-number"
                                           placeholder="593999111222">
                                </div>
                                <div class="col-12">
                                    <label for="wa-v2-start-contact-name" class="form-label">Nombre visible</label>
                                    <input type="text" class="form-control" id="wa-v2-start-contact-name"
                                           placeholder="Nombre del contacto">
                                </div>
                                <div class="col-12">
                                    <label for="wa-v2-start-patient-name" class="form-label">Paciente</label>
                                    <input type="text" class="form-control" id="wa-v2-start-patient-name"
                                           placeholder="Nombres y apellidos">
                                </div>
                                <div class="col-12">
                                    <label for="wa-v2-start-hc" class="form-label">HC</label>
                                    <input type="text" class="form-control" id="wa-v2-start-hc"
                                           placeholder="Historia clínica">
                                </div>
                                <div class="col-12">
                                    <label for="wa-v2-start-template" class="form-label">Template aprobado</label>
                                    <select class="form-select" id="wa-v2-start-template">
                                        <option value="">Selecciona un template</option>
                                        @foreach($templateOptions as $template)
                                            <option value="{{ $template['id'] }}">{{ $template['name'] }}
                                                · {{ $template['language'] ?: 'n/a' }}
                                                · {{ $template['status'] ?: 'n/a' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div id="wa-v2-start-template-variables" class="d-none">
                                        <div class="text-muted" style="font-size:12px; margin-bottom:8px;">Completa las
                                            variables del template antes de enviarlo.
                                        </div>
                                        <div class="row g-10" id="wa-v2-start-template-variables-fields"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-light mt-15 mb-0" id="wa-v2-start-chat-feedback">
                        Selecciona un contacto o escribe el número manualmente para iniciar con plantilla.
                    </div>
                </div>
                <div class="wa-v2-modal__footer">
                    <div class="text-muted" style="font-size:12px;">Esto crea o reutiliza la conversación y la deja
                        abierta en tu inbox.
                    </div>
                    <button type="button" class="btn btn-primary" id="wa-v2-start-submit">Iniciar con plantilla</button>
                </div>
            </div>
        </div>

        <div class="wa-v2-shell">
            <div class="wa-v2-panel">
                <div class="wa-v2-panel__header">
                    <div class="wa-v2-sideheading">
                        <div>
                            <div class="wa-v2-sideheading__title">Conversaciones</div>
                            <div class="wa-v2-sideheading__meta">{{ count($listData) }} visibles en este filtro</div>
                        </div>

                    </div>

                    <form method="GET" action="/v2/whatsapp/chat" class="mb-15 wa-v2-searchbar">
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

                    <form method="GET" action="/v2/whatsapp/chat" class="row g-10 mb-15">
                        <input type="hidden" name="filter" value="{{ $selectedFilter }}">
                        <input type="hidden" name="search" value="{{ $search }}">
                        @if($selectedAgentId !== null)
                            <input type="hidden" name="agent_id" value="{{ $selectedAgentId }}">
                        @endif
                        @if($selectedRoleId !== null)
                            <input type="hidden" name="role_id" value="{{ $selectedRoleId }}">
                        @endif
                        <div class="col-12">
                            <div class="text-uppercase text-muted mb-5" style="font-size:11px; letter-spacing:.08em;">
                                Última interacción
                            </div>
                        </div>
                        <div class="col-sm-5">
                            <input type="date" name="date_from" class="form-control form-control-sm"
                                   value="{{ $dateFrom }}">
                        </div>
                        <div class="col-sm-5">
                            <input type="date" name="date_to" class="form-control form-control-sm"
                                   value="{{ $dateTo }}">
                        </div>
                        <div class="col-sm-2 d-flex gap-8">
                            <button type="submit" class="btn btn-outline-primary btn-sm flex-fill"
                                    title="Aplicar rango">
                                <i class="mdi mdi-calendar-filter-outline"></i>
                            </button>
                            <a href="{{ '/v2/whatsapp/chat?' . http_build_query(array_filter(['filter' => $selectedFilter, 'search' => $search, 'agent_id' => $selectedAgentId, 'role_id' => $selectedRoleId], static fn ($value) => $value !== null && $value !== '')) }}"
                               class="btn btn-outline-danger btn-sm" title="Limpiar fechas">
                                <i class="mdi mdi-filter-off"></i>
                            </a>
                        </div>
                    </form>

                    @if($canSupervise)
                        @php
                            $summaryTotals = is_array($agentSummary['totals'] ?? null) ? $agentSummary['totals'] : [];
                        @endphp
                        <details class="wa-v2-collapse">
                            <summary>
                                <span>Panel supervisor</span>
                                <span class="text-muted" style="font-size:12px;">Cola, filtros y carga</span>
                            </summary>
                            <div class="wa-v2-collapse__body">
                                <div class="wa-v2-statgrid">
                                    <div class="wa-v2-statcard">
                                        <div
                                            class="wa-v2-statcard__value">{{ (int) ($summaryTotals['queued_open_count'] ?? 0) }}</div>
                                        <div class="wa-v2-statcard__label">Cola abierta</div>
                                    </div>
                                    <div class="wa-v2-statcard">
                                        <div
                                            class="wa-v2-statcard__value">{{ (int) ($summaryTotals['assigned_open_count'] ?? 0) }}</div>
                                        <div class="wa-v2-statcard__label">Asignados</div>
                                    </div>
                                    <div class="wa-v2-statcard">
                                        <div
                                            class="wa-v2-statcard__value">{{ (int) ($summaryTotals['unread_open_count'] ?? 0) }}</div>
                                        <div class="wa-v2-statcard__label">Con unread</div>
                                    </div>
                                    <div class="wa-v2-statcard">
                                        <div
                                            class="wa-v2-statcard__value">{{ (int) ($summaryTotals['expiring_soon_count'] ?? 0) }}</div>
                                        <div class="wa-v2-statcard__label">TTL por vencer</div>
                                    </div>
                                </div>

                                <form method="GET" action="/v2/whatsapp/chat" class="row g-10 mb-15">
                                    <input type="hidden" name="filter" value="{{ $selectedFilter }}">
                                    <input type="hidden" name="search" value="{{ $search }}">
                                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                                    <div class="col-12">
                                        <div class="text-uppercase text-muted mb-5"
                                             style="font-size:11px; letter-spacing:.08em;">Filtros supervisor
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
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
                                    <div class="col-sm-4">
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
                                    <div class="col-sm-4">
                                        <button type="submit" class="btn btn-primary btn-sm"><i
                                                class="mdi mdi-check-circle-outline"></i>
                                        </button>
                                        <a href="{{ '/v2/whatsapp/chat?' . http_build_query(array_filter(['filter' => $selectedFilter, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo], static fn ($value) => $value !== null && $value !== '')) }}"
                                           class="btn btn-danger btn-sm"><i class="mdi mdi-filter-off"></i>
                                        </a>
                                    </div>
                                </form>

                                @if(!empty($agentSummary['agents']))
                                    <div>
                                        <div class="text-uppercase text-muted mb-5"
                                             style="font-size:11px; letter-spacing:.08em;">Carga por agente
                                        </div>
                                        <div class="d-grid gap-8">
                                            @foreach(array_slice($agentSummary['agents'], 0, 4) as $agent)
                                                <div class="wa-v2-panel p-10" style="border-radius:14px;">
                                                    <div
                                                        class="d-flex justify-content-between align-items-center gap-10">
                                                        <div>
                                                            <div class="fw-700">{{ $agent['name'] }}</div>
                                                            <div class="text-muted"
                                                                 style="font-size:12px;">{{ $agent['role_name'] ?: 'Sin rol' }}
                                                                · {{ $agent['presence_status'] }}</div>
                                                        </div>
                                                        <div class="text-end" style="font-size:12px;">
                                                            <div>{{ (int) ($agent['assigned_open_count'] ?? 0) }}
                                                                asignados
                                                            </div>
                                                            <div>{{ (int) ($agent['unread_open_count'] ?? 0) }} con
                                                                unread
                                                            </div>
                                                            <div>{{ (int) ($agent['expiring_soon_count'] ?? 0) }} por
                                                                vencer
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endif

                    <div class="wa-v2-tabs-shell">
                        <button type="button" class="wa-v2-tabs-nav" data-wa-tabs-nav="left"
                                aria-label="Ver filtros anteriores">
                            <i class="mdi mdi-chevron-left"></i>
                        </button>
                        <div class="wa-v2-tabs-viewport">
                            <div class="wa-v2-tabs" id="wa-v2-filter-tabs" tabindex="0"
                                 aria-label="Filtros de conversaciones">
                                @foreach($tabs as $key => $label)
                                    @php
                                        $tabIcons = [
                                            'requires_attention' => 'mdi mdi-alert-circle-outline',
                                            'in_progress' => 'mdi mdi-account-clock-outline',
                                            'waiting_patient' => 'mdi mdi-account-arrow-left-outline',
                                            'scheduled' => 'mdi mdi-calendar-check-outline',
                                            'closed' => 'mdi mdi-archive-check-outline',
                                            'critical_backlog' => 'mdi mdi-alert-octagon-outline',
                                            'captacion' => 'mdi mdi-bullseye-arrow',
                                            'operacion' => 'mdi mdi-calendar-sync-outline',
                                            'informacion' => 'mdi mdi-information-outline',
                                            'mine' => 'mdi mdi-account-outline',
                                            'handoff' => 'mdi mdi-tray-arrow-down',
                                            'window_open' => 'mdi mdi-timer-sand',
                                            'unread' => 'mdi mdi-bell-outline',
                                            'needs_template' => 'mdi mdi-file-document-edit-outline',
                                            'resolved' => 'mdi mdi-check-circle-outline',
                                            'all' => 'mdi mdi-message-text-outline',
                                        ];
                                        $icon = $tabIcons[$key] ?? 'mdi mdi-circle-small';
                                    @endphp
                                    <a
                                        href="{{ '/v2/whatsapp/chat?' . http_build_query(array_filter(['filter' => $key, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'agent_id' => $selectedAgentId, 'role_id' => $selectedRoleId], static fn ($value) => $value !== null && $value !== '')) }}"
                                        class="wa-v2-tab {{ $selectedFilter === $key ? 'is-active' : '' }}"
                                        title="{{ $tabDescriptions[$key] ?? $label }}"
                                        aria-label="{{ $label }}"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="bottom">
                                        <span class="wa-v2-icon-label">
                                            <i class="{{ $icon }}"></i>
                                        </span>
                                        <span class="wa-v2-counter">{{ (int) ($tabCounts[$key] ?? 0) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        <button type="button" class="wa-v2-tabs-nav" data-wa-tabs-nav="right"
                                aria-label="Ver más filtros">
                            <i class="mdi mdi-chevron-right"></i>
                        </button>
                    </div>
                    <details class="mt-10">
                        <summary class="text-muted" style="cursor:pointer;font-size:12px;font-weight:700;padding:0 4px;">
                            Filtros avanzados
                        </summary>
                        <div class="wa-v2-tabs mt-8" aria-label="Filtros avanzados de conversaciones">
                            @foreach($advancedTabs as $key => $label)
                                @php
                                    $tabIcons = [
                                        'critical_backlog' => 'mdi mdi-alert-octagon-outline',
                                        'captacion' => 'mdi mdi-bullseye-arrow',
                                        'operacion' => 'mdi mdi-calendar-sync-outline',
                                        'informacion' => 'mdi mdi-information-outline',
                                        'window_open' => 'mdi mdi-timer-sand',
                                        'unread' => 'mdi mdi-bell-outline',
                                        'needs_template' => 'mdi mdi-file-document-edit-outline',
                                        'resolved' => 'mdi mdi-check-circle-outline',
                                        'all' => 'mdi mdi-message-text-outline',
                                    ];
                                    $icon = $tabIcons[$key] ?? 'mdi mdi-circle-small';
                                @endphp
                                <a
                                    href="{{ '/v2/whatsapp/chat?' . http_build_query(array_filter(['filter' => $key, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'agent_id' => $selectedAgentId, 'role_id' => $selectedRoleId], static fn ($value) => $value !== null && $value !== '')) }}"
                                    class="wa-v2-tab {{ $selectedFilter === $key ? 'is-active' : '' }}"
                                    title="{{ $tabDescriptions[$key] ?? $label }}">
                                    <span class="wa-v2-icon-label"><i class="{{ $icon }}"></i></span>
                                    <span class="wa-v2-counter">{{ (int) ($tabCounts[$key] ?? 0) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                </div>

                <div class="wa-v2-list">
                    @forelse($listData as $conversation)
                        @php
                            $isActive = (int) ($selectedConversation['id'] ?? 0) === (int) $conversation['id'];
                            $priorityState = (string) ($conversation['operational_status'] ?? 'new');
                            $priorityLevel = (string) ($conversation['priority_level'] ?? 'low');
                            $priorityLabel = (string) ($conversation['operational_status_label'] ?? 'Sin estado');
                            $priorityClass = match ($priorityState) {
                                'requires_attention' => 'is-pending',
                                'in_progress' => 'is-mine',
                                'waiting_patient' => 'is-window-open',
                                'scheduled' => 'is-window-open',
                                'resolved', 'closed_followup', 'closed_other' => 'is-resolved',
                                default => $priorityLevel === 'critical' ? 'is-pending' : 'is-needs-template',
                            };
                            $priorityIcon = match ($priorityState) {
                                'requires_attention' => 'mdi-alert-circle-outline',
                                'in_progress' => 'mdi-account-clock-outline',
                                'waiting_patient' => 'mdi-account-arrow-left-outline',
                                'scheduled' => 'mdi-calendar-check-outline',
                                'resolved' => 'mdi-check-circle-outline',
                                'closed_followup' => 'mdi-archive-arrow-down-outline',
                                'closed_other' => 'mdi-archive-outline',
                                default => 'mdi-message-text-outline',
                            };
                        @endphp
                        <a
                            href="{{ '/v2/whatsapp/chat?' . http_build_query(array_filter(['filter' => $selectedFilter, 'search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'agent_id' => $selectedAgentId, 'role_id' => $selectedRoleId, 'conversation' => $conversation['id']], static fn ($value) => $value !== null && $value !== '')) }}"
                            class="wa-v2-conversation {{ $isActive ? 'is-active' : '' }}"
                            data-priority-state="{{ $priorityState }}"
                            data-wa-conversation-item="{{ (int) $conversation['id'] }}">
                            <div class="wa-v2-row-top">
                                <div class="wa-v2-row-title">
                                    <div
                                        class="wa-v2-name">{{ $conversation['display_name'] ?: $conversation['wa_number'] }}</div>
                                </div>
                                <div
                                    data-wa-conversation-unread="{{ (int) $conversation['id'] }}"
                                    class="wa-v2-unread-dot {{ (int) $conversation['unread_count'] > 0 ? '' : 'is-empty' }}">{{ (int) $conversation['unread_count'] > 0 ? (int) $conversation['unread_count'] : '' }}</div>
                            </div>
                            <div class="wa-v2-meta mt-5">
                                {{ $conversation['patient_full_name'] ?: 'Sin paciente vinculado' }}
                                @if(!empty($conversation['patient_hc_number']))
                                    · HC {{ $conversation['patient_hc_number'] }}
                                @endif
                            </div>
                            <div
                                data-wa-conversation-preview="{{ (int) $conversation['id'] }}"
                                class="wa-v2-preview">{{ $conversation['last_message_preview'] ?: '[' . ($conversation['last_message_type'] ?: 'mensaje') . ']' }}</div>
                            <div class="wa-v2-priority-line">
                                <span class="wa-v2-priority-badge {{ $priorityClass }}">
                                    <i class="mdi {{ $priorityIcon }}"></i>
                                    {{ $priorityLabel }}
                                </span>
                                <div class="wa-v2-meta" data-ts="{{ $conversation['last_message_at'] ?? '' }}"></div>
                            </div>
                            <div class="wa-v2-meta mt-4">
                                Último: {{ $conversation['last_message_actor_label'] ?? 'Sin mensajes' }}
                                · Prioridad {{ $conversation['priority_level_label'] ?? 'Baja' }}
                                @if(!empty($conversation['priority_score']))
                                    · {{ (int) $conversation['priority_score'] }} pts
                                @endif
                            </div>
                            <div class="d-flex flex-wrap gap-8 mt-10">
                                @if((int) $conversation['unread_count'] > 0)
                                    <span class="wa-v2-pill is-unread"><i class="mdi mdi-bell"></i> Sin leer</span>
                                @endif
                                @if(!empty($conversation['needs_human']))
                                    <span class="wa-v2-pill is-queue"><i
                                            class="mdi mdi-tray-arrow-down"></i> En cola</span>
                                @else
                                    <span class="wa-v2-pill"><i class="mdi mdi-check"></i> {{ $conversation['close_reason_label'] ?? 'Cerrado' }}</span>
                                @endif
                                @if(!empty($conversation['queue_bucket_label']) && !empty($conversation['needs_human']))
                                    <span class="wa-v2-pill"><i class="mdi mdi-shape-outline"></i> {{ $conversation['queue_bucket_label'] }}</span>
                                @endif
                                @if(!empty($conversation['handoff_priority_label']) && !empty($conversation['needs_human']))
                                    <span class="wa-v2-pill"><i class="mdi mdi-flag-outline"></i> {{ $conversation['handoff_priority_label'] }}</span>
                                @endif
                                @if(!empty($conversation['priority_level_label']) && !empty($conversation['needs_human']))
                                    <span class="wa-v2-pill"><i class="mdi mdi-speedometer"></i> Prioridad {{ $conversation['priority_level_label'] }}</span>
                                @endif
                                <span
                                    class="wa-v2-pill {{ ($conversation['messaging_window_state'] ?? '') === 'window_open' ? 'is-unread' : '' }}">
                                    <i class="mdi {{ ($conversation['messaging_window_state'] ?? '') === 'window_open' ? 'mdi-timer-sand' : 'mdi-file-document-edit-outline' }}"></i>
                                    {{ $conversation['messaging_window_label'] ?? 'Sin ventana' }}
                                </span>
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
                        $selectedPriorityLevel = (string) ($selectedConversation['priority_level'] ?? 'low');
                        $selectedPriorityIcon = match ($selectedPriorityLevel) {
                            'critical' => 'mdi-alert-octagon-outline',
                            'high' => 'mdi-alert-circle-outline',
                            'normal' => 'mdi-speedometer',
                            default => 'mdi-speedometer-slow',
                        };
                    @endphp
                    <div class="wa-v2-panel__header">
                        <div class="wa-v2-chat-header">
                            <div class="wa-v2-chat-header__main">
                                <div class="wa-v2-avatar">
                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($selectedConversation['display_name'] ?: $selectedConversation['wa_number'], 0, 1)) }}
                                </div>
                                <div>
                                    <h4 class="wa-v2-chat-title">{{ $selectedConversation['display_name'] ?: $selectedConversation['wa_number'] }}</h4>
                                    <div class="wa-v2-chat-subtitle">
                                        <span class="wa-v2-chat-subtitle__item">{{ $selectedConversation['wa_number'] }}</span>
                                        <span class="wa-v2-chat-subtitle__dot">·</span>
                                        <span class="wa-v2-chat-subtitle__item wa-v2-chat-subtitle__item--patient">{{ $selectedConversation['patient_full_name'] ?: 'Sin paciente vinculado' }}</span>
                                        @if(!empty($selectedConversation['patient_hc_number']))
                                            <span class="wa-v2-chat-subtitle__dot">·</span>
                                            <span class="wa-v2-chat-subtitle__item">HC {{ $selectedConversation['patient_hc_number'] }}</span>
                                        @endif
                                        @php
                                            $attrSrc = $selectedConversation['attribution_source_category'] ?? null;
                                            $attrHead = $selectedConversation['attribution_headline'] ?? null;
                                        @endphp
                                        @if($attrSrc === 'ad')
                                            <span class="wa-v2-chat-subtitle__dot">·</span>
                                            <span class="wa-v2-origin-badge wa-v2-origin-badge--ad"
                                                  title="{{ $attrHead ? 'Anuncio: '.$attrHead : 'Desde Meta Ads' }}">
                                                🎯 {{ $attrHead ? mb_substr($attrHead, 0, 40) : 'Meta Ads' }}
                                            </span>
                                        @elseif($attrSrc === 'organic_direct')
                                            <span class="wa-v2-chat-subtitle__dot">·</span>
                                            <span class="wa-v2-origin-badge wa-v2-origin-badge--organic">🌐 Orgánico</span>
                                        @elseif($attrSrc === 'campaign_outbound')
                                            <span class="wa-v2-chat-subtitle__dot">·</span>
                                            <span class="wa-v2-origin-badge wa-v2-origin-badge--campaign">📣 Campaña</span>
                                        @elseif($attrSrc === 'support_operational')
                                            <span class="wa-v2-chat-subtitle__dot">·</span>
                                            <span class="wa-v2-origin-badge wa-v2-origin-badge--support">🔧 Soporte</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="wa-v2-actions__row wa-v2-actions__row--header">
                                @if($canOperateConversation)
                                    <button
                                        type="button"
                                        class="btn btn-primary"
                                        data-wa-action="assign-self"
                                        data-conversation-id="{{ $selectedConversation['id'] }}"
                                        title="Tomar conversación"
                                        aria-label="Tomar conversación">
                                        <span class="wa-v2-icon-label">
                                            <i class="mdi mdi-account-check-outline"></i>
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-success"
                                        data-wa-action="close"
                                        data-conversation-id="{{ $selectedConversation['id'] }}"
                                        title="Resolver conversación"
                                        aria-label="Resolver conversación">
                                        <span class="wa-v2-icon-label">
                                            <i class="mdi mdi-check-circle-outline"></i>
                                            <span class="d-none d-md-inline ms-1">Resolver</span>
                                        </span>
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="wa-v2-toolbar">
                            <div class="wa-v2-toolbar__badges">
                                <span class="wa-v2-pill"><i class="mdi mdi-map-marker-path"></i> {{ $selectedConversation['operational_status_label'] ?? 'Sin estado' }}</span>
                                <span class="wa-v2-pill"><i class="mdi {{ $selectedPriorityIcon }}"></i> Prioridad {{ $selectedConversation['priority_level_label'] ?? 'Baja' }}{{ !empty($selectedConversation['priority_score']) ? ' · ' . (int) $selectedConversation['priority_score'] . ' pts' : '' }}</span>
                                <span class="wa-v2-pill"><i class="mdi mdi-account-voice"></i> Último: {{ $selectedConversation['last_message_actor_label'] ?? 'Sin mensajes' }}</span>
                                @if(!empty($selectedConversation['needs_human']))
                                    <span class="wa-v2-pill is-queue"><i
                                            class="mdi mdi-tray-arrow-down"></i> En cola</span>
                                @else
                                    <span class="wa-v2-pill"><i class="mdi mdi-check"></i> {{ $selectedConversation['close_reason_label'] ?? 'Cerrado' }}</span>
                                @endif
                                @if(!empty($selectedConversation['queue_bucket_label']) && !empty($selectedConversation['needs_human']))
                                    <span class="wa-v2-pill"><i class="mdi mdi-shape-outline"></i> {{ $selectedConversation['queue_bucket_label'] }}</span>
                                @endif
                                @if(!empty($selectedConversation['handoff_priority_label']) && !empty($selectedConversation['needs_human']))
                                    <span class="wa-v2-pill"><i class="mdi mdi-flag-outline"></i> {{ $selectedConversation['handoff_priority_label'] }}</span>
                                @endif
                                <span
                                    class="wa-v2-pill {{ ($selectedConversation['messaging_window_state'] ?? '') === 'window_open' ? 'is-unread' : '' }}">
            <i class="mdi {{ ($selectedConversation['messaging_window_state'] ?? '') === 'window_open' ? 'mdi-timer-sand' : 'mdi-file-document-edit-outline' }}"></i>
            {{ $selectedConversation['messaging_window_label'] ?? 'Sin ventana' }}
        </span>
                                <span class="wa-v2-pill"><i class="mdi mdi-tag-outline"></i> {{ $selectedConversation['ownership_label'] ?? 'Sin ownership' }}</span>
                                @if(!empty($selectedConversation['assigned_role_name']))
                                    <span class="wa-v2-pill"><i class="mdi mdi-account-group-outline"></i> {{ $selectedConversation['assigned_role_name'] }}</span>
                                @endif
                            </div>

                            @if($canOperateConversation)
                                <div class="wa-v2-toolbar__actions">
                                    <details class="wa-v2-ops-menu">
                                        <summary>
                                            <i class="mdi mdi-tune-variant"></i>
                                            Gestionar
                                        </summary>
                                        <div class="wa-v2-ops-menu__body">
                                            <div class="wa-v2-actions">
                                                <div class="wa-v2-actions__row">
                                                    <div class="wa-v2-actions__group">
                                                        <select class="form-select" id="wa-v2-transfer-user">
                                                            <option value="">Transferir a...</option>
                                                            @foreach($agents as $agent)
                                                                <option value="{{ $agent['id'] }}">
                                                                    {{ $agent['name'] }}
                                                                    · {{ $agent['presence_status'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>

                                                        <input type="text" class="form-control" id="wa-v2-transfer-note"
                                                               placeholder="Nota de transferencia (opcional)">

                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-primary"
                                                            data-wa-action="transfer"
                                                            data-conversation-id="{{ $selectedConversation['id'] }}"
                                                            title="Transferir conversación"
                                                            aria-label="Transferir conversación">
                                                            <span class="wa-v2-icon-label">
                                                                <i class="mdi mdi-swap-horizontal"></i>
                                                            </span>
                                                        </button>
                                                    </div>

                                                    <div class="wa-v2-actions__group">
                                                        <select class="form-select" id="wa-v2-queue-role">
                                                            <option value="">Enviar a cola de rol...</option>
                                                            @foreach($roleOptions as $role)
                                                                <option
                                                                    value="{{ $role['id'] }}">{{ $role['name'] }}</option>
                                                            @endforeach
                                                        </select>

                                                        <input type="text" class="form-control" id="wa-v2-queue-note"
                                                               placeholder="Nota para la cola (opcional)">

                                                        <button
                                                            type="button"
                                                            class="btn btn-outline-warning"
                                                            data-wa-action="queue-role"
                                                            data-conversation-id="{{ $selectedConversation['id'] }}"
                                                            title="Enviar conversación a cola"
                                                            aria-label="Enviar conversación a cola">
                                                            <span class="wa-v2-icon-label">
                                                                <i class="mdi mdi-tray-arrow-down"></i>
                                                            </span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="wa-v2-chat__body" id="wa-v2-chat-body">
                        <div class="wa-v2-live-banner" id="wa-v2-live-banner" hidden>
                            <div id="wa-v2-live-banner-text">Hay mensajes nuevos en esta conversación.</div>
                        </div>
                        <div id="wa-v2-message-list" class="wa-v2-message-stack">
                            @php $lastMsgDate = null; @endphp
                            @foreach(($selectedConversation['messages'] ?? []) as $message)
                                @php
                                    $msgDateStr = '';
                                    if (!empty($message['message_timestamp'])) {
                                        try {
                                            $msgCarbon = \Carbon\Carbon::parse($message['message_timestamp']);
                                            $msgDateStr = $msgCarbon->toDateString();
                                            if ($msgDateStr !== $lastMsgDate) {
                                                $lastMsgDate = $msgDateStr;
                                                $today = \Carbon\Carbon::today()->toDateString();
                                                $yesterday = \Carbon\Carbon::yesterday()->toDateString();
                                                $dividerLabel = match($msgDateStr) {
                                                    $today     => 'Hoy',
                                                    $yesterday => 'Ayer',
                                                    default    => $msgCarbon->format('d/m/Y'),
                                                };
                                            } else {
                                                $dividerLabel = null;
                                            }
                                        } catch (\Exception $e) {
                                            $dividerLabel = null;
                                        }
                                    } else {
                                        $dividerLabel = null;
                                    }
                                @endphp
                                @if($dividerLabel !== null)
                                    <div class="wa-v2-date-divider" data-date="{{ $msgDateStr }}">{{ $dividerLabel }}</div>
                                @endif
                                @php
                                    $msgDir    = $message['direction'] ?? 'inbound';
                                    $msgStatus = $message['status'] ?? '';
                                    $tickIcon  = match ($msgStatus) {
                                        'read'      => '✓✓',
                                        'delivered' => '✓✓',
                                        'sent'      => '✓',
                                        'failed'    => '⚠',
                                        'pending'   => '○',
                                        default     => '',
                                    };
                                    $tickClass = match ($msgStatus) {
                                        'read'      => 'read',
                                        'delivered' => 'delivered',
                                        'sent'      => 'sent',
                                        'failed'    => 'failed',
                                        'pending'   => 'pending',
                                        default     => '',
                                    };
                                @endphp
                                <div
                                    class="wa-v2-message {{ $msgDir === 'outbound' ? 'is-outbound' : '' }}"
                                    data-message-id="{{ (int) ($message['id'] ?? 0) }}"
                                    data-status="{{ $msgStatus }}">
                                    <div class="wa-v2-message__body">{!! $formatWaBody($message['body'] ?: '[' . ($message['message_type'] ?? 'mensaje') . ']') !!}</div>
                                    @if(!empty($message['media']) && is_array($message['media']))
                                        @php
                                            $media = $message['media'];
                                            $mediaLabel = match ($message['message_type'] ?? '') {
                                                'image' => 'Imagen',
                                                'video' => 'Video',
                                                'document' => 'Documento',
                                                'audio' => !empty($media['voice']) ? 'Voice note' : 'Audio',
                                                default => 'Archivo',
                                            };
                                        @endphp
                                        <div class="wa-v2-media-card">
                                            <div class="wa-v2-media-card__title">{{ $mediaLabel }}</div>
                                            @if(!empty($media['filename']))
                                                <div class="wa-v2-media-card__meta">{{ $media['filename'] }}</div>
                                            @endif
                                            @if(!empty($media['mime_type']))
                                                <div class="wa-v2-media-card__meta">{{ $media['mime_type'] }}</div>
                                            @endif
                                            @if(($message['message_type'] ?? '') === 'image' && !empty($media['download_url']))
                                                <div>
                                                    <img src="{{ $media['download_url'] }}"
                                                         alt="{{ $media['filename'] ?: 'Imagen de WhatsApp' }}"
                                                         style="max-width: 240px; width: 100%; border-radius: 10px; display: block;">
                                                </div>
                                            @endif
                                            @if(($message['message_type'] ?? '') === 'video' && !empty($media['download_url']))
                                                <div>
                                                    <video controls preload="metadata"
                                                           style="max-width: 280px; width: 100%; border-radius: 10px;">
                                                        <source src="{{ $media['download_url'] }}"
                                                                type="{{ $media['mime_type'] ?: 'video/mp4' }}">
                                                    </video>
                                                </div>
                                            @endif
                                            @if(($message['message_type'] ?? '') === 'audio' && !empty($media['download_url']))
                                                <div>
                                                    <audio controls preload="metadata" style="width: 100%;">
                                                        <source src="{{ $media['download_url'] }}"
                                                                type="{{ $media['mime_type'] ?: 'audio/ogg' }}">
                                                    </audio>
                                                </div>
                                            @endif
                                            @if(!empty($media['download_url']))
                                                <div>
                                                    <a href="{{ $media['download_url'] }}" target="_blank"
                                                       rel="noopener"
                                                       class="btn btn-outline-secondary btn-sm">
                                                        Abrir media
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                    <div class="wa-v2-message__meta">
                                        @if(!empty($message['message_timestamp']))
                                            <span data-ts="{{ $message['message_timestamp'] }}" class="wa-v2-msg-time"></span>
                                        @endif
                                        @if($msgDir === 'outbound' && $tickIcon !== '')
                                            <span class="wa-v2-msg-tick wa-v2-msg-tick--{{ $tickClass }}"
                                                  title="{{ ucfirst($msgStatus) }}">{{ $tickIcon }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="wa-v2-compose">
                        <form
                            id="wa-v2-send-form"
                            data-conversation-id="{{ $selectedConversation['id'] }}"
                            data-latest-message-id="{{ (int) collect($selectedConversation['messages'] ?? [])->max('id') }}">
                            @php
                                $wState = $selectedConversation['messaging_window_state'] ?? '';
                                $wBarColor = $wState === 'window_open' ? '#dcfce7' : '#fef3c7';
                                $wBarText  = $wState === 'window_open' ? '#166534' : '#78350f';
                                $wBarIcon  = $wState === 'window_open' ? 'mdi-timer-sand' : 'mdi-file-document-edit-outline';
                                $wBarNote  = $wState === 'window_open'
                                    ? 'Ventana 24h activa — puedes responder libremente.'
                                    : 'Ventana cerrada — solo puedes iniciar con plantilla aprobada.';
                            @endphp
                            <div class="mb-8" style="display:flex;align-items:center;gap:7px;padding:6px 10px;border-radius:10px;background:{{ $wBarColor }};color:{{ $wBarText }};font-size:12px;font-weight:600;">
                                <i class="mdi {{ $wBarIcon }}" style="font-size:15px;"></i>
                                <span>{{ $wBarNote }}</span>
                            </div>
                            <div class="wa-v2-compose-grid">
                                <div class="wa-v2-compose-attachment" id="wa-v2-compose-attachment"
                                     style="display:none;">
                                    <i class="mdi mdi-paperclip"></i>
                                    <div class="wa-v2-compose-attachment__meta">
                                        <div class="wa-v2-compose-attachment__name" id="wa-v2-attachment-name"></div>
                                        <div class="wa-v2-compose-attachment__type" id="wa-v2-attachment-type"></div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm"
                                            id="wa-v2-clear-media" {{ $canReplyHere ? '' : 'disabled' }}>
                                        Quitar
                                    </button>
                                </div>
                                <div class="wa-v2-recording-meta" id="wa-v2-recording-meta">
                                    <i class="mdi mdi-microphone"></i>
                                    <span id="wa-v2-recording-label">Grabando 00:00</span>
                                </div>
                                <div class="wa-v2-compose-grid__hidden">
                                    <input type="hidden" id="wa-v2-message-type" value="text">
                                    <input type="hidden" id="wa-v2-media-url">
                                    <input type="hidden" class="form-control" id="wa-v2-media-filename"
                                           placeholder="Nombre archivo" {{ $canReplyHere ? '' : 'disabled' }}>
                                    <input type="file" class="wa-v2-compose-hidden"
                                           id="wa-v2-media-file" {{ $canReplyHere ? '' : 'disabled' }}>
                                    <input type="hidden" id="wa-v2-media-disk">
                                    <input type="hidden" id="wa-v2-media-path">
                                    <input type="hidden" id="wa-v2-media-mime-type">
                                </div>
                                <div class="wa-v2-upload-status" id="wa-v2-upload-status"></div>
                                <div class="wa-v2-upload-progress" id="wa-v2-upload-progress">
                                    <div class="wa-v2-upload-progress__bar" id="wa-v2-upload-progress-bar"></div>
                                </div>
                                <div class="wa-v2-composer-inputgroup">
                                    <div class="wa-v2-compose-actions">
                                        <details class="wa-v2-attachment-menu">
                                            <summary title="Más opciones" aria-label="Más opciones">
                                                <i class="mdi mdi-plus"></i>
                                            </summary>
                                            <div class="wa-v2-attachment-menu__items">
                                                <div class="wa-v2-attachment-menu__section">
                                                    <div class="wa-v2-attachment-menu__title">Plantilla</div>
                                                    <button type="button"
                                                            class="wa-v2-chip"
                                                            data-wa-open-start-template="1"
                                                            data-wa-number="{{ $selectedConversation['wa_number'] }}"
                                                            data-wa-contact-name="{{ $selectedConversation['display_name'] ?: $selectedConversation['wa_number'] }}"
                                                            data-wa-patient-name="{{ $selectedConversation['patient_full_name'] ?? '' }}"
                                                            data-wa-hc-number="{{ $selectedConversation['patient_hc_number'] ?? '' }}"
                                                            title="Enviar plantilla">
                                                        <i class="mdi mdi-file-document-edit-outline"></i> Enviar plantilla
                                                    </button>
                                                </div>

                                                <div class="wa-v2-attachment-menu__section">
                                                    <div class="wa-v2-attachment-menu__title">Adjuntar</div>
                                                    <div class="wa-v2-attachment-menu__media">
                                                        <button type="button" class="wa-v2-compose-action"
                                                                data-wa-picker="image"
                                                                title="Enviar imagen" {{ $canReplyHere ? '' : 'disabled' }}>
                                                            <i class="mdi mdi-image-outline"></i>
                                                        </button>
                                                        <button type="button" class="wa-v2-compose-action"
                                                                data-wa-picker="video"
                                                                title="Enviar video" {{ $canReplyHere ? '' : 'disabled' }}>
                                                            <i class="mdi mdi-video-outline"></i>
                                                        </button>
                                                        <button type="button" class="wa-v2-compose-action"
                                                                data-wa-picker="document"
                                                                title="Enviar documento" {{ $canReplyHere ? '' : 'disabled' }}>
                                                            <i class="mdi mdi-file-document-outline"></i>
                                                        </button>
                                                        <button type="button" class="wa-v2-compose-action"
                                                                data-wa-picker="audio"
                                                                title="Enviar voice note o audio" {{ $canReplyHere ? '' : 'disabled' }}>
                                                            <i class="mdi mdi-microphone-outline"></i>
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="wa-v2-attachment-menu__section">
                                                    <div class="wa-v2-attachment-menu__title">Respuestas rápidas</div>
                                                    @if(!empty($quickReplies))
                                                        <div class="wa-v2-chip-list">
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
                                                        <div class="text-muted" style="font-size:12px;">Sin respuestas
                                                            rápidas.
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </details>
                                    </div>
                                    <textarea class="form-control" id="wa-v2-message-input" rows="2"
                                              placeholder="Texto o caption" {{ $canReplyHere ? '' : 'disabled' }}></textarea>
                                    <button type="submit"
                                            class="btn btn-primary wa-v2-composer-send" {{ $canReplyHere ? '' : 'disabled' }}>
                                        Enviar
                                    </button>
                                </div>
                            </div>
                            <div id="wa-v2-send-feedback" class="mt-10 text-muted" style="font-size:10px;"></div>
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

            {{-- ── Columna 3: Herramientas ────────────────────────────────── --}}
            <div class="wa-v2-panel wa-v2-herramientas">
                <div class="wa-v2-panel__header">
                    <div class="wa-v2-sideheading" style="margin-bottom:0;">
                        <div>
                            <div class="wa-v2-sideheading__title"><i class="mdi mdi-tools" style="font-size:16px;"></i> Herramientas</div>
                            @if($selectedConversation)
                                <div class="wa-v2-sideheading__meta">{{ $selectedConversation['display_name'] ?: $selectedConversation['wa_number'] }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($selectedConversation)
                    <div class="wa-v2-herramientas__scroll">

                        {{-- Trazabilidad --}}
                        <details class="wa-v2-collapse wa-v2-tool-card" id="wa-v2-trail-card" open>
                            <summary>
                                <div>
                                    <div class="fw-700">Trazabilidad</div>
                                    <div class="text-muted" style="font-size:12px;">Asignaciones y derivaciones</div>
                                </div>
                            </summary>
                            <div class="wa-v2-collapse__body">
                                <div id="wa-v2-trail-list" style="font-size:.82rem;">
                                    <div class="text-muted" style="padding:8px 0;">Cargando trazabilidad…</div>
                                </div>
                            </div>
                        </details>

                        {{-- Notas internas --}}
                        <details class="wa-v2-collapse wa-v2-tool-card" open>
                            <summary>
                                <div>
                                    <div class="fw-700">Notas internas</div>
                                    <div class="text-muted" style="font-size:12px;">Historial y nueva nota</div>
                                </div>
                            </summary>
                            <div class="wa-v2-collapse__body">
                                <div class="wa-v2-note-list mb-10">
                                    @forelse($conversationNotes as $note)
                                        <div class="wa-v2-note">
                                            <div>{{ $note['body'] ?? '' }}</div>
                                            <div class="wa-v2-note__meta">
                                                {{ $note['author_name'] ?? 'Usuario' }}
                                                @if(!empty($note['created_at']))
                                                    · <span data-ts="{{ $note['created_at'] }}"></span>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-muted" style="font-size:12px;">Sin notas internas en esta conversación.</div>
                                    @endforelse
                                </div>
                                <form id="wa-v2-note-form" data-conversation-id="{{ $selectedConversation['id'] }}">
                                    <div class="input-group">
                                        <textarea class="form-control" id="wa-v2-note-input" rows="2"
                                                  placeholder="Agregar nota interna para el equipo"></textarea>
                                        <button type="submit" class="btn btn-outline-warning btn-sm">Guardar nota</button>
                                    </div>
                                </form>
                            </div>
                        </details>

                        {{-- Administrar respuestas rápidas --}}
                        <details class="wa-v2-collapse wa-v2-tool-card">
                            <summary>
                                <div>
                                    <div class="fw-700">Respuestas rápidas</div>
                                    <div class="text-muted" style="font-size:12px;">Crear snippet reusable</div>
                                </div>
                            </summary>
                            <div class="wa-v2-collapse__body">
                                <form id="wa-v2-quick-reply-form" class="d-grid gap-8">
                                    <input type="text" class="form-control form-control-sm mb-6"
                                           id="wa-v2-quick-title" placeholder="Título">
                                    <input type="text" class="form-control form-control-sm mb-6"
                                           id="wa-v2-quick-shortcut" placeholder="/atajo">
                                    <input type="text" class="form-control form-control-sm mb-6"
                                           id="wa-v2-quick-body" placeholder="Texto reusable">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Guardar respuesta rápida</button>
                                    </div>
                                </form>
                            </div>
                        </details>

                        {{-- Acciones administrativas --}}
                        <details style="padding: 4px 0 8px;">
                            <summary class="text-muted" style="cursor:pointer;font-size:12px;font-weight:700;">
                                Acciones administrativas
                            </summary>
                            <div style="padding-top:8px;">
                                <button type="button"
                                        id="wa-v2-baja-btn"
                                        class="waves-effect waves-light btn btn-outline-secondary btn-sm"
                                        data-placeholder="Cerrar seguimiento y generar lead"
                                        data-conversation-id="{{ $selectedConversation['id'] }}">
                                    <i class="mdi mdi-archive-arrow-down-outline"></i>
                                    Cerrar seguimiento
                                </button>
                            </div>
                        </details>

                    </div>
                @else
                    <div class="wa-v2-empty">
                        <div class="text-muted" style="font-size:13px;">Selecciona una conversación para ver las herramientas.</div>
                    </div>
                @endif
            </div>

            {{-- Modal: Cerrar seguimiento --}}
            <div class="wa-v2-modal-backdrop" id="wa-v2-baja-modal" style="display:none;">
                <div class="wa-v2-modal" style="max-width:480px;">
                    <div class="wa-v2-modal__header">
                        <div class="fw-700" style="font-size:16px;">📋 Cerrar seguimiento</div>
                        <button type="button" class="btn-close" id="wa-v2-baja-close"></button>
                    </div>
                    <div class="wa-v2-modal__body">
                        <p class="text-muted" style="font-size:13px; margin-bottom:14px;">
                            Esto no elimina al paciente ni el historial. Cerrará la conversación activa y generará un lead de seguimiento.
                        </p>
                        <div class="mb-12">
                            <label class="form-label fw-700" style="font-size:13px;">Motivo del cierre <span class="text-danger">*</span></label>
                            <textarea id="wa-v2-baja-motivo" class="form-control" rows="3"
                                      placeholder="Ej: Paciente no responde, requiere seguimiento posterior, cambió de número..."></textarea>
                        </div>
                        <div id="wa-v2-baja-feedback" class="text-danger" style="font-size:12px;"></div>
                    </div>
                    <div class="wa-v2-modal__footer">
                        <button type="button" class="btn btn-outline-secondary" id="wa-v2-baja-cancel">Cancelar</button>
                        <button type="button" class="btn btn-primary" id="wa-v2-baja-submit">Cerrar seguimiento</button>
                    </div>
                </div>
            </div>

        </div>

    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // ── Shell height: ajusta al espacio real disponible bajo el navbar/pagebar
            (function () {
                const shell = document.querySelector('.wa-v2-shell');
                if (!shell) return;
                const setHeight = function () {
                    const top = shell.getBoundingClientRect().top + window.scrollY;
                    const available = window.innerHeight - top - 8; // 8px bottom padding
                    if (available > 200) {
                        shell.style.height = available + 'px';
                        shell.style.maxHeight = available + 'px';
                    }
                };
                setHeight();
                window.addEventListener('resize', setHeight);
            }());

            // Inicializar tooltips de Bootstrap en los tabs de filtro
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    new bootstrap.Tooltip(el, { trigger: 'hover', boundary: 'window' });
                }
            });

            // Render all UTC timestamps using the browser's local timezone
            document.querySelectorAll('[data-ts]').forEach(function (el) {
                const raw = el.getAttribute('data-ts');
                if (!raw) return;
                const date = new Date(raw);
                if (Number.isNaN(date.getTime())) return;
                el.textContent = new Intl.DateTimeFormat('es-EC', {
                    day: '2-digit',
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).format(date).replace(',', '');
            });

            const form = document.getElementById('wa-v2-send-form');
            const textarea = document.getElementById('wa-v2-message-input');
            const messageTypeSelect = document.getElementById('wa-v2-message-type');
            const mediaUrlInput = document.getElementById('wa-v2-media-url');
            const mediaFilenameInput = document.getElementById('wa-v2-media-filename');
            const mediaFileInput = document.getElementById('wa-v2-media-file');
            const mediaDiskInput = document.getElementById('wa-v2-media-disk');
            const mediaPathInput = document.getElementById('wa-v2-media-path');
            const mediaMimeTypeInput = document.getElementById('wa-v2-media-mime-type');
            const attachmentBox = document.getElementById('wa-v2-compose-attachment');
            const attachmentName = document.getElementById('wa-v2-attachment-name');
            const attachmentType = document.getElementById('wa-v2-attachment-type');
            const clearMediaButton = document.getElementById('wa-v2-clear-media');
            const uploadStatus = document.getElementById('wa-v2-upload-status');
            const uploadProgress = document.getElementById('wa-v2-upload-progress');
            const uploadProgressBar = document.getElementById('wa-v2-upload-progress-bar');
            const recordingMeta = document.getElementById('wa-v2-recording-meta');
            const recordingLabel = document.getElementById('wa-v2-recording-label');
            const feedback = document.getElementById('wa-v2-send-feedback');
            const chatBody = document.getElementById('wa-v2-chat-body');
            const messageList = document.getElementById('wa-v2-message-list');
            const liveBanner = document.getElementById('wa-v2-live-banner');
            const liveBannerText = document.getElementById('wa-v2-live-banner-text');
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
            const startChatModal = document.getElementById('wa-v2-start-chat-modal');
            const startChatOpenButton = document.getElementById('wa-v2-open-start-chat');
            const startChatCloseButton = document.getElementById('wa-v2-close-start-chat');
            const startChatSearch = document.getElementById('wa-v2-start-search');
            const startChatSearchButton = document.getElementById('wa-v2-start-search-button');
            const startChatResults = document.getElementById('wa-v2-start-results');
            const startChatNumber = document.getElementById('wa-v2-start-number');
            const startChatContactName = document.getElementById('wa-v2-start-contact-name');
            const startChatPatientName = document.getElementById('wa-v2-start-patient-name');
            const startChatHc = document.getElementById('wa-v2-start-hc');
            const startChatTemplate = document.getElementById('wa-v2-start-template');
            const startChatTemplateVariables = document.getElementById('wa-v2-start-template-variables');
            const startChatTemplateVariablesFields = document.getElementById('wa-v2-start-template-variables-fields');
            const startChatTemplatePreviewBody = document.getElementById('wa-v2-start-template-preview-body');
            const startChatSubmit = document.getElementById('wa-v2-start-submit');
            const startChatFeedback = document.getElementById('wa-v2-start-chat-feedback');
            const presenceSelect = document.getElementById('wa-v2-presence');
            const requeueExpiredButton = document.getElementById('wa-v2-requeue-expired');
            const audioPickerButton = document.querySelector('[data-wa-picker="audio"]');
            const attachmentMenu = document.querySelector('.wa-v2-attachment-menu');
            const opsMenu = document.querySelector('.wa-v2-ops-menu');
            const tabsScroller = document.getElementById('wa-v2-filter-tabs');
            const realtimeConfig = window.MEDF_PusherConfig || {};
            let requestInFlight = false;
            let pollingInFlight = false;
            let latestMessageId = Number(form ? (form.getAttribute('data-latest-message-id') || 0) : 0);
            let mediaRecorder = null;
            let recorderStream = null;
            let recordedChunks = [];
            let recordingStartedAt = null;
            let recordingTimer = null;
            const defaultTitle = document.title;
            let unseenMessageCount = 0;
            let startChatSearchInFlight = false;
            let startChatSearchTimer = null;
            const startChatTemplates = @json($templateOptions);

            const initTabsScroller = function () {
                if (!tabsScroller) {
                    return;
                }

                const shell = tabsScroller.closest('.wa-v2-tabs-shell');
                if (!shell) {
                    return;
                }

                const leftButton = shell.querySelector('[data-wa-tabs-nav="left"]');
                const rightButton = shell.querySelector('[data-wa-tabs-nav="right"]');
                if (!leftButton || !rightButton) {
                    return;
                }

                const updateButtons = function () {
                    const maxScrollLeft = Math.max(0, tabsScroller.scrollWidth - tabsScroller.clientWidth);
                    leftButton.disabled = tabsScroller.scrollLeft <= 4;
                    rightButton.disabled = tabsScroller.scrollLeft >= (maxScrollLeft - 4);
                };

                const scrollTabs = function (direction) {
                    const step = Math.max(220, Math.round(tabsScroller.clientWidth * 0.7));
                    tabsScroller.scrollBy({left: direction * step, behavior: 'smooth'});
                };

                leftButton.addEventListener('click', function () {
                    scrollTabs(-1);
                });

                rightButton.addEventListener('click', function () {
                    scrollTabs(1);
                });

                tabsScroller.addEventListener('scroll', updateButtons, {passive: true});
                window.addEventListener('resize', updateButtons);

                tabsScroller.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowLeft') {
                        event.preventDefault();
                        scrollTabs(-1);
                    }

                    if (event.key === 'ArrowRight') {
                        event.preventDefault();
                        scrollTabs(1);
                    }
                });

                updateButtons();
            };

            initTabsScroller();

            const setFeedback = function (message, tone) {
                if (!feedback) {
                    return;
                }

                feedback.textContent = message;
                feedback.className = `mt-10 text-${tone}`;
            };

            const setStartChatFeedback = function (message, tone) {
                if (!startChatFeedback) {
                    return;
                }

                startChatFeedback.className = 'alert mt-15 mb-0';
                startChatFeedback.classList.add(
                    tone === 'danger'
                        ? 'alert-danger'
                        : (tone === 'success' ? 'alert-success' : 'alert-light')
                );
                startChatFeedback.textContent = message;
            };

            const selectedStartTemplateMeta = function () {
                if (!startChatTemplate) {
                    return null;
                }

                const templateId = Number(startChatTemplate.value || 0);
                if (!templateId) {
                    return null;
                }

                return Array.isArray(startChatTemplates)
                    ? (startChatTemplates.find(function (template) {
                        return Number(template.id || 0) === templateId;
                    }) || null)
                    : null;
            };

            const currentStartTemplateValues = function () {
                if (!startChatTemplateVariablesFields) {
                    return [];
                }

                return Array.from(startChatTemplateVariablesFields.querySelectorAll('[data-wa-template-variable]')).map(function (input) {
                    return (input.value || '').trim();
                });
            };

            const renderStartTemplatePreview = function () {
                if (!startChatTemplatePreviewBody) {
                    return;
                }

                const template = selectedStartTemplateMeta();
                if (!template) {
                    startChatTemplatePreviewBody.textContent = 'Selecciona un template para revisar el mensaje final.';
                    return;
                }

                const examples = Array.isArray(template.variable_examples) ? template.variable_examples : [];
                const values = currentStartTemplateValues();
                const body = String(template.body_text || '').trim();

                if (body === '') {
                    startChatTemplatePreviewBody.textContent = 'Este template no tiene cuerpo de mensaje registrado.';
                    return;
                }

                startChatTemplatePreviewBody.textContent = body.replace(/\{\{\s*(\d+)\s*\}\}/g, function (match, index) {
                    const position = Math.max(0, Number(index) - 1);
                    return values[position] || examples[position] || match;
                });
            };

            const renderStartTemplateVariables = function () {
                if (!startChatTemplateVariables || !startChatTemplateVariablesFields) {
                    return;
                }

                const template = selectedStartTemplateMeta();
                const variables = Array.isArray(template?.variables) ? template.variables : [];
                const examples = Array.isArray(template?.variable_examples) ? template.variable_examples : [];
                const isLocationHeader = (template?.header_type || '') === 'location';

                if (variables.length === 0 && !isLocationHeader) {
                    startChatTemplateVariables.classList.add('d-none');
                    startChatTemplateVariablesFields.innerHTML = '';
                    renderStartTemplatePreview();
                    return;
                }

                startChatTemplateVariables.classList.remove('d-none');
                const sedeSelectHtml = isLocationHeader ? `
                    <div class="col-12">
                        <label class="form-label">Sede de la cita <span class="text-danger">*</span></label>
                        <select class="form-control" id="wa-v2-start-location-sede">
                            <option value="">— Selecciona la sede —</option>
                            <option value="villa_club">Villa Club</option>
                            <option value="ceibos">Ceibos</option>
                            <option value="matriz">Matriz</option>
                        </select>
                        <small class="text-muted">Requerido para el header de ubicación GPS.</small>
                    </div>
                ` : '';

                startChatTemplateVariablesFields.innerHTML = sedeSelectHtml + variables.map(function (variable, index) {
                    const example = (examples[index] || '').trim();
                    const placeholder = example || `Valor para ${variable}`;
                    return `
                        <div class="col-12">
                            <label class="form-label" for="wa-v2-start-template-variable-${index}">
                                Variable ${index + 1} <span class="text-muted">${variable}</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="wa-v2-start-template-variable-${index}"
                                   data-wa-template-variable="${index}"
                                   placeholder="${placeholder.replace(/"/g, '&quot;')}">
                        </div>
                    `;
                }).join('');

                startChatTemplateVariablesFields.querySelectorAll('[data-wa-template-variable]').forEach(function (input) {
                    input.addEventListener('input', renderStartTemplatePreview);
                });
                renderStartTemplatePreview();
            };

            const openStartChatModal = function (payload) {
                if (!startChatModal) {
                    return;
                }

                if (payload && typeof payload === 'object') {
                    if (startChatNumber) startChatNumber.value = payload.waNumber || '';
                    if (startChatContactName) startChatContactName.value = payload.contactName || '';
                    if (startChatPatientName) startChatPatientName.value = payload.patientName || '';
                    if (startChatHc) startChatHc.value = payload.hcNumber || '';
                }

                renderStartTemplateVariables();
                startChatModal.classList.add('is-open');
                startChatModal.setAttribute('aria-hidden', 'false');
                setStartChatFeedback('Selecciona un template aprobado y dispara el primer mensaje.', 'muted');
            };

            const closeStartChatModal = function () {
                if (!startChatModal) {
                    return;
                }

                startChatModal.classList.remove('is-open');
                startChatModal.setAttribute('aria-hidden', 'true');
            };

            const renderStartChatResults = function (rows) {
                if (!startChatResults) {
                    return;
                }

                if (!Array.isArray(rows) || rows.length === 0) {
                    startChatResults.innerHTML = '<div class="text-muted" style="font-size:12px;">Sin resultados con número WhatsApp utilizable.</div>';
                    return;
                }

                startChatResults.innerHTML = rows.map(function (row, index) {
                    const title = row.display_name || row.wa_number || 'Contacto';
                    const meta = [row.wa_number || '', row.hc_number ? `HC ${row.hc_number}` : ''].filter(Boolean).join(' · ');
                    const sourceMap = {
                        conversation: 'Conversación existente',
                        patient_data: 'Paciente',
                        consent: 'Consentimiento',
                        crm_lead: 'CRM',
                    };
                    const sourceLabel = sourceMap[row.source] || 'Fuente';
                    const isExisting = row.source === 'conversation' && row.id;

                    const actions = isExisting
                        ? `<a href="/v2/whatsapp/chat?conversation=${row.id}&filter={{ $selectedFilter }}"
                              class="btn btn-primary btn-sm"
                              style="white-space:nowrap;">Abrir chat</a>
                           <button type="button"
                                   class="btn btn-outline-secondary btn-sm"
                                   data-wa-select-contact="1"
                                   data-wa-number="${row.wa_number || ''}"
                                   data-wa-contact-name="${title}"
                                   data-wa-patient-name="${title}"
                                   data-wa-hc-number="${row.hc_number || ''}">Nueva plantilla</button>`
                        : `<button type="button"
                                   class="btn btn-outline-secondary btn-sm"
                                   data-wa-select-contact="1"
                                   data-wa-number="${row.wa_number || ''}"
                                   data-wa-contact-name="${title}"
                                   data-wa-patient-name="${title}"
                                   data-wa-hc-number="${row.hc_number || ''}">Usar</button>`;

                    return `
                        <div class="wa-v2-picker-card ${index === 0 ? 'is-active' : ''} ${isExisting ? 'wa-v2-picker-card--existing' : ''}"
                             data-wa-number="${row.wa_number || ''}">
                            <div>
                                <div class="fw-700">${title}</div>
                                <div class="text-muted" style="font-size:12px;">${meta || 'Sin meta'}</div>
                            </div>
                            <div class="d-flex align-items-center gap-8">
                                <span class="wa-v2-picker-card__source">${sourceLabel}</span>
                                ${actions}
                            </div>
                        </div>
                    `;
                }).join('');

                startChatResults.querySelectorAll('[data-wa-select-contact]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        startChatResults.querySelectorAll('.wa-v2-picker-card').forEach(function (card) {
                            card.classList.remove('is-active');
                        });
                        button.closest('.wa-v2-picker-card')?.classList.add('is-active');
                        if (startChatNumber) startChatNumber.value = button.getAttribute('data-wa-number') || '';
                        if (startChatContactName) startChatContactName.value = button.getAttribute('data-wa-contact-name') || '';
                        if (startChatPatientName) startChatPatientName.value = button.getAttribute('data-wa-patient-name') || '';
                        if (startChatHc) startChatHc.value = button.getAttribute('data-wa-hc-number') || '';
                    });
                });
            };

            const runContactSearch = async function () {
                if (!startChatSearch || !startChatResults || startChatSearchInFlight) {
                    return;
                }

                const query = (startChatSearch.value || '').trim();
                if (!query) {
                    renderStartChatResults([]);
                    setStartChatFeedback('Escribe celular, HC o nombres para buscar.', 'muted');
                    return;
                }

                startChatSearchInFlight = true;
                setStartChatFeedback('Buscando contacto...', 'muted');

                try {
                    const response = await fetch(`/v2/whatsapp/api/contacts/search?q=${encodeURIComponent(query)}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                        throw new Error(data.error || 'No fue posible buscar contactos.');
                    }

                    renderStartChatResults(Array.isArray(data.data) ? data.data : []);
                    setStartChatFeedback('Selecciona un resultado o ajusta el número manualmente.', 'success');
                } catch (error) {
                    renderStartChatResults([]);
                    setStartChatFeedback(error.message || 'No fue posible buscar contactos.', 'danger');
                } finally {
                    startChatSearchInFlight = false;
                }
            };

            const submitStartChat = async function () {
                if (!startChatNumber || !startChatTemplate || requestInFlight) {
                    return;
                }

                const waNumber = (startChatNumber.value || '').trim();
                const templateId = Number(startChatTemplate.value || 0);
                if (!waNumber) {
                    setStartChatFeedback('Debes indicar un número WhatsApp.', 'danger');
                    return;
                }
                if (!templateId) {
                    setStartChatFeedback('Debes seleccionar un template aprobado.', 'danger');
                    return;
                }

                requestInFlight = true;
                setStartChatFeedback('Iniciando conversación con plantilla...', 'muted');

                try {
                    const response = await fetch('/v2/whatsapp/api/conversations/start-template', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        body: JSON.stringify({
                            wa_number: waNumber,
                            template_id: templateId,
                            contact_name: (startChatContactName?.value || '').trim(),
                            patient_full_name: (startChatPatientName?.value || '').trim(),
                            patient_hc_number: (startChatHc?.value || '').trim(),
                            template_variables: startChatTemplateVariablesFields
                                ? Array.from(startChatTemplateVariablesFields.querySelectorAll('[data-wa-template-variable]')).map(function (input) {
                                    return (input.value || '').trim();
                                })
                                : [],
                            location_sede: (document.getElementById('wa-v2-start-location-sede')?.value || '').trim(),
                        })
                    });
                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                        throw new Error(data.error || 'No fue posible iniciar la conversación.');
                    }

                    const conversationId = Number(data?.data?.conversation?.id || 0);
                    setStartChatFeedback('Conversación iniciada. Abriendo chat...', 'success');
                    if (conversationId > 0) {
                        const target = new URL('/v2/whatsapp/chat', window.location.origin);
                        target.searchParams.set('conversation', String(conversationId));
                        target.searchParams.set('filter', '{{ $selectedFilter }}');
                        target.searchParams.set('search', '{{ $search }}');
                        @if($dateFrom !== '')
                        target.searchParams.set('date_from', '{{ $dateFrom }}');
                        @endif
                        @if($dateTo !== '')
                        target.searchParams.set('date_to', '{{ $dateTo }}');
                        @endif
                        @if($selectedAgentId !== null)
                        target.searchParams.set('agent_id', '{{ $selectedAgentId }}');
                        @endif
                        @if($selectedRoleId !== null)
                        target.searchParams.set('role_id', '{{ $selectedRoleId }}');
                        @endif
                            window.location.href = target.toString();
                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    setStartChatFeedback(error.message || 'No fue posible iniciar la conversación.', 'danger');
                } finally {
                    requestInFlight = false;
                }
            };

            const setUploadStatus = function (message, tone) {
                if (!uploadStatus) {
                    return;
                }

                uploadStatus.textContent = message;
                uploadStatus.className = `wa-v2-upload-status text-${tone}`;
            };

            const setUploadProgress = function (percent, visible) {
                if (!uploadProgress || !uploadProgressBar) {
                    return;
                }

                uploadProgress.classList.toggle('is-visible', Boolean(visible));
                uploadProgressBar.style.width = `${Math.max(0, Math.min(100, Number(percent || 0)))}%`;
            };

            const formatDuration = function (seconds) {
                const total = Math.max(0, Number(seconds || 0));
                const minutes = Math.floor(total / 60);
                const remainder = total % 60;

                return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
            };

            const setRecordingState = function (visible, seconds) {
                if (!recordingMeta || !recordingLabel) {
                    return;
                }

                recordingMeta.classList.toggle('is-visible', Boolean(visible));
                recordingLabel.textContent = visible
                    ? `Grabando ${formatDuration(seconds)}`
                    : 'Grabando 00:00';
            };

            const stopRecordingTimer = function () {
                if (recordingTimer) {
                    window.clearInterval(recordingTimer);
                    recordingTimer = null;
                }

                recordingStartedAt = null;
                setRecordingState(false, 0);
            };

            const setLiveBanner = function (message, visible) {
                if (!liveBanner) {
                    return;
                }

                if (liveBannerText) {
                    liveBannerText.textContent = message || 'Hay mensajes nuevos en esta conversación.';
                }

                liveBanner.hidden = !visible;
            };

            const resetNotificationHint = function () {
                unseenMessageCount = 0;
                document.title = defaultTitle;
                setLiveBanner('', false);
            };

            const escapeHtml = function (value) {
                return String(value || '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            };

            // WhatsApp markdown → HTML (mirrors server-side $formatWaBody)
            const formatWaMarkdown = function (text) {
                let s = escapeHtml(text);
                s = s.replace(/\*([^*\r\n]+)\*/g,  '<strong>$1</strong>');
                s = s.replace(/_([^_\r\n]+)_/g,    '<em>$1</em>');
                s = s.replace(/~([^~\r\n]+)~/g,    '<del>$1</del>');
                s = s.replace(/`([^`\r\n]+)`/g,    '<code>$1</code>');
                s = s.replace(/\r\n|\r|\n/g,        '<br>');
                return s;
            };

            const formatTimestamp = function (value) {
                if (!value) {
                    return '';
                }

                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return '';
                }

                return new Intl.DateTimeFormat('es-EC', {
                    day: '2-digit',
                    month: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                }).format(date).replace(',', '');
            };

            const renderMediaCard = function (message) {
                if (!message.media || typeof message.media !== 'object') {
                    return '';
                }

                const media = message.media;
                const messageType = String(message.message_type || '');
                const mediaLabel = ({
                    image: 'Imagen',
                    video: 'Video',
                    document: 'Documento',
                    audio: media.voice ? 'Voice note' : 'Audio',
                })[messageType] || 'Archivo';

                let html = `<div class="wa-v2-media-card"><div class="wa-v2-media-card__title">${escapeHtml(mediaLabel)}</div>`;

                if (media.filename) {
                    html += `<div class="wa-v2-media-card__meta">${escapeHtml(media.filename)}</div>`;
                }
                if (media.mime_type) {
                    html += `<div class="wa-v2-media-card__meta">${escapeHtml(media.mime_type)}</div>`;
                }
                if (messageType === 'image' && media.download_url) {
                    html += `<div><img src="${escapeHtml(media.download_url)}" alt="${escapeHtml(media.filename || 'Imagen de WhatsApp')}" style="max-width: 240px; width: 100%; border-radius: 10px; display: block;"></div>`;
                }
                if (messageType === 'video' && media.download_url) {
                    html += `<div><video controls preload="metadata" style="max-width: 280px; width: 100%; border-radius: 10px;"><source src="${escapeHtml(media.download_url)}" type="${escapeHtml(media.mime_type || 'video/mp4')}"></video></div>`;
                }
                if (messageType === 'audio' && media.download_url) {
                    html += `<div><audio controls preload="metadata" style="width: 100%;"><source src="${escapeHtml(media.download_url)}" type="${escapeHtml(media.mime_type || 'audio/ogg')}"></audio></div>`;
                }
                if (media.download_url) {
                    html += `<div><a href="${escapeHtml(media.download_url)}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Abrir media</a></div>`;
                }

                html += '</div>';

                return html;
            };

            const deliveryTick = function (status, direction) {
                if ((direction || '') !== 'outbound') return '';
                const map = {
                    read:      { icon: '✓✓', cls: 'read' },
                    delivered: { icon: '✓✓', cls: 'delivered' },
                    sent:      { icon: '✓',  cls: 'sent' },
                    failed:    { icon: '⚠',  cls: 'failed' },
                    pending:   { icon: '○',  cls: 'pending' },
                };
                const m = map[status || ''];
                if (!m) return '';
                return `<span class="wa-v2-msg-tick wa-v2-msg-tick--${m.cls}" title="${escapeHtml(status || '')}">${m.icon}</span>`;
            };

            const renderMessageNode = function (message) {
                const wrapper = document.createElement('div');
                const isOutbound = (message.direction || '') === 'outbound';
                wrapper.className = `wa-v2-message${isOutbound ? ' is-outbound' : ''}`;
                wrapper.setAttribute('data-message-id', String(Number(message.id || 0)));
                wrapper.setAttribute('data-status', String(message.status || ''));

                const body = (message.body && String(message.body).trim() !== '')
                    ? formatWaMarkdown(message.body)
                    : `[${escapeHtml(message.message_type || 'mensaje')}]`;

                const formattedTimestamp = formatTimestamp(message.message_timestamp);
                const timeHtml = formattedTimestamp !== ''
                    ? `<span class="wa-v2-msg-time">${escapeHtml(formattedTimestamp)}</span>`
                    : '';
                const tickHtml = deliveryTick(message.status, message.direction);

                const metaHtml = (timeHtml || tickHtml)
                    ? `<div class="wa-v2-message__meta">${timeHtml}${tickHtml}</div>`
                    : '';

                wrapper.innerHTML = `<div class="wa-v2-message__body">${body}</div>${renderMediaCard(message)}${metaHtml}`;

                return wrapper;
            };

            const msgDateKey = function (ts) {
                if (!ts) return null;
                const d = new Date(ts);
                if (Number.isNaN(d.getTime())) return null;
                return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            };

            const dateLabel = function (key) {
                if (!key) return null;
                const today = msgDateKey(new Date());
                const yesterday = msgDateKey(new Date(Date.now() - 864e5));
                if (key === today) return 'Hoy';
                if (key === yesterday) return 'Ayer';
                const [y, m, d] = key.split('-');
                return `${d}/${m}/${y}`;
            };

            const lastRenderedDateKey = function () {
                const dividers = messageList ? messageList.querySelectorAll('.wa-v2-date-divider') : [];
                return dividers.length ? dividers[dividers.length - 1].getAttribute('data-date') : null;
            };

            const appendMessages = function (messages) {
                if (!messageList || !Array.isArray(messages) || messages.length === 0) {
                    return false;
                }

                const shouldStickToBottom = chatBody
                    ? (chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight) < 80
                    : true;

                let lastDateKey = lastRenderedDateKey();

                messages.forEach(function (message) {
                    if (messageList.querySelector(`[data-message-id="${Number(message.id || 0)}"]`)) {
                        return;
                    }

                    const dKey = msgDateKey(message.message_timestamp);
                    if (dKey && dKey !== lastDateKey) {
                        const divider = document.createElement('div');
                        divider.className = 'wa-v2-date-divider';
                        divider.setAttribute('data-date', dKey);
                        divider.textContent = dateLabel(dKey) || dKey;
                        messageList.appendChild(divider);
                        lastDateKey = dKey;
                    }

                    messageList.appendChild(renderMessageNode(message));
                });

                if (chatBody && shouldStickToBottom) {
                    chatBody.scrollTop = chatBody.scrollHeight;
                    resetNotificationHint();
                }

                return shouldStickToBottom;
            };

            const updateConversationListItem = function (conversation) {
                if (!conversation || typeof conversation !== 'object') {
                    return;
                }

                const conversationId = Number(conversation.id || 0);
                if (conversationId <= 0) {
                    return;
                }

                const unreadNode = document.querySelector(`[data-wa-conversation-unread="${conversationId}"]`);
                if (unreadNode) {
                    const unreadCount = Number(conversation.unread_count || 0);
                    unreadNode.textContent = unreadCount > 0 ? String(unreadCount) : '';
                    unreadNode.classList.toggle('is-empty', unreadCount <= 0);
                }

                const previewNode = document.querySelector(`[data-wa-conversation-preview="${conversationId}"]`);
                if (previewNode) {
                    const preview = String(conversation.last_message_preview || '').trim();
                    const messageType = String(conversation.last_message_type || 'mensaje').trim();
                    previewNode.textContent = preview !== '' ? preview : `[${messageType}]`;
                }
            };

            const inferVoiceNoteMimeType = function () {
                const candidates = [
                    'audio/ogg;codecs=opus',
                    'audio/webm;codecs=opus',
                    'audio/webm',
                    'audio/mp4'
                ];

                for (const candidate of candidates) {
                    if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(candidate)) {
                        return candidate;
                    }
                }

                return '';
            };

            const stopRecorderStream = function () {
                if (!recorderStream) {
                    return;
                }

                recorderStream.getTracks().forEach(function (track) {
                    track.stop();
                });

                recorderStream = null;
            };

            const resetMediaState = function () {
                if (mediaRecorder && mediaRecorder.state === 'recording') {
                    mediaRecorder.stop();
                }
                stopRecorderStream();
                recordedChunks = [];
                if (messageTypeSelect) {
                    messageTypeSelect.value = 'text';
                }
                if (mediaUrlInput) {
                    mediaUrlInput.value = '';
                }
                if (mediaFilenameInput) {
                    mediaFilenameInput.value = '';
                }
                if (mediaDiskInput) {
                    mediaDiskInput.value = '';
                }
                if (mediaPathInput) {
                    mediaPathInput.value = '';
                }
                if (mediaMimeTypeInput) {
                    mediaMimeTypeInput.value = '';
                }
                if (mediaFileInput) {
                    mediaFileInput.value = '';
                    mediaFileInput.removeAttribute('accept');
                    mediaFileInput.removeAttribute('capture');
                }
                if (audioPickerButton) {
                    audioPickerButton.classList.remove('is-recording');
                    audioPickerButton.setAttribute('title', 'Enviar voice note o audio');
                }
                stopRecordingTimer();
                if (attachmentBox) {
                    attachmentBox.style.display = 'none';
                }
                if (attachmentName) {
                    attachmentName.textContent = '';
                }
                if (attachmentType) {
                    attachmentType.textContent = '';
                }
                setUploadStatus('', 'muted');
                setUploadProgress(0, false);
                document.querySelectorAll('[data-wa-picker]').forEach(function (button) {
                    button.classList.remove('is-active');
                });
                if (form) {
                    form.classList.remove('is-drop-target');
                }
            };

            const reflectAttachment = function (name, typeLabel) {
                if (attachmentBox) {
                    attachmentBox.style.display = 'flex';
                }
                if (attachmentName) {
                    attachmentName.textContent = name || 'Archivo cargado';
                }
                if (attachmentType) {
                    attachmentType.textContent = typeLabel || '';
                }
                document.querySelectorAll('[data-wa-picker]').forEach(function (button) {
                    const isActive = button.getAttribute('data-wa-picker') === (messageTypeSelect ? messageTypeSelect.value : 'text');
                    button.classList.toggle('is-active', isActive);
                });
            };

            const inferTypeFromFile = function (file) {
                if (!file || typeof file.type !== 'string') {
                    return 'document';
                }

                if (file.type.startsWith('image/')) {
                    return 'image';
                }

                if (file.type.startsWith('video/')) {
                    return 'video';
                }

                if (file.type.startsWith('audio/')) {
                    return 'audio';
                }

                return 'document';
            };

            const notifyNewMessages = function (messages) {
                if (!Array.isArray(messages) || messages.length === 0) {
                    return;
                }

                const autoDisplayed = appendMessages(messages);
                if (autoDisplayed) {
                    return;
                }

                const inboundMessages = messages.filter(function (message) {
                    return (message.direction || '') === 'inbound';
                });
                const notificationCount = inboundMessages.length || messages.length;
                unseenMessageCount += notificationCount;
                const label = unseenMessageCount === 1 ? 'Hay 1 mensaje nuevo.' : `Hay ${unseenMessageCount} mensajes nuevos.`;

                setLiveBanner(label, true);
                document.title = `(${unseenMessageCount}) ${defaultTitle}`;

                if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
                    const preview = String((inboundMessages[0] && inboundMessages[0].body) || (messages[0] && messages[0].body) || 'Revisa la conversación.');
                    new Notification('WhatsApp V2', {body: preview});
                }
            };

            const hasPendingDraft = function () {
                return Boolean(
                    (textarea && (textarea.value || '').trim() !== '') ||
                    (mediaUrlInput && mediaUrlInput.value.trim() !== '') ||
                    (mediaPathInput && mediaPathInput.value.trim() !== '') ||
                    (messageTypeSelect && messageTypeSelect.value !== 'text') ||
                    requestInFlight
                );
            };

            if (presenceSelect) {
                presenceSelect.addEventListener('change', async function () {
                    try {
                        await fetch('/v2/whatsapp/api/presence', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
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
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
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

            const uploadSelectedFile = function (fileOverride) {
                const selectedFile = fileOverride || (mediaFileInput && mediaFileInput.files && mediaFileInput.files.length > 0 ? mediaFileInput.files[0] : null);
                if (!selectedFile) {
                    return Promise.resolve(null);
                }

                if (messageTypeSelect) {
                    messageTypeSelect.value = inferTypeFromFile(selectedFile);
                }

                return new Promise(function (resolve, reject) {
                    const formData = new FormData();
                    const xhr = new XMLHttpRequest();

                    formData.append('file', selectedFile);
                    setUploadStatus(`Cargando ${selectedFile.name}...`, 'muted');
                    setUploadProgress(2, true);

                    xhr.open('POST', '/v2/whatsapp/api/media/upload', true);
                    xhr.setRequestHeader('Accept', 'application/json');

                    xhr.upload.addEventListener('progress', function (event) {
                        if (!event.lengthComputable) {
                            setUploadProgress(55, true);
                            return;
                        }

                        const progress = Math.min(96, Math.max(5, Math.round((event.loaded / event.total) * 100)));
                        setUploadProgress(progress, true);
                    });

                    xhr.addEventListener('load', function () {
                        let data = null;

                        try {
                            data = JSON.parse(xhr.responseText || '{}');
                        } catch (error) {
                            setUploadProgress(0, false);
                            reject(new Error('La respuesta del upload no fue válida.'));
                            return;
                        }

                        if (xhr.status < 200 || xhr.status >= 300 || !data.ok || !data.data) {
                            setUploadProgress(0, false);
                            reject(new Error(data.error || 'No fue posible cargar el archivo.'));
                            return;
                        }

                        if (messageTypeSelect && data.data.type) {
                            messageTypeSelect.value = data.data.type;
                        }

                        if (mediaUrlInput) {
                            mediaUrlInput.value = data.data.url || '';
                        }

                        if (mediaFilenameInput) {
                            mediaFilenameInput.value = data.data.filename || '';
                        }

                        if (mediaDiskInput) {
                            mediaDiskInput.value = data.data.disk || '';
                        }

                        if (mediaPathInput) {
                            mediaPathInput.value = data.data.path || '';
                        }

                        if (mediaMimeTypeInput) {
                            mediaMimeTypeInput.value = data.data.mime_type || '';
                        }

                        reflectAttachment(
                            data.data.filename || '',
                            data.data.type ? `Adjunto ${data.data.type}` : 'Adjunto listo'
                        );
                        setUploadProgress(100, true);
                        setUploadStatus(`Archivo cargado: ${data.data.filename}`, 'success');
                        window.setTimeout(function () {
                            setUploadProgress(0, false);
                        }, 650);
                        resolve(data.data);
                    });

                    xhr.addEventListener('error', function () {
                        setUploadProgress(0, false);
                        reject(new Error('No fue posible cargar el archivo.'));
                    });

                    xhr.addEventListener('abort', function () {
                        setUploadProgress(0, false);
                        reject(new Error('La carga del archivo fue cancelada.'));
                    });

                    xhr.send(formData);
                });
            };

            document.querySelectorAll('[data-wa-picker]').forEach(function (button) {
                button.addEventListener('click', async function () {
                    if (!mediaFileInput) {
                        return;
                    }

                    const pickerMenu = button.closest('.wa-v2-attachment-menu');
                    if (pickerMenu) {
                        pickerMenu.removeAttribute('open');
                    }

                    const targetType = button.getAttribute('data-wa-picker') || 'document';
                    const acceptMap = {
                        image: 'image/*',
                        video: 'video/*',
                        document: '.pdf,.doc,.docx,.xls,.xlsx,.txt,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain',
                        audio: 'audio/*',
                    };

                    if (targetType === 'audio' && typeof MediaRecorder !== 'undefined' && navigator.mediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function') {
                        if (mediaRecorder && mediaRecorder.state === 'recording') {
                            mediaRecorder.stop();
                            return;
                        }

                        try {
                            const mimeType = inferVoiceNoteMimeType();
                            recorderStream = await navigator.mediaDevices.getUserMedia({audio: true});
                            recordedChunks = [];
                            mediaRecorder = mimeType !== '' ? new MediaRecorder(recorderStream, {mimeType: mimeType}) : new MediaRecorder(recorderStream);

                            mediaRecorder.addEventListener('dataavailable', function (event) {
                                if (event.data && event.data.size > 0) {
                                    recordedChunks.push(event.data);
                                }
                            });

                            mediaRecorder.addEventListener('stop', async function () {
                                const resolvedMimeType = mediaRecorder && mediaRecorder.mimeType ? mediaRecorder.mimeType : (mimeType || 'audio/webm');
                                const extension = resolvedMimeType.includes('ogg') ? 'ogg' : (resolvedMimeType.includes('mp4') ? 'm4a' : 'webm');
                                const voiceBlob = new Blob(recordedChunks, {type: resolvedMimeType});
                                const voiceFile = new File([voiceBlob], `voice-note-${Date.now()}.${extension}`, {type: resolvedMimeType});

                                mediaRecorder = null;
                                stopRecordingTimer();
                                setRecordingState(false, 0);
                                stopRecorderStream();
                                recordedChunks = [];
                                if (audioPickerButton) {
                                    audioPickerButton.classList.remove('is-recording');
                                    audioPickerButton.setAttribute('title', 'Enviar voice note o audio');
                                }

                                if (messageTypeSelect) {
                                    messageTypeSelect.value = 'audio';
                                }

                                try {
                                    await uploadSelectedFile(voiceFile);
                                    setUploadStatus('Voice note lista para enviar.', 'success');
                                } catch (error) {
                                    resetMediaState();
                                    setFeedback(error.message || 'No fue posible cargar la voice note.', 'danger');
                                }
                            });

                            if (messageTypeSelect) {
                                messageTypeSelect.value = 'audio';
                            }
                            if (audioPickerButton) {
                                audioPickerButton.classList.add('is-recording');
                                audioPickerButton.setAttribute('title', 'Detener grabación');
                            }
                            reflectAttachment('Grabando voice note...', 'Pulsa de nuevo para detener');
                            setUploadStatus('Grabando voice note... vuelve a pulsar el micrófono para detener.', 'danger');
                            recordingStartedAt = Date.now();
                            setRecordingState(true, 0);
                            recordingTimer = window.setInterval(function () {
                                if (!recordingStartedAt) {
                                    return;
                                }

                                const elapsedSeconds = Math.max(0, Math.floor((Date.now() - recordingStartedAt) / 1000));
                                setRecordingState(true, elapsedSeconds);
                            }, 250);
                            mediaRecorder.start();
                            return;
                        } catch (error) {
                            stopRecorderStream();
                            mediaRecorder = null;
                            recordedChunks = [];
                            stopRecordingTimer();
                            setRecordingState(false, 0);
                        }
                    }

                    if (messageTypeSelect) {
                        messageTypeSelect.value = targetType;
                    }

                    mediaFileInput.setAttribute('accept', acceptMap[targetType] || '*/*');

                    if (targetType === 'audio') {
                        mediaFileInput.setAttribute('capture', 'user');
                    } else {
                        mediaFileInput.removeAttribute('capture');
                    }

                    mediaFileInput.click();
                });
            });

            if (mediaFileInput) {
                mediaFileInput.addEventListener('change', async function () {
                    if (!mediaFileInput.files || mediaFileInput.files.length === 0) {
                        return;
                    }

                    try {
                        await uploadSelectedFile();
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible cargar el archivo.', 'danger');
                    }
                });
            }

            if (form) {
                ['dragenter', 'dragover'].forEach(function (eventName) {
                    form.addEventListener(eventName, function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        form.classList.add('is-drop-target');
                    });
                });

                ['dragleave', 'dragend'].forEach(function (eventName) {
                    form.addEventListener(eventName, function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        if (!form.contains(event.relatedTarget)) {
                            form.classList.remove('is-drop-target');
                        }
                    });
                });

                form.addEventListener('drop', async function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    form.classList.remove('is-drop-target');

                    const droppedFiles = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files : null;
                    if (!droppedFiles || droppedFiles.length === 0) {
                        return;
                    }

                    try {
                        await uploadSelectedFile(droppedFiles[0]);
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible cargar el archivo.', 'danger');
                    }
                });
            }

            if (clearMediaButton) {
                clearMediaButton.addEventListener('click', function () {
                    resetMediaState();
                });
            }

            if (liveBanner) {
                liveBanner.addEventListener('click', function () {
                    if (chatBody) {
                        chatBody.scrollTop = chatBody.scrollHeight;
                    }
                    resetNotificationHint();
                });
            }

            const pollConversationUpdates = async function () {
                if (!form || !form.getAttribute('data-conversation-id') || requestInFlight || pollingInFlight) {
                    return;
                }

                pollingInFlight = true;

                try {
                    const conversationId = form.getAttribute('data-conversation-id');
                    const response = await fetch(`/v2/whatsapp/api/conversations/${conversationId}?message_limit=30`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();
                    if (!response.ok || !data.ok || !data.data || !Array.isArray(data.data.messages)) {
                        return;
                    }

                    const messages = data.data.messages;
                    const newestId = messages.reduce(function (maxId, message) {
                        return Math.max(maxId, Number(message.id || 0));
                    }, 0);

                    if (newestId <= latestMessageId) {
                        return;
                    }

                    const freshMessages = messages.filter(function (message) {
                        return Number(message.id || 0) > latestMessageId;
                    });

                    latestMessageId = newestId;
                    updateConversationListItem(data.data);
                    notifyNewMessages(freshMessages);
                } catch (error) {
                    // Keep polling silent; this must not interfere with typing.
                } finally {
                    pollingInFlight = false;
                }
            };

            if ('Notification' in window && Notification.permission === 'default' && !document.hidden) {
                window.setTimeout(function () {
                    Notification.requestPermission().catch(function () {
                    });
                }, 1200);
            }

            if (document.getElementById('wa-v2-send-form')) {
                window.setInterval(function () {
                    if (realtimeConfig && realtimeConfig.enabled && realtimeConfig.key) {
                        return;
                    }

                    if (hasPendingDraft()) {
                        return;
                    }

                    pollConversationUpdates();
                }, 15000);
            }

            if (form) {
                const conversationId = form.getAttribute('data-conversation-id');

                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const message = (textarea.value || '').trim();
                    let messageType = messageTypeSelect ? messageTypeSelect.value : 'text';
                    const mediaUrl = mediaUrlInput ? mediaUrlInput.value.trim() : '';
                    const filename = mediaFilenameInput ? mediaFilenameInput.value.trim() : '';
                    const mediaDisk = mediaDiskInput ? mediaDiskInput.value.trim() : '';
                    const mediaPath = mediaPathInput ? mediaPathInput.value.trim() : '';
                    const mediaMimeType = mediaMimeTypeInput ? mediaMimeTypeInput.value.trim() : '';

                    if (messageType === 'text' && !message) {
                        setFeedback('Escribe un mensaje antes de enviar.', 'danger');
                        return;
                    }

                    if (messageType !== 'text' && !mediaUrl) {
                        try {
                            await uploadSelectedFile();
                            messageType = messageTypeSelect ? messageTypeSelect.value : messageType;
                        } catch (error) {
                            setFeedback(error.message || 'No fue posible cargar el archivo.', 'danger');
                            return;
                        }
                    }

                    const resolvedMediaUrl = mediaUrlInput ? mediaUrlInput.value.trim() : mediaUrl;
                    const resolvedFilename = mediaFilenameInput ? mediaFilenameInput.value.trim() : filename;
                    const resolvedMediaDisk = mediaDiskInput ? mediaDiskInput.value.trim() : mediaDisk;
                    const resolvedMediaPath = mediaPathInput ? mediaPathInput.value.trim() : mediaPath;
                    const resolvedMediaMimeType = mediaMimeTypeInput ? mediaMimeTypeInput.value.trim() : mediaMimeType;

                    if (messageType !== 'text' && !resolvedMediaUrl) {
                        setFeedback('Debes indicar una URL o cargar un archivo antes de enviar media.', 'danger');
                        return;
                    }

                    try {
                        resetNotificationHint();
                        await postAction(
                            `/v2/whatsapp/api/conversations/${conversationId}/messages`,
                            {
                                message,
                                message_type: messageType,
                                media_url: resolvedMediaUrl,
                                filename: resolvedFilename,
                                mime_type: resolvedMediaMimeType,
                                media_disk: resolvedMediaDisk,
                                media_path: resolvedMediaPath
                            },
                            'Enviando...',
                            'Mensaje enviado. Recargando conversación...'
                        );
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible enviar el mensaje.', 'danger');
                    }
                });
            }

            resetMediaState();
            window.MEDF = window.MEDF || {};
            window.MEDF.whatsappChat = window.MEDF.whatsappChat || {};
            window.MEDF.whatsappChat.handleRealtimeEvent = function (payload) {
                if (!payload || typeof payload !== 'object') {
                    return;
                }

                if (payload.conversation) {
                    updateConversationListItem(payload.conversation);
                }

                if (payload.type === 'inbound_message') {
                    const currentConversationId = Number(form ? (form.getAttribute('data-conversation-id') || 0) : 0);
                    const payloadConversationId = Number(payload.conversation && payload.conversation.id ? payload.conversation.id : 0);

                    if (payloadConversationId > 0 && payloadConversationId === currentConversationId && payload.message) {
                        latestMessageId = Math.max(latestMessageId, Number(payload.message.id || 0));
                        notifyNewMessages([payload.message]);
                    }
                }
            };
            if (chatBody) {
                chatBody.addEventListener('scroll', function () {
                    const nearBottom = (chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight) < 80;
                    if (nearBottom) {
                        resetNotificationHint();
                    }
                });
            }
            if (textarea) {
                textarea.addEventListener('input', function () {
                    resetNotificationHint();
                });
                textarea.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
                        return;
                    }

                    event.preventDefault();
                    if (form && typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                        return;
                    }

                    if (form) {
                        form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}));
                    }
                });
            }

            document.addEventListener('click', function (event) {
                if (attachmentMenu && attachmentMenu.hasAttribute('open') && !attachmentMenu.contains(event.target)) {
                    attachmentMenu.removeAttribute('open');
                }

                if (opsMenu && opsMenu.hasAttribute('open') && !opsMenu.contains(event.target)) {
                    opsMenu.removeAttribute('open');
                }
            });

            if (startChatOpenButton) {
                startChatOpenButton.addEventListener('click', function () {
                    openStartChatModal();
                });
            }

            if (startChatCloseButton) {
                startChatCloseButton.addEventListener('click', function () {
                    closeStartChatModal();
                });
            }

            if (startChatModal) {
                startChatModal.addEventListener('click', function (event) {
                    if (event.target === startChatModal) {
                        closeStartChatModal();
                    }
                });
            }

            if (startChatSearchButton) {
                startChatSearchButton.addEventListener('click', function () {
                    runContactSearch();
                });
            }

            if (startChatTemplate) {
                startChatTemplate.addEventListener('change', function () {
                    renderStartTemplateVariables();
                });
            }

            if (startChatSearch) {
                startChatSearch.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        runContactSearch();
                    }
                });
                startChatSearch.addEventListener('input', function () {
                    if (startChatSearchTimer) {
                        window.clearTimeout(startChatSearchTimer);
                    }

                    const value = (startChatSearch.value || '').trim();
                    if (value.length < 2) {
                        renderStartChatResults([]);
                        setStartChatFeedback('Escribe al menos 2 caracteres para buscar en tiempo real.', 'muted');
                        return;
                    }

                    startChatSearchTimer = window.setTimeout(function () {
                        runContactSearch();
                    }, 220);
                });
            }

            if (startChatSubmit) {
                startChatSubmit.addEventListener('click', function () {
                    submitStartChat();
                });
            }

            document.querySelectorAll('[data-wa-open-start-template]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const templateMenu = button.closest('.wa-v2-attachment-menu');
                    if (templateMenu) {
                        templateMenu.removeAttribute('open');
                    }

                    openStartChatModal({
                        waNumber: button.getAttribute('data-wa-number') || '',
                        contactName: button.getAttribute('data-wa-contact-name') || '',
                        patientName: button.getAttribute('data-wa-patient-name') || '',
                        hcNumber: button.getAttribute('data-wa-hc-number') || '',
                    });
                });
            });

            document.querySelectorAll('[data-wa-quick-reply]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (!textarea) {
                        return;
                    }

                    const snippet = button.getAttribute('data-wa-quick-reply') || '';
                    const current = (textarea.value || '').trim();
                    textarea.value = current ? `${current}\n${snippet}` : snippet;
                    textarea.focus();

                    const quickReplyMenu = button.closest('.wa-v2-attachment-menu');
                    if (quickReplyMenu) {
                        quickReplyMenu.removeAttribute('open');
                    }
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

            // ── Trazabilidad (trail) ──────────────────────────────────────────
            (function () {
                const trailCard = document.getElementById('wa-v2-trail-card');
                const trailList = document.getElementById('wa-v2-trail-list');
                if (!trailCard || !trailList) return;

                // Derive conversation ID from the note form (already rendered on page)
                const noteFormEl = document.getElementById('wa-v2-note-form');
                const conversationId = noteFormEl ? noteFormEl.getAttribute('data-conversation-id') : null;
                if (!conversationId) return;

                // icon → CSS modifier class for the dot color
                const ICON_CLASS = {
                    start:       'start',
                    requested:   'queued',
                    queued:      'queued',
                    assigned:    'assigned',
                    transferred: 'transferred',
                    expired:     'expired',
                    resolved:    'resolved',
                    template:    'template',
                    ad:          'ad',
                    organic:     'organic',
                    campaign:    'campaign',
                    support:     'support',
                    intent:      'intent',
                };

                // icon → emoji for visual identification
                const ICON_EMOJI = {
                    start:       '🟢',
                    requested:   '🔔',
                    queued:      '📥',
                    assigned:    '👤',
                    transferred: '🔄',
                    expired:     '⏰',
                    resolved:    '✅',
                    template:    '📨',
                    ad:          '🎯',
                    organic:     '🌐',
                    campaign:    '📣',
                    support:     '🔧',
                    intent:      '🤖',
                };

                function escHtml(str) {
                    return String(str ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                function fmtTrailTs(iso) {
                    if (!iso) return '';
                    const d = new Date(iso);
                    if (Number.isNaN(d.getTime())) return iso;
                    return new Intl.DateTimeFormat('es-EC', {
                        day: '2-digit', month: '2-digit',
                        hour: '2-digit', minute: '2-digit', hour12: false,
                    }).format(d).replace(',', '');
                }

                function renderTrail(items) {
                    if (!items || items.length === 0) {
                        trailList.innerHTML = '<div class="text-muted" style="padding:8px 0;font-size:.82rem;">Sin eventos registrados.</div>';
                        return;
                    }

                    const html = items.map(function (item) {
                        const icon    = item.icon || 'default';
                        const mod     = ICON_CLASS[icon] || '';
                        const emoji   = ICON_EMOJI[icon] || '•';
                        const itemCls = 'wa-v2-trail-item' + (mod ? ' wa-v2-trail-item--' + mod : '');
                        const ts      = fmtTrailTs(item.created_at);
                        const actor   = item.actor_name ? escHtml(item.actor_name) : '<em class="text-muted">Sistema</em>';
                        const noteHtml = item.notes
                            ? '<div class="wa-v2-trail-notes">' + escHtml(item.notes) + '</div>'
                            : '';

                        return '<div class="' + itemCls + '">'
                            + '<div class="wa-v2-trail-label">'
                            +   '<span style="margin-right:5px;">' + emoji + '</span>'
                            +   escHtml(item.event_label)
                            + '</div>'
                            + '<div class="wa-v2-trail-meta">' + actor + ' · ' + ts + '</div>'
                            + noteHtml
                            + '</div>';
                    }).join('');

                    trailList.innerHTML = '<div class="wa-v2-trail">' + html + '</div>';
                }

                async function loadTrail() {
                    trailList.innerHTML = '<div class="text-muted" style="padding:8px 0;font-size:.82rem;">Cargando trazabilidad…</div>';
                    try {
                        const response = await fetch(`/v2/whatsapp/api/conversations/${conversationId}/trail`, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const json = await response.json();
                        if (json.ok && Array.isArray(json.data)) {
                            renderTrail(json.data);
                        } else {
                            trailList.innerHTML = '<div class="text-danger" style="font-size:.82rem;">No se pudo cargar la trazabilidad.</div>';
                        }
                    } catch (e) {
                        trailList.innerHTML = '<div class="text-danger" style="font-size:.82rem;">Error de red al cargar la trazabilidad.</div>';
                    }
                }

                // Auto-load on page ready (panel is always visible in the 3rd column)
                loadTrail();

                // Also reload when the section is manually re-opened after collapse
                let firstToggle = true;
                trailCard.addEventListener('toggle', function () {
                    if (!trailCard.open || firstToggle) { firstToggle = false; return; }
                    loadTrail();
                });
            })();

            // ── Cerrar seguimiento / Lead de seguimiento ─────────────────────
            (function () {
                const bajaBtn    = document.getElementById('wa-v2-baja-btn');
                const bajaModal  = document.getElementById('wa-v2-baja-modal');
                const bajaClose  = document.getElementById('wa-v2-baja-close');
                const bajaCancel = document.getElementById('wa-v2-baja-cancel');
                const bajaSubmit = document.getElementById('wa-v2-baja-submit');
                const bajaMotivo = document.getElementById('wa-v2-baja-motivo');
                const bajaFeed   = document.getElementById('wa-v2-baja-feedback');

                if (!bajaBtn || !bajaModal) return;

                const conversationId = bajaBtn.getAttribute('data-conversation-id');

                function openModal() {
                    bajaMotivo.value = '';
                    bajaFeed.textContent = '';
                    bajaModal.style.display = 'flex';
                    bajaMotivo.focus();
                }

                function closeModal() {
                    bajaModal.style.display = 'none';
                }

                bajaBtn.addEventListener('click', openModal);
                bajaClose.addEventListener('click', closeModal);
                bajaCancel.addEventListener('click', closeModal);
                bajaModal.addEventListener('click', function (e) {
                    if (e.target === bajaModal) closeModal();
                });

                bajaSubmit.addEventListener('click', async function () {
                    const motivo = bajaMotivo.value.trim();
                    if (!motivo) {
                        bajaFeed.textContent = 'El motivo del cierre de seguimiento es obligatorio.';
                        bajaMotivo.focus();
                        return;
                    }

                    bajaSubmit.disabled = true;
                    bajaSubmit.textContent = 'Procesando…';
                    bajaFeed.textContent = '';

                    try {
                        const resp = await fetch(`/v2/whatsapp/api/conversations/${conversationId}/leads`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            },
                            body: JSON.stringify({ motivo_baja: motivo }),
                        });
                        const json = await resp.json();

                        if (json.ok) {
                            closeModal();
                            // Redirigir al chat (sin la conversación seleccionada) o recargar
                            window.location.href = '/v2/whatsapp/chat';
                        } else {
                            bajaFeed.textContent = json.error ?? 'No se pudo generar el lead.';
                            bajaSubmit.disabled = false;
                            bajaSubmit.textContent = 'Cerrar seguimiento';
                        }
                    } catch (e) {
                        bajaFeed.textContent = 'Error de red. Intenta de nuevo.';
                        bajaSubmit.disabled = false;
                        bajaSubmit.textContent = 'Cerrar seguimiento';
                    }
                });
            })();
        });
    </script>
@endpush
