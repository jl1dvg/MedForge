<?php

namespace App\Modules\Agenda\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AgendaReadController
{
    public function index(Request $request): JsonResponse|View|RedirectResponse|Response
    {
        $shouldReturnJson = $this->shouldReturnJson($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($shouldReturnJson) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $filters = $this->resolveFilters($request);

        try {
            $payload = $this->buildAgendaPayload($filters);
        } catch (\Throwable $e) {
            if ($shouldReturnJson) {
                return response()->json(['error' => 'No se pudo cargar agenda', 'detail' => $e->getMessage()], 500);
            }

            return response()->view('agenda.v2-index', [
                'pageTitle' => 'Agenda',
                'currentUser' => LegacyCurrentUser::resolve($request),
                'agendaRows' => [],
                'agendaMeta' => $this->emptyMeta($filters),
                'loadError' => 'No se pudo cargar la agenda con los filtros solicitados.',
            ], 500);
        }

        if (!$shouldReturnJson) {
            return view('agenda.v2-index', [
                'pageTitle' => 'Agenda',
                'currentUser' => LegacyCurrentUser::resolve($request),
                'agendaRows' => $payload['data'],
                'agendaMeta' => $payload['meta'],
                'loadError' => null,
            ]);
        }

        return response()->json($payload);
    }

    public function visita(Request $request, int $visitaId): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        if ($visitaId <= 0) {
            return response()->json(['error' => 'Encuentro no encontrado'], 404);
        }

        try {
            $visita = DB::selectOne(
                "SELECT v.id, v.hc_number, v.fecha_visita, v.hora_llegada, v.usuario_registro,
                        pd.fname, pd.mname, pd.lname, pd.lname2, pd.afiliacion, pd.celular, pd.fecha_nacimiento
                 FROM visitas v
                 LEFT JOIN patient_data pd ON pd.hc_number = v.hc_number
                 WHERE v.id = ? LIMIT 1",
                [$visitaId]
            );

            if (!$visita) {
                return response()->json(['error' => 'Encuentro no encontrado'], 404);
            }

            $procedimientos = DB::select(
                "SELECT pp.id, pp.form_id, pp.procedimiento_proyectado AS procedimiento, pp.doctor, pp.fecha, pp.hora,
                        pp.estado_agenda, pp.afiliacion, pp.sede_departamento, pp.id_sede, v.hora_llegada,
                        COALESCE(DATE(pp.fecha), v.fecha_visita) AS fecha_agenda
                 FROM procedimiento_proyectado pp
                 LEFT JOIN visitas v ON v.id = pp.visita_id
                 WHERE pp.visita_id = ?
                 ORDER BY fecha_agenda ASC, pp.hora ASC, pp.fecha ASC, v.hora_llegada ASC, pp.form_id ASC",
                [$visitaId]
            );

            return response()->json([
                'data' => [
                    'visita' => $visita,
                    'procedimientos' => $procedimientos,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'No se pudo cargar la visita', 'detail' => $e->getMessage()], 500);
        }
    }

    private function shouldReturnJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('v2/api/*');
    }

    /**
     * @return array{
     *     fecha_inicio:string,
     *     fecha_fin:string,
     *     doctor:string,
     *     estado:string,
     *     sede:string,
     *     solo_con_visita:bool
     * }
     */
    private function resolveFilters(Request $request): array
    {
        $start = trim((string) $request->query('fecha_inicio', ''));
        $end = trim((string) $request->query('fecha_fin', ''));

        if ($start !== '' && $end === '') {
            $end = $start;
        } elseif ($end !== '' && $start === '') {
            $start = $end;
        }

        if ($start !== '' && $end !== '' && $end < $start) {
            [$start, $end] = [$end, $start];
        }

        return [
            'fecha_inicio' => $start,
            'fecha_fin' => $end,
            'doctor' => trim((string) $request->query('doctor', '')),
            'estado' => trim((string) $request->query('estado', '')),
            'sede' => trim((string) $request->query('sede', '')),
            'solo_con_visita' => (string) $request->query('solo_con_visita', '0') !== '0',
        ];
    }

