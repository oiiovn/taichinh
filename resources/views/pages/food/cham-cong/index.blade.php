@extends('layouts.food')

@section('foodContent')
@php
$fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
$formatWorkDate = function ($date) {
    $d = \Carbon\Carbon::parse($date);
    $thu = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'][$d->dayOfWeek] ?? '';
    return $d->format('d/m/Y').($thu !== '' ? ' ('.$thu.')' : '');
};
$dailySalary = function ($log, $emp) {
    if (! $emp) return null;
    $d = \Carbon\Carbon::parse($log->work_date)->startOfDay();
    $ar = $emp->applicableRateForDate($d);
    $r = (float) $ar['rate'];
    $t = $ar['type'] ?? \App\Models\Employee::SALARY_TYPE_HOUR;
    $gross = null;
    if ($t === \App\Models\Employee::SALARY_TYPE_HOUR) {
        $mins = $log->work_minutes ?? null;
        $gross = $mins !== null ? ($mins / 60) * $r : null;
    } elseif ($t === \App\Models\Employee::SALARY_TYPE_DAY) {
        $gross = $log->check_in_at && $log->check_out_at ? $r : null;
    } elseif ($t === \App\Models\Employee::SALARY_TYPE_MONTH) {
        $gross = $log->check_in_at && $log->check_out_at ? $r / 30 : null;
    }
    if ($gross === null) return null;
    $penalty = 0;
    if ($emp->usesLatePenalty()) {
        $lateMins = $emp->lateMinutesForCheckIn($log->check_in_at, $log->work_date);
        $penalty = $emp->latePenaltyForMinutes($lateMins);
    }
    return max(0, $gross - $penalty);
};
$lateInfo = function ($log, $emp) {
    if (! $emp || ! $emp->usesLatePenalty()) return ['minutes' => 0, 'penalty' => 0];
    $mins = $emp->lateMinutesForCheckIn($log->check_in_at, $log->work_date);
    return ['minutes' => $mins, 'penalty' => $emp->latePenaltyForMinutes($mins)];
};
$displayNote = function ($log, $emp) {
    $note = trim((string) ($log->note ?? ''));
    if ($emp) $note = trim($emp->stripLatePenaltyNote($note));
    return $note !== '' ? $note : null;
};
$inputClass = 'w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-brand-900/40';
$labelClass = 'mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
$hasManualOld = old('employee_id') || old('check_in_time') || old('check_out_time') || old('note');
$isSaleDay = fn ($date) => isset(($saleDateSet ?? [])[\Carbon\Carbon::parse($date)->toDateString()]);

$avatarThemes = [
    ['bg' => 'bg-emerald-100 text-emerald-700', 'ring' => 'ring-emerald-200'],
    ['bg' => 'bg-sky-100 text-sky-700', 'ring' => 'ring-sky-200'],
    ['bg' => 'bg-violet-100 text-violet-700', 'ring' => 'ring-violet-200'],
    ['bg' => 'bg-amber-100 text-amber-700', 'ring' => 'ring-amber-200'],
    ['bg' => 'bg-rose-100 text-rose-700', 'ring' => 'ring-rose-200'],
];
$initials = function (?string $name): string {
    $name = trim((string) $name);
    if ($name === '') return 'NV';
    $parts = preg_split('/\s+/u', $name) ?: [];
    if (count($parts) >= 2) return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1));
    return mb_strtoupper(mb_substr($name, 0, 2));
};

$totalRecords = $logs->count();
$totalSalary = 0;
foreach ($logs as $logItem) {
    $empItem = $logItem->employee ?? ($employee ?? null);
    $amt = $dailySalary($logItem, $empItem);
    if ($amt !== null) $totalSalary += $amt;
}
$uniqueEmployeeCount = $logs->pluck('employee_id')->unique()->filter()->count();
$saleDayCount = ($saleDays ?? collect())->count();
$saleLogCount = $logs->filter(fn ($l) => $isSaleDay($l->work_date))->count();
@endphp

