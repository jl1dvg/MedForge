<?php

namespace Models\Agenda;

use ArrayAccess;
use LogicException;
use Modules\Shared\Services\PatientContextService;

class Visita implements ArrayAccess
{
    /**
     * @var array<string, mixed>
     */
    private array $attributes;

    /**
     * @var ProcedimientoProyectado[]
     */
    private array $procedimientos = [];

    private PatientContextService $patientContext;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $contextCache = null;

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, ProcedimientoProyectado|array<string, mixed>> $procedimientos
     */
    public function __construct(array $attributes, array $procedimientos, PatientContextService $patientContext)
    {
        $this->attributes = $attributes;
        $this->patientContext = $patientContext;
        $this->setProcedimientos($procedimientos);
        $this->hydrateFromContext();
    }

    public function getId(): ?int
    {
        return isset($this->attributes['id']) ? (int) $this->attributes['id'] : null;
    }

    public function getHcNumber(): ?string
    {
        $hcNumber = $this->attributes['hc_number'] ?? null;

        return $hcNumber !== null ? (string) $hcNumber : null;
    }

    public function getFechaVisita(): ?string
    {
        $fecha = $this->attributes['fecha_visita'] ?? null;

        return $fecha !== null ? (string) $fecha : null;
    }

    public function getFechaVisitaFormatted(): string
    {
        $fecha = $this->getFechaVisita();
        if ($fecha === null) {
            return '—';
        }

        $date = date_create($fecha);

        return $date instanceof \DateTimeInterface ? $date->format('d/m/Y') : '—';
    }

    public function getHoraLlegada(): ?string
    {
        $hora = $this->attributes['hora_llegada'] ?? null;

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

    public function getHoraLlegadaFormatted(): string
    {
        return $this->getHoraLlegada() ?? '—';
    }

    public function getUsuarioRegistro(): ?string
    {
        $usuario = $this->attributes['usuario_registro'] ?? null;

        return $usuario !== null ? (string) $usuario : null;
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

        return 'Paciente sin nombre';
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

    public function getContacto(): ?string
    {
        $contacto = $this->attributes['celular'] ?? null;
        if ($contacto === null) {
            $patient = $this->patient();
            if ($patient !== null) {
                foreach (['celular', 'telefono'] as $campo) {
                    if (!empty($patient[$campo])) {
                        $contacto = (string) $patient[$campo];
                        $this->attributes['celular'] = $contacto;
                        break;
                    }
                }
            }
        }

        return $contacto !== null ? (string) $contacto : null;
    }

    /**
     * @return ProcedimientoProyectado[]
     */
    public function getProcedimientos(): array
    {
        return $this->procedimientos;
    }

    /**
     * @param array<int, ProcedimientoProyectado|array<string, mixed>> $procedimientos
     */
    public function setProcedimientos(array $procedimientos): void
    {
        $this->procedimientos = array_map(function ($procedimiento): ProcedimientoProyectado {
            if ($procedimiento instanceof ProcedimientoProyectado) {
                return $procedimiento;
            }

            if (is_array($procedimiento)) {
                return new ProcedimientoProyectado($procedimiento, $this->patientContext);
            }

            throw new LogicException('Tipo de procedimiento no soportado.');
        }, $procedimientos);
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = $this->attributes;
        $array['patient_context'] = $this->getPatientContext();
        $array['procedimientos'] = array_map(static function (ProcedimientoProyectado $procedimiento): array {
            return $procedimiento->toArray();
        }, $this->procedimientos);

        return $array;
    }

    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }

        if ($offset === 'procedimientos') {
            return true;
        }

        return array_key_exists($offset, $this->attributes)
            || $offset === 'patient_context';
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset)) {
            return null;
        }

        return match ($offset) {
            'patient_context' => $this->getPatientContext(),
            'procedimientos' => $this->getProcedimientos(),
            default => $this->attributes[$offset] ?? null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Visita es inmutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Visita es inmutable.');
    }

    private function hydrateFromContext(): void
    {
        $patient = $this->patient();
        if ($patient !== null) {
            $fullName = $patient['full_name'] ?? $this->buildFullName($patient);
            if ($fullName !== '') {
                $this->attributes['paciente'] = $fullName;
            }

            if (!isset($this->attributes['afiliacion']) && isset($patient['afiliacion'])) {
                $this->attributes['afiliacion'] = $patient['afiliacion'];
            }

            if (!isset($this->attributes['celular'])) {
                foreach (['celular', 'telefono'] as $campo) {
                    if (!empty($patient[$campo])) {
                        $this->attributes['celular'] = $patient[$campo];
                        break;
                    }
                }
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
