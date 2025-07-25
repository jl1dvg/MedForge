<?php

namespace Models;

class IplPlanificadorModel
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getNombreCompleto(): string
    {
        return trim("{$this->data['fname']} {$this->data['lname']} {$this->data['lname2']}");
    }

    public function getDuracion(): string
    {
        $inicio = new \DateTime($this->data['hora_inicio']);
        $fin = new \DateTime($this->data['hora_fin']);
        return $inicio->diff($fin)->format('%H:%I');
    }

    public function getEstado(): string
    {
        if ($this->data['status'] == 1) return 'revisado';

        $invalid = ['CENTER', 'undefined'];
        $required = [$this->data['membrete'], $this->data['dieresis'], $this->data['exposicion'], $this->data['hallazgo'], $this->data['operatorio'],
            $this->data['complicaciones_operatorio'], $this->data['datos_cirugia'], $this->data['procedimientos'], $this->data['lateralidad'],
            $this->data['tipo_anestesia'], $this->data['diagnosticos'], $this->data['procedimiento_proyectado'], $this->data['fecha_inicio'],
            $this->data['hora_inicio'], $this->data['hora_fin']];

        foreach ($required as $field) {
            if (!empty($field)) {
                foreach ($invalid as $inv) {
                    if (stripos($field ?? '', $inv) !== false) return 'incompleto';
                }
            }
        }

        $staff = [$this->data['cirujano_1'], $this->data['instrumentista'], $this->data['cirujano_2'], $this->data['circulante'],
            $this->data['primer_ayudante'], $this->data['anestesiologo'], $this->data['segundo_ayudante'],
            $this->data['ayudante_anestesia'], $this->data['tercer_ayudante']];

        $staffCount = 0;
        foreach ($staff as $s) {
            if (!empty($s) && !in_array(strtoupper($s), $invalid)) $staffCount++;
        }

        if (!empty($this->data['cirujano_1']) && $staffCount >= 5) {
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
}