<div class="space-y-5" x-data="{ editOpen: false, editLog: null, addOpen: {{ $hasManualOld ? 'true' : 'false' }}, page: 1, perPage: 10, total: {{ $totalRecords }}, get totalPages() { return Math.max(1, Math.ceil(this.total / this.perPage)); }, rowVisible(i) { return i >= (this.page - 1) * this.perPage && i < this.page * this.perPage; }, get rangeFrom() { return this.total === 0 ? 0 : (this.page - 1) * this.perPage + 1; }, get rangeTo() { return Math.min(this.page * this.perPage, this.total); } }">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-brand-100 dark:bg-brand-900/30 dark:text-brand-400 dark:ring-brand-800/50">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.1.021.204.032.308.032 1.684 0 3.05-1.365 3.05-3.05 0-1.064-.545-2.002-1.372-2.553M9 11V9a3 3 0 116 0v2"/></svg>
            </span>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Chấm công</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }} · {{ $totalRecords }} bản ghi</p>
            </div>
        </div>
        @if($isManager && ($employeesForSelect->isNotEmpty() ?? false))
            <button type="button" @click="addOpen = true; $nextTick(() => document.getElementById('manual-cham-cong')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Thêm chấm công thủ công
            </button>
        @endif
    </div>

    @foreach(['success', 'error', 'info'] as $flash)
        @if(session($flash))
            <div @class([
                'rounded-xl border px-4 py-3 text-sm',
                'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200' => $flash === 'success',
                'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200' => $flash === 'error',
                'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-200' => $flash === 'info',
            ])>{{ session($flash) }}</div>
        @endif
    @endforeach

    @if($isManager)
        {{-- Filter --}}
        @if($employeesForSelect->isNotEmpty() || $employee || $isManager)
        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <form action="{{ route('food.cham-cong') }}" method="get" class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_1fr_auto] md:items-end">
                @if($employeesForSelect->isNotEmpty())
                    <div>
                        <label class="{{ $labelClass }}">Nhân viên</label>
                        <select name="employee_id" class="{{ $inputClass }}">
                            <option value="">Tất cả nhân viên</option>
                            @foreach($employeesForSelect as $e)
                                <option value="{{ $e->id }}" @selected((int) ($selectedEmployeeId ?? 0) === (int) $e->id)>{{ $e->user->name ?? $e->id }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif(!empty($selectedEmployeeId))
                    <input type="hidden" name="employee_id" value="{{ (int) $selectedEmployeeId }}">
                @endif
                <div class="md:hidden">
                    <label class="{{ $labelClass }}">Tháng</label>
                    <input type="month" name="month" value="{{ $month ?? $from->format('Y-m') }}" class="{{ $inputClass }}">
                </div>
                <div class="hidden md:block">
                    <label class="{{ $labelClass }}">Từ ngày</label>
                    <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="{{ $inputClass }}">
                </div>
                <div class="hidden md:block">
                    <label class="{{ $labelClass }}">Đến ngày</label>
                    <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="{{ $inputClass }}">
                </div>
                <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Xem</button>
            </form>
        </div>
        @endif

        {{-- Ngày sale --}}
        <div class="overflow-hidden rounded-2xl border border-orange-200 bg-gradient-to-br from-orange-50/90 to-amber-50/40 shadow-sm dark:border-orange-900/50 dark:from-orange-950/30 dark:to-gray-900">
            <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-start lg:justify-between lg:p-5">
                <div class="min-w-0 flex-1">
                    <p class="text-base font-bold text-orange-950 dark:text-orange-100">Ngày sale</p>
                    <p class="mt-1 text-sm text-orange-800/90 dark:text-orange-200/80">Ngày được đánh dấu sale sẽ tính tiền công từ giờ vào thật. Ngày thường vẫn chỉ tính từ 11:30.</p>
                    <form action="{{ route('food.cham-cong.sale-days.store') }}" method="post" class="mt-4 flex flex-wrap items-end gap-2">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month ?? $from->format('Y-m') }}">
                        @if(!empty($selectedEmployeeId))<input type="hidden" name="employee_id" value="{{ (int) $selectedEmployeeId }}">@endif
                        <div class="min-w-[140px] flex-1 sm:max-w-[160px]">
                            <label class="{{ $labelClass }}">Ngày sale</label>
                            <input type="date" name="work_date" value="{{ old('work_date') }}" required class="{{ $inputClass }}">
                        </div>
                        <div class="min-w-[160px] flex-[2]">
                            <label class="{{ $labelClass }}">Ghi chú</label>
                            <input type="text" name="note" value="{{ old('note') }}" maxlength="255" placeholder="Tùy chọn" class="{{ $inputClass }}">
                        </div>
                        <button type="submit" class="rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-orange-600">Thêm ngày sale</button>
                    </form>
                    @if(($saleDays ?? collect())->isNotEmpty())
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach($saleDays as $saleDay)
                                <li class="inline-flex items-center gap-1 rounded-full border border-orange-200 bg-white py-1 pl-3 pr-1 text-xs font-medium text-orange-900 dark:border-orange-800 dark:bg-orange-950/50 dark:text-orange-100">
                                    <span>{{ $formatWorkDate($saleDay->work_date) }}</span>
                                    @if(filled($saleDay->note))<span class="max-w-[10rem] truncate text-orange-600/80">· {{ $saleDay->note }}</span>@endif
                                    <form action="{{ route('food.cham-cong.sale-days.destroy', $saleDay) }}" method="post" class="inline">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="month" value="{{ $month ?? $from->format('Y-m') }}">
                                        @if(!empty($selectedEmployeeId))<input type="hidden" name="employee_id" value="{{ (int) $selectedEmployeeId }}">@endif
                                        <button type="submit" class="rounded-full p-1 text-orange-600 hover:bg-orange-100" title="Bỏ ngày sale">✕</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="grid shrink-0 grid-cols-3 gap-2 lg:w-[420px]">
                    <div class="rounded-xl border border-orange-100 bg-white/80 p-3 dark:border-orange-900/40 dark:bg-gray-900/60">
                        <p class="text-[10px] font-semibold uppercase text-gray-500">Ngày sale</p>
                        <p class="mt-1 text-lg font-bold text-blue-600">{{ $saleDayCount }} <span class="text-xs font-semibold text-gray-500">ngày</span></p>
                    </div>
                    <div class="rounded-xl border border-orange-100 bg-white/80 p-3 dark:border-orange-900/40 dark:bg-gray-900/60">
                        <p class="text-[10px] font-semibold uppercase text-gray-500">Tổng sale</p>
                        <p class="mt-1 text-lg font-bold text-orange-600">{{ $saleLogCount }} <span class="text-xs font-semibold text-gray-500">ngày</span></p>
                    </div>
                    <div class="rounded-xl border border-orange-100 bg-white/80 p-3 dark:border-orange-900/40 dark:bg-gray-900/60">
                        <p class="text-[10px] font-semibold uppercase text-gray-500">Ảnh hưởng lương</p>
                        <p class="mt-1 text-sm font-bold text-emerald-600">+11:30 h<span class="block text-[10px] font-medium text-gray-500">/nhân viên</span></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Manual form --}}
        @if($employeesForSelect->isNotEmpty())
        <div id="manual-cham-cong" x-show="addOpen" x-cloak x-transition class="overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50/80 to-sky-50/40 shadow-sm dark:border-blue-900/40 dark:from-blue-950/20 dark:to-gray-900">
            <div class="border-b border-blue-100/80 px-5 py-4 dark:border-blue-900/40">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Thêm chấm công thủ công</h3>
                    <button type="button" @click="addOpen = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-white/60"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
            </div>
            <form action="{{ route('food.cham-cong.store-manual') }}" method="post" class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-5">
                @csrf
                <div class="sm:col-span-2">
                    <label class="{{ $labelClass }}">Nhân viên</label>
                    <select name="employee_id" required class="{{ $inputClass }}">
                        <option value="">Chọn nhân viên</option>
                        @foreach($employeesForSelect as $e)
                            <option value="{{ $e->id }}" @selected((int) old('employee_id', (int) ($selectedEmployeeId ?? 0)) === (int) $e->id)>{{ $e->user->name ?? ('NV #'.$e->id) }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="{{ $labelClass }}">Ngày</label><input type="date" name="work_date" value="{{ old('work_date', now()->format('Y-m-d')) }}" required class="{{ $inputClass }}"></div>
                <div><label class="{{ $labelClass }}">Vào ca</label><input type="time" name="check_in_time" value="{{ old('check_in_time') }}" class="{{ $inputClass }}"></div>
                <div><label class="{{ $labelClass }}">Ra ca</label><input type="time" name="check_out_time" value="{{ old('check_out_time') }}" class="{{ $inputClass }}"></div>
                <div><label class="{{ $labelClass }}">Bắt đầu nghỉ</label><input type="time" name="break_start_time" value="{{ old('break_start_time') }}" class="{{ $inputClass }}"></div>
                <div><label class="{{ $labelClass }}">Kết thúc nghỉ</label><input type="time" name="break_end_time" value="{{ old('break_end_time') }}" class="{{ $inputClass }}"></div>
                <div class="sm:col-span-2 lg:col-span-4"><label class="{{ $labelClass }}">Ghi chú</label><input type="text" name="note" value="{{ old('note') }}" maxlength="500" placeholder="Tùy chọn" class="{{ $inputClass }}"></div>
                <div class="sm:col-span-2 lg:col-span-1 lg:self-end">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                        Thêm mới
                    </button>
                </div>
            </form>
        </div>
        @endif

        @if($currentUserIsEmployee ?? false)
        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="mb-3 text-sm font-bold text-gray-900 dark:text-white">Ca hôm nay</p>
            <form action="{{ route('food.cham-cong.store') }}" method="post" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @csrf
                <input type="hidden" name="work_date" value="{{ now()->format('Y-m-d') }}">
                <button type="submit" name="action" value="check_in" class="rounded-xl bg-emerald-600 px-3 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-45" {{ $hasCheckedInToday ?? false ? 'disabled' : '' }}>Vào ca</button>
                <button type="submit" name="action" value="check_out" class="rounded-xl bg-orange-500 px-3 py-2.5 text-sm font-semibold text-white hover:bg-orange-600 disabled:opacity-45" {{ $hasCheckedOutToday ?? false ? 'disabled' : '' }}>Ra ca</button>
                <button type="submit" name="action" value="break_start" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-45 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" {{ ($hasBreakStartToday ?? false) ? 'disabled' : '' }}>Bắt đầu nghỉ</button>
                <button type="submit" name="action" value="break_end" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-45 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" {{ ($hasBreakEndToday ?? false) ? 'disabled' : '' }}>Kết thúc nghỉ</button>
            </form>
        </div>
        @endif

        @if($employee || $isManager)
            @include('pages.food.cham-cong.partials.logs-section', [
                'logs' => $logs,
                'employee' => $employee,
                'isManager' => $isManager,
                'selectedEmployeeId' => $selectedEmployeeId,
            ])

            {{-- Bottom summary --}}
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></span>
                        <div><p class="text-xs text-gray-500">Tổng số bản ghi</p><p class="text-lg font-bold text-gray-900 dark:text-white">{{ $totalRecords }} <span class="text-sm font-semibold text-gray-500">bản ghi</span></p></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></span>
                        <div><p class="text-xs text-gray-500">Tổng nhân viên</p><p class="text-lg font-bold text-gray-900 dark:text-white">{{ $uniqueEmployeeCount }} <span class="text-sm font-semibold text-gray-500">nhân viên</span></p></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-50 text-orange-600"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg></span>
                        <div><p class="text-xs text-gray-500">Ngày sale</p><p class="text-lg font-bold text-gray-900 dark:text-white">{{ $saleDayCount }} <span class="text-sm font-semibold text-gray-500">ngày</span></p></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                        <div><p class="text-xs text-gray-500">Tổng tiền lương</p><p class="text-lg font-bold tabular-nums text-violet-700 dark:text-violet-300">{{ $fmt($totalSalary) }} đ <span class="text-xs font-medium text-gray-500">(tạm tính)</span></p></div>
                    </div>
                </div>
            </div>

            @if($isManager)
            <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" @keydown.escape.window="editOpen = false">
                <div x-show="editOpen" x-transition class="w-full max-w-md rounded-t-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-800 sm:rounded-2xl sm:p-6" @click.stop>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Sửa chấm công</h3>
                        <button type="button" @click="editOpen = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">✕</button>
                    </div>
                    <template x-if="editLog">
                        <form :action="'{{ url('/food/cham-cong') }}/' + editLog.id" method="POST">
                            @csrf @method('PUT')
                            <div class="space-y-3">
                                <div><label class="{{ $labelClass }}">Ngày</label><input type="date" name="work_date" :value="editLog.work_date" required class="{{ $inputClass }}"></div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div><label class="{{ $labelClass }}">Vào ca</label><input type="time" name="check_in_time" :value="editLog.check_in_time" class="{{ $inputClass }}"></div>
                                    <div><label class="{{ $labelClass }}">Ra ca</label><input type="time" name="check_out_time" :value="editLog.check_out_time" class="{{ $inputClass }}"></div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div><label class="{{ $labelClass }}">Bắt đầu nghỉ</label><input type="time" name="break_start_time" :value="editLog.break_start_time" class="{{ $inputClass }}"></div>
                                    <div><label class="{{ $labelClass }}">Kết thúc nghỉ</label><input type="time" name="break_end_time" :value="editLog.break_end_time" class="{{ $inputClass }}"></div>
                                </div>
                                <div><label class="{{ $labelClass }}">Ghi chú</label><input type="text" name="note" :value="editLog.note" maxlength="500" class="{{ $inputClass }}"></div>
                            </div>
                            <div class="mt-5 flex gap-2">
                                <button type="submit" class="flex-1 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700">Lưu</button>
                                <button type="button" @click="editOpen = false" class="rounded-xl border border-gray-300 px-4 py-3 text-sm font-medium dark:border-gray-600">Hủy</button>
                            </div>
                        </form>
                    </template>
                    <template x-if="editLog">
                        <form id="form-delete-cc-edit" :action="'{{ url('/food/cham-cong') }}/' + editLog.id" method="POST" class="mt-3">
                            @csrf @method('DELETE')
                            <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-cc-edit', message: 'Xóa bản ghi chấm công này?' })" class="w-full rounded-xl border border-red-200 px-4 py-2.5 text-sm font-medium text-red-600 hover:bg-red-50">Xóa bản ghi này</button>
                        </form>
                    </template>
                </div>
            </div>
            @endif
        @else
            <p class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/50">Chọn nhân viên bên trên để xem chấm công.</p>
        @endif

    @elseif($employee)
        {{-- Employee self view --}}
        <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <form action="{{ route('food.cham-cong') }}" method="get" class="flex items-end gap-2">
                <div class="flex-1"><label class="{{ $labelClass }}">Tháng</label><input type="month" name="month" value="{{ $month ?? $from->format('Y-m') }}" class="{{ $inputClass }}"></div>
                <button type="submit" class="rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Xem</button>
            </form>
        </div>
        @include('pages.food.cham-cong.partials.logs-section', [
            'logs' => $logs,
            'employee' => $employee,
            'isManager' => false,
            'selectedEmployeeId' => null,
        ])
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"><p class="text-xs text-gray-500">Tổng số bản ghi</p><p class="text-lg font-bold">{{ $totalRecords }} bản ghi</p></div>
            <div class="rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"><p class="text-xs text-gray-500">Ngày sale</p><p class="text-lg font-bold">{{ $saleDayCount }} ngày</p></div>
            <div class="col-span-2 rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900"><p class="text-xs text-gray-500">Tổng tiền lương (tạm tính)</p><p class="text-lg font-bold tabular-nums text-violet-700">{{ $fmt($totalSalary) }} đ</p></div>
        </div>
    @else
        <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-8 text-center text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20">Bạn chưa được thêm vào danh sách nhân viên. Liên hệ quản lý để được cấp quyền.</p>
    @endif
</div>
@endsection
