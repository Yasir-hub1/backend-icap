<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProgramaRequest extends FormRequest
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
        return [
            'nombre' => 'required|string|max:250',
            'duracion_meses' => 'required|integer|min:1|max:120',
            'total_modulos' => 'nullable|integer|min:0|max:50',
            'costo' => 'required|numeric|min:0|max:999999.99',
            'Rama_academica_id' => 'nullable|exists:rama_academica,id',
            'Tipo_programa_id' => 'required|exists:tipo_programa,id',
            'Programa_id' => 'nullable|exists:programa,id',
            'Institucion_id' => 'required|exists:institucion,id',
            'version_id' => 'nullable|exists:version,id',
            'modulos' => 'nullable|array',
            'modulos.*' => 'exists:modulo,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del programa es obligatorio',
            'nombre.max' => 'El nombre no puede tener más de 250 caracteres',
            'duracion_meses.required' => 'La duración en meses es obligatoria',
            'duracion_meses.integer' => 'La duración debe ser un número entero',
            'duracion_meses.min' => 'La duración debe ser al menos 1 mes',
            'duracion_meses.max' => 'La duración no puede ser mayor a 120 meses',
            'total_modulos.integer' => 'El total de módulos debe ser un número entero',
            'total_modulos.min' => 'El total de módulos no puede ser negativo',
            'total_modulos.max' => 'El total de módulos no puede ser mayor a 50',
            'costo.required' => 'El costo es obligatorio',
            'costo.numeric' => 'El costo debe ser un número',
            'costo.min' => 'El costo no puede ser negativo',
            'costo.max' => 'El costo no puede ser mayor a 999,999.99',
            'Rama_academica_id.exists' => 'La rama académica no es válida',
            'Tipo_programa_id.required' => 'El tipo de programa es obligatorio',
            'Tipo_programa_id.exists' => 'El tipo de programa no es válido',
            'Programa_id.exists' => 'El programa padre no es válido',
            'Institucion_id.required' => 'La institución es obligatoria',
            'Institucion_id.exists' => 'La institución no es válida',
            'version_id.exists' => 'La versión no es válida',
            'modulos.array' => 'Los módulos deben ser un arreglo',
            'modulos.*.exists' => 'Uno o más módulos no son válidos'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del programa',
            'duracion_meses' => 'duración en meses',
            'total_modulos' => 'total de módulos',
            'costo' => 'costo',
            'Rama_academica_id' => 'rama académica',
            'Tipo_programa_id' => 'tipo de programa',
            'Programa_id' => 'programa padre',
            'Institucion_id' => 'institución',
            'version_id' => 'versión',
            'modulos' => 'módulos'
        ];
    }
}
