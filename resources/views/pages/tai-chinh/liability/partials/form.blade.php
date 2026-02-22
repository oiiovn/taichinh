@php $inModal = $inModal ?? false; @endphp
<form method="POST" action="{{ route('tai-chinh.liability.store') }}" class="space-y-4">
    @csrf
    <div>
        <span class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Loại</span>
        <div class="flex flex-wrap gap-4">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 dark:border-gray-600 dark:bg-gray-800">
                <input type="radio" name="direction" value="payable" {{ old('direction', 'payable') === 'payable' ? 'checked' : '' }} class="text-brand-500 focus:ring-brand-500">
                <span class="text-sm text-gray-800 dark:text-white">Nợ (tôi đi vay)</span>
            </label>
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 dark:border-gray-600 dark:bg-gray-800">
                <input type="radio" name="direction" value="receivable" {{ old('direction') === 'receivable' ? 'checked' : '' }} class="text-brand-500 focus:ring-brand-500">
                <span class="text-sm text-gray-800 dark:text-white">Khoản cho vay (tôi cho người khác vay)</span>
            </label>
        </div>
        @error('direction')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="liability-name" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tên khoản</label>
            <input type="text" id="liability-name" name="name" value="{{ old('name') }}" required maxlength="255"
                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            @error('name')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="liability-principal" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Số tiền gốc (₫)</label>
            <input type="text" id="liability-principal" name="principal" value="{{ old('principal') }}" required inputmode="numeric" data-format-vnd
                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            @error('principal')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="liability-interest-rate" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Lãi suất (%)</label>
            <input type="number" id="liability-interest-rate" name="interest_rate" value="{{ old('interest_rate', '12') }}" required min="0" max="100" step="0.01"
                class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            @error('interest_rate')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="liability-interest-unit" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Đơn vị lãi</label>
            <select id="liability-interest-unit" name="interest_unit" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="yearly" {{ old('interest_unit', 'yearly') === 'yearly' ? 'selected' : '' }}>Năm</option>
                <option value="monthly" {{ old('interest_unit') === 'monthly' ? 'selected' : '' }}>Tháng</option>
                <option value="daily" {{ old('interest_unit') === 'daily' ? 'selected' : '' }}>Ngày</option>
            </select>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="liability-calculation" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Cách tính lãi</label>
            <select id="liability-calculation" name="interest_calculation" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="simple" {{ old('interest_calculation', 'simple') === 'simple' ? 'selected' : '' }}>Lãi đơn</option>
                <option value="compound" {{ old('interest_calculation') === 'compound' ? 'selected' : '' }}>Lãi kép</option>
            </select>
        </div>
        <div>
            <label for="liability-accrual" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Tần suất tính lãi</label>
            <select id="liability-accrual" name="accrual_frequency" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="daily" {{ old('accrual_frequency', 'daily') === 'daily' ? 'selected' : '' }}>Hàng ngày</option>
                <option value="weekly" {{ old('accrual_frequency') === 'weekly' ? 'selected' : '' }}>Hàng tuần</option>
                <option value="monthly" {{ old('accrual_frequency') === 'monthly' ? 'selected' : '' }}>Hàng tháng</option>
            </select>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-form.date-picker
                id="liability-start"
                name="start_date"
                label="Ngày bắt đầu"
                :defaultDate="old('start_date', date('Y-m-d'))"
                placeholder="Chọn ngày"
                dateFormat="Y-m-d"
            />
            @error('start_date')<p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <x-form.date-picker
                id="liability-due"
                name="due_date"
                label="Ngày đáo hạn (tùy chọn)"
                :defaultDate="old('due_date')"
                placeholder="Chọn ngày"
                dateFormat="Y-m-d"
            />
        </div>
    </div>
    <div class="flex items-center gap-2">
        <input type="hidden" name="auto_accrue" value="0">
        <input type="checkbox" id="liability-auto-accrue" name="auto_accrue" value="1" {{ old('auto_accrue', true) ? 'checked' : '' }}
            class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-800">
        <label for="liability-auto-accrue" class="text-sm text-gray-700 dark:text-gray-300">Tự động tính lãi hàng ngày</label>
    </div>
    <div class="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
        @if($inModal)
        <button type="button" @click="modalLiability = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Hủy</button>
        @elseif(request('embed'))
        <button type="button" onclick="window.parent.postMessage({type:'no-khoan-vay-done'},'*'); return false;" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Hủy</button>
        @else
        <a href="{{ route('tai-chinh', ['tab' => 'no-khoan-vay']) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">Hủy</a>
        @endif
        <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Lưu</button>
    </div>
</form>
