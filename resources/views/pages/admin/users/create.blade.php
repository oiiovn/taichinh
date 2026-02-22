@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Thêm user</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.index') }}">Quản trị</a>
            <span class="text-gray-400">/</span>
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.users.index') }}">Quản lý user</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Thêm</span>
        </nav>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6 max-w-xl">
        <form action="{{ route('admin.users.store') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tên *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="Họ tên">
                    @error('name')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="email@example.com">
                    @error('email')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Mật khẩu *</label>
                    <input type="password" name="password" required autocomplete="off"
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="••••••••">
                    @error('password')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Xác nhận mật khẩu *</label>
                    <input type="password" name="password_confirmation" required autocomplete="off"
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="••••••••">
                </div>
                @php $plansList = $plansList ?? \App\Models\PlanConfig::getList(); @endphp
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Gói</label>
                    <select name="plan" id="plan_select" data-default-term-months="{{ \App\Models\PlanConfig::getTermMonths() }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        <option value="">— Không gói —</option>
                        @foreach($plansList as $key => $info)
                            <option value="{{ $key }}" {{ old('plan') === $key ? 'selected' : '' }}>{{ $info['name'] ?? $key }} (tối đa {{ $info['max_accounts'] ?? 0 }} TK)</option>
                        @endforeach
                    </select>
                    @error('plan')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Hết hạn gói</label>
                    <input type="date" name="plan_expires_at" id="plan_expires_at" value="{{ old('plan_expires_at') }}"
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                        placeholder="Để trống = chưa set">
                    @error('plan_expires_at')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="is_admin" value="0">
                    <input type="checkbox" name="is_admin" value="1" id="is_admin" {{ old('is_admin') ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <label for="is_admin" class="text-sm text-gray-700 dark:text-gray-300">Quyền admin</label>
                </div>
                @if(!empty($featureList))
                <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                    <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Quyền sử dụng tính năng</p>
                    <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Chỉ khi được bật, user mới truy cập được tính năng tương ứng.</p>
                    <div class="flex flex-wrap gap-4">
                        @foreach($featureList as $key => $label)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="features[{{ $key }}]" value="1" {{ old("features.{$key}", $key === 'tai_chinh') ? 'checked' : '' }}
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-lg bg-success-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Lưu</button>
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
