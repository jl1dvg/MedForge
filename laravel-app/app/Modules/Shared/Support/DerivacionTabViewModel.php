<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use DateTime;
use Throwable;

final class DerivacionTabViewModel
{
    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function build(array $options): array
    {
        $hasDerivacion = (bool) ($options['has_derivacion'] ?? false);
        $archivoHref = self::nullableString($options['archivo_href'] ?? null);
        $vigencia = self::buildVigenciaMeta(self::nullableString($options['fecha_vigencia'] ?? null));
        $afiliacion = trim((string) ($options['afiliacion'] ?? ''));
        $mailStatusLabel = trim((string) ($options['mail_status_label'] ?? ''));
        $mailSentAt = trim((string) ($options['mail_sent_at'] ?? ''));
        $mailSentBy = trim((string) ($options['mail_sent_by'] ?? ''));
        $coverageVisible = (bool) ($options['coverage_visible'] ?? false);
        $authorizationVisible = (bool) ($options['authorization_visible'] ?? false);
        $rescrapeVisible = (bool) ($options['rescrape_visible'] ?? false);
        $coverageActiveMessage = trim((string) ($options['coverage_active_message'] ?? ''));
        $authorizationMessage = trim((string) ($options['authorization_message'] ?? 'Seguro particular: requiere autorización.'));

        $coverageTitle = $vigencia['expired']
            ? 'Derivación vencida'
            : 'Solicitar cobertura adicional';
        $coverageMessage = $vigencia['expired']
            ? 'Afiliación: ' . $afiliacion . '. Solicita un nuevo código por correo adjuntando la derivación.'
            : ($coverageActiveMessage !== ''
                ? $coverageActiveMessage
                : 'Puedes solicitar cobertura por correo.');

        return [
            'has_derivacion' => $hasDerivacion,
            'archivo_href' => $archivoHref,
            'vigencia' => $vigencia,
            'actions' => [
                'coverage_mail' => [
                    'visible' => $coverageVisible,
                    'style' => $vigencia['expired'] ? 'warning' : 'info',
                    'title' => $coverageTitle,
                    'message' => $coverageMessage,
                    'button_label' => trim((string) ($options['coverage_button_label'] ?? 'Solicitar cobertura por correo')),
                    'status_label' => $mailStatusLabel,
                    'sent_at' => $mailSentAt,
                    'sent_by' => $mailSentBy,
                ],
                'authorization' => [
                    'visible' => $authorizationVisible,
                    'message' => $authorizationMessage,
                    'button_label' => trim((string) ($options['authorization_button_label'] ?? 'Solicitar autorización')),
                ],
                'download_pdf' => [
                    'visible' => $archivoHref !== null,
                    'href' => $archivoHref,
                    'label' => trim((string) ($options['download_label'] ?? 'Descargar derivación')),
                ],
                'rescrape' => [
                    'visible' => $rescrapeVisible,
                    'label' => trim((string) ($options['rescrape_label'] ?? 'Re-scrapear derivación')),
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed>|null $mailLog
     * @return array{label:string,sent_at:string,sent_by:string}
     */
    public static function formatMailStatus(?array $mailLog): array
    {
        $sentAt = '';
        $sentBy = '';

        if (!empty($mailLog['sent_at'])) {
            try {
                $sentAtDate = new DateTime((string) $mailLog['sent_at']);
                $sentAt = $sentAtDate->format('d-m-Y H:i');
            } catch (Throwable) {
                $sentAt = (string) $mailLog['sent_at'];
            }
        }

        if (!empty($mailLog['sent_by_name'])) {
            $sentBy = (string) $mailLog['sent_by_name'];
        }

        if ($sentAt === '') {
            return ['label' => '', 'sent_at' => '', 'sent_by' => ''];
        }

        $label = 'Cobertura solicitada el ' . $sentAt;
        if ($sentBy !== '') {
            $label .= ' por ' . $sentBy;
        }

        return [
            'label' => $label,
            'sent_at' => $sentAt,
            'sent_by' => $sentBy,
        ];
    }

    public static function resolveArchivoHref(array $derivacion): ?string
    {
        $derivacionId = $derivacion['derivacion_id'] ?? $derivacion['id'] ?? null;
        if (!empty($derivacionId)) {
            return '/derivaciones/archivo/' . urlencode((string) $derivacionId);
        }

        if (!empty($derivacion['archivo_derivacion_path'])) {
            return '/' . ltrim((string) $derivacion['archivo_derivacion_path'], '/');
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    public static function buildVigenciaMeta(?string $fechaVigencia): array
    {
        $meta = [
            'text' => 'No disponible',
            'badge' => null,
            'expired' => false,
            'days' => null,
        ];

        if ($fechaVigencia === null || trim($fechaVigencia) === '') {
            return $meta;
        }

        try {
            $vigencia = new DateTime($fechaVigencia);
            $hoy = new DateTime();
            $days = (int) $hoy->diff($vigencia)->format('%r%a');
            $meta['days'] = $days;
            $meta['expired'] = $days < 0;
            $meta['text'] = '<strong>Días para caducar:</strong> ' . $days . ' días';

            if ($days >= 60) {
                $meta['badge'] = ['color' => 'success', 'texto' => 'Vigente', 'icon' => 'bi-check-circle'];
            } elseif ($days >= 30) {
                $meta['badge'] = ['color' => 'info', 'texto' => 'Vigente', 'icon' => 'bi-info-circle'];
            } elseif ($days >= 15) {
                $meta['badge'] = ['color' => 'warning', 'texto' => 'Por vencer', 'icon' => 'bi-hourglass-split'];
            } elseif ($days >= 0) {
                $meta['badge'] = ['color' => 'danger', 'texto' => 'Urgente', 'icon' => 'bi-exclamation-triangle'];
            } else {
                $meta['badge'] = ['color' => 'dark', 'texto' => 'Vencida', 'icon' => 'bi-x-circle'];
            }
        } catch (Throwable) {
            return $meta;
        }

        return $meta;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);
        return $trimmed !== '' ? $trimmed : null;
    }
}