    /**
     * @param array{
     *     fecha_inicio:string,
     *     fecha_fin:string,
     *     doctor:string,
     *     estado:string,
     *     sede:string,
     *     solo_con_visita:bool
     * } $filters
     * @return array{data:array<int,object>,meta:array<string,mixed>}
     */
    private function buildAgendaPayload(array $filters): array
    {
        $doctorCatalog = $this->buildDoctorCatalog();

        $sql = "SELECT
                    pp.id, pp.form_id, pp.hc_number,
                    TRIM(CONCAT_WS(' ', pd.fname, pd.mname, pd.lname, pd.lname2)) AS paciente,
                    pp.procedimiento_proyectado AS procedimiento,
                    pp.doctor, pp.fecha, pp.hora, pp.estado_agenda,
                    pp.sede_departamento, pp.id_sede, pp.afiliacion,
                    CASE
                        WHEN EXISTS (
                            SELECT 1
                            FROM consulta_data cd
                            WHERE cd.form_id = pp.form_id
                              AND cd.hc_number = pp.hc_number
                        ) THEN 1
                        ELSE 0
                    END AS tiene_consulta,
                    v.id AS visita_id, v.fecha_visita, v.hora_llegada,
                    COALESCE(DATE(pp.fecha), v.fecha_visita) AS fecha_agenda
                FROM procedimiento_proyectado pp
                LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
                LEFT JOIN visitas v ON v.id = pp.visita_id
                WHERE 1=1";
        $bind = [];

        if ($filters['fecha_inicio'] !== '' && $filters['fecha_fin'] !== '') {
            $sql .= " AND COALESCE(DATE(pp.fecha), v.fecha_visita) BETWEEN ? AND ?";
            $bind[] = $filters['fecha_inicio'];
            $bind[] = $filters['fecha_fin'];
        }

        if ($filters['solo_con_visita']) {
            $sql .= " AND pp.visita_id IS NOT NULL";
        }
        if ($filters['doctor'] !== '') {
            $doctorVariants = $doctorCatalog['variants_by_key'][$filters['doctor']] ?? [];
            if ($doctorVariants === []) {
                $sql .= ' AND 1 = 0';
            } else {
                $placeholders = implode(', ', array_fill(0, count($doctorVariants), '?'));
                $sql .= " AND TRIM(COALESCE(pp.doctor, '')) IN ($placeholders)";
                array_push($bind, ...$doctorVariants);
            }
        }
        if ($filters['estado'] !== '') {
            $sql .= " AND pp.estado_agenda = ?";
            $bind[] = $filters['estado'];
        }
        if ($filters['sede'] !== '') {
            $sql .= " AND (pp.id_sede = ? OR pp.sede_departamento = ?)";
            $bind[] = $filters['sede'];
            $bind[] = $filters['sede'];
        }

        $sql .= " ORDER BY fecha_agenda ASC, pp.hora ASC, pp.fecha ASC, v.hora_llegada ASC, pp.form_id ASC LIMIT 1000";

        $rows = DB::select($sql, $bind);
        $rows = $this->decorateAgendaRows($rows, $doctorCatalog);
        $estados = DB::select("SELECT DISTINCT estado_agenda FROM procedimiento_proyectado WHERE estado_agenda IS NOT NULL AND estado_agenda != '' ORDER BY estado_agenda");

        return [
            'data' => $rows,
            'meta' => [
                'count' => count($rows),
                'filters' => [
                    'fecha_inicio' => $filters['fecha_inicio'],
                    'fecha_fin' => $filters['fecha_fin'],
                    'doctor' => $filters['doctor'] !== '' ? $filters['doctor'] : null,
                    'estado' => $filters['estado'] !== '' ? $filters['estado'] : null,
                    'sede' => $filters['sede'] !== '' ? $filters['sede'] : null,
                    'solo_con_visita' => $filters['solo_con_visita'],
                ],
                'estados_disponibles' => array_map(fn ($r) => (string) ($r->estado_agenda ?? ''), $estados),
                'doctores_disponibles' => $doctorCatalog['options'],
            ],
        ];
    }

    /**
     * @param array{
     *     fecha_inicio:string,
     *     fecha_fin:string,
     *     doctor:string,
     *     estado:string,
     *     sede:string,
     *     solo_con_visita:bool
     * } $filters
     * @return array<string,mixed>
     */
    private function emptyMeta(array $filters): array
    {
        return [
            'count' => 0,
            'filters' => [
                'fecha_inicio' => $filters['fecha_inicio'],
                'fecha_fin' => $filters['fecha_fin'],
                'doctor' => $filters['doctor'] !== '' ? $filters['doctor'] : null,
                'estado' => $filters['estado'] !== '' ? $filters['estado'] : null,
                'sede' => $filters['sede'] !== '' ? $filters['sede'] : null,
                'solo_con_visita' => $filters['solo_con_visita'],
            ],
            'estados_disponibles' => [],
            'doctores_disponibles' => [],
        ];
    }

