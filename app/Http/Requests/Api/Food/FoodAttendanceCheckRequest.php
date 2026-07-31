<?php

namespace App\Http\Requests\Api\Food;

class FoodAttendanceCheckRequest extends FoodApiFormRequest
{
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:food_branches,id'],
            'qr_token' => ['required', 'string', 'max:128'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Thiếu chi nhánh.',
            'branch_id.exists' => 'Chi nhánh không tồn tại.',
            'qr_token.required' => 'Thiếu mã QR.',
            'latitude.required' => 'Thiếu tọa độ GPS.',
            'latitude.between' => 'Vĩ độ không hợp lệ.',
            'longitude.required' => 'Thiếu tọa độ GPS.',
            'longitude.between' => 'Kinh độ không hợp lệ.',
        ];
    }
}
