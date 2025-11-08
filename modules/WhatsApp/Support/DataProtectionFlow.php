<?php

namespace Modules\WhatsApp\Support;

use DateTimeImmutable;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Repositories\ContactConsentRepository;
use Modules\WhatsApp\Services\Messenger;
use Modules\WhatsApp\Services\PatientLookupService;
use RuntimeException;

class DataProtectionFlow
{
    private Messenger $messenger;
    private ContactConsentRepository $repository;
    private PatientLookupService $patients;
    private WhatsAppSettings $settings;

    public function __construct(
        Messenger $messenger,
        ContactConsentRepository $repository,
        PatientLookupService $patients,
        WhatsAppSettings $settings
    ) {
        $this->messenger = $messenger;
        $this->repository = $repository;
        $this->patients = $patients;
        $this->settings = $settings;
    }

    /**
     * @param array<string, mixed> $message
     */
    public function handle(string $number, string $normalizedKeyword, array $message, string $rawText): bool
    {
        $consentRecord = $this->repository->findByNumber($number);

        if ($consentRecord !== null && $consentRecord['consent_status'] === 'pending') {
            if ($this->isAcceptance($normalizedKeyword)) {
                $this->repository->markConsent($number, $consentRecord['cedula'], true);
                $this->messenger->sendTextMessage($number, '✅ Gracias. Hemos registrado tu autorización para gestionar tus datos clínicos con seguridad.');

                return true;
            }

            if ($this->isRejection($normalizedKeyword)) {
                $this->repository->markConsent($number, $consentRecord['cedula'], false);
                $this->messenger->sendTextMessage($number, 'Entendido. No utilizaremos tus datos hasta que lo autorices. Si deseas continuar responde "sí" o comunícate con nuestro equipo.');

                return true;
            }

            // Recordamos la pregunta inicial
            $this->sendConsentPrompt($number, $consentRecord['patient_full_name'] ?? null);

            return true;
        }

        $cedula = $this->detectCedula($rawText, $normalizedKeyword);
        if ($cedula === null) {
            return false;
        }

        $existing = $this->repository->findByNumberAndCedula($number, $cedula);
        if ($existing !== null && $existing['consent_status'] === 'accepted') {
            $name = trim((string) ($existing['patient_full_name'] ?? ''));
            $message = $name !== ''
                ? 'Hola ' . $name . '. Ya tenemos tu autorización vigente. ¿En qué puedo ayudarte hoy?'
                : 'Ya registramos tu autorización anteriormente. ¿Cómo puedo asistirte?';
            $this->messenger->sendTextMessage($number, $message);

            return true;
        }

        $patient = $this->patients->findLocalByCedula($cedula);
        $source = 'local';
        $rawPayload = null;

        if ($patient === null) {
            try {
                $registry = $this->patients->lookupInRegistry($cedula);
                if ($registry !== null) {
                    $patient = [
                        'hc_number' => $registry['hc_number'] ?? null,
                        'cedula' => $cedula,
                        'full_name' => $registry['full_name'] ?? null,
                    ];
                    $rawPayload = $registry['raw'] ?? $registry;
                    $source = 'registry';
                }
            } catch (RuntimeException $exception) {
                $this->messenger->sendTextMessage(
                    $number,
                    'No pudimos validar tus datos en este momento (' . $exception->getMessage() . '). Intenta nuevamente más tarde o comunícate con nuestro equipo.'
                );

                return true;
            }
        }

        if ($patient === null) {
            $this->messenger->sendTextMessage(
                $number,
                'No encontramos tu número de cédula en nuestros registros. Si crees que es un error, por favor comunícate con nuestro equipo para verificarlo.'
            );

            return true;
        }

        $name = trim((string) ($patient['full_name'] ?? ''));
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->repository->startOrUpdate([
            'wa_number' => $number,
            'cedula' => $cedula,
            'patient_hc_number' => $patient['hc_number'] ?? null,
            'patient_full_name' => $name,
            'consent_status' => 'pending',
            'consent_source' => $source,
            'consent_asked_at' => $now,
            'extra_payload' => $rawPayload,
        ]);

        $intro = $name !== ''
            ? 'Hola ' . $name . ' 👋'
            : 'Hola, gracias por escribirnos 👋';

        $this->messenger->sendTextMessage($number, $intro);
        $this->sendConsentPrompt($number, $name !== '' ? $name : null);

        return true;
    }

    private function sendConsentPrompt(string $number, ?string $name): void
    {
        $config = $this->settings->get();
        $message = (string) ($config['data_consent_message'] ?? 'Confirmamos tu identidad y protegemos tus datos personales. ¿Autorizas el uso de tu información para gestionar tus servicios médicos?');

        if ($name !== null && $name !== '') {
            $message = 'Antes de continuar, ' . $name . ', ' . $message;
        }

        $this->messenger->sendInteractiveButtons($number, $message, [
            ['id' => 'consent_yes', 'title' => 'Sí, autorizo'],
            ['id' => 'consent_no', 'title' => 'No, gracias'],
        ]);
    }

    private function isAcceptance(string $keyword): bool
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return false;
        }

        if (in_array($keyword, ['consent_yes', 'si', 'sí'], true)) {
            return true;
        }

        $config = $this->settings->get();
        foreach (($config['data_consent_yes_keywords'] ?? []) as $accepted) {
            if ($keyword === $accepted) {
                return true;
            }
        }

        return false;
    }

    private function isRejection(string $keyword): bool
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return false;
        }

        if (in_array($keyword, ['consent_no', 'no'], true)) {
            return true;
        }

        $config = $this->settings->get();
        foreach (($config['data_consent_no_keywords'] ?? []) as $rejected) {
            if ($keyword === $rejected) {
                return true;
            }
        }

        return false;
    }

    private function detectCedula(string $rawText, string $normalized): ?string
    {
        $rawDigits = preg_replace('/\D+/', '', $rawText);
        if ($rawDigits !== '' && $this->isValidLength($rawDigits)) {
            return $rawDigits;
        }

        $normalizedDigits = preg_replace('/\D+/', '', $normalized ?? '');
        if ($normalizedDigits !== '' && $this->isValidLength($normalizedDigits)) {
            return $normalizedDigits;
        }

        return null;
    }

    private function isValidLength(string $digits): bool
    {
        $length = strlen($digits);

        return in_array($length, [10, 13], true);
    }
}
