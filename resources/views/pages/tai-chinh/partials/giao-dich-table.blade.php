@php
    $userCategories = $userCategories ?? collect();
    $canEdit = $canEdit ?? true;
    $txItems = isset($transactionHistory) && method_exists($transactionHistory, 'items') ? $transactionHistory->items() : (is_array($transactionHistory ?? null) && isset($transactionHistory['data']) ? $transactionHistory['data'] : []);
    $hasPending = $canEdit && $userCategories->isNotEmpty() && collect($txItems)->filter(fn ($t) => is_object($t))->contains(fn ($t) => ($t->classification_status ?? null) === 'pending');
    $hasCategories = $userCategories->isNotEmpty();
    $showEditBar = $canEdit && $hasCategories;
    $showStkColumn = count($linkedAccountNumbers ?? []) > 1;
    $showNguoiNap = !empty($householdContext) && !empty($depositorNameMap) && is_array($depositorNameMap);
@endphp
@if(!empty($load_error))
    <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ $load_error_message ?? 'Không tải được danh sách giao dịch.' }}</div>
@endif
<div id="giao-dich-table-wrapper" data-ajax-container>
    <div id="bar-luu-danh-muc" class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50" style="display: none;">
        @if($showEditBar)
            <form method="POST" action="{{ route('tai-chinh.confirm-classification') }}" id="form-luu-danh-muc-tat-ca" class="flex flex-wrap items-center gap-3">
                @csrf
                @if(isset($transactionHistory) && $transactionHistory->currentPage() > 1)
                    <input type="hidden" name="page" value="{{ $transactionHistory->currentPage() }}">
                @endif
                <div class="relative z-20 min-w-[200px]" x-data="{
                    open: false,
                    searchQuery: '',
                    selectedId: {{ json_encode(old('user_category_id')) }},
                    selectedLabel: 'Chọn danh mục',
                    categories: {{ json_encode($userCategories->map(fn($uc) => ['id' => $uc->id, 'name' => $uc->name, 'type' => $uc->type])->values()->all()) }},
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
                    init() {
                        const id = this.selectedId;
                        if (id) {
                            const c = this.categories.find(x => x.id == id);
                            if (c) this.selectedLabel = c.name + ' (' + (c.type === 'income' ? 'Thu' : 'Chi') + ')';
                        }
                    }
                }" @click.outside="open = false; searchQuery = ''">
                    <input type="hidden" name="user_category_id" :value="selectedId || ''" id="input-user-category-id" required>
                    <button type="button" @click="open = !open; if(open) searchQuery = ''"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 flex h-11 w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2.5 pr-10 text-left text-sm text-gray-800 focus:ring-3 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        :class="{ 'ring-2 ring-brand-500': open }">
                        <span x-text="selectedLabel" class="truncate pr-2"></span>
                        <span class="pointer-events-none shrink-0 text-gray-500 dark:text-gray-400" :class="{ 'rotate-180': open }">
                            <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                    </button>
                    <div x-show="open" x-transition
                        class="absolute left-0 right-0 top-full z-50 mt-1 w-full min-w-[220px] rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                        <div class="sticky top-0 border-b border-gray-200 bg-white p-2 dark:border-gray-700 dark:bg-gray-900">
                            <input type="text" x-model="searchQuery" @click.stop
                                placeholder="Tìm danh mục..."
                                class="h-9 w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-1.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500/30 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                        </div>
                        <div class="max-h-[22rem] overflow-y-auto">
                            <template x-for="c in filteredCategories" :key="c.id">
                                <button type="button" @click="selectedId = c.id; selectedLabel = c.name + ' (' + (c.type === 'income' ? 'Thu' : 'Chi') + ')'; open = false; searchQuery = ''"
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
                <span class="text-sm text-gray-600 dark:text-gray-400">Tích chọn các hàng cần gán danh mục bên dưới, rồi bấm Lưu.</span>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:focus:ring-brand-800">
                    Lưu danh mục
                </button>
                <button type="button" id="btn-bo-chon-giao-dich" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-3.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 focus:outline-none focus:ring-3 focus:ring-gray-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700/50 dark:focus:ring-gray-600" title="Bỏ chọn tất cả">
                    Bỏ chọn
                </button>
        @else
            <span class="text-sm text-gray-600 dark:text-gray-400">Thêm danh mục của bạn bằng nút +, sau đó chọn danh mục để phân loại giao dịch.</span>
        @endif
        @if($canEdit)
            <button type="button" @click="$dispatch('open-danh-muc-modal')" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 shadow-theme-xs transition hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.05]" title="Thêm danh mục">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </button>
        @endif
        @if($showEditBar)
            </form>
        @endif
    </div>
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800">
                <tr>
                    @if($showEditBar)
                        <th class="w-10 px-2 py-2.5 text-left">
                            <label class="cursor-pointer">
                                <input type="checkbox" id="chon-tat-ca-giao-dich" class="h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500/30 dark:text-success-400 dark:focus:ring-success-400/30" title="Chọn tất cả giao dịch">
                            </label>
                        </th>
                    @endif
                    <th class="px-4 py-2.5 text-left font-medium text-gray-700 dark:text-white">Thời gian</th>
                    @if($showStkColumn)
                    <th class="px-4 py-2.5 text-left font-medium text-gray-700 dark:text-white">Số TK</th>
                    @endif
                    <th class="px-4 py-2.5 text-right font-medium text-gray-700 dark:text-white">Số tiền</th>
                    <th class="px-4 py-2.5 text-left font-medium text-gray-700 dark:text-white">Mô tả</th>
                    @if($showNguoiNap)
                    <th class="px-4 py-2.5 text-left font-medium text-gray-700 dark:text-white">Người nạp</th>
                    @endif
                    <th class="px-4 py-2.5 text-left font-medium text-gray-700 dark:text-white">Danh mục</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($transactionHistory ?? [] as $t)
                    <tr class="giao-dich-row cursor-pointer text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        @if($showEditBar)
                            <td class="w-10 px-2 py-2.5">
                                <label class="cursor-pointer">
                                    <input type="checkbox" form="form-luu-danh-muc-tat-ca" name="transaction_ids[]" value="{{ $t->id }}" class="cb-giao-dich h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500/30 dark:text-success-400 dark:focus:ring-success-400/30">
                                    <input type="hidden" form="form-luu-danh-muc-tat-ca" name="transaction_descriptions[{{ $t->id }}]" value="{{ e($t->description ?? '') }}">
                                </label>
                            </td>
                        @endif
                        <td class="px-4 py-2.5">{{ $t->transaction_date?->format('d/m/Y H:i') ?? $t->created_at->format('d/m/Y H:i') }}</td>
                        @if($showStkColumn)
                        <td class="px-4 py-2.5 font-mono">{{ $t->account_number ?? ($t->bankAccount?->account_number ?? '-') }}</td>
                        @endif
                        <td class="px-4 py-2.5 text-right font-medium {{ $t->type === 'IN' ? 'text-success-600 dark:text-success-400' : 'text-gray-900 dark:text-white' }}">{{ $t->type === 'IN' ? '+' : '-' }}{{ number_format(abs($t->amount)) }} ₫</td>
                        <td class="px-4 py-2.5 max-w-xs truncate" title="{{ $t->description }}">{{ $t->description ?: '-' }}</td>
                        @if($showNguoiNap)
                        <td class="px-4 py-2.5">
                            @php
                                $desc = (string) ($t->description ?? '');
                                $nguoiNap = '-';
                                if ($desc !== '') {
                                    $descUpper = mb_strtoupper($desc);
                                    foreach ($depositorNameMap as $keyword => $name) {
                                        if (str_contains($descUpper, $keyword)) {
                                            $nguoiNap = $name;
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            {{ $nguoiNap }}
                        </td>
                        @endif
                        <td class="px-4 py-2.5">
                            @if($t->classification_status === 'pending')
                                <span class="text-amber-600 dark:text-amber-400">Chờ phân loại</span>
                            @else
                                {{ $t->userCategory?->name ?? $t->systemCategory?->name ?? '-' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($showEditBar ? 6 : 5) - ($showStkColumn ? 0 : 1) + ($showNguoiNap ? 1 : 0) }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Chưa có giao dịch từ tài khoản đã liên kết.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{-- Chọn tất cả: xử lý bằng event delegation trong lich-su-giao-dich (để hoạt động cả sau khi load bảng qua AJAX/lọc) --}}
    @if(isset($transactionHistory) && $transactionHistory->hasPages())
        <div class="mt-4">
            {{ $transactionHistory->links() }}
        </div>
    @endif
</div>
