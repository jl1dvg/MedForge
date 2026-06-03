@extends('layouts.medforge')

{{--
    MedForge — WhatsApp Chat V3.
    Shares the WhatsappUiController::chat() data pipeline and keeps v2 hook IDs
    for compatibility with existing WhatsApp API endpoints.
--}}

@php
    $currentUser = is_array($currentUser ?? null) ? $currentUser : ['id' => null, 'display_name' => 'Usuario'];
    $selectedFilter = (string) ($selectedFilter ?? 'all');
    $search = (string) ($search ?? '');
    $dateFrom = (string) ($dateFrom ?? '');
    $dateTo = (string) ($dateTo ?? '');
    $selectedAgentId = isset($selectedAgentId) && $selectedAgentId !== null ? (int) $selectedAgentId : null;
    $selectedRoleId = isset($selectedRoleId) && $selectedRoleId !== null ? (int) $selectedRoleId : null;
    $listData = is_array($listData ?? null) ? $listData : [];
    $tabCounts = is_array($tabCounts ?? null) ? $tabCounts : [];
    $agents = is_array($agents ?? null) ? $agents : [];
    $roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
    $selectedConversation = is_array($selectedConversation ?? null) ? $selectedConversation : null;
    $canOperateConversation = (bool) ($canOperateConversation ?? false);
    $canSupervise = (bool) ($canSupervise ?? false);
    $presenceStatus = (string) ($presenceStatus ?? 'available');
    $conversationNotes = is_array($conversationNotes ?? null) ? $conversationNotes : [];
    $quickReplies = is_array($quickReplies ?? null) ? $quickReplies : [];
    $templateOptions = is_array($templateOptions ?? null) ? $templateOptions : [];
    $approvedTemplateOptions = array_values(array_filter($templateOptions, static function (array $template): bool {
        return strtolower((string) ($template['status'] ?? '')) === 'approved';
    }));
    $templateOptionsJson = json_encode($approvedTemplateOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $formatWaBody = static function (string $text): string {
        $safe = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safe = preg_replace('/\*([^*\r\n]+)\*/', '<strong>$1</strong>', $safe) ?? $safe;
        $safe = preg_replace('/_([^_\r\n]+)_/', '<em>$1</em>', $safe) ?? $safe;
        $safe = preg_replace('/~([^~\r\n]+)~/', '<del>$1</del>', $safe) ?? $safe;
        $safe = preg_replace('/`([^`\r\n]+)`/', '<code>$1</code>', $safe) ?? $safe;
        $safe = str_replace(["\r\n", "\r", "\n"], '<br>', $safe);
        return $safe;
    };

    // Tone for avatars — stable colour per name so users learn to recognise them.
    $avatarTone = static function (string $seed): string {
        $tones = ['violet', 'green', 'amber', 'rose', 'blue', 'cyan'];
        return $tones[abs(crc32($seed)) % count($tones)];
    };

    $tabs = [
        'requires_attention' => ['label' => 'Atención', 'icon' => 'mdi-alert-circle-outline'],
        'mine' => ['label' => 'Mías', 'icon' => 'mdi-account-check-outline'],
        'in_progress' => ['label' => 'En gestión', 'icon' => 'mdi-account-clock-outline'],
        'waiting_patient' => ['label' => 'Esperando', 'icon' => 'mdi-account-arrow-left-outline'],
        'scheduled' => ['label' => 'Agendados', 'icon' => 'mdi-calendar-check-outline'],
        'closed' => ['label' => 'Cerrados', 'icon' => 'mdi-archive-check-outline'],
    ];

    $advancedFilters = [
        'critical_backlog' => ['label' => 'Backlog >24h', 'icon' => 'mdi-alert-octagon-outline', 'hint' => 'Casos vencidos o sin atención oportuna.'],
        'captacion' => ['label' => 'Captación', 'icon' => 'mdi-bullseye-arrow', 'hint' => 'Pacientes nuevos o intención de agendar.'],
        'operacion' => ['label' => 'Operación', 'icon' => 'mdi-calendar-sync-outline', 'hint' => 'Citas vigentes, cambios, resultados o seguimiento.'],
        'informacion' => ['label' => 'Información', 'icon' => 'mdi-information-outline', 'hint' => 'Consultas generales sin proceso activo.'],
        'unread' => ['label' => 'Sin leer', 'icon' => 'mdi-bell-outline', 'hint' => 'Mensajes entrantes pendientes de revisión.'],
        'window_open' => ['label' => '24h abierta', 'icon' => 'mdi-timer-sand', 'hint' => 'Chats donde se puede responder libremente.'],
        'needs_template' => ['label' => 'Requiere plantilla', 'icon' => 'mdi-file-document-edit-outline', 'hint' => 'Ventana vencida; requiere plantilla aprobada.'],
    ];

    $managerMetrics = [
        'critical_backlog' => ['label' => 'SLA / backlog', 'value' => (int) ($tabCounts['critical_backlog'] ?? 0), 'tone' => 'danger'],
        'requires_attention' => ['label' => 'Atención', 'value' => (int) ($tabCounts['requires_attention'] ?? 0), 'tone' => 'danger'],
        'unread' => ['label' => 'Sin leer', 'value' => (int) ($tabCounts['unread'] ?? 0), 'tone' => 'accent'],
        'waiting_patient' => ['label' => 'Esperando', 'value' => (int) ($tabCounts['waiting_patient'] ?? 0), 'tone' => 'warning'],
        'needs_template' => ['label' => 'Plantilla', 'value' => (int) ($tabCounts['needs_template'] ?? 0), 'tone' => 'muted'],
        'scheduled' => ['label' => 'Agendados', 'value' => (int) ($tabCounts['scheduled'] ?? 0), 'tone' => 'success'],
    ];

    $emptyCopy = [
        'requires_attention' => 'No hay conversaciones nuevas que requieran atención inmediata.',
        'mine' => 'No tienes conversaciones activas asignadas en este rango.',
        'in_progress' => 'No hay conversaciones en gestión para este filtro.',
        'waiting_patient' => 'No hay conversaciones esperando respuesta del paciente.',
        'scheduled' => 'No hay conversaciones agendadas en este rango.',
        'closed' => 'No hay conversaciones cerradas en este rango.',
        'critical_backlog' => 'No hay backlog crítico mayor a 24 horas.',
        'captacion' => 'No hay conversaciones de captación en este rango.',
        'operacion' => 'No hay conversaciones de operación en este rango.',
        'informacion' => 'No hay conversaciones de información en este rango.',
        'unread' => 'No hay mensajes sin leer en este rango.',
        'window_open' => 'No hay conversaciones con ventana de 24h abierta.',
        'needs_template' => 'No hay conversaciones que requieran plantilla.',
    ];

    $previewRoute = '/v3/whatsapp/chat';
    $apiBase = '/v2/whatsapp/api';

    $buildLink = static function (array $extra = []) use ($selectedFilter, $search, $dateFrom, $dateTo, $selectedAgentId, $selectedRoleId, $previewRoute) {
        $qs = array_filter(array_merge([
            'filter' => $selectedFilter,
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'agent_id' => $selectedAgentId,
            'role_id' => $selectedRoleId,
        ], $extra), static fn ($v) => $v !== null && $v !== '');
        return $previewRoute . '?' . http_build_query($qs);
    };
@endphp

@push('styles')
    <style>
        .wa3 {
            --wa3-accent:      #5156be;
            --wa3-accent-soft: #edf2ff;
            --wa3-accent-fg:   #ffffff;
            --wa3-bg:          #f7f8fb;
            --wa3-surface:     #ffffff;
            --wa3-surface-2:   #f3f4f8;
            --wa3-border:      #ececf2;
            --wa3-border-soft: #f1f1f6;
            --wa3-text:        #172b4c;
            --wa3-text-mute:   #7e8299;
            --wa3-text-fade:   #b5b5c3;
            --wa3-bubble-in:   #ffffff;
            --wa3-bubble-out:  #eaecfb;
            --wa3-success:     #05825f;
            --wa3-danger:      #ee3158;
            --wa3-warning:     #ffa800;
            --wa3-radius:      14px;
            --wa3-radius-sm:   10px;
            display: grid;
            grid-template-columns: 360px 1fr;
            height: calc(100vh - 64px);
            background: var(--wa3-bg);
            color: var(--wa3-text);
            font-family: var(--bs-body-font-family, "IBM Plex Sans", system-ui, sans-serif);
        }
        .wa3-sr-only { position: absolute !important; width: 1px !important; height: 1px !important; padding: 0 !important; margin: -1px !important; overflow: hidden !important; clip: rect(0, 0, 0, 0) !important; white-space: nowrap !important; border: 0 !important; }
        .wa3.has-drawer { grid-template-columns: 360px 1fr 340px; }
        .wa3:not(.has-drawer) .wa3-drawer { display: none; }

        /* INBOX */
        .wa3-inbox { background: var(--wa3-surface); border-right: 1px solid var(--wa3-border); display: flex; flex-direction: column; overflow: hidden; }
        .wa3-inbox__head { padding: 18px 20px 12px; display: flex; flex-direction: column; gap: 12px; }
        .wa3-inbox__title-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .wa3-inbox__title { font: 600 18px/1.2 var(--font-display, "Rubik", system-ui, sans-serif); margin: 0; color: var(--wa3-text); }
        .wa3-iconbtn { width: 32px; height: 32px; display: inline-grid; place-items: center; background: transparent; border: 0; border-radius: 8px; color: var(--wa3-text-mute); cursor: pointer; font-size: 18px; transition: background .12s, color .12s; }
        .wa3-iconbtn:hover { background: var(--wa3-surface-2); color: var(--wa3-text); }
        .wa3-iconbtn.is-primary { color: var(--wa3-accent); }
        .wa3-iconbtn.is-primary:hover { background: var(--wa3-accent-soft); }

        .wa3-search { position: relative; }
        .wa3-search input { width: 100%; padding: 9px 14px 9px 36px; font: 400 13px var(--bs-body-font-family); color: var(--wa3-text); background: var(--wa3-surface-2); border: 1px solid transparent; border-radius: 999px; transition: background .12s, border-color .12s, box-shadow .12s; }
        .wa3-search input::placeholder { color: var(--wa3-text-fade); }
        .wa3-search input:focus { outline: 0; background: #fff; border-color: var(--wa3-accent); box-shadow: 0 0 0 3px rgba(81, 86, 190, .18); }
        .wa3-search i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--wa3-text-mute); font-size: 18px; }

        .wa3-chips-wrap { position: relative; display: flex; align-items: center; }
        .wa3-chips-arrow { flex-shrink: 0; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: var(--wa3-surface); border: 1px solid var(--wa3-border); border-radius: 50%; color: var(--wa3-text-mute); cursor: pointer; transition: opacity .15s, background .12s; z-index: 1; }
        .wa3-chips-arrow:hover { background: var(--wa3-surface-2); color: var(--wa3-text); }
        .wa3-chips-arrow.is-hidden { opacity: 0; pointer-events: none; }
        .wa3-chips-arrow--left { margin-left: 8px; margin-right: 4px; }
        .wa3-chips-arrow--right { margin-left: 4px; margin-right: 8px; }
        .wa3-chips { display: flex; gap: 6px; padding: 0 4px 10px; overflow-x: auto; scrollbar-width: none; flex: 1; }
        .wa3-chips::-webkit-scrollbar { display: none; }
        .wa3-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: transparent; border: 1px solid var(--wa3-border); color: var(--wa3-text-mute); font: 500 12px var(--bs-body-font-family); white-space: nowrap; cursor: pointer; text-decoration: none; transition: background .12s, color .12s, border-color .12s; }
        .wa3-chip:hover { color: var(--wa3-text); border-color: var(--wa3-text-fade); text-decoration: none; }
        .wa3-chip.is-active { background: var(--wa3-text); border-color: var(--wa3-text); color: #fff; }
        .wa3-chip__count { font: 700 11px var(--bs-body-font-family); padding: 0 6px; border-radius: 999px; background: rgba(255,255,255,.18); color: inherit; min-width: 18px; text-align: center; }
        .wa3-chip:not(.is-active) .wa3-chip__count { background: var(--wa3-surface-2); color: var(--wa3-text-mute); }
        .wa3-filter-menu { min-width: 340px; max-width: 380px; }
        .wa3-filter-menu .wa3-field { margin-bottom: 8px; }
        .wa3-filter-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 4px 6px 8px; }
        .wa3-filter-grid .wa3-field { margin-bottom: 0; }
        .wa3-filter-list { display: grid; gap: 4px; padding: 4px; }
        .wa3-filter-link { display: grid; grid-template-columns: 24px 1fr auto; gap: 8px; align-items: center; padding: 8px; border-radius: 10px; text-decoration: none; color: var(--wa3-text); border: 0; background: transparent; width: 100%; text-align: left; cursor: pointer; }
        .wa3-filter-link:hover { background: var(--wa3-surface-2); color: var(--wa3-text); text-decoration: none; }
        .wa3-filter-link.is-active { background: var(--wa3-accent-soft); color: var(--wa3-accent); }
        .wa3-filter-link i { font-size: 17px; color: var(--wa3-text-mute); }
        .wa3-filter-link.is-active i { color: var(--wa3-accent); }
        .wa3-filter-link strong { display: block; font: 700 12px var(--bs-body-font-family); }
        .wa3-filter-link small { display: block; font: 400 11px var(--bs-body-font-family); color: var(--wa3-text-mute); margin-top: 1px; }
        .wa3-filter-link .count { font: 800 10px var(--bs-body-font-family); min-width: 18px; text-align: center; padding: 2px 6px; border-radius: 999px; background: var(--wa3-surface-2); color: var(--wa3-text-mute); }
        .wa3-filter-link.is-active .count { background: rgba(81,86,190,.14); color: var(--wa3-accent); }
        .wa3-manager-btn { width: 32px; height: 32px; padding: 0; justify-content: center; border-radius: 999px; background: var(--wa3-surface-2); color: var(--wa3-accent); font-weight: 700; }
        .wa3-manager-btn i { color: var(--wa3-accent); }
        .wa3-manager-menu { min-width: 320px; }
        .wa3-metric-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; padding: 6px 4px 8px; }
        .wa3-metric { border: 1px solid var(--wa3-border); border-radius: 12px; padding: 9px 10px; background: #fff; }
        .wa3-metric strong { display: block; font: 800 17px/1 var(--font-display, "Rubik", system-ui, sans-serif); color: var(--wa3-text); }
        .wa3-metric span { display: block; margin-top: 4px; font: 600 10.5px var(--bs-body-font-family); color: var(--wa3-text-mute); text-transform: uppercase; letter-spacing: .04em; }
        .wa3-metric[data-tone="danger"] strong { color: var(--wa3-danger); }
        .wa3-metric[data-tone="warning"] strong { color: #8a5d0a; }
        .wa3-metric[data-tone="success"] strong { color: var(--wa3-success); }
        .wa3-metric[data-tone="accent"] strong { color: var(--wa3-accent); }

        .wa3-list { flex: 1; overflow-y: auto; padding: 4px 0 8px; }
        .wa3-row { display: grid; grid-template-columns: 44px 1fr auto; align-items: start; gap: 12px; padding: 12px 18px; cursor: pointer; border-left: 3px solid transparent; text-decoration: none; color: inherit; transition: background .12s; }
        .wa3-row:hover { background: var(--wa3-surface-2); text-decoration: none; color: inherit; }
        .wa3-row.is-active { background: var(--wa3-accent-soft); border-left-color: var(--wa3-accent); }
        .wa3-row + .wa3-row { border-top: 1px solid var(--wa3-border-soft); }

        .wa3-avatar { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; font: 600 15px var(--bs-body-font-family); color: var(--wa3-accent); background: var(--wa3-accent-soft); flex-shrink: 0; position: relative; }
        .wa3-avatar[data-tone="green"]  { background: #e0f3eb; color: #05825f; }
        .wa3-avatar[data-tone="amber"]  { background: #fff0d1; color: #8a5d0a; }
        .wa3-avatar[data-tone="rose"]   { background: #fde2e7; color: #9f2d3e; }
        .wa3-avatar[data-tone="blue"]   { background: #e3edf9; color: #2e5e99; }
        .wa3-avatar[data-tone="violet"] { background: #e9e7fb; color: #4e48a8; }
        .wa3-avatar[data-tone="cyan"]   { background: #d6f4f7; color: #0e7a87; }
        .wa3-avatar__status { position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--wa3-surface); background: var(--wa3-text-fade); }
        .wa3-avatar__status[data-state="open"]   { background: var(--wa3-success); }
        .wa3-avatar__status[data-state="urgent"] { background: var(--wa3-danger); }
        .wa3-avatar__status[data-state="warn"]   { background: var(--wa3-warning); }

        .wa3-row__main { min-width: 0; }
        .wa3-row__name { font: 600 14px var(--bs-body-font-family); color: var(--wa3-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wa3-row__sub { font: 400 11.5px var(--bs-body-font-family); color: var(--wa3-text-mute); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
        .wa3-row__preview { font: 400 12.5px var(--bs-body-font-family); color: var(--wa3-text-mute); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 4px; }
        .wa3-row.is-unread .wa3-row__preview, .wa3-row.is-unread .wa3-row__name { color: var(--wa3-text); font-weight: 600; }
        .wa3-row__aside { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; flex-shrink: 0; padding-top: 2px; }
        .wa3-row__time { font: 500 10.5px var(--bs-body-font-family); color: var(--wa3-text-mute); white-space: nowrap; }
        .wa3-row.is-unread .wa3-row__time { color: var(--wa3-accent); font-weight: 700; }
        .wa3-row__unread { background: var(--wa3-accent); color: #fff; font: 700 10px var(--bs-body-font-family); min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; display: inline-grid; place-items: center; }
        .wa3-row__tag { font: 600 10px var(--bs-body-font-family); letter-spacing: .04em; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; background: var(--wa3-surface-2); color: var(--wa3-text-mute); }
        .wa3-row__tag[data-tone="urgent"]   { background: #fde2e7; color: #9f2d3e; }
        .wa3-row__tag[data-tone="mine"]     { background: #e9e7fb; color: #4e48a8; }
        .wa3-row__tag[data-tone="waiting"]  { background: #fff0d1; color: #8a5d0a; }
        .wa3-row__tag[data-tone="resolved"] { background: #e0f3eb; color: #05825f; }
        .wa3-row__tag[data-tone="scheduled"] { background: #dff3ff; color: #0b5f84; }
        .wa3-row__tag[data-tone="closed"] { background: #eef0f4; color: #667085; }

        /* THREAD */
        .wa3-thread { display: flex; flex-direction: column; background: var(--wa3-bg); min-width: 0; overflow: hidden; }
        .wa3-thread__head { background: var(--wa3-surface); border-bottom: 1px solid var(--wa3-border); padding: 12px 22px; display: flex; align-items: center; gap: 14px; }
        .wa3-thread__main { display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1; }
        .wa3-thread__id { min-width: 0; }
        .wa3-thread__name { font: 600 15px var(--bs-body-font-family); color: var(--wa3-text); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wa3-thread__meta { font: 400 12px var(--bs-body-font-family); color: var(--wa3-text-mute); display: flex; align-items: center; gap: 6px; margin-top: 2px; }
        .wa3-thread__meta .sep { color: var(--wa3-text-fade); }
        .wa3-thread__actions { display: flex; align-items: center; gap: 4px; }
        .wa3-thread__actions .wa3-iconbtn { width: 36px; height: 36px; font-size: 19px; }

        /* Pill buttons in chat header */
        .wa3-hbtn-wrap { position: relative; }
        .wa3-hbtn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 11px; border-radius: 8px; background: transparent; border: 1px solid transparent; color: var(--wa3-text); cursor: pointer; font: 500 12.5px var(--bs-body-font-family); line-height: 1; transition: background .12s, border-color .12s, color .12s; }
        .wa3-hbtn i { font-size: 16px; color: var(--wa3-text-mute); }
        .wa3-hbtn:hover { background: var(--wa3-surface-2); }
        .wa3-hbtn:hover i { color: var(--wa3-text); }
        .wa3-hbtn.is-open { background: var(--wa3-accent-soft); color: var(--wa3-accent); }
        .wa3-hbtn.is-open i { color: var(--wa3-accent); }
        .wa3-hbtn.is-success { color: var(--wa3-success); border-color: rgba(5, 130, 95, .28); }
        .wa3-hbtn.is-success i { color: var(--wa3-success); }
        .wa3-hbtn.is-success:hover { background: rgba(5, 130, 95, .1); }
        .wa3-hbtn__menu { position: absolute; top: calc(100% + 6px); right: 0; min-width: 280px; max-width: 360px; background: var(--wa3-surface); border: 1px solid var(--wa3-border); border-radius: 12px; box-shadow: 0 12px 32px rgba(16,24,40,.12); padding: 8px; z-index: 30; }
        .wa3-hbtn__menu[hidden] { display: none; }
        .wa3-hbtn__menu h6 { font: 600 10px var(--bs-body-font-family); color: var(--wa3-text-mute); text-transform: uppercase; letter-spacing: .08em; padding: 6px 10px 4px; margin: 0; }
        .wa3-menu-item { display: grid; grid-template-columns: 28px 1fr auto; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; background: transparent; border: 0; width: 100%; text-align: left; color: var(--wa3-text); cursor: pointer; font: 500 13px var(--bs-body-font-family); line-height: 1.3; }
        .wa3-menu-item:hover { background: var(--wa3-surface-2); }
        .wa3-menu-item i.lead { font-size: 18px; color: var(--wa3-text-mute); width: 28px; text-align: center; }
        .wa3-menu-item .meta { font: 400 11.5px var(--bs-body-font-family); color: var(--wa3-text-mute); display: block; }
        a.wa3-menu-item { text-decoration: none; }
        a.wa3-menu-item:hover { color: var(--wa3-text); text-decoration: none; }
        .wa3-menu-item .dot { width: 6px; height: 6px; border-radius: 50%; background: var(--wa3-text-fade); }
        .wa3-menu-item .dot[data-state="online"] { background: var(--wa3-success); }
        .wa3-menu-item .dot[data-state="busy"]   { background: var(--wa3-warning); }
        .wa3-menu-footer { display: flex; gap: 6px; padding: 6px 4px 2px; margin-top: 4px; border-top: 1px solid var(--wa3-border-soft); }
        .wa3-manager-menu .wa3-menu-footer { flex-wrap: wrap; }
        .wa3-manager-menu .wa3-secondary-btn { padding: 7px 10px; }
        .wa3-menu-footer input, .wa3-menu-footer select { flex: 1; padding: 7px 10px; border: 1px solid var(--wa3-border); border-radius: 8px; background: var(--wa3-surface-2); color: var(--wa3-text); font: 400 12.5px var(--bs-body-font-family); }
        .wa3-menu-footer button { padding: 7px 14px; border-radius: 8px; border: 0; background: var(--wa3-accent); color: #fff; font: 600 12px var(--bs-body-font-family); cursor: pointer; }
        .wa3-iconbtn--sep { width: 1px; height: 20px; background: var(--wa3-border); margin: 0 4px; }

        /* Context bar */
        .wa3-context { background: var(--wa3-surface); border-bottom: 1px solid var(--wa3-border); padding: 8px 22px; display: flex; align-items: center; gap: 10px; overflow-x: auto; scrollbar-width: none; }
        .wa3-context::-webkit-scrollbar { display: none; }
        .wa3-context__item { display: inline-flex; align-items: center; gap: 6px; font: 500 11.5px var(--bs-body-font-family); color: var(--wa3-text-mute); white-space: nowrap; padding: 4px 0; }
        .wa3-context__item i { font-size: 14px; color: var(--wa3-text-fade); }
        .wa3-context__item strong { color: var(--wa3-text); font-weight: 600; }
        .wa3-context__item--open i  { color: var(--wa3-success); }
        .wa3-context__item--mine i  { color: var(--wa3-accent); }
        .wa3-context .sep { color: var(--wa3-border); }
        .wa3-chat-search { display: none; align-items: center; gap: 8px; padding: 8px 22px; background: #fff; border-bottom: 1px solid var(--wa3-border); }
        .wa3-chat-search.is-open { display: flex; }
        .wa3-chat-search input { flex: 1; min-width: 0; border: 1px solid var(--wa3-border); border-radius: 999px; background: var(--wa3-surface-2); color: var(--wa3-text); padding: 8px 12px; font: 500 12.5px var(--bs-body-font-family); }
        .wa3-chat-search input:focus { outline: 0; background: #fff; border-color: var(--wa3-accent); box-shadow: 0 0 0 3px rgba(81,86,190,.14); }
        .wa3-chat-search__count { min-width: 58px; text-align: center; font: 700 11px var(--bs-body-font-family); color: var(--wa3-text-mute); }
        .wa3-msg.is-search-match .wa3-bubble { border-color: rgba(81,86,190,.45); box-shadow: 0 0 0 3px rgba(81,86,190,.12); }
        .wa3-msg.is-search-current .wa3-bubble { border-color: var(--wa3-accent); box-shadow: 0 0 0 4px rgba(81,86,190,.22); }

        /* Messages */
        .wa3-messages { flex: 1; overflow-y: auto; padding: 18px 22px 8px; display: flex; flex-direction: column; gap: 6px; background: radial-gradient(circle at 20% 10%, rgba(81,86,190,.05), transparent 50%), radial-gradient(circle at 90% 90%, rgba(81,86,190,.03), transparent 60%), var(--wa3-bg); }
        .wa3-date { align-self: center; font: 600 11px var(--bs-body-font-family); color: var(--wa3-text-mute); background: var(--wa3-surface); border: 1px solid var(--wa3-border); padding: 4px 12px; border-radius: 999px; margin: 12px 0 8px; }
        .wa3-msg { display: flex; max-width: 640px; }
        .wa3-msg.is-out { align-self: flex-end; justify-content: flex-end; }
        .wa3-msg.is-in  { align-self: flex-start; }
        .wa3-bubble { background: var(--wa3-bubble-in); border: 1px solid var(--wa3-border-soft); padding: 8px 12px 7px; border-radius: var(--wa3-radius); font: 400 13.5px/1.45 var(--bs-body-font-family); color: var(--wa3-text); position: relative; max-width: 100%; box-shadow: 0 1px 2px rgba(16, 24, 40, .03); }
        .wa3-msg.is-out .wa3-bubble { background: var(--wa3-bubble-out); border-color: transparent; color: var(--wa3-text); }
        .wa3-bubble__meta { display: flex; justify-content: flex-end; align-items: center; gap: 4px; font: 500 10px var(--bs-body-font-family); color: var(--wa3-text-mute); margin-top: 4px; line-height: 1; }
        .wa3-bubble__meta i { font-size: 13px; }
        .wa3-bubble__meta .read { color: #2196f3; }
        .wa3-event { align-self: center; font: 500 11px var(--bs-body-font-family); color: var(--wa3-text-mute); background: rgba(255,168,0,.18); padding: 4px 12px; border-radius: 999px; margin: 4px 0; display: inline-flex; align-items: center; gap: 6px; }
        .wa3-event i { font-size: 13px; }
        .wa3-media { display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,.04); border-radius: 8px; padding: 8px 12px; margin: 0 -4px 6px; font: 500 12.5px var(--bs-body-font-family); }
        .wa3-media i { font-size: 22px; color: var(--wa3-text-mute); }
        .wa3-media small { color: var(--wa3-text-mute); font-weight: 400; display: block; }
        .wa3-media img, .wa3-media video { max-width: 240px; width: 100%; border-radius: 10px; display: block; margin-top: 6px; }

        /* Composer */
        .wa3-composer { background: var(--wa3-surface); border-top: 1px solid var(--wa3-border); padding: 12px 18px; }
        .wa3-composer__quickreplies { display: flex; gap: 6px; padding-bottom: 8px; overflow-x: auto; scrollbar-width: none; }
        .wa3-composer__quickreplies::-webkit-scrollbar { display: none; }
        .wa3-quickreply { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; background: var(--wa3-surface-2); border: 1px solid var(--wa3-border); color: var(--wa3-text); font: 500 12px var(--bs-body-font-family); cursor: pointer; white-space: nowrap; }
        .wa3-quickreply:hover { background: var(--wa3-accent-soft); border-color: var(--wa3-accent); color: var(--wa3-accent); }
        .wa3-composer__row { display: flex; align-items: flex-end; gap: 10px; background: var(--wa3-surface-2); border: 1px solid var(--wa3-border); border-radius: 24px; padding: 6px 6px 6px 12px; transition: border-color .12s, box-shadow .12s; }
        .wa3-composer__row:focus-within { background: #fff; border-color: var(--wa3-accent); box-shadow: 0 0 0 3px rgba(81, 86, 190, .18); }
        .wa3-composer textarea { flex: 1; resize: none; background: transparent; border: 0; font: 400 13.5px/1.5 var(--bs-body-font-family); color: var(--wa3-text); padding: 8px 4px; min-height: 24px; max-height: 140px; }
        .wa3-composer textarea:focus { outline: 0; }
        .wa3-composer__tools { display: flex; align-items: center; gap: 2px; }
        .wa3-emoji-wrap { position: relative; }
        .wa3-emoji-popover { position: absolute; right: 0; bottom: calc(100% + 10px); width: 248px; padding: 10px; border: 1px solid var(--wa3-border); border-radius: 14px; background: #fff; box-shadow: 0 14px 36px rgba(16,24,40,.14); z-index: 35; }
        .wa3-emoji-popover[hidden] { display: none; }
        .wa3-emoji-popover h6 { font: 700 10px var(--bs-body-font-family); color: var(--wa3-text-mute); text-transform: uppercase; letter-spacing: .08em; margin: 0 0 8px; }
        .wa3-emoji-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 4px; }
        .wa3-emoji { width: 26px; height: 26px; border: 0; border-radius: 8px; background: transparent; cursor: pointer; font-size: 17px; line-height: 1; }
        .wa3-emoji:hover { background: var(--wa3-surface-2); }
        .wa3-send { width: 38px; height: 38px; border-radius: 50%; border: 0; cursor: pointer; background: var(--wa3-accent); color: #fff; display: grid; place-items: center; font-size: 20px; transition: transform .12s, background .12s; }
        .wa3-send:hover:not(:disabled) { background: #3c40a0; }
        .wa3-send:disabled { background: var(--wa3-border); color: #fff; cursor: not-allowed; }
        .wa3-composer__hint { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; font: 400 11px var(--bs-body-font-family); color: var(--wa3-text-mute); }
        .wa3-composer__hint kbd { font: 600 10px ui-monospace, monospace; padding: 1px 5px; border-radius: 4px; background: var(--wa3-surface-2); border: 1px solid var(--wa3-border); color: var(--wa3-text-mute); }

        /* Drawer */
        .wa3-drawer { background: var(--wa3-surface); border-left: 1px solid var(--wa3-border); overflow-y: auto; }
        .wa3-drawer__profile { padding: 28px 22px 18px; text-align: center; border-bottom: 1px solid var(--wa3-border-soft); }
        .wa3-drawer__profile .wa3-avatar { width: 76px; height: 76px; font-size: 26px; margin: 0 auto 10px; }
        .wa3-drawer__profile h3 { font: 600 17px var(--font-display, "Rubik", system-ui, sans-serif); margin: 0; color: var(--wa3-text); }
        .wa3-drawer__profile p { font: 400 12.5px var(--bs-body-font-family); color: var(--wa3-text-mute); margin: 4px 0 14px; }
        .wa3-drawer__quickactions { display: flex; gap: 6px; justify-content: center; }
        .wa3-quickaction { flex: 1; max-width: 80px; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 10px 6px; border-radius: var(--wa3-radius-sm); background: var(--wa3-surface-2); border: 1px solid transparent; color: var(--wa3-text); cursor: pointer; font: 500 10.5px var(--bs-body-font-family); text-align: center; transition: border-color .12s, color .12s, background .12s; }
        .wa3-quickaction:hover { border-color: var(--wa3-accent); color: var(--wa3-accent); background: var(--wa3-accent-soft); }
        .wa3-quickaction i { font-size: 18px; color: var(--wa3-text-mute); }
        .wa3-quickaction:hover i { color: var(--wa3-accent); }
        a.wa3-quickaction { text-decoration: none; }
        .wa3-quickaction[aria-disabled="true"] { opacity: .45; cursor: not-allowed; pointer-events: none; }
        .wa3-drawer__section { padding: 14px 22px; border-bottom: 1px solid var(--wa3-border-soft); }
        .wa3-drawer__section h6 { font: 600 10px var(--bs-body-font-family); color: var(--wa3-text-mute); text-transform: uppercase; letter-spacing: .1em; margin: 0 0 10px; }
        .wa3-kv { display: grid; grid-template-columns: 1fr; gap: 8px; }
        .wa3-kv__row { display: flex; justify-content: space-between; gap: 12px; }
        .wa3-kv__row .k { font: 500 12px var(--bs-body-font-family); color: var(--wa3-text-mute); display: inline-flex; align-items: center; gap: 6px; }
        .wa3-kv__row .v { font: 500 12.5px var(--bs-body-font-family); color: var(--wa3-text); text-align: right; }
        .wa3-tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .wa3-tag { font: 500 11px var(--bs-body-font-family); padding: 3px 9px; border-radius: 999px; background: var(--wa3-accent-soft); color: var(--wa3-accent); }

        .wa3-admin-btn { width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 9px 12px; border-radius: 10px; border: 1px solid var(--wa3-border, #ececf2); background: var(--wa3-surface-2, #f3f4f8); color: var(--wa3-text, #172b4c); font: 600 12px var(--bs-body-font-family); cursor: pointer; }
        .wa3-admin-btn:hover { background: var(--wa3-accent-soft, #edf2ff); border-color: var(--wa3-accent, #5156be); color: var(--wa3-accent, #5156be); }
        .wa3-field { display: grid; gap: 5px; margin-bottom: 10px; }
        .wa3-field label { font: 600 11px var(--bs-body-font-family); color: var(--wa3-text-mute, #7e8299); }
        .wa3-field input, .wa3-field select, .wa3-field textarea { width: 100%; border: 1px solid var(--wa3-border, #ececf2); border-radius: 10px; background: #fff; color: var(--wa3-text, #172b4c); padding: 9px 11px; font: 400 13px var(--bs-body-font-family); }
        .wa3-field textarea { resize: vertical; min-height: 76px; }
        .wa3-field input:focus, .wa3-field select:focus, .wa3-field textarea:focus { outline: 0; border-color: var(--wa3-accent, #5156be); box-shadow: 0 0 0 3px rgba(81,86,190,.14); }
        .wa3-action-row { display: flex; gap: 8px; align-items: center; justify-content: flex-end; }
        .wa3-primary-btn, .wa3-secondary-btn { border-radius: 10px; padding: 9px 14px; font: 700 12px var(--bs-body-font-family); cursor: pointer; border: 1px solid transparent; }
        .wa3-primary-btn { background: var(--wa3-accent, #5156be); color: #fff; }
        .wa3-secondary-btn { background: var(--wa3-surface-2, #f3f4f8); color: var(--wa3-text, #172b4c); border-color: var(--wa3-border, #ececf2); }
        .wa3-feedback { font: 500 12px var(--bs-body-font-family); color: var(--wa3-text-mute, #7e8299); min-height: 18px; }
        .wa3-feedback[data-tone="success"] { color: var(--wa3-success, #05825f); }
        .wa3-feedback[data-tone="danger"] { color: var(--wa3-danger, #ee3158); }
        .wa3-picker-results { display: grid; gap: 8px; max-height: 260px; overflow: auto; margin-top: 10px; }
        .wa3-picker-card { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: center; padding: 10px; border: 1px solid var(--wa3-border, #ececf2); border-radius: 12px; background: #fff; }
        .wa3-picker-card.is-active { border-color: var(--wa3-accent, #5156be); background: var(--wa3-accent-soft, #edf2ff); }
        .wa3-picker-card strong { font: 700 13px var(--bs-body-font-family); color: var(--wa3-text, #172b4c); }
        .wa3-picker-card small { display: block; font: 500 11.5px var(--bs-body-font-family); color: var(--wa3-text-mute, #7e8299); margin-top: 2px; }
        .wa3-template-preview { border: 1px dashed var(--wa3-border, #ececf2); border-radius: 12px; padding: 12px; background: var(--wa3-surface-2, #f3f4f8); font: 400 12.5px/1.45 var(--bs-body-font-family); color: var(--wa3-text, #172b4c); white-space: pre-wrap; }
        .wa3-upload { display: none; align-items: center; justify-content: space-between; gap: 10px; margin-top: 8px; padding: 8px 10px; border-radius: 12px; background: var(--wa3-accent-soft, #edf2ff); color: var(--wa3-accent, #5156be); font: 600 12px var(--bs-body-font-family); }
        .wa3-upload.is-visible { display: flex; }
        .wa3-upload button { border: 0; background: transparent; color: var(--wa3-accent, #5156be); cursor: pointer; font-size: 17px; }
        .wa3-realtime { display: none; align-items: center; justify-content: space-between; gap: 10px; padding: 8px 22px; background: #fff7e6; border-bottom: 1px solid #ffe0a6; color: #8a5d0a; font: 600 12px var(--bs-body-font-family); }
        .wa3-realtime.is-visible { display: flex; }
        .wa3-realtime button { border: 0; border-radius: 999px; padding: 5px 10px; background: #8a5d0a; color: #fff; font: 700 11px var(--bs-body-font-family); cursor: pointer; }
        .wa3-trail { position: relative; display: grid; gap: 9px; padding-left: 14px; }
        .wa3-trail::before { content: ""; position: absolute; left: 3px; top: 4px; bottom: 4px; width: 1px; background: var(--wa3-border); }
        .wa3-trail__item { position: relative; font: 400 12px var(--bs-body-font-family); color: var(--wa3-text); }
        .wa3-trail__item::before { content: ""; position: absolute; left: -15px; top: 4px; width: 8px; height: 8px; border-radius: 50%; background: var(--wa3-accent); border: 2px solid #fff; }
        .wa3-trail__meta { color: var(--wa3-text-mute); font-size: 11px; margin-top: 2px; }
        .wa3-trail-scroll { max-height: 260px; overflow-y: auto; padding-right: 4px; }
        .wa3-modal {
            --wa3-accent: #5156be;
            --wa3-accent-soft: #edf2ff;
            --wa3-bg: #f7f8fb;
            --wa3-surface: #ffffff;
            --wa3-surface-2: #f3f4f8;
            --wa3-border: #ececf2;
            --wa3-text: #172b4c;
            --wa3-text-mute: #7e8299;
            --wa3-success: #05825f;
            --wa3-danger: #ee3158;
            position: fixed;
            inset: 0;
            z-index: 2147483000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(16,24,40,.62);
            isolation: isolate;
            backdrop-filter: blur(2px);
        }
        .wa3-modal[hidden] { display: none; }
        .wa3-modal__card { position: relative; z-index: 1; width: min(920px, 100%); max-height: calc(100vh - 48px); overflow: auto; border-radius: 20px; background: #fff; color: #172b4c; box-shadow: 0 24px 70px rgba(16,24,40,.28); opacity: 1; }
        .wa3-modal__head, .wa3-modal__foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #ececf2; background: #fff; }
        .wa3-modal__foot { border-top: 1px solid #ececf2; border-bottom: 0; }
        .wa3-modal__head h3 { font: 700 16px var(--font-display, "Rubik", system-ui, sans-serif); margin: 0; color: #172b4c; }
        .wa3-modal__body { padding: 18px; }
        .wa3-modal__grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(280px, .65fr); gap: 16px; }
        @media (max-width: 820px) { .wa3-modal__grid { grid-template-columns: 1fr; } }
        .wa3-tour-modal .wa3-modal__card { width: min(560px, 100%); }
        .wa3-tour-modal .wa3-modal__body p { margin: 0 0 10px; font: 400 13px/1.5 var(--bs-body-font-family); color: var(--wa3-text-mute); }
        .wa3-tour-step { display: flex; align-items: center; gap: 8px; margin-top: 12px; color: var(--wa3-text-mute); font: 700 11px var(--bs-body-font-family); text-transform: uppercase; letter-spacing: .06em; }
        .wa3-tour-step__bar { flex: 1; height: 4px; border-radius: 999px; background: var(--wa3-surface-2); overflow: hidden; }
        .wa3-tour-step__bar span { display: block; height: 100%; width: 0; border-radius: inherit; background: var(--wa3-accent); transition: width .18s ease-out; }
        .wa3-tour-focus { position: relative; z-index: 2147482999; outline: 3px solid rgba(81, 86, 190, .75); outline-offset: 4px; box-shadow: 0 0 0 8px rgba(81, 86, 190, .14); border-radius: 12px; }

        .wa3-empty { display: flex; align-items: center; justify-content: center; height: 100%; padding: 40px; }
        .wa3-empty__card { text-align: center; max-width: 360px; }
        .wa3-empty__icon { width: 64px; height: 64px; margin: 0 auto 16px; border-radius: 50%; background: var(--wa3-accent-soft); color: var(--wa3-accent); display: grid; place-items: center; font-size: 32px; }
        .wa3-empty h3 { font: 600 16px var(--font-display, "Rubik", system-ui, sans-serif); margin: 0 0 6px; }
        .wa3-empty p { font: 400 13px var(--bs-body-font-family); color: var(--wa3-text-mute); margin: 0; }

        @media (max-width: 1280px) { .wa3.has-drawer { grid-template-columns: 320px 1fr 0; } .wa3.has-drawer .wa3-drawer { display: none; } }
        @media (max-width: 1000px) { .wa3 { grid-template-columns: 280px 1fr; } }
        @media (max-width: 760px) {
            .wa3, .wa3.has-drawer { grid-template-columns: 1fr; grid-template-rows: minmax(220px, 40vh) minmax(0, 1fr); height: auto; min-height: calc(100vh - 64px); }
            .wa3-inbox { border-right: 0; border-bottom: 1px solid var(--wa3-border); min-width: 0; }
            .wa3-thread__head { align-items: flex-start; flex-wrap: wrap; padding: 12px 14px; }
            .wa3-thread__actions { width: 100%; overflow-x: auto; padding-bottom: 2px; }
            .wa3-context, .wa3-chat-search, .wa3-realtime { padding-left: 14px; padding-right: 14px; }
            .wa3-messages { min-height: 48vh; padding-left: 14px; padding-right: 14px; }
            .wa3-composer { padding: 10px 12px; }
            .wa3-composer__tools { display: none; }
            .wa3-modal { padding: 12px; align-items: flex-end; }
            .wa3-modal__card { max-height: calc(100vh - 24px); border-radius: 16px 16px 0 0; }
        }

        /* Conversations / Agents view tabs */
        .wa3-inbox__tabs { display: inline-flex; gap: 0; background: var(--wa3-surface-2); padding: 3px; border-radius: 10px; }
        .wa3-inbox__tab { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border: 0; background: transparent; border-radius: 7px; color: var(--wa3-text-mute); font: 600 12.5px var(--bs-body-font-family); cursor: pointer; line-height: 1; transition: background .12s, color .12s; }
        .wa3-inbox__tab i { font-size: 15px; }
        .wa3-inbox__tab:hover { color: var(--wa3-text); }
        .wa3-inbox__tab.is-active { background: var(--wa3-surface); color: var(--wa3-text); box-shadow: 0 1px 3px rgba(16,24,40,.06); }

        /* Agent filter banner */
        .wa3-agentfilter { display: flex; align-items: center; gap: 8px; margin: 0 16px 8px; padding: 7px 10px; background: var(--wa3-accent-soft); color: var(--wa3-accent); border-radius: 8px; font: 500 12px var(--bs-body-font-family); }
        .wa3-agentfilter i { font-size: 16px; }
        .wa3-agentfilter strong { font-weight: 700; }
        .wa3-agentfilter button { margin-left: auto; background: transparent; border: 0; color: var(--wa3-accent); cursor: pointer; width: 20px; height: 20px; display: grid; place-items: center; border-radius: 4px; }
        .wa3-agentfilter button:hover { background: rgba(81,86,190,.15); }

        /* Supervisor summary strip */
        .wa3-supervisor { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; margin: 0 16px 10px; background: var(--wa3-border-soft); border: 1px solid var(--wa3-border-soft); border-radius: 10px; overflow: hidden; }
        .wa3-supervisor__metric { background: var(--wa3-surface); padding: 10px 12px; display: flex; flex-direction: column; gap: 2px; }
        .wa3-supervisor__metric .k { font: 600 9.5px var(--bs-body-font-family); color: var(--wa3-text-mute); text-transform: uppercase; letter-spacing: .08em; }
        .wa3-supervisor__metric .v { font: 700 20px var(--font-display, "Rubik", system-ui, sans-serif); color: var(--wa3-text); line-height: 1.1; }
        .wa3-supervisor__metric .of { font: 500 12px var(--bs-body-font-family); color: var(--wa3-text-mute); margin-left: 4px; }

        /* Agent row in inbox list */
        .wa3-agent { display: grid; grid-template-columns: 44px 1fr 60px; gap: 12px; align-items: center; padding: 12px 18px; cursor: pointer; border-left: 3px solid transparent; transition: background .12s; }
        .wa3-agent:hover { background: var(--wa3-surface-2); }
        .wa3-agent.is-active { background: var(--wa3-accent-soft); border-left-color: var(--wa3-accent); }
        .wa3-agent + .wa3-agent { border-top: 1px solid var(--wa3-border-soft); }
        .wa3-agent__main { min-width: 0; }
        .wa3-agent__name { font: 600 14px var(--bs-body-font-family); color: var(--wa3-text); display: flex; align-items: center; gap: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .wa3-agent__me { font: 700 9px var(--bs-body-font-family); color: var(--wa3-accent); text-transform: uppercase; letter-spacing: .06em; background: var(--wa3-accent-soft); padding: 2px 5px; border-radius: 4px; }
        .wa3-agent__role { font: 400 11.5px var(--bs-body-font-family); color: var(--wa3-text-mute); margin-top: 1px; }
        .wa3-agent__stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 4px; font: 500 11px var(--bs-body-font-family); color: var(--wa3-text-mute); }
        .wa3-agent__stats span { display: inline-flex; align-items: center; gap: 3px; }
        .wa3-agent__stats i { font-size: 13px; color: var(--wa3-text-fade); }
        .wa3-agent__workload { width: 50px; height: 4px; border-radius: 999px; background: var(--wa3-surface-2); overflow: hidden; }
        .wa3-agent__workload-bar { height: 100%; background: linear-gradient(90deg, var(--wa3-success) 0%, var(--wa3-warning) 60%, var(--wa3-danger) 100%); border-radius: 999px; transition: width .3s; }

        /* Trazabilidad timeline */
        .wa3-trail-flat { position: relative; display: grid; gap: 9px; padding-left: 14px; max-height: 250px; overflow-y: auto; }
        .wa3-trail-flat::before { content: ""; position: absolute; left: 3px; top: 4px; bottom: 4px; width: 1px; background: var(--wa3-border); }
        .wa3-trail-flat__item { position: relative; font: 400 12px var(--bs-body-font-family); color: var(--wa3-text); }
        .wa3-trail-flat__item::before { content: ""; position: absolute; left: -15px; top: 4px; width: 8px; height: 8px; border-radius: 50%; background: var(--wa3-accent); border: 2px solid #fff; }
        .wa3-trail-flat__meta { color: var(--wa3-text-mute); font-size: 11px; margin-top: 2px; }

        /* Notes in drawer */
        .wa3-note { font: 400 12.5px var(--bs-body-font-family); color: var(--wa3-text); padding: 7px 0; border-bottom: 1px solid var(--wa3-border-soft); }
        .wa3-note__who { font-weight: 600; color: var(--wa3-accent); font-size: 11px; margin-bottom: 2px; }

        /* Toast notifications */
        .wa3-toast-wrap { position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); z-index: 250; }
        .wa3-toast { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 999px; background: var(--wa3-text); color: #fff; font: 600 13px var(--bs-body-font-family); box-shadow: 0 12px 32px rgba(16,24,40,.24); animation: wa3-toast-in .2s ease-out; }
        .wa3-toast i { font-size: 18px; color: #fff; }
        @keyframes wa3-toast-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    </style>
@endpush

@section('content')
{{-- WhatsApp Chat v3 — React SPA mount point.
     All UI is rendered by resources/js/whatsapp/main.jsx via Vite. --}}
<script id="wa3-config" type="application/json">{!! json_encode([
    'currentUser' => [
        'id'   => $currentUser['id'] ?? null,
        'name' => $currentUser['display_name'] ?? $currentUser['name'] ?? 'Usuario',
    ],
    'canSupervise'  => $canSupervise,
    'canOperate'    => $canOperateConversation,
    'pusher'        => $realtimeConfig ?? [],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
<div id="wa3-react-root" style="height:calc(100vh - 64px)"></div>
@endsection

@push('scripts')
@vite('resources/js/whatsapp/main.jsx')
@endpush
