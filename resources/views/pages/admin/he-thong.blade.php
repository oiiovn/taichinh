@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Hệ thống</h2>
        <nav class="flex items-center gap-1.5 text-sm">
            <a class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300" href="{{ route('admin.index') }}">Quản trị</a>
            <span class="text-gray-400">/</span>
            <span class="text-gray-800 dark:text-white/90">Hệ thống</span>
        </nav>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="space-y-6">
        {{-- Cấu hình gói --}}
        @php
            $planConfig = $planConfig ?? \App\Models\PlanConfig::getFullConfig();
            $planList = $planConfig['list'] ?? [];
            $planListSorted = collect($planList)->sortBy(fn($p) => (int) ($p['max_accounts'] ?? 0))->keys();
        @endphp
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/5 p-5 md:p-6">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Cấu hình gói</h3>
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">Giá, tên và số tài khoản tối đa từng gói. Kỳ hạn mặc định và các kỳ cho phép chọn.</p>

            {{-- Điều chỉnh đồng loạt giá --}}
            <div class="mb-6 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Điều chỉnh đồng loạt giá (theo công thức)</h4>
                <form action="{{ route('admin.he-thong.plans.adjust-prices') }}" method="POST" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Công thức</label>
                        <select name="type" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="add">Cộng thêm (VND)</option>
                            <option value="subtract">Trừ bớt (VND)</option>
                            <option value="multiply">Nhân với</option>
                            <option value="divide">Chia cho</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Giá trị</label>
                        <input type="number" name="value" step="any" min="0.0001" placeholder="VD: 50000 hoặc 1.1" required
                            class="w-40 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <button type="submit" class="rounded-lg bg-primary-500 px-4 py-2 text-sm font-medium text-white hover:bg-primary-600 dark:bg-primary-600 dark:hover:bg-primary-500">Áp dụng cho tất cả gói</button>
                </form>
            </div>

            <form action="{{ route('admin.he-thong.plans.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-4 flex gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Kỳ hạn mặc định (tháng)</label>
                        <input type="number" name="term_months" value="{{ old('term_months', $planConfig['term_months'] ?? 3) }}" min="1" max="24"
                            class="w-24 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Kỳ hạn cho phép chọn (tháng, cách nhau dấu phẩy)</label>
                        <input type="text" name="term_options_str" value="{{ old('term_options_str', implode(', ', $planConfig['term_options'] ?? [3, 6, 12])) }}" placeholder="3, 6, 12"
                            class="w-full max-w-xs rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="pb-2 text-left font-medium text-gray-700 dark:text-gray-300">Gói (key)</th>
                                <th class="pb-2 text-left font-medium text-gray-700 dark:text-gray-300">Tên hiển thị</th>
                                <th class="pb-2 text-left font-medium text-gray-700 dark:text-gray-300">Giá (VND)</th>
                                <th class="pb-2 text-left font-medium text-gray-700 dark:text-gray-300">Số TK tối đa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($planListSorted as $key)
                                @php $p = $planList[$key] ?? []; @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                        <td class="py-2.5 font-mono text-gray-500">{{ $key }}</td>
                                        <td class="py-2.5">
                                            <input type="text" name="list[{{ $key }}][name]" value="{{ old("list.{$key}.name", $p['name'] ?? '') }}"
                                                class="w-full max-w-[120px] rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        </td>
                                        <td class="py-2.5">
                                            <input type="number" name="list[{{ $key }}][price]" value="{{ old("list.{$key}.price", $p['price'] ?? 0) }}" min="0"
                                                class="w-28 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        </td>
                                        <td class="py-2.5">
                                            <input type="number" name="list[{{ $key }}][max_accounts]" value="{{ old("list.{$key}.max_accounts", $p['max_accounts'] ?? 1) }}" min="1"
                                                class="w-20 rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        </td>
                                    </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">
                    <button type="submit" class="rounded-lg bg-success-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Lưu cấu hình gói</button>
                </div>
            </form>
        </div>

        {{-- Cấu hình thanh toán web (dùng cho hiển thị QR thanh toán khi user mua gói) --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/5 p-5 md:p-6">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Cấu hình thanh toán web</h3>
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">Thông tin tài khoản ngân hàng để tạo QR thanh toán khi user mua gói.</p>
            <form action="{{ route('admin.he-thong.payment.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Mã ngân hàng (VietQR) *</label>
                        <input type="text" name="bank_id" value="{{ old('bank_id', $paymentConfig?->bank_id ?? '') }}" placeholder="VD: 970422"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tên ngân hàng</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $paymentConfig?->bank_name ?? '') }}" placeholder="VD: MB Bank"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Số tài khoản ngân hàng admin *</label>
                        <input type="text" name="account_number" value="{{ old('account_number', $paymentConfig?->account_number ?? '') }}" placeholder="Nhập số tài khoản"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Chủ tài khoản *</label>
                        <input type="text" name="account_holder" value="{{ old('account_holder', $paymentConfig?->account_holder ?? '') }}" placeholder="Tên chủ tài khoản"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Chi nhánh</label>
                        <input type="text" name="branch" value="{{ old('branch', $paymentConfig?->branch ?? '') }}" placeholder="Chi nhánh / PGD"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Template QR</label>
                        <select name="qr_template" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="compact" {{ old('qr_template', $paymentConfig?->qr_template ?? 'compact') === 'compact' ? 'selected' : '' }}>Compact</option>
                            <option value="compact2" {{ old('qr_template', $paymentConfig?->qr_template ?? '') === 'compact2' ? 'selected' : '' }}>Compact 2</option>
                            <option value="qr_only" {{ old('qr_template', $paymentConfig?->qr_template ?? '') === 'qr_only' ? 'selected' : '' }}>Chỉ QR</option>
                        </select>
                    </div>
                </div>
                <div class="mt-5">
                    <button type="submit" class="rounded-lg bg-success-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Lưu cấu hình</button>
                </div>
            </form>
        </div>

        {{-- Pay2s – Lấy toàn bộ giao dịch (tất cả ngân hàng, tất cả ngày) --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/5 p-5 md:p-6">
            <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-white">Pay2s – Lấy toàn bộ giao dịch</h3>
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">Cấu hình Secret Key từ Pay2S (Tích hợp Web/App). Để lấy <strong>tất cả ngân hàng, tất cả ngày</strong>: để trống Số TK, Từ ngày = 01/01/2020, Đến ngày = 31/12/2029. Sau đó vào <a href="{{ route('admin.lich-su-giao-dich.index') }}" class="font-medium text-success-600 dark:text-success-400">Lịch sử giao dịch</a> và bấm "Lấy toàn bộ giao dịch".</p>
            <form action="{{ route('admin.he-thong.pay2s-api.update') }}" method="POST">
                @csrf
                @method('PUT')
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Secret Key *</label>
                        <input type="text" name="secret_key" value="{{ old('secret_key', $pay2sApiConfig?->secret_key ?? '') }}" placeholder="Secret key từ Pay2S"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Base URL</label>
                        <input type="url" name="base_url" value="{{ old('base_url', $pay2sApiConfig?->base_url ?? 'https://my.pay2s.vn') }}" placeholder="https://my.pay2s.vn"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Số TK (để trống = tất cả ngân hàng)</label>
                        <input type="text" name="bank_accounts" value="{{ old('bank_accounts', $pay2sApiConfig?->bank_accounts ?? '') }}" placeholder="Trống hoặc VD: 737478888,46241987"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Từ ngày (dd/mm/yyyy)</label>
                        <input type="text" name="fetch_begin" value="{{ old('fetch_begin', $pay2sApiConfig?->fetch_begin ?? '01/01/2020') }}" placeholder="01/01/2020"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Đến ngày (dd/mm/yyyy)</label>
                        <input type="text" name="fetch_end" value="{{ old('fetch_end', $pay2sApiConfig?->fetch_end ?? '31/12/2029') }}" placeholder="31/12/2029"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                </div>
                <input type="hidden" name="path_transactions" value="userapi/transactions">
                <div class="mt-4 flex items-center gap-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" id="pay2s_active" {{ old('is_active', $pay2sApiConfig?->is_active ?? true) ? 'checked' : '' }} class="h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <label for="pay2s_active" class="text-sm font-medium text-gray-700 dark:text-gray-300">Bật Pay2s</label>
                </div>
                <div class="mt-5">
                    <button type="submit" class="rounded-lg bg-success-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Lưu cấu hình</button>
                    <a href="{{ route('admin.lich-su-giao-dich.index') }}" class="ml-3 inline-flex rounded-lg bg-gray-200 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Xem lịch sử giao dịch</a>
                </div>
            </form>
        </div>
    </div>
@endsection
