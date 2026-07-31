<?php

namespace App\Http\Requests\Api\Food;

use Carbon\Carbon;
use Illuminate\Validation\Validator;

class FoodAttendanceHistoryRequest extends FoodApiFormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $from = $this->input('from');
            $to = $this->input('to');
            if ($from && $to && Carbon::parse($from)->gt(Carbon::parse($to))) {
                $validator->errors()->add('from', 'Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.');
            }
        });
    }

    public function fromDate(): Carbon
    {
        if ($this->filled('from')) {
            return Carbon::parse($this->input('from'))->startOfDay();
        }

        return now()->startOfMonth()->startOfDay();
    }

    public function toDate(): Carbon
    {
        if ($this->filled('to')) {
            return Carbon::parse($this->input('to'))->endOfDay();
        }

        return now()->endOfMonth()->endOfDay();
    }

    public function perPage(): int
    {
        return (int) ($this->input('per_page') ?: 20);
    }
}
