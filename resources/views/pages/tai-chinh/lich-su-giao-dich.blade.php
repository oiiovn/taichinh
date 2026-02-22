<div class="space-y-4" x-data="{ showConfirmDelete: false, formIdToSubmit: null }" @confirm-delete-open.window="showConfirmDelete = true; formIdToSubmit = $event.detail.formId" @confirm-delete.window="if (formIdToSubmit) { const f = document.getElementById(formIdToSubmit); if (f) f.submit(); } formIdToSubmit = null; showConfirmDelete = false">
    {{-- Thông báo success/error hiển thị ở layout cha (tai-chinh.blade.php) để tránh bị bắn hai lần --}}
    <form id="form-giao-dich-filter" method="GET" action="{{ route('tai-chinh') }}">
        <input type="hidden" name="tab" value="giao-dich">
        <div class="flex flex-col gap-4 mb-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">Tổng lịch sử giao dịch</h2>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                        <svg class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M3.04199 9.37381C3.04199 5.87712 5.87735 3.04218 9.37533 3.04218C12.8733 3.04218 15.7087 5.87712 15.7087 9.37381C15.7087 12.8705 12.8733 15.7055 9.37533 15.7055C5.87735 15.7055 3.04199 12.8705 3.04199 9.37381ZM9.37533 1.54218C5.04926 1.54218 1.54199 5.04835 1.54199 9.37381C1.54199 13.6993 5.04926 17.2055 9.37533 17.2055C11.2676 17.2055 13.0032 16.5346 14.3572 15.4178L17.1773 18.2381C17.4702 18.531 17.945 18.5311 18.2379 18.2382C18.5308 17.9453 18.5309 17.4704 18.238 17.1775L15.4182 14.3575C16.5367 13.0035 17.2087 11.2671 17.2087 9.37381C17.2087 5.04835 13.7014 1.54218 9.37533 1.54218Z" fill=""/>
                        </svg>
                    </span>
                    <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="Tìm kiếm Số TK, mô tả..."
                        class="h-[42px] w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pl-[42px] pr-4 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-blue-800 xl:w-[300px]">
                </div>
                @if(count($linkedAccountNumbers ?? []) > 1)
                <select id="stk" name="stk" class="h-[42px] rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] sm:w-[140px]">
                    <option value="">Tất cả Số TK</option>
                    @foreach($linkedAccountNumbers ?? [] as $num)
                        <option value="{{ $num }}" {{ request('stk') === $num ? 'selected' : '' }}>{{ $num }}</option>
                    @endforeach
                </select>
                @endif
                @php
                    $loaiOrder = ['' => 'Tất cả', 'IN' => 'Vào', 'OUT' => 'Ra'];
                    $loaiCurrent = request('loai');
                    $loaiCurrent = in_array($loaiCurrent, ['IN', 'OUT'], true) ? $loaiCurrent : '';
                    $categoryIdCurrent = request('category_id', '');
                    $categoryLabelCurrent = 'Tất cả danh mục';
                    if ($categoryIdCurrent && $userCategories && $userCategories->isNotEmpty()) {
                        $cat = $userCategories->firstWhere('id', (int) $categoryIdCurrent);
                        if ($cat) $categoryLabelCurrent = $cat->name . ' (' . ($cat->type === 'income' ? 'Thu' : 'Chi') . ')';
                    }
                    $hasFilterInitial = request()->filled('q') || request()->filled('stk') || $loaiCurrent !== '' || request()->filled('pending') || $categoryIdCurrent !== '';
                @endphp
                <input type="hidden" name="loai" id="loai" value="{{ $loaiCurrent }}">
                <input type="hidden" name="category_id" id="category_id" value="{{ $categoryIdCurrent }}">
                <div class="relative z-20 min-w-[180px]" x-data="{
                    open: false,
                    searchQuery: '',
                    selectedId: {{ json_encode($categoryIdCurrent) }},
                    selectedLabel: {{ json_encode($categoryLabelCurrent) }},
                    categories: {{ json_encode($userCategories ? $userCategories->map(fn($uc) => ['id' => $uc->id, 'name' => $uc->name, 'type' => $uc->type])->values()->all() : []) }},
                    normalize(s) {
                        if (!s) return '';
                        const map = {'à':'a','á':'a','ả':'a','ã':'a','ạ':'a','ă':'a','ằ':'a','ắ':'a','ẳ':'a','ẵ':'a','ặ':'a','â':'a','ầ':'a','ấ':'a','ẩ':'a','ẫ':'a','ậ':'a','è':'e','é':'e','ẻ':'e','ẽ':'e','ẹ':'e','ê':'e','ề':'e','ế':'e','ể':'e','ễ':'e','ệ':'e','ì':'i','í':'i','ỉ':'i','ĩ':'i','ị':'i','ò':'o','ó':'o','ỏ':'o','õ':'o','ọ':'o','ô':'o','ồ':'o','ố':'o','ổ':'o','ỗ':'o','ộ':'o','ơ':'o','ờ':'o','ớ':'o','ở':'o','ỡ':'o','ợ':'o','ù':'u','ú':'u','ủ':'u','ũ':'u','ụ':'u','ư':'u','ừ':'u','ứ':'u','ử':'u','ữ':'u','ự':'u','ỳ':'y','ý':'y','ỷ':'y','ỹ':'y','ỵ':'y','đ':'d'};
                        return String(s).toLowerCase().replace(/[àáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ]/g, m => map[m] || m);
                    },
                    get filteredCategories() {
                        const q = this.normalize(this.searchQuery).trim();
                        if (!q) return this.categories;
                        return this.categories.filter(c => this.normalize(c.name).includes(q) || this.normalize(c.type === 'income' ? 'Thu' : 'Chi').includes(q));
                    },
                    chooseAll() { this.selectedId = ''; this.selectedLabel = 'Tất cả danh mục'; var h = document.getElementById('category_id'); if (h) h.value = ''; this.open = false; this.searchQuery = ''; if (window.fetchTableGiaoDich) window.fetchTableGiaoDich(); },
                    choose(c) { this.selectedId = c.id; this.selectedLabel = c.name + ' (' + (c.type === 'income' ? 'Thu' : 'Chi') + ')'; var h = document.getElementById('category_id'); if (h) h.value = c.id; this.open = false; this.searchQuery = ''; if (window.fetchTableGiaoDich) window.fetchTableGiaoDich(); }
                }" @giao-dich-clear-filter.window="selectedId = ''; selectedLabel = 'Tất cả danh mục'" @click.outside="open = false; searchQuery = ''">
                    <button type="button" @click="open = !open; if(open) searchQuery = ''"
                        class="flex h-[42px] w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2.5 pr-10 text-left text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white/90 dark:hover:bg-white/[0.03]"
                        :class="{ 'ring-2 ring-brand-500': open }">
                        <span x-text="selectedLabel" class="truncate pr-2"></span>
                        <span class="pointer-events-none shrink-0 text-gray-500 dark:text-gray-400" :class="{ 'rotate-180': open }">
                            <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                    </button>
                    <div x-show="open" x-transition x-cloak
                        class="absolute left-0 right-0 top-full z-50 mt-1 w-full min-w-[220px] rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                        <div class="sticky top-0 border-b border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                            <input type="text" x-model="searchQuery" @click.stop
                                placeholder="Tìm danh mục..."
                                class="h-9 w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                        </div>
                        <div class="max-h-[22rem] overflow-y-auto">
                            <button type="button" @click="chooseAll()"
                                class="flex w-full items-center px-4 py-2.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400">
                                Tất cả danh mục
                            </button>
                            <template x-for="c in filteredCategories" :key="c.id">
                                <button type="button" @click="choose(c)"
                                    class="flex w-full items-center px-4 py-2.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800"
                                    :class="c.type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                                    <span x-text="c.name"></span>
                                    <span class="ml-1" x-text="'(' + (c.type === 'income' ? 'Thu' : 'Chi') + ')'"></span>
                                </button>
                            </template>
                            <p x-show="filteredCategories.length === 0" class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">Không có danh mục phù hợp.</p>
                        </div>
                    </div>
                </div>
                <button type="button" id="btn-loai-cycle" class="inline-flex h-[42px] items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-theme-sm font-medium shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-white/[0.03] {{ $loaiCurrent ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500' }}" aria-label="Lọc loại: click đổi Tất cả → Vào → Ra">{{ $loaiOrder[$loaiCurrent] }}</button>
                <input type="hidden" name="pending" id="pending" value="{{ request('pending') ? '1' : '' }}">
                <button type="button" id="btn-pending" class="inline-flex h-[42px] items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-3 text-theme-sm font-medium shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-white/[0.03] {{ request('pending') ? 'font-semibold text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500' }}" aria-pressed="{{ request('pending') ? 'true' : 'false' }}">Chờ phân loại</button>
                <button type="button" id="btn-xoa-loc" class="inline-flex h-[42px] items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-white/[0.03]" style="{{ $hasFilterInitial ? '' : 'display:none;' }}">Xóa lọc</button>
            </div>
        </div>
    </form>

    {{-- Modal Thêm/Sửa danh mục --}}
    @php
        $editCategoryId = session('edit_category_id');
        $editCategory = $editCategoryId && $userCategories->isNotEmpty() ? $userCategories->firstWhere('id', $editCategoryId) : null;
    @endphp
    <x-ui.modal :isOpen="session('open_modal') === 'danh-muc'" @open-danh-muc-modal.window="open = true" class="w-full max-w-[280px]">
        <div class="relative w-full overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900" x-data="{
            categories: {{ json_encode($userCategories->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'type' => $c->type])->values()) }},
            editId: {{ $editCategory ? $editCategory->id : 'null' }},
            editName: {{ $editCategory ? json_encode($editCategory->name) : "''" }},
            editType: {{ $editCategory ? json_encode($editCategory->type) : "''" }},
            updateUrlTemplate: {{ json_encode(route('tai-chinh.danh-muc.update', ['id' => '__ID__'])) }},
            startEdit(c) { this.editId = c.id; this.editName = c.name; this.editType = c.type; },
            cancelEdit() { this.editId = null; this.editName = ''; this.editType = ''; }
        }" x-init="if (editId && !editName) { const c = categories.find(x => x.id == editId); if (c) { editName = c.name; editType = c.type; } }">
            <div class="pr-8">
                <h4 class="mb-0.5 text-base font-semibold text-gray-800 dark:text-white/90" x-text="editId ? 'Sửa danh mục' : 'Thêm danh mục'"></h4>
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">Tùy chọn khi phân loại giao dịch.</p>
            </div>
            <form x-show="editId === null" method="POST" action="{{ route('tai-chinh.danh-muc.store') }}" class="flex flex-col">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label for="dm-name" class="mb-0.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Tên danh mục</label>
                        <input type="text" id="dm-name" name="name" value="{{ old('name') }}" maxlength="100" required
                            class="h-10 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="dm-type" class="mb-0.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Loại</label>
                        <select id="dm-type" name="type" required class="h-10 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                            <option value="">Chọn loại</option>
                            <option value="expense" {{ old('type') === 'expense' ? 'selected' : '' }}>Chi</option>
                            <option value="income" {{ old('type') === 'income' ? 'selected' : '' }}>Thu</option>
                        </select>
                        @error('type')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 justify-end">
                    <button type="button" @click="open = false" class="inline-flex justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">Hủy</button>
                    <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/10">Lưu</button>
                </div>
            </form>
            <form x-show="editId !== null" x-cloak method="POST" :action="updateUrlTemplate.replace('__ID__', editId)" class="flex flex-col">
                @csrf
                @method('PUT')
                <div class="space-y-3">
                    <div>
                        <label for="dm-edit-name" class="mb-0.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Tên danh mục</label>
                        <input type="text" id="dm-edit-name" name="name" x-model="editName" maxlength="100" required
                            class="h-10 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800">
                    </div>
                    <div>
                        <label for="dm-edit-type" class="mb-0.5 block text-xs font-medium text-gray-700 dark:text-gray-400">Loại</label>
                        <select id="dm-edit-type" name="type" x-model="editType" required class="h-10 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800">
                            <option value="">Chọn loại</option>
                            <option value="expense">Chi</option>
                            <option value="income">Thu</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 justify-end">
                    <button type="button" @click="cancelEdit()" class="inline-flex justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">Hủy</button>
                    <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/10">Cập nhật</button>
                </div>
            </form>
            @if($userCategories->isNotEmpty())
            <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-400">Danh mục của bạn</p>
                <ul class="max-h-48 space-y-1 overflow-y-auto text-sm">
                    @foreach($userCategories as $uc)
                    <li class="flex items-center justify-between gap-2 rounded-lg py-1.5 px-2 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <span class="min-w-0 truncate {{ $uc->type === 'income' ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400' }}">{{ $uc->name }} <span class="text-gray-400">({{ $uc->type === 'income' ? 'Thu' : 'Chi' }})</span></span>
                        <span class="flex shrink-0 gap-0.5">
                            <button type="button" @click="startEdit({{ json_encode(['id' => $uc->id, 'name' => $uc->name, 'type' => $uc->type]) }})" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Sửa"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg></button>
                            <form id="form-delete-category-{{ $uc->id }}" method="POST" action="{{ route('tai-chinh.danh-muc.destroy', $uc->id) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="button" @click="$dispatch('confirm-delete-open', { formId: 'form-delete-category-{{ $uc->id }}' })" class="shrink-0 rounded-full p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-700 dark:hover:bg-gray-600 dark:hover:text-gray-200" title="Xóa"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg></button>
                            </form>
                        </span>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
    </x-ui.modal>

    <x-ui.confirm-delete openVar="showConfirmDelete" title="Xác nhận xóa danh mục" defaultMessage="Xóa danh mục này? Các giao dịch đã gán sẽ chuyển về chưa phân loại." />

    <div id="giao-dich-ajax-message" class="mb-2 hidden rounded-lg border p-3 text-sm" role="alert" aria-live="polite"></div>
    <div id="giao-dich-table-container" data-table-url="{{ route('tai-chinh.giao-dich-table') }}">
        @include('pages.tai-chinh.partials.giao-dich-table')
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    var DEBOUNCE_MS = 280;
    var container = document.getElementById('giao-dich-table-container');
    var form = document.getElementById('form-giao-dich-filter');
    if (!container || !form) return;
    var tableUrl = (container.getAttribute('data-table-url') || '').replace(/\/+$/, '');
    if (!tableUrl) return;
    var debounceTimer = null;
    var STORAGE_KEY = 'tai_chinh_giao_dich_selected_ids';

    function getStoredSelectedIds() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return {};
            var arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr.reduce(function(o, id) { o[id] = true; return o; }, {}) : {};
        } catch (e) { return {}; }
    }

    function setStoredSelectedIds(ids) {
        var arr = Object.keys(ids).filter(function(k) { return ids[k]; }).map(Number);
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(arr)); } catch (e) {}
    }

    function restoreCheckedStateFromStorage() {
        var allCb = container.querySelectorAll('.cb-giao-dich');
        var selectAll = container.querySelector('#chon-tat-ca-giao-dich');
        var stored = getStoredSelectedIds();
        allCb.forEach(function(cb) {
            var id = parseInt(cb.value, 10);
            cb.checked = !!stored[id];
        });
        if (selectAll && allCb.length) {
            selectAll.checked = Array.prototype.every.call(allCb, function(c) { return c.checked; });
        }
        updateBarVisibility();
    }

    function persistCheckedState() {
        var allCb = container.querySelectorAll('.cb-giao-dich');
        var stored = getStoredSelectedIds();
        allCb.forEach(function(cb) {
            var id = parseInt(cb.value, 10);
            if (cb.checked) stored[id] = true; else delete stored[id];
        });
        setStoredSelectedIds(stored);
    }

    function buildParams() {
        var qEl = document.getElementById('q');
        var stkEl = document.getElementById('stk');
        var loaiEl = document.getElementById('loai');
        var pendingEl = document.getElementById('pending');
        var categoryEl = document.getElementById('category_id');
        var q = (qEl && qEl.value) ? qEl.value.trim() : '';
        var stk = (stkEl && stkEl.value) ? stkEl.value : '';
        var loai = (loaiEl && loaiEl.value) ? loaiEl.value : '';
        var params = new URLSearchParams();
        if (q) params.set('q', q);
        if (stk) params.set('stk', stk);
        if (loai) params.set('loai', loai);
        if (pendingEl && pendingEl.value === '1') params.set('pending', '1');
        if (categoryEl && categoryEl.value) params.set('category_id', categoryEl.value);
        return params;
    }

    function syncUrlToState(u) {
        var idx = (u || '').indexOf('?');
        var query = idx >= 0 ? u.substring(idx + 1) : '';
        var params = new URLSearchParams(query);
        params.set('tab', 'giao-dich');
        var newPath = window.location.pathname + '?' + params.toString();
        if (window.location.pathname + (window.location.search || '') !== newPath) {
            history.replaceState(null, '', newPath);
        }
    }

    function fetchTable(url) {
        var u = url || (tableUrl + '?' + buildParams().toString());
        container.classList.add('opacity-70');
        fetch(u, {
            credentials: 'same-origin',
            headers: { 'Accept': 'text/html' }
        })
            .then(function(r) {
                if (!r.ok) throw new Error('Network error');
                return r.text();
            })
            .then(function(html) {
                var wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                var inner = wrap.querySelector('[data-ajax-container]');
                if (inner) {
                    container.innerHTML = inner.innerHTML;
                } else if (wrap.firstElementChild) {
                    container.innerHTML = wrap.firstElementChild.innerHTML;
                }
                container.classList.remove('opacity-70');
                // Không restore checkbox từ storage sau khi lọc, tránh tự tích vào hàng
                syncUrlToState(u);
                if (typeof updateFilterButtonStates === 'function') updateFilterButtonStates();
            })
            .catch(function() { container.classList.remove('opacity-70'); });
    }

    function scheduleFetch() {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchTable, DEBOUNCE_MS);
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (debounceTimer) clearTimeout(debounceTimer);
        fetchTable();
        return false;
    });

    var qEl = document.getElementById('q');
    if (qEl) {
        qEl.addEventListener('input', scheduleFetch);
        qEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); if (debounceTimer) clearTimeout(debounceTimer); fetchTable(); }
        });
    }
    var stkEl = document.getElementById('stk');
    if (stkEl) stkEl.addEventListener('change', function() { if (debounceTimer) clearTimeout(debounceTimer); fetchTable(); });
    var loaiEl = document.getElementById('loai');
    var btnLoaiCycle = document.getElementById('btn-loai-cycle');
    var loaiCycleOrder = ['', 'IN', 'OUT'];
    var loaiLabels = { '': 'Tất cả', 'IN': 'Vào', 'OUT': 'Ra' };
    function updateFilterButtonStates() {
        var loaiVal = loaiEl ? (loaiEl.value || '') : '';
        var pendingVal = pendingEl ? pendingEl.value : '';
        var qEl = document.getElementById('q');
        var q = (qEl && qEl.value) ? qEl.value.trim() : '';
        var stkVal = (stkEl && stkEl.value) ? stkEl.value : '';
        var catEl = document.getElementById('category_id');
        var catVal = (catEl && catEl.value) ? catEl.value : '';
        var hasAnyFilter = !!q || !!stkVal || !!loaiVal || pendingVal === '1' || !!catVal;
        function addTextClasses(el, classStr) {
            if (!el) return;
            (classStr || '').split(/\s+/).forEach(function(c) { if (c) el.classList.add(c); });
        }
        if (btnLoaiCycle) {
            btnLoaiCycle.textContent = loaiLabels[loaiVal] || 'Tất cả';
            btnLoaiCycle.classList.remove('text-gray-400', 'text-gray-700', 'dark:text-gray-500', 'dark:text-gray-200');
            addTextClasses(btnLoaiCycle, loaiVal ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500');
        }
        if (btnPending) {
            btnPending.classList.toggle('font-semibold', pendingVal === '1');
            btnPending.setAttribute('aria-pressed', pendingVal === '1' ? 'true' : 'false');
            btnPending.classList.remove('text-gray-400', 'text-gray-700', 'dark:text-gray-500', 'dark:text-gray-200');
            addTextClasses(btnPending, pendingVal === '1' ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500');
        }
        if (btnXoaLoc) {
            btnXoaLoc.style.display = hasAnyFilter ? '' : 'none';
        }
    }
    var btnXoaLoc = document.getElementById('btn-xoa-loc');
    if (btnLoaiCycle && loaiEl) {
        btnLoaiCycle.addEventListener('click', function() {
            if (debounceTimer) clearTimeout(debounceTimer);
            var idx = loaiCycleOrder.indexOf(loaiEl.value || '');
            idx = (idx + 1) % 3;
            loaiEl.value = loaiCycleOrder[idx];
            updateFilterButtonStates();
            fetchTable();
        });
    }
    var pendingEl = document.getElementById('pending');
    var btnPending = document.getElementById('btn-pending');
    if (btnPending && pendingEl) {
        btnPending.addEventListener('click', function() {
            if (debounceTimer) clearTimeout(debounceTimer);
            var isOn = pendingEl.value === '1';
            pendingEl.value = isOn ? '' : '1';
            updateFilterButtonStates();
            fetchTable();
        });
    }
    if (btnXoaLoc) {
        btnXoaLoc.addEventListener('click', function() {
            if (debounceTimer) clearTimeout(debounceTimer);
            var qEl = document.getElementById('q');
            if (qEl) qEl.value = '';
            if (stkEl) stkEl.selectedIndex = 0;
            if (loaiEl) loaiEl.value = '';
            if (pendingEl) pendingEl.value = '';
            var catEl = document.getElementById('category_id');
            if (catEl) catEl.value = '';
            window.dispatchEvent(new CustomEvent('giao-dich-clear-filter'));
            updateFilterButtonStates();
            fetchTable();
        });
    }

    window.fetchTableGiaoDich = fetchTable;

    container.addEventListener('click', function(e) {
        var a = e.target.closest('a[href*="tai-chinh"]');
        if (!a || !a.href) return;
        e.preventDefault();
        var href = a.getAttribute('href') || '';
        var pageMatch = href.match(/[?&]page=(\d+)/);
        var params = buildParams();
        if (pageMatch) params.set('page', pageMatch[1]);
        var url = tableUrl + '?' + params.toString();
        fetchTable(url);
    });

    function updateBarVisibility() {
        var bar = container.querySelector('#bar-luu-danh-muc');
        if (!bar) return;
        var allCb = container.querySelectorAll('.cb-giao-dich');
        var selectAll = container.querySelector('#chon-tat-ca-giao-dich');
        var anyChecked = (selectAll && selectAll.checked) || Array.prototype.some.call(allCb, function(c) { return c.checked; });
        bar.style.display = anyChecked ? '' : 'none';
    }

    container.addEventListener('change', function(e) {
        if (e.target.id === 'chon-tat-ca-giao-dich') {
            var cbs = container.querySelectorAll('.cb-giao-dich');
            cbs.forEach(function(cb) { cb.checked = e.target.checked; });
        }
        if (e.target.id === 'chon-tat-ca-giao-dich' || e.target.classList.contains('cb-giao-dich')) {
            updateBarVisibility();
            persistCheckedState();
        }
    });

    container.addEventListener('click', function(e) {
        if (e.target.id !== 'btn-bo-chon-giao-dich' && !e.target.closest('#btn-bo-chon-giao-dich')) return;
        var allCb = container.querySelectorAll('.cb-giao-dich');
        var selectAll = container.querySelector('#chon-tat-ca-giao-dich');
        allCb.forEach(function(cb) { cb.checked = false; });
        if (selectAll) selectAll.checked = false;
        setStoredSelectedIds({});
        updateBarVisibility();
    });

    container.addEventListener('click', function(e) {
        var tr = e.target.closest('tr.giao-dich-row');
        if (!tr) return;
        var cb = tr.querySelector('.cb-giao-dich');
        if (!cb) return;
        if (e.target.closest('label') || e.target === cb) return;
        e.preventDefault();
        cb.checked = !cb.checked;
        updateBarVisibility();
        persistCheckedState();
    });

    function showAjaxMessage(text, isError) {
        var el = document.getElementById('giao-dich-ajax-message');
        if (!el) return;
        el.textContent = text;
        el.classList.remove('hidden', 'border-green-200', 'bg-green-50', 'text-green-800', 'dark:border-green-800', 'dark:bg-green-900/20', 'dark:text-green-400', 'border-red-200', 'bg-red-50', 'text-red-700', 'dark:border-red-800', 'dark:bg-red-900/20', 'dark:text-red-400');
        if (isError) {
            el.classList.add('border-red-200', 'bg-red-50', 'text-red-700', 'dark:border-red-800', 'dark:bg-red-900/20', 'dark:text-red-400');
        } else {
            el.classList.add('border-green-200', 'bg-green-50', 'text-green-800', 'dark:border-green-800', 'dark:bg-green-900/20', 'dark:text-green-400');
        }
        el.classList.remove('hidden');
        setTimeout(function() { el.classList.add('hidden'); }, 5000);
    }

    container.addEventListener('submit', function(e) {
        if (e.target.id !== 'form-luu-danh-muc-tat-ca') return;
        e.preventDefault();
        var formEl = e.target;
        var action = formEl.getAttribute('action') || '';
        var tokenEl = formEl.querySelector('input[name="_token"]');
        var token = tokenEl ? tokenEl.value : '';
        var msgEl = document.getElementById('giao-dich-ajax-message');
        if (msgEl) msgEl.classList.add('hidden');
        var fd = new FormData(formEl);
        fetch(action, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data }; }); })
            .then(function(res) {
                if (res.ok && res.data && res.data.success) {
                    showAjaxMessage(res.data.message || 'Đã lưu danh mục.', false);
                    var allCb = container.querySelectorAll('.cb-giao-dich');
                    var selectAll = container.querySelector('#chon-tat-ca-giao-dich');
                    allCb.forEach(function(cb) { cb.checked = false; });
                    if (selectAll) selectAll.checked = false;
                    setStoredSelectedIds({});
                    updateBarVisibility();
                    fetchTable();
                } else {
                    var errMsg = (res.data && (res.data.message || (res.data.errors && Object.values(res.data.errors).flat().length))) ? (res.data.message || Object.values(res.data.errors).flat().join(' ')) : 'Không lưu được danh mục.';
                    showAjaxMessage(errMsg, true);
                }
            })
            .catch(function() {
                showAjaxMessage('Lỗi kết nối. Vui lòng thử lại.', true);
            });
        return false;
    });

    restoreCheckedStateFromStorage();
    updateFilterButtonStates();
});
    </script>
</div>
