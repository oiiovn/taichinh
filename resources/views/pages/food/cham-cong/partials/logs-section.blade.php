{{-- Requires parent Alpine scope: page, perPage, total, rowVisible, rangeFrom, rangeTo, totalPages, editOpen, editLog --}}
@php
    $showEmployeeCol = $isManager && empty($selectedEmployeeId);
    $colCount = ($showEmployeeCol ? 1 : 0) + 9 + ($isManager ? 2 : 0);
@endphp

{{-- Mobile cards --}}
<div class="space-y-3 lg:hidden">
    @forelse($logs as $log)
        @php
            $empCard = $log->employee ?? $employee;
            $amt = $dailySalary($log, $empCard);
            $li = $lateInfo($log, $empCard);
            $noteText = $displayNote($log, $empCard);
            $name = $empCard?->user?->name ?? '—';
            $theme = $avatarThemes[$loop->index % count($avatarThemes)];
            $isOff = ! $log->check_in_at && ! $log->check_out_at;
        @endphp
        <article x-show="rowVisible({{ $loop->index }})" @class([
            'overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900',
            'bg-slate-50/80 dark:bg-slate-900/30' => $isOff,
        ])>
            <div class="flex items-start gap-3 p-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-2 {{ $theme['bg'] }} {{ $theme['ring'] }}">{{ $initials($name) }}</span>
                <div class="min-w-0 flex-1">
                    @if($showEmployeeCol && $log->employee?->user?->name)
                        <p class="text-xs font-semibold text-brand-600">{{ $log->employee->user->name }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $formatWorkDate($log->work_date) }}</h3>
                        @if($isOff)
                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-700 dark:bg-slate-700 dark:text-slate-200">OFF</span>
                        @elseif($log->work_minutes !== null)
                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 dark:bg-brand-900/40 dark:text-brand-300">{{ $log->work_minutes }} phút</span>
                        @endif
                        @if($isSaleDay($log->work_date))<span class="rounded-md bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-orange-700">Sale</span>@endif
                    </div>

                    @if(! $isOff)
                        <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                            <div><span class="text-gray-500">Vào:</span> <strong>{{ $log->check_in_at?->format('H:i') ?? '—' }}</strong></div>
                            <div><span class="text-gray-500">Ra:</span> <strong>{{ $log->check_out_at?->format('H:i') ?? '—' }}</strong></div>
                            @if($log->work_minutes !== null)
                                <div><span class="text-gray-500">Phút làm:</span> <strong>{{ $log->work_minutes }}</strong></div>
                            @endif
                            @if($amt !== null)
                                <div><span class="text-gray-500">Lương:</span> <strong>{{ $fmt($amt) }} đ</strong></div>
                            @endif
                        </div>
                    @endif

                    @if((! $isOff && ($log->break_start_at || $amt !== null || $li['penalty'] > 0)) || filled($noteText))
                        <div class="mt-2 space-y-1 text-xs">
                            @if(! $isOff && $log->break_start_at)
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-gray-500">Nghỉ</span>
                                    <span class="font-medium tabular-nums">{{ $log->break_start_at->format('H:i') }} – {{ $log->break_end_at?->format('H:i') ?? '—' }}</span>
                                </div>
                            @endif
                            @if(! $isOff && $li['penalty'] > 0)
                                <p class="text-red-600">Phạt trễ −{{ $fmt($li['penalty']) }} đ ({{ $li['minutes'] }} phút)</p>
                            @endif
                            @if(filled($noteText))
                                <p class="rounded-lg bg-gray-50 px-2 py-1.5 text-[11px] leading-relaxed text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $noteText }}</p>
                            @endif
                        </div>
                    @elseif($isOff)
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Ngày nghỉ — không chấm công</p>
                    @endif
                </div>
            </div>
            @if($isManager)
            <div class="flex gap-3 border-t border-gray-100 px-4 py-2 dark:border-gray-800">
                <button type="button" @click="editOpen = true; editLog = { id: {{ $log->id }}, work_date: '{{ $log->work_date->format('Y-m-d') }}', check_in_time: '{{ $log->check_in_at?->format('H:i') ?? '' }}', check_out_time: '{{ $log->check_out_at?->format('H:i') ?? '' }}', break_start_time: '{{ $log->break_start_at?->format('H:i') ?? '' }}', break_end_time: '{{ $log->break_end_at?->format('H:i') ?? '' }}', note: {{ json_encode($log->note ?? '') }} }" class="text-xs font-semibold text-brand-600">Sửa</button>
                <form id="form-delete-cc-m-{{ $log->id }}" action="{{ route('food.cham-cong.destroy', $log) }}" method="POST" class="inline">@csrf @method('DELETE')
                    <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-cc-m-{{ $log->id }}', message: @js('Xóa chấm công ngày '.$log->work_date->format('d/m/Y').'?') })" class="text-xs font-semibold text-red-600">Xóa</button>
                </form>
            </div>
            @endif
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-4 py-10 text-center dark:border-gray-700 dark:bg-gray-900/50">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Chưa có bản ghi chấm công.</p>
        </div>
    @endforelse
