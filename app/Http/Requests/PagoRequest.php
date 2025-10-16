<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PagoRequest extends FormRequest
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
            'cuotas_id' => 'required|exists:cuotas,id',
            'monto' => 'required|numeric|min:0.01|max:999999.99',
            'token' => 'nullable|string|max:100'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'cuotas_id.required' => 'La cuota es obligatoria',
            'cuotas_id.exists' => 'La cuota no es válida',
            'monto.required' => 'El monto es obligatorio',
            'monto.numeric' => 'El monto debe ser un número',
            'monto.min' => 'El monto debe ser mayor a 0',
            'monto.max' => 'El monto no puede ser mayor a 999,999.99',
            'token.max' => 'El token no puede tener más de 100 caracteres'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'cuotas_id' => 'cuota',
            'monto' => 'monto',
            'token' => 'token'
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('cuotas_id') && $this->has('monto')) {
                $cuotaId = $this->input('cuotas_id');
                $monto = $this->input('monto');

                // Verificar que la cuota existe y obtener información
                $cuota = \App\Models\Cuota::find($cuotaId);

                if ($cuota) {
                    // Verificar que la cuota no esté ya pagada completamente
                    $montoPagado = $cuota->monto_pagado;
                    $montoRestante = $cuota->monto - $montoPagado;

                    if ($montoRestante <= 0) {
                        $validator->errors()->add(
                            'cuotas_id',
                            'Esta cuota ya está pagada completamente'
                        );
                    }

                    // Verificar que el monto no exceda el saldo pendiente
                    if ($monto > $montoRestante) {
                        $validator->errors()->add(
                            'monto',
                            'El monto excede el saldo pendiente de la cuota (' . number_format($montoRestante, 2) . ')'
                        );
                    }
                }
            }
        });
    }
}
