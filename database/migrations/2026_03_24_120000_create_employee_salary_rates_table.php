<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('salary_rate', 14, 2);
            $table->string('salary_type', 20);
            $table->timestamps();

            $table->unique(['employee_id', 'effective_from']);
        });

        if (Schema::hasTable('employees')) {
            $rows = DB::table('employees')->select('id', 'salary_rate', 'salary_type', 'start_date', 'created_at')->get();
            foreach ($rows as $e) {
                $from = $e->start_date
                    ? \Carbon\Carbon::parse($e->start_date)->toDateString()
                    : \Carbon\Carbon::parse($e->created_at)->toDateString();
                DB::table('employee_salary_rates')->insert([
                    'employee_id' => $e->id,
                    'effective_from' => $from,
                    'salary_rate' => $e->salary_rate,
                    'salary_type' => $e->salary_type ?? 'hour',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_rates');
    }
};
