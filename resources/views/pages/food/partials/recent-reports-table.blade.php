@php
    $reportsDoanhSo = $reportsDoanhSo ?? collect();
    $branchThemes = [
        ['box' => 'bg-emerald-50 dark:bg-emerald-900/30', 'icon' => 'text-emerald-600 dark:text-emerald-400', 'ring' => 'ring-emerald-100 dark:ring-emerald-800/50'],
        ['box' => 'bg-amber-50 dark:bg-amber-900/30', 'icon' => 'text-amber-600 dark:text-amber-400', 'ring' => 'ring-amber-100 dark:ring-amber-800/50'],
        ['box' => 'bg-rose-50 dark:bg-rose-900/30', 'icon' => 'text-rose-600 dark:text-rose-400', 'ring' => 'ring-rose-100 dark:ring-rose-800/50'],
        ['box' => 'bg-sky-50 dark:bg-sky-900/30', 'icon' => 'text-sky-600 dark:text-sky-400', 'ring' => 'ring-sky-100 dark:ring-sky-800/50'],
    ];
    $branchIndexMap = [];
    foreach ($reportsDoanhSo->pluck('branch.name')->unique()->filter() as $idx => $name) {
        $branchIndexMap[$name] = $idx;
    }
@endphp
<div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-6">
        <h3 class="text-base font-bold text-gray-900 dark:text-white">Báo cáo gần đây</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[960px] text-left text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/80 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                    <th class="px-4 py-3">Mã báo cáo</th>
                    <th class="px-4 py-3">Chi nhánh</th>
                    <th class="px-4 py-3">Ngày báo cáo</th>
                    <th class="px-4 py-3 text-right">Quyết toán</th>
                    <th class="px-4 py-3 text-right">Doanh số</th>
                    <th class="px-4 py-3 text-right">Phí buff</th>
                    <th class="px-4 py-3 text-right">Ads</th>
                    <th class="px-4 py-3 text-right">Lợi nhuận</th>
                    <th class="px-4 py-3 text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($reportsDoanhSo as $r)
                    @php
                        $branchName = $r->branch?->name ?? '—';
                        $themeIdx = $branchIndexMap[$branchName] ?? 0;
                        $theme = $branchThemes[$themeIdx % count($branchThemes)];
                        $loiNhuan = $r->loi_nhuan ?? null;
                        $positive = $loiNhuan === null || $loiNhuan >= 0;
                    @endphp
                    <tr class="transition hover:bg-gray-50/70 dark:hover:bg-gray-800/30" x-data="{
                        editing: false,
                        menuOpen: false,
                        doanhSo: {{ json_encode($r->doanh_so !== null ? (int)$r->doanh_so : '') }},
                        phiBuff: {{ json_encode($r->phi_buff !== null ? (int)$r->phi_buff : '') }},
                        phiAds: {{ json_encode($r->phi_ads !== null ? (int)$r->phi_ads : '') }},
                        quyetToan: {{ (int) round($r->quyet_toan) }},
                        get loiNhuan() {
                            var ds = parseInt(this.doanhSo, 10);
                            if (isNaN(ds)) return null;
                            var buff = parseInt(this.phiBuff, 10);
                            if (isNaN(buff)) buff = 0;
                            var ads = parseInt(this.phiAds, 10);
                            if (isNaN(ads)) ads = 0;
                            return ds - this.quyetToan - buff - ads;
                        },
                        saving: false,
                        async save() {
                            this.saving = true;
                            try {
                                const res = await fetch('{{ url('/food/bao-cao-ban-hang/' . (int) $r->id . '/doanh-so') }}', {
                                    method: 'PUT',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                                    body: JSON.stringify({
                                        doanh_so: this.doanhSo === '' ? null : parseInt(this.doanhSo, 10),
                                        phi_buff: this.phiBuff === '' ? null : parseInt(this.phiBuff, 10),
                                        phi_ads: this.phiAds === '' ? null : parseInt(this.phiAds, 10)
                                    })
                                });
                                const data = await res.json();
                                if (data.success) {
                                    this.doanhSo = data.doanh_so ?? '';
                                    this.phiBuff = data.phi_buff ?? '';
                                    this.phiAds = data.phi_ads ?? '';
                                    this.editing = false;
                                } else { alert(data.message || 'Lưu thất bại'); }
                            } catch (e) { alert('Lỗi kết nối'); }
                            this.saving = false;
                        }
                    }">
                        <td class="px-4 py-3.5 font-medium text-gray-900 dark:text-white">{{ $r->report_code ?? '—' }}</td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1 {{ $theme['box'] }} {{ $theme['ring'] }}">
                                    <svg class="h-4 w-4 {{ $theme['icon'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 4l9 5.75V19a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1V9.75z"/></svg>
                                </span>
                                <span class="text-gray-800 dark:text-gray-200">{{ $branchName }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-gray-700 dark:text-gray-300">{{ $r->report_date ? $r->report_date->format('d/m/Y') : '—' }}</td>
                        <td class="px-4 py-3.5 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($r->quyet_toan) }} đ</td>
                        <td class="px-4 py-3.5 text-right tabular-nums">
                            <template x-if="!editing"><span class="text-gray-900 dark:text-white" x-text="doanhSo !== '' ? new Intl.NumberFormat('vi-VN').format(doanhSo) + ' đ' : '—'"></span></template>
                            <template x-if="editing"><input type="text" x-model="doanhSo" inputmode="numeric" class="w-24 rounded-lg border border-gray-200 px-2 py-1 text-right text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></template>
                        </td>
                        <td class="px-4 py-3.5 text-right tabular-nums">
                            <template x-if="!editing"><span class="text-gray-900 dark:text-white" x-text="phiBuff !== '' ? new Intl.NumberFormat('vi-VN').format(phiBuff) + ' đ' : '0 đ'"></span></template>
                            <template x-if="editing"><input type="text" x-model="phiBuff" inputmode="numeric" class="w-20 rounded-lg border border-gray-200 px-2 py-1 text-right text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></template>
                        </td>
                        <td class="px-4 py-3.5 text-right tabular-nums">
                            <template x-if="!editing"><span class="text-gray-900 dark:text-white" x-text="phiAds !== '' ? new Intl.NumberFormat('vi-VN').format(phiAds) + ' đ' : '0 đ'"></span></template>
                            <template x-if="editing"><input type="text" x-model="phiAds" inputmode="numeric" class="w-20 rounded-lg border border-gray-200 px-2 py-1 text-right text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></template>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <span @class([
                                'font-semibold tabular-nums',
                                'text-emerald-600 dark:text-emerald-400' => $positive,
                                'text-rose-600 dark:text-rose-400' => ! $positive,
                            ]) x-text="loiNhuan !== null ? new Intl.NumberFormat('vi-VN').format(loiNhuan) + ' đ' : '—'"></span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center justify-center gap-1.5">
                                <template x-if="editing">
                                    <button type="button" @click="save()" :disabled="saving" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50">Lưu</button>
                                </template>
                                <a href="{{ route('food.bao-cao-ban-hang.show', $r) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-900/30 dark:text-brand-300">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Xem chi tiết
                                </a>
                                <div class="relative">
                                    <button type="button" @click="menuOpen = !menuOpen" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                    </button>
                                    <div x-show="menuOpen" @click.outside="menuOpen = false" x-cloak class="absolute right-0 z-20 mt-1 w-40 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-800">
                                        <button type="button" @click="editing = true; menuOpen = false" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Sửa doanh số</button>
                                        <button type="button" x-show="editing" @click="editing = false; menuOpen = false" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">Hủy sửa</button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">Chưa có báo cáo nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
