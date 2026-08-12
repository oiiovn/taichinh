@php
    $log = $log ?? null;
    $empRow = $log->employee ?? ($employee ?? null);
    $dayAmt = $dailySalary($log, $empRow);
    $li = $lateInfo($log, $empRow);
    $noteText = $displayNote($log, $empRow);
    $name = $empRow?->user?->name ?? '—';
    $theme = $avatarThemes[$loop->index % count($avatarThemes)];
    $showEmployee = ($isManager ?? false) && empty($selectedEmployeeId);
@endphp
<tr x-show="rowVisible({{ $loop->index }})" x-cloak class="transition hover:bg-gray-50/70 dark:hover:bg-gray-800/30" data-row-index="{{ $loop->index }}">
    @if($showEmployee)
        <td class="px-5 py-3.5">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-2 {{ $theme['bg'] }} {{ $theme['ring'] }}">{{ $initials($name) }}</span>
                <div>
                    <p class="font-bold text-gray-900 dark:text-white">{{ $name }}</p>
                    <p class="text-xs text-gray-500">Nhân viên</p>
                </div>
            </div>
        </td>
    @endif
    <td class="px-4 py-3.5 text-gray-800 dark:text-gray-200">
        <div class="flex flex-wrap items-center gap-2">
            <span>{{ $formatWorkDate($log->work_date) }}</span>
            @if($isSaleDay($log->work_date))
                <span class="rounded-md bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">Sale</span>
            @endif
        </div>
    </td>
    <td class="px-4 py-3.5 tabular-nums text-gray-700 dark:text-gray-300">{{ $log->check_in_at?->format('H:i') ?? '—' }}</td>
    <td class="px-4 py-3.5 tabular-nums text-gray-700 dark:text-gray-300">{{ $log->check_out_at?->format('H:i') ?? '—' }}</td>
    <td class="px-4 py-3.5 tabular-nums text-gray-700 dark:text-gray-300">{{ $log->break_start_at ? $log->break_start_at->format('H:i').' – '.($log->break_end_at?->format('H:i') ?? '—') : '—' }}</td>
    <td class="px-4 py-3.5 text-center tabular-nums text-gray-700 dark:text-gray-300">{{ $log->work_minutes !== null ? $log->work_minutes : '—' }}</td>
    <td class="px-4 py-3.5 text-right text-base font-bold tabular-nums text-gray-900 dark:text-white">{{ $dayAmt !== null ? $fmt($dayAmt).' đ' : '—' }}</td>
    <td class="px-4 py-3.5 text-center tabular-nums text-gray-700 dark:text-gray-300">{{ ($empRow && $empRow->usesLatePenalty() && $li['minutes'] > 0) ? $li['minutes'] : '—' }}</td>
    <td class="px-4 py-3.5 text-right tabular-nums {{ $li['penalty'] > 0 ? 'font-semibold text-red-600 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">{{ ($empRow && $empRow->usesLatePenalty() && $li['penalty'] > 0) ? $fmt($li['penalty']).' đ' : '—' }}</td>
    <td class="max-w-[180px] px-4 py-3.5 text-gray-600 dark:text-gray-400">{{ $noteText ?? '—' }}</td>
    @if($isManager ?? false)
        <td class="px-4 py-3.5">
            <div class="flex items-center gap-2">
                <button type="button"
                    @click="editOpen = true; editLog = { id: {{ $log->id }}, work_date: '{{ $log->work_date->format('Y-m-d') }}', check_in_time: '{{ $log->check_in_at?->format('H:i') ?? '' }}', check_out_time: '{{ $log->check_out_at?->format('H:i') ?? '' }}', break_start_time: '{{ $log->break_start_at?->format('H:i') ?? '' }}', break_end_time: '{{ $log->break_end_at?->format('H:i') ?? '' }}', note: {{ json_encode($log->note ?? '') }} }"
                    class="text-sm font-semibold text-brand-600 hover:underline dark:text-brand-400">Sửa</button>
                <form id="form-delete-cc-d-{{ $log->id }}" action="{{ route('food.cham-cong.destroy', $log) }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="button"
                        @click="$dispatch('confirm-delete-open', { formId: 'form-delete-cc-d-{{ $log->id }}', message: @js('Xóa chấm công ngày '.$log->work_date->format('d/m/Y').($log->employee?->user?->name ? ' của '.$log->employee->user->name : '').'?') })"
                        class="text-sm font-semibold text-red-600 hover:underline dark:text-red-400">Xóa</button>
                </form>
            </div>
        </td>
        <td class="px-2 py-3.5 text-center text-gray-300">
            <svg class="mx-auto h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </td>
    @endif
</tr>
