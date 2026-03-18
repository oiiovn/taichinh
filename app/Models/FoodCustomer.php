<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodCustomer extends Model
{
    protected $table = 'food_customers';

    protected $fillable = [
        'user_id',
        'customer_key',
        'customer_name',
        'first_order_date',
        'last_order_date',
        'order_count',
        'is_returning_customer',
    ];

    protected $casts = [
        'first_order_date' => 'date',
        'last_order_date' => 'date',
        'is_returning_customer' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Đồng bộ bảng food_customers từ toàn bộ báo cáo bán hàng của user.
     * Gom đơn theo (report_id, ma_hoa_don), gom khách theo customer_key (chuẩn hóa).
     */
    public static function syncForUser(int $userId): void
    {
        $reports = FoodSalesReport::query()
            ->where('user_id', $userId)
            ->with('items')
            ->orderBy('report_date')
            ->get();

        $byCustomer = [];
        foreach ($reports as $report) {
            $reportDate = $report->report_date ? $report->report_date->format('Y-m-d') : null;
            if (! $reportDate) {
                continue;
            }
            $byInvoice = [];
            foreach ($report->items as $item) {
                $don = $item->ma_hoa_don ?? '_' . $item->id;
                if (! isset($byInvoice[$don])) {
                    $byInvoice[$don] = [
                        'date' => $reportDate,
                        'khach_hang' => $item->khach_hang,
                    ];
                }
            }
            foreach ($byInvoice as $ord) {
                $name = trim((string) ($ord['khach_hang'] ?? ''));
                if ($name === '') {
                    $name = '— Không tên —';
                }
                $key = mb_strtolower($name);
                if (! isset($byCustomer[$key])) {
                    $byCustomer[$key] = [
                        'name' => $name,
                        'first' => $ord['date'],
                        'last' => $ord['date'],
                        'count' => 0,
                    ];
                }
                $byCustomer[$key]['count']++;
                if (strcmp($ord['date'], $byCustomer[$key]['first']) < 0) {
                    $byCustomer[$key]['first'] = $ord['date'];
                }
                if (strcmp($ord['date'], $byCustomer[$key]['last']) > 0) {
                    $byCustomer[$key]['last'] = $ord['date'];
                }
            }
        }

        foreach ($byCustomer as $key => $c) {
            self::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'customer_key' => $key,
                ],
                [
                    'customer_name' => $c['name'],
                    'first_order_date' => $c['first'],
                    'last_order_date' => $c['last'],
                    'order_count' => $c['count'],
                    'is_returning_customer' => $c['count'] >= 2,
                ]
            );
        }

        self::query()
            ->where('user_id', $userId)
            ->whereNotIn('customer_key', array_keys($byCustomer))
            ->delete();
    }
}
