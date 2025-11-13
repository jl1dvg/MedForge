<?php
/**
 * @var array{
 *     feed: array<int, array<string, mixed>>,
 *     contacts: array<int, array<string, mixed>>,
 *     stats: array<string, mixed>,
 *     contexts: array<string, array<int, array{value:string,label:string}>>,
 *     filters?: array<string, mixed>
 * } $mailbox
 * @var string|null $flashMessage
 */

$mailbox = $mailbox ?? [];
$feed = $mailbox['feed'] ?? [];
$contacts = $mailbox['contacts'] ?? [];
$stats = $mailbox['stats'] ?? ['folders' => []];
$contexts = $mailbox['contexts'] ?? [];
$config = $mailbox['config'] ?? [];
$selectedMessage = $feed[0] ?? null;

$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$sourceBadges = [
    'solicitudes' => 'badge bg-primary',
    'examenes' => 'badge bg-info',
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

?>
<section class="content">
    <div class="row">
        <?php if (!$mailboxEnabled): ?>
            <div class="col-12">
                <div class="alert alert-warning">
                    El Mailbox está desactivado desde Configuración → Mailbox. Puedes volver a activarlo en cualquier momento.
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($flashMessage)): ?>
            <div class="col-12">
                <div class="alert alert-info alert-dismissible">
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    <?= $escape($flashMessage) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-xl-2 col-lg-4 col-12">
            <?php if ($composeEnabled): ?>
                <button class="btn btn-danger w-p100 mb-30" type="button" data-bs-toggle="modal"
                        data-bs-target="#mailboxComposeModal">
                    <i class="mdi mdi-email-plus-outline"></i> Compose
                </button>
            <?php else: ?>
                <button class="btn btn-danger w-p100 mb-10" type="button" disabled>
                    <i class="mdi mdi-email-off-outline"></i> Compose deshabilitado
                </button>
                <p class="text-muted small">Habilítalo en Configuración → Mailbox.</p>
            <?php endif; ?>

            <div class="box">
                <div class="box-body no-padding mailbox-nav">
                    <ul class="nav nav-pills flex-column">
                        <?php foreach ($stats['folders'] ?? [] as $folder): ?>
                            <li class="nav-item">
                                <a class="nav-link<?= $folder['key'] === 'inbox' ? ' active' : '' ?>" href="javascript:void(0)">
                                    <i class="<?= $escape($folder['icon'] ?? 'ion ion-ios-email-outline') ?>"></i>
                                    <?= $escape($folder['label'] ?? 'Inbox') ?>
                                    <span class="label label-primary float-end"><?= $formatCount($folder['count'] ?? 0) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="box">
                <div class="box-body pt-0 contact-bx" style="max-height: 300px; overflow-y: auto;">
                    <?php if ($contacts === []): ?>
                        <p class="text-muted mb-0">No hay contactos recientes.</p>
                    <?php else: ?>
                        <div class="media-list media-list-hover">
                            <?php foreach ($contacts as $contact): ?>
                                <div class="media py-10 px-0 align-items-center border-bottom">
                                    <div class="avatar avatar-lg bg-primary text-white me-3">
                                        <?= $escape($contact['avatar'] ?? $contact['label'][0] ?? 'M') ?>
                                    </div>
                                    <div class="media-body">
                                        <p class="fs-16 mb-0 fw-600"><?= $escape($contact['label']) ?></p>
                                        <small class="d-block text-muted">
                                            <?= $escape($contact['channel'] ?? 'Inbox') ?> ·
                                            <?= $formatCount($contact['count'] ?? 0) ?> mensajes
                                        </small>
                                        <?php if (!empty($contact['last_relative'])): ?>
                                            <small class="text-muted"><?= $escape($contact['last_relative']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
                                    <input type="text" name="q" placeholder="Buscar..." value="<?= $escape($mailbox['filters']['query'] ?? '') ?>">
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
                        <span class="ms-3 text-muted"><?= $formatCount(count($feed)) ?> mensajes</span>
                    </div>
                    <div class="mailbox-messages inbox-bx" style="max-height: 540px; overflow-y: auto;">
                        <?php if ($feed === []): ?>
                            <div class="text-center py-50 text-muted">
                                <i class="mdi mdi-inbox-arrow-down fs-36 d-block mb-10"></i>
                                <p class="mb-0">Aún no hay actividad para mostrar.</p>
                            </div>
                        <?php else: ?>
                            <table class="table table-hover table-striped" data-mailbox-table>
                                <tbody>
                                <?php foreach ($feed as $message): ?>
                                    <?php
                                    $isSelected = $selectedMessage && (($selectedMessage['uid'] ?? null) === ($message['uid'] ?? null));
                                    ?>
                                    <tr class="mailbox-row<?= $isSelected ? ' is-active' : '' ?>"
                                        data-mailbox-row
                                        data-mailbox-uid="<?= $escape($message['uid'] ?? '') ?>"
                                        data-mailbox-entry="<?= $encodeEntry($message) ?>">
                                        <td>
                                            <input type="checkbox" value="<?= $escape($message['uid']) ?>">
                                        </td>
                                        <td class="mailbox-star">
                                            <i class="fa <?= in_array($message['source'], ['solicitudes', 'examenes'], true) ? 'fa-star text-yellow' : 'fa-star-o text-yellow' ?>"></i>
                                        </td>
                                        <td>
                                            <p class="mailbox-name mb-0 fs-16 fw-600">
                                                <?= $escape($message['contact']['label'] ?? 'Contacto') ?>
                                            </p>
                                            <a class="mailbox-subject" href="javascript:void(0)">
                                                <span class="<?= $escape($sourceBadges[$message['source']] ?? 'badge bg-light text-dark') ?>">
                                                    <?= $escape($message['source_label'] ?? 'Inbox') ?>
                                                </span>
                                                <strong><?= $escape($message['subject'] ?? 'Mensaje') ?></strong>
                                                <?php if (!empty($message['snippet'])): ?>
                                                    - <?= $escape($message['snippet']) ?>
                                                <?php endif; ?>
                                            </a>
                                            <?php if (!empty($message['meta'])): ?>
                                                <div class="text-muted small">
                                                    <?php foreach ($message['meta'] as $label => $value): ?>
                                                        <span class="me-2"><?= $escape($label) ?>: <?= $escape($value) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="mailbox-date text-nowrap">
                                            <?= $escape($message['relative_time'] ?? '') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $hasSelection = $selectedMessage !== null;
        $initialSubject = $hasSelection ? ($selectedMessage['subject'] ?? 'Mensaje seleccionado') : 'Selecciona un mensaje';
        $initialContact = $hasSelection ? ($selectedMessage['contact']['label'] ?? 'Contacto') : 'Sin seleccionar';
        $initialTime = $hasSelection ? ($selectedMessage['relative_time'] ?? '') : '';
        $initialBody = $hasSelection ? trim((string) ($selectedMessage['body'] ?? '')) : 'Haz clic en un mensaje para ver los detalles.';
        $initialChannels = $hasSelection ? ($selectedMessage['channels'] ?? []) : [];
        $initialMeta = $hasSelection ? ($selectedMessage['meta'] ?? []) : [];
        $initialLinks = $hasSelection ? ($selectedMessage['links'] ?? []) : [];
        ?>
        <div class="col-xl-4 col-12">
            <div class="box">
                <div class="box-body pt-10 mailbox-detail"
                     data-mailbox-detail
                     data-mailbox-empty-subject="Selecciona un mensaje"
                     data-mailbox-empty-contact="Sin seleccionar"
                     data-mailbox-empty-time=""
                     data-mailbox-empty-body="Haz clic en un mensaje para ver los detalles.">
                    <div class="mailbox-read-info">
                        <h4 data-mailbox-field="subject"><?= $escape($initialSubject) ?></h4>
                        <div class="d-flex justify-content-between text-muted">
                            <span data-mailbox-field="contact"><?= $escape($initialContact) ?></span>
                            <span data-mailbox-field="time"><?= $escape($initialTime) ?></span>
                        </div>
                    </div>
                    <div class="mt-3" data-mailbox-channels>
                        <?php foreach ($initialChannels as $channel): ?>
                            <span class="badge bg-light text-dark me-1"><?= $escape($channel) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="mailbox-read-message read-mail-bx mt-3"
                         data-mailbox-field="body"
                         style="max-height: 350px; overflow-y: auto;">
                        <?php if ($initialBody !== ''): ?>
                            <p><?= nl2br($escape($initialBody)) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="mt-20" data-mailbox-meta-section<?= empty($initialMeta) ? ' hidden' : '' ?>>
                        <h5 class="box-title fs-16">Contexto</h5>
                        <ul class="list-unstyled mb-0" data-mailbox-meta>
                            <?php foreach ($initialMeta as $label => $value): ?>
                                <li class="d-flex justify-content-between py-5 border-bottom">
                                    <span class="text-muted"><?= $escape($label) ?></span>
                                    <span><?= $escape($value) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="mt-20" data-mailbox-actions-section<?= empty($initialLinks) ? ' hidden' : '' ?>>
                        <h5 class="box-title fs-16">Acciones</h5>
                        <div data-mailbox-actions>
                            <?php foreach ($initialLinks as $label => $url): ?>
                                <a class="btn btn-sm btn-outline-primary me-2 mb-2"
                                   href="<?= $escape($url) ?>" target="_blank" rel="noopener">
                                    Ir a <?= $escape(ucfirst($label)) ?>
                                </a>
                            <?php endforeach; ?>
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
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label">Conversaciones recientes</label>
                        <select class="form-select" name="target_reference">
                            <option value="">-- Selecciona una conversación --</option>
                            <?php foreach ($contexts as $type => $options): ?>
                                <?php if ($options === []) { continue; } ?>
                                <optgroup label="<?= $escape($contextLabels[$type] ?? ucfirst($type)) ?>">
                                    <?php foreach ($options as $option): ?>
                                        <option value="<?= $escape($option['value']) ?>">
                                            <?= $escape($option['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
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
