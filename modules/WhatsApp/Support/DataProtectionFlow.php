<?php

namespace Modules\WhatsApp\Support;

use DateTimeImmutable;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Repositories\ContactConsentRepository;
use Modules\WhatsApp\Services\Messenger;
use Modules\WhatsApp\Services\PatientLookupService;

use function array_filter;
use function array_merge;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function preg_split;
use function sha1;
use function strlen;
use function strtr;
use function strtoupper;
use function substr;
use function trim;

class DataProtectionFlow
{
    private const STAGE_AWAITING_CONSENT = 'awaiting_consent';
    private const STAGE_AWAITING_IDENTIFIER = 'awaiting_identifier';
    private const STAGE_COMPLETE = 'complete';
    private const MIN_HISTORY_LENGTH = 6;

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
        $record = $this->repository->findByNumber($number);

        if ($record !== null && !isset($record['identifier']) && isset($record['cedula'])) {
            $record['identifier'] = (string) $record['cedula'];
        }

        if ($record === null) {
            $this->beginConsentHandshake($number);

            return true;
        }

        $status = (string) ($record['consent_status'] ?? 'pending');

        if ($status === 'accepted') {
            return false;
        }

        $stage = $this->resolveStage($record);

        if ($status === 'declined') {
            if ($this->isAcceptance($normalizedKeyword)) {
                $identifierValue = (string) ($record['identifier'] ?? '');
                $this->repository->markPendingResponse($number, $identifierValue);
                $this->updateStage($record, self::STAGE_AWAITING_IDENTIFIER, [
                    'restarted_at' => (new DateTimeImmutable())->format('c'),
                ]);
                $this->messenger->sendTextMessage(
                    $number,
                    'Perfecto, continuemos. Escribe tu número de historia clínica 🪪'
                );

                return true;
            }

            $this->messenger->sendTextMessage(
                $number,
                'Para continuar necesitamos tu autorización. Si deseas habilitarla responde "sí" o utiliza los botones enviados anteriormente.'
            );

            return true;
        }

        if ($status === 'pending') {
            if ($stage === self::STAGE_AWAITING_CONSENT) {
                return $this->handleConsentStage($number, $record, $normalizedKeyword);
            }

            return $this->handleIdentifierStage($number, $record, $normalizedKeyword, $rawText);
        }