    /**
     * @return array{
     *     options:array<int,array{value:string,label:string}>,
     *     variants_by_key:array<string,array<int,string>>,
     *     lookup_by_normalized_raw:array<string,array{key:string,label:string}>
     * }
     */
    private function buildDoctorCatalog(): array
    {
        $rawDoctors = DB::select(
            "SELECT DISTINCT TRIM(doctor) AS doctor
             FROM procedimiento_proyectado
             WHERE doctor IS NOT NULL AND TRIM(doctor) != ''
             ORDER BY doctor ASC"
        );

        $canonicalByReference = $this->buildDoctorCanonicalDirectory();
        $groups = [];

        foreach ($rawDoctors as $row) {
            $rawDoctor = $this->normalizeWhitespace((string) ($row->doctor ?? ''));
            if ($rawDoctor === '') {
                continue;
            }

            $normalizedRaw = $this->normalizeDoctorReference($rawDoctor);
            $mapped = $canonicalByReference[$normalizedRaw] ?? null;
            if (!is_array($mapped) && !$this->isDoctorLikeValue($rawDoctor)) {
                continue;
            }

            $groupKey = is_array($mapped) ? (string) ($mapped['key'] ?? '') : $this->doctorCanonicalKey($rawDoctor);
            if ($groupKey === '') {
                $groupKey = $normalizedRaw;
            }

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'label' => is_array($mapped)
                        ? (string) ($mapped['label'] ?? $this->formatDoctorLabel($rawDoctor))
                        : $this->formatDoctorLabel($rawDoctor),
                    'mapped' => is_array($mapped),
                    'variants' => [],
                    'lookup' => [],
                ];
            } elseif (is_array($mapped) && !$groups[$groupKey]['mapped']) {
                $groups[$groupKey]['label'] = (string) ($mapped['label'] ?? $groups[$groupKey]['label']);
                $groups[$groupKey]['mapped'] = true;
            }

