<?php

namespace App\Modules\Derivaciones\Services;

use RuntimeException;

class DerivacionesScraperService
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $pythonPath = '/usr/bin/python3',
    ) {
    }

    /**
     * @return array{payload:array<string,mixed>,raw_output:string,exit_code:int}
     */
    public function ejecutar(string $formId, string $hcNumber): array
    {
        [$payload, $rawOutput, $exitCode, $mensaje] = $this->runDerivacionScraper($formId, $hcNumber);

        if (!is_array($payload) && $this->shouldRetryWithLookup($mensaje)) {
            $lookupFormId = $this->resolveLookupFormIdFromAdmisiones($formId, $hcNumber);
            if ($lookupFormId !== null && $this->normalizeComparableId($lookupFormId) !== $this->normalizeComparableId($formId)) {
                [$retryPayload, $retryRaw, $retryCode, $retryMensaje] = $this->runDerivacionScraper($lookupFormId, $hcNumber);
                if (is_array($retryPayload)) {
                    $retryPayload['_lookup_form_id'] = $lookupFormId;
                    return [
                        'payload' => $retryPayload,
                        'raw_output' => $retryRaw,
                        'exit_code' => $retryCode,
                    ];
                }

                throw new RuntimeException($retryMensaje . ' (fallback pedido_id=' . $lookupFormId . ')');
            }
        }

        if (!is_array($payload)) {
            throw new RuntimeException($mensaje);
        }

        return [
            'payload' => $payload,
            'raw_output' => $rawOutput,
            'exit_code' => $exitCode,
        ];
    }

    /**
     * @return array{0:array<string,mixed>|null,1:string,2:int,3:string}
     */
    private function runDerivacionScraper(string $formId, string $hcNumber): array
    {
        $command = $this->buildDerivacionScraperCommand($formId, $hcNumber);
        [$outputLines, $exitCode] = $this->runCommand($command);
        $rawOutput = trim(implode("\n", $outputLines));
        $payload = $this->parseJsonPayload($outputLines, $rawOutput);
        $mensaje = $this->resolveScraperErrorMessage($rawOutput, $exitCode);

        return [$payload, $rawOutput, $exitCode, $mensaje];
    }

    private function buildDerivacionScraperCommand(string $formId, string $hcNumber): string
    {
        $script = $this->projectRoot . '/scrapping/scrape_derivacion.py';
        if (!is_file($script)) {
            throw new RuntimeException('No se encontró el script de scraping.');
        }

        $python = is_file($this->pythonPath) ? $this->pythonPath : 'python3';

        return sprintf(
            '%s %s %s %s --quiet 2>&1',
            escapeshellcmd($python),
            escapeshellarg($script),
            escapeshellarg($formId),
            escapeshellarg($hcNumber)
        );
    }

    private function shouldRetryWithLookup(string $mensaje): bool
    {
        $needle = strtolower(trim($mensaje));
        if ($needle === '') {
            return false;
        }

        return str_contains($needle, 'update-solicitud')
            || str_contains($needle, 'enlace de actualización');
    }

    private function resolveLookupFormIdFromAdmisiones(string $formId, string $hcNumber): ?string
    {
        $script = $this->projectRoot . '/scrapping/scrape_index_admisiones_hc.py';
        if (!is_file($script)) {
            return null;
        }

        $python = is_file($this->pythonPath) ? $this->pythonPath : 'python3';
        $command = sprintf(
            '%s %s %s --group --quiet 2>&1',
            escapeshellcmd($python),
            escapeshellarg($script),
            escapeshellarg($hcNumber)
        );
        [$outputLines, $exitCode] = $this->runCommand($command);
        if ($exitCode !== 0) {
            return null;
        }

        $rawOutput = trim(implode("\n", $outputLines));
        $parsed = $this->parseJsonPayload($outputLines, $rawOutput);
        if (!is_array($parsed)) {
            return null;
        }

        $grouped = $parsed['grouped'] ?? null;
        if (!is_array($grouped)) {
            return null;
        }

        $targetForm = $this->normalizeComparableId($formId);
        if ($targetForm === '') {
            return null;
        }

        foreach ($grouped as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pedido = trim((string) ($item['pedido_id_mas_antiguo'] ?? ''));
            if ($pedido === '') {
                continue;
            }

            $data = is_array($item['data'] ?? null) ? $item['data'] : [];
            $prefactura = trim((string) ($data['prefactura'] ?? $item['prefactura'] ?? ''));

            if ($this->normalizeComparableId($prefactura) === $targetForm) {
                return $pedido;
            }
        }

        return null;
    }

    private function normalizeComparableId(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/\d+/', $value, $matches) === 1) {
            return ltrim((string) $matches[0], '0') ?: '0';
        }

        return $value;
    }

    /**
     * @return array{0:array<int,string>,1:int}
     */
    private function runCommand(string $command): array
    {
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        return [$output, $exitCode];
    }

    /**
     * @param array<int,string> $outputLines
     * @return array<string,mixed>|null
     */
    private function parseJsonPayload(array $outputLines, string $rawOutput): ?array
    {
        for ($i = count($outputLines) - 1; $i >= 0; $i--) {
            $line = trim((string) $outputLines[$i]);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if ($rawOutput !== '') {
            $decoded = json_decode($rawOutput, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function resolveScraperErrorMessage(string $rawOutput, int $exitCode): string
    {
        $lines = preg_split('/\R+/', $rawOutput) ?: [];
        $candidates = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, 'Warning: Binary output')) {
                continue;
            }
            $candidates[] = $line;
        }

        if ($candidates !== []) {
            $lastLine = $candidates[count($candidates) - 1];
            return $lastLine;
        }

        if ($exitCode !== 0) {
            return sprintf('El scraper falló con código %d.', $exitCode);
        }

        return 'No se pudo procesar la salida del scraper.';
    }
}
