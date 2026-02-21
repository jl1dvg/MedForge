<?php

declare(strict_types=1);

$contract = require __DIR__ . '/http_smoke.contract.php';

$options = getopt('', [
    'module::',
    'endpoint::',
    'fail-fast',
    'allow-destructive',
    'legacy-base::',
    'v2-base::',
    'timeout::',
    'cookie::',
    'hc-number::',
    'billing-form-id::',
    'billing-hc-number::',
    'help',
]);

if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php tools/tests/http_smoke.php [--module=billing] [--endpoint=billing_no_facturados] [--fail-fast]\n";
    echo "       [--legacy-base=http://127.0.0.1:8080] [--v2-base=http://127.0.0.1:8081] [--timeout=20]\n";
    echo "       [--cookie='PHPSESSID=...'] [--hc-number=HC-REAL-001]\n";
    echo "       [--allow-destructive] [--billing-form-id=123] [--billing-hc-number=HC123]\n";
    exit(0);
}

$legacyBase = rtrim((string) ($options['legacy-base'] ?? $contract['defaults']['legacy_base_url']), '/');
$v2Base = rtrim((string) ($options['v2-base'] ?? $contract['defaults']['v2_base_url']), '/');
$timeout = max(1, (int) ($options['timeout'] ?? $contract['defaults']['timeout_seconds']));
$moduleFilter = isset($options['module']) ? trim((string) $options['module']) : null;
$endpointFilter = isset($options['endpoint']) ? trim((string) $options['endpoint']) : null;
$failFast = isset($options['fail-fast']);
$allowDestructive = isset($options['allow-destructive']);
$cookieHeader = isset($options['cookie']) ? trim((string) $options['cookie']) : null;
$isAuthenticated = $cookieHeader !== null && $cookieHeader !== '';

$fixtures = [
    'HC_NUMBER' => trim((string) ($options['hc-number'] ?? $contract['fixtures']['HC_NUMBER'] ?? 'HC-TEST-001')),
    'BILLING_FORM_ID' => trim((string) ($options['billing-form-id'] ?? $contract['fixtures']['BILLING_FORM_ID'] ?? '')),
    'BILLING_HC_NUMBER' => trim((string) ($options['billing-hc-number'] ?? $contract['fixtures']['BILLING_HC_NUMBER'] ?? '')),
];

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

if ($isAuthenticated && hasBillingDynamicFixtureNeed($tests) && ($fixtures['BILLING_FORM_ID'] === '' || $fixtures['BILLING_HC_NUMBER'] === '')) {
    $dynamicFixture = resolveDynamicBillingFixture($v2Base, $timeout, $cookieHeader);
    if (is_array($dynamicFixture)) {
        if ($fixtures['BILLING_FORM_ID'] === '') {
            $fixtures['BILLING_FORM_ID'] = $dynamicFixture['BILLING_FORM_ID'] ?? '';
        }
        if ($fixtures['BILLING_HC_NUMBER'] === '') {
            $fixtures['BILLING_HC_NUMBER'] = $dynamicFixture['BILLING_HC_NUMBER'] ?? '';
        }

        if ($fixtures['BILLING_FORM_ID'] !== '' && $fixtures['BILLING_HC_NUMBER'] !== '') {
            echo "Dynamic fixture: form_id={$fixtures['BILLING_FORM_ID']} hc_number={$fixtures['BILLING_HC_NUMBER']}\n";
        }
    }
}

$passed = 0;
$failed = 0;
$skipped = 0;

