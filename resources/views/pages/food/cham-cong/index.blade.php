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
        $lateMins = $emp->lateMinutesForCheckIn($log->check_in_at);
        $penalty = $emp->latePenaltyForMinutes($lateMins);
    }

    return max(0, $gross - $penalty);
};
$lateInfo = function ($log, $emp) {
    if (! $emp || ! $emp->usesLatePenalty()) {
        return ['minutes' => 0, 'penalty' => 0];
    }
    $mins = $emp->lateMinutesForCheckIn($log->check_in_at);

    return ['minutes' => $mins, 'penalty' => $emp->latePenaltyForMinutes($mins)];
};
$displayNote = function ($log, $emp) use ($lateInfo) {
    $note = trim((string) ($log->note ?? ''));
    $li = $lateInfo($log, $emp);
    if ($emp && $li['minutes'] > 0 && $li['penalty'] > 0) {
        $auto = $emp->formatLatePenaltyNote($li['minutes'], $li['penalty']);
        if ($note === '') {
            return $auto;
        }
        if (! str_contains($note, 'Đi trễ')) {
            return $note.' | '.$auto;
        }
    }

    return $note !== '' ? $note : null;
};
@endphp
<div class="space-y-6" x-data="{ editOpen: false, editLog: null }">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Chấm công</h2>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
    @endif
    @if(session('info'))
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-200">{{ session('info') }}</div>
    @endif

    @if($isManager)
        @if($employeesForSelect->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Xem chấm công:</label>
                <select onchange="window.location.href='{{ route('food.cham-cong') }}?employee_id='+this.value+'&from_date={{ $from->format('Y-m-d') }}&to_date={{ $to->format('Y-m-d') }}'" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="" {{ empty($selectedEmployeeId) ? 'selected' : '' }}>Tất cả</option>
                    @foreach($employeesForSelect as $e)
                        <option value="{{ $e->id }}" {{ (int) ($selectedEmployeeId ?? 0) === (int) $e->id ? 'selected' : '' }}>{{ $e->user->name ?? $e->id }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if($employeesForSelect->isNotEmpty())
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">Thêm chấm công thủ công</p>
                <form action="{{ route('food.cham-cong.store-manual') }}" method="post" class="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    @csrf
                    <div class="xl:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Nhân viên</label>
                        <select name="employee_id" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="">Chọn nhân viên</option>
                            @foreach($employeesForSelect as $e)
                                <option value="{{ $e->id }}" @selected((int) old('employee_id', (int) ($selectedEmployeeId ?? 0)) === (int) $e->id)>{{ $e->user->name ?? ('NV #' . $e->id) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Ngày</label>
                        <input type="date" name="work_date" value="{{ old('work_date', now()->format('Y-m-d')) }}" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Vào ca</label>
                        <input type="time" name="check_in_time" value="{{ old('check_in_time') }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Ra ca</label>
                        <input type="time" name="check_out_time" value="{{ old('check_out_time') }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Bắt đầu nghỉ</label>
                        <input type="time" name="break_start_time" value="{{ old('break_start_time') }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Kết thúc nghỉ</label>
                        <input type="time" name="break_end_time" value="{{ old('break_end_time') }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div class="md:col-span-3 xl:col-span-5">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Ghi chú</label>
                        <input type="text" name="note" value="{{ old('note') }}" maxlength="500" placeholder="Tùy chọn" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    </div>
                    <div class="md:col-span-3 xl:col-span-1 xl:self-end">
                        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Thêm mới</button>
                    </div>
                </form>
            </div>
        @endif

        @if($currentUserIsEmployee ?? false)
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
            <form action="{{ route('food.cham-cong.store') }}" method="post" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="hidden" name="work_date" value="{{ now()->format('Y-m-d') }}">
                <button type="submit" name="action" value="check_in" class="rounded-lg bg-green-600 px-3 py-2 text-sm text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed" {{ $hasCheckedInToday ?? false ? 'disabled' : '' }}>Vào ca</button>
                <button type="submit" name="action" value="check_out" class="rounded-lg bg-orange-600 px-3 py-2 text-sm text-white hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed" {{ $hasCheckedOutToday ?? false ? 'disabled' : '' }}>Ra ca</button>
                <button type="submit" name="action" value="break_start" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed" {{ ($hasBreakStartToday ?? false) ? 'disabled' : '' }}>Bắt đầu nghỉ</button>
                <button type="submit" name="action" value="break_end" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:text-gray-300 disabled:opacity-50 disabled:cursor-not-allowed" {{ ($hasBreakEndToday ?? false) ? 'disabled' : '' }}>Kết thúc nghỉ</button>
            </form>
        </div>
        @endif

        @if($employee || $isManager)
        <div class="flex flex-wrap items-center gap-2 pr-2 sm:pr-0">
            <form action="{{ route('food.cham-cong') }}" method="get" class="flex flex-wrap items-center gap-2 min-w-0">
                @if(!empty($selectedEmployeeId))
                    <input type="hidden" name="employee_id" value="{{ (int) $selectedEmployeeId }}">
                @endif
                <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white min-w-0">
                <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white min-w-0">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700 shrink-0">Xem</button>
            </form>
        </div>
        {{-- Mobile: card từng hàng --}}
        <div class="space-y-3 md:hidden">
            @forelse($logs as $log)
                @php
                    $empCard = $log->employee ?? $employee;
                    $amt = $dailySalary($log, $empCard);
                    $li = $lateInfo($log, $empCard);
                    $noteText = $displayNote($log, $empCard);
                    $isOff = ! $log->check_in_at && ! $log->check_out_at;
                @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-700">
                        <span class="font-medium text-gray-900 dark:text-white">{{ $formatWorkDate($log->work_date) }}</span>
                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                            @if($isOff)
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-700 dark:text-slate-300">OFF</span>
                            @elseif($log->work_minutes !== null)
                                <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">{{ $log->work_minutes }} phút</span>
                            @endif
                        </div>
                    </div>
                    @if(! $isOff || $noteText || ($isManager && empty($selectedEmployeeId) && $log->employee?->user?->name) || $li['penalty'] > 0)
                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                        @if($log->check_in_at)
                            <dt class="text-gray-500 dark:text-gray-400">Vào ca</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $log->check_in_at->format('H:i') }}</dd>
                        @endif
                        @if($log->check_out_at)
                            <dt class="text-gray-500 dark:text-gray-400">Ra ca</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $log->check_out_at->format('H:i') }}</dd>
                        @endif
                        @if($log->break_start_at)
                            <dt class="text-gray-500 dark:text-gray-400">Nghỉ</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $log->break_start_at->format('H:i') }} – {{ $log->break_end_at?->format('H:i') ?? '—' }}</dd>
                        @endif
                        @if($amt !== null)
                            <dt class="text-gray-500 dark:text-gray-400">Lương ngày</dt>
                            <dd class="text-gray-900 dark:text-white font-medium">{{ $fmt($amt) }} đ</dd>
                        @endif
                        @if($li['penalty'] > 0)
                            <dt class="text-gray-500 dark:text-gray-400">Phút trễ</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $li['minutes'] }} phút</dd>
                            <dt class="text-gray-500 dark:text-gray-400">Phạt đi trễ</dt>
                            <dd class="text-red-600 dark:text-red-400 font-medium">{{ $fmt($li['penalty']) }} đ</dd>
                        @endif
                        @if($isManager && empty($selectedEmployeeId) && $log->employee?->user?->name)
                            <dt class="text-gray-500 dark:text-gray-400">Nhân viên</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $log->employee->user->name }}</dd>
                        @endif
                        @if(filled($noteText))
                            <dt class="col-span-2 text-gray-500 dark:text-gray-400">Ghi chú</dt>
                            <dd class="col-span-2 text-gray-900 dark:text-white">{{ $noteText }}</dd>
                        @endif
                    </dl>
                    @endif
                    @if($isManager)
                        <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                            <button type="button" @click="editOpen = true; editLog = { id: {{ $log->id }}, work_date: '{{ $log->work_date->format('Y-m-d') }}', check_in_time: '{{ $log->check_in_at?->format('H:i') ?? '' }}', check_out_time: '{{ $log->check_out_at?->format('H:i') ?? '' }}', break_start_time: '{{ $log->break_start_at?->format('H:i') ?? '' }}', break_end_time: '{{ $log->break_end_at?->format('H:i') ?? '' }}', note: {{ json_encode($log->note ?? '') }} }" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">Sửa</button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">Chưa có bản ghi chấm công trong khoảng thời gian này.</p>
            @endforelse
        </div>

        {{-- Desktop: bảng --}}
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
            {{-- Modal sửa chấm công --}}
            <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="editOpen = false">
                <div x-show="editOpen" x-transition class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800" @click.stop>
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Sửa chấm công</h3>
                    <template x-if="editLog">
                        <form :action="'{{ url('/food/cham-cong') }}/' + editLog.id" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="space-y-3">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày</label>
                                    <input type="date" name="work_date" :value="editLog.work_date" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Vào ca</label>
                                        <input type="time" name="check_in_time" :value="editLog.check_in_time" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Ra ca</label>
                                        <input type="time" name="check_out_time" :value="editLog.check_out_time" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Bắt đầu nghỉ</label>
                                        <input type="time" name="break_start_time" :value="editLog.break_start_time" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Kết thúc nghỉ</label>
                                        <input type="time" name="break_end_time" :value="editLog.break_end_time" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Ghi chú</label>
                                    <input type="text" name="note" :value="editLog.note" maxlength="500" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" placeholder="Tùy chọn">
                                </div>
                            </div>
                            <div class="mt-4 flex gap-2">
                                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Lưu</button>
                                <button type="button" @click="editOpen = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Hủy</button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>
        @endif
        @else
        <p class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">Chọn nhân viên bên trên để xem chấm công.</p>
        @endif
    @elseif($employee)
        <div class="flex flex-wrap items-center gap-2 pr-2 sm:pr-0">
            <form action="{{ route('food.cham-cong') }}" method="get" class="flex flex-wrap items-center gap-2 min-w-0">
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white min-w-0">
                <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white min-w-0">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700 shrink-0">Xem</button>
            </form>
        </div>
        <div class="space-y-3 md:hidden">
            @forelse($logs as $log)
                @php
                    $amt = $dailySalary($log, $employee);
                    $li = $lateInfo($log, $employee);
                    $noteText = $displayNote($log, $employee);
                    $isOff = ! $log->check_in_at && ! $log->check_out_at;
                @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-700">
                        <span class="font-medium text-gray-900 dark:text-white">{{ $formatWorkDate($log->work_date) }}</span>
                        <div class="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                            @if($isOff)
                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-700 dark:text-slate-300">OFF</span>
                            @elseif($log->work_minutes !== null)
                                <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">{{ $log->work_minutes }} phút</span>
                            @endif
                        </div>
                    </div>
                    @if(! $isOff || $noteText || $li['penalty'] > 0)
                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                        @if($log->check_in_at)
                            <dt class="text-gray-500 dark:text-gray-400">Vào ca</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $log->check_in_at->format('H:i') }}</dd>
                        @endif
                        @if($log->check_out_at)
                            <dt class="text-gray-500 dark:text-gray-400">Ra ca</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $log->check_out_at->format('H:i') }}</dd>
                        @endif
                        @if($log->break_start_at)
                            <dt class="text-gray-500 dark:text-gray-400">Nghỉ</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $log->break_start_at->format('H:i') }} – {{ $log->break_end_at?->format('H:i') ?? '—' }}</dd>
                        @endif
                        @if($amt !== null)
                            <dt class="text-gray-500 dark:text-gray-400">Lương ngày</dt>
                            <dd class="text-gray-900 dark:text-white font-medium">{{ $fmt($amt) }} đ</dd>
                        @endif
                        @if($li['penalty'] > 0)
                            <dt class="text-gray-500 dark:text-gray-400">Phút trễ</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $li['minutes'] }} phút</dd>
                            <dt class="text-gray-500 dark:text-gray-400">Phạt đi trễ</dt>
                            <dd class="text-red-600 dark:text-red-400 font-medium">{{ $fmt($li['penalty']) }} đ</dd>
                        @endif
                        @if(filled($noteText))
                            <dt class="col-span-2 text-gray-500 dark:text-gray-400">Ghi chú</dt>
                            <dd class="col-span-2 text-gray-900 dark:text-white">{{ $noteText }}</dd>
                        @endif
                    </dl>
                    @endif
                </div>
            @empty
                <p class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">Chưa có bản ghi chấm công trong khoảng thời gian này.</p>
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
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">Bạn chưa được thêm vào danh sách nhân viên. Liên hệ quản lý để được cấp quyền.</p>
    @endif
</div>
@endsection
