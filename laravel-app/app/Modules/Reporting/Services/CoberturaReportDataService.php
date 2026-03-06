<?php

namespace App\Modules\Reporting\Services;

use Illuminate\Support\Facades\DB;
use PDO;

class CoberturaReportDataService
{
    private PDO $db;

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    /** @var array<string, array<string, bool>> */
    private array $tableColumnsCache = [];

    /** @var array<string, bool>|null */
    private ?array $solicitudColumns = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connection()->getPdo();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCoberturaData(string $formId, string $hcNumber): array
    {
        $formId = trim($formId);
        $hcNumber = trim($hcNumber);

        if ($formId === '' || $hcNumber === '') {
            return [];
        }

        $contexto = $this->resolverContextoCobertura($formId, $hcNumber);
        $resolvedFormId = $contexto['form_id'];
        $resolvedHc = $contexto['hc_number'];

        $solicitudId = $this->obtenerSolicitudIdPorFormHc($resolvedFormId, $resolvedHc);
        if ($solicitudId === null) {
            $solicitudId = $this->obtenerSolicitudIdPorFormId($resolvedFormId);
        }

        $derivacion = $this->ensureDerivacion($resolvedFormId, $resolvedHc, $solicitudId);

        $solicitud = $this->obtenerDatosYCirujanoSolicitud($resolvedFormId, $resolvedHc);
        if ($solicitud === []) {
            $solicitud = $this->obtenerDatosYCirujanoSolicitudPorFormId($resolvedFormId);
        }
        if ($solicitud === []) {
            $solicitud = $this->construirSolicitudFallback($resolvedFormId, $resolvedHc, $contexto);
        }

        $consulta = $this->obtenerConsultaDeSolicitud($resolvedFormId);
        if ($consulta === []) {
            $consulta = $this->obtenerConsultaPorFormHc($resolvedFormId, $resolvedHc);
        }
        if ($consulta === []) {
            $consulta = $this->construirConsultaFallback($resolvedFormId, $resolvedHc, $contexto, $solicitud, $derivacion);
        }

        $paciente = $this->obtenerPaciente($resolvedHc);
        if ($paciente === [] && $resolvedHc !== $hcNumber) {
            $paciente = $this->obtenerPaciente($hcNumber);
        }

        $diagnostico = $this->obtenerDxDeSolicitud($resolvedFormId);
        if ($diagnostico === []) {
            $diagnostico = $this->construirDiagnosticoFallback($contexto, $derivacion);
        }

        return [
            'derivacion' => $derivacion,
            'solicitud' => $solicitud,
            'paciente' => $paciente,
            'diagnostico' => $diagnostico,
            'consulta' => $consulta,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ensureDerivacion(string $formId, string $hcNumber, ?int $solicitudId = null): ?array
    {
        $seleccion = null;

        if ($solicitudId !== null && $solicitudId > 0) {
            $seleccion = $this->obtenerDerivacionPreseleccion($solicitudId);
        }

        if (!$seleccion) {
            $seleccion = $this->obtenerDerivacionPreseleccionPorFormHc($formId, $hcNumber);
        }

        $lookupFormId = trim((string) ($seleccion['derivacion_pedido_id'] ?? $formId));
        if ($lookupFormId === '') {
            $lookupFormId = $formId;
        }

        $hasSelection = !empty($seleccion['derivacion_pedido_id']);

        if ($hasSelection) {
            $derivacion = $this->obtenerDerivacionPorFormId($lookupFormId);
            if (is_array($derivacion) && $derivacion !== []) {
                return $derivacion;
            }
        } else {
            $derivacion = $this->obtenerDerivacionPorFormId($formId);
            if (is_array($derivacion) && $derivacion !== []) {
                return $derivacion;
            }
        }

        if ($lookupFormId !== $formId) {
            $fallback = $this->obtenerDerivacionPorFormId($lookupFormId);
            if (is_array($fallback) && $fallback !== []) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerDerivacionPorFormId(string $formId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT
                    rf.id AS derivacion_id,
                    r.referral_code AS cod_derivacion,
                    r.referral_code AS codigo_derivacion,
                    f.iess_form_id AS form_id,
                    f.hc_number,
                    f.fecha_creacion,
                    f.fecha_registro,
                    COALESCE(r.valid_until, f.fecha_vigencia) AS fecha_vigencia,
                    f.referido,
                    f.diagnostico,
                    f.sede,
                    f.parentesco,
                    f.archivo_derivacion_path
                 FROM derivaciones_forms f
                 LEFT JOIN derivaciones_referral_forms rf ON rf.form_id = f.id
                 LEFT JOIN derivaciones_referrals r ON r.id = rf.referral_id
                 WHERE f.iess_form_id = ?
                 ORDER BY COALESCE(rf.linked_at, f.updated_at) DESC, f.id DESC
                 LIMIT 1"
            );
            $stmt->execute([$formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $row['id'] = $row['derivacion_id'] ?? null;
                return $row;
            }
        } catch (\Throwable) {
            // Fallback a tabla legacy.
        }

        try {
            $stmtLegacy = $this->db->prepare('SELECT * FROM derivaciones_form_id WHERE form_id = ? LIMIT 1');
            $stmtLegacy->execute([$formId]);
            $rowLegacy = $stmtLegacy->fetch(PDO::FETCH_ASSOC);
            return is_array($rowLegacy) ? $rowLegacy : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerDerivacionPreseleccion(int $solicitudId): ?array
    {
        $codigoExpr = $this->selectSolicitudColumn('derivacion_codigo');
        $pedidoExpr = $this->selectSolicitudColumn('derivacion_pedido_id');
        $lateralidadExpr = $this->selectSolicitudColumn('derivacion_lateralidad');
        $vigenciaExpr = $this->selectSolicitudColumn('derivacion_fecha_vigencia_sel');
        $prefacturaExpr = $this->selectSolicitudColumn('derivacion_prefactura');

        $stmt = $this->db->prepare(
            "SELECT
                {$codigoExpr},
                {$pedidoExpr},
                {$lateralidadExpr},
                {$vigenciaExpr},
                {$prefacturaExpr}
             FROM solicitud_procedimiento sp
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $solicitudId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerDerivacionPreseleccionPorFormHc(string $formId, string $hcNumber): ?array
    {
        $codigoExpr = $this->selectSolicitudColumn('derivacion_codigo');
        $pedidoExpr = $this->selectSolicitudColumn('derivacion_pedido_id');
        $lateralidadExpr = $this->selectSolicitudColumn('derivacion_lateralidad');
        $vigenciaExpr = $this->selectSolicitudColumn('derivacion_fecha_vigencia_sel');
        $prefacturaExpr = $this->selectSolicitudColumn('derivacion_prefactura');

        $stmt = $this->db->prepare(
            "SELECT
                id,
                {$codigoExpr},
                {$pedidoExpr},
                {$lateralidadExpr},
                {$vigenciaExpr},
                {$prefacturaExpr}
             FROM solicitud_procedimiento sp
             WHERE form_id = :form_id
               AND hc_number = :hc
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':hc' => $hcNumber,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function obtenerSolicitudIdPorFormHc(string $formId, string $hcNumber): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM solicitud_procedimiento WHERE form_id = :form_id AND hc_number = :hc ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':hc' => $hcNumber,
        ]);

        $row = $stmt->fetchColumn();
        if ($row === false) {
            return null;
        }

        return (int) $row;
    }

    private function obtenerSolicitudIdPorFormId(string $formId): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM solicitud_procedimiento WHERE form_id = :form_id ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $stmt->execute([
            ':form_id' => $formId,
        ]);

        $row = $stmt->fetchColumn();
        if ($row === false) {
            return null;
        }

        return (int) $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerDatosYCirujanoSolicitud(string $formId, string $hcNumber): array
    {
        $doctorJoinSubquery = $this->buildDoctorPreferredUserIdSubquery('sp.doctor');
        $doctorSignaturePathExpression = $this->buildDoctorSignaturePathExpression('u');
        $doctorFirmaExpression = $this->buildDoctorFirmaExpression('u');

        $sql = "SELECT
                sp.*,
                sp.id AS solicitud_id,
                sp.id AS id,
                u.id AS user_id,
                u.nombre AS user_nombre,
                u.email AS user_email,
                u.first_name AS doctor_first_name,
                u.middle_name AS doctor_middle_name,
                u.last_name AS doctor_last_name,
                u.second_last_name AS doctor_second_last_name,
                u.cedula AS doctor_cedula,
                {$doctorSignaturePathExpression} AS doctor_signature_path,
                {$doctorFirmaExpression} AS doctor_firma,
                u.full_name AS doctor_full_name
            FROM solicitud_procedimiento sp
            LEFT JOIN users u
                ON u.id = {$doctorJoinSubquery}
            WHERE sp.form_id = ? AND sp.hc_number = ?
            ORDER BY sp.created_at DESC
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$formId, $hcNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerDatosYCirujanoSolicitudPorFormId(string $formId): array
    {
        $doctorJoinSubquery = $this->buildDoctorPreferredUserIdSubquery('sp.doctor');
        $doctorSignaturePathExpression = $this->buildDoctorSignaturePathExpression('u');
        $doctorFirmaExpression = $this->buildDoctorFirmaExpression('u');

        $sql = "SELECT
                sp.*,
                sp.id AS solicitud_id,
                sp.id AS id,
                u.id AS user_id,
                u.nombre AS user_nombre,
                u.email AS user_email,
                u.first_name AS doctor_first_name,
                u.middle_name AS doctor_middle_name,
                u.last_name AS doctor_last_name,
                u.second_last_name AS doctor_second_last_name,
                u.cedula AS doctor_cedula,
                {$doctorSignaturePathExpression} AS doctor_signature_path,
                {$doctorFirmaExpression} AS doctor_firma,
                u.full_name AS doctor_full_name
            FROM solicitud_procedimiento sp
            LEFT JOIN users u
                ON u.id = {$doctorJoinSubquery}
            WHERE sp.form_id = ?
            ORDER BY sp.created_at DESC, sp.id DESC
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerConsultaDeSolicitud(string $formId): array
    {
        $doctorJoinSubquery = $this->buildDoctorPreferredUserIdSubquery('pp.doctor');
        $doctorSignaturePathExpression = $this->buildDoctorSignaturePathExpression('u');
        $doctorFirmaExpression = $this->buildDoctorFirmaExpression('u');

        $sql = "SELECT
                cd.*,
                pp.doctor AS procedimiento_doctor,
                u.id AS doctor_user_id,
                u.first_name AS matched_doctor_fname,
                u.middle_name AS matched_doctor_mname,
                u.last_name AS matched_doctor_lname,
                u.second_last_name AS matched_doctor_lname2,
                u.cedula AS matched_doctor_cedula,
                {$doctorSignaturePathExpression} AS matched_doctor_signature_path,
                {$doctorFirmaExpression} AS matched_doctor_firma,
                u.full_name AS matched_doctor_full_name
            FROM consulta_data cd
            LEFT JOIN procedimiento_proyectado pp
                ON pp.id = (
                    SELECT pp2.id
                    FROM procedimiento_proyectado pp2
                    WHERE pp2.form_id = cd.form_id
                      AND pp2.hc_number = cd.hc_number
                      AND pp2.doctor IS NOT NULL
                      AND TRIM(pp2.doctor) <> ''
                    ORDER BY pp2.id DESC
                    LIMIT 1
                )
            LEFT JOIN users u
                ON u.id = {$doctorJoinSubquery}
            WHERE cd.form_id = ?
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$formId]);
        $consulta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($consulta)) {
            return [];
        }

        $fallbackMap = [
            'matched_doctor_fname' => 'doctor_fname',
            'matched_doctor_mname' => 'doctor_mname',
            'matched_doctor_lname' => 'doctor_lname',
            'matched_doctor_lname2' => 'doctor_lname2',
            'matched_doctor_cedula' => 'doctor_cedula',
            'matched_doctor_signature_path' => 'doctor_signature_path',
            'matched_doctor_firma' => 'doctor_firma',
            'matched_doctor_full_name' => 'doctor_full_name',
        ];

        foreach ($fallbackMap as $sourceField => $targetField) {
            $sourceValue = trim((string) ($consulta[$sourceField] ?? ''));
            if ($sourceValue !== '') {
                $consulta[$targetField] = $sourceValue;
            }
            unset($consulta[$sourceField]);
        }

        if (trim((string) ($consulta['doctor'] ?? '')) === '') {
            $fromProcedimiento = trim((string) ($consulta['procedimiento_doctor'] ?? ''));
            $fromFullName = trim((string) ($consulta['doctor_full_name'] ?? ''));
            $consulta['doctor'] = $fromFullName !== '' ? $fromFullName : $fromProcedimiento;
        }

        return $consulta;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerConsultaPorFormHc(string $formId, string $hcNumber): array
    {
        $doctorJoinSubquery = $this->buildDoctorPreferredUserIdSubquery('pp.doctor');
        $doctorSignaturePathExpression = $this->buildDoctorSignaturePathExpression('u');
        $doctorFirmaExpression = $this->buildDoctorFirmaExpression('u');

        $sql = "SELECT
                cd.*,
                pp.doctor AS procedimiento_doctor,
                u.id AS doctor_user_id,
                u.first_name AS matched_doctor_fname,
                u.middle_name AS matched_doctor_mname,
                u.last_name AS matched_doctor_lname,
                u.second_last_name AS matched_doctor_lname2,
                u.cedula AS matched_doctor_cedula,
                {$doctorSignaturePathExpression} AS matched_doctor_signature_path,
                {$doctorFirmaExpression} AS matched_doctor_firma,
                u.full_name AS matched_doctor_full_name
            FROM consulta_data cd
            LEFT JOIN procedimiento_proyectado pp
                ON pp.id = (
                    SELECT pp2.id
                    FROM procedimiento_proyectado pp2
                    WHERE pp2.form_id = cd.form_id
                      AND pp2.hc_number = cd.hc_number
                      AND pp2.doctor IS NOT NULL
                      AND TRIM(pp2.doctor) <> ''
                    ORDER BY pp2.id DESC
                    LIMIT 1
                )
            LEFT JOIN users u
                ON u.id = {$doctorJoinSubquery}
            WHERE cd.form_id = ?
              AND cd.hc_number = ?
            ORDER BY COALESCE(cd.created_at, cd.fecha) DESC
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$formId, $hcNumber]);
        $consulta = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($consulta)) {
            return [];
        }

        $fallbackMap = [
            'matched_doctor_fname' => 'doctor_fname',
            'matched_doctor_mname' => 'doctor_mname',
            'matched_doctor_lname' => 'doctor_lname',
            'matched_doctor_lname2' => 'doctor_lname2',
            'matched_doctor_cedula' => 'doctor_cedula',
            'matched_doctor_signature_path' => 'doctor_signature_path',
            'matched_doctor_firma' => 'doctor_firma',
            'matched_doctor_full_name' => 'doctor_full_name',
        ];

        foreach ($fallbackMap as $sourceField => $targetField) {
            $sourceValue = trim((string) ($consulta[$sourceField] ?? ''));
            if ($sourceValue !== '') {
                $consulta[$targetField] = $sourceValue;
            }
            unset($consulta[$sourceField]);
        }

        if (trim((string) ($consulta['doctor'] ?? '')) === '') {
            $fromProcedimiento = trim((string) ($consulta['procedimiento_doctor'] ?? ''));
            $fromFullName = trim((string) ($consulta['doctor_full_name'] ?? ''));
            $consulta['doctor'] = $fromFullName !== '' ? $fromFullName : $fromProcedimiento;
        }

        return $consulta;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function obtenerDxDeSolicitud(string $formId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM diagnosticos_asignados WHERE form_id = ?');
        $stmt->execute([$formId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerPaciente(string $hcNumber): array
    {
        $stmt = $this->db->prepare('SELECT * FROM patient_data WHERE hc_number = ? LIMIT 1');
        $stmt->execute([$hcNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /**
     * @return array{form_id:string,hc_number:string,procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null}
     */
    private function resolverContextoCobertura(string $formId, string $hcNumber): array
    {
        $resolvedFormId = trim($formId);
        $resolvedHc = trim($hcNumber);

        $procedimiento = $this->fetchProcedimientoProyectadoByFormHc($resolvedFormId, $resolvedHc);
        if ($procedimiento === null) {
            $procedimiento = $this->fetchProcedimientoProyectadoByFormId($resolvedFormId);
        }

        if (is_array($procedimiento)) {
            $hcProc = trim((string) ($procedimiento['hc_number'] ?? ''));
            if ($hcProc !== '') {
                $resolvedHc = $hcProc;
            }
        }

        $protocolo = $this->fetchProtocoloByFormHc($resolvedFormId, $resolvedHc);
        if ($protocolo === null) {
            $protocolo = $this->fetchProtocoloByFormId($resolvedFormId);
            if (is_array($protocolo)) {
                $hcProtocolo = trim((string) ($protocolo['hc_number'] ?? ''));
                if ($hcProtocolo !== '') {
                    $resolvedHc = $hcProtocolo;
                }
            }
        }

        return [
            'form_id' => $resolvedFormId,
            'hc_number' => $resolvedHc,
            'procedimiento' => $procedimiento,
            'protocolo' => $protocolo,
        ];
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     * @return array<string, mixed>
     */
    private function construirSolicitudFallback(string $formId, string $hcNumber, array $contexto): array
    {
        $procedimiento = is_array($contexto['procedimiento'] ?? null) ? $contexto['procedimiento'] : [];
        $protocolo = is_array($contexto['protocolo'] ?? null) ? $contexto['protocolo'] : [];

        $doctor = $this->resolverDoctorDesdeContexto($contexto);
        $procedimientoTexto = $this->resolverProcedimientoDesdeContexto($contexto);
        $createdAt = $this->resolverCreatedAtDesdeContexto($contexto);

        $solicitud = [
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'doctor' => $doctor,
            'procedimiento' => $procedimientoTexto,
            'created_at' => $createdAt,
            'afiliacion' => (string) ($procedimiento['afiliacion'] ?? ($protocolo['afiliacion'] ?? '')),
        ];

        $solicitud = $this->enriquecerSolicitudConUsuarioDoctor($solicitud, $doctor);

        if (trim((string) ($solicitud['doctor_full_name'] ?? '')) === '' && $doctor !== '') {
            $solicitud['doctor_full_name'] = $doctor;
        }

        return $solicitud;
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     * @param array<string, mixed> $solicitud
     * @param array<string, mixed>|null $derivacion
     * @return array<string, mixed>
     */
    private function construirConsultaFallback(
        string $formId,
        string $hcNumber,
        array $contexto,
        array $solicitud,
        ?array $derivacion
    ): array {
        $doctor = trim((string) ($solicitud['doctor'] ?? ''));
        if ($doctor === '') {
            $doctor = $this->resolverDoctorDesdeContexto($contexto);
        }

        $procedimientoTexto = trim((string) ($solicitud['procedimiento'] ?? ''));
        if ($procedimientoTexto === '') {
            $procedimientoTexto = $this->resolverProcedimientoDesdeContexto($contexto);
        }

        $createdAt = trim((string) ($solicitud['created_at'] ?? ''));
        if ($createdAt === '') {
            $createdAt = $this->resolverCreatedAtDesdeContexto($contexto);
        }

        $fecha = '';
        if ($createdAt !== '') {
            $fecha = substr($createdAt, 0, 10);
        }

        $motivo = trim((string) (($derivacion['diagnostico'] ?? null) ?? ''));

        $consulta = [
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'fecha' => $fecha,
            'created_at' => $createdAt,
            'motivo_consulta' => $motivo,
            'enfermedad_actual' => $motivo,
            'examen_fisico' => '',
            'plan' => $procedimientoTexto,
            'diagnostico_plan' => $procedimientoTexto,
            'doctor' => $doctor,
            'procedimiento_doctor' => $doctor,
        ];

        return $this->enriquecerConsultaConUsuarioDoctor($consulta, $doctor);
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     * @param array<string, mixed>|null $derivacion
     * @return array<int, array<string, mixed>>
     */
    private function construirDiagnosticoFallback(array $contexto, ?array $derivacion): array
    {
        $diagnosticos = [];
        $protocolo = is_array($contexto['protocolo'] ?? null) ? $contexto['protocolo'] : [];

        $raw = $this->decodeJsonArray($protocolo['diagnosticos'] ?? null);
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $dxCode = trim((string) ($item['dx_code'] ?? ($item['cie10'] ?? '')));
            $descripcion = trim((string) ($item['descripcion'] ?? ''));

            $idDiagnostico = trim((string) ($item['idDiagnostico'] ?? ''));
            if ($descripcion === '' && $idDiagnostico !== '') {
                [$dxParsed, $descParsed] = $this->parseDiagnosticoTexto($idDiagnostico);
                if ($dxCode === '') {
                    $dxCode = $dxParsed;
                }
                if ($descripcion === '') {
                    $descripcion = $descParsed;
                }
            }

            if ($dxCode === '' && $descripcion === '') {
                continue;
            }

            $diagnosticos[] = [
                'dx_code' => $dxCode,
                'descripcion' => $descripcion,
                'fuente' => 'protocolo',
            ];

            if (count($diagnosticos) >= 6) {
                break;
            }
        }

        if ($diagnosticos !== []) {
            return $diagnosticos;
        }

        $diagDerivacion = trim((string) (($derivacion['diagnostico'] ?? null) ?? ''));
        if ($diagDerivacion !== '') {
            $partes = array_values(array_filter(array_map(
                static fn(string $v): string => trim($v),
                preg_split('/\s*;\s*/', $diagDerivacion) ?: []
            ), static fn(string $v): bool => $v !== ''));

            foreach ($partes as $parte) {
                [$dxCode, $descripcion] = $this->parseDiagnosticoTexto($parte);
                if ($dxCode === '' && $descripcion === '') {
                    continue;
                }

                $diagnosticos[] = [
                    'dx_code' => $dxCode,
                    'descripcion' => $descripcion,
                    'fuente' => 'derivacion',
                ];

                if (count($diagnosticos) >= 6) {
                    break;
                }
            }
        }

        return $diagnosticos;
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     */
    private function resolverDoctorDesdeContexto(array $contexto): string
    {
        $procedimiento = is_array($contexto['procedimiento'] ?? null) ? $contexto['procedimiento'] : [];
        $protocolo = is_array($contexto['protocolo'] ?? null) ? $contexto['protocolo'] : [];

        $doctor = trim((string) ($procedimiento['doctor'] ?? ''));
        if ($doctor !== '') {
            return $doctor;
        }

        return trim((string) ($protocolo['cirujano_1'] ?? ''));
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     */
    private function resolverProcedimientoDesdeContexto(array $contexto): string
    {
        $procedimiento = is_array($contexto['procedimiento'] ?? null) ? $contexto['procedimiento'] : [];
        $protocolo = is_array($contexto['protocolo'] ?? null) ? $contexto['protocolo'] : [];

        $texto = trim((string) ($procedimiento['procedimiento_proyectado'] ?? ''));
        if ($texto !== '') {
            return $texto;
        }

        return trim((string) ($protocolo['membrete'] ?? ''));
    }

    /**
     * @param array{procedimiento:array<string,mixed>|null,protocolo:array<string,mixed>|null} $contexto
     */
    private function resolverCreatedAtDesdeContexto(array $contexto): string
    {
        $procedimiento = is_array($contexto['procedimiento'] ?? null) ? $contexto['procedimiento'] : [];
        $protocolo = is_array($contexto['protocolo'] ?? null) ? $contexto['protocolo'] : [];

        $fechaProc = trim((string) ($procedimiento['fecha'] ?? ''));
        $horaProc = trim((string) ($procedimiento['hora'] ?? ''));
        if ($fechaProc !== '') {
            return $fechaProc . ($horaProc !== '' ? (' ' . $horaProc) : '');
        }

        return trim((string) ($protocolo['fecha_inicio'] ?? ''));
    }

    /**
     * @param array<string, mixed> $solicitud
     * @return array<string, mixed>
     */
    private function enriquecerSolicitudConUsuarioDoctor(array $solicitud, string $doctorNombre): array
    {
        $usuario = $this->obtenerUsuarioPorDoctorNombre($doctorNombre);
        if ($usuario === []) {
            return $solicitud;
        }

        $signaturePath = $this->resolverSignaturePathUsuario($usuario);
        $fullName = trim((string) ($usuario['full_name'] ?? $usuario['nombre'] ?? ''));

        if (trim((string) ($solicitud['doctor_first_name'] ?? '')) === '') {
            $solicitud['doctor_first_name'] = (string) ($usuario['first_name'] ?? '');
        }
        if (trim((string) ($solicitud['doctor_middle_name'] ?? '')) === '') {
            $solicitud['doctor_middle_name'] = (string) ($usuario['middle_name'] ?? '');
        }
        if (trim((string) ($solicitud['doctor_last_name'] ?? '')) === '') {
            $solicitud['doctor_last_name'] = (string) ($usuario['last_name'] ?? '');
        }
        if (trim((string) ($solicitud['doctor_second_last_name'] ?? '')) === '') {
            $solicitud['doctor_second_last_name'] = (string) ($usuario['second_last_name'] ?? '');
        }
        if (trim((string) ($solicitud['doctor_cedula'] ?? '')) === '') {
            $solicitud['doctor_cedula'] = (string) ($usuario['cedula'] ?? '');
        }
        if (trim((string) ($solicitud['doctor_signature_path'] ?? '')) === '' && $signaturePath !== '') {
            $solicitud['doctor_signature_path'] = $signaturePath;
        }
        if (trim((string) ($solicitud['doctor_firma'] ?? '')) === '') {
            $solicitud['doctor_firma'] = (string) ($usuario['firma'] ?? '');
        }
        if (trim((string) ($solicitud['doctor_full_name'] ?? '')) === '' && $fullName !== '') {
            $solicitud['doctor_full_name'] = $fullName;
        }
        if (trim((string) ($solicitud['doctor'] ?? '')) === '' && $fullName !== '') {
            $solicitud['doctor'] = $fullName;
        }

        if (trim((string) ($solicitud['signature_path'] ?? '')) === '' && $signaturePath !== '') {
            $solicitud['signature_path'] = $signaturePath;
        }
        if (trim((string) ($solicitud['firma'] ?? '')) === '') {
            $solicitud['firma'] = (string) ($usuario['firma'] ?? '');
        }
        if (trim((string) ($solicitud['cedula'] ?? '')) === '') {
            $solicitud['cedula'] = (string) ($usuario['cedula'] ?? '');
        }

        return $solicitud;
    }

    /**
     * @param array<string, mixed> $consulta
     * @return array<string, mixed>
     */
    private function enriquecerConsultaConUsuarioDoctor(array $consulta, string $doctorNombre): array
    {
        $usuario = $this->obtenerUsuarioPorDoctorNombre($doctorNombre);
        if ($usuario === []) {
            return $consulta;
        }

        $signaturePath = $this->resolverSignaturePathUsuario($usuario);
        $fullName = trim((string) ($usuario['full_name'] ?? $usuario['nombre'] ?? ''));

        if (trim((string) ($consulta['doctor_fname'] ?? '')) === '') {
            $consulta['doctor_fname'] = (string) ($usuario['first_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_mname'] ?? '')) === '') {
            $consulta['doctor_mname'] = (string) ($usuario['middle_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_lname'] ?? '')) === '') {
            $consulta['doctor_lname'] = (string) ($usuario['last_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_lname2'] ?? '')) === '') {
            $consulta['doctor_lname2'] = (string) ($usuario['second_last_name'] ?? '');
        }
        if (trim((string) ($consulta['doctor_cedula'] ?? '')) === '') {
            $consulta['doctor_cedula'] = (string) ($usuario['cedula'] ?? '');
        }
        if (trim((string) ($consulta['doctor_signature_path'] ?? '')) === '' && $signaturePath !== '') {
            $consulta['doctor_signature_path'] = $signaturePath;
        }
        if (trim((string) ($consulta['doctor_firma'] ?? '')) === '') {
            $consulta['doctor_firma'] = (string) ($usuario['firma'] ?? '');
        }
        if (trim((string) ($consulta['doctor_full_name'] ?? '')) === '' && $fullName !== '') {
            $consulta['doctor_full_name'] = $fullName;
        }
        if (trim((string) ($consulta['doctor'] ?? '')) === '' && $fullName !== '') {
            $consulta['doctor'] = $fullName;
        }
        if ((int) ($consulta['doctor_user_id'] ?? 0) <= 0 && isset($usuario['id'])) {
            $consulta['doctor_user_id'] = (int) $usuario['id'];
        }

        return $consulta;
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerUsuarioPorDoctorNombre(string $doctorNombre): array
    {
        $doctorNombre = trim($doctorNombre);
        if ($doctorNombre === '') {
            return [];
        }

        $conditions = ['UPPER(TRIM(u.nombre)) = UPPER(TRIM(?))'];
        $params = [$doctorNombre];

        if ($this->hasColumnInTable('users', 'full_name')) {
            $conditions[] = 'UPPER(TRIM(u.full_name)) = UPPER(TRIM(?))';
            $params[] = $doctorNombre;
        }

        if ($this->hasColumnInTable('users', 'nombre_norm')) {
            $conditions[] = 'u.nombre_norm = TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(?)), " "), " SNS ", " "), "  ", " "), "  ", " "))';
            $params[] = $doctorNombre;
            $conditions[] = 'UPPER(TRIM(u.nombre_norm)) = UPPER(TRIM(?))';
            $params[] = $doctorNombre;
        }

        if ($this->hasColumnInTable('users', 'nombre_norm_rev')) {
            $conditions[] = 'u.nombre_norm_rev = TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(?)), " "), " SNS ", " "), "  ", " "), "  ", " "))';
            $params[] = $doctorNombre;
            $conditions[] = 'UPPER(TRIM(u.nombre_norm_rev)) = UPPER(TRIM(?))';
            $params[] = $doctorNombre;
        }

        $conditions[] = 'u.nombre LIKE ?';
        $params[] = '%' . $doctorNombre . '%';

        $sql = 'SELECT u.* FROM users u WHERE (' . implode(' OR ', $conditions) . ') ORDER BY u.id DESC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /**
     * @param array<string, mixed> $usuario
     */
    private function resolverSignaturePathUsuario(array $usuario): string
    {
        $signaturePath = trim((string) ($usuario['signature_path'] ?? ''));
        if ($signaturePath !== '') {
            return $signaturePath;
        }

        return trim((string) ($usuario['seal_signature_path'] ?? ''));
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseDiagnosticoTexto(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['', ''];
        }

        if (preg_match('/^\s*([A-Z][0-9A-Z\.]+)\s*[-–:]\s*(.+)\s*$/u', $value, $m) === 1) {
            return [trim((string) ($m[1] ?? '')), trim((string) ($m[2] ?? ''))];
        }

        return ['', $value];
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonArray(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values($raw);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProcedimientoProyectadoByFormHc(string $formId, string $hcNumber): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, form_id, hc_number, procedimiento_proyectado, doctor, fecha, hora, afiliacion
             FROM procedimiento_proyectado
             WHERE form_id = ? AND hc_number = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$formId, $hcNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProcedimientoProyectadoByFormId(string $formId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, form_id, hc_number, procedimiento_proyectado, doctor, fecha, hora, afiliacion
             FROM procedimiento_proyectado
             WHERE form_id = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProtocoloByFormHc(string $formId, string $hcNumber): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT form_id, hc_number, fecha_inicio, membrete, cirujano_1, diagnosticos, diagnosticos_previos
             FROM protocolo_data
             WHERE form_id = ? AND hc_number = ?
             ORDER BY fecha_inicio DESC
             LIMIT 1'
        );
        $stmt->execute([$formId, $hcNumber]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProtocoloByFormId(string $formId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT form_id, hc_number, fecha_inicio, membrete, cirujano_1, diagnosticos, diagnosticos_previos
             FROM protocolo_data
             WHERE form_id = ?
             ORDER BY fecha_inicio DESC
             LIMIT 1'
        );
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Construye una condición SQL para comparar nombres de médico tolerando el token "SNS".
     */
    private function buildDoctorNameMatchCondition(string $leftExpression, string $rightExpression): string
    {
        $leftNormalized = $this->normalizeDoctorNameSql($leftExpression);
        $rightNormalized = $this->normalizeDoctorNameSql($rightExpression);

        return sprintf(
            "(UPPER(TRIM(%s)) = UPPER(TRIM(%s))
              OR %s = %s
              OR %s LIKE CONCAT('%%', %s, '%%')
              OR %s LIKE CONCAT('%%', %s, '%%'))",
            $leftExpression,
            $rightExpression,
            $leftNormalized,
            $rightNormalized,
            $leftNormalized,
            $rightNormalized,
            $rightNormalized,
            $leftNormalized
        );
    }

    private function buildDoctorUserMatchCondition(string $doctorExpression, string $userAlias = 'u'): string
    {
        $userNameExpression = $userAlias . '.nombre';
        $userStructuredNameExpression = $this->buildDoctorStructuredNameExpression($userAlias, false);
        $userStructuredReverseNameExpression = $this->buildDoctorStructuredNameExpression($userAlias, true);
        $conditions = [
            $this->buildDoctorNameMatchCondition($doctorExpression, $userNameExpression),
            $this->buildDoctorNameMatchCondition($doctorExpression, $userStructuredNameExpression),
            $this->buildDoctorNameMatchCondition($doctorExpression, $userStructuredReverseNameExpression),
        ];

        $doctorRaw = "UPPER(TRIM({$doctorExpression}))";
        $doctorNormalized = $this->normalizeDoctorNameSql($doctorExpression);

        if ($this->hasColumnInTable('users', 'full_name')) {
            $conditions[] = $this->buildDoctorNameMatchCondition($doctorExpression, "{$userAlias}.full_name");
        }

        if ($this->hasColumnInTable('users', 'nombre_norm')) {
            $conditions[] = "{$doctorRaw} = {$userAlias}.nombre_norm";
            $conditions[] = "{$doctorNormalized} = {$userAlias}.nombre_norm";
        }

        if ($this->hasColumnInTable('users', 'nombre_norm_rev')) {
            $conditions[] = "{$doctorRaw} = {$userAlias}.nombre_norm_rev";
            $conditions[] = "{$doctorNormalized} = {$userAlias}.nombre_norm_rev";
        }

        return '(' . implode(' OR ', array_unique($conditions)) . ')';
    }

    private function buildDoctorPreferredUserIdSubquery(string $doctorExpression): string
    {
        $matchCondition = $this->buildDoctorUserMatchCondition($doctorExpression, 'u2');
        $doctorRaw = "UPPER(TRIM({$doctorExpression}))";
        $doctorNormalized = $this->normalizeDoctorNameSql($doctorExpression);
        $orderBy = [];

        if ($this->hasColumnInTable('users', 'nombre_norm_rev')) {
            $orderBy[] = "CASE WHEN {$doctorRaw} = u2.nombre_norm_rev THEN 0 ELSE 1 END";
            $orderBy[] = "CASE WHEN {$doctorNormalized} = u2.nombre_norm_rev THEN 0 ELSE 1 END";
        }

        if ($this->hasColumnInTable('users', 'nombre_norm')) {
            $orderBy[] = "CASE WHEN {$doctorRaw} = u2.nombre_norm THEN 0 ELSE 1 END";
            $orderBy[] = "CASE WHEN {$doctorNormalized} = u2.nombre_norm THEN 0 ELSE 1 END";
        }

        $orderBy[] = "CASE WHEN {$doctorNormalized} = " . $this->normalizeDoctorNameSql('u2.nombre') . ' THEN 0 ELSE 1 END';
        $orderBy[] = "CASE WHEN {$doctorNormalized} = " . $this->normalizeDoctorNameSql($this->buildDoctorStructuredNameExpression('u2', false)) . ' THEN 0 ELSE 1 END';
        $orderBy[] = "CASE WHEN {$doctorNormalized} = " . $this->normalizeDoctorNameSql($this->buildDoctorStructuredNameExpression('u2', true)) . ' THEN 0 ELSE 1 END';

        if ($this->hasColumnInTable('users', 'full_name')) {
            $orderBy[] = "CASE WHEN {$doctorNormalized} = " . $this->normalizeDoctorNameSql('u2.full_name') . ' THEN 0 ELSE 1 END';
        }

        $signatureExpression = $this->buildDoctorSignaturePathExpression('u2');
        $firmaExpression = $this->buildDoctorFirmaExpression('u2');

        if ($signatureExpression !== 'NULL') {
            $orderBy[] = "CASE WHEN {$signatureExpression} IS NOT NULL THEN 0 ELSE 1 END";
        }

        if ($firmaExpression !== 'NULL') {
            $orderBy[] = "CASE WHEN {$firmaExpression} IS NOT NULL THEN 0 ELSE 1 END";
        }

        $orderBy[] = 'u2.id DESC';

        return "(SELECT u2.id
            FROM users u2
            WHERE {$matchCondition}
            ORDER BY " . implode(', ', $orderBy) . '
            LIMIT 1)';
    }

    private function buildDoctorSignaturePathExpression(string $userAlias = 'u'): string
    {
        $candidates = [];

        if ($this->hasColumnInTable('users', 'signature_path')) {
            $candidates[] = "NULLIF(TRIM({$userAlias}.signature_path), '')";
        }

        if ($this->hasColumnInTable('users', 'seal_signature_path')) {
            $candidates[] = "NULLIF(TRIM({$userAlias}.seal_signature_path), '')";
        }

        if ($candidates === []) {
            return 'NULL';
        }

        return 'COALESCE(' . implode(', ', $candidates) . ')';
    }

    private function buildDoctorFirmaExpression(string $userAlias = 'u'): string
    {
        $candidates = [];

        if ($this->hasColumnInTable('users', 'firma')) {
            $candidates[] = "NULLIF(TRIM({$userAlias}.firma), '')";
        }

        if ($candidates === []) {
            return 'NULL';
        }

        return 'COALESCE(' . implode(', ', $candidates) . ')';
    }

    private function buildDoctorStructuredNameExpression(string $userAlias = 'u', bool $reverse = false): string
    {
        if ($reverse) {
            return "TRIM(CONCAT_WS(' ',
                NULLIF(TRIM({$userAlias}.last_name), ''),
                NULLIF(TRIM({$userAlias}.second_last_name), ''),
                NULLIF(TRIM({$userAlias}.first_name), ''),
                NULLIF(TRIM({$userAlias}.middle_name), '')
            ))";
        }

        return "TRIM(CONCAT_WS(' ',
            NULLIF(TRIM({$userAlias}.first_name), ''),
            NULLIF(TRIM({$userAlias}.middle_name), ''),
            NULLIF(TRIM({$userAlias}.last_name), ''),
            NULLIF(TRIM({$userAlias}.second_last_name), '')
        ))";
    }

    private function normalizeDoctorNameSql(string $expression): string
    {
        return "TRIM(REPLACE(REPLACE(REPLACE(CONCAT(' ', UPPER(TRIM({$expression})), ' '), ' SNS ', ' '), '  ', ' '), '  ', ' '))";
    }

    private function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $exists = false;
        try {
            $safeTable = str_replace('`', '', $table);
            $this->db->query("SELECT 1 FROM `{$safeTable}` LIMIT 1");
            $exists = true;
        } catch (\Throwable) {
            $exists = false;
        }

        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    private function hasColumnInTable(string $table, string $column): bool
    {
        if (isset($this->tableColumnsCache[$table][$column])) {
            return $this->tableColumnsCache[$table][$column];
        }

        $exists = false;

        if ($this->hasTable($table)) {
            try {
                $safeTable = str_replace('`', '', $table);
                $stmt = $this->db->prepare("SHOW COLUMNS FROM `{$safeTable}` LIKE :column");
                $stmt->bindValue(':column', $column, PDO::PARAM_STR);
                $stmt->execute();
                $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Throwable) {
                $exists = false;
            }

            if (!$exists) {
                try {
                    $safeTable = str_replace('`', '', $table);
                    $safeColumn = str_replace('`', '', $column);
                    $this->db->query("SELECT `{$safeColumn}` FROM `{$safeTable}` LIMIT 0");
                    $exists = true;
                } catch (\Throwable) {
                    $exists = false;
                }
            }
        }

        if (!isset($this->tableColumnsCache[$table])) {
            $this->tableColumnsCache[$table] = [];
        }
        $this->tableColumnsCache[$table][$column] = $exists;

        return $exists;
    }

    private function selectSolicitudColumn(string $column, ?string $alias = null): string
    {
        $alias = $alias ?? $column;

        if ($this->hasSolicitudColumn($column)) {
            return 'sp.' . $this->quoteIdentifier($column) . ' AS ' . $this->quoteIdentifier($alias);
        }

        return 'NULL AS ' . $this->quoteIdentifier($alias);
    }

    private function hasSolicitudColumn(string $column): bool
    {
        if ($this->solicitudColumns === null) {
            $this->solicitudColumns = [];
            try {
                $stmt = $this->db->query('SHOW COLUMNS FROM solicitud_procedimiento');
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                foreach ($rows as $row) {
                    $field = (string) ($row['Field'] ?? '');
                    if ($field !== '') {
                        $this->solicitudColumns[$field] = true;
                    }
                }
            } catch (\Throwable) {
                $this->solicitudColumns = [];
            }
        }

        return isset($this->solicitudColumns[$column]);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
