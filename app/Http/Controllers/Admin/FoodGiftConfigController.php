<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoodGiftConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FoodGiftConfigController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'item_value' => ['required', 'integer', 'min:1000'],
            'item_image' => ['nullable', 'image', 'max:4096'],
        ], [
            'item_name.required' => 'Vui lòng nhập tên món.',
            'item_value.required' => 'Vui lòng nhập giá trị quà tặng.',
        ]);

        $config = FoodGiftConfig::getConfig();
        $config->item_name = $validated['item_name'];
        $config->item_value = (int) $validated['item_value'];

        if ($request->hasFile('item_image')) {
            $oldPath = $config->item_image_path;
            $path = $request->file('item_image')->store('food-gifts', 'public');
            $config->item_image_path = $path;
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $config->save();

        return back()->with('success', 'Đã lưu cấu hình quà tặng.');
    }
}

