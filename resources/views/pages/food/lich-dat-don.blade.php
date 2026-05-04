@extends('layouts.food')

@section('foodContent')
@php
    $branchNameById = $branchNameById ?? [];
    $scheduleBranchOptions = ($allScheduleBranches ?? collect())->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values();
@endphp
<div
    class="space-y-6"
    x-data="{
        showCreateSchedule: false,
        scheduleChannel: '{{ old('order_channel', 'WEB') === 'ShopeeFood' ? 'ShopeeFood' : 'WEB' }}',
        branchOptions: {{ \Illuminate\Support\Js::from($scheduleBranchOptions) }},
        scheduleRows: [{ food_branch_id: '', order_count: 1 }],
        addScheduleRow() { this.scheduleRows.push({ food_branch_id: '', order_count: 1 }); },
        removeScheduleRow(i) { if (this.scheduleRows.length > 1) this.scheduleRows.splice(i, 1); },
    }"
>
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Lịch đặt đơn</h1>
            <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">Giao mục tiêu đơn theo ngày và chi nhánh; theo dõi người nhận đã xác nhận hay chưa.</p>
        </div>
        <a href="{{ route('food.thong-ke-buff') }}" class="shrink-0 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">← Thống kê seeding</a>
    </div>

    <div class="rounded-xl border border-violet-200 bg-violet-50/90 p-4 dark:border-violet-900/60 dark:bg-violet-950/40">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-violet-950 dark:text-violet-100">Tạo / cập nhật lịch</p>
                <p class="mt-0.5 text-xs text-violet-800/90 dark:text-violet-300/90">Cùng ngày và cùng nhóm người nhận thì hệ thống cập nhật bản ghi hiện có; người nhận cần xác nhận lại.</p>
            </div>
            <button type="button" @click="showCreateSchedule = true" class="shrink-0 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">Tạo lịch</button>
        </div>
        @if(($buffSchedulesAdminMonitor ?? collect())->isNotEmpty())
            <div class="mt-4 border-t border-violet-200/80 pt-3 dark:border-violet-800">
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-violet-800 dark:text-violet-300">Theo dõi xác nhận lịch</p>
                <ul class="max-h-72 space-y-3 overflow-y-auto text-xs text-violet-950 dark:text-violet-100">
                    @foreach($buffSchedulesAdminMonitor as $row)
                        <li class="rounded-lg border px-2 py-2 dark:bg-gray-900/50 @if(!empty($row['has_pending'])) border-amber-400 bg-amber-100/70 dark:border-amber-700 dark:bg-amber-950/40 @else border-violet-100 bg-white/80 dark:border-violet-900 @endif">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <span class="font-semibold">Ngày {{ $row['date_label'] }}</span>
                                    @if(!empty($row['has_pending']))
                                        <span class="ml-1.5 rounded bg-amber-600 px-1.5 py-px text-[10px] font-bold uppercase text-white dark:bg-amber-500">Còn người chưa xác nhận</span>
                                    @else
                                        <span class="ml-1.5 text-[10px] font-medium text-green-700 dark:text-green-400">Đủ xác nhận</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-[10px] text-violet-600 dark:text-violet-400">#{{ $row['id'] }}</span>
                                    <form action="{{ route('food.lich-dat-don.destroy', $row['id']) }}" method="POST" class="inline" onsubmit="return confirm('Xóa lịch ngày {{ e($row['date_label']) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded border border-red-200 bg-white px-2 py-0.5 text-[10px] font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:bg-red-950/40 dark:text-red-400 dark:hover:bg-red-900/30">Xóa</button>
                                    </form>
                                </div>
                            </div>
                            @if(!empty($row['summary']))
                                <p class="mt-1 text-[11px] text-violet-800 dark:text-violet-300">{{ $row['summary'] }}</p>
                            @endif
                            <ul class="mt-2 space-y-1 border-t border-violet-200/60 pt-2 dark:border-violet-800">
                                @foreach($row['assignees'] as $ar)
                                    @php $st = $ar['status'] ?? ''; @endphp
                                    <li class="flex flex-wrap items-baseline justify-between gap-1">
                                        <span class="font-medium">{{ $ar['name'] ?? '—' }}</span>
                                        @if($st === 'done')
                                            <span class="text-green-700 dark:text-green-400">Đã xác nhận · {{ $ar['status_label'] ?? '' }}</span>
                                        @elseif($st === 'future')
                                            <span class="text-gray-600 dark:text-gray-400">{{ $ar['status_label'] ?? '' }}</span>
                                        @else
                                            <span class="font-semibold text-amber-800 dark:text-amber-300">{{ $ar['status_label'] ?? 'Chưa xác nhận' }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    @include('pages.food.partials.food-buff-order-schedule-popup')

    <div
        x-show="showCreateSchedule"
        x-cloak
        class="fixed inset-0 z-[90] flex items-end justify-center bg-black/50 p-4 sm:items-center"
        @keydown.escape.window="showCreateSchedule = false"
    >
        <div
            class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-600 dark:bg-gray-900"
            @click.outside="showCreateSchedule = false"
        >
            <div class="flex items-start justify-between gap-2">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Tạo lịch đặt đơn</h3>
                <button type="button" class="rounded p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" @click="showCreateSchedule = false" aria-label="Đóng">×</button>
            </div>
            <form method="POST" action="{{ route('food.lich-dat-don.store') }}" class="mt-4 space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Từ ngày</label>
                        <input type="date" name="schedule_from_date" value="{{ old('schedule_from_date', now()->format('Y-m-d')) }}" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('schedule_from_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Đến ngày</label>
                        <input type="date" name="schedule_to_date" value="{{ old('schedule_to_date', now()->format('Y-m-d')) }}" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @error('schedule_to_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">Kênh đặt đơn</label>
                    <select name="order_channel" x-model="scheduleChannel" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="WEB">WEB</option>
                        <option value="ShopeeFood">ShopeeFood</option>
                    </select>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Chi nhánh &amp; số đơn</label>
                        <button type="button" class="text-xs font-medium text-violet-600 hover:underline dark:text-violet-400" @click="addScheduleRow()">+ Thêm dòng</button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(row, idx) in scheduleRows" :key="idx">
                            <div class="flex flex-wrap items-end gap-2 rounded-lg border border-gray-100 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800/80">
                                <div class="min-w-[160px] flex-1">
                                    <select
                                        class="w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        x-model="row.food_branch_id"
                                        :name="`targets[${idx}][food_branch_id]`"
                                        required
                                    >
                                        <option value="">Chọn chi nhánh</option>
                                        <template x-for="b in branchOptions" :key="b.id">
                                            <option :value="b.id" x-text="b.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="w-24">
                                    <input type="number" min="1" max="999" :name="`targets[${idx}][order_count]`" x-model.number="row.order_count" required class="w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                </div>
                                <button type="button" class="text-xs text-red-600 hover:underline" @click="removeScheduleRow(idx)" x-show="scheduleRows.length > 1">Xóa</button>
                            </div>
                        </template>
                    </div>
                    @error('targets')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('targets.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">User nhận lịch</label>
                    <select name="assignee_user_ids[]" multiple required size="6" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @foreach(($scheduleAssignableUsers ?? collect()) as $su)
                            <option value="{{ $su->id }}" @selected(collect(old('assignee_user_ids', []))->contains($su->id))>{{ $su->name }} ({{ $su->email }})</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Giữ Cmd/Ctrl để chọn nhiều người.</p>
                    @error('assignee_user_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('assignee_user_ids.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" @click="showCreateSchedule = false">Hủy</button>
                    <button type="submit" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">Lưu lịch</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
