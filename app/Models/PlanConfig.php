<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlanConfig extends Model
{
    const CONFIG_KEY = 'plans';

    protected $table = 'plan_config';

    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Lấy toàn bộ cấu hình gói (từ DB nếu có, không thì từ config).
     */
    public static function getFullConfig(): array
    {
        return Cache::remember('plan_config.full', 300, function () {
            $row = self::where('key', self::CONFIG_KEY)->first();
            if ($row && is_array($row->value)) {
                return array_merge(self::defaultConfig(), $row->value);
            }
            return config('plans', self::defaultConfig());
        });
    }

    public static function defaultConfig(): array
    {
        return [
            'term_months' => 3,
            'term_options' => [3, 6, 12],
            'order' => ['basic' => 0, 'starter' => 1, 'pro' => 2, 'team' => 3, 'company' => 4, 'corporate' => 5],
            'list' => [
                'basic' => ['name' => 'BASIC', 'price' => 150000, 'max_accounts' => 1],
                'starter' => ['name' => 'STARTER', 'price' => 250000, 'max_accounts' => 3],
                'pro' => ['name' => 'PRO', 'price' => 450000, 'max_accounts' => 5],
                'team' => ['name' => 'TEAM', 'price' => 750000, 'max_accounts' => 10],
                'company' => ['name' => 'COMPANY', 'price' => 1750000, 'max_accounts' => 25],
                'corporate' => ['name' => 'CORPORATE', 'price' => 3250000, 'max_accounts' => 50],
            ],
        ];
    }

    public static function getList(): array
    {
        return self::getFullConfig()['list'] ?? [];
    }

    public static function getOrder(): array
    {
        return self::getFullConfig()['order'] ?? [];
    }

    public static function getTermOptions(): array
    {
        return self::getFullConfig()['term_options'] ?? [3, 6, 12];
    }

    public static function getTermMonths(): int
    {
        return (int) (self::getFullConfig()['term_months'] ?? 3);
    }

    /**
     * Lưu toàn bộ cấu hình (và xóa cache).
     */
    public static function setFullConfig(array $data): void
    {
        $row = self::firstOrNew(['key' => self::CONFIG_KEY]);
        $row->value = array_merge(self::getFullConfig(), $data);
        $row->save();
        Cache::forget('plan_config.full');
    }

    /**
     * Áp dụng công thức đồng loạt lên giá các gói.
     * $type: 'add' | 'subtract' | 'multiply' | 'divide'
     * $value: số (VND cho add/subtract, hệ số cho multiply/divide).
     */
    public static function adjustAllPrices(string $type, float $value): void
    {
        $config = self::getFullConfig();
        $list = $config['list'] ?? [];
        foreach ($list as $key => $item) {
            $price = (float) ($item['price'] ?? 0);
            $list[$key]['price'] = match ($type) {
                'add' => (int) round($price + $value),
                'subtract' => (int) max(0, round($price - $value)),
                'multiply' => $value > 0 ? (int) round($price * $value) : $price,
                'divide' => $value != 0 ? (int) round($price / $value) : $price,
                default => $price,
            };
        }
        $config['list'] = $list;
        self::setFullConfig($config);
    }

    public static function clearCache(): void
    {
        Cache::forget('plan_config.full');
    }
}
