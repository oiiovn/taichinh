{{-- Modal thêm/sửa công việc --}}
<div x-show="showAddTask" x-cloak x-ref="addTaskModalOverlay"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-effect="showAddTask && $nextTick(() => $refs.addTaskModalOverlay?.scrollTo({ top: 0, behavior: 'instant' }))"
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 pt-24 pb-8 px-4"
    @click.self="showAddTask = false; if (window.location.search.includes('edit=')) window.location = '{{ route('cong-viec') }}';">
    <div class="modal-add-task-content relative z-0 mt-0 mb-8 w-full max-w-[600px] shrink-0 overflow-visible rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800" @click.stop>
        <div class="px-4 py-3.5">
            @include('pages.cong-viec.partials.form-them-cong-viec', ['task' => $editTask ?? null])
        </div>
    </div>
</div>
{{-- Modal xác nhận xoá --}}
<div x-show="showDeleteModal" x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[55] flex items-center justify-center bg-black/50 p-4"
    @click.self="closeDeleteModal()">
    <div class="w-full max-w-sm rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800 p-5" @click.stop>
        <div class="flex items-center gap-3 text-red-600 dark:text-red-400 mb-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
            </span>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Xoá công việc</h3>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-300 mb-5">
            Bạn có chắc muốn xoá công việc <span x-text="deleteTaskTitle" class="font-medium text-gray-900 dark:text-white"></span>? Hành động này không thể hoàn tác.
        </p>
        <form :action="deleteFormAction" method="POST" class="flex flex-wrap items-center justify-end gap-2">
            @csrf
            @method('DELETE')
            <button type="button" @click="closeDeleteModal()" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                Huỷ
            </button>
            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700">
                Xoá
            </button>
        </form>
    </div>
</div>
{{-- Modal xác nhận hoàn thành --}}
<div x-show="showConfirmCompleteModal" x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[55] flex items-center justify-center bg-black/50 p-4"
    @click.self="closeConfirmCompleteModal()">
    <div class="w-full max-w-sm rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-600 dark:bg-gray-800 p-5" @click.stop>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Xác nhận hoàn thành</h3>
        <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
            Hệ thống gợi ý xác nhận: P(thật) = <span x-text="confirmP != null ? Math.round(confirmP * 100) + '%' : ''"></span>. Bạn có thực sự đã hoàn thành?
        </p>
        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button" @click="closeConfirmCompleteModal()" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                Huỷ
            </button>
            <button type="button" @click="confirmCompleteSubmit()" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700">
                Xác nhận
            </button>
        </div>
    </div>
</div>
