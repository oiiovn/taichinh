@extends('layouts.food')

@section('foodContent')
<div class="space-y-6" x-data="{ congNoOpen: false, congNoReportId: null }">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Báo cáo bán hàng</h2>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    {{-- Form dán mẫu --}}
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Dán mẫu báo cáo (copy từ sheet, dòng đầu là header)</p>
        <form action="{{ route('food.bao-cao-ban-hang.store') }}" method="POST">
            @csrf
            <textarea name="data" placeholder="Dán nội dung có các cột: Nhóm hàng, Mã hàng, Tên hàng, ..., Mã hóa đơn, Thời gian, SL, ..." class="mb-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" rows="6"></textarea>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                Tải báo cáo lên
            </button>
        </form>
    </div>

    {{-- Bảng danh sách báo cáo --}}
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full min-w-[640px] text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Mã báo cáo</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày báo cáo</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Tổng đơn</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Quyết toán</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Ngày tải lên</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Công nợ (user)</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Trạng thái thanh toán</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $r)
                    <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $r->report_code }}</td>
                        <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $r->report_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $r->total_orders }}</td>
                        <td class="px-4 py-2 text-gray-900 dark:text-white">{{ \App\Helpers\BaoCaoHelper::formatGiaVonNguyen((float) $r->total_cost + (float) $r->total_tien_cong) }} đ</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $r->uploaded_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2 text-gray-900 dark:text-white">
                            @if($r->debts->isEmpty())
                                <span class="text-gray-400">—</span>
                            @else
                                {{ $r->debts->map(fn ($d) => $d->debtor?->name ?? '—')->filter()->join(', ') }}
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @if($r->debts->isEmpty())
                                <span class="text-gray-400">—</span>
                            @else
                                @foreach($r->debts as $d)
                                    <span class="{{ $d->payment ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                                        {{ $d->debtor?->name }}: {{ $d->payment ? 'Đã thanh toán' : 'Chưa thanh toán' }}
                                    </span>@if(!$loop->last)<br>@endif
                                @endforeach
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <a href="{{ route('food.bao-cao-ban-hang.show', $r) }}" class="text-brand-600 hover:underline dark:text-brand-400">Chi tiết</a>
                            <span class="mx-1 text-gray-300 dark:text-gray-600">|</span>
                            <button type="button" @click="congNoReportId = {{ $r->id }}; congNoOpen = true" class="text-amber-600 hover:underline dark:text-amber-400">Xử lý công nợ</button>
                            <span class="mx-1 text-gray-300 dark:text-gray-600">|</span>
                            <form action="{{ route('food.bao-cao-ban-hang.destroy', $r) }}" method="POST" class="inline" onsubmit="return confirm('Xóa báo cáo {{ $r->report_code }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline dark:text-red-400">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Chưa có báo cáo. Dán mẫu và nhấn "Tải báo cáo lên".</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal Xử lý công nợ --}}
    <div x-show="congNoOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="congNoOpen = false">
        <div x-show="congNoOpen" x-transition class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800" @click.stop>
            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Tạo công nợ</h3>
            <form :action="`{{ url('/food/bao-cao-ban-hang') }}/${congNoReportId}/cong-no`" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Chọn user</label>
                    <select name="debtor_user_id" required class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">-- Chọn user --</option>
                        @foreach($users ?? [] as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-4">
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="only_tien_cong" value="1" class="rounded border-gray-300">
                        Chỉ trả tiền công
                    </label>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Tạo công nợ cho user đó</button>
                    <button type="button" @click="congNoOpen = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Hủy</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
