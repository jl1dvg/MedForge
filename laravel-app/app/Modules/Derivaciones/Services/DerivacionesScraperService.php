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
        $script = $this->projectRoot . '/scrapping/scrape_derivacion.py';
        if (!is_file($script)) {
            throw new RuntimeException('No se encontró el script de scraping.');
        }

        $python = is_file($this->pythonPath) ? $this->pythonPath : 'python3';
        $command = sprintf(
            '%s %s %s %s --quiet 2>&1',
            escapeshellcmd($python),
            escapeshellarg($script),
            escapeshellarg($formId),
            escapeshellarg($hcNumber)
        );

        [$outputLines, $exitCode] = $this->runCommand($command);
        $rawOutput = trim(implode("\n", $outputLines));
        $payload = $this->parseJsonPayload($outputLines, $rawOutput);

        if (!is_array($payload)) {
            $mensaje = $this->resolveScraperErrorMessage($rawOutput, $exitCode);
            throw new RuntimeException($mensaje);
        }

        return [
            'payload' => $payload,
            'raw_output' => $rawOutput,
            'exit_code' => $exitCode,
        ];
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
