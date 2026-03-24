<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryRate extends Model
{
    protected $table = 'employee_salary_rates';

    protected $fillable = [
        'employee_id',
        'effective_from',
        'salary_rate',
        'salary_type',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'salary_rate' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
