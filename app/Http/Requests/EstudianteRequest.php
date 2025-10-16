<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EstudianteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $estudianteId = $this->route('id');

        return [
            'ci' => [
                'required',
                'string',
                'max:20',
                Rule::unique('Estudiante', 'ci')->ignore($estudianteId)
            ],
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'celular' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'direccion' => 'nullable|string|max:300',
            'fotografia' => 'nullable|string',
            'provincia' => 'nullable|string|max:100',
            'Estado_id' => 'required|exists:Estado_estudiante,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'ci.required' => 'El CI es obligatorio',
            'ci.unique' => 'El CI ya está registrado en el sistema',
            'ci.max' => 'El CI no puede tener más de 20 caracteres',
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres',
            'apellido.required' => 'El apellido es obligatorio',
            'apellido.max' => 'El apellido no puede tener más de 100 caracteres',
            'celular.max' => 'El celular no puede tener más de 20 caracteres',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy',
            'direccion.max' => 'La dirección no puede tener más de 300 caracteres',
            'provincia.max' => 'La provincia no puede tener más de 100 caracteres',
            'Estado_id.required' => 'El estado del estudiante es obligatorio',
            'Estado_id.exists' => 'El estado del estudiante no es válido'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'ci' => 'cédula de identidad',
            'nombre' => 'nombre',
            'apellido' => 'apellido',
            'celular' => 'celular',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'direccion' => 'dirección',
            'fotografia' => 'fotografía',
            'provincia' => 'provincia',
            'Estado_id' => 'estado del estudiante'
        ];
    }
}
