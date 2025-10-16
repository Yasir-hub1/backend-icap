<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InscripcionRequest extends FormRequest
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
            'Programa_id' => 'required|exists:Programa,id',
            'Estudiante_id' => 'required|exists:Estudiante,id',
            'Descuento_id' => 'nullable|exists:Descuento,id',
            'plan_pagos' => 'nullable|array',
            'plan_pagos.total_cuotas' => 'required_with:plan_pagos|integer|min:1|max:24',
            'plan_pagos.cuotas' => 'required_with:plan_pagos|array|min:1',
            'plan_pagos.cuotas.*.fecha_ini' => 'required_with:plan_pagos|date|after_or_equal:today',
            'plan_pagos.cuotas.*.fecha_fin' => 'required_with:plan_pagos|date|after:plan_pagos.cuotas.*.fecha_ini',
            'plan_pagos.cuotas.*.monto' => 'required_with:plan_pagos|numeric|min:0.01'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'Programa_id.required' => 'El programa es obligatorio',
            'Programa_id.exists' => 'El programa no es válido',
            'Estudiante_id.required' => 'El estudiante es obligatorio',
            'Estudiante_id.exists' => 'El estudiante no es válido',
            'Descuento_id.exists' => 'El descuento no es válido',
            'plan_pagos.array' => 'El plan de pagos debe ser un arreglo',
            'plan_pagos.total_cuotas.required_with' => 'El total de cuotas es obligatorio cuando se especifica un plan de pagos',
            'plan_pagos.total_cuotas.integer' => 'El total de cuotas debe ser un número entero',
            'plan_pagos.total_cuotas.min' => 'El total de cuotas debe ser al menos 1',
            'plan_pagos.total_cuotas.max' => 'El total de cuotas no puede ser mayor a 24',
            'plan_pagos.cuotas.required_with' => 'Las cuotas son obligatorias cuando se especifica un plan de pagos',
            'plan_pagos.cuotas.array' => 'Las cuotas deben ser un arreglo',
            'plan_pagos.cuotas.min' => 'Debe especificar al menos una cuota',
            'plan_pagos.cuotas.*.fecha_ini.required_with' => 'La fecha de inicio de la cuota es obligatoria',
            'plan_pagos.cuotas.*.fecha_ini.date' => 'La fecha de inicio debe ser una fecha válida',
            'plan_pagos.cuotas.*.fecha_ini.after_or_equal' => 'La fecha de inicio debe ser hoy o posterior',
            'plan_pagos.cuotas.*.fecha_fin.required_with' => 'La fecha de fin de la cuota es obligatoria',
            'plan_pagos.cuotas.*.fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'plan_pagos.cuotas.*.fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'plan_pagos.cuotas.*.monto.required_with' => 'El monto de la cuota es obligatorio',
            'plan_pagos.cuotas.*.monto.numeric' => 'El monto debe ser un número',
            'plan_pagos.cuotas.*.monto.min' => 'El monto debe ser mayor a 0'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'Programa_id' => 'programa',
            'Estudiante_id' => 'estudiante',
            'Descuento_id' => 'descuento',
            'plan_pagos' => 'plan de pagos',
            'plan_pagos.total_cuotas' => 'total de cuotas',
            'plan_pagos.cuotas' => 'cuotas',
            'plan_pagos.cuotas.*.fecha_ini' => 'fecha de inicio',
            'plan_pagos.cuotas.*.fecha_fin' => 'fecha de fin',
            'plan_pagos.cuotas.*.monto' => 'monto'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que el total de cuotas coincida con el número de cuotas especificadas
            if ($this->has('plan_pagos.cuotas') && $this->has('plan_pagos.total_cuotas')) {
                $cuotas = $this->input('plan_pagos.cuotas', []);
                $totalCuotas = $this->input('plan_pagos.total_cuotas');

                if (count($cuotas) !== $totalCuotas) {
                    $validator->errors()->add(
                        'plan_pagos.total_cuotas',
                        'El total de cuotas debe coincidir con el número de cuotas especificadas'
                    );
                }
            }

            // Validar que las fechas de las cuotas estén en orden
            if ($this->has('plan_pagos.cuotas')) {
                $cuotas = $this->input('plan_pagos.cuotas', []);
                $fechasInicio = array_column($cuotas, 'fecha_ini');
                $fechasFin = array_column($cuotas, 'fecha_fin');

                // Verificar que las fechas estén en orden cronológico
                for ($i = 1; $i < count($fechasInicio); $i++) {
                    if ($fechasInicio[$i] < $fechasFin[$i - 1]) {
                        $validator->errors()->add(
                            'plan_pagos.cuotas',
                            'Las fechas de las cuotas deben estar en orden cronológico'
                        );
                        break;
                    }
                }
            }
        });
    }
}
