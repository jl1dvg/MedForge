<?php

declare(strict_types=1);

$contract = require __DIR__ . '/http_smoke.contract.php';

$options = getopt('', [
    'module::',
    'endpoint::',
    'fail-fast',
    'legacy-base::',
    'v2-base::',
    'timeout::',
    'cookie::',
    'help',
]);

if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php tools/tests/http_smoke.php [--module=billing] [--endpoint=billing_no_facturados] [--fail-fast]\n";
    echo "       [--legacy-base=http://127.0.0.1:8080] [--v2-base=http://127.0.0.1:8081] [--timeout=20]\n";
    echo "       [--cookie='PHPSESSID=...']\n";
    exit(0);
}

$legacyBase = rtrim((string) ($options['legacy-base'] ?? $contract['defaults']['legacy_base_url']), '/');
$v2Base = rtrim((string) ($options['v2-base'] ?? $contract['defaults']['v2_base_url']), '/');
$timeout = max(1, (int) ($options['timeout'] ?? $contract['defaults']['timeout_seconds']));
$moduleFilter = isset($options['module']) ? trim((string) $options['module']) : null;
$endpointFilter = isset($options['endpoint']) ? trim((string) $options['endpoint']) : null;
$failFast = isset($options['fail-fast']);
$cookieHeader = isset($options['cookie']) ? trim((string) $options['cookie']) : null;

$tests = [];
foreach ($contract['modules'] as $module => $moduleTests) {
    if ($moduleFilter !== null && $moduleFilter !== '' && $module !== $moduleFilter) {
        continue;
    }
    foreach ($moduleTests as $test) {
        if ($endpointFilter !== null && $endpointFilter !== '' && ($test['id'] ?? '') !== $endpointFilter) {
            continue;
        }
        $test['module'] = $module;
        $tests[] = $test;
    }
}

if (empty($tests)) {
    fwrite(STDERR, "No tests matched current filters.\n");
    exit(2);
}

$passed = 0;
$failed = 0;

foreach ($tests as $test) {
    $id = (string) $test['id'];
    $module = (string) $test['module'];
    $method = strtoupper((string) ($test['method'] ?? 'GET'));
    $compareMode = (string) ($test['compare_mode'] ?? 'full');

    echo "\n[$module/$id] $method\n";

    $legacyResult = null;
    if ($compareMode === 'full') {
        $legacyUrl = $legacyBase . (string) $test['legacy_path'];
        $legacyResult = request($legacyUrl, $method, $test['body'] ?? null, $timeout, $cookieHeader);
    }

    $v2Url = $v2Base . (string) $test['v2_path'];
    $v2Result = request($v2Url, $method, $test['body'] ?? null, $timeout, $cookieHeader);

    $errors = [];

    $expectV2Status = (int) ($test['expect_v2_status'] ?? $test['expect_status'] ?? 200);
    $expectLegacyStatus = (int) ($test['expect_legacy_status'] ?? $test['expect_status'] ?? 200);

    if ($v2Result['status'] !== $expectV2Status) {
        $errors[] = "V2 status expected $expectV2Status, got {$v2Result['status']}";
    }

    if ($compareMode === 'full' && $legacyResult !== null && $legacyResult['status'] !== $expectLegacyStatus) {
        $errors[] = "Legacy status expected $expectLegacyStatus, got {$legacyResult['status']}";
    }

    if ($compareMode === 'full' && $legacyResult !== null && $legacyResult['status'] !== $v2Result['status']) {
        $errors[] = "Status mismatch legacy={$legacyResult['status']} v2={$v2Result['status']}";
    }

    $requiredPaths = (array) ($test['required_json_paths'] ?? []);
    if (!empty($requiredPaths)) {
        foreach ($requiredPaths as $path) {
            if (!hasJsonPath($v2Result['json'], (string) $path)) {
                $errors[] = "V2 missing required path: $path";
            }
            if ($compareMode === 'full' && $legacyResult !== null && !hasJsonPath($legacyResult['json'], (string) $path)) {
                $errors[] = "Legacy missing required path: $path";
            }
        }
    }

    if ($compareMode === 'full' && $legacyResult !== null) {
        $legacyShape = jsonShape($legacyResult['json']);
        $v2Shape = jsonShape($v2Result['json']);
        if ($legacyShape !== $v2Shape) {
            $errors[] = "JSON shape differs (legacy vs v2).";
        }
    }

    if (empty($errors)) {
        echo "  PASS\n";
        echo "  status: " . $v2Result['status'] . "\n";
        if (!empty($test['notes'])) {
            echo "  note: " . $test['notes'] . "\n";
        }
        $passed++;
        continue;
    }

    echo "  FAIL\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }

    echo "  legacy: " . ($legacyResult['url'] ?? 'n/a') . "\n";
    if ($legacyResult !== null) {
        echo "    status=" . $legacyResult['status'] . " body=" . truncate($legacyResult['body']) . "\n";
    }
    echo "  v2: " . $v2Result['url'] . "\n";
    echo "    status=" . $v2Result['status'] . " body=" . truncate($v2Result['body']) . "\n";

    $failed++;
    if ($failFast) {
        break;
    }
}

echo "\nSummary: passed=$passed failed=$failed total=" . ($passed + $failed) . "\n";
exit($failed > 0 ? 1 : 0);

function request(string $url, string $method, ?array $body, int $timeout, ?string $cookieHeader = null): array
{
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Request-Id: smoke-' . bin2hex(random_bytes(6)),
    ];
    if ($cookieHeader !== null && $cookieHeader !== '') {
        $headers[] = 'Cookie: ' . $cookieHeader;
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'url' => $url,
            'status' => 0,
            'body' => 'curl_error: ' . $error,
            'json' => null,
        ];
    }

    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $bodyText = substr($raw, $headerSize) ?: '';
    $json = json_decode($bodyText, true);

    return [
        'url' => $url,
        'status' => $status,
        'body' => $bodyText,
        'json' => is_array($json) ? $json : null,
    ];
}

function hasJsonPath(?array $data, string $path): bool
{
    if ($data === null) {
        return false;
    }

    if ($path === '') {
        return true;
    }

    $segments = explode('.', $path);
    $current = $data;

    foreach ($segments as $segment) {
        if (is_array($current) && array_key_exists($segment, $current)) {
            $current = $current[$segment];
            continue;
        }

        if (is_array($current) && ctype_digit($segment)) {
            $index = (int) $segment;
            if (array_key_exists($index, $current)) {
                $current = $current[$index];
                continue;
            }
        }

        return false;
    }

    return true;
}

function jsonShape(mixed $value): mixed
{
    if (is_array($value)) {
        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) {
            if ($value === []) {
                return ['list' => []];
            }
            return ['list' => jsonShape($value[0])];
        }

        $shape = [];
        ksort($value);
        foreach ($value as $key => $child) {
            $shape[(string) $key] = jsonShape($child);
        }
        return ['object' => $shape];
    }

    return gettype($value);
}

function truncate(string $value, int $max = 240): string
{
    $trimmed = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if (strlen($trimmed) <= $max) {
        return $trimmed;
    }
    return substr($trimmed, 0, $max) . '...';
}
