<?php

namespace App\Http\Requests\Api\Food;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class FoodApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        $first = $validator->errors()->first() ?: 'Dữ liệu không hợp lệ.';

        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $first,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'details' => $validator->errors(),
            ],
        ], 422));
    }
}