            $groups[$groupKey]['variants'][$rawDoctor] = true;
            $groups[$groupKey]['lookup'][$normalizedRaw] = [
                'key' => $groupKey,
                'label' => (string) $groups[$groupKey]['label'],
            ];
        }

        $options = [];
        $variantsByKey = [];
        $lookupByNormalizedRaw = [];

        foreach ($groups as $groupKey => $group) {
            $variants = array_keys((array) ($group['variants'] ?? []));
            sort($variants, SORT_NATURAL | SORT_FLAG_CASE);

            $label = (string) ($group['label'] ?? '');
            if ($label === '') {
                $label = $this->choosePreferredDoctorLabel($variants);
            }

            foreach ($variants as $variant) {
                $lookupByNormalizedRaw[$this->normalizeDoctorReference($variant)] = [
                    'key' => (string) $groupKey,
                    'label' => $label,
                ];
            }

            $variantsByKey[(string) $groupKey] = $variants;
            $options[] = [
                'value' => (string) $groupKey,
                'label' => $label,
            ];
        }

        usort($options, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return [
            'options' => $options,
            'variants_by_key' => $variantsByKey,
            'lookup_by_normalized_raw' => $lookupByNormalizedRaw,
        ];
    }

    /**
     * @return array<string,array{key:string,label:string}>
     */
    private function buildDoctorCanonicalDirectory(): array
    {
        $directory = [];

        try {
            $users = DB::select(
                "SELECT
                    TRIM(COALESCE(nombre, '')) AS nombre,
                    TRIM(COALESCE(nombre_norm, '')) AS nombre_norm,
                    TRIM(COALESCE(nombre_norm_rev, '')) AS nombre_norm_rev
                 FROM users
                 WHERE nombre IS NOT NULL AND TRIM(nombre) != ''"
            );
        } catch (\Throwable) {
            return [];
        }

        foreach ($users as $user) {
            $label = $this->formatDoctorLabel((string) ($user->nombre ?? ''));
            if ($label === '') {
                continue;
            }

            $key = $this->doctorCanonicalKey($label);
            if ($key === '') {
                continue;
            }

            $candidates = [
                $label,
                (string) ($user->nombre_norm ?? ''),
                (string) ($user->nombre_norm_rev ?? ''),
            ];

            foreach ($candidates as $candidate) {
                $normalized = $this->normalizeDoctorReference($candidate);
                if ($normalized === '') {
                    continue;
                }

                $directory[$normalized] = [
                    'key' => $key,
                    'label' => $label,
                ];
            }
        }

        return $directory;
    }

    /**
     * @param array<int,object> $rows
     * @param array{
     *     lookup_by_normalized_raw:array<string,array{key:string,label:string}>
     * } $doctorCatalog
     * @return array<int,object>
     */
    private function decorateAgendaRows(array $rows, array $doctorCatalog): array
    {
        $lookup = (array) ($doctorCatalog['lookup_by_normalized_raw'] ?? []);

        foreach ($rows as $row) {
            $rawDoctor = $this->normalizeWhitespace((string) ($row->doctor ?? ''));
            $normalizedRaw = $this->normalizeDoctorReference($rawDoctor);
            $resolved = $lookup[$normalizedRaw] ?? null;

            $row->doctor_display = is_array($resolved)
                ? (string) ($resolved['label'] ?? $rawDoctor)
                : $rawDoctor;
            $row->doctor_filter_key = is_array($resolved)
                ? (string) ($resolved['key'] ?? $this->doctorCanonicalKey($rawDoctor))
                : $this->doctorCanonicalKey($rawDoctor);
        }

        return $rows;
    }

    private function choosePreferredDoctorLabel(array $variants): string
    {
        if ($variants === []) {
            return '';
        }

        usort($variants, static function (string $left, string $right): int {
            $lengthComparison = strlen($right) <=> strlen($left);
            if ($lengthComparison !== 0) {
                return $lengthComparison;
            }

            return strcasecmp($left, $right);
        });

        return $this->formatDoctorLabel($variants[0]);
    }

    private function doctorCanonicalKey(string $value): string
    {
        $normalized = $this->normalizeDoctorReference($value);
        if ($normalized === '') {
            return '';
        }

        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        $tokens = array_values(array_filter($tokens, static fn ($token): bool => $token !== ''));
        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }

    private function normalizeDoctorReference(string $value): string
    {
        $normalized = $this->normalizeWhitespace($value);
        if ($normalized === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if (is_string($ascii) && $ascii !== '') {
            $normalized = $ascii;
        }

        $normalized = mb_strtoupper($normalized, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;

        return $this->normalizeWhitespace($normalized);
    }

    private function normalizeWhitespace(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));
        return $normalized !== null ? trim($normalized) : trim($value);
    }

    private function formatDoctorLabel(string $value): string
    {
        $normalized = $this->normalizeWhitespace($value);
        if ($normalized === '') {
            return '';
        }

        $formatted = mb_convert_case(mb_strtolower($normalized, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $tokens = preg_split('/\s+/u', $formatted) ?: [];
        $lowercaseParticles = ['De', 'Del', 'La', 'Las', 'Los', 'Y', 'Da', 'Das', 'Do', 'Dos'];
        $upperTokens = ['SNS'];

        foreach ($tokens as $index => $token) {
            if ($index > 0 && in_array($token, $lowercaseParticles, true)) {
                $tokens[$index] = mb_strtolower($token, 'UTF-8');
                continue;
            }

            if (in_array(mb_strtoupper($token, 'UTF-8'), $upperTokens, true)) {
                $tokens[$index] = mb_strtoupper($token, 'UTF-8');
            }
        }

        return implode(' ', $tokens);
    }

    private function isDoctorLikeValue(string $value): bool
    {
        $rawNormalized = mb_strtoupper($this->normalizeWhitespace($value), 'UTF-8');
        if ($rawNormalized === '') {
            return false;
        }

        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $rawNormalized) === 1) {
            return false;
        }

        if (preg_match('/\d/', $rawNormalized) === 1) {
            return false;
        }

        $blockedPatterns = [
            '/\bRETINOGRAFIA\b/u',
            '/\bNERVIO\s+OPTICO\b/u',
            '/\bAMBOS\s+OJOS\b/u',
            '/\b(DERECHO|IZQUIERDO)\b/u',
            '/\b(AO|OD|OI)\b/u',
            '/\bCONSULTA\b/u',
            '/\bPROCEDIMIENTO\b/u',
            '/\bDOCTOR\s+EJEMPLO\b/u',
            '/^HC\b/u',
            '/\bOPTOMETRIA\b/u',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $rawNormalized) === 1) {
                return false;
            }
        }

        if (str_contains($rawNormalized, '(') || str_contains($rawNormalized, ')') || str_contains($rawNormalized, '-')) {
            return false;
        }

        $normalized = $this->normalizeDoctorReference($value);
        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        return count(array_filter($tokens, static fn ($token): bool => $token !== '')) >= 2;
    }
}