</div>

{{-- Desktop table --}}
<div class="hidden overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 lg:block">
    <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        <h3 class="text-base font-bold text-gray-900 dark:text-white">Danh sách chấm công</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1100px] text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/90 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800/60 dark:text-gray-400">
                    @if($showEmployeeCol)<th class="px-5 py-3.5">Nhân viên</th>@endif
                    <th class="px-4 py-3.5">Ngày</th>
                    <th class="px-4 py-3.5">Vào ca</th>
                    <th class="px-4 py-3.5">Ra ca</th>
                    <th class="px-4 py-3.5">Nghỉ (bắt đầu – kết thúc)</th>
                    <th class="px-4 py-3.5 text-center">Số phút làm</th>
                    <th class="px-4 py-3.5 text-right">Lương ngày</th>
                    <th class="px-4 py-3.5 text-center">Phút trễ</th>
                    <th class="px-4 py-3.5 text-right">Phạt đi trễ</th>
                    <th class="px-4 py-3.5">Ghi chú</th>
                    @if($isManager)<th class="px-4 py-3.5">Thao tác</th><th class="w-10 px-2 py-3.5"></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($logs as $log)
                    @include('pages.food.cham-cong.partials.log-table-row', [
                        'log' => $log,
                        'employee' => $employee,
                        'isManager' => $isManager,
                        'selectedEmployeeId' => $selectedEmployeeId,
                    ])
                @empty
                    <tr><td colspan="{{ $colCount }}" class="px-5 py-12 text-center text-gray-500">Chưa có bản ghi chấm công trong khoảng thời gian này.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->isNotEmpty())
    <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-500">
            Hiển thị <span class="font-medium text-gray-700 dark:text-gray-300" x-text="rangeFrom"></span> đến <span class="font-medium text-gray-700 dark:text-gray-300" x-text="rangeTo"></span> trong tổng số <span class="font-medium text-gray-700 dark:text-gray-300">{{ $logs->count() }}</span> bản ghi
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="page = Math.max(1, page - 1)" :disabled="page <= 1" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:text-gray-300">Trước</button>
            <template x-for="p in totalPages" :key="p">
                <button type="button" @click="page = p" :class="page === p ? 'bg-brand-600 text-white border-brand-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300'" class="min-w-[2rem] rounded-lg border px-2 py-1.5 text-sm font-semibold" x-text="p"></button>
            </template>
            <button type="button" @click="page = Math.min(totalPages, page + 1)" :disabled="page >= totalPages" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:text-gray-300">Sau</button>
            <select x-model.number="perPage" @change="page = 1" class="rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                <option value="10">10 / trang</option>
                <option value="20">20 / trang</option>
                <option value="50">50 / trang</option>
            </select>
        </div>
    </div>
    @endif
</div>
