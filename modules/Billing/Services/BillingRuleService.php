<?php

namespace Modules\Billing\Services;

use Models\SettingsModel;

class BillingRuleService
{
    private array $rulesByType = [
        'code' => [],
        'affiliation' => [],
        'age' => [],
    ];

    public function __construct(private readonly SettingsModel $settingsModel)
    {
        $this->rulesByType['code'] = $this->decodeRules($this->settingsModel->getOption('billing_rules_code'), 'code');
        $this->rulesByType['affiliation'] = $this->decodeRules($this->settingsModel->getOption('billing_rules_affiliation'), 'affiliation');
        $this->rulesByType['age'] = $this->decodeRules($this->settingsModel->getOption('billing_rules_age'), 'age');
    }

    public function applyToPreview(array $preview, array $context): array
    {
        $trace = [];

        $preview['procedimientos'] = $this->applyRulesToItems(
            $preview['procedimientos'] ?? [],
            'procCodigo',
            'procPrecio',
            $context,
            $trace,
            'procedimientos'
        );

        $preview['insumos'] = $this->applyRulesToItems(
            $preview['insumos'] ?? [],
            'codigo',
            'precio',
            $context,
            $trace,
            'insumos'
        );

        $preview['derechos'] = $this->applyRulesToItems(
            $preview['derechos'] ?? [],
            'codigo',
            'precioAfiliacion',
            $context,
            $trace,
            'derechos'
        );

        $preview['anestesia'] = $this->applyRulesToItems(
            $preview['anestesia'] ?? [],
            'codigo',
            'precio',
            $context,
            $trace,
            'anestesia'
        );

        $preview['reglas_aplicadas'] = $trace;

        return $preview;
    }

    public function traceFromDetalle(array $itemsPorGrupo, array $context): array
    {
        $trace = [];

        foreach ($itemsPorGrupo as $grupo => $items) {
            $this->applyRulesToItems($items, 'proc_codigo', 'proc_precio', $context, $trace, $grupo, false);
        }

        return $trace;
    }

    private function applyRulesToItems(
        array $items,
        string $codeKey,
        string $priceKey,
        array $context,
        array &$trace,
        string $collection,
        bool $mutate = true
    ): array {
        $result = [];

        foreach ($items as $item) {
            $code = trim((string)($item[$codeKey] ?? ''));
            $price = (float)($item[$priceKey] ?? 0);
            $rule = $this->findRuleForContext($code, $context);

            if ($rule === null) {
                $result[] = $item;
                continue;
            }

            $applied = $this->applyRule($item, $rule, $priceKey, $mutate);

            $trace[] = [
                'coleccion' => $collection,
                'codigo' => $code,
                'accion' => $rule['action'],
                'valor_original' => $price,
                'valor_final' => $applied['precio_final'],
                'regla' => $rule,
            ];

            if ($mutate && $applied['excluir']) {
                continue;
            }

            if ($mutate) {
                $item[$priceKey] = $applied['precio_final'];
            }

            $result[] = $item;
        }

        return $result;
    }

    private function applyRule(array $item, array $rule, string $priceKey, bool $mutate): array
    {
        $price = (float)($item[$priceKey] ?? 0);
        $finalPrice = $price;
        $exclude = false;

        switch ($rule['action']) {
            case 'tarifa':
                $finalPrice = (float)($rule['value'] ?? 0);
                break;
            case 'descuento':
                $porcentaje = max(0.0, (float)($rule['value'] ?? 0));
                $finalPrice = round($price * (1 - ($porcentaje / 100)), 2);
                break;
            case 'exclusion':
                if ($mutate) {
                    $exclude = true;
                }
                $finalPrice = 0.0;
                break;
        }

        return [
            'precio_final' => $finalPrice,
            'excluir' => $exclude,
        ];
    }

    private function findRuleForContext(?string $code, array $context): ?array
    {
        $affiliation = strtoupper(trim((string)($context['afiliacion'] ?? '')));
        $age = isset($context['edad']) ? (int)$context['edad'] : null;

        foreach ($this->rulesByType['code'] as $rule) {
            if ($code !== '' && strtoupper($rule['code']) === strtoupper($code)) {
                return $rule;
            }
        }

        foreach ($this->rulesByType['affiliation'] as $rule) {
            if ($affiliation !== '' && strtoupper($rule['affiliation']) === $affiliation) {
                return $rule;
            }
        }

        if ($age !== null) {
            foreach ($this->rulesByType['age'] as $rule) {
                $min = $rule['min_age'];
                $max = $rule['max_age'];

                $isAboveMin = $min === null || $age >= $min;
                $isBelowMax = $max === null || $age <= $max;

                if ($isAboveMin && $isBelowMax) {
                    return $rule;
                }
            }
        }

        return null;
    }

    private function decodeRules(?string $json, string $type): array
    {
        if (!$json) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $rule) {
            if (!is_array($rule) || empty($rule['action'])) {
                continue;
            }

            $normalized[] = $this->normalizeRule($rule, $type);
        }

        return $normalized;
    }

    private function normalizeRule(array $rule, string $type): array
    {
        $base = [
            'id' => $rule['id'] ?? uniqid($type . '_', true),
            'action' => $rule['action'],
            'value' => isset($rule['value']) ? (float)$rule['value'] : null,
            'notes' => trim((string)($rule['notes'] ?? '')),
        ];

        return match ($type) {
            'code' => $base + [
                'type' => 'code',
                'code' => trim((string)($rule['code'] ?? '')),
            ],
            'affiliation' => $base + [
                'type' => 'affiliation',
                'affiliation' => trim((string)($rule['affiliation'] ?? '')),
            ],
            'age' => $base + [
                'type' => 'age',
                'min_age' => isset($rule['min_age']) && $rule['min_age'] !== '' ? (int)$rule['min_age'] : null,
                'max_age' => isset($rule['max_age']) && $rule['max_age'] !== '' ? (int)$rule['max_age'] : null,
            ],
            default => $base,
        };
    }
}
