<?php

namespace App\Modules\Reporting\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class ImagenesReportDataService
{
    /**
     * @return array<string, mixed>
     */
    public function buildInforme012BData(string $formId, string $hcNumber): array
    {
        $formId = trim($formId);
        $hcNumber = trim($hcNumber);
        if ($formId === '' || $hcNumber === '') {
            return [];
        }

        $procedimiento = $this->fetchProcedimientoProyectadoByFormHc($formId, $hcNumber);
        if ($procedimiento === null) {
            $procedimiento = $this->fetchProcedimientoProyectadoByFormId($formId);
        }
        if ($procedimiento === null) {
            return [];
        }

        $informe = $this->fetchInformeImagen($formId);
        $payload = $this->decodeJsonObject($informe['payload_json'] ?? null);

        $hcForPatient = trim((string) ($procedimiento['hc_number'] ?? $hcNumber));
        if ($hcForPatient === '') {
            $hcForPatient = $hcNumber;
        }
        $paciente = $this->fetchPatientData($hcForPatient);

        $tipoExamen = trim((string) ($procedimiento['procedimiento_proyectado'] ?? ($informe['tipo_examen'] ?? '')));
        $plantilla = isset($informe['plantilla']) ? trim((string) $informe['plantilla']) : '';
        if ($plantilla === '' && $tipoExamen !== '') {
            $plantilla = (string) ($this->mapearPlantillaInforme($tipoExamen) ?? '');
        }

        $parsed = $this->parseProcedimientoImagen($tipoExamen);
        $descripcionBase = $parsed['texto'] !== '' ? $parsed['texto'] : $tipoExamen;
        $descripcion = $descripcionBase;

        $codigoTarifario = $this->extraerCodigoTarifario($descripcionBase);
        if ($codigoTarifario !== null) {
            $tarifario = $this->obtenerTarifarioPorCodigo($codigoTarifario);
            if (is_array($tarifario) && $tarifario !== []) {
                $nombreTarifario = trim((string) ($tarifario['descripcion'] ?? ($tarifario['short_description'] ?? '')));
                $codigoMostrar = trim((string) ($tarifario['codigo'] ?? $codigoTarifario));
                if ($nombreTarifario !== '') {
                    $descripcion = $nombreTarifario . ' (' . $codigoMostrar . ')';
                }
            }
        }

        if ($parsed['ojo'] !== '') {
            $descripcion = trim($descripcion . ' - ' . $parsed['ojo']);
        }

        $hallazgos = $this->construirHallazgosInforme($payload !== [] ? $payload : null, $plantilla !== '' ? $plantilla : null);
        $conclusiones = $this->construirConclusionesInforme($payload !== [] ? $payload : null);

        [$fechaInforme, $horaInforme] = $this->resolverFechaHoraInforme(
            isset($informe['updated_at']) ? (string) $informe['updated_at'] : null,
            isset($procedimiento['fecha']) ? (string) $procedimiento['fecha'] : null,
            isset($procedimiento['hora']) ? (string) $procedimiento['hora'] : null
        );

        $fechaNacimiento = $paciente['fecha_nacimiento'] ?? null;
        $edad = $this->calcularEdad(
            is_string($fechaNacimiento) ? $fechaNacimiento : null,
            $fechaInforme
        );

        $patient = [
            'afiliacion' => (string) ($procedimiento['afiliacion'] ?? ($paciente['afiliacion'] ?? '')),
            'hc_number' => (string) ($paciente['hc_number'] ?? $hcForPatient),
            'archive_number' => (string) ($paciente['hc_number'] ?? $hcForPatient),
            'lname' => (string) ($paciente['lname'] ?? ''),
            'lname2' => (string) ($paciente['lname2'] ?? ''),
            'fname' => (string) ($paciente['fname'] ?? ''),
            'mname' => (string) ($paciente['mname'] ?? ''),
            'sexo' => (string) ($paciente['sexo'] ?? ''),
            'fecha_nacimiento' => (string) ($fechaNacimiento ?? ''),
            'edad' => $edad !== null ? (string) $edad : '',
        ];

        $firmanteId = 0;
        if (isset($payload['firmante_id']) && is_numeric($payload['firmante_id'])) {
            $firmanteId = (int) $payload['firmante_id'];
        }
        if ($firmanteId <= 0 && isset($informe['firmado_por']) && is_numeric($informe['firmado_por'])) {
            $firmanteId = (int) $informe['firmado_por'];
        }
        $firmante = $this->obtenerDatosFirmante($firmanteId > 0 ? $firmanteId : null);

        return [
            'report' => [
                'patient' => $patient,
                'examen' => [
                    'descripcion' => $descripcion,
                    'tipo_examen' => $tipoExamen,
                ],
                'informe' => [
                    'hallazgos' => $hallazgos,
                    'conclusiones' => $conclusiones,
                    'fecha' => $fechaInforme,
                    'hora' => $horaInforme,
                ],
                'firmante' => $firmante,
            ],
            'fecha' => $fechaInforme !== '' ? $fechaInforme : null,
            'hc_number' => $patient['hc_number'] !== '' ? $patient['hc_number'] : $hcForPatient,
            'tipo_examen' => $tipoExamen,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $selectedItems
     * @return array<string, mixed>
     */
    public function buildCobertura012AData(
        string $formId,
        string $hcNumber,
        ?int $examenId = null,
        array $selectedItems = [],
        bool $preserveBaseContext = false
    ): array
    {
        $formId = trim($formId);
        $hcNumber = trim($hcNumber);
        if ($formId === '' || $hcNumber === '') {
            return [];
        }

        $contextoOrigen = $this->resolveSolicitudOrigenContextFor012A($formId, $hcNumber);
        if ($selectedItems !== [] && !$preserveBaseContext) {
            $contextoOrigen = $this->resolverMejorContextoClinico012A($contextoOrigen, $selectedItems);
        }

        $contextFormId = trim((string) ($contextoOrigen['form_id'] ?? $formId));
        $contextHcNumber = trim((string) ($contextoOrigen['hc_number'] ?? $hcNumber));
        $contextExamenId = $examenId ?: (isset($contextoOrigen['examen_id']) ? (int) $contextoOrigen['examen_id'] : null);

        $viewData = $this->obtenerDatosParaVista012A($contextHcNumber, $contextFormId, $contextExamenId);
        if (empty($viewData['examen'])) {
            $viewData = $this->buildCobertura012AFallbackViewData($contextFormId, $contextHcNumber);
        }

        $selectedExamDate = $this->resolveSelectedItemsExamDateTime($selectedItems);
        if ($selectedExamDate !== null) {
            if (!isset($viewData['consulta']) || !is_array($viewData['consulta'])) {
                $viewData['consulta'] = [];
            }
            $viewData['consulta']['fecha'] = $selectedExamDate['date'];
            $viewData['consulta']['created_at'] = $selectedExamDate['raw'];
        }

        $dxDerivacion = [];
        if (!empty($viewData['derivacion']['diagnostico'])) {
            $dxDerivacion[] = ['diagnostico' => $viewData['derivacion']['diagnostico']];
        }

        $estudios012A = [];
        if ($selectedItems !== []) {
            $estudios012A = $this->construirEstudios012AFromSelectedItems($selectedItems);
        }

        if ($estudios012A === []) {
            $estudios012A = $this->construirEstudios012A(
                is_array($viewData['examenes_relacionados'] ?? null) ? $viewData['examenes_relacionados'] : [],
                is_array($viewData['imagenes_solicitadas'] ?? null) ? $viewData['imagenes_solicitadas'] : []
            );
        }

        return [
            'paciente' => $viewData['paciente'] ?? [],
            'consulta' => $viewData['consulta'] ?? [],
            'diagnostico' => $viewData['diagnostico'] ?? [],
            'dx_derivacion' => $dxDerivacion,
            'solicitud' => [
                'created_at' => $selectedExamDate['raw'] ?? ($viewData['examen']['created_at'] ?? null),
                'created_at_date' => $selectedExamDate['date'] ?? ($viewData['examen']['created_at'] ?? null),
                'created_at_time' => $selectedExamDate['time'] ?? ($viewData['examen']['created_at'] ?? null),
            ],
            'examenes_relacionados' => $viewData['examenes_relacionados'] ?? [],
            'imagenes_solicitadas' => $viewData['imagenes_solicitadas'] ?? [],
            'estudios_012a' => $estudios012A,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerDatosParaVista012A(string $hcNumber, string $formId, ?int $examenId = null): array
    {
        $examen = $this->fetchExamenPorFormHc($formId, $hcNumber, $examenId);
        if ($examen === null) {
            $candidatos = $this->fetchExamenesPorFormId($formId);
            $primero = !empty($candidatos) ? $candidatos[0] : null;
            if (is_array($primero)) {
                $hcAlterno = trim((string) ($primero['hc_number'] ?? ''));
                $idAlterno = (int) ($primero['id'] ?? 0);
                if ($hcAlterno !== '') {
                    $examen = $this->fetchExamenPorFormHc($formId, $hcAlterno, $idAlterno > 0 ? $idAlterno : null);
                    if ($examen !== null) {
                        $hcNumber = $hcAlterno;
                    }
                }
            }
        }

        if ($examen === null) {
            return ['examen' => null];
        }

        $consulta = $this->fetchConsultaPorFormHc($formId, $hcNumber) ?? $this->fetchConsultaPorFormId($formId) ?? [];
        $hcConsulta = trim((string) ($consulta['hc_number'] ?? ''));

        $paciente = $this->fetchPatientData($hcNumber);
        if ($paciente === [] && $hcConsulta !== '' && $hcConsulta !== $hcNumber) {
            $paciente = $this->fetchPatientData($hcConsulta);
        }
        if ($paciente !== [] && trim((string) ($paciente['hc_number'] ?? '')) === '' && $hcConsulta !== '') {
            $paciente['hc_number'] = $hcConsulta;
        }

        if (trim((string) ($consulta['doctor'] ?? '')) === '') {
            $doctorFromJoin = trim((string) ($consulta['doctor_nombre'] ?? ($consulta['procedimiento_doctor'] ?? '')));
            if ($doctorFromJoin !== '') {
                $consulta['doctor'] = $doctorFromJoin;
            }
        }

        $examenesRelacionados = $this->fetchExamenesPorFormHc($formId, $hcNumber);
        if ($examenesRelacionados === []) {
            $examenesRelacionados = $this->fetchExamenesPorFormId($formId);
        }
        $examenesRelacionados = array_map(fn(array $row): array => $this->transformExamenRow($row), $examenesRelacionados);

        $consultaSolicitante = trim((string) ($consulta['solicitante'] ?? ''));
        if ($consultaSolicitante === '') {
            foreach ($examenesRelacionados as $rel) {
                $candidate = trim((string) ($rel['solicitante'] ?? ''));
                if ($candidate === '') {
                    continue;
                }
                $consultaSolicitante = $candidate;
                break;
            }
            if ($consultaSolicitante !== '') {
                $consulta['solicitante'] = $consultaSolicitante;
            }
        }

        if (trim((string) ($consulta['doctor'] ?? '')) === '') {
            $doctor = '';
            foreach ($examenesRelacionados as $rel) {
                $candidate = trim((string) ($rel['doctor'] ?? ($rel['solicitante'] ?? '')));
                if ($candidate === '') {
                    continue;
                }
                $doctor = $candidate;
                break;
            }
            if ($doctor === '') {
                $doctor = (string) ($this->obtenerDoctorProcedimientoProyectado($formId, $hcNumber) ?? '');
            }
            if ($doctor !== '') {
                $consulta['doctor'] = $doctor;
            }
        }

        $consulta = $this->enriquecerDoctorConsulta012A($consulta);
        $diagnosticos = $this->extraerDiagnosticosDesdeConsulta($consulta);

        $imagenesSolicitadas = $this->extraerImagenesSolicitadas(
            $consulta['examenes'] ?? null,
            $examenesRelacionados,
            []
        );

        return [
            'examen' => $examen,
            'paciente' => $paciente,
            'consulta' => $consulta,
            'diagnostico' => $diagnosticos,
            'imagenes_solicitadas' => $imagenesSolicitadas,
            'examenes_relacionados' => $examenesRelacionados,
            'derivacion' => $this->obtenerDerivacionPorFormId($formId) ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCobertura012AFallbackViewData(string $formId, string $hcNumber): array
    {
        $consultaFallback = $this->fetchConsultaPorFormHc($formId, $hcNumber) ?? $this->fetchConsultaPorFormId($formId) ?? [];
        $hcFallback = trim((string) ($consultaFallback['hc_number'] ?? $hcNumber));

        $procedimientoFallback = $this->fetchProcedimientoProyectadoByFormHc($formId, $hcFallback !== '' ? $hcFallback : $hcNumber);
        if ($procedimientoFallback === null) {
            $procedimientoFallback = $this->fetchProcedimientoProyectadoByFormId($formId);
        }

        if (is_array($procedimientoFallback)) {
            $hcProc = trim((string) ($procedimientoFallback['hc_number'] ?? ''));
            if ($hcFallback === '' && $hcProc !== '') {
                $hcFallback = $hcProc;
            }

            if ($consultaFallback === []) {
                $fechaProc = trim((string) ($procedimientoFallback['fecha'] ?? ''));
                $horaProc = trim((string) ($procedimientoFallback['hora'] ?? ''));
                $createdAtProc = trim(($fechaProc !== '' ? $fechaProc : date('Y-m-d')) . ($horaProc !== '' ? (' ' . $horaProc) : ''));
                $consultaFallback = [
                    'form_id' => $formId,
                    'hc_number' => $hcFallback !== '' ? $hcFallback : $hcNumber,
                    'fecha' => $fechaProc,
                    'created_at' => $createdAtProc,
                    'plan' => (string) ($procedimientoFallback['procedimiento_proyectado'] ?? ''),
                ];
            }
        }

        $pacienteFallback = $this->fetchPatientData($hcFallback !== '' ? $hcFallback : $hcNumber);

        $examenesRelacionadosFallback = $this->fetchExamenesPorFormHc($formId, $hcFallback !== '' ? $hcFallback : $hcNumber);
        if ($examenesRelacionadosFallback === []) {
            $examenesRelacionadosFallback = $this->fetchExamenesPorFormId($formId);
        }

        if ($examenesRelacionadosFallback === [] && is_array($procedimientoFallback)) {
            $proc = trim((string) ($procedimientoFallback['procedimiento_proyectado'] ?? ''));
            if ($proc !== '') {
                $codigo = '';
                if (preg_match('/\b(\d{6})\b/', $proc, $matchCodigo) === 1) {
                    $codigo = trim((string) ($matchCodigo[1] ?? ''));
                }

                $examenesRelacionadosFallback[] = [
                    'id' => 0,
                    'hc_number' => $hcFallback !== '' ? $hcFallback : $hcNumber,
                    'form_id' => $formId,
                    'examen_codigo' => $codigo,
                    'examen_nombre' => $proc,
                    'estado' => 'pendiente',
                    'consulta_fecha' => $consultaFallback['fecha'] ?? null,
                    'created_at' => $consultaFallback['created_at'] ?? null,
                ];
            }
        }

        $examenesRelacionadosFallback = array_map(
            fn(array $row): array => $this->transformExamenRow($row),
            $examenesRelacionadosFallback
        );

        $consultaFallback = $this->enriquecerDoctorConsulta012A($consultaFallback);
        $diagnosticosFallback = $this->extraerDiagnosticosDesdeConsulta($consultaFallback);

        return [
            'examen' => ['created_at' => null],
            'paciente' => $pacienteFallback,
            'consulta' => $consultaFallback,
            'diagnostico' => $diagnosticosFallback,
            'derivacion' => [],
            'examenes_relacionados' => $examenesRelacionadosFallback,
            'imagenes_solicitadas' => $this->extraerImagenesSolicitadas(
                $consultaFallback['examenes'] ?? null,
                $examenesRelacionadosFallback,
                []
            ),
        ];
    }

    /**
     * @return array{form_id:string,hc_number:string,examen_id:int|null}
     */
    private function resolveSolicitudOrigenContextFor012A(string $formId, string $hcNumber): array
    {
        $resolvedFormId = trim($formId);
        $resolvedHc = trim($hcNumber);
        $resolvedExamenId = null;

        if ($resolvedFormId === '' || $resolvedHc === '') {
            return ['form_id' => $resolvedFormId, 'hc_number' => $resolvedHc, 'examen_id' => $resolvedExamenId];
        }

        $consultaDirecta = $this->fetchConsultaPorFormHc($resolvedFormId, $resolvedHc);
        if (is_array($consultaDirecta) && $consultaDirecta !== []) {
            return ['form_id' => $resolvedFormId, 'hc_number' => $resolvedHc, 'examen_id' => $resolvedExamenId];
        }

        $procedimiento = $this->fetchProcedimientoProyectadoByFormHc($resolvedFormId, $resolvedHc);
        if ($procedimiento === null) {
            $procedimiento = $this->fetchProcedimientoProyectadoByFormId($resolvedFormId);
        }
        if ($procedimiento === null) {
            return ['form_id' => $resolvedFormId, 'hc_number' => $resolvedHc, 'examen_id' => $resolvedExamenId];
        }

        $hcProc = trim((string) ($procedimiento['hc_number'] ?? ''));
        if ($hcProc !== '') {
            $resolvedHc = $hcProc;
        }

        $tipoExamenRaw = trim((string) ($procedimiento['procedimiento_proyectado'] ?? ''));
        $codigoExamen = $this->extractCodigoFromProcedimiento($tipoExamenRaw);
        $nombreExamen = $this->extractNombreFromProcedimiento($tipoExamenRaw);

        $fechaProc = trim((string) ($procedimiento['fecha'] ?? ''));
        $horaProc = trim((string) ($procedimiento['hora'] ?? ''));
        $fechaReferencia = '';
        if ($fechaProc !== '') {
            $fechaReferencia = $fechaProc . ($horaProc !== '' ? (' ' . $horaProc . ':00') : ' 23:59:59');
        }

        $candidato = $this->buscarConsultaExamenOrigen(
            $resolvedHc,
            $codigoExamen !== '' ? $codigoExamen : null,
            $fechaReferencia !== '' ? $fechaReferencia : null,
            $nombreExamen !== '' ? $nombreExamen : null
        );

        if (is_array($candidato) && $candidato !== []) {
            $resolvedFormId = trim((string) ($candidato['form_id'] ?? $resolvedFormId));
            $resolvedHc = trim((string) ($candidato['hc_number'] ?? $resolvedHc));
            $resolvedExamenId = isset($candidato['id']) ? (int) $candidato['id'] : null;
        }

        return ['form_id' => $resolvedFormId, 'hc_number' => $resolvedHc, 'examen_id' => $resolvedExamenId];
    }

    /**
     * @param array{form_id:string,hc_number:string,examen_id:int|null} $baseContext
     * @param array<int, array<string, mixed>> $selectedItems
     * @return array{form_id:string,hc_number:string,examen_id:int|null}
     */
    private function resolverMejorContextoClinico012A(array $baseContext, array $selectedItems): array
    {
        $candidatos = [];
        $maxFechaPorHc = [];

        $pushCandidato = static function (array $contexto) use (&$candidatos): void {
            $form = trim((string) ($contexto['form_id'] ?? ''));
            $hc = trim((string) ($contexto['hc_number'] ?? ''));
            if ($form === '' || $hc === '') {
                return;
            }

            $key = $form . '|' . $hc;
            if (isset($candidatos[$key])) {
                return;
            }

            $candidatos[$key] = [
                'form_id' => $form,
                'hc_number' => $hc,
                'examen_id' => isset($contexto['examen_id']) ? (int) $contexto['examen_id'] : null,
            ];
        };

        $pushCandidato($baseContext);

        foreach ($selectedItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $form = trim((string) ($item['form_id'] ?? ''));
            $hc = trim((string) ($item['hc_number'] ?? ''));
            if ($form === '' || $hc === '') {
                continue;
            }

            $fechaItemRaw = trim((string) ($item['fecha_examen'] ?? ($item['fecha'] ?? '')));
            if ($fechaItemRaw !== '') {
                $fechaItem = substr($fechaItemRaw, 0, 10);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaItem) === 1) {
                    if (!isset($maxFechaPorHc[$hc]) || strcmp($fechaItem, $maxFechaPorHc[$hc]) > 0) {
                        $maxFechaPorHc[$hc] = $fechaItem;
                    }
                }
            }

            $resolved = $this->resolveSolicitudOrigenContextFor012A($form, $hc);
            $pushCandidato($resolved);
        }

        foreach ($maxFechaPorHc as $hc => $maxFecha) {
            $candClinico = $this->obtenerConsultaClinicaSerOftPorHcHastaFecha((string) $hc, (string) $maxFecha);
            if (is_array($candClinico) && $candClinico !== []) {
                $pushCandidato([
                    'form_id' => trim((string) ($candClinico['form_id'] ?? '')),
                    'hc_number' => trim((string) ($candClinico['hc_number'] ?? $hc)),
                    'examen_id' => null,
                ]);
            }
        }

        $best = $baseContext;
        $bestScore = -1;

        foreach ($candidatos as $cand) {
            $form = trim((string) ($cand['form_id'] ?? ''));
            $hc = trim((string) ($cand['hc_number'] ?? ''));
            if ($form === '' || $hc === '') {
                continue;
            }

            $consulta = $this->fetchConsultaPorFormHc($form, $hc) ?? $this->fetchConsultaPorFormId($form) ?? [];
            if (!is_array($consulta) || $consulta === []) {
                continue;
            }

            $consulta = $this->enriquecerDoctorConsulta012A($consulta);
            $diagnosticos = $this->extraerDiagnosticosDesdeConsulta($consulta);

            $hasFirma = trim((string) ($consulta['doctor_signature_path'] ?? '')) !== ''
                || trim((string) ($consulta['doctor_firma'] ?? '')) !== '';
            $hasDoctor = trim((string) ($consulta['doctor'] ?? ($consulta['procedimiento_doctor'] ?? ''))) !== '';

            $score = (count($diagnosticos) * 10)
                + ($hasFirma ? 3 : 0)
                + (((int) ($consulta['doctor_user_id'] ?? 0) > 0) ? 2 : 0)
                + ($hasDoctor ? 1 : 0);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'form_id' => $form,
                    'hc_number' => trim((string) ($consulta['hc_number'] ?? $hc)) ?: $hc,
                    'examen_id' => isset($cand['examen_id']) ? (int) $cand['examen_id'] : null,
                ];
            }
        }

        return $best;
    }

    /**
     * @param array<int, array<string, mixed>> $selectedItems
     * @return array{raw:string,date:string,time:string}|null
     */
    private function resolveSelectedItemsExamDateTime(array $selectedItems): ?array
    {
        $candidates = [];

        foreach ($selectedItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $raw = trim((string) ($item['fecha_examen'] ?? ($item['fecha'] ?? '')));
            if ($raw === '') {
                continue;
            }

            $timestamp = strtotime($raw);
            if ($timestamp === false) {
                continue;
            }

            $candidates[] = [
                'raw' => date('Y-m-d H:i:s', $timestamp),
                'date' => date('Y-m-d', $timestamp),
                'time' => date('H:i', $timestamp),
                'ts' => $timestamp,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn (array $a, array $b): int => ($a['ts'] <=> $b['ts']));
        $selected = $candidates[0];

        return [
            'raw' => (string) $selected['raw'],
            'date' => (string) $selected['date'],
            'time' => (string) $selected['time'],
        ];
    }

    /**
     * @return array<int, array{linea:string, estado:string}>
     */
    private function construirEstudios012A(array $examenesRelacionados, array $imagenesSolicitadas, bool $preferPendientes = true): array
    {
        $records = [];

        $push = function (?string $nombreRaw, ?string $codigoRaw, ?string $estadoRaw) use (&$records): void {
            $nombre = trim((string) $nombreRaw);
            $codigo = trim((string) $codigoRaw);
            $estado = trim((string) $estadoRaw);
            if ($nombre === '' && $codigo === '') {
                return;
            }

            $parsed = $this->parseProcedimientoImagen($nombre);
            $nombreLimpio = trim((string) ($parsed['texto'] ?? ''));
            $ojo = trim((string) ($parsed['ojo'] ?? ''));
            if ($nombreLimpio === '') {
                $nombreLimpio = $nombre;
            }

            if ($codigo === '') {
                $codigo = (string) ($this->extraerCodigoTarifario($nombreLimpio) ?? '');
            }

            $tarifaDesc = '';
            if ($codigo !== '') {
                $tarifa = $this->obtenerTarifarioPorCodigo($codigo);
                if (is_array($tarifa) && $tarifa !== []) {
                    $tarifaDesc = trim((string) ($tarifa['descripcion'] ?? ($tarifa['short_description'] ?? '')));
                }
            }

            $detalle = $nombreLimpio;
            if ($codigo !== '') {
                $detalle = preg_replace('/\b' . preg_quote($codigo, '/') . '\b\s*[-:]?\s*/iu', '', $detalle) ?? $detalle;
            }
            $detalle = trim((string) $detalle, " -\t\n\r\0\x0B");

            $records[] = [
                'codigo' => $codigo,
                'tarifa_desc' => $tarifaDesc,
                'detalle' => $detalle !== '' ? $detalle : $nombreLimpio,
                'ojo' => $ojo,
                'estado' => $estado,
            ];
        };

        foreach ($examenesRelacionados as $rel) {
            if (!is_array($rel)) {
                continue;
            }
            $push(
                (string) ($rel['examen_nombre'] ?? ($rel['procedimiento'] ?? '')),
                (string) ($rel['examen_codigo'] ?? ($rel['tipo'] ?? '')),
                (string) ($rel['kanban_estado'] ?? ($rel['estado'] ?? ''))
            );
        }

        foreach ($imagenesSolicitadas as $img) {
            if (!is_array($img)) {
                continue;
            }
            $push(
                (string) ($img['nombre'] ?? ($img['examen'] ?? '')),
                (string) ($img['codigo'] ?? ''),
                (string) ($img['estado'] ?? '')
            );
        }

        $unique = [];
        $seen = [];
        foreach ($records as $record) {
            $codigo = trim((string) ($record['codigo'] ?? ''));
            $tarifaDesc = trim((string) ($record['tarifa_desc'] ?? ''));
            $detalle = trim((string) ($record['detalle'] ?? ''));
            $ojo = trim((string) ($record['ojo'] ?? ''));
            if ($codigo === '' && $detalle === '') {
                continue;
            }
            $key = $this->normalizarTexto($codigo . '|' . $tarifaDesc . '|' . $detalle . '|' . $ojo);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $record;
        }

        $aprobados = ['listo-para-agenda', 'completado', 'atendido'];
        $pendientes = [];
        foreach ($unique as $record) {
            $estado = trim((string) ($record['estado'] ?? ''));
            $slug = str_replace(' ', '-', $this->normalizarTexto($estado));
            $isApproved = in_array($slug, $aprobados, true) || str_contains($slug, 'aprob');
            if (!$isApproved) {
                $pendientes[] = $record;
            }
        }

        $target = ($preferPendientes && $pendientes !== []) ? $pendientes : $unique;

        $conteoPorCodigo = [];
        foreach ($target as $record) {
            $codigo = trim((string) ($record['codigo'] ?? ''));
            if ($codigo === '') {
                continue;
            }
            $conteoPorCodigo[$codigo] = ($conteoPorCodigo[$codigo] ?? 0) + 1;
        }

        $result = [];
        foreach ($target as $record) {
            $codigo = trim((string) ($record['codigo'] ?? ''));
            $tarifaDesc = trim((string) ($record['tarifa_desc'] ?? ''));
            $detalle = trim((string) ($record['detalle'] ?? ''));
            $ojo = trim((string) ($record['ojo'] ?? ''));

            if ($codigo !== '' && $tarifaDesc !== '') {
                $linea = $tarifaDesc . ' (' . $codigo . ')';
                if (($conteoPorCodigo[$codigo] ?? 0) > 1) {
                    $suffix = $this->normalizarDetalleEstudio012A($detalle, $tarifaDesc);
                    if ($suffix !== '') {
                        $linea .= ' - ' . $suffix;
                    }
                }
            } elseif ($codigo !== '') {
                $linea = ($detalle !== '' ? $detalle : 'SIN DETALLE') . ' (' . $codigo . ')';
            } else {
                $linea = $detalle;
            }

            if ($ojo !== '') {
                $lineaNorm = $this->normalizarTexto($linea);
                $ojoNorm = $this->normalizarTexto($ojo);
                if ($ojoNorm !== '' && !str_contains($lineaNorm, $ojoNorm)) {
                    $linea .= ' - ' . $ojo;
                }
            }

            $result[] = [
                'linea' => trim($linea),
                'estado' => (string) ($record['estado'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $selectedItems
     * @return array<int, array{linea:string, estado:string}>
     */
    private function construirEstudios012AFromSelectedItems(array $selectedItems): array
    {
        $examenes = [];

        foreach ($selectedItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $formId = trim((string) ($item['form_id'] ?? ''));
            $hcNumber = trim((string) ($item['hc_number'] ?? ''));
            $rawSeleccion = trim((string) ($item['tipo_examen'] ?? ($item['tipo_examen_raw'] ?? '')));
            $rawSeleccionParsed = $this->parseProcedimientoImagen($rawSeleccion);
            $rawSeleccionTexto = trim((string) ($rawSeleccionParsed['texto'] ?? ''));
            $nombre = $rawSeleccion;
            $codigo = trim((string) ($item['codigo'] ?? ($item['examen_codigo'] ?? '')));
            $estado = trim((string) ($item['estado_agenda'] ?? ($item['estado'] ?? '')));

            if ($formId !== '') {
                $informe = $this->fetchInformeImagen($formId);
                if (is_array($informe) && $informe !== []) {
                    $hcInforme = trim((string) ($informe['hc_number'] ?? ''));
                    if ($hcNumber === '' || $hcInforme === '' || $hcInforme === $hcNumber) {
                        $tipoInforme = trim((string) ($informe['tipo_examen'] ?? ''));
                        if ($nombre === '') {
                            $nombre = $tipoInforme;
                        }
                    }
                }
            }

            if ($nombre === '' && $formId !== '' && $hcNumber !== '') {
                $proc = $this->fetchProcedimientoProyectadoByFormHc($formId, $hcNumber);
                if ($proc === null) {
                    $proc = $this->fetchProcedimientoProyectadoByFormId($formId);
                }

                if (is_array($proc) && $proc !== []) {
                    $nombre = trim((string) ($proc['procedimiento_proyectado'] ?? ''));
                    if ($estado === '') {
                        $estado = trim((string) ($proc['estado_agenda'] ?? ''));
                    }
                }
            }

            if ($codigo === '' && $rawSeleccion !== '') {
                $codigo = (string) ($this->extraerCodigoTarifario($rawSeleccionTexto !== '' ? $rawSeleccionTexto : $rawSeleccion) ?? '');
            }
            if ($codigo === '' && $nombre !== '') {
                $codigo = (string) ($this->extraerCodigoTarifario($nombre) ?? '');
            }

            if ($nombre === '' && $codigo === '') {
                continue;
            }

            $examenes[] = [
                'examen_nombre' => $nombre,
                'examen_codigo' => $codigo,
                'estado' => $estado,
            ];
        }

        if ($examenes === []) {
            return [];
        }

        return $this->construirEstudios012A($examenes, [], false);
    }

    private function normalizarDetalleEstudio012A(string $detalle, string $tarifaDesc): string
    {
        $detalle = trim(preg_replace('/\s+/', ' ', $detalle) ?? '');
        $tarifaDesc = trim(preg_replace('/\s+/', ' ', $tarifaDesc) ?? '');
        if ($detalle === '') {
            return '';
        }

        $detalleNorm = $this->normalizarTexto($detalle);
        $tarifaNorm = $this->normalizarTexto($tarifaDesc);
        if ($detalleNorm !== '' && $detalleNorm === $tarifaNorm) {
            return '';
        }

        $detalle = preg_replace('/^OCT\s+/iu', '', $detalle) ?? $detalle;

        return trim($detalle);
    }

    /**
     * @param array<int, array<string, mixed>> $examenesRelacionados
     * @param array<int, array<string, mixed>> $adjuntosCrm
     * @return array<int, array<string, mixed>>
     */
    private function extraerImagenesSolicitadas($rawExamenes, array $examenesRelacionados, array $adjuntosCrm): array
    {
        $items = [];
        if (is_string($rawExamenes) && trim($rawExamenes) !== '') {
            $decoded = json_decode($rawExamenes, true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        } elseif (is_array($rawExamenes)) {
            $items = $rawExamenes;
        }

        if (!is_array($items)) {
            $items = [];
        }

        $normalizedAdjuntos = [];
        foreach ($adjuntosCrm as $adjunto) {
            if (!is_array($adjunto)) {
                continue;
            }
            $normalizedAdjuntos[] = [
                'raw' => $adjunto,
                'search' => $this->normalizarTexto(
                    (string) ($adjunto['descripcion'] ?? '') . ' ' . (string) ($adjunto['nombre_original'] ?? '')
                ),
            ];
        }

        $buildRecord = function ($item, bool $allowNonImage) use ($examenesRelacionados, $normalizedAdjuntos): ?array {
            $nombre = null;
            $codigo = null;
            $fuente = 'Consulta';
            $fecha = null;

            if (is_array($item)) {
                $nombre = trim((string) ($item['nombre'] ?? ($item['examen'] ?? ($item['descripcion'] ?? ''))));
                $codigo = trim((string) ($item['codigo'] ?? ($item['id'] ?? ($item['code'] ?? ''))));
                $fuente = trim((string) ($item['fuente'] ?? ($item['origen'] ?? 'Consulta'))) ?: 'Consulta';
                $fecha = $item['fecha'] ?? null;
            } elseif (is_string($item)) {
                $nombre = trim($item);
            }

            if ($nombre === null || $nombre === '') {
                return null;
            }

            if (!$allowNonImage && !$this->esEstudioImagen($nombre, $codigo)) {
                return null;
            }

            $nombreNorm = $this->normalizarTexto($nombre);
            $match = null;
            foreach ($examenesRelacionados as $rel) {
                if (!is_array($rel)) {
                    continue;
                }

                $relNorm = $this->normalizarTexto((string) ($rel['examen_nombre'] ?? ''));
                if ($relNorm === '') {
                    continue;
                }
                if ($relNorm === $nombreNorm || str_contains($relNorm, $nombreNorm) || str_contains($nombreNorm, $relNorm)) {
                    $match = $rel;
                    break;
                }
            }

            $estado = $match['estado'] ?? 'Solicitado';
            $fuenteFinal = $fuente;
            if (($fuenteFinal === '' || $fuenteFinal === 'Consulta') && !empty($match['solicitante'])) {
                $fuenteFinal = (string) $match['solicitante'];
            }
            $fechaFinal = $match['consulta_fecha'] ?? $fecha ?? $match['created_at'] ?? null;

            $evidencias = [];
            foreach ($normalizedAdjuntos as $adjunto) {
                $search = $adjunto['search'] ?? '';
                if (!is_string($search) || $search === '' || !str_contains($search, $nombreNorm)) {
                    continue;
                }

                $raw = is_array($adjunto['raw'] ?? null) ? $adjunto['raw'] : [];
                $evidencias[] = [
                    'url' => $raw['url'] ?? null,
                    'descripcion' => $raw['descripcion'] ?? null,
                    'nombre' => $raw['nombre_original'] ?? null,
                ];
            }

            return [
                'nombre' => $nombre,
                'codigo' => $codigo !== '' ? $codigo : null,
                'estado' => $estado,
                'fuente' => $fuenteFinal !== '' ? $fuenteFinal : 'Consulta',
                'fecha' => $fechaFinal,
                'evidencias' => $evidencias,
                'evidencias_count' => count($evidencias),
            ];
        };

        $records = [];
        $seen = [];

        foreach ($items as $item) {
            $record = $buildRecord($item, false);
            if (!$record) {
                continue;
            }

            $key = $this->normalizarTexto((string) ($record['nombre'] ?? '') . '|' . (string) ($record['codigo'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $records[] = $record;
        }

        if ($records === []) {
            foreach ($items as $item) {
                $record = $buildRecord($item, true);
                if (!$record) {
                    continue;
                }

                $key = $this->normalizarTexto((string) ($record['nombre'] ?? '') . '|' . (string) ($record['codigo'] ?? ''));
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $records[] = $record;
            }
        }

        return $records;
    }

    private function esEstudioImagen(string $nombre, ?string $codigo = null): bool
    {
        $texto = $this->normalizarTexto($nombre . ' ' . ($codigo ?? ''));
        if ($texto === '') {
            return false;
        }

        $keywords = [
            'oct',
            'tomografia',
            'retinografia',
            'angiografia',
            'ecografia',
            'ultrasonido',
            'biometria',
            'campimetria',
            'paquimetria',
            'resonancia',
            'tac',
            'rx',
            'rayos x',
            'fotografia',
            'imagen',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($texto, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($texto, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $texto = preg_replace('/\p{Mn}/u', '', $normalized) ?? $texto;
            }
        }

        $texto = function_exists('mb_strtolower')
            ? mb_strtolower($texto, 'UTF-8')
            : strtolower($texto);
        $texto = preg_replace('/[^a-z0-9\s]/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * @return array<int, array{dx_code:string, descripcion:string}>
     */
    private function extraerDiagnosticosDesdeConsulta(array $consulta): array
    {
        $raw = $consulta['diagnosticos'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizarDiagnosticosPara012A($decoded);
    }

    /**
     * @param array<int, mixed> $diagnosticos
     * @return array<int, array{dx_code:string, descripcion:string}>
     */
    private function normalizarDiagnosticosPara012A(array $diagnosticos): array
    {
        $result = [];
        $seen = [];

        foreach ($diagnosticos as $dx) {
            if (!is_array($dx)) {
                continue;
            }

            $code = trim((string) ($dx['dx_code'] ?? ($dx['codigo'] ?? '')));
            $desc = trim((string) ($dx['descripcion'] ?? ($dx['descripcion_dx'] ?? ($dx['nombre'] ?? ''))));

            if (($code === '' || $desc === '') && isset($dx['idDiagnostico'])) {
                [$parsedCode, $parsedDesc] = $this->parseDiagnosticoCie10((string) $dx['idDiagnostico']);
                if ($code === '') {
                    $code = $parsedCode;
                }
                if ($desc === '') {
                    $desc = $parsedDesc;
                }
            }

            if ($code === '' && $desc === '') {
                continue;
            }

            $key = strtoupper($code . '|' . $desc);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $result[] = [
                'dx_code' => $code,
                'descripcion' => $desc,
            ];
        }

        return $result;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseDiagnosticoCie10(string $value): array
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
     * @param array<string, mixed> $consulta
     * @return array<string, mixed>
     */
    private function enriquecerDoctorConsulta012A(array $consulta): array
    {
        $hasDoctorNames = trim((string) ($consulta['doctor_fname'] ?? '')) !== ''
            || trim((string) ($consulta['doctor_lname'] ?? '')) !== '';
        $hasFirma = trim((string) ($consulta['doctor_signature_path'] ?? '')) !== ''
            || trim((string) ($consulta['doctor_firma'] ?? '')) !== '';

        if ($hasDoctorNames && $hasFirma) {
            return $consulta;
        }

        $doctorNombreRef = trim((string) ($consulta['doctor'] ?? ''));
        if ($doctorNombreRef === '') {
            $doctorNombreRef = trim((string) ($consulta['doctor_nombre'] ?? ($consulta['procedimiento_doctor'] ?? '')));
        }
        if ($doctorNombreRef === '') {
            return $consulta;
        }

        $usuario = $this->obtenerUsuarioPorDoctorNombre($doctorNombreRef);
        if (!is_array($usuario) || $usuario === []) {
            return $consulta;
        }

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
        if (trim((string) ($consulta['doctor_signature_path'] ?? '')) === '') {
            $consulta['doctor_signature_path'] = (string) ($usuario['signature_path'] ?? '');
        }
        if (trim((string) ($consulta['doctor_firma'] ?? '')) === '') {
            $consulta['doctor_firma'] = (string) ($usuario['firma'] ?? '');
        }
        if ((int) ($consulta['doctor_user_id'] ?? 0) <= 0 && isset($usuario['id'])) {
            $consulta['doctor_user_id'] = (int) $usuario['id'];
        }

        return $consulta;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerUsuarioPorDoctorNombre(string $doctorNombre): ?array
    {
        $doctorNombre = trim($doctorNombre);
        if ($doctorNombre === '') {
            return null;
        }

        $variantes = $this->buildDoctorNombreVariantes($doctorNombre);
        if ($variantes === []) {
            return null;
        }

        $nombreNormPlaceholders = implode(', ', array_fill(0, count($variantes), '?'));
        $nombreRevPlaceholders = implode(', ', array_fill(0, count($variantes), '?'));

        $params = array_merge($variantes, $variantes);

        $sql = 'SELECT
                    u.id,
                    u.first_name,
                    u.middle_name,
                    u.last_name,
                    u.second_last_name,
                    u.cedula,
                    u.signature_path,
                    u.firma,
                    u.nombre
                FROM users u
                WHERE (
                    u.nombre_norm IN (' . $nombreNormPlaceholders . ')
                    OR u.nombre_norm_rev IN (' . $nombreRevPlaceholders . ')
                )
                  AND (
                    UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMÓLOGO"
                    OR UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMOLOGO"
                  )
                ORDER BY u.id ASC
                LIMIT 1';

        $row = DB::selectOne($sql, $params);
        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<int, string>
     */
    private function buildDoctorNombreVariantes(string $doctorNombre): array
    {
        $base = strtoupper(preg_replace('/\s+/', ' ', trim($doctorNombre)) ?? trim($doctorNombre));
        if ($base === '') {
            return [];
        }

        $variantes = [$base];
        $sinSns = preg_replace('/\bSNS\b/u', ' ', $base) ?? $base;
        $sinSns = trim(preg_replace('/\s+/', ' ', $sinSns) ?? $sinSns);
        if ($sinSns !== '' && $sinSns !== $base) {
            $variantes[] = $sinSns;
        }

        return array_values(array_unique($variantes));
    }

    /**
     * @return array<string, mixed>
     */
    private function transformExamenRow(array $row): array
    {
        if (empty($row['fecha'] ?? null)) {
            $row['fecha'] = $row['consulta_fecha'] ?? ($row['created_at'] ?? null);
        }

        if (empty($row['procedimiento'] ?? null)) {
            $row['procedimiento'] = $row['examen_nombre'] ?? ($row['examen_codigo'] ?? null);
        }

        if (empty($row['tipo'] ?? null)) {
            $row['tipo'] = $row['examen_codigo'] ?? ($row['examen_nombre'] ?? null);
        }

        if (empty($row['observacion'] ?? null)) {
            $row['observacion'] = $row['observaciones'] ?? null;
        }

        if (empty($row['ojo'] ?? null)) {
            $row['ojo'] = $row['lateralidad'] ?? null;
        }

        return $row;
    }

    private function extractCodigoFromProcedimiento(string $procedimiento): string
    {
        if ($procedimiento === '') {
            return '';
        }

        if (preg_match('/\b(\d{6})\b/', $procedimiento, $match) === 1) {
            return trim((string) ($match[1] ?? ''));
        }

        return '';
    }

    private function extractNombreFromProcedimiento(string $procedimiento): string
    {
        $procedimiento = trim(preg_replace('/\s+/', ' ', $procedimiento) ?? '');
        if ($procedimiento === '') {
            return '';
        }

        $parts = array_values(array_filter(array_map('trim', explode(' - ', $procedimiento)), static fn($part): bool => $part !== ''));
        foreach ($parts as $part) {
            if (preg_match('/\b\d{6}\b/', $part) !== 1) {
                continue;
            }
            $nombre = trim(preg_replace('/\b\d{6}\s*[-:]?\s*/', '', $part) ?? '');
            if ($nombre !== '') {
                return $nombre;
            }
        }

        return '';
    }

    private function mapearPlantillaInforme(string $tipoExamen): ?string
    {
        $texto = $this->normalizarTexto($tipoExamen);
        if ($texto === '') {
            return null;
        }

        if (str_contains($texto, 'angio')) {
            return 'angio';
        }
        if (str_contains($texto, 'angulo')) {
            return 'angulo';
        }
        if (str_contains($texto, 'auto') || str_contains($texto, 'autorefrac')) {
            return 'auto';
        }
        if (str_contains($texto, 'biometria') || str_contains($texto, 'biometr')) {
            return 'biometria';
        }
        if (str_contains($texto, '281197') || (str_contains($texto, 'microscopia') && str_contains($texto, 'especular'))) {
            return 'microespecular';
        }
        if (str_contains($texto, '281229') || str_contains($texto, 'paquimetr')) {
            return 'paquimetria';
        }
        if (
            str_contains($texto, 'oct')
            && (
                str_contains($texto, 'cornea')
                || str_contains($texto, 'corneal')
                || str_contains($texto, 'esclera')
            )
        ) {
            return 'octcornea';
        }
        if (str_contains($texto, 'cornea') || str_contains($texto, 'corneal') || str_contains($texto, 'topograf')) {
            return 'cornea';
        }
        if (str_contains($texto, 'campo visual') || str_contains($texto, 'campimet') || preg_match('/\bcv\b/', $texto) === 1) {
            return 'cv';
        }
        if (str_contains($texto, 'eco') || str_contains($texto, 'ecografia')) {
            return 'eco';
        }
        if (
            str_contains($texto, 'oct') && (
                str_contains($texto, 'nervio')
                || str_contains($texto, 'papila')
                || str_contains($texto, 'cfnr')
                || str_contains($texto, 'fibras nerviosas')
                || str_contains($texto, 'rnfl')
            )
        ) {
            return 'octno';
        }
        if (str_contains($texto, 'oct')) {
            return 'octm';
        }
        if (str_contains($texto, 'retino') || str_contains($texto, 'retin')) {
            return 'retino';
        }

        return null;
    }

    /**
     * @return array{texto:string,ojo:string}
     */
    private function parseProcedimientoImagen(?string $raw): array
    {
        $texto = trim((string) ($raw ?? ''));
        $ojo = '';

        if ($texto !== '' && preg_match('/\s-\s(AMBOS OJOS|IZQUIERDO|DERECHO|OD|OI|AO)\s*$/i', $texto, $match) === 1) {
            $ojo = strtoupper(trim((string) ($match[1] ?? '')));
            $texto = trim(substr($texto, 0, -strlen((string) $match[0])));
        }

        if ($texto !== '') {
            $partes = preg_split('/\s-\s/', $texto) ?: [];
            if (isset($partes[0]) && strcasecmp(trim((string) $partes[0]), 'IMAGENES') === 0) {
                array_shift($partes);
            }
            if (isset($partes[0]) && preg_match('/^IMA[-_]/i', trim((string) $partes[0])) === 1) {
                array_shift($partes);
            }
            $texto = trim(implode(' - ', array_map(static fn($value): string => trim((string) $value), $partes)));
        }

        $ojoMap = [
            'OD' => 'Derecho',
            'OI' => 'Izquierdo',
            'AO' => 'Ambos ojos',
            'DERECHO' => 'Derecho',
            'IZQUIERDO' => 'Izquierdo',
            'AMBOS OJOS' => 'Ambos ojos',
        ];

        return [
            'texto' => $texto,
            'ojo' => $ojoMap[$ojo] ?? $ojo,
        ];
    }

    private function construirHallazgosInforme(?array $payload, ?string $plantilla): string
    {
        if (!$payload || !is_array($payload)) {
            return '';
        }

        $plantilla = $plantilla ?? '';

        if ($plantilla === 'octm') {
            $defecto = 'Arquitectura retiniana bien definida, fóvea con depresión central bien delineada, epitelio pigmentario continuo y uniforme, membrana limitante interna es hiporreflectiva y continua, células de Müller están bien alineadas sin signos de edema o tracción.';
            $ctmOd = trim((string) ($payload['inputOD'] ?? ''));
            $ctmOi = trim((string) ($payload['inputOI'] ?? ''));
            $textOd = trim((string) ($payload['textOD'] ?? ''));
            $textOi = trim((string) ($payload['textOI'] ?? ''));

            if ($ctmOd !== '' && $textOd === '') {
                $textOd = $defecto;
            }
            if ($ctmOi !== '' && $textOi === '') {
                $textOi = $defecto;
            }

            $lines = [];
            if ($ctmOd !== '') {
                $lines[] = 'GROSOR FOVEAL PROMEDIO OJO DERECHO: ' . $ctmOd . 'um';
            }
            if ($ctmOi !== '') {
                $lines[] = 'GROSOR FOVEAL PROMEDIO OJO IZQUIERDO: ' . $ctmOi . 'um';
            }

            if ($textOd !== '' || $textOi !== '') {
                $lines[] = 'LAS IMÁGENES SON SUGESTIVAS DE:';
                if ($textOd !== '') {
                    $lines[] = '**Ojo Derecho: **' . $textOd;
                }
                if ($textOi !== '') {
                    $lines[] = '**Ojo Izquierdo: **' . $textOi;
                }
            }

            return trim(implode("\n", $lines));
        }

        if ($plantilla === 'octno') {
            $odValor = trim((string) ($payload['inputOD'] ?? ''));
            $oiValor = trim((string) ($payload['inputOI'] ?? ''));

            $odCuadrantes = $this->resolverCuadrantesOctNo($payload, 'od');
            $oiCuadrantes = $this->resolverCuadrantesOctNo($payload, 'oi');

            $lines = [];
            $odBloque = $this->buildOctNoEyeBlock('OD', $odValor, $odCuadrantes);
            $oiBloque = $this->buildOctNoEyeBlock('OI', $oiValor, $oiCuadrantes);

            if ($odBloque !== '') {
                $lines[] = $odBloque;
            }
            if ($oiBloque !== '') {
                $lines[] = $oiBloque;
            }

            return trim(implode("\n\n", $lines));
        }

        if ($plantilla === 'biometria') {
            $odCamara = trim((string) ($payload['camaraOD'] ?? ''));
            $odCristalino = trim((string) ($payload['cristalinoOD'] ?? ''));
            $odAxial = trim((string) ($payload['axialOD'] ?? ''));
            $oiCamara = trim((string) ($payload['camaraOI'] ?? ''));
            $oiCristalino = trim((string) ($payload['cristalinoOI'] ?? ''));
            $oiAxial = trim((string) ($payload['axialOI'] ?? ''));

            $lines = [];
            if ($odCamara !== '' || $odCristalino !== '' || $odAxial !== '') {
                $lines[] = '**Ojo Derecho:**';
                if ($odCamara !== '') {
                    $lines[] = 'Cámara anterior: ' . $odCamara;
                }
                if ($odCristalino !== '') {
                    $lines[] = 'Cristalino: ' . $odCristalino;
                }
                if ($odAxial !== '') {
                    $lines[] = 'Longitud axial: ' . $odAxial;
                }
            }

            if ($oiCamara !== '' || $oiCristalino !== '' || $oiAxial !== '') {
                $lines[] = '**Ojo Izquierdo:**';
                if ($oiCamara !== '') {
                    $lines[] = 'Cámara anterior: ' . $oiCamara;
                }
                if ($oiCristalino !== '') {
                    $lines[] = 'Cristalino: ' . $oiCristalino;
                }
                if ($oiAxial !== '') {
                    $lines[] = 'Longitud axial: ' . $oiAxial;
                }
            }

            return trim(implode("\n", $lines));
        }

        if ($plantilla === 'cornea') {
            $buildEye = function (string $suffix, string $label) use ($payload): array {
                $kFlat = trim((string) ($payload['kFlat' . $suffix] ?? ''));
                $axisFlat = trim((string) ($payload['axisFlat' . $suffix] ?? ''));
                $kSteep = trim((string) ($payload['kSteep' . $suffix] ?? ''));
                $axisSteep = trim((string) ($payload['axisSteep' . $suffix] ?? ''));
                $cilindro = trim((string) ($payload['cilindro' . $suffix] ?? ''));
                $kPromedio = trim((string) ($payload['kPromedio' . $suffix] ?? ''));

                $flatNum = is_numeric(str_replace(',', '.', $kFlat)) ? (float) str_replace(',', '.', $kFlat) : null;
                $steepNum = is_numeric(str_replace(',', '.', $kSteep)) ? (float) str_replace(',', '.', $kSteep) : null;
                $axisFlatNum = is_numeric($axisFlat) ? (int) round((float) $axisFlat) : null;

                if ($axisSteep === '' && $axisFlatNum !== null) {
                    $calcAxis = $axisFlatNum + 90;
                    while ($calcAxis > 180) {
                        $calcAxis -= 180;
                    }
                    while ($calcAxis <= 0) {
                        $calcAxis += 180;
                    }
                    $axisSteep = (string) $calcAxis;
                }
                if ($cilindro === '' && $flatNum !== null && $steepNum !== null) {
                    $cilindro = number_format(abs($steepNum - $flatNum), 2, '.', '');
                }
                if ($kPromedio === '' && $flatNum !== null && $steepNum !== null) {
                    $kPromedio = number_format(($flatNum + $steepNum) / 2, 2, '.', '');
                }

                $lines = [];
                if ($kFlat !== '' || $axisFlat !== '' || $kSteep !== '' || $axisSteep !== '' || $cilindro !== '' || $kPromedio !== '') {
                    $lines[] = '**' . $label . ':**';
                    if ($kFlat !== '') {
                        $lines[] = 'K Flat: ' . $kFlat;
                    }
                    if ($axisFlat !== '') {
                        $lines[] = 'Axis: ' . $axisFlat;
                    }
                    if ($kSteep !== '') {
                        $lines[] = 'K Steep: ' . $kSteep;
                    }
                    if ($axisSteep !== '') {
                        $lines[] = 'Axis (steep): ' . $axisSteep;
                    }
                    if ($cilindro !== '') {
                        $lines[] = 'Cilindro: ' . $cilindro;
                    }
                    if ($kPromedio !== '') {
                        $lines[] = 'K Promedio: ' . $kPromedio;
                    }
                }
                return $lines;
            };

            $od = $buildEye('OD', 'Ojo Derecho');
            $oi = $buildEye('OI', 'Ojo Izquierdo');
            return trim(implode("\n", array_merge($od, $oi)));
        }

        if ($plantilla === 'microespecular') {
            $odDensidad = trim((string) ($payload['densidadOD'] ?? ''));
            $odDesv = trim((string) ($payload['desviacionOD'] ?? ''));
            $odCv = trim((string) ($payload['coefVarOD'] ?? ''));
            $oiDensidad = trim((string) ($payload['densidadOI'] ?? ''));
            $oiDesv = trim((string) ($payload['desviacionOI'] ?? ''));
            $oiCv = trim((string) ($payload['coefVarOI'] ?? ''));

            $lines = [];
            if ($odDensidad !== '' || $odDesv !== '' || $odCv !== '') {
                $lines[] = '**Ojo Derecho:**';
                if ($odDensidad !== '') {
                    $lines[] = 'Densidad celular: ' . $odDensidad;
                }
                if ($odDesv !== '') {
                    $lines[] = 'Desviación estándar: ' . $odDesv;
                }
                if ($odCv !== '') {
                    $lines[] = 'Coeficiente de variación: ' . $odCv;
                }
            }

            if ($oiDensidad !== '' || $oiDesv !== '' || $oiCv !== '') {
                $lines[] = '**Ojo Izquierdo:**';
                if ($oiDensidad !== '') {
                    $lines[] = 'Densidad celular: ' . $oiDensidad;
                }
                if ($oiDesv !== '') {
                    $lines[] = 'Desviación estándar: ' . $oiDesv;
                }
                if ($oiCv !== '') {
                    $lines[] = 'Coeficiente de variación: ' . $oiCv;
                }
            }

            return trim(implode("\n", $lines));
        }

        if ($plantilla === 'cv') {
            $od = $this->buildCvEyeBlock('OD', $payload);
            $oi = $this->buildCvEyeBlock('OI', $payload);

            return trim(implode("\n\n", array_values(array_filter([$od, $oi], static fn(string $value): bool => $value !== ''))));
        }

        if ($plantilla === 'paquimetria') {
            $od = trim((string) ($payload['inputOD'] ?? ''));
            $oi = trim((string) ($payload['inputOI'] ?? ''));
            $lines = [];
            if ($od !== '') {
                $lines[] = '**Ojo Derecho:**';
                $lines[] = 'Espesor corneal central: ' . $od . ' micras';
            }
            if ($oi !== '') {
                $lines[] = '**Ojo Izquierdo:**';
                $lines[] = 'Espesor corneal central: ' . $oi . ' micras';
            }
            return trim(implode("\n", $lines));
        }

        if ($plantilla === 'octcornea') {
            $od = trim((string) ($payload['textOD'] ?? ''));
            $oi = trim((string) ($payload['textOI'] ?? ''));
            $lines = [];
            if ($od !== '') {
                $lines[] = '**Ojo Derecho:**';
                $lines[] = $od;
            }
            if ($oi !== '') {
                $lines[] = '**Ojo Izquierdo:**';
                $lines[] = $oi;
            }
            return trim(implode("\n", $lines));
        }

        $od = trim((string) ($payload['inputOD'] ?? ''));
        $oi = trim((string) ($payload['inputOI'] ?? ''));

        if ($plantilla === 'angulo') {
            if ($od !== '' && preg_match('/°$/', $od) !== 1) {
                $od .= '°';
            }
            if ($oi !== '' && preg_match('/°$/', $oi) !== 1) {
                $oi .= '°';
            }
        }

        $lines = [];
        if ($od !== '') {
            $lines[] = '**Ojo Derecho: **' . $od;
        }
        if ($oi !== '') {
            $lines[] = '**Ojo Izquierdo: **' . $oi;
        }

        return trim(implode("\n", $lines));
    }

    private function buildCvEyeBlock(string $eye, array $payload): string
    {
        $suffix = strtoupper($eye) === 'OI' ? 'OI' : 'OD';
        $label = $suffix === 'OD' ? 'OJO: DERECHO' : 'OJO: IZQUIERDO';
        $text = trim((string) ($payload['input' . $suffix] ?? ''));
        $dln = $this->payloadFlagEnabled($payload['checkbox' . $suffix . '_dln'] ?? null);
        $amaurosis = $this->payloadFlagEnabled($payload['checkbox' . $suffix . '_amaurosis'] ?? null);

        if (!$dln && !$amaurosis && $text === '') {
            return '';
        }

        $lines = [
            $label,
            'SE REALIZA CAMPO VISUAL OCTOPUS 600 IMPRESIÓN HFA.',
            'ESTRATEGIA: 24.2 DINÁMICO',
            'CONFIABILIDAD: BUENA',
        ];

        if ($amaurosis) {
            $lines[] = 'SENSIBILIDAD FOVEAL: NULA';
            if ($text !== '') {
                $lines[] = 'LECTURA: ' . $text;
            }
            $lines[] = 'CONCLUSIONES: CAMPO VISUAL AMAUROTICO';
            return implode("\n", $lines);
        }

        $lines[] = 'SENSIBILIDAD FOVEAL: ACTIVA';

        if ($dln) {
            $lines[] = 'CONCLUSIONES: CAMPO VISUAL DENTRO DE LIMITES NORMALES';
            $lines[] = '';
            $lines[] = 'SE RECOMIENDA CORRELACIONAR CON CLÍNICA.';
            return implode("\n", $lines);
        }

        if ($text !== '') {
            $lines[] = 'LECTURA: ' . $text;
        }
        $lines[] = 'CONCLUSIONES: CAMPO VISUAL FUERA DE LIMITES NORMALES';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function resolverCuadrantesOctNo(array $payload, string $eye): array
    {
        $eye = strtolower($eye) === 'oi' ? 'oi' : 'od';

        $map = [
            'INF' => [
                'octno_' . $eye . '_inf',
                'checkboxI' . ($eye === 'oi' ? '_OI' : ''),
            ],
            'SUP' => [
                'octno_' . $eye . '_sup',
                'checkboxS' . ($eye === 'oi' ? '_OI' : ''),
            ],
            'NAS' => [
                'octno_' . $eye . '_nas',
                'checkboxN' . ($eye === 'oi' ? '_OI' : ''),
            ],
            'TEMP' => [
                'octno_' . $eye . '_temp',
                'checkboxT' . ($eye === 'oi' ? '_OI' : ''),
            ],
        ];

        $activos = [];
        foreach ($map as $label => $keys) {
            foreach ($keys as $key) {
                if ($this->payloadFlagEnabled($payload[$key] ?? null)) {
                    $activos[] = $label;
                    break;
                }
            }
        }

        return $activos;
    }

    private function payloadFlagEnabled($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value > 0;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return false;
        }

        return in_array(strtolower($text), ['1', 'true', 'on', 'yes', 'si', 'sí'], true);
    }

    /**
     * @param array<int, string> $cuadrantes
     */
    private function buildOctNoEyeBlock(string $eye, string $valor, array $cuadrantes): string
    {
        $valorNum = (float) str_replace(',', '.', $valor);
        $tieneValor = trim($valor) !== '';
        $tieneCuadrantes = !empty($cuadrantes);

        if (!$tieneValor && !$tieneCuadrantes) {
            return '';
        }

        $clasificacion = 'AL BORDE DE LIMITES NORMALES';
        if ($tieneValor && $valorNum >= 85) {
            $clasificacion = 'DENTRO DE LIMITES NORMALES';
        } elseif ($tieneCuadrantes) {
            $clasificacion = 'FUERA DE LIMITES NORMALES';
        }

        $lines = [];
        $lines[] = $eye === 'OD' ? 'OJO DERECHO' : 'OJO IZQUIERDO';
        $lines[] = 'CONFIABILIDAD: BUENA';

        if ($tieneCuadrantes) {
            $lines[] = 'SE APRECIA DISMINUCIÓN DEL ESPESOR DE CAPA DE FIBRAS NERVIOSAS RETINALES EN CUADRANTES ' . implode(', ', $cuadrantes) . '.';
        }

        if ($tieneValor) {
            $lines[] = 'PROMEDIO ESPESOR CFNR ' . $eye . ': ' . $valor . 'UM';
        }

        $lines[] = 'CLASIFICACIÓN: ' . $clasificacion;

        return implode("\n", $lines);
    }

    private function construirConclusionesInforme(?array $payload): string
    {
        if (!$payload || !is_array($payload)) {
            return '';
        }

        $keys = [
            'conclusiones',
            'conclusion',
            'conclusion_general',
            'conclusionGeneral',
            'conclusiones_generales',
            'conclusion_texto',
            'conclusionTexto',
            'diagnostico',
            'observaciones',
        ];

        foreach ($keys as $key) {
            if (!isset($payload[$key])) {
                continue;
            }
            $value = trim((string) $payload[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolverFechaHoraInforme(?string $updatedAt, ?string $fechaExamen, ?string $horaExamen): array
    {
        $fecha = '';
        $hora = '';

        if ($updatedAt) {
            $timestamp = strtotime($updatedAt);
            if ($timestamp !== false) {
                $fecha = date('Y-m-d', $timestamp);
                $hora = date('H:i', $timestamp);
            }
        }

        if ($fecha === '' && $fechaExamen) {
            $fecha = (string) $fechaExamen;
        }

        if ($hora === '' && $horaExamen) {
            $hora = substr((string) $horaExamen, 0, 5);
        }

        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        if ($hora === '') {
            $hora = date('H:i');
        }

        return [$fecha, $hora];
    }

    private function calcularEdad(?string $fechaNacimiento, ?string $fechaReferencia): ?int
    {
        $fechaNacimiento = trim((string) ($fechaNacimiento ?? ''));
        if ($fechaNacimiento === '') {
            return null;
        }

        try {
            $dob = new DateTimeImmutable($fechaNacimiento);
            $ref = $fechaReferencia && trim($fechaReferencia) !== ''
                ? new DateTimeImmutable($fechaReferencia)
                : new DateTimeImmutable('now');
            return $dob->diff($ref)->y;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extraerCodigoTarifario(string $texto): ?string
    {
        if ($texto === '') {
            return null;
        }

        if (preg_match_all('/\b(\d{5,6})\b/', $texto, $matches) === 1 || !empty($matches[1])) {
            $candidatos = array_values(array_unique($matches[1] ?? []));

            foreach ($candidatos as $candidate) {
                if ($this->obtenerTarifarioPorCodigo((string) $candidate) !== null) {
                    return (string) $candidate;
                }
            }

            if (!empty($candidatos)) {
                return (string) $candidatos[0];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerTarifarioPorCodigo(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $row = DB::selectOne(
            'SELECT codigo, descripcion, short_description FROM tarifario_2014 WHERE codigo = ? LIMIT 1',
            [$codigo]
        );
        if (is_object($row)) {
            return (array) $row;
        }

        $codigoSinCeros = ltrim($codigo, '0');
        if ($codigoSinCeros === '' || $codigoSinCeros === $codigo) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT codigo, descripcion, short_description FROM tarifario_2014 WHERE codigo = ? LIMIT 1',
            [$codigoSinCeros]
        );

        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array{nombres:string,apellido1:string,apellido2:string,documento:string,registro:string,firma:string,signature_path:string}
     */
    private function obtenerDatosFirmante(?int $firmanteId): array
    {
        if (!$firmanteId) {
            return [
                'nombres' => '',
                'apellido1' => '',
                'apellido2' => '',
                'documento' => '',
                'registro' => '',
                'firma' => '',
                'signature_path' => '',
            ];
        }

        $row = DB::selectOne('SELECT * FROM users WHERE id = ? LIMIT 1', [$firmanteId]);
        $user = is_object($row) ? (array) $row : [];

        $nombres = trim((string) ($user['first_name'] ?? ''));
        $segundoNombre = trim((string) ($user['middle_name'] ?? ''));
        if ($segundoNombre !== '') {
            $nombres = trim($nombres . ' ' . $segundoNombre);
        }

        return [
            'nombres' => $nombres,
            'apellido1' => trim((string) ($user['last_name'] ?? '')),
            'apellido2' => trim((string) ($user['second_last_name'] ?? '')),
            'documento' => trim((string) ($user['cedula'] ?? '')),
            'registro' => trim((string) ($user['registro'] ?? '')),
            'firma' => (string) ($user['firma'] ?? ''),
            'signature_path' => (string) ($user['signature_path'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject($raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonArray($raw): array
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
     * @return array<string, mixed>
     */
    private function fetchPatientData(string $hcNumber): array
    {
        $hcNumber = trim($hcNumber);
        if ($hcNumber === '') {
            return [];
        }

        $row = DB::selectOne(
            'SELECT hc_number, fname, mname, lname, lname2, sexo, fecha_nacimiento, afiliacion
             FROM patient_data
             WHERE hc_number = ?
             LIMIT 1',
            [$hcNumber]
        );

        return is_object($row) ? (array) $row : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProcedimientoProyectadoByFormHc(string $formId, string $hcNumber): ?array
    {
        $row = DB::selectOne(
            'SELECT pp.id, pp.form_id, pp.hc_number, pp.procedimiento_proyectado, pp.fecha, pp.hora, pp.afiliacion, pp.estado_agenda
             FROM procedimiento_proyectado pp
             WHERE pp.form_id = ? AND pp.hc_number = ?
             LIMIT 1',
            [$formId, $hcNumber]
        );

        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchProcedimientoProyectadoByFormId(string $formId): ?array
    {
        $row = DB::selectOne(
            'SELECT pp.id, pp.form_id, pp.hc_number, pp.procedimiento_proyectado, pp.fecha, pp.hora, pp.afiliacion, pp.estado_agenda
             FROM procedimiento_proyectado pp
             WHERE pp.form_id = ?
             ORDER BY pp.id DESC
             LIMIT 1',
            [$formId]
        );

        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchInformeImagen(string $formId): ?array
    {
        $row = DB::selectOne(
            'SELECT id, form_id, hc_number, tipo_examen, plantilla, payload_json, firmado_por, created_by, updated_by, created_at, updated_at
             FROM imagenes_informes
             WHERE form_id = ?
             LIMIT 1',
            [$formId]
        );

        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchExamenPorFormHc(string $formId, string $hcNumber, ?int $examenId = null): ?array
    {
        $sql = 'SELECT
                    ce.id,
                    ce.hc_number,
                    ce.form_id,
                    ce.examen_codigo,
                    ce.examen_nombre,
                    ce.doctor,
                    ce.solicitante,
                    ce.estado,
                    ce.prioridad,
                    ce.lateralidad,
                    ce.observaciones,
                    ce.consulta_fecha,
                    ce.created_at,
                    ce.updated_at
                FROM consulta_examenes ce
                WHERE ce.form_id = ?
                  AND ce.hc_number = ?';
        $params = [$formId, $hcNumber];

        if ($examenId !== null && $examenId > 0) {
            $sql .= ' AND ce.id = ?';
            $params[] = $examenId;
        }

        $sql .= ' ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC, ce.id DESC LIMIT 1';

        $row = DB::selectOne($sql, $params);
        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchExamenesPorFormHc(string $formId, string $hcNumber): array
    {
        $rows = DB::select(
            'SELECT
                ce.id,
                ce.hc_number,
                ce.form_id,
                ce.examen_codigo,
                ce.examen_nombre,
                ce.estado,
                ce.doctor,
                ce.solicitante,
                ce.consulta_fecha,
                ce.created_at
             FROM consulta_examenes ce
             WHERE ce.form_id = ?
               AND ce.hc_number = ?
             ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC, ce.id DESC',
            [$formId, $hcNumber]
        );

        return array_map(static fn($row): array => (array) $row, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchExamenesPorFormId(string $formId): array
    {
        $rows = DB::select(
            'SELECT
                ce.id,
                ce.hc_number,
                ce.form_id,
                ce.examen_codigo,
                ce.examen_nombre,
                ce.estado,
                ce.doctor,
                ce.solicitante,
                ce.consulta_fecha,
                ce.created_at
             FROM consulta_examenes ce
             WHERE ce.form_id = ?
             ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC, ce.id DESC',
            [$formId]
        );

        return array_map(static fn($row): array => (array) $row, $rows);
    }

    private function obtenerDoctorProcedimientoProyectado(string $formId, string $hcNumber): ?string
    {
        $sql = 'SELECT pp.doctor
                FROM procedimiento_proyectado pp
                WHERE pp.form_id = ?
                  AND pp.hc_number = ?
                  AND pp.doctor IS NOT NULL
                  AND TRIM(pp.doctor) <> ""
                  AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) NOT LIKE "IMAGENES%"
                  AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) NOT LIKE "CONSULTA OPTOMETRIA%"
                  AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) NOT LIKE "CONSULTA OPTOMETRÍA%"
                  AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) NOT LIKE "%ANESTESIOLOGIA%"
                  AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) NOT LIKE "%ANESTESIOLOGÍA%"
                  AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) LIKE "%SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT%"
                  AND EXISTS (
                    SELECT 1
                    FROM users u
                    WHERE (
                        UPPER(TRIM(pp.doctor)) = u.nombre_norm
                        OR UPPER(TRIM(pp.doctor)) = u.nombre_norm_rev
                        OR TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(pp.doctor)), " "), " SNS ", " "), "  ", " "), "  ", " ")) = u.nombre_norm
                        OR TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(pp.doctor)), " "), " SNS ", " "), "  ", " "), "  ", " ")) = u.nombre_norm_rev
                    )
                    AND (
                        UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMÓLOGO"
                        OR UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMOLOGO"
                    )
                )
                ORDER BY pp.id DESC
                LIMIT 1';

        $row = DB::selectOne($sql, [$formId, $hcNumber]);
        if (is_object($row) && isset($row->doctor)) {
            $doctor = trim((string) $row->doctor);
            if ($doctor !== '') {
                return $doctor;
            }
        }

        $sqlFallback = 'SELECT pp.doctor
                        FROM procedimiento_proyectado pp
                        WHERE pp.hc_number = ?
                          AND pp.doctor IS NOT NULL
                          AND TRIM(pp.doctor) <> ""
                          AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) NOT LIKE "IMAGENES%"
                          AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) NOT LIKE "CONSULTA OPTOMETRIA%"
                          AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) NOT LIKE "CONSULTA OPTOMETRÍA%"
                          AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) NOT LIKE "%ANESTESIOLOGIA%"
                          AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) NOT LIKE "%ANESTESIOLOGÍA%"
                          AND UPPER(TRIM(COALESCE(pp.procedimiento_proyectado, ""))) LIKE "%SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT%"
                          AND EXISTS (
                            SELECT 1
                            FROM users u
                            WHERE (
                                UPPER(TRIM(pp.doctor)) = u.nombre_norm
                                OR UPPER(TRIM(pp.doctor)) = u.nombre_norm_rev
                                OR TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(pp.doctor)), " "), " SNS ", " "), "  ", " "), "  ", " ")) = u.nombre_norm
                                OR TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(pp.doctor)), " "), " SNS ", " "), "  ", " "), "  ", " ")) = u.nombre_norm_rev
                            )
                            AND (
                                UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMÓLOGO"
                                OR UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMOLOGO"
                            )
                        )
                        ORDER BY pp.form_id DESC, pp.id DESC
                        LIMIT 1';

        $row = DB::selectOne($sqlFallback, [$hcNumber]);
        if (!is_object($row) || !isset($row->doctor)) {
            return null;
        }

        $doctor = trim((string) $row->doctor);
        return $doctor !== '' ? $doctor : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchConsultaPorFormHc(string $formId, string $hcNumber): ?array
    {
        $sql = 'SELECT
                    cd.*,
                    pp.doctor AS procedimiento_doctor,
                    u.id AS doctor_user_id,
                    u.first_name AS doctor_fname,
                    u.middle_name AS doctor_mname,
                    u.last_name AS doctor_lname,
                    u.second_last_name AS doctor_lname2,
                    u.cedula AS doctor_cedula,
                    u.signature_path AS doctor_signature_path,
                    u.firma AS doctor_firma,
                    u.nombre AS doctor_nombre
                FROM consulta_data cd
                LEFT JOIN procedimiento_proyectado pp
                    ON pp.id = (
                        SELECT pp2.id
                        FROM procedimiento_proyectado pp2
                        WHERE pp2.form_id = cd.form_id
                          AND pp2.hc_number = cd.hc_number
                          AND pp2.doctor IS NOT NULL
                          AND TRIM(pp2.doctor) <> ""
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) NOT LIKE "IMAGENES%"
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) NOT LIKE "CONSULTA OPTOMETRIA%"
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) NOT LIKE "CONSULTA OPTOMETRÍA%"
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) NOT LIKE "%ANESTESIOLOGIA%"
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) NOT LIKE "%ANESTESIOLOGÍA%"
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) LIKE "%SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT%"
                        ORDER BY pp2.id DESC
                        LIMIT 1
                    )
                LEFT JOIN users u
                    ON (
                        UPPER(TRIM(pp.doctor)) = u.nombre_norm
                        OR UPPER(TRIM(pp.doctor)) = u.nombre_norm_rev
                        OR TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(pp.doctor)), " "), " SNS ", " "), "  ", " "), "  ", " ")) = u.nombre_norm
                        OR TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(pp.doctor)), " "), " SNS ", " "), "  ", " "), "  ", " ")) = u.nombre_norm_rev
                    )
                    AND (
                        UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMÓLOGO"
                        OR UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMOLOGO"
                    )
                WHERE cd.form_id = ?
                  AND cd.hc_number = ?
                LIMIT 1';

        $row = DB::selectOne($sql, [$formId, $hcNumber]);
        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchConsultaPorFormId(string $formId): ?array
    {
        $sql = 'SELECT
                    cd.*,
                    pp.doctor AS procedimiento_doctor,
                    u.id AS doctor_user_id,
                    u.first_name AS doctor_fname,
                    u.middle_name AS doctor_mname,
                    u.last_name AS doctor_lname,
                    u.second_last_name AS doctor_lname2,
                    u.cedula AS doctor_cedula,
                    u.signature_path AS doctor_signature_path,
                    u.firma AS doctor_firma,
                    u.nombre AS doctor_nombre
                FROM consulta_data cd
                LEFT JOIN procedimiento_proyectado pp
                    ON pp.id = (
                        SELECT pp2.id
                        FROM procedimiento_proyectado pp2
                        WHERE pp2.form_id = cd.form_id
                          AND pp2.hc_number = cd.hc_number
                          AND pp2.doctor IS NOT NULL
                          AND TRIM(pp2.doctor) <> ""
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) NOT LIKE "IMAGENES%"
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) NOT LIKE "CONSULTA OPTOMETRIA%"
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) NOT LIKE "CONSULTA OPTOMETRÍA%"
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) NOT LIKE "%ANESTESIOLOGIA%"
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) NOT LIKE "%ANESTESIOLOGÍA%"
                          AND UPPER(TRIM(COALESCE(pp2.procedimiento_proyectado, ""))) LIKE "%SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT%"
                        ORDER BY pp2.id DESC
                        LIMIT 1
                    )
                LEFT JOIN users u
                    ON (
                        UPPER(TRIM(pp.doctor)) = u.nombre_norm
                        OR UPPER(TRIM(pp.doctor)) = u.nombre_norm_rev
                        OR TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(pp.doctor)), " "), " SNS ", " "), "  ", " "), "  ", " ")) = u.nombre_norm
                        OR TRIM(REPLACE(REPLACE(REPLACE(CONCAT(" ", UPPER(TRIM(pp.doctor)), " "), " SNS ", " "), "  ", " "), "  ", " ")) = u.nombre_norm_rev
                    )
                    AND (
                        UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMÓLOGO"
                        OR UPPER(TRIM(COALESCE(u.especialidad, ""))) = "CIRUJANO OFTALMOLOGO"
                    )
                WHERE cd.form_id = ?
                LIMIT 1';

        $row = DB::selectOne($sql, [$formId]);
        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarConsultaExamenOrigen(
        string $hcNumber,
        ?string $examenCodigo = null,
        ?string $fechaReferencia = null,
        ?string $nombreLike = null
    ): ?array {
        $codigo = trim((string) $examenCodigo);
        $nombre = trim((string) $nombreLike);
        $fecha = trim((string) $fechaReferencia);

        $baseSql = 'SELECT
                ce.id,
                ce.form_id,
                ce.hc_number,
                ce.examen_codigo,
                ce.examen_nombre,
                COALESCE(ce.consulta_fecha, ce.created_at) AS fecha_ref
            FROM consulta_examenes ce
            WHERE ce.hc_number = ?';
        $params = [$hcNumber];

        if ($codigo !== '') {
            $baseSql .= ' AND ce.examen_codigo = ?';
            $params[] = $codigo;
        } elseif ($nombre !== '') {
            $nombreUpper = function_exists('mb_strtoupper') ? mb_strtoupper($nombre, 'UTF-8') : strtoupper($nombre);
            $baseSql .= ' AND UPPER(TRIM(ce.examen_nombre)) LIKE ?';
            $params[] = '%' . $nombreUpper . '%';
        }

        if ($fecha !== '') {
            $sqlConFecha = $baseSql . '
                AND COALESCE(ce.consulta_fecha, ce.created_at) <= ?
                ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC, ce.id DESC
                LIMIT 1';
            $row = DB::selectOne($sqlConFecha, array_merge($params, [$fecha]));
            if (is_object($row)) {
                return (array) $row;
            }
        }

        $sql = $baseSql . '
            ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC, ce.id DESC
            LIMIT 1';
        $row = DB::selectOne($sql, $params);

        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerConsultaClinicaSerOftPorHcHastaFecha(string $hcNumber, string $fechaMax): ?array
    {
        $hcNumber = trim($hcNumber);
        $fechaMax = trim($fechaMax);
        if ($hcNumber === '' || $fechaMax === '') {
            return null;
        }

        $row = DB::selectOne(
            'SELECT
                cd.form_id,
                cd.hc_number
             FROM consulta_data cd
             WHERE cd.hc_number = ?
               AND COALESCE(cd.fecha, "1900-01-01") <= ?
               AND JSON_VALID(cd.diagnosticos)
               AND JSON_LENGTH(cd.diagnosticos) > 0
               AND EXISTS (
                    SELECT 1
                    FROM procedimiento_proyectado pp
                    WHERE pp.form_id = cd.form_id
                      AND pp.hc_number = cd.hc_number
                      AND UPPER(COALESCE(pp.procedimiento_proyectado, "")) LIKE "%SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT%"
               )
             ORDER BY COALESCE(cd.fecha, "1900-01-01") DESC, cd.form_id DESC
             LIMIT 1',
            [$hcNumber, $fechaMax]
        );

        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerDerivacionPorFormId(string $formId): ?array
    {
        $row = DB::selectOne(
            'SELECT
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
             LIMIT 1',
            [$formId]
        );

        if (is_object($row)) {
            $out = (array) $row;
            $out['id'] = $out['derivacion_id'] ?? null;
            return $out;
        }

        $legacy = DB::selectOne('SELECT * FROM derivaciones_form_id WHERE form_id = ? LIMIT 1', [$formId]);
        return is_object($legacy) ? (array) $legacy : null;
    }
}
