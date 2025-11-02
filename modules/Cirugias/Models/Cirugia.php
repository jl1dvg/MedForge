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
}