<?php

namespace Modules\Cirugias\Models;

class Cirugia
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getNombreCompleto(): string
    {
        $patient = $this->patient();
        if (is_array($patient)) {
            $fullName = $patient['full_name'] ?? trim(implode(' ', array_filter([
                $patient['fname'] ?? null,
                $patient['mname'] ?? null,
                $patient['lname'] ?? null,
                $patient['lname2'] ?? null,
            ])));

            if ($fullName !== '') {
                return $fullName;
            }
        }

        return trim(implode(' ', array_filter([
            $this->data['fname'] ?? null,
            $this->data['mname'] ?? null,
            $this->data['lname'] ?? null,
            $this->data['lname2'] ?? null,
        ])));
    }

    public function getDuracion(): string
    {
        $inicio = new \DateTime($this->data['hora_inicio']);
        $fin = new \DateTime($this->data['hora_fin']);
        return $inicio->diff($fin)->format('%H:%I');
    }

    public function getEstado(): string
    {
        if ($this->__get('status') == 1) return 'revisado';

        $invalid = ['CENTER', 'undefined'];
        $required = [
            $this->__get('membrete'), $this->__get('dieresis'), $this->__get('exposicion'),
            $this->__get('hallazgo'), $this->__get('operatorio'), $this->__get('complicaciones_operatorio'),
            $this->__get('datos_cirugia'), $this->__get('procedimientos'), $this->__get('lateralidad'),
            $this->__get('tipo_anestesia'), $this->__get('diagnosticos'), $this->__get('procedimiento_proyectado'),
            $this->__get('fecha_inicio'), $this->__get('hora_inicio'), $this->__get('hora_fin')
        ];

        foreach ($required as $field) {
            if (!empty($field)) {
                foreach ($invalid as $inv) {
                    if (stripos($field ?? '', $inv) !== false) return 'incompleto';
                }
            }
        }

        $staff = [
            $this->__get('cirujano_1'), $this->__get('instrumentista'), $this->__get('cirujano_2'),
            $this->__get('circulante'), $this->__get('primer_ayudante'), $this->__get('anestesiologo'),
            $this->__get('segundo_ayudante'), $this->__get('ayudante_anestesia'), $this->__get('tercer_ayudante')
        ];

        $staffCount = 0;
        foreach ($staff as $s) {
            if (!empty($s) && !in_array(strtoupper($s), $invalid)) $staffCount++;
        }

        if (!empty($this->__get('cirujano_1')) && $staffCount >= 5) {
            return 'no revisado';
        }

        return 'incompleto';
    }

    public function getBadgeClass(): string
    {
        return match ($this->getEstado()) {
            'revisado' => 'badge-success',
            'no revisado' => 'badge-warning',
            default => 'badge-danger'
        };
    }

    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPatientContext(): array
    {
        return $this->data['patient_context'] ?? [
            'hc_number' => $this->data['hc_number'] ?? '',
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
}
