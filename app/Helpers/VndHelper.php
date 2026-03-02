<?php

namespace App\Helpers;

/**
 * Quy ước tiền VND (Việt Nam Đồng):
 *
 * - Luôn lưu trong DB dạng giá trị số (số nguyên đồng), không lưu chuỗi có dấu chấm
 *   phân cách hàng nghìn (ví dụ: lưu 21822, không lưu 21.822).
 * - Không format số theo locale (dấu chấm hàng nghìn) rồi đem tính toán hoặc lưu.
 * - Khi nhập từ user: "21,822" (dấu phẩy phân cách hàng nghìn) → parse thành 21822 rồi mới lưu.
 * - Hiển thị: format ra chuỗi (21,822 đ) chỉ để hiển thị, không dùng chuỗi đó để lưu/tính.
 */
class VndHelper
{
    /**
     * Chuẩn hóa giá VND từ input (form/paste): bỏ dấu phẩy và dấu chấm phân cách hàng nghìn, parse thành số.
     * VD: "21,822" hoặc "21.822" → 21822. Luôn trả về >= 0.
     * Chuỗi từ Excel/Sheet thường có dấu chấm hàng nghìn → bỏ hết . và , rồi parse, không dùng (float) trực tiếp.
     */
    public static function parseAmount(mixed $value): float
    {
        if (is_int($value) || (is_float($value) && $value === round($value))) {
            return max(0, (float) $value);
        }
        $s = trim((string) $value);
        $s = str_replace([' ', ',', '.'], '', $s);
        if ($s === '') {
            return 0.0;
        }

        return max(0, (float) $s);
    }

    /**
     * Lưu VND dạng số nguyên đồng (tránh lưu nhầm 21.822 thay vì 21822).
     */
    public static function toStoredAmount(mixed $value): int
    {
        return (int) round(self::parseAmount($value));
    }
}
