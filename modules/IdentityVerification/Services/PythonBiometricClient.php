<?php

namespace Modules\IdentityVerification\Services;

class PythonBiometricClient
{
    private string $pythonBinary;
    private string $scriptPath;
    private bool $initialized = false;
    private bool $available = false;

    public function __construct(?string $pythonBinary = null, ?string $scriptPath = null)
    {
        $this->pythonBinary = $pythonBinary !== null && $pythonBinary !== ''
            ? $pythonBinary
            : (getenv('PYTHON3_BIN') ?: 'python3');

        $this->scriptPath = $scriptPath !== null && $scriptPath !== ''
            ? $scriptPath
            : BASE_PATH . '/tools/biometrics/cli.py';
    }

    public function isAvailable(): bool
    {
        if ($this->initialized) {
            return $this->available;
        }

        $this->initialized = true;
        if (!is_file($this->scriptPath) || !is_readable($this->scriptPath)) {
            $this->available = false;
            return false;
        }

        $result = $this->call(['action' => 'ping']);
        $this->available = isset($result['ok']) && $result['ok'] === true;

        return $this->available;
    }

    public function generateTemplate(string $modality, string $binary): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $response = $this->call([
            'action' => 'template',
            'modality' => $modality,
            'binary' => base64_encode($binary),
        ]);

        if (!isset($response['ok']) || $response['ok'] !== true) {
            return null;
        }

        return $response['template'] ?? null;
    }

    public function compare(string $modality, array $reference, array $sample): ?float
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $response = $this->call([
            'action' => 'compare',
            'modality' => $modality,
            'reference' => $reference,
            'sample' => $sample,
        ]);

        if (!isset($response['ok']) || $response['ok'] !== true) {
            return null;
        }

        $score = $response['score'] ?? null;

        return is_numeric($score) ? (float) $score : null;
    }

    private function call(array $payload): ?array
    {
        $command = sprintf(
            '%s %s',
            escapeshellcmd($this->pythonBinary),
            escapeshellarg($this->scriptPath)
        );

        $descriptorSpec = [
            0 => ['pipe', 'r'], // child STDIN (we write)
            1 => ['pipe', 'w'], // child STDOUT (we read)
            2 => ['pipe', 'w'], // child STDERR (we read)
        ];

        $process = @proc_open($command, $descriptorSpec, $pipes, BASE_PATH);
        if (!is_resource($process)) {
            return null;
        }

        $input = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($input === false) {
            $this->closeProcess($process, $pipes);
            return null;
        }

        if (is_resource($pipes[0])) {
            fwrite($pipes[0], $input);
            fclose($pipes[0]);
        }

        $output = is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : '';
        if (is_resource($pipes[1])) {
            fclose($pipes[1]);
        }

        $error = is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : '';
        if (is_resource($pipes[2])) {
            fclose($pipes[2]);
        }

        $status = proc_close($process);
        if ($status !== 0) {
            if (!empty($error)) {
                error_log('[PythonBiometricClient] ' . trim($error));
            }
            return null;
        }

        $decoded = json_decode($output, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    private function closeProcess($process, array $pipes): void
    {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (is_resource($process)) {
            proc_close($process);
        }
    }
}
