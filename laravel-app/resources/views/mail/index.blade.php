@extends('layouts.medforge')

@php
    $mailbox = $mailbox ?? [];
    $feed = $mailbox['feed'] ?? [];
    $contacts = $mailbox['contacts'] ?? [];
    $stats = $mailbox['stats'] ?? ['folders' => []];
    $contexts = $mailbox['contexts'] ?? [];
    $config = $mailbox['config'] ?? [];
    $selectedMessage = $feed[0] ?? null;

    $sourceBadges = [
        'solicitudes' => 'badge bg-primary',
        'examenes' => 'badge bg-info',
        'cobertura' => 'badge bg-warning text-dark',
        'tickets' => 'badge bg-secondary',
        'whatsapp' => 'badge bg-success',
    ];

    $contextLabels = [
        'solicitud' => 'Solicitudes recientes',
        'examen' => 'Exámenes recientes',
        'ticket' => 'Tickets recientes',
    ];

    $formatCount = static fn(?int $value): string => number_format(max(0, (int) ($value ?? 0)));
    $encodeEntry = static fn(array $value): string => htmlspecialchars(
        json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ENT_QUOTES,
        'UTF-8'
    );
    $mailboxEnabled = (bool) ($config['enabled'] ?? true);
    $composeEnabled = (bool) ($config['compose_enabled'] ?? true);

    $hasSelection = $selectedMessage !== null;
    $initialSubject = $hasSelection ? ($selectedMessage['subject'] ?? 'Mensaje seleccionado') : 'Selecciona un mensaje';
    $initialContact = $hasSelection ? ($selectedMessage['contact']['label'] ?? 'Contacto') : 'Sin seleccionar';
    $initialTime = $hasSelection ? ($selectedMessage['relative_time'] ?? '') : '';
    $initialBody = $hasSelection ? trim((string) ($selectedMessage['body'] ?? '')) : 'Haz clic en un mensaje para ver los detalles.';
    $initialChannels = $hasSelection ? ($selectedMessage['channels'] ?? []) : [];
    $initialMeta = $hasSelection ? ($selectedMessage['meta'] ?? []) : [];
    $initialLinks = $hasSelection ? ($selectedMessage['links'] ?? []) : [];
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Mailbox</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Mailbox</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="row">
            @if (!$mailboxEnabled)
                <div class="col-12">
                    <div class="alert alert-warning">
                        El Mailbox está desactivado desde Configuración → Mailbox. Puedes volver a activarlo en cualquier momento.
                    </div>
                </div>
            @endif

            @if (!empty($flashMessage))
                <div class="col-12">
                    <div class="alert alert-info alert-dismissible">
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                        {{ $flashMessage }}
                    </div>
                </div>
            @endif

            <div class="col-xl-2 col-lg-4 col-12">
                @if ($composeEnabled)
                    <button class="btn btn-danger w-p100 mb-30" type="button" data-bs-toggle="modal"
                            data-bs-target="#mailboxComposeModal">
                        <i class="mdi mdi-email-plus-outline"></i> Compose
                    </button>
                @else
                    <button class="btn btn-danger w-p100 mb-10" type="button" disabled>
                        <i class="mdi mdi-email-off-outline"></i> Compose deshabilitado
                    </button>
                    <p class="text-muted small">Habilítalo en Configuración → Mailbox.</p>
                @endif

                <div class="box">
                    <div class="box-body no-padding mailbox-nav">
                        <ul class="nav nav-pills flex-column">
                            @foreach (($stats['folders'] ?? []) as $folder)
                                <li class="nav-item">
                                    <a class="nav-link{{ $folder['key'] === 'inbox' ? ' active' : '' }}" href="javascript:void(0)">
                                        <i class="{{ $folder['icon'] ?? 'ion ion-ios-email-outline' }}"></i>
                                        {{ $folder['label'] ?? 'Inbox' }}
                                        <span class="label label-primary float-end">{{ $formatCount($folder['count'] ?? 0) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="box">
                    <div class="box-body pt-0 contact-bx" style="max-height: 300px; overflow-y: auto;">
                        @if ($contacts === [])
                            <p class="text-muted mb-0">No hay contactos recientes.</p>
                        @else
                            <div class="media-list media-list-hover">
                                @foreach ($contacts as $contact)
                                    <div class="media py-10 px-0 align-items-center border-bottom">
                                        <div class="avatar avatar-lg bg-primary text-white me-3">
                                            {{ $contact['avatar'] ?? ($contact['label'][0] ?? 'M') }}
                                        </div>
                                        <div class="media-body">
                                            <p class="fs-16 mb-0 fw-600">{{ $contact['label'] }}</p>
                                            <small class="d-block text-muted">
                                                {{ $contact['channel'] ?? 'Inbox' }} ·
                                                {{ $formatCount($contact['count'] ?? 0) }} mensajes
                                            </small>
                                            @if (!empty($contact['last_relative']))
                                                <small class="text-muted">{{ $contact['last_relative'] }}</small>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-6 col-lg-8 col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h4 class="box-title">Inbox</h4>
                        <div class="box-controls pull-right">
                            <div class="box-header-actions">
                                <div class="lookup lookup-sm lookup-right d-none d-lg-block">
                                    <form method="get" action="/mailbox">
                                        <input type="text" name="q" placeholder="Buscar..." value="{{ $mailbox['filters']['query'] ?? '' }}">
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="mailbox-controls mb-2">
                            <button type="button" class="btn btn-primary btn-sm checkbox-toggle">
                                <i class="ion ion-android-checkbox-outline-blank"></i>
                            </button>
                            <div class="btn-group">
                                <button type="button" class="btn btn-primary btn-sm"><i class="ion ion-refresh"></i></button>
                            </div>
                            <span class="ms-3 text-muted">{{ $formatCount(count($feed)) }} mensajes</span>
                        </div>
                        <div class="mailbox-messages inbox-bx" style="max-height: 540px; overflow-y: auto;">
                            @if ($feed === [])
                                <div class="text-center py-50 text-muted">
                                    <i class="mdi mdi-inbox-arrow-down fs-36 d-block mb-10"></i>
                                    <p class="mb-0">Aún no hay actividad para mostrar.</p>
                                </div>
                            @else
                                <table class="table table-hover table-striped" data-mailbox-table>
                                    <tbody>
                                    @foreach ($feed as $message)
                                        @php
                                            $isSelected = $selectedMessage && (($selectedMessage['uid'] ?? null) === ($message['uid'] ?? null));
                                        @endphp
                                        <tr class="mailbox-row{{ $isSelected ? ' is-active' : '' }}"
                                            data-mailbox-row
                                            data-mailbox-uid="{{ $message['uid'] ?? '' }}"
                                            data-mailbox-entry="{!! $encodeEntry($message) !!}">
                                            <td>
                                                <input type="checkbox" value="{{ $message['uid'] }}">
                                            </td>
                                            <td class="mailbox-star">
                                                <i class="fa {{ in_array($message['source'], ['solicitudes', 'examenes', 'cobertura'], true) ? 'fa-star text-yellow' : 'fa-star-o text-yellow' }}"></i>
                                            </td>
                                            <td>
                                                <p class="mailbox-name mb-0 fs-16 fw-600">
                                                    {{ $message['contact']['label'] ?? 'Contacto' }}
                                                </p>
                                                <a class="mailbox-subject" href="javascript:void(0)">
                                                    <span class="{{ $sourceBadges[$message['source']] ?? 'badge bg-light text-dark' }}">
                                                        {{ $message['source_label'] ?? 'Inbox' }}
                                                    </span>
                                                    <strong>{{ $message['subject'] ?? 'Mensaje' }}</strong>
                                                    @if (!empty($message['snippet']))
                                                        - {{ $message['snippet'] }}
                                                    @endif
                                                </a>
                                                @if (!empty($message['meta']))
                                                    <div class="text-muted small">
                                                        @foreach ($message['meta'] as $label => $value)
                                                            <span class="me-2">{{ $label }}: {{ $value }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="mailbox-date text-nowrap">
                                                {{ $message['relative_time'] ?? '' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-body pt-10 mailbox-detail"
                         data-mailbox-detail
                         data-mailbox-empty-subject="Selecciona un mensaje"
                         data-mailbox-empty-contact="Sin seleccionar"
                         data-mailbox-empty-time=""
                         data-mailbox-empty-body="Haz clic en un mensaje para ver los detalles.">
                        <div class="mailbox-read-info">
                            <h4 data-mailbox-field="subject">{{ $initialSubject }}</h4>
                            <div class="d-flex justify-content-between text-muted">
                                <span data-mailbox-field="contact">{{ $initialContact }}</span>
                                <span data-mailbox-field="time">{{ $initialTime }}</span>
                            </div>
                        </div>
                        <div class="mt-3" data-mailbox-channels>
                            @foreach ($initialChannels as $channel)
                                <span class="badge bg-light text-dark me-1">{{ $channel }}</span>
                            @endforeach
                        </div>
                        <div class="mailbox-read-message read-mail-bx mt-3"
                             data-mailbox-field="body"
                             style="max-height: 350px; overflow-y: auto;">
                            @if ($initialBody !== '')
                                <p>{!! nl2br(e($initialBody)) !!}</p>
                            @endif
                        </div>

                        <div class="mt-20" data-mailbox-meta-section @if (empty($initialMeta)) hidden @endif>
                            <h5 class="box-title fs-16">Contexto</h5>
                            <ul class="list-unstyled mb-0" data-mailbox-meta>
                                @foreach ($initialMeta as $label => $value)
                                    <li class="d-flex justify-content-between py-5 border-bottom">
                                        <span class="text-muted">{{ $label }}</span>
                                        <span>{{ $value }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="mt-20" data-mailbox-actions-section @if (empty($initialLinks)) hidden @endif>
                            <h5 class="box-title fs-16">Acciones</h5>
                            <div data-mailbox-actions>
                                @foreach ($initialLinks as $label => $url)
                                    <a class="btn btn-sm btn-outline-primary me-2 mb-2"
                                       href="{{ $url }}" target="_blank" rel="noopener">
                                        Ir a {{ ucfirst($label) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" id="mailboxComposeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Registrar nuevo mensaje</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="post" action="/mailbox/compose">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label class="form-label">Conversaciones recientes</label>
                            <select class="form-select" name="target_reference">
                                <option value="">-- Selecciona una conversación --</option>
                                @foreach ($contexts as $type => $options)
                                    @if ($options === []) @continue @endif
                                    <optgroup label="{{ $contextLabels[$type] ?? ucfirst($type) }}">
                                        @foreach ($options as $option)
                                            <option value="{{ $option['value'] }}">
                                                {{ $option['label'] }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <small class="text-muted d-block">Puedes elegir un hilo de la lista o indicar los datos manualmente.</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Tipo de destino</label>
                                    <select class="form-select" name="target_type">
                                        <option value="">-- Selecciona --</option>
                                        <option value="solicitud">Solicitud</option>
                                        <option value="examen">Examen</option>
                                        <option value="ticket">Ticket</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">ID de destino</label>
                                    <input type="number" min="1" class="form-control" name="target_id"
                                           placeholder="Ej. 1204">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mensaje</label>
                            <textarea class="form-control" name="message" rows="6"
                                      placeholder="Describe la actualización o instructivo" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Registrar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="/js/pages/mailbox.js"></script>
@endpush
