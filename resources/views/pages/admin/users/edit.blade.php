@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Sửa user</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.index') }}">Quản trị</a>
            <span class="text-gray-400">/</span>
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.users.index') }}">Quản lý user</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Sửa</span>
        </nav>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 max-w-xl">
        <form action="{{ route('admin.users.update', $user) }}" method="POST" autocomplete="off">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tên *</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="Họ tên">
                    @error('name')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="email@example.com">
                    @error('email')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Mật khẩu mới (để trống nếu không đổi)</label>
                    <input type="password" name="password" autocomplete="new-password" readonly
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="••••••••"
                        onfocus="this.removeAttribute('readonly')">
                    @error('password')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Xác nhận mật khẩu mới</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password" readonly
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="••••••••"
                        onfocus="this.removeAttribute('readonly')">
                </div>
                @php $plansList = $plansList ?? \App\Models\PlanConfig::getList(); @endphp
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Gói</label>
                    <select name="plan" id="plan_select" data-default-term-months="{{ \App\Models\PlanConfig::getTermMonths() }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="">— Không gói —</option>
                        @foreach($plansList as $key => $info)
                            <option value="{{ $key }}" {{ old('plan', $user->plan) === $key ? 'selected' : '' }}>{{ $info['name'] ?? $key }} (tối đa {{ $info['max_accounts'] ?? 0 }} TK)</option>
                        @endforeach
                    </select>
                    @error('plan')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Hết hạn gói</label>
                    <input type="date" name="plan_expires_at" id="plan_expires_at" value="{{ old('plan_expires_at', $user->plan_expires_at?->format('Y-m-d')) }}"
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="Để trống = chưa set">
                    @error('plan_expires_at')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_admin" value="0">
                    <input type="checkbox" name="is_admin" value="1" id="is_admin" {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <label for="is_admin" class="text-sm text-gray-700 dark:text-gray-300">Quyền admin</label>
                </div>
                <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Quyền Food (quản lý)</p>
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Chỉ hiển thị các mục được tích chọn.</p>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_manage_food_tong_quan" value="0">
                            <input type="checkbox" name="can_manage_food_tong_quan" value="1" {{ old('can_manage_food_tong_quan', $user->can_manage_food_tong_quan) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Tổng quan</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_manage_food_doanh_so" value="0">
                            <input type="checkbox" name="can_manage_food_doanh_so" value="1" {{ old('can_manage_food_doanh_so', $user->can_manage_food_doanh_so) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Doanh số</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_manage_food_san_pham" value="0">
                            <input type="checkbox" name="can_manage_food_san_pham" value="1" {{ old('can_manage_food_san_pham', $user->can_manage_food_san_pham) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Sản phẩm</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_manage_food_bao_cao" value="0">
                            <input type="checkbox" name="can_manage_food_bao_cao" value="1" {{ old('can_manage_food_bao_cao', $user->can_manage_food_bao_cao) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Báo cáo bán hàng</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_manage_food_thong_ke_buff" value="0">
                            <input type="checkbox" name="can_manage_food_thong_ke_buff" value="1" {{ old('can_manage_food_thong_ke_buff', $user->can_manage_food_thong_ke_buff) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Thống kê seeding</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_create_food_buff_order" value="0">
                            <input type="checkbox" name="can_create_food_buff_order" value="1" {{ old('can_create_food_buff_order', $user->can_create_food_buff_order) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Được tạo đơn Food thủ công</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_manage_food_reviews" value="0">
                            <input type="checkbox" name="can_manage_food_reviews" value="1" {{ old('can_manage_food_reviews', $user->can_manage_food_reviews) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Đánh giá (/food/danh-gia)</span>
                        </label>
                        <p class="pl-6 text-xs text-gray-500 dark:text-gray-400">User chỉ được xem trang /food/danh-gia. Tự bật tính năng Food khi lưu.</p>
                        <div class="pl-6">
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Nhân viên được xem ở thống kê seeding (mỗi dòng hoặc dấu phẩy)</label>
                            <textarea name="food_buff_assigned_employees" rows="3" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Ví dụ: Nguyễn Văn A, Trần Thị B">{{ old('food_buff_assigned_employees', implode("\n", $user->getFoodBuffAssignedEmployees())) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Để trống: xem tất cả nhân viên.</p>
                            @error('food_buff_assigned_employees')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <label class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                            <input type="hidden" name="can_manage_food_employees" value="0">
                            <input type="checkbox" name="can_manage_food_employees" value="1" {{ old('can_manage_food_employees', $user->can_manage_food_employees) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Quản lý nhân viên</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_manage_food_cham_cong" value="0">
                            <input type="checkbox" name="can_manage_food_cham_cong" value="1" {{ old('can_manage_food_cham_cong', $user->can_manage_food_cham_cong) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Quản lý chấm công</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_manage_food_xin_nghi" value="0">
                            <input type="checkbox" name="can_manage_food_xin_nghi" value="1" {{ old('can_manage_food_xin_nghi', $user->can_manage_food_xin_nghi) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Quản lý xin nghỉ</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_manage_food_ung_luong" value="0">
                            <input type="checkbox" name="can_manage_food_ung_luong" value="1" {{ old('can_manage_food_ung_luong', $user->can_manage_food_ung_luong) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Quản lý ứng lương</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="hidden" name="can_manage_food_luong" value="0">
                            <input type="checkbox" name="can_manage_food_luong" value="1" {{ old('can_manage_food_luong', $user->can_manage_food_luong) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Quản lý bảng lương</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2">
                            <input type="hidden" name="can_record_food_salary_payment" value="0">
                            <input type="checkbox" name="can_record_food_salary_payment" value="1" {{ old('can_record_food_salary_payment', $user->can_record_food_salary_payment) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Ghi nhận đã trả lương (thủ công)</span>
                        </label>
                        <label class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                            <input type="hidden" name="can_use_food_employee" value="0">
                            <input type="checkbox" name="can_use_food_employee" value="1" {{ old('can_use_food_employee', $user->can_use_food_employee) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Được dùng phần nhân viên (chấm công, xin nghỉ, ứng lương, lương của tôi)</span>
                        </label>
                        <label class="flex items-center gap-2 mt-2">
                            <input type="hidden" name="can_use_qr_cham_cong" value="0">
                            <input type="checkbox" name="can_use_qr_cham_cong" value="1" {{ old('can_use_qr_cham_cong', $user->can_use_qr_cham_cong) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-sm text-gray-700 dark:text-gray-300">QR chấm công (chỉ truy cập trang hiển thị mã QR)</span>
                        </label>
                    </div>
                </div>
                @if(!empty($featureList))
                <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Quyền sử dụng tính năng</p>
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Chỉ khi được bật, user mới truy cập được tính năng tương ứng.</p>
                    <div class="flex flex-wrap gap-4">
                        @php $allowed = $user->allowed_features ?? []; $allAllowed = $allowed === [] || $allowed === null; @endphp
                        @foreach($featureList as $key => $label)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="features[{{ $key }}]" value="1"
                                    {{ old("features.{$key}", $allAllowed || in_array($key, $allowed, true)) ? 'checked' : '' }}
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-lg bg-success-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Cập nhật</button>
                <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Hủy</a>
            </div>
        </form>
    </div>
    <script>
        (function(){
            var sel = document.getElementById('plan_select');
            var exp = document.getElementById('plan_expires_at');
            if (!sel || !exp) return;
            sel.addEventListener('change', function(){
                if (!this.value) return;
                var months = parseInt(sel.getAttribute('data-default-term-months') || '3', 10);
                var d = new Date();
                d.setMonth(d.getMonth() + months);
                exp.value = d.toISOString().slice(0, 10);
            });
        })();
    </script>
@endsection
