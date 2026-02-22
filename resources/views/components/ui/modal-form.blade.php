@props([
    'openVar' => 'open',
    'title' => '',
    'maxWidth' => 'max-w-2xl',
])

<div x-show="{{ $openVar }}" x-cloak
    class="fixed inset-0 z-[100000] flex items-center justify-center overflow-y-auto p-4"
    @keydown.escape.window="{{ $openVar }} = false">
    {{-- Backdrop --}}
    <div x-show="{{ $openVar }}"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100001] bg-gray-500/60 dark:bg-gray-900/70"
        @click="{{ $openVar }} = false"></div>
    {{-- Content --}}
    <div x-show="{{ $openVar }}"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative z-[100002] w-full {{ $maxWidth }} rounded-xl border border-gray-200 bg-white p-6 shadow-xl dark:border-gray-700 dark:bg-gray-dark"
        @click.stop>
        {{-- Header --}}
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
        {{-- Errors: dùng slot 'validationErrors' để hiển thị lỗi --}}
        @isset($validationErrors)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                {{ $validationErrors }}
            </div>
        @endisset
        {{-- Body (scrollable): chặn double submit, đóng modal khi submit --}}
        <div class="max-h-[70vh] overflow-y-auto"
            x-data="{ submitted: false }"
            @submit.capture="
                if (submitted) { $event.preventDefault(); return; }
                submitted = true;
                const btn = $event.target.querySelector('button[type=submit]');
                if (btn) { btn.disabled = true; btn.textContent = btn.dataset.loadingText || 'Đang lưu...'; }
                $dispatch('modal-form-submitted');
            ">
            {{ $slot }}
        </div>
    </div>
</div>
