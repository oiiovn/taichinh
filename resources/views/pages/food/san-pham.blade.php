@extends('layouts.food')

@section('foodContent')
@php
    $productsJson = $products->map(fn ($p) => [
        'id' => $p->id,
        'ma_hang' => $p->ma_hang,
        'ten_hang' => $p->ten_hang ?? '',
        'gia_von' => (float) $p->gia_von,
        'is_combo' => (bool) $p->is_combo,
    ])->values()->toJson();
@endphp
<div class="space-y-6" x-data="sanPhamPage({{ $productsJson }})" x-cloak>
    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Sản phẩm</h2>

    {{-- Dán mẫu từ sheet --}}
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Dán mẫu (copy từ sheet, dòng đầu là header)</p>
        <textarea x-model="pasteData" placeholder="Dán nội dung từ Excel/Sheet (có cột Mã hàng, Tên hàng hóa, Giá vốn)..." class="mb-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" rows="4"></textarea>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="doPaste()" :disabled="pasting" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                <span x-show="!pasting">Dán & lưu</span>
                <span x-show="pasting">Đang xử lý...</span>
            </button>
            <span x-show="pasteMessage" x-text="pasteMessage" :class="pasteOk ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="text-sm"></span>
        </div>
    </div>

    {{-- Toolbar: tìm kiếm + Thêm sản phẩm --}}
    <div class="flex flex-wrap items-center gap-3">
        <input type="text" x-model="searchQuery" placeholder="Tìm Mã hàng, Tên hàng..." class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white w-64">
        <button type="button" @click="showAddModal = true" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Thêm sản phẩm</button>
    </div>

    {{-- Giá vốn hàng loạt (khi có chọn) --}}
    <div x-show="selectedIds.length > 0" x-transition class="flex flex-wrap items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
        <span class="text-sm text-gray-700 dark:text-gray-300">Đã chọn <span x-text="selectedIds.length"></span> hàng — Giá vốn hàng loạt:</span>
        <input type="text" x-model="bulkGiaVonStr" inputmode="decimal" placeholder="21,147" class="w-28 rounded-lg border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <span class="text-sm text-gray-500">đ</span>
        <button type="button" @click="doBulkGiaVon()" :disabled="bulkSaving" class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm text-white hover:bg-amber-700 disabled:opacity-50">Áp dụng</button>
        <button type="button" @click="selectedIds = []" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Bỏ chọn</button>
    </div>

    {{-- Bảng danh sách --}}
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full min-w-[600px] text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3"><input type="checkbox" :checked="selectedIds.length === filteredProducts.length && filteredProducts.length > 0" @change="toggleSelectAll()" class="rounded border-gray-300"></th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Mã hàng</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Tên hàng hóa</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Giá vốn</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Combo</th>
                    <th class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="p in filteredProducts" :key="p.id">
                    <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-800/50" :class="{ 'bg-brand-50/50 dark:bg-brand-500/10': selectedIds.includes(p.id) }">
                        <td class="px-4 py-2"><input type="checkbox" :checked="selectedIds.includes(p.id)" @change="toggleSelect(p.id)" class="rounded border-gray-300"></td>
                        <td class="px-4 py-2"><input type="text" :value="p.ma_hang" @blur="updateField(p, 'ma_hang', $event.target.value)" class="w-full min-w-[100px] rounded border border-transparent bg-transparent px-1 py-0.5 focus:border-brand-500 focus:bg-white focus:outline-none dark:focus:bg-gray-800"></td>
                        <td class="px-4 py-2"><input type="text" :value="p.ten_hang" @blur="updateField(p, 'ten_hang', $event.target.value)" class="w-full min-w-[120px] rounded border border-transparent bg-transparent px-1 py-0.5 focus:border-brand-500 focus:bg-white focus:outline-none dark:focus:bg-gray-800"></td>
                        <td class="px-4 py-2"><input type="text" :value="formatGiaVon(p.gia_von)" inputmode="decimal" @blur="updateField(p, 'gia_von', parseGiaVon($event.target.value))" class="w-28 rounded border border-transparent bg-transparent px-1 py-0.5 text-right focus:border-brand-500 focus:bg-white focus:outline-none dark:focus:bg-gray-800"><span class="ml-0.5 text-gray-500">đ</span></td>
                        <td class="px-4 py-2"><input type="checkbox" :checked="p.is_combo" @change="updateField(p, 'is_combo', $event.target.checked)" class="rounded border-gray-300"></td>
                        <td class="px-4 py-2"><button type="button" @click="deleteProduct(p)" class="text-red-600 hover:underline dark:text-red-400">Xóa</button></td>
                    </tr>
                </template>
                <tr x-show="filteredProducts.length === 0">
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Chưa có sản phẩm. Thêm thủ công hoặc dán mẫu từ sheet.</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Modal thêm sản phẩm --}}
    <div x-show="showAddModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="showAddModal = false">
        <div x-show="showAddModal" x-transition class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800" @click.stop>
            <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Thêm sản phẩm</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mã hàng</label>
                    <input type="text" x-model="addForm.ma_hang" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tên hàng hóa</label>
                    <input type="text" x-model="addForm.ten_hang" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Giá vốn (vd: 21,147)</label>
                    <input type="text" x-model="addForm.gia_von" inputmode="decimal" placeholder="21,147" class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <span class="text-xs text-gray-500">đ</span>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="showAddModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Hủy</button>
                <button type="button" @click="doAdd()" :disabled="addSaving" class="rounded-lg bg-brand-600 px-4 py-2 text-sm text-white hover:bg-brand-700 disabled:opacity-50">Thêm</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('sanPhamPage', (initialProducts) => ({
        products: initialProducts,
        searchQuery: '',
        selectedIds: [],
        bulkGiaVonStr: '',
        bulkSaving: false,
        formatGiaVon(n) {
            if (n == null || isNaN(n)) return '';
            const num = Number(n);
            const rounded = Math.round(num * 1000) / 1000;
            const isInteger = rounded === Math.floor(rounded);
            if (isInteger) {
                return Math.round(rounded).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }
            const parts = rounded.toFixed(3).replace(/0+$/, '').split('.');
            const intStr = parseInt(parts[0], 10).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return parts[1] ? intStr + '.' + parts[1] : intStr;
        },
        parseGiaVon(s) {
            if (s == null || typeof s !== 'string') return 0;
            const normalized = String(s).trim().replace(/\s/g, '').replace(/,/g, '');
            const num = parseFloat(normalized);
            return isNaN(num) || num < 0 ? 0 : num;
        },
        pasteData: '',
        pasting: false,
        pasteMessage: '',
        pasteOk: false,
        showAddModal: false,
        addForm: { ma_hang: '', ten_hang: '', gia_von: '' },
        addSaving: false,
        get filteredProducts() {
            const q = (this.searchQuery || '').toLowerCase().trim();
            if (!q) return this.products;
            return this.products.filter(p =>
                (p.ma_hang || '').toLowerCase().includes(q) ||
                (p.ten_hang || '').toLowerCase().includes(q)
            );
        },
        toggleSelect(id) {
            const i = this.selectedIds.indexOf(id);
            if (i >= 0) this.selectedIds.splice(i, 1);
            else this.selectedIds.push(id);
        },
        toggleSelectAll() {
            if (this.selectedIds.length === this.filteredProducts.length) this.selectedIds = [];
            else this.selectedIds = this.filteredProducts.map(p => p.id);
        },
        async doPaste() {
            if (!this.pasteData.trim()) return;
            this.pasting = true; this.pasteMessage = '';
            try {
                const r = await fetch('{{ route("food.san-pham.paste") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ data: this.pasteData })
                });
                const j = await r.json();
                this.pasteOk = j.ok;
                this.pasteMessage = j.ok ? `Đã lưu ${j.saved} sản phẩm.` : (j.message || 'Lỗi');
                if (j.ok && j.products) this.products = j.products;
            } catch (e) { this.pasteOk = false; this.pasteMessage = 'Lỗi kết nối'; }
            this.pasting = false;
        },
        async updateField(p, field, value) {
            const payload = { [field]: value };
            try {
                const r = await fetch(`/food/san-pham/${p.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const j = await r.json();
                if (j.ok && j.product) { Object.assign(p, j.product); p.gia_von = parseFloat(j.product.gia_von); p.is_combo = !!j.product.is_combo; }
            } catch (_) {}
        },
        async doBulkGiaVon() {
            if (this.selectedIds.length === 0) return;
            this.bulkSaving = true;
            try {
                const r = await fetch('{{ route("food.san-pham.bulk-gia-von") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ ids: this.selectedIds, gia_von: this.parseGiaVon(this.bulkGiaVonStr) })
                });
                const j = await r.json();
                if (j.ok) {
                    const val = this.parseGiaVon(this.bulkGiaVonStr);
                    this.products.forEach(pr => { if (this.selectedIds.includes(pr.id)) pr.gia_von = val; });
                    this.selectedIds = [];
                    this.bulkGiaVonStr = '';
                }
            } catch (_) {}
            this.bulkSaving = false;
        },
        async doAdd() {
            const ma = (this.addForm.ma_hang || '').trim();
            if (!ma) return;
            this.addSaving = true;
            try {
                const r = await fetch('{{ route("food.san-pham.store") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ ma_hang: ma, ten_hang: (this.addForm.ten_hang || '').trim(), gia_von: this.parseGiaVon(this.addForm.gia_von) })
                });
                const j = await r.json();
                if (j.ok && j.product) {
                    this.products.push({ id: j.product.id, ma_hang: j.product.ma_hang, ten_hang: j.product.ten_hang || '', gia_von: parseFloat(j.product.gia_von), is_combo: !!j.product.is_combo });
                    this.addForm = { ma_hang: '', ten_hang: '', gia_von: '' };
                    this.showAddModal = false;
                }
            } catch (_) {}
            this.addSaving = false;
        },
        async deleteProduct(p) {
            if (!confirm('Xóa sản phẩm này?')) return;
            try {
                const r = await fetch(`/food/san-pham/${p.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                });
                const j = await r.json();
                if (j.ok) { this.products = this.products.filter(x => x.id !== p.id); this.selectedIds = this.selectedIds.filter(id => id !== p.id); }
            } catch (_) {}
        }
    }));
});
</script>
@endsection