foreach ($tests as $test) {
    $test = applyFixtureTokens($test, $fixtures);

    $id = (string) $test['id'];
    $module = (string) $test['module'];
    $method = strtoupper((string) ($test['method'] ?? 'GET'));
    $compareMode = (string) resolveByAuth($test, 'compare_mode', $isAuthenticated, 'full');
    $bodyFormat = (string) resolveByAuth($test, 'body_format', $isAuthenticated, 'json');
    $requestBody = is_array($test['body'] ?? null) ? (array) $test['body'] : null;

    echo "\n[$module/$id] $method\n";

    if ((bool) ($test['destructive'] ?? false) && !$allowDestructive) {
        echo "  SKIP\n";
        echo "  note: Destructive test disabled. Re-run with --allow-destructive.\n";
        $skipped++;
        continue;
    }

    if ((bool) ($test['requires_dynamic_fixture'] ?? false) && ($fixtures['BILLING_FORM_ID'] === '' || $fixtures['BILLING_HC_NUMBER'] === '')) {
        echo "  SKIP\n";
        echo "  note: Dynamic billing fixture unavailable. Provide --billing-form-id/--billing-hc-number or ensure /v2/api/billing/no-facturados has data.\n";
        $skipped++;
        continue;
    }

    $sourceResult = null;
    $hydration = resolveByAuth($test, 'body_from_v2_json', $isAuthenticated, null);
    if (is_array($hydration)) {
        $sourcePath = (string) ($hydration['path'] ?? '');
        $sourceMethod = strtoupper((string) ($hydration['method'] ?? 'GET'));
        $sourceExpectStatus = (int) ($hydration['expect_status'] ?? 200);
        $sourceFields = is_array($hydration['fields'] ?? null) ? (array) $hydration['fields'] : [];
        $sourceRequired = (bool) ($hydration['required'] ?? true);
        $sourceBodyFormat = (string) ($hydration['body_format'] ?? 'json');

        if ($sourcePath !== '') {
            $sourceResult = request(
                $v2Base . $sourcePath,
                $sourceMethod,
                null,
                $timeout,
                $cookieHeader,
                $sourceBodyFormat
            );

            if ($sourceResult['status'] !== $sourceExpectStatus) {
                echo "  FAIL\n";
                echo "  - Body hydration source status expected $sourceExpectStatus, got {$sourceResult['status']}\n";
                echo "  source: " . $sourceResult['url'] . "\n";
                echo "    status=" . $sourceResult['status'] . " body=" . truncate($sourceResult['body']) . "\n";
                $failed++;
                if ($failFast) {
                    break;
                }
                continue;
            }

            if ($requestBody === null) {
                $requestBody = [];
            }

            $hydrationErrors = [];
            foreach ($sourceFields as $bodyKey => $jsonPath) {
                [$found, $value] = jsonPathRead($sourceResult['json'], (string) $jsonPath);
                if (!$found) {
                    if ($sourceRequired) {
                        $hydrationErrors[] = "Hydration source missing path: $jsonPath";
                    }
                    continue;
                }
                $requestBody[(string) $bodyKey] = $value;
            }

            if ($hydrationErrors !== []) {
                echo "  FAIL\n";
                foreach ($hydrationErrors as $error) {
                    echo "  - $error\n";
                }
                echo "  source: " . $sourceResult['url'] . "\n";
                echo "    status=" . $sourceResult['status'] . " body=" . truncate($sourceResult['body']) . "\n";
                $failed++;
                if ($failFast) {
                    break;
                }
                continue;
            }
        }
    }

    $legacyResult = null;
    if ($compareMode !== 'v2_only') {
        $legacyUrl = $legacyBase . (string) $test['legacy_path'];
        $legacyResult = request($legacyUrl, $method, $requestBody, $timeout, $cookieHeader, $bodyFormat);
    }

    $v2Url = $v2Base . (string) $test['v2_path'];
    $v2Result = request($v2Url, $method, $requestBody, $timeout, $cookieHeader, $bodyFormat);

    $errors = [];

    $expectV2Status = (int) resolveExpectedStatus($test, 'v2', $isAuthenticated);
    $expectLegacyStatus = (int) resolveExpectedStatus($test, 'legacy', $isAuthenticated);

    if ($v2Result['status'] !== $expectV2Status) {
        $errors[] = "V2 status expected $expectV2Status, got {$v2Result['status']}";
    }

    if ($compareMode !== 'v2_only' && $legacyResult !== null && $legacyResult['status'] !== $expectLegacyStatus) {
        $errors[] = "Legacy status expected $expectLegacyStatus, got {$legacyResult['status']}";
    }

    if ($compareMode !== 'v2_only' && $legacyResult !== null && $legacyResult['status'] !== $v2Result['status']) {
        $errors[] = "Status mismatch legacy={$legacyResult['status']} v2={$v2Result['status']}";
    }

    $requiredPaths = (array) resolveByAuth($test, 'required_json_paths', $isAuthenticated, []);
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

    $postCheck = resolveByAuth($test, 'post_check_v2', $isAuthenticated, null);
    if ($errors === [] && is_array($postCheck)) {
        $checkPath = (string) ($postCheck['path'] ?? '');
        if ($checkPath !== '') {
            $checkMethod = strtoupper((string) ($postCheck['method'] ?? 'GET'));
            $checkExpectStatus = (int) ($postCheck['expect_status'] ?? 200);
            $checkRequiredPaths = (array) ($postCheck['required_json_paths'] ?? []);
            $checkEquals = is_array($postCheck['assert_json_equals'] ?? null) ? (array) $postCheck['assert_json_equals'] : [];

            $checkResult = request(
                $v2Base . $checkPath,
                $checkMethod,
                null,
                $timeout,
                $cookieHeader,
                (string) ($postCheck['body_format'] ?? 'json')
            );

            if ($checkResult['status'] !== $checkExpectStatus) {
                $errors[] = "Post-check v2 status expected $checkExpectStatus, got {$checkResult['status']}";
            }

            foreach ($checkRequiredPaths as $path) {
                if (!hasJsonPath($checkResult['json'], (string) $path)) {
                    $errors[] = "Post-check v2 missing required path: $path";
                }
            }

            foreach ($checkEquals as $path => $expectedRaw) {
                $expected = resolveExpectedValue($expectedRaw, $requestBody ?? []);
                [$found, $actual] = jsonPathRead($checkResult['json'], (string) $path);
                if (!$found) {
                    $errors[] = "Post-check v2 missing path for equality assertion: $path";
                    continue;
                }

                if (!valuesEqual($actual, $expected)) {
                    $errors[] = sprintf(
                        'Post-check mismatch at %s expected=%s actual=%s',
                        (string) $path,
                        normalizePrintable($expected),
                        normalizePrintable($actual)
                    );
                }
            }
        }
    }

    if (empty($errors)) {
        echo "  PASS\n";
        echo "  status: " . $v2Result['status'] . "\n";
        $note = (string) resolveByAuth($test, 'notes', $isAuthenticated, '');
        if ($note !== '') {
            echo "  note: " . $note . "\n";
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

echo "\nSummary: passed=$passed failed=$failed skipped=$skipped total=" . ($passed + $failed + $skipped) . "\n";
exit($failed > 0 ? 1 : 0);

/**
 * @param array<int, array<string, mixed>> $tests
 */
function hasBillingDynamicFixtureNeed(array $tests): bool
{
    foreach ($tests as $test) {
        if ((string) ($test['module'] ?? '') !== 'billing') {
            continue;
        }
        if ((bool) ($test['requires_dynamic_fixture'] ?? false)) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{BILLING_FORM_ID:string,BILLING_HC_NUMBER:string}|null
 */
function resolveDynamicBillingFixture(string $v2Base, int $timeout, ?string $cookieHeader): ?array
{
    $url = rtrim($v2Base, '/') . '/v2/api/billing/no-facturados?start=0&length=1';
    $result = request($url, 'GET', null, $timeout, $cookieHeader);
    if ((int) ($result['status'] ?? 0) !== 200 || !is_array($result['json'] ?? null)) {
        return null;
    }

    [$found, $first] = jsonPathRead(is_array($result['json']) ? $result['json'] : null, 'data.0');
    if (!$found || !is_array($first)) {
        return null;
    }

    $formId = trim((string) ($first['form_id'] ?? ''));
    $hcNumber = trim((string) ($first['hc_number'] ?? ''));
    if ($formId === '' || $hcNumber === '') {
        return null;
    }

    return [
        'BILLING_FORM_ID' => $formId,
        'BILLING_HC_NUMBER' => $hcNumber,
    ];
}

/**
 * @param array<string, mixed> $test
 * @param array<string, string> $fixtures
 * @return array<string, mixed>
 */
function applyFixtureTokens(array $test, array $fixtures): array
{
    foreach ([
        'legacy_path',
        'v2_path',
        'body',
        'notes',
        'notes_auth',
        'body_from_v2_json',
        'body_from_v2_json_auth',
        'post_check_v2',
        'post_check_v2_auth',
    ] as $field) {
        if (array_key_exists($field, $test)) {
            $test[$field] = replaceTokens($test[$field], $fixtures);
        }
    }

    return $test;
}

/**
 * @param array<string, mixed> $test
 */
function resolveExpectedStatus(array $test, string $target, bool $isAuthenticated): int
{
    $specificKey = $target === 'legacy' ? 'expect_legacy_status' : 'expect_v2_status';
    $defaultValue = $test['expect_status'] ?? 200;

    if ($isAuthenticated && array_key_exists($specificKey . '_auth', $test)) {
        return (int) $test[$specificKey . '_auth'];
    }

    if (array_key_exists($specificKey, $test)) {
        return (int) $test[$specificKey];
    }

    if ($isAuthenticated && array_key_exists('expect_status_auth', $test)) {
        return (int) $test['expect_status_auth'];
    }

    return (int) $defaultValue;
}

/**
 * @param array<string, mixed> $test
 */
function resolveByAuth(array $test, string $field, bool $isAuthenticated, mixed $default): mixed
{
    if ($isAuthenticated && array_key_exists($field . '_auth', $test)) {
        return $test[$field . '_auth'];
    }

    if (array_key_exists($field, $test)) {
        return $test[$field];
    }

    return $default;
}

/**
 * @param mixed $value
 * @param array<string, string> $fixtures
 * @return mixed
 */
function replaceTokens(mixed $value, array $fixtures): mixed
{
    if (is_string($value)) {
        $replaced = $value;
        foreach ($fixtures as $token => $tokenValue) {
            $replaced = str_replace('{' . $token . '}', $tokenValue, $replaced);
        }
        return $replaced;
    }

    if (is_array($value)) {
        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = replaceTokens($item, $fixtures);
        }
        return $result;
    }

    return $value;
}

/**
 * @param array<string, mixed>|null $body
 */
function request(
    string $url,
    string $method,
    ?array $body,
    int $timeout,
    ?string $cookieHeader = null,
    string $bodyFormat = 'json'
): array
{
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
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
        if ($bodyFormat === 'form') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(flattenFormBody($body), '', '&', PHP_QUERY_RFC3986));
        } else {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
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

/**
 * @param array<string, mixed> $payload
 * @return array<string, scalar|null>
 */
function flattenFormBody(array $payload, string $prefix = ''): array
{
    $result = [];
    foreach ($payload as $key => $value) {
        $keyString = $prefix === '' ? (string) $key : $prefix . '[' . (string) $key . ']';

        if (is_array($value)) {
            $result += flattenFormBody($value, $keyString);
            continue;
        }

        if (is_bool($value)) {
            $result[$keyString] = $value ? '1' : '0';
            continue;
        }

        if (is_scalar($value)) {
            $result[$keyString] = $value;
            continue;
        }

        if ($value === null) {
            $result[$keyString] = '';
            continue;
        }

        $result[$keyString] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return $result;
}

/**
 * @param array<string, mixed> $body
 */
function resolveExpectedValue(mixed $expectedRaw, array $body): mixed
{
    if (is_string($expectedRaw) && str_starts_with($expectedRaw, '@body.')) {
        $bodyPath = substr($expectedRaw, 6);
        [$found, $value] = jsonPathRead($body, $bodyPath);
        if ($found) {
            return $value;
        }
    }

    return $expectedRaw;
}

function valuesEqual(mixed $actual, mixed $expected): bool
{
    if ($actual === $expected) {
        return true;
    }

    // Legacy forms frequently normalize null DB values to empty strings.
    if (($actual === null && $expected === '') || ($actual === '' && $expected === null)) {
        return true;
    }

    if ($actual === null || $expected === null) {
        return false;
    }

    return (string) $actual === (string) $expected;
}

/**
 * @return array{0: bool, 1: mixed}
 */
function jsonPathRead(?array $data, string $path): array
{
    if ($data === null) {
        return [false, null];
    }

    if ($path === '') {
        return [true, $data];
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

        return [false, null];
    }

    return [true, $current];
}

function normalizePrintable(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    }

    if (is_scalar($value) || $value === null) {
        return (string) $value;
    }

    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $encoded === false ? '[unprintable]' : $encoded;
}
