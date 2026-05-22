<?php

namespace App\Modules\MailTemplates\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CoberturaMailTemplateService
{
    private const CONTEXT = 'cobertura';

    private const RULES = [
        [
            'key' => 'msp_informe',
            'priority' => 100,
            'exact' => [
                'msp',
                'ministerio de salud',
                'salud publica',
                'red publica',
            ],
            'contains' => [
                'msp',
                'ministerio de salud',
                'salud publica',
                'red publica',
                'red publica integral',
                'red publica integral de salud',
            ],
        ],
        [
            'key' => 'isspol_informe',
            'priority' => 90,
            'exact' => ['isspol'],
            'contains' => ['isspol', 'policia', 'policia nacional', 'seguro policial'],
        ],
        [
            'key' => 'issfa_informe',
            'priority' => 80,
            'exact' => ['issfa'],
            'contains' => ['issfa', 'ffaa', 'fuerzas armadas'],
        ],
        [
            'key' => 'iess_cive',
            'priority' => 10,
            'exact' => [
                'contribuyente voluntario',
                'conyuge',
                'conyuge pensionista',
                'seguro campesino',
                'seguro general por montepio',
                'seguro general tiempo parcial',
                'iess',
                'hijos dependientes',
                'seguro campesino jubilado',
                'seguro general',
                'seguro general jubilado',
            ],
            'contains' => [
                'contribuyente voluntario',
                'conyuge',
                'conyuge pensionista',
                'seguro campesino',
                'seguro general por montepio',
                'seguro general tiempo parcial',
                'iess',
                'hijos dependientes',
                'seguro campesino jubilado',
                'seguro general',
                'seguro general jubilado',
            ],
        ],
    ];

    private const IMAGE_RULES = [
        [
            'key' => 'solicitud_imagenes_msp',
            'priority' => 100,
            'exact' => [
                'msp',
                'ministerio de salud',
                'salud publica',
                'red publica',
            ],
            'contains' => [
                'msp',
                'ministerio de salud',
                'salud publica',
                'red publica',
                'red publica integral',
                'red publica integral de salud',
            ],
        ],
        [
            'key' => 'solicitud_imagenes_isspol',
            'priority' => 90,
            'exact' => ['isspol'],
            'contains' => ['isspol', 'policia', 'policia nacional', 'seguro policial'],
        ],
        [
            'key' => 'solicitud_imagenes_issfa',
            'priority' => 80,
            'exact' => ['issfa'],
            'contains' => ['issfa', 'ffaa', 'fuerzas armadas'],
        ],
        [
            'key' => 'solicitud_imagenes_iess',
            'priority' => 10,
            'exact' => [
                'contribuyente voluntario',
                'conyuge',
                'conyuge pensionista',
                'seguro campesino',
                'seguro general por montepio',
                'seguro general tiempo parcial',
                'iess',
                'hijos dependientes',
                'seguro campesino jubilado',
                'seguro general',
                'seguro general jubilado',
            ],
            'contains' => [
                'contribuyente voluntario',
                'conyuge',
                'conyuge pensionista',
                'seguro campesino',
                'seguro general por montepio',
                'seguro general tiempo parcial',
                'iess',
                'hijos dependientes',
                'seguro campesino jubilado',
                'seguro general',
                'seguro general jubilado',
            ],
        ],
    ];

    public function resolveTemplateKey(string $afiliacion): ?string
    {
        $normalized = $this->normalize($afiliacion);
        if ($normalized === '') {
            return null;
        }

        $rules = $this->sortedRules();
        foreach ($rules as $rule) {
            foreach ($rule['exact'] as $matcher) {
                if ($normalized === $matcher) {
                    return $rule['key'];
                }
            }
        }

        foreach ($rules as $rule) {
            foreach ($rule['contains'] as $matcher) {
                if ($matcher !== '' && str_contains($normalized, $matcher)) {
                    return $rule['key'];
                }
            }
        }

        return null;
    }

