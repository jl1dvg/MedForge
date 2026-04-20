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
    $tabs = [
        'mine' => 'Mis chats',
        'handoff' => 'Pendientes',
        'window_open' => '24h abierta',
        'unread' => 'Sin leer',
        'needs_template' => 'Plantilla',
        'resolved' => 'Resueltos',
        'all' => 'Todos',
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
            padding: 22px 24px;
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

        .wa-v2-shell {
            display: grid;
            grid-template-columns: 390px minmax(0, 1fr);
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
            padding: 18px 20px;
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

        .wa-v2-tabs {
            display: flex;
            flex-wrap: nowrap;
            gap: 8px;
            overflow: auto;
            padding-bottom: 2px;
            scrollbar-width: none;
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
            background: linear-gradient(180deg, rgba(248, 250, 252, .9) 0%, rgba(232, 244, 241, .96) 100%),
            radial-gradient(circle at top left, rgba(16, 185, 129, .08), transparent 26%);
        }

        .wa-v2-chat__body {
            padding: 18px 18px 10px;
            overflow: auto;
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
            color: var(--wa-muted);
            font-size: 13px;
        }

        .wa-v2-chat-patient {
            margin-top: 3px;
            color: #334155;
            font-size: 13px;
        }

        .wa-v2-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: nowrap;
            margin-top: 16px;
            padding-top: 14px;
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
            flex: 1 1 auto;
        }

        .wa-v2-actions {
            display: grid;
            gap: 12px;
            margin-top: 0;
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

        .wa-v2-message-stack {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .wa-v2-message {
            max-width: 78%;
            padding: 12px 14px;
            border-radius: 20px 20px 20px 8px;
            margin-bottom: 12px;
            background: #fff;
            border: 1px solid rgba(15, 23, 42, .06);
            box-shadow: 0 10px 20px rgba(15, 23, 42, .05);
        }

        .wa-v2-message.is-outbound {
            margin-left: auto;
            background: linear-gradient(180deg, #d2f4e7 0%, #c7efe1 100%);
            border-color: rgba(15, 118, 110, .18);
            border-radius: 20px 20px 8px 20px;
        }

        .wa-v2-message__meta {
            margin-top: 6px;
            font-size: 11px;
            color: #64748b;
        }

        .wa-v2-media-card {
            display: grid;
            gap: 8px;
            margin-top: 8px;
            padding: 10px 12px;
            border-radius: 14px;
            background: rgba(15, 23, 42, .04);
            border: 1px solid rgba(15, 23, 42, .08);
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
            padding: 10px 12px;
            margin: 0 18px 12px;
            border-radius: 12px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1e3a8a;
            box-shadow: 0 10px 16px rgba(59, 130, 246, .10);
        }

        .wa-v2-live-banner[hidden] {
            display: none;
        }

        .wa-v2-tools {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 360px);
            gap: 14px;
            padding: 14px 18px 0;
        }

        .wa-v2-tool-card {
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 20px;
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

        .wa-v2-compose {
            padding: 16px 18px 18px;
            border-top: 1px solid rgba(15, 23, 42, .08);
            background: rgba(255, 255, 255, .86);
            backdrop-filter: blur(10px);
            position: sticky;
            bottom: 0;
        }

        .wa-v2-compose-grid {
            display: grid;
            gap: 10px;
            padding: 12px;
            border-radius: 22px;
            background: rgba(255, 255, 255, .94);
            border: 1px solid rgba(15, 23, 42, .08);
            box-shadow: 0 14px 24px rgba(15, 23, 42, .06);
        }

        .wa-v2-compose-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
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
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
        }

        .wa-v2-composer-inputgroup textarea {
            min-height: 54px;
            resize: vertical;
            border-radius: 18px;
            border-color: #d7e3dd;
            padding: 12px 14px;
        }

        .wa-v2-composer-send {
            min-width: 110px;
            height: 54px;
            border-radius: 18px;
            font-weight: 800;
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

            .wa-v2-tools {
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
                max-width: 92%;
            }

            .wa-v2-composer-inputgroup {
                grid-template-columns: 1fr;
            }

            .wa-v2-composer-send {
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
                        <button type="button" class="btn btn-light btn-sm" id="wa-v2-open-start-chat">
                            <i class="mdi mdi-message-plus-outline"></i> Nuevo chat
                        </button>
                        <span class="wa-v2-hero-pill"><i class="mdi mdi-account-circle-outline"></i> {{ $currentUser['display_name'] ?? 'Usuario' }}</span>
                        <span class="wa-v2-hero-pill"><i class="mdi mdi-layers-outline"></i> {{ $canSupervise ? 'Modo supervisor' : 'Vista de agente' }}</span>
                        <span class="wa-v2-hero-pill"><i class="mdi mdi-access-point"></i> Realtime listo</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-15">
            <div class="col-12">
                <div class="box mb-0" style="border-radius:22px;">
                    <div
                        class="box-body bg-transparent d-flex flex-wrap gap-10 align-items-center justify-content-between">
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

        <div class="wa-v2-modal-backdrop" id="wa-v2-start-chat-modal" aria-hidden="true">
            <div class="wa-v2-modal">
                <div class="wa-v2-modal__header d-flex justify-content-between align-items-start gap-10">
                    <div>
                        <div class="wa-v2-sideheading__title" style="font-size:20px;">Nuevo chat con plantilla</div>
                        <div class="text-muted" style="font-size:13px;">Busca en pacientes, o escribe el número, y abre la conversación con un template aprobado.</div>
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
                                <button type="button" class="btn btn-outline-secondary" id="wa-v2-start-search-button">Buscar</button>
                            </div>
                            <div class="wa-v2-picker-results" id="wa-v2-start-results"></div>
                        </div>
                        <div class="col-lg-5">
                            <div class="row g-10">
                                <div class="col-12">
                                    <label for="wa-v2-start-number" class="form-label">Número WhatsApp</label>
                                    <input type="text" class="form-control" id="wa-v2-start-number" placeholder="593999111222">
                                </div>
                                <div class="col-12">
                                    <label for="wa-v2-start-contact-name" class="form-label">Nombre visible</label>
                                    <input type="text" class="form-control" id="wa-v2-start-contact-name" placeholder="Nombre del contacto">
                                </div>
                                <div class="col-12">
                                    <label for="wa-v2-start-patient-name" class="form-label">Paciente</label>
                                    <input type="text" class="form-control" id="wa-v2-start-patient-name" placeholder="Nombres y apellidos">
                                </div>
                                <div class="col-12">
                                    <label for="wa-v2-start-hc" class="form-label">HC</label>
                                    <input type="text" class="form-control" id="wa-v2-start-hc" placeholder="Historia clínica">
                                </div>
                                <div class="col-12">
                                    <label for="wa-v2-start-template" class="form-label">Template aprobado</label>
                                    <select class="form-select" id="wa-v2-start-template">
                                        <option value="">Selecciona un template</option>
                                        @foreach($templateOptions as $template)
                                            <option value="{{ $template['id'] }}">{{ $template['name'] }} · {{ $template['language'] ?: 'n/a' }} · {{ $template['status'] ?: 'n/a' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-light mt-15 mb-0" id="wa-v2-start-chat-feedback">
                        Selecciona un contacto o escribe el número manualmente para iniciar con plantilla.
                    </div>
                </div>
                <div class="wa-v2-modal__footer">
                    <div class="text-muted" style="font-size:12px;">Esto crea o reutiliza la conversación y la deja abierta en tu inbox.</div>
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
                            <div class="text-uppercase text-muted mb-5" style="font-size:11px; letter-spacing:.08em;">Última interacción</div>
                        </div>
                        <div class="col-sm-5">
                            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                        </div>
                        <div class="col-sm-5">
                            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                        </div>
                        <div class="col-sm-2 d-flex gap-8">
                            <button type="submit" class="btn btn-outline-primary btn-sm flex-fill" title="Aplicar rango">
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
                            </div>
                        </details>
                    @endif

                    <div class="wa-v2-tabs">
                        @foreach($tabs as $key => $label)
                            @php
                                $tabIcons = [
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
                            $priorityState = match (true) {
                                !empty($conversation['needs_human']) && empty($conversation['assigned_user_id']) => 'pending',
                                ($conversation['ownership_state'] ?? '') === 'mine' => 'mine',
                                ($conversation['messaging_window_state'] ?? '') === 'window_open' => 'window_open',
                                !empty($conversation['needs_human']) => 'needs_template',
                                default => 'resolved',
                            };
                            $priorityLabel = match ($priorityState) {
                                'pending' => 'Pendiente',
                                'mine' => 'Mío',
                                'window_open' => '24h abierta',
                                'needs_template' => 'Plantilla',
                                default => 'Resuelto',
                            };
                            $priorityClass = match ($priorityState) {
                                'pending' => 'is-pending',
                                'mine' => 'is-mine',
                                'window_open' => 'is-window-open',
                                'needs_template' => 'is-needs-template',
                                default => 'is-resolved',
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
                                    <i class="mdi {{ $priorityState === 'pending' ? 'mdi-alert-circle-outline' : ($priorityState === 'mine' ? 'mdi-account-check-outline' : ($priorityState === 'window_open' ? 'mdi-timer-sand' : ($priorityState === 'needs_template' ? 'mdi-file-document-edit-outline' : 'mdi-check-circle-outline'))) }}"></i>
                                    {{ $priorityLabel }}
                                </span>
                                <div class="wa-v2-meta">{{ !empty($conversation['last_message_at']) ? \Illuminate\Support\Carbon::parse($conversation['last_message_at'])->format('d/m H:i') : '' }}</div>
                            </div>
                            <div class="d-flex flex-wrap gap-8 mt-10">
                                @if((int) $conversation['unread_count'] > 0)
                                    <span class="wa-v2-pill is-unread"><i class="mdi mdi-bell"></i> Sin leer</span>
                                @endif
                                @if(!empty($conversation['needs_human']))
                                    <span class="wa-v2-pill is-queue"><i
                                            class="mdi mdi-tray-arrow-down"></i> En cola</span>
                                @else
                                    <span class="wa-v2-pill"><i class="mdi mdi-check"></i> Resuelto</span>
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
                        $waLink = 'https://wa.me/' . preg_replace('/\D+/', '', (string) ($selectedConversation['wa_number'] ?? ''));
                    @endphp
                    <div class="wa-v2-panel__header">
                        <div class="wa-v2-chat-header">
                            <div class="wa-v2-chat-header__main">
                                <div class="wa-v2-avatar">
                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($selectedConversation['display_name'] ?: $selectedConversation['wa_number'], 0, 1)) }}
                                </div>
                                <div>
                                    <h4 class="wa-v2-chat-title">{{ $selectedConversation['display_name'] ?: $selectedConversation['wa_number'] }}</h4>
                                    <div class="wa-v2-chat-subtitle">{{ $selectedConversation['wa_number'] }}</div>
                                    <div class="wa-v2-chat-patient">
                                        {{ $selectedConversation['patient_full_name'] ?: 'Sin paciente vinculado' }}
                                        @if(!empty($selectedConversation['patient_hc_number']))
                                            · HC {{ $selectedConversation['patient_hc_number'] }}
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="wa-v2-actions__row wa-v2-actions__row--header">
                                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn btn-success-light"
                                   title="Abrir en WhatsApp" aria-label="Abrir en WhatsApp">
                                    <span class="wa-v2-icon-label">
                                        <i class="mdi mdi-whatsapp"></i>
                                    </span>
                                </a>

                                @if(($selectedConversation['messaging_window_state'] ?? '') !== 'window_open')
                                    <button type="button"
                                            class="btn btn-outline-primary"
                                            data-wa-open-start-template="1"
                                            data-wa-number="{{ $selectedConversation['wa_number'] }}"
                                            data-wa-contact-name="{{ $selectedConversation['display_name'] ?: $selectedConversation['wa_number'] }}"
                                            data-wa-patient-name="{{ $selectedConversation['patient_full_name'] ?? '' }}"
                                            data-wa-hc-number="{{ $selectedConversation['patient_hc_number'] ?? '' }}"
                                            title="Enviar plantilla"
                                            aria-label="Enviar plantilla">
                                        <span class="wa-v2-icon-label">
                                            <i class="mdi mdi-file-document-edit-outline"></i>
                                        </span>
                                    </button>
                                @endif

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
                                        class="btn btn-outline-danger"
                                        data-wa-action="close"
                                        data-conversation-id="{{ $selectedConversation['id'] }}"
                                        title="Cerrar conversación"
                                        aria-label="Cerrar conversación">
                                        <span class="wa-v2-icon-label">
                                            <i class="mdi mdi-close-circle-outline"></i>
                                        </span>
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="wa-v2-toolbar">
                            <div class="wa-v2-toolbar__badges">
                                @if(!empty($selectedConversation['needs_human']))
                                    <span class="wa-v2-pill is-queue"><i
                                            class="mdi mdi-tray-arrow-down"></i> En cola</span>
                                @else
                                    <span class="wa-v2-pill"><i class="mdi mdi-check"></i> Resuelto</span>
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
                                    <div class="wa-v2-actions">
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
                                                        <option value="{{ $role['id'] }}">{{ $role['name'] }}</option>
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
                            @endif
                        </div>
                    </div>

                    <div class="wa-v2-chat__body" id="wa-v2-chat-body">
                        <div class="wa-v2-live-banner" id="wa-v2-live-banner" hidden>
                            <div id="wa-v2-live-banner-text">Hay mensajes nuevos en esta conversación.</div>
                        </div>
                        <div id="wa-v2-message-list" class="wa-v2-message-stack">
                            @foreach(($selectedConversation['messages'] ?? []) as $message)
                                <div
                                    class="wa-v2-message {{ ($message['direction'] ?? '') === 'outbound' ? 'is-outbound' : '' }}"
                                    data-message-id="{{ (int) ($message['id'] ?? 0) }}">
                                    <div>{{ $message['body'] ?: '[' . ($message['message_type'] ?? 'mensaje') . ']' }}</div>
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
                    </div>

                    <div class="wa-v2-tools">
                        <details class="wa-v2-collapse wa-v2-tool-card">
                            <summary>
                                <div>
                                    <div class="fw-700">Respuestas rápidas</div>
                                    <div class="text-muted" style="font-size:12px;">Snippets y guardado</div>
                                </div>
                            </summary>
                            <div class="wa-v2-collapse__body">
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
                                    <div class="text-muted mb-10" style="font-size:12px;">Todavía no hay respuestas rápidas
                                        cargadas.
                                    </div>
                                @endif

                                <form id="wa-v2-quick-reply-form" class="d-grid gap-8">
                                    <div class="row g-8">
                                        <div class="col-md-4">
                                            <input type="text" class="form-control form-control-sm" id="wa-v2-quick-title"
                                                   placeholder="Título">
                                        </div>
                                        <div class="col-md-3">
                                            <input type="text" class="form-control form-control-sm"
                                                   id="wa-v2-quick-shortcut" placeholder="/atajo">
                                        </div>
                                        <div class="col-md-5">
                                            <input type="text" class="form-control form-control-sm" id="wa-v2-quick-body"
                                                   placeholder="Texto reusable">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Guardar respuesta
                                            rápida
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </details>

                        <details class="wa-v2-collapse wa-v2-tool-card">
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
                                                    · {{ \Illuminate\Support\Carbon::parse($note['created_at'])->format('d/m H:i') }}
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-muted" style="font-size:12px;">Sin notas internas en esta
                                            conversación.
                                        </div>
                                    @endforelse
                                </div>
                                <form id="wa-v2-note-form" data-conversation-id="{{ $selectedConversation['id'] }}">
                                    <div class="input-group">
                                        <textarea class="form-control" id="wa-v2-note-input" rows="2"
                                                  placeholder="Agregar nota interna para el equipo"></textarea>
                                        <button type="submit" class="btn btn-outline-warning">Guardar nota</button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    </div>

                    <div class="wa-v2-compose">
                        <form
                            id="wa-v2-send-form"
                            data-conversation-id="{{ $selectedConversation['id'] }}"
                            data-latest-message-id="{{ (int) collect($selectedConversation['messages'] ?? [])->max('id') }}">
                            <div class="mb-10 text-muted" style="font-size:12px;">
                                Estado de escritura:
                                <strong>{{ $selectedConversation['messaging_window_label'] ?? 'Sin ventana' }}</strong>.
                                Si la última entrada ya salió de 24h, solo debes seguir con plantilla.
                            </div>
                            <div class="wa-v2-compose-grid">
                                <div class="wa-v2-compose-actions">
                                    <button type="button" class="wa-v2-compose-action" data-wa-picker="image"
                                            title="Enviar imagen" {{ $canReplyHere ? '' : 'disabled' }}>
                                        <i class="mdi mdi-image-outline"></i>
                                    </button>
                                    <button type="button" class="wa-v2-compose-action" data-wa-picker="video"
                                            title="Enviar video" {{ $canReplyHere ? '' : 'disabled' }}>
                                        <i class="mdi mdi-video-outline"></i>
                                    </button>
                                    <button type="button" class="wa-v2-compose-action" data-wa-picker="document"
                                            title="Enviar documento" {{ $canReplyHere ? '' : 'disabled' }}>
                                        <i class="mdi mdi-file-document-outline"></i>
                                    </button>
                                    <button type="button" class="wa-v2-compose-action" data-wa-picker="audio"
                                            title="Enviar voice note o audio" {{ $canReplyHere ? '' : 'disabled' }}>
                                        <i class="mdi mdi-microphone-outline"></i>
                                    </button>
                                </div>
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
                                    <textarea class="form-control" id="wa-v2-message-input" rows="2"
                                              placeholder="Texto o caption" {{ $canReplyHere ? '' : 'disabled' }}></textarea>
                                    <button type="submit"
                                            class="btn btn-primary wa-v2-composer-send" {{ $canReplyHere ? '' : 'disabled' }}>
                                        Enviar
                                    </button>
                                </div>
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

        <aside id="kanbanNotificationPanel" class="control-sidebar notification-panel" aria-hidden="true">
            <div class="rpanel-title notification-panel__header d-flex align-items-start justify-content-between">
                <div class="notification-panel__headline">
                    <h5 class="mb-1 d-flex align-items-center">
                        <i class="mdi mdi-bell-outline me-2"></i>
                        Avisos de WhatsApp
                    </h5>
                    <small class="text-muted">
                        Mensajes entrantes, conversaciones sin tomar y cambios operativos del inbox.
                    </small>
                    <div class="notification-panel__channels mt-1 text-muted small" data-channel-flags>
                        Canal activo: <strong>WhatsApp realtime</strong>
                    </div>
                </div>

                <button type="button"
                        class="btn btn-sm btn-danger ms-2"
                        data-action="close-panel"
                        title="Cerrar panel de avisos"
                        aria-label="Cerrar panel de avisos">
                    <i class="mdi mdi-close"></i>
                </button>
            </div>

            <div class="notification-panel__intro">
                <ul>
                    <li><strong>Actividad del inbox:</strong> nuevos mensajes y cambios de ownership.</li>
                    <li><strong>Pendientes:</strong> conversaciones en cola o sin tomar.</li>
                </ul>
            </div>

            <div class="notification-panel__warning text-danger d-none" data-integration-warning></div>
            <div class="notification-panel__stats">
                <span>Recibidas: <strong data-summary-count="received">0</strong></span>
                <span>Por revisar: <strong data-summary-count="unread">0</strong></span>
                <button type="button"
                        class="btn btn-xs btn-outline-primary"
                        data-action="mark-all-reviewed"
                        title="Marcar todas las alertas como revisadas"
                        aria-label="Marcar todas las alertas como revisadas">
                    Marcar todas como revisadas
                </button>
            </div>

            <ul class="nav nav-tabs control-sidebar-tabs notification-panel__tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a href="#control-sidebar-home-tab" data-bs-toggle="tab" class="nav-link active" role="tab"
                       aria-controls="control-sidebar-home-tab" aria-selected="true"
                       title="Eventos en tiempo real">
                        <i class="mdi mdi-message-text"></i>
                        <span class="badge bg-primary rounded-pill" data-count="realtime">0</span>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="#control-sidebar-settings-tab" data-bs-toggle="tab" class="nav-link" role="tab"
                       aria-controls="control-sidebar-settings-tab" aria-selected="false"
                       title="Alertas pendientes por revisar">
                        <i class="mdi mdi-playlist-check"></i>
                        <span class="badge bg-secondary rounded-pill" data-count="pending">0</span>
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="control-sidebar-home-tab" role="tabpanel">
                    <div class="flexbox notification-panel__toolbar align-items-center">
                        <span class="text-grey" aria-hidden="true">
                            <i class="mdi mdi-dots-horizontal"></i>
                        </span>
                        <p class="mb-0">Actividad del inbox</p>
                        <span class="text-end text-grey" aria-hidden="true">
                            <i class="mdi mdi-plus"></i>
                        </span>
                    </div>
                    <div class="notification-panel__section-header mt-2">
                        <span>Mensajes y cambios operativos generados por WhatsApp.</span>
                    </div>
                    <div class="media-list media-list-hover mt-20" data-panel-list="realtime">
                        <p class="notification-empty">Sin actividad reciente de WhatsApp.</p>
                    </div>
                </div>
                <div class="tab-pane fade" id="control-sidebar-settings-tab" role="tabpanel">
                    <div class="flexbox notification-panel__toolbar align-items-center">
                        <span class="text-grey" aria-hidden="true">
                            <i class="mdi mdi-dots-horizontal"></i>
                        </span>
                        <p class="mb-0">Alertas pendientes</p>
                        <span class="text-end text-grey" aria-hidden="true">
                            <i class="mdi mdi-plus"></i>
                        </span>
                    </div>
                    <div class="notification-panel__section-header mt-2">
                        <span>Conversaciones que todavía no han sido atendidas o requieren seguimiento.</span>
                    </div>
                    <div class="media-list media-list-hover mt-20" data-panel-list="pending">
                        <p class="notification-empty">Sin alertas pendientes por revisar.</p>
                    </div>
                </div>
            </div>
        </aside>
        <div id="notificationPanelBackdrop" class="notification-panel__backdrop" data-action="close-panel"></div>
    </section>
@endsection

@push('scripts')
    <script>
        window.MEDF = window.MEDF || {};
        window.MEDF.currentUser = {
            id: @json((int) ($currentUser['id'] ?? 0)),
            name: @json((string) ($currentUser['display_name'] ?? '')),
        };
        window.MEDF.defaultNotificationChannels = @json($realtimeConfig['channels'] ?? ['email' => false, 'sms' => false, 'daily_summary' => false]);
        window.MEDF_PusherConfig = @json($realtimeConfig ?? []);
        window.__WHATSAPP_V2_REALTIME__ = {
            currentConversationId: @json((int) ($selectedConversation['id'] ?? 0)),
            canSupervise: @json((bool) ($canSupervise ?? false)),
            assetVersion: @json($whatsappAssetVersion ?? ''),
        };
    </script>
    @if(!empty($realtimeConfig['enabled']) && !empty($realtimeConfig['key']))
        <script src="/assets/vendor_components/pusher/pusher.min.js"></script>
    @endif
    <script type="module"
            src="/js/pages/whatsapp/v2-chat-realtime.js?v={{ urlencode((string) ($whatsappAssetVersion ?? '1')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
            const startChatSubmit = document.getElementById('wa-v2-start-submit');
            const startChatFeedback = document.getElementById('wa-v2-start-chat-feedback');
            const presenceSelect = document.getElementById('wa-v2-presence');
            const requeueExpiredButton = document.getElementById('wa-v2-requeue-expired');
            const audioPickerButton = document.querySelector('[data-wa-picker="audio"]');
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
                        conversation: 'Conversación',
                        patient_data: 'Paciente',
                        consent: 'Consentimiento',
                        crm_lead: 'CRM',
                    };
                    const sourceLabel = sourceMap[row.source] || 'Fuente';

                    return `
                        <button type="button"
                                class="wa-v2-picker-card ${index === 0 ? 'is-active' : ''}"
                                data-wa-select-contact="1"
                                data-wa-number="${row.wa_number || ''}"
                                data-wa-contact-name="${title}"
                                data-wa-patient-name="${title}"
                                data-wa-hc-number="${row.hc_number || ''}">
                            <div>
                                <div class="fw-700">${title}</div>
                                <div class="text-muted" style="font-size:12px;">${meta || 'Sin meta'}</div>
                            </div>
                            <div class="d-flex align-items-center gap-8">
                                <span class="wa-v2-picker-card__source">${sourceLabel}</span>
                                <span class="btn btn-outline-secondary btn-sm">Usar</span>
                            </div>
                        </button>
                    `;
                }).join('');

                startChatResults.querySelectorAll('[data-wa-select-contact]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        startChatResults.querySelectorAll('.wa-v2-picker-card').forEach(function (row) {
                            row.classList.remove('is-active');
                        });
                        button.classList.add('is-active');
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
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            wa_number: waNumber,
                            template_id: templateId,
                            contact_name: (startChatContactName?.value || '').trim(),
                            patient_full_name: (startChatPatientName?.value || '').trim(),
                            patient_hc_number: (startChatHc?.value || '').trim(),
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
                    hour12: false
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

            const renderMessageNode = function (message) {
                const wrapper = document.createElement('div');
                wrapper.className = `wa-v2-message ${(message.direction || '') === 'outbound' ? 'is-outbound' : ''}`;
                wrapper.setAttribute('data-message-id', String(Number(message.id || 0)));

                const body = (message.body && String(message.body).trim() !== '')
                    ? escapeHtml(message.body)
                    : `[${escapeHtml(message.message_type || 'mensaje')}]`;
                const directionLabel = (message.direction || '') === 'outbound' ? 'Salida' : 'Entrada';
                const metaParts = [
                    directionLabel,
                    escapeHtml(message.message_type || 'text')
                ];

                if (message.status) {
                    metaParts.push(escapeHtml(message.status));
                }

                const formattedTimestamp = formatTimestamp(message.message_timestamp);
                if (formattedTimestamp !== '') {
                    metaParts.push(escapeHtml(formattedTimestamp));
                }

                wrapper.innerHTML = `<div>${body}</div>${renderMediaCard(message)}<div class="wa-v2-message__meta">${metaParts.join(' · ')}</div>`;

                return wrapper;
            };

            const appendMessages = function (messages) {
                if (!messageList || !Array.isArray(messages) || messages.length === 0) {
                    return false;
                }

                const shouldStickToBottom = chatBody
                    ? (chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight) < 80
                    : true;

                messages.forEach(function (message) {
                    if (messageList.querySelector(`[data-message-id="${Number(message.id || 0)}"]`)) {
                        return;
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
            }

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
