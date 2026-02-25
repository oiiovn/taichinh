<?php

namespace App\Http\Controllers\TaiChinh;

use App\Http\Controllers\Controller;
use App\Models\TransactionHistory;
use App\Models\UserCategory;
use App\Services\TaiChinh\TaiChinhViewCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $redirectTab = fn ($q = []) => redirect()->route('tai-chinh', array_merge(['tab' => 'giao-dich'], $q));

        if (! $user) {
            return $redirectTab()->with('error', 'Vui lòng đăng nhập.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'type' => 'required|in:income,expense',
        ], [
            'name.required' => 'Vui lòng nhập tên danh mục.',
            'name.max' => 'Tên danh mục tối đa 100 ký tự.',
            'type.required' => 'Vui lòng chọn loại (Thu hoặc Chi).',
            'type.in' => 'Loại không hợp lệ.',
        ]);

        if ($validator->fails()) {
            return $redirectTab()->withErrors($validator)->withInput()->with('open_modal', 'danh-muc');
        }

        $name = trim($request->input('name'));
        $type = $request->input('type');

        $exists = UserCategory::where('user_id', $user->id)
            ->where('type', $type)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return $redirectTab()->withErrors(['name' => 'Bạn đã có danh mục trùng tên và loại.'])->withInput()->with('open_modal', 'danh-muc');
        }

        try {
            UserCategory::create([
                'user_id' => $user->id,
                'name' => $name,
                'type' => $type,
                'based_on_system_category_id' => null,
            ]);
            return $redirectTab()->with('success', 'Đã thêm danh mục "' . e($name) . '".');
        } catch (\Throwable $e) {
            Log::error('UserCategoryController@store: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return $redirectTab()->with('error', 'Không thêm được danh mục. Vui lòng thử lại sau.')->withInput()->with('open_modal', 'danh-muc');
        }
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $redirectTab = fn ($q = []) => redirect()->route('tai-chinh', array_merge(['tab' => 'giao-dich'], $q));
        if (! $user) {
            return $redirectTab()->with('error', 'Vui lòng đăng nhập.');
        }
        $cat = UserCategory::where('user_id', $user->id)->find($id);
        if (! $cat) {
            return $redirectTab()->with('error', 'Không tìm thấy danh mục.');
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'type' => 'required|in:income,expense',
        ], [
            'name.required' => 'Vui lòng nhập tên danh mục.',
            'name.max' => 'Tên danh mục tối đa 100 ký tự.',
            'type.required' => 'Vui lòng chọn loại (Thu hoặc Chi).',
            'type.in' => 'Loại không hợp lệ.',
        ]);
        if ($validator->fails()) {
            return $redirectTab()->withErrors($validator)->withInput()->with('open_modal', 'danh-muc')->with('edit_category_id', $id);
        }
        $name = trim($request->input('name'));
        $type = $request->input('type');
        $exists = UserCategory::where('user_id', $user->id)
            ->where('type', $type)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->where('id', '!=', $id)
            ->exists();
        if ($exists) {
            return $redirectTab()->withErrors(['name' => 'Bạn đã có danh mục trùng tên và loại.'])->withInput()->with('open_modal', 'danh-muc')->with('edit_category_id', $id);
        }
        try {
            $cat->update(['name' => $name, 'type' => $type]);
            TaiChinhViewCache::forget($user->id);
            return $redirectTab()->with('success', 'Đã cập nhật danh mục "' . e($name) . '".');
        } catch (\Throwable $e) {
            Log::error('UserCategoryController@update: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return $redirectTab()->with('error', 'Không cập nhật được danh mục.')->withInput()->with('open_modal', 'danh-muc')->with('edit_category_id', $id);
        }
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $redirectTab = fn ($q = []) => redirect()->route('tai-chinh', array_merge(['tab' => 'giao-dich'], $q));
        if (! $user) {
            return $redirectTab()->with('error', 'Vui lòng đăng nhập.');
        }
        $cat = UserCategory::where('user_id', $user->id)->find($id);
        if (! $cat) {
            return $redirectTab()->with('error', 'Không tìm thấy danh mục.');
        }
        try {
            TransactionHistory::where('user_category_id', $id)->update(['user_category_id' => null]);
            $cat->delete();
            TaiChinhViewCache::forget($user->id);
            return $redirectTab()->with('success', 'Đã xóa danh mục "' . e($cat->name) . '". Các giao dịch đã gán sẽ chuyển về chưa phân loại.');
        } catch (\Throwable $e) {
            Log::error('UserCategoryController@destroy: ' . $e->getMessage(), ['user_id' => $user->id, 'trace' => $e->getTraceAsString()]);
            return $redirectTab()->with('error', 'Không xóa được danh mục.');
        }
    }
}
