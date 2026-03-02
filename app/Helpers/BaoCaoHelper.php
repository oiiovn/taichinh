<?php

namespace App\Helpers;

class BaoCaoHelper
{
    /**
     * Format giá vốn: dấu phẩy phân cách hàng nghìn, có thập phân khi cần.
     * VD: 10000 → "10,000", 21.147 → "21,147", 441093.5 → "441,093.5"
     */
    public static function formatGiaVon(mixed $value): string
    {
        $n = (float) $value;
        $rounded = round($n, 3);
        $decimals = ($rounded == floor($rounded)) ? 0 : 3;

        return number_format($rounded, $decimals, '.', ',');
    }

    /**
     * Format giá vốn số nguyên (làm tròn, không thập phân). VD: 180932.34 → "180,932"
     */
    public static function formatGiaVonNguyen(mixed $value): string
    {
        $n = (float) $value;

        return number_format((int) round($n), 0, '.', ',');
    }
}
