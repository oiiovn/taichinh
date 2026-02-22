@props([
    'openVar' => 'open',
    'title' => 'Xác nhận xóa',
    'defaultMessage' => 'Bạn có chắc muốn xóa? Hành động không thể hoàn tác.',
    'confirmText' => 'Xóa',
    'cancelText' => 'Hủy',
    'confirmEvent' => 'confirm-delete',
])

<div x-show="{{ $openVar }}" x-cloak
    class="fixed inset-0 z-[100000] flex items-center justify-center overflow-y-auto p-4"
    @keydown.escape.window="{{ $openVar }} = false">
    <div x-show="{{ $openVar }}"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100001] bg-gray-500/60 dark:bg-gray-900/70"
        @click="{{ $openVar }} = false"></div>
    <div x-show="{{ $openVar }}"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative z-[100002] w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-dark"
        @click.stop>
        <div class="flex items-center justify-between border-b border-gray-200 pb-4 mb-4 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
            <button type="button" @click="{{ $openVar }} = false"
                class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                aria-label="Đóng">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <p class="mb-6 text-theme-sm text-gray-600 dark:text-gray-300">
            {{ $message ?? $defaultMessage }}
        </p>
        <div class="flex justify-end gap-2">
            <button type="button" @click="{{ $openVar }} = false"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                {{ $cancelText }}
            </button>
            <button type="button"
                @click="{{ $openVar }} = false; $dispatch('{{ $confirmEvent }}')"
                class="rounded-lg border border-error-200 bg-error-500 px-4 py-2 text-theme-sm font-medium text-white hover:bg-error-600 dark:border-error-700 dark:bg-error-600 dark:hover:bg-error-700">
                {{ $confirmText }}
            </button>
        </div>
    </div>
</div>
