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
    if (! $emp) {
        return null;
    }
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
    if ($gross === null) {
        return null;
    }
    $penalty = 0;
    if ($emp->usesLatePenalty()) {
        $lateMins = $emp->lateMinutesForCheckIn($log->check_in_at, $log->work_date);
        $penalty = $emp->latePenaltyForMinutes($lateMins);
    }

    return max(0, $gross - $penalty);
};
$lateInfo = function ($log, $emp) {
    if (! $emp || ! $emp->usesLatePenalty()) {
        return ['minutes' => 0, 'penalty' => 0];
    }
    $mins = $emp->lateMinutesForCheckIn($log->check_in_at, $log->work_date);

    return ['minutes' => $mins, 'penalty' => $emp->latePenaltyForMinutes($mins)];
};
$displayNote = function ($log, $emp) use ($lateInfo) {
    $note = trim((string) ($log->note ?? ''));
    if ($emp) {
        $note = trim($emp->stripLatePenaltyNote($note));
    }
    $li = $lateInfo($log, $emp);
    if ($emp && $li['minutes'] > 0 && $li['penalty'] > 0) {
        $auto = $emp->formatLatePenaltyNote($li['minutes'], $li['penalty']);
        if ($note === '') {
            return $auto;
        }

        return $note.' | '.$auto;
    }

    return $note !== '' ? $note : null;
};
$inputClass = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100 dark:border-gray-600 dark:bg-gray-900 dark:text-white dark:focus:ring-brand-900/40';
$labelClass = 'mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
$hasManualOld = old('employee_id') || old('check_in_time') || old('check_out_time') || old('note');
@endphp
<div class="space-y-3 md:space-y-6" x-data="{ editOpen: false, editLog: null, addOpen: {{ $hasManualOld ? 'true' : 'false' }} }">
    {{-- Header (desktop); mobile dùng sticky app bar của layout Food --}}
    <div class="hidden items-start justify-between gap-3 md:flex">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold tracking-tight text-gray-950 dark:text-white">Chấm công</h2>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }} · {{ $logs->count() }} bản ghi</p>
        </div>
    </div>
    <div class="flex items-center justify-between gap-3 md:hidden">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }} · {{ $logs->count() }} bản ghi</p>
        @if($isManager && $employeesForSelect->isNotEmpty())
            <button type="button"
                @click="addOpen = !addOpen"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-brand-600 px-3.5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-brand-700 active:scale-[0.98]">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Thêm
            </button>
        @endif
    </div>
    @if($isManager && $employeesForSelect->isNotEmpty())
        <div class="hidden md:flex md:justify-end">
            {{-- desktop add is always visible in form panel --}}
        </div>
    @endif

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif
    @if(session('info'))
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-200">{{ session('info') }}</div>
    @endif

    @if($isManager)
        {{-- Filter card --}}
        @if($employeesForSelect->isNotEmpty() || $employee || $isManager)
        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <form action="{{ route('food.cham-cong') }}" method="get" class="space-y-2.5 md:space-y-0 md:flex md:flex-wrap md:items-end md:gap-3">
                @if($employeesForSelect->isNotEmpty())
                    <div class="min-w-0 flex-1 md:max-w-xs">
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
                <div class="grid grid-cols-2 gap-2 md:flex md:items-end md:gap-3">
                    <div class="min-w-0">
                        <label class="{{ $labelClass }}">Từ ngày</label>
                        <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="{{ $inputClass }}">
                    </div>
                    <div class="min-w-0">
                        <label class="{{ $labelClass }}">Đến ngày</label>
                        <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="{{ $inputClass }}">
                    </div>
                </div>
                <button type="submit" class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 md:w-auto md:py-2">Xem</button>
            </form>
        </div>
        @endif

        {{-- Manual add form: collapsible on mobile, always open on md+ --}}
        @if($employeesForSelect->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <button type="button"
                    class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left md:hidden"
                    @click="addOpen = !addOpen">
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Thêm chấm công thủ công</p>
                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400" x-text="addOpen ? 'Nhấn để thu gọn' : 'Nhấn để mở form'"></p>
                    </div>
                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="addOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="hidden border-b border-gray-100 px-3 py-2.5 md:block dark:border-gray-800">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Thêm chấm công thủ công</p>
                </div>
                <div class="hidden border-t border-gray-100 px-3 pb-3 pt-2.5 dark:border-gray-800 md:block md:border-t-0"
                    :class="addOpen && '!block'">
                    <form action="{{ route('food.cham-cong.store-manual') }}" method="post" class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-6">
                        @csrf
                        <div class="sm:col-span-2 xl:col-span-2">
                            <label class="{{ $labelClass }}">Nhân viên</label>
                            <select name="employee_id" required class="{{ $inputClass }}">
                                <option value="">Chọn nhân viên</option>
                                @foreach($employeesForSelect as $e)
                                    <option value="{{ $e->id }}" @selected((int) old('employee_id', (int) ($selectedEmployeeId ?? 0)) === (int) $e->id)>{{ $e->user->name ?? ('NV #' . $e->id) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $labelClass }}">Ngày</label>
                            <input type="date" name="work_date" value="{{ old('work_date', now()->format('Y-m-d')) }}" required class="{{ $inputClass }}">
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:contents">
                            <div>
                                <label class="{{ $labelClass }}">Vào ca</label>
                                <input type="time" name="check_in_time" value="{{ old('check_in_time') }}" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Ra ca</label>
                                <input type="time" name="check_out_time" value="{{ old('check_out_time') }}" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Bắt đầu nghỉ</label>
                                <input type="time" name="break_start_time" value="{{ old('break_start_time') }}" class="{{ $inputClass }}">
                            </div>
                            <div>
                                <label class="{{ $labelClass }}">Kết thúc nghỉ</label>
                                <input type="time" name="break_end_time" value="{{ old('break_end_time') }}" class="{{ $inputClass }}">
                            </div>
                        </div>
                        <div class="sm:col-span-2 xl:col-span-5">
                            <label class="{{ $labelClass }}">Ghi chú</label>
                            <input type="text" name="note" value="{{ old('note') }}" maxlength="500" placeholder="Tùy chọn" class="{{ $inputClass }}">
                        </div>
                        <div class="sm:col-span-2 xl:col-span-1 xl:self-end">
                            <button type="submit" class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Thêm mới</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if($currentUserIsEmployee ?? false)
        <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-white to-gray-50 p-3 shadow-sm dark:border-gray-700 dark:from-gray-900 dark:to-gray-900">
            <p class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Ca hôm nay</p>
            <form action="{{ route('food.cham-cong.store') }}" method="post" class="grid grid-cols-2 gap-2">
                @csrf
                <input type="hidden" name="work_date" value="{{ now()->format('Y-m-d') }}">
                <button type="submit" name="action" value="check_in" class="rounded-lg bg-emerald-600 px-2.5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-45" {{ $hasCheckedInToday ?? false ? 'disabled' : '' }}>Vào ca</button>
                <button type="submit" name="action" value="check_out" class="rounded-lg bg-orange-500 px-2.5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600 disabled:cursor-not-allowed disabled:opacity-45" {{ $hasCheckedOutToday ?? false ? 'disabled' : '' }}>Ra ca</button>
                <button type="submit" name="action" value="break_start" class="rounded-lg border border-gray-200 bg-white px-2.5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-45 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" {{ ($hasBreakStartToday ?? false) ? 'disabled' : '' }}>Bắt đầu nghỉ</button>
                <button type="submit" name="action" value="break_end" class="rounded-lg border border-gray-200 bg-white px-2.5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-45 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" {{ ($hasBreakEndToday ?? false) ? 'disabled' : '' }}>Kết thúc nghỉ</button>
            </form>
        </div>
        @endif

        @if($employee || $isManager)
        {{-- Mobile cards --}}
        <div class="space-y-2 md:hidden">
            @forelse($logs as $log)
                @php
                    $empCard = $log->employee ?? $employee;
                    $amt = $dailySalary($log, $empCard);
                    $li = $lateInfo($log, $empCard);
                    $noteText = $displayNote($log, $empCard);
                    $isOff = ! $log->check_in_at && ! $log->check_out_at;
                @endphp
                <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-2 px-3 pt-2.5">
                        <div class="min-w-0">
                            @if($isManager && empty($selectedEmployeeId) && $log->employee?->user?->name)
                                <p class="truncate text-[11px] font-medium text-brand-600 dark:text-brand-400">{{ $log->employee->user->name }}</p>
                            @endif
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $formatWorkDate($log->work_date) }}</h3>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1">
                            @if($isOff)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">OFF</span>
                            @elseif($log->work_minutes !== null)
                                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">{{ $log->work_minutes }} phút</span>
                            @endif
                        </div>
                    </div>

                    @if(! $isOff)
                        <div class="mt-2 grid grid-cols-2 gap-1.5 px-3">
                            <div class="rounded-lg bg-emerald-50/80 px-2.5 py-1.5 dark:bg-emerald-900/20">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-700/80 dark:text-emerald-300/80">Vào ca</p>
                                <p class="text-sm font-semibold tabular-nums text-emerald-800 dark:text-emerald-200">{{ $log->check_in_at?->format('H:i') ?? '—' }}</p>
                            </div>
                            <div class="rounded-lg bg-orange-50/80 px-2.5 py-1.5 dark:bg-orange-900/20">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-orange-700/80 dark:text-orange-300/80">Ra ca</p>
                                <p class="text-sm font-semibold tabular-nums text-orange-800 dark:text-orange-200">{{ $log->check_out_at?->format('H:i') ?? '—' }}</p>
                            </div>
                        </div>
                    @endif

                    @if((! $isOff && ($log->break_start_at || $amt !== null || $li['penalty'] > 0)) || filled($noteText))
                        <div class="mt-2 space-y-1 border-t border-gray-100 px-3 py-2 text-xs dark:border-gray-800">
                            @if($log->break_start_at)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-500 dark:text-gray-400">Nghỉ</span>
                                    <span class="font-medium tabular-nums text-gray-900 dark:text-white">{{ $log->break_start_at->format('H:i') }} – {{ $log->break_end_at?->format('H:i') ?? '—' }}</span>
                                </div>
                            @endif
                            @if($amt !== null)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-500 dark:text-gray-400">Lương ngày</span>
                                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $fmt($amt) }} đ</span>
                                </div>
                            @endif
                            @if($li['penalty'] > 0)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-500 dark:text-gray-400">Đi trễ {{ $li['minutes'] }} phút</span>
                                    <span class="font-semibold tabular-nums text-red-600 dark:text-red-400">−{{ $fmt($li['penalty']) }} đ</span>
                                </div>
                            @endif
                            @if(filled($noteText))
                                <p class="rounded-lg bg-gray-50 px-2 py-1.5 text-[11px] leading-relaxed text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $noteText }}</p>
                            @endif
                        </div>
                    @elseif($isOff && ! filled($noteText))
                        <p class="px-3 pb-2.5 pt-1.5 text-xs text-gray-500 dark:text-gray-400">Ngày nghỉ / không có ca</p>
                    @endif

                    @if($isManager)
                        <div class="border-t border-gray-100 px-3 py-2 dark:border-gray-800">
                            <button type="button"
                                @click="editOpen = true; editLog = { id: {{ $log->id }}, work_date: '{{ $log->work_date->format('Y-m-d') }}', check_in_time: '{{ $log->check_in_at?->format('H:i') ?? '' }}', check_out_time: '{{ $log->check_out_at?->format('H:i') ?? '' }}', break_start_time: '{{ $log->break_start_at?->format('H:i') ?? '' }}', break_end_time: '{{ $log->break_end_at?->format('H:i') ?? '' }}', note: {{ json_encode($log->note ?? '') }} }"
                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-brand-600 transition hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-900/20">
                                Sửa ca
                            </button>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-8 text-center dark:border-gray-700 dark:bg-gray-900/50">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Chưa có bản ghi</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Thử đổi khoảng ngày hoặc chọn nhân viên khác.</p>
                </div>
            @endforelse
        </div>

        {{-- Desktop table --}}
        <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                    <tr>
                        @if($isManager && empty($selectedEmployeeId))
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nhân viên</th>
                        @endif
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Vào ca</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ra ca</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nghỉ (bắt đầu – kết thúc)</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số phút làm</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Lương ngày</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Phút trễ</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Phạt đi trễ</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ghi chú</th>
                        @if($isManager)
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Thao tác</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $empRow = $log->employee ?? $employee;
                            $dayAmt = $dailySalary($log, $empRow);
                            $li = $lateInfo($log, $empRow);
                        @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-700/50">
                            @if($isManager && empty($selectedEmployeeId))
                                <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $log->employee?->user?->name ?? '—' }}</td>
                            @endif
                            <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $formatWorkDate($log->work_date) }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->check_in_at?->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->check_out_at?->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->break_start_at ? $log->break_start_at->format('H:i') . ' – ' . ($log->break_end_at?->format('H:i') ?? '—') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->work_minutes !== null ? $log->work_minutes . ' phút' : '—' }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-white font-medium">{{ $dayAmt !== null ? $fmt($dayAmt) . ' đ' : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ ($empRow && $empRow->usesLatePenalty() && $li['minutes'] > 0) ? $li['minutes'] . ' phút' : '—' }}</td>
                            <td class="px-4 py-2 {{ $li['penalty'] > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300' }}">{{ ($empRow && $empRow->usesLatePenalty() && $li['penalty'] > 0) ? $fmt($li['penalty']) . ' đ' : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300 max-w-[220px] break-words">{{ ($dn = $displayNote($log, $empRow)) ? $dn : '—' }}</td>
                            @if($isManager)
                                <td class="px-4 py-2">
                                    <button type="button" @click="editOpen = true; editLog = { id: {{ $log->id }}, work_date: '{{ $log->work_date->format('Y-m-d') }}', check_in_time: '{{ $log->check_in_at?->format('H:i') ?? '' }}', check_out_time: '{{ $log->check_out_at?->format('H:i') ?? '' }}', break_start_time: '{{ $log->break_start_at?->format('H:i') ?? '' }}', break_end_time: '{{ $log->break_end_at?->format('H:i') ?? '' }}', note: {{ json_encode($log->note ?? '') }} }" class="text-brand-600 hover:underline dark:text-brand-400 text-sm">Sửa</button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($isManager ? 10 : 9) + (($isManager && empty($selectedEmployeeId)) ? 1 : 0) }}" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có bản ghi chấm công trong khoảng thời gian này.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($isManager)
            <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4" @keydown.escape.window="editOpen = false">
                <div x-show="editOpen" x-transition class="w-full max-w-md rounded-t-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-800 sm:rounded-2xl sm:p-6" @click.stop>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Sửa chấm công</h3>
                        <button type="button" @click="editOpen = false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">✕</button>
                    </div>
                    <template x-if="editLog">
                        <form :action="'{{ url('/food/cham-cong') }}/' + editLog.id" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="space-y-3">
                                <div>
                                    <label class="{{ $labelClass }}">Ngày</label>
                                    <input type="date" name="work_date" :value="editLog.work_date" required class="{{ $inputClass }}">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="{{ $labelClass }}">Vào ca</label>
                                        <input type="time" name="check_in_time" :value="editLog.check_in_time" class="{{ $inputClass }}">
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">Ra ca</label>
                                        <input type="time" name="check_out_time" :value="editLog.check_out_time" class="{{ $inputClass }}">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="{{ $labelClass }}">Bắt đầu nghỉ</label>
                                        <input type="time" name="break_start_time" :value="editLog.break_start_time" class="{{ $inputClass }}">
                                    </div>
                                    <div>
                                        <label class="{{ $labelClass }}">Kết thúc nghỉ</label>
                                        <input type="time" name="break_end_time" :value="editLog.break_end_time" class="{{ $inputClass }}">
                                    </div>
                                </div>
                                <div>
                                    <label class="{{ $labelClass }}">Ghi chú</label>
                                    <input type="text" name="note" :value="editLog.note" maxlength="500" class="{{ $inputClass }}" placeholder="Tùy chọn">
                                </div>
                            </div>
                            <div class="mt-5 flex gap-2">
                                <button type="submit" class="flex-1 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700">Lưu</button>
                                <button type="button" @click="editOpen = false" class="rounded-xl border border-gray-300 px-4 py-3 text-sm font-medium dark:border-gray-600 dark:text-gray-300">Hủy</button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>
        @endif
        @else
        <p class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">Chọn nhân viên bên trên để xem chấm công.</p>
        @endif
    @elseif($employee)
        <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <form action="{{ route('food.cham-cong') }}" method="get" class="space-y-2.5 md:flex md:flex-wrap md:items-end md:gap-3 md:space-y-0">
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <div class="grid grid-cols-2 gap-2 md:flex md:gap-3">
                    <div class="min-w-0">
                        <label class="{{ $labelClass }}">Từ ngày</label>
                        <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="{{ $inputClass }}">
                    </div>
                    <div class="min-w-0">
                        <label class="{{ $labelClass }}">Đến ngày</label>
                        <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="{{ $inputClass }}">
                    </div>
                </div>
                <button type="submit" class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700 md:w-auto">Xem</button>
            </form>
        </div>
        <div class="space-y-2 md:hidden">
            @forelse($logs as $log)
                @php
                    $amt = $dailySalary($log, $employee);
                    $li = $lateInfo($log, $employee);
                    $noteText = $displayNote($log, $employee);
                    $isOff = ! $log->check_in_at && ! $log->check_out_at;
                @endphp
                <article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-2 px-3 pt-2.5">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $formatWorkDate($log->work_date) }}</h3>
                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1">
                            @if($isOff)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">OFF</span>
                            @elseif($log->work_minutes !== null)
                                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">{{ $log->work_minutes }} phút</span>
                            @endif
                        </div>
                    </div>
                    @if(! $isOff)
                        <div class="mt-2 grid grid-cols-2 gap-1.5 px-3">
                            <div class="rounded-lg bg-emerald-50/80 px-2.5 py-1.5 dark:bg-emerald-900/20">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-700/80 dark:text-emerald-300/80">Vào ca</p>
                                <p class="text-sm font-semibold tabular-nums text-emerald-800 dark:text-emerald-200">{{ $log->check_in_at?->format('H:i') ?? '—' }}</p>
                            </div>
                            <div class="rounded-lg bg-orange-50/80 px-2.5 py-1.5 dark:bg-orange-900/20">
                                <p class="text-[10px] font-medium uppercase tracking-wide text-orange-700/80 dark:text-orange-300/80">Ra ca</p>
                                <p class="text-sm font-semibold tabular-nums text-orange-800 dark:text-orange-200">{{ $log->check_out_at?->format('H:i') ?? '—' }}</p>
                            </div>
                        </div>
                    @endif
                    @if((! $isOff && ($log->break_start_at || $amt !== null || $li['penalty'] > 0)) || filled($noteText))
                        <div class="mt-2 space-y-1 border-t border-gray-100 px-3 py-2 text-xs dark:border-gray-800">
                            @if($log->break_start_at)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-500 dark:text-gray-400">Nghỉ</span>
                                    <span class="font-medium tabular-nums text-gray-900 dark:text-white">{{ $log->break_start_at->format('H:i') }} – {{ $log->break_end_at?->format('H:i') ?? '—' }}</span>
                                </div>
                            @endif
                            @if($amt !== null)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-500 dark:text-gray-400">Lương ngày</span>
                                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $fmt($amt) }} đ</span>
                                </div>
                            @endif
                            @if($li['penalty'] > 0)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-500 dark:text-gray-400">Đi trễ {{ $li['minutes'] }} phút</span>
                                    <span class="font-semibold tabular-nums text-red-600 dark:text-red-400">−{{ $fmt($li['penalty']) }} đ</span>
                                </div>
                            @endif
                            @if(filled($noteText))
                                <p class="rounded-lg bg-gray-50 px-2 py-1.5 text-[11px] leading-relaxed text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $noteText }}</p>
                            @endif
                        </div>
                    @elseif($isOff && ! filled($noteText))
                        <p class="px-3 pb-2.5 pt-1.5 text-xs text-gray-500 dark:text-gray-400">Ngày nghỉ / không có ca</p>
                    @endif
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-3 py-8 text-center dark:border-gray-700 dark:bg-gray-900/50">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Chưa có bản ghi</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Thử đổi khoảng ngày xem lại.</p>
                </div>
            @endforelse
        </div>
        <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Vào ca</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ra ca</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nghỉ (bắt đầu – kết thúc)</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số phút làm</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Lương ngày</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Phút trễ</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Phạt đi trễ</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $dayAmt = $dailySalary($log, $employee);
                            $li = $lateInfo($log, $employee);
                        @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-700/50">
                            <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $formatWorkDate($log->work_date) }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->check_in_at?->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->check_out_at?->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->break_start_at ? $log->break_start_at->format('H:i') . ' – ' . ($log->break_end_at?->format('H:i') ?? '—') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->work_minutes !== null ? $log->work_minutes . ' phút' : '—' }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-white font-medium">{{ $dayAmt !== null ? $fmt($dayAmt) . ' đ' : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ ($employee->usesLatePenalty() && $li['minutes'] > 0) ? $li['minutes'] . ' phút' : '—' }}</td>
                            <td class="px-4 py-2 {{ $li['penalty'] > 0 ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-700 dark:text-gray-300' }}">{{ ($employee->usesLatePenalty() && $li['penalty'] > 0) ? $fmt($li['penalty']) . ' đ' : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300 max-w-[220px] break-words">{{ ($dn = $displayNote($log, $employee)) ? $dn : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có bản ghi chấm công trong khoảng thời gian này.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-8 text-center text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">Bạn chưa được thêm vào danh sách nhân viên. Liên hệ quản lý để được cấp quyền.</p>
    @endif
</div>
@endsection
