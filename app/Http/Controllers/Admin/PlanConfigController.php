<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanConfig;
use Illuminate\Http\Request;

class PlanConfigController extends Controller
{
    public function update(Request $request)
    {
        $config = PlanConfig::getFullConfig();
        $list = $config['list'] ?? [];

        foreach (array_keys($list) as $key) {
            $list[$key]['name'] = $request->input("list.{$key}.name", $list[$key]['name'] ?? '');
            $list[$key]['price'] = (int) $request->input("list.{$key}.price", $list[$key]['price'] ?? 0);
            $list[$key]['max_accounts'] = (int) $request->input("list.{$key}.max_accounts", $list[$key]['max_accounts'] ?? 1);
        }

        $termOptionsStr = $request->input('term_options_str');
        $termOptions = $termOptionsStr
            ? array_values(array_filter(array_map('intval', array_map('trim', explode(',', $termOptionsStr)))))
            : ($config['term_options'] ?? [3, 6, 12]);
        if (empty($termOptions)) {
            $termOptions = [3, 6, 12];
        }

        PlanConfig::setFullConfig([
            'term_months' => (int) $request->input('term_months', $config['term_months']),
            'term_options' => $termOptions,
            'order' => $config['order'] ?? PlanConfig::defaultConfig()['order'],
            'list' => $list,
        ]);

        return back()->with('success', 'Đã lưu cấu hình gói.');
    }

    public function adjustPrices(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:add,subtract,multiply,divide'],
            'value' => ['required', 'numeric', 'min:0.0001'],
        ], [
            'type.in' => 'Chọn loại: Cộng, Trừ, Nhân hoặc Chia.',
            'value.min' => 'Giá trị phải lớn hơn 0.',
        ]);

        PlanConfig::adjustAllPrices(
            $validated['type'],
            (float) $validated['value']
        );

        $label = match ($validated['type']) {
            'add' => 'cộng ' . number_format($validated['value'], 0, ',', '.') . ' VND',
            'subtract' => 'trừ ' . number_format($validated['value'], 0, ',', '.') . ' VND',
            'multiply' => 'nhân ' . $validated['value'],
            'divide' => 'chia ' . $validated['value'],
        };

        return back()->with('success', 'Đã áp dụng điều chỉnh đồng loạt (' . $label . ') cho tất cả gói.');
    }
}