    public function resolveImagenesTemplateKey(string $afiliacion): ?string
    {
        $normalized = $this->normalize($afiliacion);
        if ($normalized === '') {
            return null;
        }

        $rules = $this->sortedRules(self::IMAGE_RULES);
        foreach ($rules as $rule) {
            foreach ($rule['exact'] as $matcher) {
                if ($normalized === $matcher) {
                    return $rule['key'];
                }
            }
        }

        foreach ($rules as $rule) {
            foreach ($rule['contains'] as $matcher) {
                if ($matcher !== '' && str_contains($normalized, $matcher)) {
                    return $rule['key'];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTemplateForAffiliation(string $afiliacion): ?array
    {
        $key = $this->resolveTemplateKey($afiliacion);
        if ($key === null) {
            return null;
        }

        return $this->findEnabledByKey(self::CONTEXT, $key);
    }

    public function hasEnabledTemplate(string $templateKey): bool
    {
        return $this->findEnabledByKey(self::CONTEXT, $templateKey) !== null;
    }

    /**
     * @param array<string, mixed> $template
     * @param array<string, string> $variables
     * @return array{subject:string,body_html:?string,body_text:?string,recipients_to:?string,recipients_cc:?string,template_key:string}
     */
    public function hydrateTemplate(array $template, array $variables): array
    {
        $subjectTemplate = (string) ($template['subject_template'] ?? '');
        $bodyHtmlTemplate = $template['body_template_html'] ?? null;
        $bodyTextTemplate = $template['body_template_text'] ?? null;

        $subject = $this->replaceVariables($subjectTemplate, $variables, false);
        $bodyHtml = $bodyHtmlTemplate !== null
            ? $this->replaceVariables((string) $bodyHtmlTemplate, $variables, true)
            : null;
        $bodyText = $bodyTextTemplate !== null
            ? $this->replaceVariables((string) $bodyTextTemplate, $variables, false)
            : null;

        return [
            'template_key' => (string) ($template['template_key'] ?? ''),
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'recipients_to' => $template['recipients_to'] ?? null,
            'recipients_cc' => $template['recipients_cc'] ?? null,
        ];
    }

    /**
     * @param array<string, string> $payload
     * @return array<string, string>
     */
    public function buildVariables(array $payload): array
    {
        $pdfUrl = trim((string) ($payload['PDF_URL'] ?? ''));
        return [
            '{PACIENTE}' => $payload['PACIENTE'] ?? 'Paciente',
            '{HC}' => $payload['HC'] ?? '—',
            '{PROC}' => $payload['PROC'] ?? 'Procedimiento solicitado',
            '{PLAN}' => $payload['PLAN'] ?? 'Plan de consulta',
            '{FORM_ID}' => $payload['FORM_ID'] ?? '—',
            '{PDF_URL}' => $pdfUrl,
            '{EXAMENES_PENDIENTES}' => $payload['EXAMENES_PENDIENTES'] ?? '—',
            '{EXAMENES_PENDIENTES_HTML}' => $payload['EXAMENES_PENDIENTES_HTML'] ?? ($payload['EXAMENES_PENDIENTES'] ?? '—'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(): array
    {
        return DB::table('mail_templates')
            ->where('context', self::CONTEXT)
            ->orderBy('template_key')
            ->get()
            ->map(static fn($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findTemplate(string $templateKey): ?array
    {
        $row = DB::table('mail_templates')
            ->where('context', self::CONTEXT)
            ->where('template_key', $templateKey)
            ->first();

        return $row !== null ? (array) $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveTemplate(string $templateKey, array $data, int $userId): void
    {
        $existing = $this->findTemplate($templateKey);

        $payload = [
            'name' => $data['name'] ?? $templateKey,
            'subject_template' => $data['subject_template'] ?? null,
            'body_template_html' => $data['body_template_html'] ?? null,
            'body_template_text' => $data['body_template_text'] ?? null,
            'recipients_to' => $data['recipients_to'] ?? null,
            'recipients_cc' => $data['recipients_cc'] ?? null,
            'enabled' => (int) ($data['enabled'] ?? 0),
            'updated_by' => $userId > 0 ? $userId : null,
        ];

        try {
            if ($existing !== null) {
                DB::table('mail_templates')
                    ->where('context', self::CONTEXT)
                    ->where('template_key', $templateKey)
                    ->update($payload);
            } else {
                DB::table('mail_templates')->insert(array_merge($payload, [
                    'context' => self::CONTEXT,
                    'template_key' => $templateKey,
                ]));
            }
        } catch (\Throwable $e) {
            Log::error('MailTemplates: failed to save template', ['key' => $templateKey, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findEnabledByKey(string $context, string $templateKey): ?array
    {
        $row = DB::table('mail_templates')
            ->where('context', $context)
            ->where('template_key', $templateKey)
            ->where('enabled', 1)
            ->first();

        return $row !== null ? (array) $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sortedRules(?array $rules = null): array
    {
        $rules = $rules ?? self::RULES;
        usort($rules, static fn(array $a, array $b): int => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        $normalized = [];
        foreach ($rules as $rule) {
            $normalized[] = [
                'key' => $rule['key'],
                'priority' => $rule['priority'] ?? 0,
                'exact' => $this->normalizeMatchers($rule['exact'] ?? []),
                'contains' => $this->normalizeMatchers($rule['contains'] ?? []),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $list
     * @return array<int, string>
     */
    private function normalizeMatchers(array $list): array
    {
        $output = [];
        foreach ($list as $item) {
            $normalized = $this->normalize($item);
            if ($normalized !== '') {
                $output[] = $normalized;
            }
        }
        return $output;
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'Á' => 'a',
            'É' => 'e',
            'Í' => 'i',
            'Ó' => 'o',
            'Ú' => 'u',
            'ñ' => 'n',
            'Ñ' => 'n',
        ]);

        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param array<string, string> $variables
     */
    private function replaceVariables(string $template, array $variables, bool $escape): string
    {
        if ($template === '') {
            return '';
        }

        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements[$key] = $escape
                ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                : $value;
        }

        return strtr($template, $replacements);
    }
}
