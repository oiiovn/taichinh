@php
    $storeUrl = route('tai-chinh.payment-schedules.store');
    $editUrlTemplate = route('tai-chinh.payment-schedules.update', ['id' => '__ID__']);
    $editJsonUrlTemplate = route('tai-chinh.payment-schedules.edit', ['id' => '__ID__']);
@endphp
<form method="post" class="space-y-0" id="form-them-lich"
    x-data="{
        storeUrl: @js($storeUrl),
        editUrlTemplate: @js($editUrlTemplate),
        editJsonUrlTemplate: @js($editJsonUrlTemplate),
        isEdit: false,
        editId: null,
        justFilledForEdit: false,
        step: 1,
        name: '',
        expected_amount: '',
        amount_is_variable: false,
        currency: 'VND',
        internal_note: '',
        frequency: 'monthly',
        interval_value: 30,
        next_due_date: '',
        day_of_month: '',
        reminder_days: '7',
        reminder_custom: '',
        grace_window_days: '7',
        keywords: '',
        bank_account_number: '',
        transfer_note_pattern: '',
        amount_tolerance_pct: '5',
        amount_tolerance_custom: '',
        auto_update_amount: false,
        status: 'active',
        reliability_tracking: true,
        overdue_alert: true,
        auto_advance_on_match: true,
        get reminderValue() { return (this.reminder_custom !== '' && this.reminder_custom !== null) ? this.reminder_custom : this.reminder_days; },
        get toleranceValue() { return (this.amount_tolerance_custom !== '' && this.amount_tolerance_custom !== null) ? this.amount_tolerance_custom : this.amount_tolerance_pct; },
        get intervalValueSubmit() { return this.frequency === 'custom_days' ? this.interval_value : (this.frequency === 'monthly' ? 1 : (this.frequency === 'every_2_months' ? 2 : (this.frequency === 'quarterly' ? 3 : 12))); },
        resetForm() { this.step = 1; this.name = ''; this.expected_amount = ''; this.amount_is_variable = false; this.currency = 'VND'; this.internal_note = ''; this.frequency = 'monthly'; this.interval_value = 30; this.next_due_date = ''; this.day_of_month = ''; this.reminder_days = '7'; this.reminder_custom = ''; this.grace_window_days = '7'; this.keywords = ''; this.bank_account_number = ''; this.transfer_note_pattern = ''; this.amount_tolerance_pct = '5'; this.amount_tolerance_custom = ''; this.auto_update_amount = false; this.status = 'active'; this.reliability_tracking = true; this.overdue_alert = true; this.auto_advance_on_match = true; this.isEdit = false; this.editId = null; },
        fillForm(d) { this.name = d.name ?? ''; this.expected_amount = d.expected_amount ?? ''; this.amount_is_variable = !!d.amount_is_variable; this.currency = d.currency ?? 'VND'; this.internal_note = d.internal_note ?? ''; this.frequency = d.frequency ?? 'monthly'; this.interval_value = d.interval_value ?? 30; this.next_due_date = d.next_due_date ?? ''; this.day_of_month = d.day_of_month ?? ''; this.reminder_days = d.reminder_days != null ? String(d.reminder_days) : '7'; this.reminder_custom = ''; this.grace_window_days = d.grace_window_days != null ? String(d.grace_window_days) : '7'; this.keywords = d.keywords ?? ''; this.bank_account_number = d.bank_account_number ?? ''; this.transfer_note_pattern = d.transfer_note_pattern ?? ''; this.amount_tolerance_pct = d.amount_tolerance_pct != null ? String(d.amount_tolerance_pct) : '5'; this.amount_tolerance_custom = ''; this.auto_update_amount = !!d.auto_update_amount; this.status = d.status ?? 'active'; this.reliability_tracking = !!d.reliability_tracking; this.overdue_alert = !!d.overdue_alert; this.auto_advance_on_match = !!d.auto_advance_on_match; this.isEdit = true; this.editId = d.id; this.step = 1; }
    }"
    :action="isEdit ? editUrlTemplate.replace('__ID__', editId) : storeUrl"
    @open-edit-lich-modal.window="const id = $event.detail && $event.detail.id; if (!id) return; fetch(editJsonUrlTemplate.replace('__ID__', id), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(r => r.json()).then(j => { if (j.success && j.data) { fillForm(j.data); justFilledForEdit = true; $dispatch('open-them-lich-modal'); } });"
    @open-them-lich-modal.window="if (!justFilledForEdit) resetForm(); justFilledForEdit = false;">
    @csrf
    <template x-if="isEdit"><input type="hidden" name="_method" value="PUT"></template>
    <div class="p-6">
        {{-- Bước 1: Cơ bản --}}
        <div x-show="step === 1" x-cloak class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Bước 1 – Thông tin cơ bản</h3>
            <div>
                <label for="lich-name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tên khoản thanh toán <span class="text-red-500">*</span></label>
                <input type="text" id="lich-name" name="name" x-model="name" required maxlength="255" placeholder="VD: Wifi FPT, Điện EVN"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </div>
            <div>
                <label for="lich-expected_amount" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Số tiền dự kiến (VNĐ) <span class="text-red-500">*</span></label>
                <input type="number" id="lich-expected_amount" name="expected_amount" x-model="expected_amount" min="0" step="1000" required
                    :readonly="amount_is_variable"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" name="amount_is_variable" value="1" x-model="amount_is_variable" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                    Số tiền biến động
                </label>
            </div>
            <div>
                <label for="lich-currency" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Loại tiền</label>
                <select id="lich-currency" name="currency" x-model="currency" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="VND">VND</option>
                </select>
            </div>
            <div>
                <label for="lich-internal_note" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Ghi chú nội bộ</label>
                <textarea id="lich-internal_note" name="internal_note" x-model="internal_note" rows="2" maxlength="2000" placeholder="Ghi chú chỉ bạn thấy"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Chu kỳ thanh toán <span class="text-red-500">*</span></label>
                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-2 text-sm" :class="frequency === 'monthly' ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-900/20 dark:text-brand-300' : 'border-gray-300 dark:border-gray-600'">
                        <input type="radio" name="frequency" value="monthly" x-model="frequency" class="sr-only"> Hàng tháng
                    </label>
                    <label class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-2 text-sm" :class="frequency === 'every_2_months' ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-900/20 dark:text-brand-300' : 'border-gray-300 dark:border-gray-600'">
                        <input type="radio" name="frequency" value="every_2_months" x-model="frequency" class="sr-only"> 2 tháng
                    </label>
                    <label class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-2 text-sm" :class="frequency === 'quarterly' ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-900/20 dark:text-brand-300' : 'border-gray-300 dark:border-gray-600'">
                        <input type="radio" name="frequency" value="quarterly" x-model="frequency" class="sr-only"> 3 tháng
                    </label>
                    <label class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-2 text-sm" :class="frequency === 'yearly' ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-900/20 dark:text-brand-300' : 'border-gray-300 dark:border-gray-600'">
                        <input type="radio" name="frequency" value="yearly" x-model="frequency" class="sr-only"> Hàng năm
                    </label>
                    <label class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-2 text-sm" :class="frequency === 'custom_days' ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-900/20 dark:text-brand-300' : 'border-gray-300 dark:border-gray-600'">
                        <input type="radio" name="frequency" value="custom_days" x-model="frequency" class="sr-only"> Mỗi
                    </label>
                    <input type="number" x-model="interval_value" min="1" max="366" x-show="frequency === 'custom_days'" class="h-9 w-20 rounded border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"> ngày
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="lich-next_due_date" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày đến hạn <span class="text-red-500">*</span></label>
                    <input type="date" id="lich-next_due_date" name="next_due_date" x-model="next_due_date" required
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
                <div>
                    <label for="lich-day_of_month" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày cố định trong tháng (1–31)</label>
                    <input type="number" id="lich-day_of_month" name="day_of_month" x-model="day_of_month" min="1" max="31" placeholder="VD: 5"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Nhắc trước</label>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex items-center gap-1 text-sm"><input type="radio" value="1" x-model="reminder_days" class="rounded border-gray-300 text-brand-600"> 1 ngày</label>
                    <label class="inline-flex items-center gap-1 text-sm"><input type="radio" value="3" x-model="reminder_days" class="rounded border-gray-300 text-brand-600"> 3 ngày</label>
                    <label class="inline-flex items-center gap-1 text-sm"><input type="radio" value="7" x-model="reminder_days" class="rounded border-gray-300 text-brand-600"> 7 ngày</label>
                    <label class="inline-flex items-center gap-1 text-sm">Tuỳ chỉnh <input type="number" x-model="reminder_custom" min="0" max="90" class="h-8 w-16 rounded border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="0"> ngày</label>
                </div>
            </div>
        </div>

        {{-- Bước 2: Tự động nhận diện --}}
        <div x-show="step === 2" x-cloak class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Bước 2 – Tự động nhận diện giao dịch</h3>
            <div>
                <label for="lich-keywords" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Từ khóa nhận diện</label>
                <input type="text" id="lich-keywords" name="keywords" x-model="keywords" maxlength="500" placeholder="VD: FPT, INTERNET FPT (phân cách bằng dấu phẩy)"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </div>
            <div>
                <label for="lich-bank_account_number" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">STK nhận tiền (optional)</label>
                <input type="text" id="lich-bank_account_number" name="bank_account_number" x-model="bank_account_number" maxlength="50" placeholder="Số tài khoản đích"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </div>
            <div>
                <label for="lich-transfer_note_pattern" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Mẫu nội dung chuyển khoản (optional)</label>
                <input type="text" id="lich-transfer_note_pattern" name="transfer_note_pattern" x-model="transfer_note_pattern" maxlength="255" placeholder="VD: HD 012345"
                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Sai số số tiền cho phép</label>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex items-center gap-1 text-sm"><input type="radio" name="amount_tolerance_pct" value="5" x-model="amount_tolerance_pct" class="rounded border-gray-300 text-brand-600"> ±5%</label>
                    <label class="inline-flex items-center gap-1 text-sm"><input type="radio" name="amount_tolerance_pct" value="10" x-model="amount_tolerance_pct" class="rounded border-gray-300 text-brand-600"> ±10%</label>
                    <label class="inline-flex items-center gap-1 text-sm">Tuỳ chỉnh <input type="number" name="amount_tolerance_pct" x-model="amount_tolerance_custom" min="0" max="100" step="0.5" class="h-8 w-16 rounded border border-gray-300 px-2 text-sm dark:border-gray-600 dark:bg-gray-800"> %</label>
                </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="hidden" name="auto_update_amount" value="0">
                <input type="checkbox" name="auto_update_amount" value="1" x-model="auto_update_amount" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                Tự cập nhật số tiền nếu lệch
            </label>
        </div>

        {{-- Bước 3: Nâng cao --}}
        <div x-show="step === 3" x-cloak class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Bước 3 – Nâng cao</h3>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Grace window để match (± ngày)</label>
                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-2 text-sm" :class="grace_window_days === '3' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-300 dark:border-gray-600'">
                        <input type="radio" name="grace_window_days" value="3" x-model="grace_window_days" class="sr-only"> ±3 ngày
                    </label>
                    <label class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-2 text-sm" :class="grace_window_days === '5' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-300 dark:border-gray-600'">
                        <input type="radio" name="grace_window_days" value="5" x-model="grace_window_days" class="sr-only"> ±5 ngày
                    </label>
                    <label class="inline-flex cursor-pointer items-center rounded-lg border px-3 py-2 text-sm" :class="grace_window_days === '7' ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-300 dark:border-gray-600'">
                        <input type="radio" name="grace_window_days" value="7" x-model="grace_window_days" class="sr-only"> ±7 ngày
                    </label>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Trạng thái</label>
                <select name="status" x-model="status" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="active">Đang dùng (Active)</option>
                    <option value="paused">Tạm dừng (Paused)</option>
                    <option value="ended">Kết thúc (Ended)</option>
                </select>
            </div>
            <div class="space-y-2">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="hidden" name="reliability_tracking" value="0">
                    <input type="checkbox" name="reliability_tracking" value="1" x-model="reliability_tracking" class="rounded border-gray-300 text-brand-600"> Theo dõi độ tin cậy (mặc định bật)
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="hidden" name="overdue_alert" value="0">
                    <input type="checkbox" name="overdue_alert" value="1" x-model="overdue_alert" class="rounded border-gray-300 text-brand-600"> Cảnh báo quá hạn
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="hidden" name="auto_advance_on_match" value="0">
                    <input type="checkbox" name="auto_advance_on_match" value="1" x-model="auto_advance_on_match" class="rounded border-gray-300 text-brand-600"> Tự gia hạn chu kỳ khi match (mặc định bật)
                </label>
            </div>
        </div>

        {{-- Hidden: reminder, tolerance, interval_value --}}
        <input type="hidden" name="reminder_days" :value="reminderValue">
        <input type="hidden" name="amount_tolerance_pct" :value="toleranceValue">
        <input type="hidden" name="interval_value" :value="intervalValueSubmit">

        {{-- Nút bước --}}
        <div class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
            <div>
                <template x-if="step > 1">
                    <button type="button" @click="step--" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Quay lại</button>
                </template>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="open = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Hủy</button>
                <template x-if="step < 3">
                    <button type="button" @click="step++" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600">Tiếp</button>
                </template>
                <template x-if="step === 3">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 dark:bg-brand-500 dark:hover:bg-brand-600" x-text="isEdit ? 'Cập nhật' : 'Tạo lịch'">Tạo lịch</button>
                </template>
            </div>
        </div>
    </div>
</form>
