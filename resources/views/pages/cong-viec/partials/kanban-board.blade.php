@php
    $kanbanStatusUrl = route('cong-viec.tasks.kanban-status', ['id' => '__ID__']);
    $columnsUpdateUrl = route('cong-viec.kanban-columns.update', ['id' => '__ID__']);
    $columnsStoreUrl = route('cong-viec.kanban-columns.store');
@endphp
<div class="kanban-board" x-data="kanbanBoard()" x-init="init()" data-status-url="{{ $kanbanStatusUrl }}" data-csrf="{{ csrf_token() }}" data-columns-update-url="{{ $columnsUpdateUrl }}" data-columns-store-url="{{ $columnsStoreUrl }}">
    <div class="flex items-start gap-4 overflow-x-auto pb-4">
        @foreach($kanbanColumns as $col)
            <div class="kanban-column group/col flex-shrink-0 flex-grow-0 w-[280px] min-w-[280px] max-w-[280px] rounded-xl border border-gray-200 bg-gray-50/50 dark:border-gray-700 dark:bg-gray-800/50 p-3"
                 data-status="{{ $col->slug }}"
                 @drop.prevent="onDrop($event, '{{ $col->slug }}')"
                 @dragover.prevent="dragOver = '{{ $col->slug }}'"
                 @dragleave="dragOver = null"
                 @mouseenter="showAddInColumn = true"
                 @mouseleave="showAddInColumn = false"
                 :class="{ 'ring-2 ring-brand-500': dragOver === '{{ $col->slug }}' }"
                 x-data="{ editing: false, label: {{ json_encode($col->label) }}, showAddInColumn: false }">
                <div class="mb-3 flex items-center gap-1">
                    <template x-if="!editing">
                        <h3 class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-700 dark:text-gray-300" x-text="label" @dblclick="editing = true"></h3>
                    </template>
                    <template x-if="editing">
                        <input type="text" x-model="label" @keydown.enter.prevent="saveColumnLabel({{ $col->id }}, label); editing = false" @blur="saveColumnLabel({{ $col->id }}, label); editing = false"
                            class="w-full rounded border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white" x-ref="editInput"
                            x-init="$watch('editing', v => v && $nextTick(() => $refs.editInput?.focus()))">
                    </template>
                    <button type="button" @click="editing = true" class="shrink-0 rounded p-1 text-gray-400 opacity-0 transition-opacity hover:bg-gray-200 hover:text-gray-600 group-hover/col:opacity-100" title="Sửa tên cột">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                    </button>
                </div>
                <div class="space-y-2">
                    @foreach($kanbanTasks[$col->slug] ?? [] as $task)
                        @include('pages.cong-viec.partials.kanban-card', ['task' => $task, 'statusUrl' => str_replace('__ID__', $task->id, $kanbanStatusUrl)])
                    @endforeach
                    <div x-show="showAddInColumn" x-cloak
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="pt-0.5">
                        <button type="button" @click="$dispatch('open-add-task', { kanban_status: '{{ $col->slug }}' })" class="flex w-full items-center gap-2 rounded-lg py-2 text-left text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-red-500">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                            </span>
                            <span class="text-sm">Thêm công việc</span>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="relative flex shrink-0 items-center">
            <button type="button" @click="showAddColumnModal = true; $nextTick(() => $refs.newColumnInput?.focus())" class="flex items-center justify-center rounded-full p-2 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-600 dark:hover:text-white" title="Thêm cột">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div x-show="showAddColumnModal" x-cloak x-transition
                class="absolute left-0 top-full z-30 mt-1.5 w-56 rounded-lg border border-gray-200 bg-white p-2.5 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                @click.outside="showAddColumnModal = false; newColumnLabel = ''">
                <input type="text" x-model="newColumnLabel" x-ref="newColumnInput" placeholder="Tên cột mới"
                    class="mb-2 w-full rounded border border-gray-300 px-2.5 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    @keydown.enter.prevent="submitNewColumn()">
                <div class="flex gap-1.5">
                    <button type="button" @click="submitNewColumn()" class="flex-1 rounded bg-brand-500 py-1.5 text-sm font-medium text-white hover:bg-brand-600">Tạo</button>
                    <button type="button" @click="showAddColumnModal = false; newColumnLabel = ''" class="rounded border border-gray-300 py-1.5 px-2 text-sm dark:border-gray-600">Hủy</button>
                </div>
            </div>
        </div>
    </div>
    {{-- Modal nhập giờ khi kéo sang Done --}}
    <div x-show="showActualModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="showActualModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-sm w-full p-5" @click.stop>
            <h4 class="font-semibold text-gray-900 dark:text-white mb-2">Nhập giờ thực tế</h4>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Cần ghi lại thời gian thực tế (phút) trước khi chuyển sang Done.</p>
            <input type="number" x-model="pendingActualMinutes" min="0" step="1" placeholder="Phút"
                   class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white px-3 py-2">
            <div class="flex gap-2 mt-4">
                <button type="button" @click="confirmActualDuration()" class="flex-1 rounded-lg bg-brand-500 text-white py-2 text-sm font-medium hover:bg-brand-600">Xác nhận</button>
                <button type="button" @click="cancelActualModal()" class="flex-1 rounded-lg border border-gray-300 dark:border-gray-600 py-2 text-sm text-gray-700 dark:text-gray-300">Hủy</button>
            </div>
        </div>
    </div>
