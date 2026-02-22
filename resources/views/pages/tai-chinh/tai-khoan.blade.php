{{-- Nội dung tab Tài khoản: Liên kết tài khoản ngân hàng --}}
@php
    $canAddAccount = $canAddAccount ?? false;
    $maxAccounts = (int) ($maxAccounts ?? 0);
    $currentAccountCount = (int) ($currentAccountCount ?? 0);
    $userBankAccounts = $userBankAccounts ?? collect();
    $planExpiresAt = $planExpiresAt ?? null;
    $accountBalances = $accountBalances ?? [];
@endphp
<div class="space-y-6" x-data="{
    step: 1,
    selectedBank: 'BIDV',
    accountTab: 'ca-nhan',
    showAccountTabs: false,
    apiType: 'openapi',
    showUnlinkModal: false,
    unlinkAccountNumber: '',
    unlinkLast4: '',
    bankLabels: {
        'BIDV': 'Ngân hàng Đầu Tư và Phát Triển VN (BIDV)',
        'ACB': 'Ngân hàng Á Châu (ACB)',
        'MB': 'Ngân hàng Quân đội (MBBank)',
        'Vietcombank': 'Ngân hàng Ngoại Thương VN (VietcomBank)',
        'VietinBank': 'Ngân hàng Công Thương VN (Vietinbank)'
    }
}">
    {{-- Thông báo success/error hiển thị ở layout cha (tai-chinh.blade.php) để tránh trùng --}}
    {{-- Tiêu đề + giới hạn gói --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Liên kết tài khoản ngân hàng</h2>
        @if($maxAccounts > 0)
            <p class="text-sm text-gray-600 dark:text-gray-400">Đã dùng {{ $currentAccountCount }} / {{ $maxAccounts }} tài khoản
                @if(!$canAddAccount && $currentAccountCount >= $maxAccounts)
                    <span class="text-amber-600 dark:text-amber-400">(đã đạt giới hạn)</span>
                @endif
            </p>
        @endif
    </div>

    {{-- Thẻ ngân hàng đã lưu --}}
    @if($userBankAccounts->isNotEmpty())
        <div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($userBankAccounts as $acc)
                    @php
                        $bankLabels = [
                            'BIDV' => 'BIDV',
                            'ACB' => 'ACB',
                            'MB' => 'MBBank',
                            'Vietcombank' => 'Vietcombank',
                            'VietinBank' => 'VietinBank',
                        ];
                        $bankName = $bankLabels[$acc->bank_code ?? ''] ?? $acc->bank_code ?? 'Ngân hàng';
                        $holder = $acc->account_type === 'doanh_nghiep' ? ($acc->company_name ?? '—') : ($acc->full_name ?? '—');
                    @endphp
                    <div class="relative overflow-hidden rounded-2xl border-0 bg-gradient-to-br from-slate-50 via-white to-emerald-50/60 p-5 shadow-lg shadow-slate-200/50 ring-1 ring-slate-200/60 dark:from-slate-800/80 dark:via-slate-800/60 dark:to-emerald-950/40 dark:shadow-none dark:ring-slate-700/50">
                        <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-emerald-400/10 dark:bg-emerald-500/10"></div>
                        <div class="absolute -bottom-6 -left-6 h-20 w-20 rounded-full bg-slate-300/20 dark:bg-slate-600/20"></div>
                        <div class="relative flex items-start justify-between gap-2">
                            <p class="font-semibold text-slate-800 dark:text-white">{{ $bankName }}</p>
                            <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-400/20 dark:text-emerald-300">Đã lưu</span>
                        </div>
                        <p class="relative mt-3 text-sm text-slate-600 dark:text-slate-400">{{ $holder }}</p>
                        <p class="relative mt-1 font-mono text-sm tracking-wider text-slate-700 dark:text-slate-300">•••• {{ $acc->account_number ? substr($acc->account_number, -4) : '' }}</p>
                        @php
                            $stk = $acc->account_number ? trim((string) $acc->account_number) : '';
                            $balance = $stk !== '' && array_key_exists($stk, $accountBalances) ? $accountBalances[$stk] : null;
                        @endphp
                    <div class="relative" x-data="{ openBalanceForm: false }">
                        <p class="relative mt-2 text-base font-semibold text-slate-800 dark:text-white">
                            Số dư: @if($balance !== null){{ number_format($balance, 0, ',', '.') }} đ@else—@endif
                        </p>
                        <template x-if="openBalanceForm">
                            <form action="{{ route('tai-chinh.tai-khoan.update-balance') }}" method="post" class="relative mt-2 flex flex-wrap items-end gap-2">
                                @csrf
                                <input type="hidden" name="account_number" value="{{ $stk }}">
                                <label class="sr-only" for="balance-{{ $acc->id ?? $loop->index }}">Số dư (đ)</label>
                                <input type="text" id="balance-{{ $acc->id ?? $loop->index }}" name="balance" inputmode="numeric" data-format-vnd value="{{ $balance !== null ? (int) round($balance) : '' }}" placeholder="Nhập số dư" class="w-36 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                                <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600">Lưu</button>
                                <button type="button" @click="openBalanceForm = false" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-800">Hủy</button>
                            </form>
                        </template>
                        @if(!isset($balance) || $balance === null)
                            <button type="button" @click="openBalanceForm = true" class="relative mt-1 text-sm text-emerald-600 hover:underline dark:text-emerald-400">Cập nhật số dư</button>
                        @else
                            <button type="button" @click="openBalanceForm = true" class="relative mt-1 text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">Cập nhật số dư</button>
                        @endif
                        <div class="relative mt-3 flex w-full flex-wrap items-center justify-between gap-x-2 gap-y-1 text-xs text-slate-500 dark:text-slate-500">
                            <span>Lưu lúc {{ $acc->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                            <span class="flex items-center gap-2">
                                @if($planExpiresAt)
                                    <span class="font-medium text-slate-600 dark:text-slate-400">Hết hạn gói: {{ $planExpiresAt->format('d/m/Y') }}</span>
                                @endif
                                <button type="button" data-account-number="{{ $stk }}" data-last4="{{ $acc->account_number ? substr($acc->account_number, -4) : '' }}" @click="unlinkAccountNumber = $event.currentTarget.dataset.accountNumber; unlinkLast4 = $event.currentTarget.dataset.last4; showUnlinkModal = true" class="text-slate-400 hover:text-red-600 dark:text-slate-500 dark:hover:text-red-400">Gỡ liên kết</button>
                            </span>
                        </div>
                    </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Modal gỡ liên kết --}}
        <div x-show="showUnlinkModal" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="showUnlinkModal = false">
            <div class="absolute inset-0 bg-black/50" @click="showUnlinkModal = false"></div>
            <div class="relative w-full max-w-sm rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-800" @click.stop>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Gỡ liên kết tài khoản</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Bạn có chắc muốn gỡ liên kết tài khoản <span class="font-mono font-medium text-gray-800 dark:text-gray-200" x-text="'•••• ' + unlinkLast4"></span>? Số dư và giao dịch của tài khoản này sẽ không còn hiển thị trên Dashboard và Giao dịch. Bạn có thể liên kết lại sau.
                </p>
                <form method="post" action="{{ route('tai-chinh.tai-khoan.unlink') }}" class="mt-5 flex flex-wrap justify-end gap-2">
                    @csrf
                    <input type="hidden" name="account_number" :value="unlinkAccountNumber">
                    <button type="button" @click="showUnlinkModal = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Hủy</button>
                    <button type="submit" @click="showUnlinkModal = false" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600">Gỡ liên kết</button>
                </form>
            </div>
        </div>
    @endif

    {{-- Hai bước (click để chuyển tab) --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <button type="button" @click="step = 1"
            class="w-full rounded-xl border p-4 text-left transition-colors hover:opacity-90"
            :class="step >= 1 ? 'border-brand-200 bg-brand-50 dark:border-brand-800 dark:bg-brand-500/10' : 'border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03]'">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium text-white transition-colors"
                :class="step >= 1 ? 'bg-brand-500' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'">1</span>
            <h3 class="mt-2 font-medium transition-colors" :class="step >= 1 ? 'text-brand-700 dark:text-brand-400' : 'text-gray-900 dark:text-white'">Danh sách ngân hàng</h3>
            <p class="mt-1 text-sm transition-colors" :class="step >= 1 ? 'text-brand-600 dark:text-brand-400/90' : 'text-gray-600 dark:text-gray-400'">Chọn ngân hàng liên kết phù hợp của bạn</p>
        </button>
        <div class="rounded-xl border p-4 transition-colors"
            :class="step >= 2 ? 'border-brand-200 bg-brand-50 dark:border-brand-800 dark:bg-brand-500/10' : 'border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03]'">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium text-white transition-colors"
                :class="step >= 2 ? 'bg-brand-500' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'">2</span>
            <h3 class="mt-2 font-medium transition-colors" :class="step >= 2 ? 'text-brand-700 dark:text-brand-400' : 'text-gray-900 dark:text-white'">Thêm thông tin tài khoản ngân hàng</h3>
            <p class="mt-1 text-sm transition-colors" :class="step >= 2 ? 'text-brand-600 dark:text-brand-400/90' : 'text-gray-600 dark:text-gray-400'">Nhập đầy đủ và chính xác thông tin đăng nhập</p>
        </div>
    </div>

    {{-- Bước 1: Bảng ngân hàng (dữ liệu mẫu) --}}
    @php
        $banks = [
            [
                'name' => 'BIDV',
                'logo' => null,
                'speed' => '1-3 giây',
                'has_check' => true,
                'account_types' => ['Cá nhân'],
                'support' => ['OpenBanking', 'Tài khoản ảo VA'],
            ],
            [
                'name' => 'ACB',
                'logo' => null,
                'speed' => '1-3 giây',
                'has_check' => true,
                'account_types' => ['Cá nhân', 'Doanh nghiệp'],
                'support' => ['OpenBanking', 'Đồng bộ giao dịch tiền vào - tiền ra'],
            ],
            [
                'name' => 'MB',
                'logo' => null,
                'speed' => '1-3 giây',
                'has_check' => true,
                'account_types' => ['Cá nhân', 'Doanh nghiệp'],
                'support' => ['OpenBanking', 'Đồng bộ giao dịch tiền vào - tiền ra'],
            ],
            [
                'name' => 'Vietcombank',
                'logo' => null,
                'speed' => '1-3 giây',
                'has_check' => false,
                'account_types' => ['Cá nhân', 'Doanh nghiệp'],
                'support' => ['Đồng bộ giao dịch tiền vào - tiền ra'],
            ],
            [
                'name' => 'VietinBank',
                'logo' => null,
                'speed' => '1-3 giây',
                'has_check' => false,
                'account_types' => ['Cá nhân', 'Doanh nghiệp'],
                'support' => ['Đồng bộ giao dịch tiền vào - tiền ra'],
            ],
        ];
    @endphp
    <div x-show="step === 1" x-cloak class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 bg-success-50 dark:border-gray-800 dark:bg-gray-800">
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Ngân hàng</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">
                            <span class="inline-flex items-center gap-1">Tốc độ
                                <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                        </th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Tài khoản</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Hỗ trợ</th>
                        <th class="px-4 py-3 font-medium text-gray-800 dark:text-white">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($banks as $bank)
                    <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-sm font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ strtoupper(mb_substr($bank['name'], 0, 2)) }}</span>
                                @if($bank['has_check'])
                                    <span class="text-success-500">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    </span>
                                @endif
                                <span class="font-medium text-gray-900 dark:text-white">{{ $bank['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $bank['speed'] }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($bank['account_types'] as $type)
                                    @if($type === 'Cá nhân')
                                        <span class="inline-flex rounded-md bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/20 dark:text-success-400">{{ $type }}</span>
                                    @else
                                        <span class="inline-flex rounded-md bg-blue-light-100 px-2 py-0.5 text-xs font-medium text-blue-light-700 dark:bg-blue-light-500/20 dark:text-blue-light-400">{{ $type }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                            <ul class="space-y-0.5">
                                @foreach($bank['support'] as $item)
                                    <li> - {{ $item }}</li>
                                @endforeach
                            </ul>
                        </td>
                        <td class="px-4 py-3">
                            <button type="button" @click="step = 2; selectedBank = '{{ $bank['name'] }}'; showAccountTabs = {{ (in_array('Cá nhân', $bank['account_types']) && in_array('Doanh nghiệp', $bank['account_types'])) ? 'true' : 'false' }}; accountTab = 'ca-nhan'; if (selectedBank === 'Vietcombank' || selectedBank === 'VietinBank') apiType = 'pay2s'" class="rounded-lg bg-success-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">Kết nối</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bước 2: Hai khối form + thông tin (chia đôi 50/50) --}}
    <div x-show="step === 2" x-cloak class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        {{-- Khối trái: Form thêm tài khoản --}}
        <div class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <form method="POST" action="{{ route('tai-chinh.tai-khoan.store') }}" id="form-bank-account"
                @submit="
                    var at = accountTab === 'doanh-nghiep' ? 'doanh_nghiep' : 'ca_nhan';
                    var currentKey = selectedBank + '_' + at + '_' + apiType;
                    document.querySelectorAll('#form-bank-account [data-form-block]').forEach(function(block) {
                        var key = block.getAttribute('data-form-block');
                        var isActive = key === currentKey || key.indexOf(',') >= 0 ? key.split(',').indexOf(currentKey) >= 0 : (currentKey.startsWith(key + '_') || currentKey === key);
                        block.querySelectorAll('input, select, textarea').forEach(function(inp) {
                            if (inp.name && !['_token','bank_code','account_type','api_type'].includes(inp.name)) inp.disabled = !isActive;
                        });
                    });
                ">
                @csrf
                <input type="hidden" name="bank_code" :value="selectedBank">
                <input type="hidden" name="account_type" :value="accountTab === 'doanh-nghiep' ? 'doanh_nghiep' : 'ca_nhan'">
                <input type="hidden" name="api_type" :value="apiType">
            {{-- Hai khối hai cột: chiều dài nút cố định (50% mỗi ô), 1 hoặc 2 nút đều bằng nhau --}}
            <div class="mb-5 grid gap-2" style="grid-template-columns: 1fr 1fr;">
                <div>
                    <button type="button" @click="accountTab = 'ca-nhan'"
                        class="w-full rounded-lg py-2.5 text-center text-sm font-medium transition-colors"
                        :class="accountTab === 'ca-nhan' ? 'bg-success-500 text-white' : 'border border-success-500 bg-white text-success-600 dark:border-success-500 dark:bg-transparent dark:text-success-400'">
                        Tài khoản cá nhân
                    </button>
                </div>
                <div>
                    <template x-if="showAccountTabs">
                        <button type="button" @click="accountTab = 'doanh-nghiep'"
                            class="w-full rounded-lg py-2.5 text-center text-sm font-medium transition-colors"
                            :class="accountTab === 'doanh-nghiep' ? 'bg-success-500 text-white' : 'border border-success-500 bg-white text-success-600 dark:border-success-500 dark:bg-transparent dark:text-success-400'">
                            Tài khoản doanh nghiệp
                        </button>
                    </template>
                </div>
            </div>
            {{-- Chọn kiểu API: BIDV, MB luôn hiện; ACB chỉ khi Cá nhân; VCB/VietinBank chỉ Bank30S --}}
            <div x-show="selectedBank === 'BIDV' || selectedBank === 'MB' || (selectedBank === 'ACB' && accountTab === 'ca-nhan')" x-cloak class="mb-5">
                <p class="mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">Chọn kiểu API</p>
                <div class="flex gap-4">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" x-model="apiType" value="openapi" class="h-4 w-4 border-gray-300 text-success-500 focus:ring-success-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">OpenAPI (khuyên dùng)</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input type="radio" x-model="apiType" value="pay2s" class="h-4 w-4 border-gray-300 text-success-500 focus:ring-success-500">
                        <span class="text-sm text-gray-700 dark:text-gray-300">Bank30S API</span>
                    </label>
                </div>
            </div>
            <div x-show="selectedBank === 'Vietcombank' || selectedBank === 'VietinBank'" x-cloak class="mb-5">
                <p class="mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">Kiểu API</p>
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="radio" x-model="apiType" value="pay2s" class="h-4 w-4 border-gray-300 text-success-500 focus:ring-success-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300">Bank30S API</span>
                </label>
            </div>

            {{-- Form BIDV OpenAPI --}}
            <div x-show="selectedBank === 'BIDV' && apiType === 'openapi'" x-cloak class="space-y-4" data-form-block="BIDV_ca_nhan_openapi">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Họ và tên (không dấu) *</label>
                        <input type="text" name="full_name" placeholder="NHẬP HỌ VÀ TÊN..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Email *</label>
                        <input type="email" name="email" placeholder="Nhập email..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số điện thoại *</label>
                        <input type="tel" name="phone" placeholder="Nhập số điện thoại..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số căn cước công dân *</label>
                        <input type="text" name="id_number" placeholder="Nhập số CCCD..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số tài khoản BIDV *</label>
                        <input type="text" name="account_number" placeholder="Nhập số tài khoản..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Tạo số tài khoản ảo *</label>
                        <div class="flex gap-2">
                            <span class="inline-flex items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">963869</span>
                            <input type="text" name="virtual_account_suffix" placeholder="Nhập số hoặc chữ (ví dụ: VYNT)" class="flex-1 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                        </div>
                    </div>
                </div>
                <label class="mt-4 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="agreed_terms" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Tôi đồng ý với <a href="#" class="font-medium text-success-600 hover:underline dark:text-success-400">Chính sách bảo mật</a> * và cho phép Bank30S truy cập thông tin tài chính từ ngân hàng cũng như nhận thông báo thanh toán.</span>
                </label>
                <button type="submit" class="mt-6 w-full rounded-lg bg-success-500 py-3 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">THÊM TÀI KHOẢN</button>
            </div>

            {{-- Form ACB Cá nhân --}}
            <div x-show="selectedBank === 'ACB' && accountTab === 'ca-nhan'" x-cloak class="space-y-4" data-form-block="ACB_ca_nhan">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Họ và tên (không dấu) *</label>
                        <input type="text" name="full_name" placeholder="NHẬP HỌ VÀ TÊN..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số điện thoại *</label>
                        <input type="tel" name="phone" placeholder="Nhập số điện thoại..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số tài khoản ACB *</label>
                        <input type="text" name="account_number" placeholder="Nhập số tài khoản ACB..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                </div>
                <label class="mt-4 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="agreed_terms" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Bằng cách cung cấp thông tin cho Bank30S. Bạn đã đồng ý với <a href="#" class="font-medium text-success-600 hover:underline dark:text-success-400">Chính sách bảo mật</a> * của Bank30S và cho phép Bank30S truy xuất thông tin tài chính từ ngân hàng của bạn và Đồng ý nhận thông báo tiền về từ ngân hàng đến hệ thống Bank30S.</span>
                </label>
                <button type="submit" class="mt-6 w-full rounded-lg bg-success-500 py-3 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">THÊM TÀI KHOẢN</button>
            </div>

            {{-- Form ACB Doanh nghiệp --}}
            <div x-show="selectedBank === 'ACB' && accountTab === 'doanh-nghiep'" x-cloak class="space-y-4" data-form-block="ACB_doanh_nghiep">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Tên công ty (không dấu) *</label>
                        <input type="text" name="company_name" placeholder="NHẬP TÊN CÔNG TY..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số điện thoại *</label>
                        <input type="tel" name="phone" placeholder="Nhập số điện thoại..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số tài khoản ACB *</label>
                        <input type="text" name="account_number" placeholder="Nhập số tài khoản ACB..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Username ACB Onebiz *</label>
                        <input type="text" name="login_username" placeholder="Nhập username ACB Onebiz" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                </div>
                <label class="mt-4 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="agreed_terms" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Bằng cách cung cấp thông tin cho Bank30S. Bạn đã đồng ý với <a href="#" class="font-medium text-success-600 hover:underline dark:text-success-400">Chính sách bảo mật</a> * của Bank30S và cho phép Bank30S truy xuất thông tin tài chính từ ngân hàng của bạn và Đồng ý nhận thông báo tiền về từ ngân hàng đến hệ thống Bank30S.</span>
                </label>
                <button type="submit" class="mt-6 w-full rounded-lg bg-success-500 py-3 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">THÊM TÀI KHOẢN</button>
            </div>

            {{-- Form MB Cá nhân + OpenAPI --}}
            <div x-show="selectedBank === 'MB' && accountTab === 'ca-nhan' && apiType === 'openapi'" x-cloak class="space-y-4" data-form-block="MB_ca_nhan_openapi">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Họ và tên (không dấu) *</label>
                        <input type="text" name="full_name" placeholder="NHẬP HỌ VÀ TÊN..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Email *</label>
                        <input type="email" name="email" placeholder="Nhập email..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số điện thoại *</label>
                        <input type="tel" name="phone" placeholder="Nhập số điện thoại..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số căn cước công dân *</label>
                        <input type="text" name="id_number" placeholder="Nhập số CCCD..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số tài khoản MBB *</label>
                        <input type="text" name="account_number" placeholder="Nhập số tài khoản MBB..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Loại giao dịch *</label>
                        <select name="transaction_type" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">Chọn loại giao dịch</option>
                            <option value="all" selected>Tất cả</option>
                        </select>
                    </div>
                </div>
                <label class="mt-4 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="agreed_terms" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Bằng cách cung cấp thông tin cho Bank30S. Bạn đã đồng ý với <a href="#" class="font-medium text-success-600 hover:underline dark:text-success-400">Chính sách bảo mật</a> * của Bank30S và cho phép Bank30S truy xuất thông tin tài chính từ ngân hàng của bạn và Đồng ý nhận thông báo tiền về từ ngân hàng đến hệ thống Bank30S.</span>
                </label>
                <button type="submit" class="mt-6 w-full rounded-lg bg-success-500 py-3 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">THÊM TÀI KHOẢN</button>
            </div>

            {{-- Form MB Cá nhân + Bank30S API --}}
            <div x-show="selectedBank === 'MB' && accountTab === 'ca-nhan' && apiType === 'pay2s'" x-cloak class="space-y-4" data-form-block="MB_ca_nhan_pay2s">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Tên đăng nhập MBB *</label>
                        <input type="text" name="login_username" placeholder="Nhập tên đăng nhập MBB..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Mật khẩu tài khoản MBB *</label>
                        <input type="password" name="login_password" placeholder="Nhập mật khẩu..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số tài khoản MBB *</label>
                        <input type="text" name="account_number" placeholder="Nhập số tài khoản MBB..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                </div>
                <label class="mt-4 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="agreed_terms" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Bằng cách cung cấp thông tin cho Bank30S. Bạn đã đồng ý với <a href="#" class="font-medium text-success-600 hover:underline dark:text-success-400">Chính sách bảo mật</a> * của Bank30S và cho phép Bank30S truy xuất thông tin tài chính từ ngân hàng của bạn và Đồng ý nhận thông báo tiền về từ ngân hàng đến hệ thống Bank30S.</span>
                </label>
                <button type="submit" class="mt-6 w-full rounded-lg bg-success-500 py-3 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">THÊM TÀI KHOẢN</button>
            </div>

            {{-- Form MB Doanh nghiệp + OpenAPI --}}
            <div x-show="selectedBank === 'MB' && accountTab === 'doanh-nghiep' && apiType === 'openapi'" x-cloak class="space-y-4" data-form-block="MB_doanh_nghiep_openapi">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Tên công ty (không dấu) *</label>
                        <input type="text" name="company_name" placeholder="NHẬP TÊN CÔNG TY..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số điện thoại *</label>
                        <input type="tel" name="phone" placeholder="Nhập số điện thoại..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Mã số thuế *</label>
                        <input type="text" name="tax_code" placeholder="Nhập mã số thuế..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số tài khoản MBB *</label>
                        <input type="text" name="account_number" placeholder="Nhập số tài khoản MBB..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Loại giao dịch *</label>
                        <select name="transaction_type" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">Chọn loại giao dịch</option>
                            <option value="all" selected>Tất cả</option>
                        </select>
                    </div>
                </div>
                <label class="mt-4 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="agreed_terms" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Bằng cách cung cấp thông tin cho Bank30S. Bạn đã đồng ý với <a href="#" class="font-medium text-success-600 hover:underline dark:text-success-400">Chính sách bảo mật</a> * của Bank30S và cho phép Bank30S truy xuất thông tin tài chính từ ngân hàng của bạn và Đồng ý nhận thông báo tiền về từ ngân hàng đến hệ thống Bank30S.</span>
                </label>
                <button type="submit" class="mt-6 w-full rounded-lg bg-success-500 py-3 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">THÊM TÀI KHOẢN</button>
            </div>

            {{-- Form MB Doanh nghiệp + Bank30S API --}}
            <div x-show="selectedBank === 'MB' && accountTab === 'doanh-nghiep' && apiType === 'pay2s'" x-cloak class="space-y-4" data-form-block="MB_doanh_nghiep_pay2s">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Tên đăng nhập tài khoản MBB *</label>
                        <input type="text" name="login_username" placeholder="Nhập tên đăng nhập MBB..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Mật khẩu tài khoản MBB *</label>
                        <input type="password" name="login_password" placeholder="Nhập mật khẩu..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số tài khoản MBB *</label>
                        <input type="text" name="account_number" placeholder="Nhập số tài khoản MBB..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Mã doanh nghiệp *</label>
                        <input type="text" name="company_code" placeholder="Nhập mã doanh nghiệp của bạn" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                </div>
                <label class="mt-4 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="agreed_terms" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Bằng cách cung cấp thông tin cho Bank30S. Bạn đã đồng ý với <a href="#" class="font-medium text-success-600 hover:underline dark:text-success-400">Chính sách bảo mật</a> * của Bank30S và cho phép Bank30S truy xuất thông tin tài chính từ ngân hàng của bạn và Đồng ý nhận thông báo tiền về từ ngân hàng đến hệ thống Bank30S.</span>
                </label>
                <button type="submit" class="mt-6 w-full rounded-lg bg-success-500 py-3 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">THÊM TÀI KHOẢN</button>
            </div>

            {{-- Form Vietcombank / VietinBank (Bank30S: Tên đăng nhập, Mật khẩu, Số TK) --}}
            <div x-show="(selectedBank === 'Vietcombank' || selectedBank === 'VietinBank') && apiType === 'pay2s'" x-cloak class="space-y-4" data-form-block="Vietcombank_ca_nhan_pay2s,Vietcombank_doanh_nghiep_pay2s,VietinBank_ca_nhan_pay2s,VietinBank_doanh_nghiep_pay2s">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400" x-text="'Tên đăng nhập ' + (selectedBank === 'Vietcombank' ? 'VCB' : 'VietinBank') + ' *'"></label>
                        <input type="text" name="login_username" :placeholder="'Nhập tên đăng nhập ' + (selectedBank === 'Vietcombank' ? 'VCB' : 'VietinBank') + '...'" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400" x-text="'Mật khẩu tài khoản ' + (selectedBank === 'Vietcombank' ? 'VCB' : 'VietinBank') + ' *'"></label>
                        <input type="password" name="login_password" placeholder="Nhập mật khẩu..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400" x-text="'Số tài khoản ' + (selectedBank === 'Vietcombank' ? 'VCB' : 'VietinBank') + ' *'"></label>
                        <input type="text" name="account_number" :placeholder="'Nhập số tài khoản ' + (selectedBank === 'Vietcombank' ? 'VCB' : 'VietinBank') + '...'" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                </div>
                <label class="mt-4 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="agreed_terms" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Bằng cách cung cấp thông tin cho Bank30S. Bạn đã đồng ý với <a href="#" class="font-medium text-success-600 hover:underline dark:text-success-400">Chính sách bảo mật</a> * của Bank30S và cho phép Bank30S truy xuất thông tin tài chính từ ngân hàng của bạn và Đồng ý nhận thông báo tiền về từ ngân hàng đến hệ thống Bank30S.</span>
                </label>
                <button type="submit" class="mt-6 w-full rounded-lg bg-success-500 py-3 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">THÊM TÀI KHOẢN</button>
            </div>

            {{-- Form BIDV khi chọn Bank30S API --}}
            <div x-show="selectedBank === 'BIDV' && apiType === 'pay2s'" x-cloak class="space-y-4" data-form-block="BIDV_ca_nhan_pay2s">
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Tên đăng nhập BIDV *</label>
                        <input type="text" name="login_username" placeholder="Nhập tên đăng nhập BIDV..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Mật khẩu tài khoản BIDV *</label>
                        <input type="password" name="login_password" placeholder="Nhập mật khẩu..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-400">Số tài khoản BIDV *</label>
                        <input type="text" name="account_number" placeholder="Nhập số tài khoản..." class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-500">
                    </div>
                </div>
                <label class="mt-4 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" name="agreed_terms" value="1" class="mt-1 h-4 w-4 rounded border-gray-300 text-success-500 focus:ring-success-500">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Bằng cách cung cấp thông tin cho Bank30S. Bạn đã đồng ý với <a href="#" class="font-medium text-success-600 hover:underline dark:text-success-400">Chính sách bảo mật</a> * của Bank30S và cho phép Bank30S truy xuất thông tin tài chính từ ngân hàng của bạn và Đồng ý nhận thông báo tiền về từ ngân hàng đến hệ thống Bank30S.</span>
                </label>
                <button type="submit" class="mt-6 w-full rounded-lg bg-success-500 py-3 text-sm font-medium text-white hover:bg-success-600 dark:bg-success-600 dark:hover:bg-success-500">THÊM TÀI KHOẢN</button>
            </div>
            </form>
        </div>

        {{-- Khối phải: Thông tin hướng dẫn --}}
        <div class="min-w-0 min-h-0 overflow-hidden space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-700 dark:text-gray-300">Vui lòng nhập chính xác thông tin tài khoản <span x-text="bankLabels[selectedBank] || selectedBank"></span>.</p>
            <a href="#" class="text-sm font-medium text-success-600 hover:underline dark:text-success-400">Xem thêm Tài liệu hướng dẫn.</a>
            <p class="text-sm text-gray-600 dark:text-gray-400">Ngân hàng hỗ trợ tài khoản doanh nghiệp: ACB, Vietcombank, Vietinbank, MB Bank</p>
            <div>
                <p class="mb-1 text-sm font-medium text-gray-800 dark:text-white">Lưu ý:</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Ngân hàng kết nối qua OpenAPI có hiển thị số tài khoản ảo (Virtual Account – VA), giao dịch phải qua số tài khoản ảo thì Bank30S mới nhận được dữ liệu. Số tài khoản ảo được tạo và hiển thị sau khi thêm tài khoản thành công.</p>
            </div>
            {{-- Chỉ BIDV: Cấu trúc VA --}}
            <div x-show="selectedBank === 'BIDV'" x-cloak class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-gray-800 dark:bg-gray-dark">
                <p class="mb-2 text-sm font-medium text-success-800 dark:text-success-400">Cấu trúc tài khoản ảo (VA) ngân hàng BIDV</p>
                <p class="text-sm text-success-700 dark:text-success-400/90">Tài khoản ảo cá nhân bắt đầu bằng prefix <strong class="text-success-600 dark:text-success-400">963869</strong>, phần suffix A–Z, 0–9, tối đa 19 ký tự.</p>
            </div>
            {{-- MB Doanh nghiệp OpenAPI: Hợp đồng 3 bên --}}
            <div x-show="selectedBank === 'MB' && accountTab === 'doanh-nghiep' && apiType === 'openapi'" x-cloak class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-gray-800 dark:bg-gray-dark">
                <p class="mb-2 text-sm font-medium text-success-800 dark:text-success-400">Đối với Tài khoản doanh nghiệp MBBank sử dụng OpenAPI</p>
                <p class="mb-3 text-sm text-success-700 dark:text-success-400/90">Quý doanh nghiệp cần ký hợp đồng 3 bên (Quý khách hàng, MB Bank và Bank30S) trong vòng 07 ngày để hoàn tất hồ sơ đăng ký sử dụng dịch vụ.</p>
                <p class="mb-2 text-sm font-medium text-success-800 dark:text-success-400">Đại diện doanh nghiệp vui lòng ủy quyền, đóng dấu và gửi về địa chỉ:</p>
                <ul class="mb-3 space-y-1 text-sm text-success-700 dark:text-success-400/90">
                    <li><strong>Tên người nhận:</strong> Công ty Cổ Phần FUTE</li>
                    <li><strong>Địa chỉ:</strong> 15/40/30 Đường số 59, Phường 14, Quận Gò Vấp, Thành Phố Hồ Chí Minh</li>
                    <li><strong>Số điện thoại:</strong> 0775778190</li>
                </ul>
                <p class="text-sm text-success-700 dark:text-success-400/90">Quý doanh nghiệp vui lòng liên hệ với Bank30S để lấy hợp đồng và kích hoạt sử dụng.</p>
            </div>
        </div>
    </div>
</div>
