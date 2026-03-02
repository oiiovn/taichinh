<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodSalesReportItem extends Model
{
    protected $table = 'food_sales_report_items';

    protected $fillable = [
        'food_sales_report_id',
        'nhom_hang',
        'ma_hang',
        'ten_hang',
        'don_vi_tinh',
        'sl_ban',
        'gia_tri_niem_yet',
        'doanh_thu',
        'chenh_lech',
        'sl_tra',
        'gia_tri_tra',
        'doanh_thu_thuan',
        'ma_hoa_don',
        'thoi_gian',
        'nguoi_nhan_don',
        'khach_hang',
        'sl',
        'gia_tri_niem_yet_chi_tiet',
        'doanh_thu_chi_tiet',
        'gia_tri_ban_chi_tiet',
        'gia_von_unit',
    ];

    protected $casts = [
        'sl_ban' => 'decimal:2',
        'sl' => 'decimal:2',
        'sl_tra' => 'decimal:2',
        'gia_tri_niem_yet' => 'decimal:2',
        'doanh_thu' => 'decimal:2',
        'chenh_lech' => 'decimal:2',
        'gia_tri_tra' => 'decimal:2',
        'doanh_thu_thuan' => 'decimal:2',
        'gia_tri_niem_yet_chi_tiet' => 'decimal:2',
        'doanh_thu_chi_tiet' => 'decimal:2',
        'gia_tri_ban_chi_tiet' => 'decimal:2',
        'gia_von_unit' => 'integer', // VND: số nguyên đồng
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(FoodSalesReport::class, 'food_sales_report_id');
    }
}
