<?php

namespace App\Http\Requests\Api\Food;

use Carbon\Carbon;
use Illuminate\Validation\Validator;

class FoodManagerAttendanceRequest extends FoodApiFormRequest
{
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:food_branches,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'date' => ['nullable', 'date'],
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

    public function workDate(): Carbon
    {
        return $this->filled('date')
            ? Carbon::parse($this->input('date'))->startOfDay()
            : Carbon::today();
    }

    public function fromDate(): Carbon
    {
        return $this->filled('from')
            ? Carbon::parse($this->input('from'))->startOfDay()
            : now()->startOfMonth()->startOfDay();
    }

    public function toDate(): Carbon
    {
        return $this->filled('to')
            ? Carbon::parse($this->input('to'))->endOfDay()
            : now()->endOfMonth()->endOfDay();
    }

    public function perPage(): int
    {
        return (int) ($this->input('per_page') ?: 30);
    }
}
