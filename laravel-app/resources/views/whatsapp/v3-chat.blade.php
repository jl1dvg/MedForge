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

        .wa3-chips { display: flex; gap: 6px; padding: 0 16px 10px; overflow-x: auto; scrollbar-width: none; }
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
        .wa3-filter-link { display: grid; grid-template-columns: 24px 1fr auto; gap: 8px; align-items: center; padding: 8px; border-radius: 10px; text-decoration: none; color: var(--wa3-text); }
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
    </style>
@endpush

@section('content')
<div class="wa3 has-drawer" id="wa3-root">

    {{-- ================= INBOX ================= --}}
    <aside class="wa3-inbox">
        <div class="wa3-inbox__head">
            <div class="wa3-inbox__title-row">
                <h2 class="wa3-inbox__title">Conversaciones</h2>
                <div style="display:flex;gap:2px;">
                    @if($canSupervise)
                        <div class="wa3-hbtn-wrap" data-wa3-menu="manager">
                            <button class="wa3-hbtn wa3-manager-btn" type="button" data-wa3-menu-toggle="manager" title="Vista gerencial" aria-label="Vista gerencial">
                                <i class="mdi mdi-view-dashboard-outline"></i>
                                <span class="wa3-sr-only">Vista gerencial</span>
                            </button>
                            <div class="wa3-hbtn__menu wa3-manager-menu" hidden>
                                <h6>Métricas rápidas</h6>
                                <div class="wa3-metric-grid">
                                    @foreach($managerMetrics as $metric)
                                        <div class="wa3-metric" data-tone="{{ $metric['tone'] }}">
                                            <strong>{{ $metric['value'] }}</strong>
                                            <span>{{ $metric['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="wa3-menu-footer">
                                    <a class="wa3-secondary-btn" href="/v2/whatsapp/dashboard"
                                       style="text-decoration:none;display:inline-flex;align-items:center;">Dashboard</a>
                                    <a class="wa3-secondary-btn" href="{{ $buildLink(['filter' => 'critical_backlog']) }}"
                                       style="text-decoration:none;display:inline-flex;align-items:center;">Ver backlog</a>
                                    <a class="wa3-secondary-btn" href="{{ $buildLink(['filter' => 'unread']) }}"
                                       style="text-decoration:none;display:inline-flex;align-items:center;">Sin leer</a>
                                    <a class="wa3-secondary-btn" href="{{ $buildLink(['filter' => 'needs_template']) }}"
                                       style="text-decoration:none;display:inline-flex;align-items:center;">Plantilla</a>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if($canSupervise)
                        <button class="wa3-iconbtn" title="Reencolar vencidos" aria-label="Reencolar vencidos" type="button" id="wa3-requeue-expired">
                            <i class="mdi mdi-restore-alert"></i>
                            <span class="wa3-sr-only">Reencolar vencidos</span>
                        </button>
                    @endif
                    <button class="wa3-iconbtn" title="Nueva conversación" aria-label="Nueva conversación" type="button"
                            onclick="document.getElementById('wa3-new-modal')?.removeAttribute('hidden')">
                        <i class="mdi mdi-plus"></i>
                        <span class="wa3-sr-only">Nueva conversación</span>
                    </button>
                    <div class="wa3-hbtn-wrap" data-wa3-menu="filters">
                        <button class="wa3-iconbtn" title="Filtros avanzados" aria-label="Filtros avanzados" type="button" data-wa3-menu-toggle="filters">
                            <i class="mdi mdi-tune-variant"></i>
                            <span class="wa3-sr-only">Filtros avanzados</span>
                        </button>
                        <div class="wa3-hbtn__menu wa3-filter-menu" hidden>
                            <h6>Rango de conversaciones</h6>
                            <form method="GET" action="{{ $previewRoute }}">
                                <input type="hidden" name="filter" value="{{ $selectedFilter }}">
                                <input type="hidden" name="search" value="{{ $search }}">
                                @if($selectedAgentId !== null)
                                    <input type="hidden" name="agent_id" value="{{ $selectedAgentId }}">
                                @endif
                                @if($selectedRoleId !== null)
                                    <input type="hidden" name="role_id" value="{{ $selectedRoleId }}">
                                @endif
                                <div class="wa3-filter-grid">
                                    <div class="wa3-field">
                                        <label for="wa3-date-from">Desde</label>
                                        <input type="date" id="wa3-date-from" name="date_from" value="{{ $dateFrom }}">
                                    </div>
                                    <div class="wa3-field">
                                        <label for="wa3-date-to">Hasta</label>
                                        <input type="date" id="wa3-date-to" name="date_to" value="{{ $dateTo }}">
                                    </div>
                                </div>
                                <div class="wa3-menu-footer">
                                    <a class="wa3-secondary-btn" href="{{ $buildLink(['date_from' => null, 'date_to' => null]) }}"
                                       style="text-decoration:none;display:inline-flex;align-items:center;">Limpiar</a>
                                    <button type="submit">Aplicar fechas</button>
                                </div>
                            </form>
                            <h6>Bandejas avanzadas</h6>
                            <div class="wa3-filter-list">
                                @foreach($advancedFilters as $key => $filterMeta)
                                    <a href="{{ $buildLink(['filter' => $key]) }}"
                                       class="wa3-filter-link {{ $selectedFilter === $key ? 'is-active' : '' }}">
                                        <i class="mdi {{ $filterMeta['icon'] }}"></i>
                                        <span>
                                            <strong>{{ $filterMeta['label'] }}</strong>
                                            <small>{{ $filterMeta['hint'] }}</small>
                                        </span>
                                        <span class="count">{{ (int) ($tabCounts[$key] ?? 0) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <form method="GET" action="{{ $previewRoute }}" class="wa3-search">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $search }}" placeholder="Buscar nombre, número o HC…">
                <input type="hidden" name="filter" value="{{ $selectedFilter }}">
                @if($dateFrom !== '')
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                @endif
                @if($dateTo !== '')
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                @endif
                @if($selectedAgentId !== null)
                    <input type="hidden" name="agent_id" value="{{ $selectedAgentId }}">
                @endif
                @if($selectedRoleId !== null)
                    <input type="hidden" name="role_id" value="{{ $selectedRoleId }}">
                @endif
            </form>
        </div>

        <nav class="wa3-chips">
            @foreach($tabs as $key => $t)
                <a href="{{ $buildLink(['filter' => $key]) }}"
                   class="wa3-chip {{ $selectedFilter === $key ? 'is-active' : '' }}">
                    {{ $t['label'] }}
                    @if(isset($tabCounts[$key]))
                        <span class="wa3-chip__count">{{ $tabCounts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="wa3-list">
            @forelse($listData as $c)
                @php
                    $isActive = $selectedConversation && (int) $selectedConversation['id'] === (int) $c['id'];
                    $isUnread = (int) ($c['unread_count'] ?? 0) > 0;
                    $name = $c['display_name'] ?: $c['wa_number'];
                    $initials = mb_strtoupper(mb_substr($name, 0, 1) . mb_substr(strpos($name, ' ') !== false ? substr($name, strpos($name, ' ') + 1) : '', 0, 1));
                    $tone = $avatarTone($name);

                    $operationalStatus = (string) ($c['operational_status'] ?? 'new');
                    $priorityLevel = (string) ($c['priority_level'] ?? 'low');
                    $statusDot = match (true) {
                        $priorityLevel === 'critical' => 'urgent',
                        in_array($operationalStatus, ['requires_attention', 'in_progress'], true) => 'open',
                        $operationalStatus === 'waiting_patient' => 'warn',
                        default => null,
                    };

                    $tagTone = match ($operationalStatus) {
                        'requires_attention' => 'urgent',
                        'in_progress' => 'mine',
                        'waiting_patient' => 'waiting',
                        'scheduled' => 'scheduled',
                        'resolved', 'closed_followup', 'closed_other' => 'closed',
                        default => 'gray',
                    };
                    $tagLabel = (string) ($c['operational_status_label'] ?? 'Nuevo');
                @endphp
                <a href="{{ $buildLink(['conversation' => $c['id']]) }}"
                   class="wa3-row {{ $isActive ? 'is-active' : '' }} {{ $isUnread ? 'is-unread' : '' }}"
                   data-wa-conversation-item="{{ (int) $c['id'] }}">
                    <div class="wa3-avatar" data-tone="{{ $tone }}">
                        {{ $initials }}
                        @if($statusDot)
                            <span class="wa3-avatar__status" data-state="{{ $statusDot }}"></span>
                        @endif
                    </div>
                    <div class="wa3-row__main">
                        <div class="wa3-row__name">{{ $name }}</div>
                        <div class="wa3-row__sub">
                            {{ $c['patient_full_name'] ?: $c['wa_number'] }}
                            @if(!empty($c['patient_hc_number'])) · HC {{ $c['patient_hc_number'] }} @endif
                        </div>
                        <div class="wa3-row__preview"
                             data-wa-conversation-preview="{{ (int) $c['id'] }}">{{ $c['last_message_preview'] ?: '[' . ($c['last_message_type'] ?: 'mensaje') . ']' }}</div>
                    </div>
                    <div class="wa3-row__aside">
                        <span class="wa3-row__time" data-ts="{{ $c['last_message_at'] ?? '' }}">
                            @if(!empty($c['last_message_at']))
                                {{ \Carbon\Carbon::parse($c['last_message_at'])->isToday() ? \Carbon\Carbon::parse($c['last_message_at'])->format('H:i') : \Carbon\Carbon::parse($c['last_message_at'])->format('d/m') }}
                            @endif
                        </span>
                        @if($isUnread)
                            <span class="wa3-row__unread"
                                  data-wa-conversation-unread="{{ (int) $c['id'] }}">{{ (int) $c['unread_count'] }}</span>
                        @elseif($tagLabel)
                            <span class="wa3-row__tag" data-tone="{{ $tagTone }}">{{ $tagLabel }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <div style="padding:24px;text-align:center;color:var(--wa3-text-mute);font:400 13px var(--bs-body-font-family);">
                    <div style="width:44px;height:44px;margin:0 auto 10px;border-radius:14px;background:var(--wa3-surface-2);display:grid;place-items:center;color:var(--wa3-text-fade);font-size:22px;">
                        <i class="mdi mdi-inbox-outline"></i>
                    </div>
                    <div style="font-weight:700;color:var(--wa3-text);margin-bottom:4px;">
                        {{ $tabs[$selectedFilter]['label'] ?? $advancedFilters[$selectedFilter]['label'] ?? 'Filtro' }}
                    </div>
                    <div>{{ $emptyCopy[$selectedFilter] ?? 'No hay conversaciones para este filtro.' }}</div>
                    @if($dateFrom !== '' || $dateTo !== '' || $search !== '')
                        <a href="{{ $buildLink(['search' => null, 'date_from' => null, 'date_to' => null]) }}"
                           style="display:inline-flex;margin-top:10px;color:var(--wa3-accent);font-weight:700;text-decoration:none;">Limpiar búsqueda y fechas</a>
                    @endif
                </div>
            @endforelse
        </div>
    </aside>

    {{-- ================= THREAD ================= --}}
    <section class="wa3-thread">
        @if($selectedConversation)
            @php
                $selName = $selectedConversation['display_name'] ?: $selectedConversation['wa_number'];
                $selInit = mb_strtoupper(mb_substr($selName, 0, 1) . mb_substr(strpos($selName, ' ') !== false ? substr($selName, strpos($selName, ' ') + 1) : '', 0, 1));
                $selTone = $avatarTone($selName);
                $selWState = $selectedConversation['messaging_window_state'] ?? '';
                $selAssignedUserId = (int) ($selectedConversation['assigned_user_id'] ?? 0);
                $currentUserId = (int) ($currentUser['id'] ?? 0);
                $canReplyHere = $selAssignedUserId > 0 && $selAssignedUserId === $currentUserId;
                $isMine = $canReplyHere;
                $selOperationalStatus = (string) ($selectedConversation['operational_status'] ?? 'new');
                $selPriorityLevel = (string) ($selectedConversation['priority_level'] ?? 'low');
            @endphp

            <header class="wa3-thread__head">
                <div class="wa3-thread__main">
                    <div class="wa3-avatar" data-tone="{{ $selTone }}">
                        {{ $selInit }}
                        <span class="wa3-avatar__status" data-state="{{ $selWState === 'window_open' ? 'open' : 'warn' }}"></span>
                    </div>
                    <div class="wa3-thread__id">
                        <h3 class="wa3-thread__name">{{ $selName }}</h3>
                        <div class="wa3-thread__meta">
                            <span>{{ $selectedConversation['wa_number'] }}</span>
                            @if(!empty($selectedConversation['patient_full_name']))
                                <span class="sep">·</span>
                                <span>{{ $selectedConversation['patient_full_name'] }}</span>
                            @endif
                            @if(!empty($selectedConversation['patient_hc_number']))
                                <span class="sep">·</span>
                                <span>HC {{ $selectedConversation['patient_hc_number'] }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="wa3-thread__actions">
                    <button class="wa3-iconbtn" title="Buscar en chat" aria-label="Buscar en chat" type="button" id="wa3-chat-search-toggle"><i class="mdi mdi-magnify"></i><span class="wa3-sr-only">Buscar en chat</span></button>
                    <span class="wa3-iconbtn--sep"></span>

                    @if($canOperateConversation && !$isMine)
                        <button class="wa3-hbtn" type="button"
                                data-wa-action="assign-self"
                                data-conversation-id="{{ $selectedConversation['id'] }}">
                            <i class="mdi mdi-account-plus-outline"></i><span>Tomar</span>
                        </button>
                    @endif

                    {{-- Transferir --}}
                    <div class="wa3-hbtn-wrap" data-wa3-menu="transfer">
                        <button class="wa3-hbtn" type="button" data-wa3-menu-toggle="transfer">
                            <i class="mdi mdi-account-arrow-right-outline"></i><span>Transferir</span>
                        </button>
                        <div class="wa3-hbtn__menu" hidden>
                            <h6>Transferir conversación</h6>
                            @forelse($agents as $a)
                                <button class="wa3-menu-item" type="button"
                                        data-wa-action="transfer"
                                        data-conversation-id="{{ $selectedConversation['id'] }}"
                                        data-user-id="{{ $a['id'] }}">
                                    <i class="mdi mdi-account-outline lead"></i>
                                    <span>{{ $a['name'] }}<span class="meta">{{ $a['role_name'] ?? '—' }} · {{ $a['active_conversations'] ?? 0 }} chats</span></span>
                                    <span class="dot" data-state="{{ ($a['presence_status'] ?? '') === 'available' ? 'online' : (($a['presence_status'] ?? '') === 'busy' ? 'busy' : 'away') }}"></span>
                                </button>
                            @empty
                                <div style="padding:10px;color:var(--wa3-text-mute);font-size:12.5px;">No hay agentes disponibles.</div>
                            @endforelse
                            @if(count($roleOptions) > 0)
                                <h6>Derivar por equipo</h6>
                                @foreach($roleOptions as $role)
                                    <button class="wa3-menu-item" type="button"
                                            data-wa-action="queue-role"
                                            data-conversation-id="{{ $selectedConversation['id'] }}"
                                            data-role-id="{{ $role['id'] }}">
                                        <i class="mdi mdi-account-group-outline lead"></i>
                                        <span>{{ $role['name'] }}<span class="meta">Cola de atención</span></span>
                                    </button>
                                @endforeach
                            @endif
                            <div class="wa3-menu-footer">
                                <input type="text" id="wa3-transfer-note" placeholder="Nota (opcional)">
                            </div>
                        </div>
                    </div>

                    {{-- Plantillas --}}
                    <div class="wa3-hbtn-wrap" data-wa3-menu="templates">
                        <button class="wa3-hbtn" type="button" data-wa3-menu-toggle="templates">
                            <i class="mdi mdi-file-document-outline"></i><span>Plantillas</span>
                        </button>
                        <div class="wa3-hbtn__menu" hidden>
                            <h6>Plantillas aprobadas</h6>
                            @forelse($approvedTemplateOptions as $tpl)
                                <button class="wa3-menu-item" type="button"
                                        data-wa3-template-id="{{ $tpl['id'] ?? '' }}"
                                        data-wa3-template-start="1"
                                        data-wa-number="{{ $selectedConversation['wa_number'] ?? '' }}"
                                        data-contact-name="{{ $selectedConversation['display_name'] ?? '' }}"
                                        data-patient-name="{{ $selectedConversation['patient_full_name'] ?? '' }}"
                                        data-hc-number="{{ $selectedConversation['patient_hc_number'] ?? '' }}">
                                    <i class="mdi mdi-clipboard-text-outline lead"></i>
                                    <span>{{ $tpl['name'] ?? 'Plantilla' }}<span class="meta">{{ strtoupper($tpl['category'] ?? 'UTILITY') }} · {{ strtoupper($tpl['language'] ?? 'ES') }}</span></span>
                                </button>
                            @empty
                                <div style="padding:10px;color:var(--wa3-text-mute);font-size:12.5px;">No hay plantillas configuradas.</div>
                            @endforelse
                        </div>
                    </div>

                    <span class="wa3-iconbtn--sep"></span>

                    @if($canOperateConversation)
                        <button class="wa3-hbtn is-success" type="button"
                                data-wa-action="close" data-conversation-id="{{ $selectedConversation['id'] }}">
                            <i class="mdi mdi-check-circle-outline"></i><span>Resolver</span>
                        </button>
                    @endif

                    <button class="wa3-iconbtn is-primary" id="wa3-toggle-drawer" type="button"
                            title="Ocultar ficha" aria-label="Ocultar ficha del paciente"><i class="mdi mdi-account-details-outline"></i><span class="wa3-sr-only">Ocultar ficha del paciente</span></button>
                    <div class="wa3-hbtn-wrap" data-wa3-menu="more">
                        <button class="wa3-iconbtn" title="Más opciones" aria-label="Más opciones" type="button" data-wa3-menu-toggle="more">
                            <i class="mdi mdi-dots-vertical"></i>
                            <span class="wa3-sr-only">Más opciones</span>
                        </button>
                        <div class="wa3-hbtn__menu" hidden>
                            <h6>Más opciones</h6>
                            <button class="wa3-menu-item" type="button" data-wa3-copy="{{ $selectedConversation['wa_number'] ?? '' }}">
                                <i class="mdi mdi-content-copy lead"></i>
                                <span>Copiar WhatsApp<span class="meta">{{ $selectedConversation['wa_number'] ?? '' }}</span></span>
                            </button>
                            @if(!empty($selectedConversation['patient_hc_number']))
                                <button class="wa3-menu-item" type="button" data-wa3-copy="{{ $selectedConversation['patient_hc_number'] }}">
                                    <i class="mdi mdi-card-account-details-outline lead"></i>
                                    <span>Copiar HC<span class="meta">{{ $selectedConversation['patient_hc_number'] }}</span></span>
                                </button>
                                <a class="wa3-menu-item"
                                   href="/v2/pacientes/detalles?hc_number={{ urlencode((string) $selectedConversation['patient_hc_number']) }}"
                                   target="_blank"
                                   rel="noopener">
                                    <i class="mdi mdi-file-eye-outline lead"></i>
                                    <span>Abrir ficha<span class="meta">Detalle del paciente</span></span>
                                </a>
                            @endif
                            <button class="wa3-menu-item" type="button" id="wa3-more-trail">
                                <i class="mdi mdi-timeline-text-outline lead"></i>
                                <span>Ver trazabilidad<span class="meta">Abrir panel lateral</span></span>
                            </button>
                            @if($canOperateConversation)
                                <button class="wa3-menu-item" type="button" id="wa3-more-followup">
                                    <i class="mdi mdi-archive-arrow-down-outline lead"></i>
                                    <span>Cerrar seguimiento<span class="meta">Genera lead WhatsApp</span></span>
                                </button>
                            @endif
                            <button class="wa3-menu-item" type="button" data-wa3-copy="{{ $buildLink(['conversation' => $selectedConversation['id']]) }}">
                                <i class="mdi mdi-link-variant lead"></i>
                                <span>Copiar link<span class="meta">Abrir esta conversación</span></span>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <div class="wa3-context">
                <span class="wa3-context__item"><i class="mdi mdi-map-marker-path"></i>{{ $selectedConversation['operational_status_label'] ?? 'Sin estado' }}</span>
                <span class="sep">·</span>
                <span class="wa3-context__item"><i class="mdi mdi-speedometer"></i>Prioridad <strong>{{ $selectedConversation['priority_level_label'] ?? 'Baja' }}</strong></span>
                <span class="sep">·</span>
                <span class="wa3-context__item"><i class="mdi mdi-account-voice"></i>Último: {{ $selectedConversation['last_message_actor_label'] ?? 'Sin mensajes' }}</span>
                @if($isMine)
                    <span class="sep">·</span>
                    <span class="wa3-context__item wa3-context__item--mine"><i class="mdi mdi-account-check-outline"></i><strong>Asignada a ti</strong></span>
                @elseif(!empty($selectedConversation['assigned_user_name']))
                    <span class="sep">·</span>
                    <span class="wa3-context__item"><i class="mdi mdi-account-outline"></i>{{ $selectedConversation['assigned_user_name'] }}</span>
                @endif
                @if($selWState === 'window_open')
                    <span class="sep">·</span>
                    <span class="wa3-context__item wa3-context__item--open"><i class="mdi mdi-timer-sand"></i>Ventana 24h <strong>abierta</strong></span>
                @else
                    <span class="sep">·</span>
                    <span class="wa3-context__item"><i class="mdi mdi-file-document-edit-outline"></i>Sólo plantilla</span>
                @endif
                @if(!empty($selectedConversation['queue_bucket_label']))
                    <span class="sep">·</span>
                    <span class="wa3-context__item"><i class="mdi mdi-tag-outline"></i>{{ $selectedConversation['queue_bucket_label'] }}</span>
                @endif
                @if(!empty($selectedConversation['attribution_headline']))
                    <span class="sep">·</span>
                    <span class="wa3-context__item"><i class="mdi mdi-bullseye-arrow"></i>{{ $selectedConversation['attribution_headline'] }}</span>
                @endif
            </div>

            <div class="wa3-chat-search" id="wa3-chat-search" aria-hidden="true">
                <i class="mdi mdi-magnify" style="color:var(--wa3-text-mute);font-size:18px;"></i>
                <input type="search" id="wa3-chat-search-input" placeholder="Buscar dentro de esta conversación...">
                <span class="wa3-chat-search__count" id="wa3-chat-search-count">0/0</span>
                <button class="wa3-iconbtn" type="button" id="wa3-chat-search-prev" title="Anterior" aria-label="Resultado anterior"><i class="mdi mdi-chevron-up"></i><span class="wa3-sr-only">Resultado anterior</span></button>
                <button class="wa3-iconbtn" type="button" id="wa3-chat-search-next" title="Siguiente" aria-label="Resultado siguiente"><i class="mdi mdi-chevron-down"></i><span class="wa3-sr-only">Resultado siguiente</span></button>
                <button class="wa3-iconbtn" type="button" id="wa3-chat-search-close" title="Cerrar búsqueda" aria-label="Cerrar búsqueda"><i class="mdi mdi-close"></i><span class="wa3-sr-only">Cerrar búsqueda</span></button>
            </div>

            <div class="wa3-realtime" id="wa3-realtime-banner">
                <span>Hay mensajes nuevos en esta conversación.</span>
                <button type="button" id="wa3-realtime-reload">Actualizar</button>
            </div>

            <div class="wa3-messages" id="wa-v2-message-list">
                @php $lastMsgDate = null; @endphp
                @foreach(($selectedConversation['messages'] ?? []) as $message)
                    @php
                        $msgDateStr = '';
                        $dividerLabel = null;
                        if (!empty($message['message_timestamp'])) {
                            try {
                                $mc = \Carbon\Carbon::parse($message['message_timestamp']);
                                $msgDateStr = $mc->toDateString();
                                if ($msgDateStr !== $lastMsgDate) {
                                    $lastMsgDate = $msgDateStr;
                                    $dividerLabel = match ($msgDateStr) {
                                        \Carbon\Carbon::today()->toDateString()     => 'Hoy',
                                        \Carbon\Carbon::yesterday()->toDateString() => 'Ayer',
                                        default => $mc->format('d/m/Y'),
                                    };
                                }
                                $msgTimeShort = $mc->format('H:i');
                            } catch (\Exception $e) { $msgTimeShort = ''; }
                        } else { $msgTimeShort = ''; }
                        $msgDir = $message['direction'] ?? 'inbound';
                        $msgStatus = $message['status'] ?? '';
                    @endphp
                    @if($dividerLabel !== null)
                        <div class="wa3-date">{{ $dividerLabel }}</div>
                    @endif
                    <div class="wa3-msg is-{{ $msgDir === 'outbound' ? 'out' : 'in' }}"
                         data-message-id="{{ (int) ($message['id'] ?? 0) }}"
                         data-status="{{ $msgStatus }}">
                        <div class="wa3-bubble">
                            @if(!empty($message['media']) && is_array($message['media']))
                                @php $media = $message['media']; @endphp
                                <div class="wa3-media">
                                    @if(($message['message_type'] ?? '') === 'image' && !empty($media['download_url']))
                                        <div>
                                            <strong>{{ $media['filename'] ?: 'Imagen' }}</strong>
                                            <small>{{ $media['mime_type'] ?? 'image' }}</small>
                                            <img src="{{ $media['download_url'] }}" alt="Imagen adjunta de WhatsApp">
                                        </div>
                                    @elseif(($message['message_type'] ?? '') === 'video' && !empty($media['download_url']))
                                        <div>
                                            <strong>{{ $media['filename'] ?: 'Video' }}</strong>
                                            <small>{{ $media['mime_type'] ?? 'video' }}</small>
                                            <video controls preload="metadata"><source src="{{ $media['download_url'] }}" type="{{ $media['mime_type'] ?: 'video/mp4' }}"></video>
                                        </div>
                                    @elseif(($message['message_type'] ?? '') === 'audio' && !empty($media['download_url']))
                                        <div style="flex:1;">
                                            <strong>{{ !empty($media['voice']) ? 'Mensaje de voz' : 'Audio' }}</strong>
                                            <audio controls preload="metadata" style="width:100%;"><source src="{{ $media['download_url'] }}" type="{{ $media['mime_type'] ?: 'audio/ogg' }}"></audio>
                                        </div>
                                    @else
                                        <i class="mdi mdi-file-document-outline"></i>
                                        <div>
                                            <strong>{{ $media['filename'] ?: 'Archivo' }}</strong>
                                            <small>{{ $media['mime_type'] ?? '' }}</small>
                                        </div>
                                        @if(!empty($media['download_url']))
                                            <a href="{{ $media['download_url'] }}" target="_blank" rel="noopener" style="margin-left:auto;color:var(--wa3-accent);font-size:12px;">Abrir</a>
                                        @endif
                                    @endif
                                </div>
                            @endif
                            @if(!empty($message['body']))
                                <div>{!! $formatWaBody($message['body']) !!}</div>
                            @endif
                            <div class="wa3-bubble__meta">
                                <span>{{ $msgTimeShort }}</span>
                                @if($msgDir === 'outbound')
                                    @if($msgStatus === 'read')      <i class="mdi mdi-check-all read"></i>
                                    @elseif($msgStatus === 'delivered') <i class="mdi mdi-check-all"></i>
                                    @elseif($msgStatus === 'sent')      <i class="mdi mdi-check"></i>
                                    @elseif($msgStatus === 'failed')    <i class="mdi mdi-alert-circle-outline" style="color:var(--wa3-danger);"></i>
                                    @elseif($msgStatus === 'pending')   <i class="mdi mdi-clock-outline"></i>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($canOperateConversation)
                <div class="wa3-composer">
                    @if(count($quickReplies) > 0)
                        <div class="wa3-composer__quickreplies">
                            @foreach($quickReplies as $qr)
                                <button type="button" class="wa3-quickreply"
                                        data-wa3-quick-body="{{ $qr['body'] ?? '' }}">
                                    <i class="mdi mdi-lightning-bolt-outline"></i>{{ $qr['title'] ?? $qr['body'] ?? 'Respuesta' }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <form id="wa-v2-send-form"
                          data-conversation-id="{{ $selectedConversation['id'] }}"
                          onsubmit="return false;">
                        <div class="wa3-composer__row">
                            <button class="wa3-iconbtn" type="button" title="Adjuntar" aria-label="Adjuntar archivo"
                                    onclick="document.getElementById('wa-v2-media-file').click()">
                                <i class="mdi mdi-paperclip"></i>
                                <span class="wa3-sr-only">Adjuntar archivo</span>
                            </button>
                            <textarea id="wa-v2-message-input" rows="1"
                                      placeholder="Escribe un mensaje…" {{ $canReplyHere ? '' : 'disabled' }}></textarea>
                            <input type="hidden" id="wa-v2-message-type" value="text">
                            <input type="hidden" id="wa-v2-media-url" value="">
                            <input type="hidden" id="wa-v2-media-filename" value="">
                            <input type="hidden" id="wa-v2-media-disk" value="">
                            <input type="hidden" id="wa-v2-media-path" value="">
                            <input type="hidden" id="wa-v2-media-mime-type" value="">
                            <input type="file" id="wa-v2-media-file" style="display:none;" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                            <div class="wa3-composer__tools">
                                <button class="wa3-iconbtn" type="button" title="Grabar audio" aria-label="Grabar audio" id="wa3-voice-record"><i class="mdi mdi-microphone-outline"></i><span class="wa3-sr-only">Grabar audio</span></button>
                                <div class="wa3-emoji-wrap">
                                    <button class="wa3-iconbtn" type="button" title="Emoji" aria-label="Abrir emojis" id="wa3-emoji-toggle"><i class="mdi mdi-emoticon-outline"></i><span class="wa3-sr-only">Abrir emojis</span></button>
                                    <div class="wa3-emoji-popover" id="wa3-emoji-popover" hidden>
                                        <h6>Emojis rápidos</h6>
                                        <div class="wa3-emoji-grid">
                                            @foreach(['👁️','👀','🙂','😊','🙏','✅','📅','🕒','📍','🏥','👨‍⚕️','👩‍⚕️','🤓','💬','📄','🔎','⚠️','😔','👍','✨','🟢','🔴','🟡','📞'] as $emoji)
                                                <button class="wa3-emoji" type="button" data-wa3-emoji="{{ $emoji }}">{{ $emoji }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <button class="wa3-iconbtn" type="button" title="Nota interna" aria-label="Ir a nota interna"
                                        onclick="document.getElementById('wa3-note-body')?.focus()"><i class="mdi mdi-note-edit-outline"></i><span class="wa3-sr-only">Ir a nota interna</span></button>
                            </div>
                            <button class="wa3-send wa-v2-composer-send" type="button" id="wa3-send-btn"
                                    title="Enviar (Enter)" aria-label="Enviar mensaje" {{ $canReplyHere ? '' : 'disabled' }}>
                                <i class="mdi mdi-send"></i>
                                <span class="wa3-sr-only">Enviar mensaje</span>
                            </button>
                        </div>
                        <div class="wa3-upload" id="wa3-upload-status">
                            <span id="wa3-upload-text">Adjunto listo</span>
                            <button type="button" id="wa3-upload-clear" title="Quitar adjunto" aria-label="Quitar adjunto"><i class="mdi mdi-close"></i><span class="wa3-sr-only">Quitar adjunto</span></button>
                        </div>
                        <div class="wa3-composer__hint">
                            <span>
                                @if($selWState === 'window_open')
                                    Ventana de 24h abierta — puedes responder libremente.
                                @else
                                    Ventana cerrada — usa una plantilla aprobada para iniciar.
                                @endif
                            </span>
                            <span><kbd>Enter</kbd> enviar · <kbd>Shift+Enter</kbd> nueva línea</span>
                        </div>
                    </form>
                </div>
            @endif
        @else
            <div class="wa3-empty">
                <div class="wa3-empty__card">
                    <div class="wa3-empty__icon"><i class="mdi mdi-message-text-outline"></i></div>
                    <h3>Selecciona una conversación</h3>
                    <p>Elige un chat del panel izquierdo para comenzar a atender al paciente.</p>
                </div>
            </div>
        @endif
    </section>

    {{-- ================= DRAWER ================= --}}
    @if($selectedConversation)
        <aside class="wa3-drawer" id="wa3-drawer">
            @php
                $selName = $selectedConversation['display_name'] ?: $selectedConversation['wa_number'];
                $selInit = mb_strtoupper(mb_substr($selName, 0, 1) . mb_substr(strpos($selName, ' ') !== false ? substr($selName, strpos($selName, ' ') + 1) : '', 0, 1));
                $selTone = $avatarTone($selName);
                $agendaQuery = http_build_query(array_filter([
                    'hc_number' => $selectedConversation['patient_hc_number'] ?? null,
                    'wa_number' => $selectedConversation['wa_number'] ?? null,
                    'conversation_id' => $selectedConversation['id'] ?? null,
                ], static fn ($value) => $value !== null && $value !== ''));
                $agendaHref = '/v2/agenda' . ($agendaQuery !== '' ? '?' . $agendaQuery : '');
                $canOpenAgenda = !empty($selectedConversation['patient_hc_number']) || !empty($selectedConversation['wa_number']);
            @endphp
            <div class="wa3-drawer__profile">
                <div class="wa3-avatar" data-tone="{{ $selTone }}">{{ $selInit }}</div>
                <h3>{{ $selectedConversation['patient_full_name'] ?: $selName }}</h3>
                <p>
                    @if(!empty($selectedConversation['patient_hc_number']))
                        HC {{ $selectedConversation['patient_hc_number'] }}
                    @else
                        Sin paciente vinculado
                    @endif
                </p>
                <div class="wa3-drawer__quickactions">
                    @if($canOpenAgenda)
                        <a class="wa3-quickaction"
                           href="{{ $agendaHref }}"
                           target="_blank"
                           rel="noopener">
                            <i class="mdi mdi-calendar-plus-outline"></i>Agendar
                        </a>
                    @else
                        <button class="wa3-quickaction" type="button" aria-disabled="true" title="No hay datos suficientes para abrir agenda">
                            <i class="mdi mdi-calendar-plus-outline"></i>Agendar
                        </button>
                    @endif
                    @if(!empty($selectedConversation['patient_hc_number']))
                        <a class="wa3-quickaction"
                           href="/v2/pacientes/detalles?hc_number={{ urlencode((string) $selectedConversation['patient_hc_number']) }}"
                           target="_blank"
                           rel="noopener">
                            <i class="mdi mdi-file-eye-outline"></i>Ficha
                        </a>
                    @else
                        <button class="wa3-quickaction" type="button" aria-disabled="true" title="No hay HC vinculado">
                            <i class="mdi mdi-file-eye-outline"></i>Ficha
                        </button>
                    @endif
                </div>
            </div>

            <div class="wa3-drawer__section">
                <h6>Paciente</h6>
                <div class="wa3-kv">
                    <div class="wa3-kv__row">
                        <span class="k"><i class="mdi mdi-phone-outline"></i>Teléfono</span>
                        <span class="v">{{ $selectedConversation['wa_number'] }}</span>
                    </div>
                    @if(!empty($selectedConversation['assigned_role_name']))
                        <div class="wa3-kv__row">
                            <span class="k"><i class="mdi mdi-account-group-outline"></i>Equipo</span>
                            <span class="v">{{ $selectedConversation['assigned_role_name'] }}</span>
                        </div>
                    @endif
                    @if(!empty($selectedConversation['ownership_label']))
                        <div class="wa3-kv__row">
                            <span class="k"><i class="mdi mdi-tag-outline"></i>Responsable</span>
                            <span class="v">{{ $selectedConversation['ownership_label'] }}</span>
                        </div>
                    @endif
                    @if(!empty($selectedConversation['operational_status_label']))
                        <div class="wa3-kv__row">
                            <span class="k"><i class="mdi mdi-map-marker-path"></i>Estado operativo</span>
                            <span class="v">{{ $selectedConversation['operational_status_label'] }}</span>
                        </div>
                    @endif
                    @if(!empty($selectedConversation['priority_level_label']))
                        <div class="wa3-kv__row">
                            <span class="k"><i class="mdi mdi-speedometer"></i>Prioridad</span>
                            <span class="v">{{ $selectedConversation['priority_level_label'] }}</span>
                        </div>
                    @endif
                    @if(!empty($selectedConversation['messaging_window_label']))
                        <div class="wa3-kv__row">
                            <span class="k"><i class="mdi mdi-timer-sand"></i>Ventana</span>
                            <span class="v">{{ $selectedConversation['messaging_window_label'] }}</span>
                        </div>
                    @endif
                </div>
            </div>

            @if(!empty($selectedConversation['attribution_headline']))
                <div class="wa3-drawer__section">
                    <h6>Atribución</h6>
                    <div class="wa3-tags">
                        <span class="wa3-tag">{{ $selectedConversation['attribution_source_category'] ?? 'Origen' }}</span>
                        <span class="wa3-tag">{{ $selectedConversation['attribution_headline'] }}</span>
                    </div>
                </div>
            @endif

            <div class="wa3-drawer__section">
                <h6>Trazabilidad</h6>
                <div id="wa3-trail-list" class="wa3-trail-scroll" style="font:400 12px var(--bs-body-font-family);color:var(--wa3-text-mute);">
                    Cargando trazabilidad...
                </div>
            </div>

            <div class="wa3-drawer__section">
                <h6>Notas internas</h6>
                <div class="wa3-kv" id="wa3-notes-list">
                    @forelse($conversationNotes as $note)
                        <div style="font:400 12.5px var(--bs-body-font-family);color:var(--wa3-text);padding:6px 0;border-bottom:1px solid var(--wa3-border-soft);">
                            <div style="font-weight:600;color:var(--wa3-accent);font-size:11px;">
                                {{ $note['author_name'] ?? 'Equipo' }} · {{ !empty($note['created_at']) ? \Carbon\Carbon::parse($note['created_at'])->diffForHumans() : '' }}
                            </div>
                            {{ $note['body'] ?? '' }}
                        </div>
                    @empty
                        <div style="font:400 12px var(--bs-body-font-family);color:var(--wa3-text-mute);">Sin notas internas.</div>
                    @endforelse
                </div>
                <div class="wa3-field" style="margin-top:10px;">
                    <textarea id="wa3-note-body" placeholder="Agregar nota interna..."></textarea>
                </div>
                <div class="wa3-action-row">
                    <span class="wa3-feedback" id="wa3-note-feedback"></span>
                    <button class="wa3-primary-btn" type="button" id="wa3-note-submit"
                            data-conversation-id="{{ $selectedConversation['id'] }}">Guardar nota</button>
                </div>
            </div>

            <div class="wa3-drawer__section">
                <h6>Productividad</h6>
                <div class="wa3-field">
                    <input type="text" id="wa3-qr-title" placeholder="Título de respuesta rápida">
                </div>
                <div class="wa3-field">
                    <textarea id="wa3-qr-body" placeholder="Texto de respuesta rápida"></textarea>
                </div>
                <div class="wa3-action-row">
                    <span class="wa3-feedback" id="wa3-qr-feedback"></span>
                    <button class="wa3-secondary-btn" type="button" id="wa3-qr-submit">Crear respuesta rápida</button>
                </div>
            </div>

            @if($canOperateConversation)
                <div class="wa3-drawer__section">
                    <h6>Acciones administrativas</h6>
                    <button class="wa3-admin-btn" type="button" id="wa3-followup-open"
                            data-conversation-id="{{ $selectedConversation['id'] }}">
                        <i class="mdi mdi-archive-arrow-down-outline"></i>
                        Cerrar seguimiento
                    </button>
                </div>
            @endif
        </aside>
    @endif
</div>

<div class="wa3-modal" id="wa3-new-modal" hidden>
    <div class="wa3-modal__card">
        <div class="wa3-modal__head">
            <div>
                <h3>Nueva conversación con plantilla</h3>
                <div style="font:400 12px var(--bs-body-font-family);color:#7e8299;margin-top:3px;">Usa una plantilla aprobada para iniciar o continuar fuera de ventana.</div>
            </div>
            <button class="wa3-iconbtn" type="button" data-wa3-modal-close="wa3-new-modal" aria-label="Cerrar nueva conversación"><i class="mdi mdi-close"></i><span class="wa3-sr-only">Cerrar nueva conversación</span></button>
        </div>
        <div class="wa3-modal__body">
            <div class="wa3-modal__grid">
                <div>
                    <div class="wa3-field">
                        <label for="wa3-start-search">Buscar paciente o número</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="wa3-start-search" placeholder="Celular, HC, nombres o apellidos">
                            <button class="wa3-secondary-btn" type="button" id="wa3-start-search-button">Buscar</button>
                        </div>
                    </div>
                    <div class="wa3-picker-results" id="wa3-start-results"></div>
                    <div class="wa3-field" style="margin-top:12px;">
                        <label>Preview del mensaje</label>
                        <div class="wa3-template-preview" id="wa3-start-preview">Selecciona una plantilla para revisar el mensaje final.</div>
                    </div>
                </div>
                <div>
                    <div class="wa3-field">
                        <label for="wa3-start-number">Número WhatsApp</label>
                        <input type="text" id="wa3-start-number" placeholder="593999111222">
                    </div>
                    <div class="wa3-field">
                        <label for="wa3-start-contact-name">Nombre visible</label>
                        <input type="text" id="wa3-start-contact-name" placeholder="Nombre del contacto">
                    </div>
                    <div class="wa3-field">
                        <label for="wa3-start-patient-name">Paciente</label>
                        <input type="text" id="wa3-start-patient-name" placeholder="Nombres y apellidos">
                    </div>
                    <div class="wa3-field">
                        <label for="wa3-start-hc">HC</label>
                        <input type="text" id="wa3-start-hc" placeholder="Historia clínica">
                    </div>
                    <div class="wa3-field">
                        <label for="wa3-start-template">Plantilla aprobada</label>
                        <select id="wa3-start-template">
                            <option value="">Selecciona una plantilla</option>
                            @foreach($approvedTemplateOptions as $template)
                                <option value="{{ $template['id'] }}">{{ $template['name'] ?? 'Plantilla' }} · {{ $template['language'] ?: 'n/a' }} · {{ $template['status'] ?: 'n/a' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="wa3-start-template-vars"></div>
                </div>
            </div>
            <div class="wa3-feedback" id="wa3-start-feedback" style="margin-top:12px;">Selecciona un contacto o escribe el número manualmente para iniciar con plantilla.</div>
        </div>
        <div class="wa3-modal__foot">
            <div style="font:400 12px var(--bs-body-font-family);color:#7e8299;">Esto crea o reutiliza la conversación y la deja abierta en tu inbox.</div>
            <button class="wa3-primary-btn" type="button" id="wa3-start-submit">Iniciar con plantilla</button>
        </div>
    </div>
</div>

<div class="wa3-modal" id="wa3-followup-modal" hidden>
    <div class="wa3-modal__card" style="width:min(520px,100%);">
        <div class="wa3-modal__head">
            <div>
                <h3>Cerrar seguimiento</h3>
                <div style="font:400 12px var(--bs-body-font-family);color:#7e8299;margin-top:3px;">Esto no elimina al paciente ni el historial. Cerrará la conversación activa y generará un lead de seguimiento.</div>
            </div>
            <button class="wa3-iconbtn" type="button" data-wa3-modal-close="wa3-followup-modal" aria-label="Cerrar seguimiento"><i class="mdi mdi-close"></i><span class="wa3-sr-only">Cerrar seguimiento</span></button>
        </div>
        <div class="wa3-modal__body">
            <div class="wa3-field">
                <label for="wa3-followup-reason">Motivo del cierre</label>
                <textarea id="wa3-followup-reason" placeholder="Ej.: paciente no responde, retomar seguimiento comercial, pidió contacto posterior..."></textarea>
            </div>
            <div class="wa3-feedback" id="wa3-followup-feedback"></div>
        </div>
        <div class="wa3-modal__foot">
            <button class="wa3-secondary-btn" type="button" data-wa3-modal-close="wa3-followup-modal">Cancelar</button>
            <button class="wa3-primary-btn" type="button" id="wa3-followup-submit">Cerrar seguimiento</button>
        </div>
    </div>
</div>

<div class="wa3-modal wa3-tour-modal" id="wa3-tour-modal" hidden role="dialog" aria-modal="true" aria-labelledby="wa3-tour-title">
    <div class="wa3-modal__card">
        <div class="wa3-modal__head">
            <div>
                <h3 id="wa3-tour-title">Nuevo Chat de WhatsApp</h3>
                <div style="font:400 12px var(--bs-body-font-family);color:#7e8299;margin-top:3px;">Una vista más clara para atender conversaciones.</div>
            </div>
            <button class="wa3-iconbtn" type="button" id="wa3-tour-close" aria-label="Cerrar tour">
                <i class="mdi mdi-close"></i>
                <span class="wa3-sr-only">Cerrar tour</span>
            </button>
        </div>
        <div class="wa3-modal__body">
            <p>Ahora las conversaciones, los mensajes y la ficha del paciente están separados para que encuentres cada cosa más rápido.</p>
            <p>El chat también muestra acciones principales en la parte superior y accesos rápidos junto al campo de escritura.</p>
            <div class="wa3-tour-step">
                <span id="wa3-tour-counter">Paso 1 de 5</span>
                <span class="wa3-tour-step__bar"><span id="wa3-tour-progress"></span></span>
            </div>
            <h4 id="wa3-tour-step-title" style="font:700 16px var(--font-display, 'Rubik', system-ui, sans-serif);color:#172b4c;margin:14px 0 6px;">Conversaciones</h4>
            <p id="wa3-tour-step-copy">Aquí eliges qué chat atender.</p>
        </div>
        <div class="wa3-modal__foot">
            <button class="wa3-secondary-btn" type="button" id="wa3-tour-prev">Anterior</button>
            <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                <button class="wa3-secondary-btn" type="button" id="wa3-tour-next">Siguiente</button>
                <button class="wa3-primary-btn" type="button" id="wa3-tour-done">Entendido, explorar</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Header menus (Transferir / Plantillas / Más opciones) ────────────────
    const wraps = document.querySelectorAll('[data-wa3-menu]');
    const closeWa3Menus = () => {
        wraps.forEach((w) => {
            w.querySelector('.wa3-hbtn__menu')?.setAttribute('hidden', '');
            w.querySelector('[data-wa3-menu-toggle]')?.classList.remove('is-open');
        });
    };
    wraps.forEach((w) => {
        const btn = w.querySelector('[data-wa3-menu-toggle]');
        const menu = w.querySelector('.wa3-hbtn__menu');
        if (!btn || !menu) return;
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = !menu.hasAttribute('hidden');
            closeWa3Menus();
            if (!isOpen) { menu.removeAttribute('hidden'); btn.classList.add('is-open'); }
        });
    });
    document.addEventListener('mousedown', (e) => {
        if (!e.target.closest('.wa3-hbtn-wrap')) {
            closeWa3Menus();
        }
    });

    // ── Drawer toggle ───────────────────────────────────────────────────────
    const root = document.getElementById('wa3-root');
    const drawerBtn = document.getElementById('wa3-toggle-drawer');
    if (drawerBtn && root) {
        drawerBtn.addEventListener('click', () => {
            root.classList.toggle('has-drawer');
            const isOpen = root.classList.contains('has-drawer');
            drawerBtn.classList.toggle('is-primary', isOpen);
            drawerBtn.title = isOpen ? 'Ocultar ficha' : 'Ver ficha del paciente';
            drawerBtn.setAttribute('aria-label', isOpen ? 'Ocultar ficha del paciente' : 'Ver ficha del paciente');
        });
    }

    // ── Search inside current conversation ─────────────────────────────────
    const chatSearch = document.getElementById('wa3-chat-search');
    const chatSearchToggle = document.getElementById('wa3-chat-search-toggle');
    const chatSearchInput = document.getElementById('wa3-chat-search-input');
    const chatSearchCount = document.getElementById('wa3-chat-search-count');
    let chatSearchMatches = [];
    let chatSearchIndex = -1;

    function clearChatSearchMarks() {
        document.querySelectorAll('.wa3-msg.is-search-match, .wa3-msg.is-search-current').forEach((node) => {
            node.classList.remove('is-search-match', 'is-search-current');
        });
    }

    function updateChatSearchCount() {
        if (!chatSearchCount) return;
        chatSearchCount.textContent = chatSearchMatches.length > 0
            ? `${chatSearchIndex + 1}/${chatSearchMatches.length}`
            : '0/0';
    }

    function focusChatSearchMatch(index) {
        if (chatSearchMatches.length === 0) {
            chatSearchIndex = -1;
            updateChatSearchCount();
            return;
        }

        chatSearchMatches.forEach((node) => node.classList.remove('is-search-current'));
        chatSearchIndex = (index + chatSearchMatches.length) % chatSearchMatches.length;
        const current = chatSearchMatches[chatSearchIndex];
        current.classList.add('is-search-current');
        current.scrollIntoView({ behavior: 'smooth', block: 'center' });
        updateChatSearchCount();
    }

    function runChatSearch() {
        const query = (chatSearchInput?.value || '').trim().toLowerCase();
        clearChatSearchMarks();
        chatSearchMatches = [];
        chatSearchIndex = -1;

        if (query === '') {
            updateChatSearchCount();
            return;
        }

        document.querySelectorAll('#wa-v2-message-list .wa3-msg').forEach((message) => {
            const text = (message.textContent || '').toLowerCase();
            if (!text.includes(query)) return;
            message.classList.add('is-search-match');
            chatSearchMatches.push(message);
        });

        focusChatSearchMatch(0);
    }

    function openChatSearch() {
        if (!chatSearch || !chatSearchInput) return;
        chatSearch.classList.add('is-open');
        chatSearch.setAttribute('aria-hidden', 'false');
        chatSearchInput.focus();
        chatSearchInput.select();
        runChatSearch();
    }

    function closeChatSearch() {
        if (!chatSearch || !chatSearchInput) return;
        chatSearch.classList.remove('is-open');
        chatSearch.setAttribute('aria-hidden', 'true');
        chatSearchInput.value = '';
        clearChatSearchMarks();
        chatSearchMatches = [];
        chatSearchIndex = -1;
        updateChatSearchCount();
    }

    chatSearchToggle?.addEventListener('click', () => {
        if (chatSearch?.classList.contains('is-open')) {
            closeChatSearch();
            return;
        }
        openChatSearch();
    });
    chatSearchInput?.addEventListener('input', runChatSearch);
    chatSearchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeChatSearch();
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            focusChatSearchMatch(chatSearchIndex + (event.shiftKey ? -1 : 1));
        }
    });
    document.getElementById('wa3-chat-search-prev')?.addEventListener('click', () => focusChatSearchMatch(chatSearchIndex - 1));
    document.getElementById('wa3-chat-search-next')?.addEventListener('click', () => focusChatSearchMatch(chatSearchIndex + 1));
    document.getElementById('wa3-chat-search-close')?.addEventListener('click', closeChatSearch);

    // ── Composer ────────────────────────────────────────────────────────────
    const ta = document.getElementById('wa-v2-message-input');
    const sendBtn = document.getElementById('wa3-send-btn');
    const form = document.getElementById('wa-v2-send-form');

    const autosize = () => {
        if (!ta) return;
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight, 140) + 'px';
    };
    ta?.addEventListener('input', autosize);

    const emojiToggle = document.getElementById('wa3-emoji-toggle');
    const emojiPopover = document.getElementById('wa3-emoji-popover');
    const insertAtCursor = (textarea, value) => {
        if (!textarea || textarea.disabled) return;
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        textarea.value = textarea.value.slice(0, start) + value + textarea.value.slice(end);
        const next = start + value.length;
        textarea.focus();
        textarea.setSelectionRange(next, next);
        autosize();
    };
    emojiToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        if (!emojiPopover) return;
        emojiPopover.toggleAttribute('hidden');
    });
    document.querySelectorAll('[data-wa3-emoji]').forEach((button) => {
        button.addEventListener('click', () => {
            insertAtCursor(ta, button.dataset.wa3Emoji || '');
            emojiPopover?.setAttribute('hidden', '');
        });
    });
    document.addEventListener('mousedown', (event) => {
        if (!event.target.closest('.wa3-emoji-wrap')) {
            emojiPopover?.setAttribute('hidden', '');
        }
    });

    // Quick replies populate the composer; templates open the approved-template modal.
    document.querySelectorAll('[data-wa3-quick-body]').forEach((b) => {
        b.addEventListener('click', () => { if (ta) { ta.value = b.dataset.wa3QuickBody; autosize(); ta.focus(); } });
    });
    document.querySelectorAll('[data-wa3-template-start]').forEach((b) => {
        b.addEventListener('click', () => {
            openStartTemplateModal({
                templateId: b.dataset.wa3TemplateId || '',
                waNumber: b.dataset.waNumber || '',
                contactName: b.dataset.contactName || '',
                patientName: b.dataset.patientName || '',
                hcNumber: b.dataset.hcNumber || '',
            });
        });
    });

    // Send via existing WhatsApp message endpoint.
    const csrfTokenEl = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenEl ? csrfTokenEl.getAttribute('content') : '';
    const apiBase = @json($apiBase);
    const previewRoute = @json($previewRoute);
    const selectedFilter = @json($selectedFilter);
    const selectedSearch = @json($search);
    const selectedDateFrom = @json($dateFrom);
    const selectedDateTo = @json($dateTo);
    const templates = {!! $templateOptionsJson ?: '[]' !!};
    let requestInFlight = false;

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const copyText = async (value, button = null) => {
        const text = String(value || '');
        if (!text) return;
        const finalText = text.startsWith('/') ? `${window.location.origin}${text}` : text;
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(finalText);
        } else {
            const scratch = document.createElement('textarea');
            scratch.value = finalText;
            scratch.setAttribute('readonly', '');
            scratch.style.position = 'fixed';
            scratch.style.opacity = '0';
            document.body.appendChild(scratch);
            scratch.select();
            document.execCommand('copy');
            scratch.remove();
        }
        if (button) {
            const original = button.querySelector('.meta')?.textContent || '';
            const meta = button.querySelector('.meta');
            if (meta) {
                meta.textContent = 'Copiado';
                window.setTimeout(() => { meta.textContent = original; }, 1400);
            }
        }
    };
    document.querySelectorAll('[data-wa3-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await copyText(button.dataset.wa3Copy || '', button);
                closeWa3Menus();
            } catch (err) {
                alert('No se pudo copiar al portapapeles.');
            }
        });
    });
    const inferTypeFromFile = (file) => {
        const mime = file?.type || '';
        if (mime.startsWith('image/')) return 'image';
        if (mime.startsWith('video/')) return 'video';
        if (mime.startsWith('audio/')) return 'audio';
        return 'document';
    };
    const uploadStatus = document.getElementById('wa3-upload-status');
    const uploadText = document.getElementById('wa3-upload-text');
    const setUploadStatus = (message, visible = true) => {
        if (!uploadStatus || !uploadText) return;
        uploadText.textContent = message;
        uploadStatus.classList.toggle('is-visible', visible);
    };
    function resetMediaState() {
        ['wa-v2-media-url', 'wa-v2-media-filename', 'wa-v2-media-disk', 'wa-v2-media-path', 'wa-v2-media-mime-type'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const type = document.getElementById('wa-v2-message-type');
        const file = document.getElementById('wa-v2-media-file');
        if (type) type.value = 'text';
        if (file) file.value = '';
        setUploadStatus('', false);
    }
    async function uploadFile(file) {
        if (!file) return;
        const type = inferTypeFromFile(file);
        const typeEl = document.getElementById('wa-v2-message-type');
        if (typeEl) typeEl.value = type;
        setUploadStatus(`Cargando ${file.name}...`);
        const fd = new FormData();
        fd.append('file', file);
        const res = await fetch(`${apiBase}/media/upload`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.ok || !json.data) {
            resetMediaState();
            throw new Error(json.error || 'No fue posible cargar el archivo.');
        }
        const data = json.data;
        document.getElementById('wa-v2-media-url').value = data.url || '';
        document.getElementById('wa-v2-media-filename').value = data.filename || file.name || '';
        document.getElementById('wa-v2-media-disk').value = data.disk || '';
        document.getElementById('wa-v2-media-path').value = data.path || '';
        document.getElementById('wa-v2-media-mime-type').value = data.mime_type || file.type || '';
        if (typeEl) typeEl.value = data.type || type;
        setUploadStatus(`Adjunto listo: ${data.filename || file.name}`);
    }

    async function sendMessage() {
        if (!form || !ta) return;
        const text = (ta.value || '').trim();
        const messageType = document.getElementById('wa-v2-message-type')?.value || 'text';
        const mediaUrl = document.getElementById('wa-v2-media-url')?.value || '';
        if ((messageType === 'text' && !text) || (messageType !== 'text' && !mediaUrl) || sendBtn?.disabled) return;
        const conversationId = form.dataset.conversationId;
        sendBtn.disabled = true;
        requestInFlight = true;

        try {
            const payload = {
                message_type: messageType,
                message: text,
                media_url: mediaUrl,
                filename: document.getElementById('wa-v2-media-filename')?.value || '',
                media_disk: document.getElementById('wa-v2-media-disk')?.value || '',
                media_path: document.getElementById('wa-v2-media-path')?.value || '',
                mime_type: document.getElementById('wa-v2-media-mime-type')?.value || '',
            };
            const res = await fetch(`${apiBase}/conversations/${conversationId}/messages`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
            if (!res.ok) throw new Error('Send failed (' + res.status + ')');
            if (messageType !== 'text') {
                window.location.reload();
                return;
            }

            // Optimistic UI: append the bubble locally
            const list = document.getElementById('wa-v2-message-list');
            if (list) {
                const wrap = document.createElement('div');
                wrap.className = 'wa3-msg is-out';
                wrap.dataset.status = 'sent';
                wrap.innerHTML = `<div class="wa3-bubble"><div>${text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))}</div><div class="wa3-bubble__meta"><span>${new Date().toTimeString().slice(0,5)}</span><i class="mdi mdi-check"></i></div></div>`;
                list.appendChild(wrap);
                list.scrollTop = list.scrollHeight;
            }
            ta.value = '';
            resetMediaState();
            autosize();
        } catch (err) {
            alert('No se pudo enviar el mensaje. Inténtalo nuevamente.');
        } finally {
            sendBtn.disabled = false;
            requestInFlight = false;
            ta.focus();
        }
    }

    sendBtn?.addEventListener('click', sendMessage);
    ta?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    // ── Transfer / Close actions wired to existing endpoints ────────────────
    document.querySelectorAll('[data-wa-action="transfer"]').forEach((b) => {
        b.addEventListener('click', async () => {
            const note = document.getElementById('wa3-transfer-note')?.value || '';
            const conversationId = b.dataset.conversationId;
            const userId = b.dataset.userId;
            if (!confirm(`¿Transferir esta conversación?`)) return;
            try {
                const res = await fetch(`${apiBase}/conversations/${conversationId}/transfer`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ user_id: Number(userId), note }),
                });
                if (!res.ok) throw new Error('Transfer failed');
                window.location.reload();
            } catch (err) { alert('No se pudo transferir la conversación.'); }
        });
    });

    document.querySelectorAll('[data-wa-action="close"]').forEach((b) => {
        b.addEventListener('click', async () => {
            const conversationId = b.dataset.conversationId;
            if (!confirm('¿Marcar como resuelta?')) return;
            try {
                const res = await fetch(`${apiBase}/conversations/${conversationId}/close`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({}),
                });
                if (!res.ok) throw new Error('Close failed');
                window.location.href = `${previewRoute}?filter=closed`;
            } catch (err) { alert('No se pudo resolver la conversación.'); }
        });
    });

    document.getElementById('wa-v2-media-file')?.addEventListener('change', async (event) => {
        const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
        if (!file) return;
        try {
            await uploadFile(file);
        } catch (err) {
            alert(err.message || 'No se pudo cargar el archivo.');
        }
    });
    document.getElementById('wa3-upload-clear')?.addEventListener('click', resetMediaState);

    let voiceRecorder = null;
    let voiceStream = null;
    let voiceChunks = [];
    const stopVoiceStream = () => {
        if (voiceStream) voiceStream.getTracks().forEach((track) => track.stop());
        voiceStream = null;
    };
    const bestVoiceMime = () => {
        const options = ['audio/ogg;codecs=opus', 'audio/webm;codecs=opus', 'audio/mp4'];
        return options.find((type) => typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(type)) || '';
    };
    document.getElementById('wa3-voice-record')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        if (voiceRecorder && voiceRecorder.state === 'recording') {
            voiceRecorder.stop();
            return;
        }
        if (typeof MediaRecorder === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
            alert('Este navegador no permite grabar audio aquí.');
            return;
        }
        try {
            const mime = bestVoiceMime();
            voiceStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            voiceChunks = [];
            voiceRecorder = mime ? new MediaRecorder(voiceStream, { mimeType: mime }) : new MediaRecorder(voiceStream);
            voiceRecorder.addEventListener('dataavailable', (e) => {
                if (e.data && e.data.size > 0) voiceChunks.push(e.data);
            });
            voiceRecorder.addEventListener('stop', async () => {
                const resolvedMime = voiceRecorder?.mimeType || mime || 'audio/webm';
                const extension = resolvedMime.includes('ogg') ? 'ogg' : (resolvedMime.includes('mp4') ? 'm4a' : 'webm');
                const file = new File([new Blob(voiceChunks, { type: resolvedMime })], `voice-note-${Date.now()}.${extension}`, { type: resolvedMime });
                voiceRecorder = null;
                voiceChunks = [];
                stopVoiceStream();
                button.classList.remove('is-primary');
                try {
                    await uploadFile(file);
                    setUploadStatus('Audio listo para enviar.');
                } catch (err) {
                    alert(err.message || 'No se pudo cargar el audio.');
                }
            });
            button.classList.add('is-primary');
            setUploadStatus('Grabando audio... pulsa el micrófono otra vez para detener.');
            voiceRecorder.start();
        } catch (err) {
            stopVoiceStream();
            alert('No se pudo iniciar la grabación.');
        }
    });

    async function postJson(url, payload = {}) {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || json.ok === false) throw new Error(json.error || 'No fue posible completar la acción.');
        return json;
    }

    document.querySelectorAll('[data-wa-action="assign-self"]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await postJson(`${apiBase}/conversations/${button.dataset.conversationId}/assign`, {});
                window.location.reload();
            } catch (err) {
                alert(err.message || 'No se pudo tomar la conversación.');
            }
        });
    });

    document.querySelectorAll('[data-wa-action="queue-role"]').forEach((button) => {
        button.addEventListener('click', async () => {
            const note = document.getElementById('wa3-transfer-note')?.value || '';
            try {
                await postJson(`${apiBase}/conversations/${button.dataset.conversationId}/queue-by-role`, {
                    role_id: Number(button.dataset.roleId || 0),
                    note,
                });
                window.location.reload();
            } catch (err) {
                alert(err.message || 'No se pudo derivar la conversación.');
            }
        });
    });

    document.getElementById('wa3-requeue-expired')?.addEventListener('click', async () => {
        if (!confirm('¿Reencolar handoffs vencidos?')) return;
        try {
            await postJson(`${apiBase}/handoffs/requeue-expired`, {});
            window.location.reload();
        } catch (err) {
            alert(err.message || 'No se pudo reencolar vencidos.');
        }
    });

    document.getElementById('wa3-note-submit')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const bodyEl = document.getElementById('wa3-note-body');
        const feedback = document.getElementById('wa3-note-feedback');
        const body = (bodyEl?.value || '').trim();
        if (!body) {
            if (feedback) feedback.textContent = 'Escribe una nota.';
            return;
        }
        button.disabled = true;
        try {
            await postJson(`${apiBase}/conversations/${button.dataset.conversationId}/notes`, { body });
            window.location.reload();
        } catch (err) {
            if (feedback) feedback.textContent = err.message || 'No se pudo guardar.';
        } finally {
            button.disabled = false;
        }
    });

    document.getElementById('wa3-qr-submit')?.addEventListener('click', async () => {
        const title = (document.getElementById('wa3-qr-title')?.value || '').trim();
        const body = (document.getElementById('wa3-qr-body')?.value || '').trim();
        const feedback = document.getElementById('wa3-qr-feedback');
        if (!title || !body) {
            if (feedback) feedback.textContent = 'Título y texto son obligatorios.';
            return;
        }
        try {
            await postJson(`${apiBase}/quick-replies`, { title, body });
            if (feedback) {
                feedback.dataset.tone = 'success';
                feedback.textContent = 'Respuesta rápida creada.';
            }
        } catch (err) {
            if (feedback) {
                feedback.dataset.tone = 'danger';
                feedback.textContent = err.message || 'No se pudo crear.';
            }
        }
    });

    document.querySelectorAll('[data-wa3-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById(button.dataset.wa3ModalClose)?.setAttribute('hidden', '');
        });
    });
    document.querySelectorAll('.wa3-modal').forEach((modal) => {
        modal.addEventListener('mousedown', (event) => {
            if (event.target === modal) modal.setAttribute('hidden', '');
        });
    });

    const followupOpen = document.getElementById('wa3-followup-open');
    followupOpen?.addEventListener('click', () => {
        document.getElementById('wa3-followup-modal')?.removeAttribute('hidden');
        document.getElementById('wa3-followup-reason')?.focus();
    });
    document.getElementById('wa3-more-followup')?.addEventListener('click', () => {
        closeWa3Menus();
        followupOpen?.click();
    });
    document.getElementById('wa3-more-trail')?.addEventListener('click', () => {
        closeWa3Menus();
        root?.classList.add('has-drawer');
        drawerBtn?.classList.add('is-primary');
        const trail = document.getElementById('wa3-trail-list');
        trail?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    document.getElementById('wa3-followup-submit')?.addEventListener('click', async () => {
        const reasonEl = document.getElementById('wa3-followup-reason');
        const feedback = document.getElementById('wa3-followup-feedback');
        const reason = (reasonEl?.value || '').trim();
        if (!reason) {
            if (feedback) feedback.textContent = 'El motivo del cierre es obligatorio.';
            reasonEl?.focus();
            return;
        }
        try {
            await postJson(`${apiBase}/conversations/${followupOpen?.dataset.conversationId}/leads`, { motivo_baja: reason });
            window.location.href = `${previewRoute}?filter=closed`;
        } catch (err) {
            if (feedback) {
                feedback.dataset.tone = 'danger';
                feedback.textContent = err.message || 'No se pudo cerrar seguimiento.';
            }
        }
    });

    async function loadTrail() {
        const trail = document.getElementById('wa3-trail-list');
        const conversationId = form?.dataset.conversationId;
        if (!trail || !conversationId) return;
        try {
            const res = await fetch(`${apiBase}/conversations/${conversationId}/trail`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            const rows = Array.isArray(json.data) ? json.data : [];
            if (!res.ok || !json.ok || rows.length === 0) {
                trail.textContent = rows.length === 0 ? 'Sin eventos registrados.' : 'No se pudo cargar la trazabilidad.';
                return;
            }
            trail.innerHTML = '<div class="wa3-trail">' + rows.map((item) => {
                const label = esc(item.label || item.type || 'Evento');
                const actor = esc(item.actor_name || item.actor || 'Sistema');
                const at = esc(item.created_at_label || item.created_at || '');
                const notes = item.notes ? `<div class="wa3-trail__meta">${esc(item.notes)}</div>` : '';
                return `<div class="wa3-trail__item"><strong>${label}</strong><div class="wa3-trail__meta">${actor}${at ? ' · ' + at : ''}</div>${notes}</div>`;
            }).join('') + '</div>';
        } catch (err) {
            trail.textContent = 'Error de red al cargar la trazabilidad.';
        }
    }
    loadTrail();

    const templateById = (id) => templates.find((tpl) => Number(tpl.id || 0) === Number(id || 0));
    const startTemplate = document.getElementById('wa3-start-template');
    const startVars = document.getElementById('wa3-start-template-vars');
    const startPreview = document.getElementById('wa3-start-preview');
    const startFeedback = document.getElementById('wa3-start-feedback');
    const setStartFeedback = (message, tone = '') => {
        if (!startFeedback) return;
        startFeedback.dataset.tone = tone;
        startFeedback.textContent = message;
    };
    function openStartTemplateModal(payload = {}) {
        document.getElementById('wa3-new-modal')?.removeAttribute('hidden');
        if (payload.waNumber !== undefined) document.getElementById('wa3-start-number').value = payload.waNumber || '';
        if (payload.contactName !== undefined) document.getElementById('wa3-start-contact-name').value = payload.contactName || '';
        if (payload.patientName !== undefined) document.getElementById('wa3-start-patient-name').value = payload.patientName || '';
        if (payload.hcNumber !== undefined) document.getElementById('wa3-start-hc').value = payload.hcNumber || '';
        if (payload.templateId && startTemplate) {
            startTemplate.value = String(payload.templateId);
            renderStartVariables();
        }
        setStartFeedback('Completa los datos de la plantilla antes de enviarla.', 'success');
    }
    function renderStartPreview() {
        if (!startTemplate || !startPreview) return;
        const tpl = templateById(startTemplate.value);
        const body = tpl?.body_text || tpl?.preview || '';
        const examples = Array.isArray(tpl?.variable_examples) ? tpl.variable_examples : [];
        const values = startVars ? Array.from(startVars.querySelectorAll('[data-wa-template-variable]')).map((input) => input.value.trim()) : [];
        startPreview.textContent = body
            ? body.replace(/\{\{\s*(\d+)\s*\}\}/g, (match, index) => values[Number(index) - 1] || examples[Number(index) - 1] || match)
            : 'Esta plantilla no tiene mensaje registrado.';
    }
    function renderStartVariables() {
        if (!startTemplate || !startVars) return;
        const tpl = templateById(startTemplate.value);
        const variables = Array.isArray(tpl?.variables) ? tpl.variables : [];
        const examples = Array.isArray(tpl?.variable_examples) ? tpl.variable_examples : [];
        startVars.innerHTML = variables.map((variable, index) => `
            <div class="wa3-field">
                <label>Variable ${index + 1} ${esc(variable)}</label>
                <input type="text" data-wa-template-variable="${index}" placeholder="${esc(examples[index] || 'Valor')}">
            </div>
        `).join('');
        startVars.querySelectorAll('[data-wa-template-variable]').forEach((input) => input.addEventListener('input', renderStartPreview));
        renderStartPreview();
    }
    startTemplate?.addEventListener('change', renderStartVariables);
    renderStartVariables();

    function renderStartResults(rows) {
        const box = document.getElementById('wa3-start-results');
        if (!box) return;
        if (!Array.isArray(rows) || rows.length === 0) {
            box.innerHTML = '<div style="font:400 12px var(--bs-body-font-family);color:var(--wa3-text-mute);">Sin resultados con número WhatsApp utilizable.</div>';
            return;
        }
        box.innerHTML = rows.map((row, index) => {
            const title = esc(row.display_name || row.wa_number || 'Contacto');
            const meta = [row.wa_number || '', row.hc_number ? `HC ${row.hc_number}` : ''].filter(Boolean).join(' · ');
            const open = row.source === 'conversation' && row.id
                ? `<a class="wa3-secondary-btn" href="${previewRoute}?conversation=${Number(row.id)}&filter={{ $selectedFilter }}">Abrir</a>`
                : '';
            return `<div class="wa3-picker-card ${index === 0 ? 'is-active' : ''}">
                <div><strong>${title}</strong><small>${esc(meta || 'Sin meta')}</small></div>
                <div style="display:flex;gap:6px;">${open}<button class="wa3-secondary-btn" type="button" data-wa3-pick-contact data-wa-number="${esc(row.wa_number || '')}" data-wa-name="${title}" data-wa-hc="${esc(row.hc_number || '')}">Usar</button></div>
            </div>`;
        }).join('');
        box.querySelectorAll('[data-wa3-pick-contact]').forEach((button) => {
            button.addEventListener('click', () => {
                box.querySelectorAll('.wa3-picker-card').forEach((card) => card.classList.remove('is-active'));
                button.closest('.wa3-picker-card')?.classList.add('is-active');
                document.getElementById('wa3-start-number').value = button.dataset.waNumber || '';
                document.getElementById('wa3-start-contact-name').value = button.dataset.waName || '';
                document.getElementById('wa3-start-patient-name').value = button.dataset.waName || '';
                document.getElementById('wa3-start-hc').value = button.dataset.waHc || '';
            });
        });
    }
    document.getElementById('wa3-start-search-button')?.addEventListener('click', async () => {
        const query = (document.getElementById('wa3-start-search')?.value || '').trim();
        if (!query) {
            setStartFeedback('Escribe celular, HC o nombres para buscar.');
            return;
        }
        setStartFeedback('Buscando contacto...');
        try {
            const res = await fetch(`${apiBase}/contacts/search?q=${encodeURIComponent(query)}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            if (!res.ok || !json.ok) throw new Error(json.error || 'No fue posible buscar contactos.');
            renderStartResults(json.data || []);
            setStartFeedback('Selecciona un resultado o ajusta el número manualmente.', 'success');
        } catch (err) {
            renderStartResults([]);
            setStartFeedback(err.message || 'No fue posible buscar contactos.', 'danger');
        }
    });
    document.getElementById('wa3-start-search')?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.getElementById('wa3-start-search-button')?.click();
        }
    });
    document.getElementById('wa3-start-submit')?.addEventListener('click', async () => {
        const waNumber = (document.getElementById('wa3-start-number')?.value || '').trim();
        const templateId = Number(startTemplate?.value || 0);
        if (!waNumber || !templateId) {
            setStartFeedback('Número WhatsApp y plantilla son obligatorios.', 'danger');
            return;
        }
        try {
            setStartFeedback('Iniciando conversación con plantilla...');
            const json = await postJson(`${apiBase}/conversations/start-template`, {
                wa_number: waNumber,
                template_id: templateId,
                contact_name: (document.getElementById('wa3-start-contact-name')?.value || '').trim(),
                patient_full_name: (document.getElementById('wa3-start-patient-name')?.value || '').trim(),
                patient_hc_number: (document.getElementById('wa3-start-hc')?.value || '').trim(),
                template_variables: startVars ? Array.from(startVars.querySelectorAll('[data-wa-template-variable]')).map((input) => input.value.trim()) : [],
            });
            const conversationId = Number(json?.data?.conversation?.id || 0);
            window.location.href = conversationId > 0 ? `${previewRoute}?conversation=${conversationId}&filter={{ $selectedFilter }}` : previewRoute;
        } catch (err) {
            setStartFeedback(err.message || 'No fue posible iniciar la conversación.', 'danger');
        }
    });

    let latestMessageId = Array.from(document.querySelectorAll('[data-message-id]')).reduce((max, el) => Math.max(max, Number(el.dataset.messageId || 0)), 0);
    let polling = false;
    async function pollConversation() {
        const conversationId = form?.dataset.conversationId;
        if (!conversationId || requestInFlight || polling) return;
        polling = true;
        try {
            const res = await fetch(`${apiBase}/conversations/${conversationId}?message_limit=30`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            const messages = Array.isArray(json?.data?.messages) ? json.data.messages : [];
            const newest = messages.reduce((max, message) => Math.max(max, Number(message.id || 0)), latestMessageId);
            if (newest > latestMessageId) {
                latestMessageId = newest;
                const banner = document.getElementById('wa3-realtime-banner');
                if (banner) banner.classList.add('is-visible');
            }
            if (json?.data?.last_message_preview) {
                const selectedPreview = document.querySelector(`[data-wa-conversation-preview="${conversationId}"]`);
                if (selectedPreview) selectedPreview.textContent = json.data.last_message_preview;
            }
        } catch (err) {
            // silent polling
        } finally {
            polling = false;
        }
    }
    async function pollInbox() {
        try {
            const params = new URLSearchParams({ filter: selectedFilter || 'requires_attention', search: selectedSearch || '', per_page: '25' });
            if (selectedDateFrom) params.set('date_from', selectedDateFrom);
            if (selectedDateTo) params.set('date_to', selectedDateTo);
            const res = await fetch(`${apiBase}/conversations?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            const rows = Array.isArray(json?.data?.data)
                ? json.data.data
                : (Array.isArray(json?.data?.items) ? json.data.items : (Array.isArray(json?.data) ? json.data : []));
            rows.forEach((row) => {
                const id = Number(row.id || 0);
                const item = document.querySelector(`[data-wa-conversation-item="${id}"]`);
                if (!item) return;
                const preview = item.querySelector(`[data-wa-conversation-preview="${id}"]`);
                if (preview && row.last_message_preview) preview.textContent = row.last_message_preview;
                const unread = Number(row.unread_count || 0);
                item.classList.toggle('is-unread', unread > 0);
                const unreadEl = item.querySelector(`[data-wa-conversation-unread="${id}"]`);
                if (unreadEl) unreadEl.textContent = String(unread);
            });
        } catch (err) {
            // silent inbox polling
        }
    }
    document.getElementById('wa3-realtime-reload')?.addEventListener('click', () => window.location.reload());
    if (form) window.setInterval(pollConversation, 10000);
    window.setInterval(pollInbox, 15000);

    // ── Auto-scroll to bottom on initial load ───────────────────────────────
    const list = document.getElementById('wa-v2-message-list');
    if (list) list.scrollTop = list.scrollHeight;

    // ── First-visit welcome tour ───────────────────────────────────────────
    const tourStorageKey = 'medforge_chat_tour_visto';
    const tourModal = document.getElementById('wa3-tour-modal');
    const tourClose = document.getElementById('wa3-tour-close');
    const tourPrev = document.getElementById('wa3-tour-prev');
    const tourNext = document.getElementById('wa3-tour-next');
    const tourDone = document.getElementById('wa3-tour-done');
    const tourCounter = document.getElementById('wa3-tour-counter');
    const tourProgress = document.getElementById('wa3-tour-progress');
    const tourStepTitle = document.getElementById('wa3-tour-step-title');
    const tourStepCopy = document.getElementById('wa3-tour-step-copy');
    const tourSteps = [
        { selector: '.wa3-inbox', title: 'Lista de conversaciones', copy: 'Aquí eliges el chat que vas a revisar. Usa las pestañas para ver pendientes, tuyas o cerradas.' },
        { selector: '.wa3-thread', title: 'Mensajes del paciente', copy: 'En el centro ves el historial completo. Los mensajes nuevos aparecen en esta zona.' },
        { selector: '.wa3-thread__actions', title: 'Acciones principales', copy: 'Desde arriba puedes tomar, transferir, usar plantillas, resolver o abrir más opciones del chat.' },
        { selector: '.wa3-composer', title: 'Campo para escribir', copy: 'Escribe la respuesta abajo. También puedes adjuntar archivos, usar respuestas rápidas o enviar audio si está disponible.' },
        { selector: '.wa3-drawer, #wa3-toggle-drawer', title: 'Ficha del paciente', copy: 'A la derecha están los datos del paciente, notas internas, trazabilidad y accesos como agenda o ficha.' },
    ].filter((step) => document.querySelector(step.selector));
    let tourIndex = 0;
    let activeTourTarget = null;

    function clearTourFocus() {
        if (activeTourTarget) activeTourTarget.classList.remove('wa3-tour-focus');
        activeTourTarget = null;
    }

    function closeTour() {
        clearTourFocus();
        tourModal?.setAttribute('hidden', '');
        try { window.localStorage.setItem(tourStorageKey, '1'); } catch (err) {}
    }

    function renderTourStep() {
        if (!tourModal || tourSteps.length === 0) return;
        clearTourFocus();
        const step = tourSteps[tourIndex];
        activeTourTarget = document.querySelector(step.selector);
        if (activeTourTarget) {
            activeTourTarget.classList.add('wa3-tour-focus');
            activeTourTarget.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        }
        if (tourCounter) tourCounter.textContent = `Paso ${tourIndex + 1} de ${tourSteps.length}`;
        if (tourProgress) tourProgress.style.width = `${((tourIndex + 1) / tourSteps.length) * 100}%`;
        if (tourStepTitle) tourStepTitle.textContent = step.title;
        if (tourStepCopy) tourStepCopy.textContent = step.copy;
        if (tourPrev) tourPrev.disabled = tourIndex === 0;
        if (tourNext) tourNext.hidden = tourIndex === tourSteps.length - 1;
    }

    function openTourIfNeeded() {
        if (!tourModal || tourSteps.length === 0) return;
        try {
            if (window.localStorage.getItem(tourStorageKey) === '1') return;
        } catch (err) {}
        tourModal.removeAttribute('hidden');
        renderTourStep();
    }

    tourPrev?.addEventListener('click', () => {
        tourIndex = Math.max(0, tourIndex - 1);
        renderTourStep();
    });
    tourNext?.addEventListener('click', () => {
        tourIndex = Math.min(tourSteps.length - 1, tourIndex + 1);
        renderTourStep();
    });
    tourDone?.addEventListener('click', closeTour);
    tourClose?.addEventListener('click', closeTour);
    tourModal?.addEventListener('mousedown', (event) => {
        if (event.target === tourModal) closeTour();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && tourModal && !tourModal.hasAttribute('hidden')) closeTour();
    });
    window.setTimeout(openTourIfNeeded, 350);
})();
</script>
@endpush
