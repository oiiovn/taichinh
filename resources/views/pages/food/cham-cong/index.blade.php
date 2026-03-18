@extends('layouts.food')

@section('foodContent')
@php
$fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
$dailySalary = function ($log, $emp) {
    if (!$emp) return null;
    $r = (float) $emp->salary_rate;
    $t = $emp->salary_type ?? 'hour';
    if ($t === 'hour') {
        $mins = $log->work_minutes ?? null;
        return $mins !== null ? ($mins / 60) * $r : null;
    }
    if ($t === 'day') return $log->check_in_at && $log->check_out_at ? $r : null;
    if ($t === 'month') return $log->check_in_at && $log->check_out_at ? $r / 30 : null;
    return null;
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
                    <option value="">— Chọn nhân viên —</option>
                    @foreach($employeesForSelect as $e)
                        <option value="{{ $e->id }}" {{ $employee && $e->id == $employee->id ? 'selected' : '' }}>{{ $e->user->name ?? $e->id }}</option>
                    @endforeach
                </select>
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

        @if($employee)
        <div class="flex flex-wrap items-center gap-2 pr-2 sm:pr-0">
            <form action="{{ route('food.cham-cong') }}" method="get" class="flex flex-wrap items-center gap-2 min-w-0">
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                <input type="date" name="from_date" value="{{ $from->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white min-w-0">
                <input type="date" name="to_date" value="{{ $to->format('Y-m-d') }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white min-w-0">
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700 shrink-0">Xem</button>
            </form>
        </div>
        {{-- Mobile: card từng hàng --}}
        <div class="space-y-3 md:hidden">
            @forelse($logs as $log)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-700">
                        <span class="font-medium text-gray-900 dark:text-white">{{ $log->work_date->format('d/m/Y') }}</span>
                        @if($log->work_minutes !== null)
                            <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">{{ $log->work_minutes }} phút</span>
                        @endif
                    </div>
                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Vào ca</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $log->check_in_at?->format('H:i') ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Ra ca</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $log->check_out_at?->format('H:i') ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Nghỉ</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $log->break_start_at ? $log->break_start_at->format('H:i') . ' – ' . ($log->break_end_at?->format('H:i') ?? '—') : '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Lương ngày</dt>
                        <dd class="text-gray-900 dark:text-white font-medium">{{ ($amt = $dailySalary($log, $employee)) !== null ? $fmt($amt) . ' đ' : '—' }}</dd>
                    </dl>
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
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Vào ca</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ra ca</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Nghỉ (bắt đầu – kết thúc)</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Số phút làm</th>
                        <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Lương ngày</th>
                        @if($isManager)
                            <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Thao tác</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php $dayAmt = $dailySalary($log, $employee); @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-700/50">
                            <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $log->work_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->check_in_at?->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->check_out_at?->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->break_start_at ? $log->break_start_at->format('H:i') . ' – ' . ($log->break_end_at?->format('H:i') ?? '—') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->work_minutes !== null ? $log->work_minutes . ' phút' : '—' }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-white font-medium">{{ $dayAmt !== null ? $fmt($dayAmt) . ' đ' : '—' }}</td>
                            @if($isManager)
                                <td class="px-4 py-2">
                                    <button type="button" @click="editOpen = true; editLog = { id: {{ $log->id }}, work_date: '{{ $log->work_date->format('Y-m-d') }}', check_in_time: '{{ $log->check_in_at?->format('H:i') ?? '' }}', check_out_time: '{{ $log->check_out_at?->format('H:i') ?? '' }}', break_start_time: '{{ $log->break_start_at?->format('H:i') ?? '' }}', break_end_time: '{{ $log->break_end_at?->format('H:i') ?? '' }}', note: {{ json_encode($log->note ?? '') }} }" class="text-brand-600 hover:underline dark:text-brand-400 text-sm">Sửa</button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isManager ? 7 : 6 }}" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có bản ghi chấm công trong khoảng thời gian này.</td>
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
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800/50">
                    <div class="flex items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-700">
                        <span class="font-medium text-gray-900 dark:text-white">{{ $log->work_date->format('d/m/Y') }}</span>
                        @if($log->work_minutes !== null)
                            <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">{{ $log->work_minutes }} phút</span>
                        @endif
                    </div>
                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Vào ca</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $log->check_in_at?->format('H:i') ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Ra ca</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $log->check_out_at?->format('H:i') ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Nghỉ</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $log->break_start_at ? $log->break_start_at->format('H:i') . ' – ' . ($log->break_end_at?->format('H:i') ?? '—') : '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Lương ngày</dt>
                        <dd class="text-gray-900 dark:text-white font-medium">{{ ($amt = $dailySalary($log, $employee)) !== null ? $fmt($amt) . ' đ' : '—' }}</dd>
                    </dl>
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
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php $dayAmt = $dailySalary($log, $employee); @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-700/50">
                            <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $log->work_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->check_in_at?->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->check_out_at?->format('H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->break_start_at ? $log->break_start_at->format('H:i') . ' – ' . ($log->break_end_at?->format('H:i') ?? '—') : '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log->work_minutes !== null ? $log->work_minutes . ' phút' : '—' }}</td>
                            <td class="px-4 py-2 text-gray-900 dark:text-white font-medium">{{ $dayAmt !== null ? $fmt($dayAmt) . ' đ' : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Chưa có bản ghi chấm công trong khoảng thời gian này.</td>
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
