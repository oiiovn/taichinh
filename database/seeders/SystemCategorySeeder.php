<?php

namespace Database\Seeders;

use App\Models\SystemCategory;
use Illuminate\Database\Seeder;

class SystemCategorySeeder extends Seeder
{
    public function run(): void
    {
        if (SystemCategory::count() > 0) {
            $this->command->info('system_categories đã có dữ liệu, bỏ qua seed.');
            return;
        }

        $expense = [
            'Ăn uống',
            'Di chuyển',
            'Mua sắm',
            'Hóa đơn & tiện ích',
            'Giải trí',
            'Sức khỏe',
            'Giáo dục',
            'Cho vay / Trả nợ',
            'Đầu tư',
            'Khác (chi)',
        ];

        $income = [
            'Lương',
            'Thưởng',
            'Kinh doanh',
            'Cho vay thu hồi',
            'Đầu tư / Lãi',
            'Quà tặng / Nhận chuyển',
            'Khác (thu)',
        ];

        foreach ($expense as $name) {
            SystemCategory::create(['name' => $name, 'type' => 'expense']);
        }
        foreach ($income as $name) {
            SystemCategory::create(['name' => $name, 'type' => 'income']);
        }

        $this->command->info('Đã seed ' . SystemCategory::count() . ' system_categories.');
    }
}
