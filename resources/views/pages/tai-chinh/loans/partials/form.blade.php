@php $inModal = $inModal ?? false; @endphp
<form method="POST" action="{{ route('tai-chinh.loans.store') }}" class="space-y-4">
    @csrf
    <div>
        <label for="name" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Tên hợp đồng</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="255"
            class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800">
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="principal_at_start" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Số tiền gốc (₫)</label>
            <input type="text" id="principal_at_start" name="principal_at_start" value="{{ old('principal_at_start') }}" required inputmode="numeric" data-format-vnd
                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800">
        </div>
        <div>
            <label for="borrower_email" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Email bên vay (có tài khoản)</label>
            <input type="email" id="borrower_email" name="borrower_email" value="{{ old('borrower_email') }}"
                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" placeholder="hoặc để trống">
        </div>
    </div>
    <div>
        <label for="borrower_external_name" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Tên đối tác (không có tài khoản)</label>
        <input type="text" id="borrower_external_name" name="borrower_external_name" value="{{ old('borrower_external_name') }}" maxlength="255"
            class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800" placeholder="Nếu bên vay không dùng hệ thống">
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="interest_rate" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Lãi suất (%)</label>
            <input type="number" id="interest_rate" name="interest_rate" value="{{ old('interest_rate', '12') }}" required min="0" max="100" step="0.01"
                class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800">
        </div>
        <div>
            <label for="interest_unit" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Đơn vị lãi</label>
            <select id="interest_unit" name="interest_unit" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800">
                <option value="yearly" {{ old('interest_unit', 'yearly') === 'yearly' ? 'selected' : '' }}>Năm</option>
                <option value="monthly" {{ old('interest_unit') === 'monthly' ? 'selected' : '' }}>Tháng</option>
                <option value="daily" {{ old('interest_unit') === 'daily' ? 'selected' : '' }}>Ngày</option>
            </select>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="interest_calculation" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Cách tính lãi</label>
            <select id="interest_calculation" name="interest_calculation" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800">
                <option value="simple" {{ old('interest_calculation', 'simple') === 'simple' ? 'selected' : '' }}>Lãi đơn</option>
                <option value="compound" {{ old('interest_calculation') === 'compound' ? 'selected' : '' }}>Lãi kép</option>
                <option value="reducing_balance" {{ old('interest_calculation') === 'reducing_balance' ? 'selected' : '' }}>Dư nợ giảm dần</option>
            </select>
        </div>
        <div>
            <label for="accrual_frequency" class="mb-1.5 block text-theme-sm font-medium text-gray-700 dark:text-gray-300">Tần suất tính lãi</label>
            <select id="accrual_frequency" name="accrual_frequency" class="h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:focus:border-brand-800">
                <option value="daily" {{ old('accrual_frequency', 'daily') === 'daily' ? 'selected' : '' }}>Hàng ngày</option>
                <option value="weekly" {{ old('accrual_frequency') === 'weekly' ? 'selected' : '' }}>Hàng tuần</option>
                <option value="monthly" {{ old('accrual_frequency') === 'monthly' ? 'selected' : '' }}>Hàng tháng</option>
            </select>
        </div>
    </div>
    <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
        <div class="flex items-center gap-3">
            <input type="checkbox" id="payment_schedule_enabled" name="payment_schedule_enabled" value="1" {{ old('payment_schedule_enabled') ? 'checked' : '' }}
                class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800">
            <label for="payment_schedule_enabled" class="text-theme-sm font-medium text-gray-700 dark:text-gray-300">Tự tạo giao dịch chờ thanh toán (liên kết: đến ngày trả tự tạo, có nội dung CK để đối chiếu)</label>
        </div>
        <div class="mt-3 pl-7">
            <label for="payment_day_of_month" class="mb-1 block text-theme-xs text-gray-500 dark:text-gray-400">Ngày trong tháng (1-28, để trống = dùng ngày đáo hạn)</label>
            <input type="number" id="payment_day_of_month" name="payment_day_of_month" value="{{ old('payment_day_of_month') }}" min="1" max="28"
                class="h-10 w-24 rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white" placeholder="vd: 15">
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-form.date-picker
                id="loan-start_date"
                name="start_date"
                label="Ngày bắt đầu"
                :defaultDate="old('start_date', date('Y-m-d'))"
                placeholder="Chọn ngày"
                dateFormat="Y-m-d"
            />
        </div>
        <div>
            <x-form.date-picker
                id="loan-due_date"
                name="due_date"
                label="Ngày đáo hạn (tùy chọn)"
                :defaultDate="old('due_date')"
                placeholder="Chọn ngày"
                dateFormat="Y-m-d"
            />
        </div>
    </div>
    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
        @if($inModal)
        <button type="button" @click="modalLoan = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.05]">Hủy</button>
        @elseif(request('embed'))
        <button type="button" onclick="window.parent.postMessage({type:'no-khoan-vay-done'},'*'); return false;" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.05]">Hủy</button>
        @else
        <a href="{{ route('tai-chinh.loans.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.05]">Hủy</a>
        @endif
        <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-theme-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:focus:ring-brand-800">Tạo hợp đồng</button>
    </div>
</form>
