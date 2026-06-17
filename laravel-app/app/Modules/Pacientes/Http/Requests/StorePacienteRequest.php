<?php

namespace App\Modules\Pacientes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cedula' => ['nullable', 'string', 'max:64'],
            'fname' => ['required_without:nombres', 'string', 'max:100'],
            'mname' => ['nullable', 'string', 'max:100'],
            'lname' => ['required_without:apellidos', 'string', 'max:100'],
            'lname2' => ['nullable', 'string', 'max:100'],
            'nombres' => ['required_without:fname', 'string', 'max:255'],
            'apellidos' => ['required_without:lname', 'string', 'max:255'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'fecha_nac' => ['nullable', 'date'],
            'sexo' => ['nullable', 'in:M,F'],
            'telefono' => ['nullable', 'string', 'max:15'],
            'celular' => ['nullable', 'string', 'max:15'],
            'telefono_alt' => ['nullable', 'string', 'max:64'],
            'afiliacion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'medico' => ['nullable', 'integer'],
            'medico_tratante_id' => ['nullable', 'integer'],
            'sede' => ['nullable', 'in:matriz,ceibos'],
            'sede_principal' => ['nullable', 'in:matriz,ceibos'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fecha_nacimiento' => $this->input('fecha_nacimiento') ?: $this->input('fecha_nac'),
            'telefono_alt' => $this->blankToNull($this->input('telefono_alt')),
            'medico' => $this->blankToNull($this->input('medico')),
            'medico_tratante_id' => $this->blankToNull($this->input('medico_tratante_id')),
            'sede' => $this->blankToNull($this->input('sede')),
            'sede_principal' => $this->blankToNull($this->input('sede_principal')),
        ]);
    }

    private function blankToNull(mixed $value): mixed
    {
        return trim((string) ($value ?? '')) === '' ? null : $value;
    }
}