        return false;
    }

    private function beginConsentHandshake(string $number): void
    {
        $now = new DateTimeImmutable();

        $this->sendConsentIntro($number);
        $this->sendConsentPrompt($number, null);

        $payload = [
            'stage' => self::STAGE_AWAITING_CONSENT,
            'intro_sent_at' => $now->format('c'),
        ];

        $this->repository->startOrUpdate([
            'wa_number' => $number,
            'identifier' => $this->placeholderIdentifier($number),
            'patient_hc_number' => null,
            'patient_full_name' => null,
            'consent_status' => 'pending',
            'consent_source' => 'manual',
            'consent_asked_at' => $now->format('Y-m-d H:i:s'),
            'extra_payload' => $payload,
        ]);
    }

    private function sendConsentIntro(string $number): void
    {
        $config = $this->settings->get();
        $termsUrl = trim((string) ($config['data_terms_url'] ?? ''));
        $parts = ['Por favor, antes de continuar, ayúdanos con unos datos.'];

        if ($termsUrl !== '') {
            $parts[] = "Para continuar con la conversación, debes aceptar nuestros Términos, Condiciones y Aviso de Privacidad:
👉 " . $termsUrl;
        } else {
            $parts[] = 'Para continuar con la conversación, debes aceptar nuestros Términos, Condiciones y Aviso de Privacidad.';
        }

        $parts[] = '¿Continuamos?';

        $this->messenger->sendTextMessage($number, implode("

", $parts));
    }

    /**
     * @param array<string, mixed> $record
     */
    private function handleConsentStage(string $number, array $record, string $keyword): bool
    {
        if ($this->isAcceptance($keyword)) {
            $this->updateStage($record, self::STAGE_AWAITING_IDENTIFIER, [
                'accepted_at' => (new DateTimeImmutable())->format('c'),
            ]);
            $this->messenger->sendTextMessage(
                $number,
                'Escribe tu número de historia clínica 🪪'
            );

            return true;
        }

        if ($this->isRejection($keyword)) {
            $identifierValue = (string) ($record['identifier'] ?? '');
            $this->repository->markConsent($number, $identifierValue, false);
            $this->messenger->sendTextMessage(
                $number,
                'Entendido. No utilizaremos tus datos hasta que lo autorices. Si deseas continuar responde "sí" o comunícate con nuestro equipo.'
            );

            return true;
        }

        $this->messenger->sendTextMessage(
            $number,
            'Necesitamos tu confirmación para continuar. Usa los botones enviados o responde "sí, autorizo" si estás de acuerdo.'
        );
        $this->sendConsentPrompt($number, $record['patient_full_name'] ?? null);

        return true;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function handleIdentifierStage(string $number, array $record, string $keyword, string $rawText): bool
    {
        $identifier = $this->detectIdentifier($rawText, $keyword);

        if ($identifier === null) {
            $this->messenger->sendTextMessage(
                $number,
                'Por favor, escribe tu número de historia clínica para validar que estás registrado.'
            );
            $this->updateStage($record, self::STAGE_AWAITING_IDENTIFIER);

            return true;
        }

        $patient = $this->patients->findLocalByHistoryNumber($identifier['value']);
        if ($patient === null) {
            $this->messenger->sendTextMessage(
                $number,
                'No encontramos tu registro con el número de historia clínica proporcionado. Verifícalo y vuelve a intentarlo.'
            );
            $this->updateStage($record, self::STAGE_AWAITING_IDENTIFIER, [
                'last_identifier_attempt' => $identifier['value'],
            ]);

            return true;
        }

        $historyNumber = isset($patient['hc_number']) ? trim((string) $patient['hc_number']) : '';
        if ($historyNumber === '') {
            $historyNumber = strtoupper($identifier['value']);
        }

        $fullName = isset($patient['full_name']) ? trim((string) $patient['full_name']) : null;
        if ($fullName === '') {
            $fullName = null;
        }

        $payload = array_merge(
            $this->payloadFromRecord($record),
            [
                'stage' => self::STAGE_COMPLETE,
                'identifier' => [
                    'type' => 'history',
                    'value' => $historyNumber,
                    'input' => $identifier['value'],
                    'display' => $identifier['display'],
                ],
                'verified_at' => (new DateTimeImmutable())->format('c'),
            ]
        );

        $currentIdentifier = (string) ($record['identifier'] ?? '');

        $this->repository->reassignIdentifier(
            $number,
            $currentIdentifier,
            $historyNumber,
            $historyNumber,
            $fullName,
            'local',
            $payload
        );

        $this->repository->markConsent($number, $historyNumber, true);

        $this->messenger->sendTextMessage($number, '¿Está seguro de que la información ingresada es correcta? ✅');
        $this->messenger->sendTextMessage(
            $number,
            'Por favor, verifica si tu número de historia clínica ' . $historyNumber . ' está correcto antes de continuar. ¡Gracias por tu atención! 😊'
        );
        $this->messenger->sendTextMessage(
            $number,
            'Cuando confirmes la información, responde con la opción que necesites o escribe "menu" para ver las alternativas disponibles.'
        );
        $this->messenger->sendTextMessage(
            $number,
            'Tu autorización quedó registrada en nuestro sistema. Continuemos con la atención. ✅'
        );

        return true;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function payloadFromRecord(array $record): array
    {
        $payload = $record['extra_payload'] ?? null;

        if (is_array($payload)) {
            return $payload;
        }

        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function updateStage(array $record, string $stage, array $extra = []): void
    {
        $payload = array_merge($this->payloadFromRecord($record), $extra);
        $payload['stage'] = $stage;

        $identifier = (string) ($record['identifier'] ?? $record['cedula'] ?? '');

        $this->repository->updateExtraPayload($record['wa_number'], $identifier, $payload);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function resolveStage(array $record): string
    {
        $payload = $this->payloadFromRecord($record);
        $stage = $payload['stage'] ?? null;

        return is_string($stage) ? $stage : self::STAGE_AWAITING_IDENTIFIER;
    }

    private function placeholderIdentifier(string $number): string
    {
        return '__pending_' . substr(sha1($number), 0, 10);
    }

    /**
     * @return array{type: string, value: string, display: string}|null
     */
    private function detectIdentifier(string $rawText, string $normalized): ?array
    {
        $history = $this->normalizeHistoryIdentifier($rawText);
        if ($history !== null) {
            return $history;
        }

        return $this->normalizeHistoryIdentifier($normalized);
    }

    /**
     * @return array{type: string, value: string, display: string}|null
     */
    private function normalizeHistoryIdentifier(string $value): ?array
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $value);
        if ($clean === null || $clean === '') {
            return null;
        }

        if (!preg_match('/\d/', $clean)) {
            return null;
        }

        if (strlen($clean) < self::MIN_HISTORY_LENGTH) {
            return null;
        }

        $display = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($display === '') {
            $display = $clean;
        }

        return [
            'type' => 'history',
            'value' => strtoupper($clean),
            'display' => $display,
        ];
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
        return $this->matchesKeyword(
            $keyword,
            [
                'consent_yes',
                'si',
                'si autorizo',
                'autorizo',
                'autorizo si',
                'claro autorizo',
            ],
            $this->collectConfiguredKeywords('data_consent_yes_keywords')
        );
    }

    private function isRejection(string $keyword): bool
    {
        return $this->matchesKeyword(
            $keyword,
            [
                'consent_no',
                'no',
                'no autorizo',
                'no gracias',
                'rechazo',
            ],
            $this->collectConfiguredKeywords('data_consent_no_keywords')
        );
    }

    /**
     * @param array<int, string> $variants
     * @param array<int, string> $configured
     */
    private function matchesKeyword(string $keyword, array $variants, array $configured): bool
    {
        $normalized = trim($keyword);
        if ($normalized === '') {
            return false;
        }

        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $tokens = array_values(array_filter($tokens, static fn($token) => $token !== ''));
        $candidates = array_merge($variants, $configured);

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            if ($normalized === $candidate) {
                return true;
            }

            if (in_array($candidate, $tokens, true)) {
                return true;
            }

            $candidateTokens = preg_split('/\s+/', $candidate) ?: [];
            $candidateTokens = array_values(array_filter($candidateTokens, static fn($token) => $token !== ''));
            if ($candidateTokens === []) {
                continue;
            }

            if ($this->tokensContainSequence($tokens, $candidateTokens)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $tokens
     * @param array<int, string> $sequence
     */
    private function tokensContainSequence(array $tokens, array $sequence): bool
    {
        $needleLength = count($sequence);
        if ($needleLength === 0) {
            return false;
        }

        $haystackLength = count($tokens);
        if ($haystackLength < $needleLength) {
            return false;
        }

        for ($offset = 0; $offset <= $haystackLength - $needleLength; $offset++) {
            $matches = true;
            for ($index = 0; $index < $needleLength; $index++) {
                if ($tokens[$offset + $index] !== $sequence[$index]) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function collectConfiguredKeywords(string $key): array
    {
        $config = $this->settings->get();
        $values = [];

        foreach (($config[$key] ?? []) as $variant) {
            if (!is_string($variant)) {
                continue;
            }

            $normalized = $this->normalizeVariant($variant);
            if ($normalized !== '') {
                $values[] = $normalized;
            }
        }

        return $values;
    }

    private function normalizeVariant(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9 ]+/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
