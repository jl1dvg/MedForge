<?php

namespace Models\Agenda;

use ArrayAccess;
use LogicException;
use Modules\Shared\Services\PatientContextService;

class ProcedimientoProyectado implements ArrayAccess
{
    /**
     * @var array<string, mixed>
     */
    private array $attributes;

    private PatientContextService $patientContext;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $contextCache = null;

    private ?Visita $visitaCache = null;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes, PatientContextService $patientContext)
    {
        $this->attributes = $attributes;
        $this->patientContext = $patientContext;
        $this->hydrateFromContext();
    }

    public function getHcNumber(): ?string
    {
        $hcNumber = $this->attributes['hc_number'] ?? null;

        return $hcNumber !== null ? (string) $hcNumber : null;
    }

    public function getFormId(): ?int
    {
        return isset($this->attributes['form_id']) ? (int) $this->attributes['form_id'] : null;
    }

    public function getFechaAgenda(): ?string
    {
        $fecha = $this->attributes['fecha_agenda'] ?? null;

        return $fecha !== null ? (string) $fecha : null;
    }

    public function getFechaAgendaFormatted(): string
    {
        $fecha = $this->getFechaAgenda();
        if ($fecha === null) {
            return '—';
        }

        $date = date_create($fecha);

        return $date instanceof \DateTimeInterface ? $date->format('d/m/Y') : '—';
    }

    public function getHoraAgenda(): ?string
    {
        $hora = $this->attributes['hora_agenda'] ?? $this->attributes['hora'] ?? null;

        if ($hora === null) {
            return null;
        }

        $hora = trim((string) $hora);

        if ($hora === '') {
            return null;
        }

        $time = date_create($hora);

        return $time instanceof \DateTimeInterface ? $time->format('H:i') : $hora;
    }

    public function getPacienteNombre(): string
    {
        $paciente = $this->attributes['paciente'] ?? null;
        if ($paciente !== null && trim((string) $paciente) !== '') {
            return trim((string) $paciente);
        }

        $patient = $this->patient();
        if ($patient !== null) {
            $fullName = $patient['full_name'] ?? $this->buildFullName($patient);
            if ($fullName !== '') {
                $this->attributes['paciente'] = $fullName;

                return $fullName;
            }
        }

        return 'Sin registro';
    }

    public function getProcedimiento(): ?string
    {
        $procedimiento = $this->attributes['procedimiento'] ?? $this->attributes['procedimiento_proyectado'] ?? null;

        return $procedimiento !== null ? (string) $procedimiento : null;
    }

    public function getDoctor(): ?string
    {
        $doctor = $this->attributes['doctor'] ?? null;

        return $doctor !== null ? (string) $doctor : null;
    }

    public function getEstadoAgenda(): ?string
    {
        $estado = $this->attributes['estado_agenda'] ?? null;

        return $estado !== null ? (string) $estado : null;
    }

    public function getAfiliacion(): ?string
    {
        $afiliacion = $this->attributes['afiliacion'] ?? null;

        if ($afiliacion === null) {
            $patient = $this->patient();
            if ($patient !== null && isset($patient['afiliacion'])) {
                $afiliacion = (string) $patient['afiliacion'];
                $this->attributes['afiliacion'] = $afiliacion;
            }
        }

        return $afiliacion !== null ? (string) $afiliacion : null;
    }

    public function getSedeNombre(): string
    {
        $departamento = $this->attributes['sede_departamento'] ?? null;
        $idSede = $this->attributes['id_sede'] ?? null;

        $partes = array_filter([
            $departamento !== null && trim((string) $departamento) !== '' ? (string) $departamento : null,
            $idSede !== null && trim((string) $idSede) !== '' ? '#' . trim((string) $idSede) : null,
        ]);

        return $partes ? implode(' ', $partes) : '—';
    }

    public function getVisitaId(): ?int
    {
        return isset($this->attributes['visita_id']) ? (int) $this->attributes['visita_id'] : null;
    }

    public function hasVisita(): bool
    {
        return $this->getVisitaId() !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistorialEstados(): array
    {
        $historial = $this->attributes['historial_estados'] ?? [];

        return is_array($historial) ? $historial : [];
    }

    public function getPatientContext(): array
    {
        if ($this->contextCache !== null) {
            return $this->contextCache;
        }

        $hcNumber = $this->getHcNumber();
        if ($hcNumber === null) {
            return $this->contextCache = [
                'hc_number' => '',
                'clinic' => ['patient' => null],
                'crm' => [
                    'customers' => [],
                    'primary_customer' => null,
                    'leads' => [],
                    'primary_lead' => null,
                ],
                'communications' => [
                    'conversations' => [],
                    'primary_conversation' => null,
                ],
            ];
        }

        return $this->contextCache = $this->patientContext->getContext($hcNumber);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function patient(): ?array
    {
        $context = $this->getPatientContext();

        return $context['clinic']['patient'] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function customer(): ?array
    {
        $context = $this->getPatientContext();

        return $context['crm']['primary_customer'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function communications(): array
    {
        $context = $this->getPatientContext();

        return $context['communications'];
    }

    public function getVisita(): ?Visita
    {
        if (!$this->hasVisita()) {
            return null;
        }

        if ($this->visitaCache instanceof Visita) {
            return $this->visitaCache;
        }

        $visitaData = [
            'id' => $this->getVisitaId(),
            'hc_number' => $this->getHcNumber(),
            'fecha_visita' => $this->attributes['fecha_visita'] ?? null,
            'hora_llegada' => $this->attributes['hora_llegada'] ?? null,
        ];

        $this->visitaCache = new Visita($visitaData, [], $this->patientContext);

        return $this->visitaCache;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = $this->attributes;
        $array['patient_context'] = $this->getPatientContext();

        return $array;
    }

    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }

        return array_key_exists($offset, $this->attributes)
            || $offset === 'patient_context'
            || $offset === 'visita';
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset)) {
            return null;
        }

        return match ($offset) {
            'patient_context' => $this->getPatientContext(),
            'visita' => $this->getVisita(),
            default => $this->attributes[$offset] ?? null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('ProcedimientoProyectado es inmutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('ProcedimientoProyectado es inmutable.');
    }

    private function hydrateFromContext(): void
    {
        $context = $this->getPatientContext();
        $patient = $context['clinic']['patient'] ?? null;

        if ($patient !== null) {
            $fullName = $patient['full_name'] ?? $this->buildFullName($patient);
            if ($fullName !== '') {
                $this->attributes['paciente'] = $fullName;
            }

            if (!isset($this->attributes['afiliacion']) && isset($patient['afiliacion'])) {
                $this->attributes['afiliacion'] = $patient['afiliacion'];
            }
        }

        if (!isset($this->attributes['hora_agenda'])) {
            $hora = $this->attributes['hora'] ?? $this->attributes['hora_llegada'] ?? null;
            if ($hora !== null) {
                $this->attributes['hora_agenda'] = $hora;
            }
        }
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function buildFullName(array $parts): string
    {
        $pieces = array_filter([
            $this->sanitizeString($parts['fname'] ?? null),
            $this->sanitizeString($parts['mname'] ?? null),
            $this->sanitizeString($parts['lname'] ?? null),
            $this->sanitizeString($parts['lname2'] ?? null),
        ]);

        return trim(implode(' ', $pieces));
    }

    private function sanitizeString(mixed $value): ?string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