</div>

<script>
function kanbanBoard() {
    return {
        dragOver: null,
        showAddColumnModal: false,
        newColumnLabel: '',
        showActualModal: false,
        pendingTaskId: null,
        pendingNewStatus: null,
        pendingActualMinutes: null,
        init() {
            document.addEventListener('cong-viec-kanban-move', (e) => this.handleMove(e.detail));
        },
        saveColumnLabel(columnId, label) {
            const el = document.querySelector('.kanban-board');
            const url = (el && el.dataset.columnsUpdateUrl) ? el.dataset.columnsUpdateUrl.replace('__ID__', columnId) : '';
            const token = (el && el.dataset.csrf) ? el.dataset.csrf : document.querySelector('meta[name=csrf-token]')?.content;
            if (!url || !label.trim()) return;
            fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ label: label.trim(), _token: token })
            }).then(r => r.json()).then(() => window.location.reload());
        },
        submitNewColumn() {
            const el = document.querySelector('.kanban-board');
            const url = (el && el.dataset.columnsStoreUrl) || '';
            const token = (el && el.dataset.csrf) ? el.dataset.csrf : document.querySelector('meta[name=csrf-token]')?.content;
            const label = this.newColumnLabel.trim();
            if (!label) return;
            this.showAddColumnModal = false;
            this.newColumnLabel = '';
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ label: label, _token: token })
            }).then(r => r.json()).then(() => window.location.reload());
        },
        handleMove({ taskId, newStatus }) {
            const el = document.querySelector('.kanban-board');
            const url = (el && el.dataset.statusUrl) ? el.dataset.statusUrl.replace('__ID__', taskId) : '';
            const token = (el && el.dataset.csrf) ? el.dataset.csrf : document.querySelector('meta[name=csrf-token]')?.content;
            fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ kanban_status: newStatus, _token: token })
            }).then(r => r.json().then(data => ({ ok: r.ok, data, status: r.status }))).then(({ ok, data, status }) => {
                if (status === 422 && data.require_actual_duration) {
                    this.pendingTaskId = taskId;
                    this.pendingNewStatus = newStatus;
                    this.pendingActualMinutes = '';
                    this.showActualModal = true;
                    return;
                }
                if (ok) window.location.reload();
            });
        },
        confirmActualDuration() {
            if (this.pendingTaskId == null || !this.pendingActualMinutes || this.pendingActualMinutes < 0) return;
            const el = document.querySelector('.kanban-board');
            const url = (el && el.dataset.statusUrl) ? el.dataset.statusUrl.replace('__ID__', this.pendingTaskId) : '';
            const token = (el && el.dataset.csrf) ? el.dataset.csrf : document.querySelector('meta[name=csrf-token]')?.content;
            fetch(url, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ kanban_status: this.pendingNewStatus, actual_duration: parseInt(this.pendingActualMinutes, 10), _token: token })
            }).then(r => r.json()).then(() => { this.cancelActualModal(); window.location.reload(); });
        },
        cancelActualModal() {
            this.showActualModal = false;
            this.pendingTaskId = null;
            this.pendingNewStatus = null;
            this.pendingActualMinutes = null;
        },
        onDrop(e, status) {
            this.dragOver = null;
            const taskId = e.dataTransfer.getData('text/plain');
            if (!taskId) return;
            this.handleMove({ taskId, newStatus: status });
        }
    };
}
</script>
