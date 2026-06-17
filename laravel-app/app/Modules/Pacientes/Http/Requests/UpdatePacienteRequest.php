<?php

namespace App\Modules\Pacientes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePacienteRequest extends FormRequest
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
            'hc_number' => ['required', 'string', 'max:255'],
            'cedula' => ['nullable', 'string', 'max:64'],
            'fname' => ['required', 'string', 'max:100'],
            'mname' => ['nullable', 'string', 'max:100'],
            'lname' => ['required', 'string', 'max:100'],
            'lname2' => ['nullable', 'string', 'max:100'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'sexo' => ['nullable', 'in:M,F'],
            'celular' => ['nullable', 'string', 'max:15'],
            'telefono_alt' => ['nullable', 'string', 'max:64'],
            'afiliacion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'medico_tratante_id' => ['nullable', 'integer'],
            'sede_principal' => ['nullable', 'in:matriz,ceibos'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telefono_alt' => $this->blankToNull($this->input('telefono_alt')),
            'medico_tratante_id' => $this->blankToNull($this->input('medico_tratante_id')),
            'sede_principal' => $this->blankToNull($this->input('sede_principal')),
        ]);
    }

    private function blankToNull(mixed $value): mixed
    {
        return trim((string) ($value ?? '')) === '' ? null : $value;
    }
}
