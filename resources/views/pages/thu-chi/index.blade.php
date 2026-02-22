@extends('layouts.app')

@section('content')
@php
    $tab = $tab ?? 'dashboard';
@endphp
<div class="space-y-6" x-data="{ showConfirmDelete: false, formIdToSubmit: null }" @confirm-delete-open.window="showConfirmDelete = true; formIdToSubmit = $event.detail.formId" @confirm-delete.window="if (formIdToSubmit) { const f = document.getElementById(formIdToSubmit); if (f) f.submit(); } formIdToSubmit = null; showConfirmDelete = false">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-theme-xl font-semibold text-gray-900 dark:text-white">Thu chi ước tính</h1>
        <nav class="flex rounded-lg border border-gray-200 bg-gray-100 p-1 dark:border-gray-700 dark:bg-gray-800" aria-label="Tab thu chi">
            <a href="{{ route('thu-chi', ['tab' => 'dashboard']) }}" class="rounded-md px-4 py-2 text-theme-sm font-medium transition-colors {{ $tab === 'dashboard' ? 'bg-white text-brand-600 shadow-theme-xs dark:bg-gray-900 dark:text-brand-400' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">Dashboard</a>
            <a href="{{ route('thu-chi', ['tab' => 'ghi-chep']) }}" class="rounded-md px-4 py-2 text-theme-sm font-medium transition-colors {{ $tab === 'ghi-chep' ? 'bg-white text-brand-600 shadow-theme-xs dark:bg-gray-900 dark:text-brand-400' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">Ghi chép</a>
            <a href="{{ route('thu-chi', ['tab' => 'lich-su']) }}" class="rounded-md px-4 py-2 text-theme-sm font-medium transition-colors {{ $tab === 'lich-su' ? 'bg-white text-brand-600 shadow-theme-xs dark:bg-gray-900 dark:text-brand-400' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">Lịch sử</a>
        </nav>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-theme-sm text-success-800 dark:border-success-800 dark:bg-success-900/30 dark:text-success-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-800 dark:border-error-800 dark:bg-error-900/30 dark:text-error-200">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-theme-sm text-error-800 dark:border-error-800 dark:bg-error-900/30 dark:text-error-200">
            <ul class="list-inside list-disc">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($tab === 'dashboard')
    {{-- Tab Dashboard: Hôm nay + 7 ngày + Theo nguồn thu --}}
    {{-- Block 1 – Hôm nay --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
        <h2 class="mb-4 text-theme-base font-semibold text-gray-900 dark:text-white">Hôm nay</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Thu hôm nay</p>
                <p class="mt-1 text-xl font-semibold text-success-600 dark:text-success-400">{{ number_format($today['thu']) }} ₫</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Chi hôm nay</p>
                <p class="mt-1 text-xl font-semibold text-error-600 dark:text-error-400">{{ number_format($today['chi']) }} ₫</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Chênh lệch</p>
                @php $net = $today['net']; @endphp
                <p class="mt-1 text-xl font-semibold {{ $net >= 0 ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">
                    {{ $net >= 0 ? '+' : '' }}{{ number_format($net) }} ₫
                </p>
            </div>
        </div>
    </div>

    {{-- Dự kiến tháng này (trung bình ngày đã qua × số ngày tháng) --}}
    @php $mp = $monthProjection ?? []; @endphp
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
        <h2 class="mb-1 text-theme-base font-semibold text-gray-900 dark:text-white">Dự kiến tháng này</h2>
        <p class="mb-4 text-theme-xs text-gray-500 dark:text-gray-400">Trung bình {{ $mp['days_elapsed'] ?? 0 }} ngày đã qua × {{ $mp['days_in_month'] ?? 30 }} ngày trong tháng</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Thu dự kiến</p>
                <p class="mt-1 text-xl font-semibold text-success-600 dark:text-success-400">{{ number_format($mp['thu'] ?? 0) }} ₫</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Chi dự kiến</p>
                <p class="mt-1 text-xl font-semibold text-error-600 dark:text-error-400">{{ number_format($mp['chi'] ?? 0) }} ₫</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Chênh lệch dự kiến</p>
                @php $netM = $mp['net'] ?? 0; @endphp
                <p class="mt-1 text-xl font-semibold {{ $netM >= 0 ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">
                    {{ $netM >= 0 ? '+' : '' }}{{ number_format($netM) }} ₫
                </p>
            </div>
        </div>
    </div>

    {{-- Block 2 – 7 ngày gần nhất --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
        <h2 class="mb-4 text-theme-base font-semibold text-gray-900 dark:text-white">7 ngày gần nhất</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
            @foreach($last7Days as $day)
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-theme-sm font-semibold text-gray-900 dark:text-white">{{ $day['date_label'] }}</p>
                    @foreach($day['by_source'] as $src)
                        @if($src['amount'] > 0)
                            <p class="mt-1 text-theme-xs text-gray-600 dark:text-gray-400">{{ $src['name'] }}: {{ number_format($src['amount']) }} ₫</p>
                        @endif
                    @endforeach
                    <p class="mt-2 text-theme-sm font-medium text-success-600 dark:text-success-400">Thu: {{ number_format($day['thu']) }} ₫</p>
                    <p class="text-theme-sm font-medium text-error-600 dark:text-error-400">Chi: {{ number_format($day['chi']) }} ₫</p>
                    <p class="text-theme-sm font-semibold {{ $day['net'] >= 0 ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">
                        Net: {{ $day['net'] >= 0 ? '+' : '' }}{{ number_format($day['net']) }} ₫
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Block 3 – Theo nguồn thu --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
        <h2 class="mb-4 text-theme-base font-semibold text-gray-900 dark:text-white">Theo nguồn thu</h2>
        @if(empty($bySource))
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Chưa có nguồn thu. Thêm nguồn thu ở tab Ghi chép.</p>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($bySource as $src)
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800" @if($src['color']) style="border-left: 4px solid {{ $src['color'] }}" @endif>
                        <p class="text-theme-base font-semibold text-gray-900 dark:text-white">{{ $src['name'] }}</p>
                        <p class="mt-2 text-theme-sm text-gray-600 dark:text-gray-400">Hôm nay: <strong>{{ number_format($src['today']) }} ₫</strong></p>
                        <p class="text-theme-sm text-gray-600 dark:text-gray-400">Tuần này: <strong>{{ number_format($src['week']) }} ₫</strong></p>
                        <p class="text-theme-sm text-gray-600 dark:text-gray-400">Tháng này: <strong>{{ number_format($src['month']) }} ₫</strong></p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @elseif($tab === 'ghi-chep')
    {{-- Tab Ghi chép: Form thêm nguồn, thu, chi + Định kỳ --}}
    {{-- Form: Thêm nguồn thu + Thêm thu + Thêm chi --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <h3 class="mb-3 text-theme-sm font-semibold text-gray-900 dark:text-white">Thêm nguồn thu</h3>
            <form method="POST" action="{{ route('thu-chi.sources.store') }}" class="space-y-3">
                @csrf
                <input type="text" name="name" value="{{ old('name') }}" placeholder="VD: Food, Chạy hàng" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <input type="text" name="type" value="{{ old('type') }}" placeholder="Loại (tùy chọn)"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <input type="text" name="color" value="{{ old('color') }}" placeholder="Màu #hex (tùy chọn)"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-2 text-theme-sm font-medium text-white hover:bg-brand-600">Thêm nguồn</button>
            </form>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <h3 class="mb-3 text-theme-sm font-semibold text-gray-900 dark:text-white">Thêm thu</h3>
            <form method="POST" action="{{ route('thu-chi.income.store') }}" class="space-y-3">
                @csrf
                <select name="source_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="">Chọn nguồn thu</option>
                    @foreach($sources as $s)
                        <option value="{{ $s->id }}" {{ old('source_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <input type="text" name="amount" value="{{ old('amount') }}" placeholder="Số tiền" required inputmode="numeric" data-format-vnd
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <input type="text" name="note" value="{{ old('note') }}" placeholder="Ghi chú"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <button type="submit" class="w-full rounded-lg bg-success-500 px-4 py-2 text-theme-sm font-medium text-white hover:bg-success-600">Thêm thu</button>
            </form>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <h3 class="mb-3 text-theme-sm font-semibold text-gray-900 dark:text-white">Thêm chi</h3>
            <form method="POST" action="{{ route('thu-chi.expense.store') }}" class="space-y-3">
                @csrf
                <input type="date" name="date" value="{{ old('date', date('Y-m-d')) }}" required
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <input type="text" name="amount" value="{{ old('amount') }}" placeholder="Số tiền" required inputmode="numeric" data-format-vnd
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <input type="text" name="category" value="{{ old('category') }}" placeholder="Danh mục (tùy chọn)"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <input type="text" name="note" value="{{ old('note') }}" placeholder="Ghi chú"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <button type="submit" class="w-full rounded-lg bg-error-500 px-4 py-2 text-theme-sm font-medium text-white hover:bg-error-600">Thêm chi</button>
            </form>
        </div>
    </div>

    {{-- Thu/chi định kỳ – mỗi ngày tự tạo bản ghi --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
        <h2 class="mb-4 text-theme-base font-semibold text-gray-900 dark:text-white">Thu / Chi định kỳ (tự tạo mỗi ngày)</h2>
        <p class="mb-4 text-theme-sm text-gray-500 dark:text-gray-400">Thiết lập nguồn thu hoặc khoản chi lặp theo ngày, tuần hoặc tháng. Hệ thống chạy cron mỗi ngày 05:00 để tạo bản ghi ước tính.</p>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                <h3 class="mb-3 text-theme-sm font-semibold text-gray-900 dark:text-white">Thêm mẫu định kỳ</h3>
                <form method="POST" action="{{ route('thu-chi.recurring.store') }}" class="space-y-3">
                    @csrf
                    <div class="flex gap-2">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="type" value="income" {{ old('type', 'income') === 'income' ? 'checked' : '' }}>
                            <span class="text-theme-sm">Thu</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="type" value="expense" {{ old('type') === 'expense' ? 'checked' : '' }}>
                            <span class="text-theme-sm">Chi</span>
                        </label>
                    </div>
                    <div id="recurring-source-wrap">
                        <select name="source_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="">Chọn nguồn thu</option>
                            @foreach($sources as $s)
                                <option value="{{ $s->id }}" {{ old('source_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="text" name="amount" value="{{ old('amount') }}" placeholder="Số tiền mỗi kỳ" required inputmode="numeric" data-format-vnd
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <select name="frequency" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="daily" {{ old('frequency', 'daily') === 'daily' ? 'selected' : '' }}>Mỗi ngày</option>
                        <option value="weekly" {{ old('frequency') === 'weekly' ? 'selected' : '' }}>Mỗi tuần</option>
                        <option value="monthly" {{ old('frequency') === 'monthly' ? 'selected' : '' }}>Mỗi tháng</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" name="start_date" value="{{ old('start_date', date('Y-m-d')) }}" required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <input type="date" name="end_date" value="{{ old('end_date') }}" placeholder="Đến ngày (tùy chọn)"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <input type="text" name="note" value="{{ old('note') }}" placeholder="Ghi chú"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <input type="text" name="category" value="{{ old('category') }}" placeholder="Danh mục chi (nếu là chi)"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-theme-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-2 text-theme-sm font-medium text-white hover:bg-brand-600">Thêm định kỳ</button>
                </form>
            </div>
            <div>
                <h3 class="mb-3 text-theme-sm font-semibold text-gray-900 dark:text-white">Mẫu đang dùng</h3>
                @if($recurringTemplates->isEmpty())
                    <p class="text-theme-sm text-gray-500 dark:text-gray-400">Chưa có mẫu định kỳ.</p>
                @else
                    <ul class="space-y-2">
                        @foreach($recurringTemplates as $t)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                                <span class="text-theme-sm">
                                    @if($t->type === 'income')
                                        <span class="font-medium text-success-600 dark:text-success-400">Thu:</span> {{ $t->source?->name ?? '—' }}
                                    @else
                                        <span class="font-medium text-error-600 dark:text-error-400">Chi:</span> {{ $t->category ?: $t->note ?: 'Chi định kỳ' }}
                                    @endif
                                    — {{ number_format($t->amount) }} ₫ /
                                    {{ $t->frequency === 'daily' ? 'ngày' : ($t->frequency === 'weekly' ? 'tuần' : 'tháng') }}
                                    @if(!$t->is_active)
                                        <span class="text-gray-500 dark:text-gray-400">(đã tắt)</span>
                                    @endif
                                </span>
                                <span class="flex gap-1">
                                    <form method="POST" action="{{ route('thu-chi.recurring.toggle', $t->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="rounded px-2 py-1 text-theme-xs {{ $t->is_active ? 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-300' : 'bg-success-500 text-white' }}">
                                            {{ $t->is_active ? 'Tắt' : 'Bật' }}
                                        </button>
                                    </form>
                                    <form id="form-delete-recurring-{{ $t->id }}" method="POST" action="{{ route('thu-chi.recurring.destroy', $t->id) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-recurring-{{ $t->id }}' })" class="rounded px-2 py-1 text-theme-xs bg-error-100 text-error-700 dark:bg-error-900/30 dark:text-error-300">Xóa</button>
                                    </form>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    @else
    {{-- Tab Lịch sử: Một bảng thu + chi, sửa, xóa --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 dark:text-white">
        <h2 class="mb-4 text-theme-base font-semibold text-gray-900 dark:text-white">Lịch sử thu chi (gần nhất)</h2>
        @if(isset($historyEntries) && $historyEntries->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-theme-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Ngày</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Loại</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Nguồn / Danh mục</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Số tiền</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Ghi chú</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-700 dark:text-gray-300">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($historyEntries as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ $row['date']->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">
                                    @if($row['type'] === 'income')
                                        <span class="font-medium text-success-600 dark:text-success-400">Thu</span>
                                    @else
                                        <span class="font-medium text-error-600 dark:text-error-400">Chi</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['label'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-right font-medium {{ $row['type'] === 'income' ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">
                                    {{ $row['type'] === 'income' ? '+' : '-' }}{{ number_format($row['amount']) }} ₫
                                </td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $row['note'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if($row['type'] === 'income')
                                        <a href="{{ route('thu-chi.income.edit', $row['id']) }}" class="text-brand-600 hover:underline dark:text-brand-400">Sửa</a>
                                        <form id="form-delete-income-{{ $row['id'] }}" method="POST" action="{{ route('thu-chi.income.destroy', $row['id']) }}" class="inline ml-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-income-{{ $row['id'] }}' })" class="text-error-600 hover:underline dark:text-error-400">Xóa</button>
                                        </form>
                                    @else
                                        <a href="{{ route('thu-chi.expense.edit', $row['id']) }}" class="text-brand-600 hover:underline dark:text-brand-400">Sửa</a>
                                        <form id="form-delete-expense-{{ $row['id'] }}" method="POST" action="{{ route('thu-chi.expense.destroy', $row['id']) }}" class="inline ml-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-expense-{{ $row['id'] }}' })" class="text-error-600 hover:underline dark:text-error-400">Xóa</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-theme-sm text-gray-500 dark:text-gray-400">Chưa có bản ghi thu chi.</p>
        @endif
    </div>
    @endif
    <x-ui.confirm-delete openVar="showConfirmDelete" title="Xác nhận xóa" defaultMessage="Bạn có chắc muốn xóa? Hành động không thể hoàn tác." />
</div>
@endsection
