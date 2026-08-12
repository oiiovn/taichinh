@extends('layouts.food')

@section('foodContent')
@php
    $fmt = fn ($n) => \App\Helpers\BaoCaoHelper::formatGiaVonNguyen($n);
    $renderReviewContent = static function (?string $text): string {
        if ($text === null || trim($text) === '') {
            return '';
        }

        $escaped = e($text);
        // Bỏ cụm rác hay gặp trong text copy
        $escaped = (string) preg_replace('/\boo[•\.\s]*More\b/iu', '', $escaped);
        $escaped = (string) preg_replace('/\{\%\s*Gửi\s*Voucher[^}\n\r]*\}?/iu', '', $escaped);
        $escaped = (string) preg_replace('/\bGửi\s*Voucher\b/iu', '', $escaped);
        $escaped = trim((string) preg_replace('/\s{2,}/u', ' ', $escaped));

        if (preg_match('/(?<!\d)([1-5])(?:\.0)?(?!\d)/u', $escaped)) {
            return '';
        }

        return (string) preg_replace_callback('/(?<!\d)([1-5](?:\.0)?)(?!\d)/u', function ($m) {
            $score = (float) $m[1];
            $full = max(0, min(5, (int) round($score)));
            $stars = str_repeat('★', $full).str_repeat('☆', 5 - $full);

            return '<span class="inline-flex items-center gap-1 rounded border border-amber-300 bg-amber-50 px-1.5 py-px text-[11px] font-semibold text-amber-700 dark:border-amber-500/40 dark:bg-amber-900/30 dark:text-amber-200">'.$stars.' '.$m[1].'</span>';
        }, $escaped);
    };
@endphp
    @php
        $canMarkRewarded = auth()->user()?->is_admin || auth()->user()?->canManageFoodReviews();
        $canImportReviews = auth()->user()?->canManageFoodReviews();
        $showReviewServerFilters = auth()->user()?->is_admin;
        $hasFoodMobileAppBar = auth()->user()
            && method_exists(auth()->user(), 'isFoodReviewsOnlyUser')
            && ! auth()->user()->isFoodReviewsOnlyUser();
    @endphp
    <div class="max-w-full overflow-x-hidden space-y-3 md:space-y-4">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Quản lý đánh giá</h2>
        <div class="flex flex-wrap items-center gap-2">
            @if($canImportReviews)
                <a href="{{ route('food.reviews.import') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 sm:w-auto sm:rounded-lg sm:px-3 sm:py-2 sm:text-xs sm:font-medium">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Nhập đánh giá
                </a>
            @endif
            @if($showReviewServerFilters && auth()->user()?->canAccessFoodReviewsSubpages())
                <a href="{{ route('food.reviews.gift-attempts') }}" class="hidden sm:inline-flex rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Lịch sử nhận quà</a>
            @endif
        </div>
    </div>

    <div @class([
        'sticky z-20 -mx-4 border-b border-gray-200/80 bg-white/95 px-4 py-2.5 backdrop-blur-md md:static md:mx-0 md:border-0 md:bg-transparent md:p-0 md:backdrop-blur-none dark:border-gray-800/80 dark:bg-gray-950/95 md:dark:bg-transparent',
        'top-[calc(3.75rem+env(safe-area-inset-top,0px))]' => $hasFoodMobileAppBar,
        'top-0 pt-[env(safe-area-inset-top)]' => ! $hasFoodMobileAppBar,
    ])>
        <form method="GET" class="@if($showReviewServerFilters) grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 md:grid-cols-6 dark:border-gray-700 dark:bg-gray-800/50 @else flex w-full gap-2 @endif">
            <input
                type="search"
                name="q"
                value="{{ $q }}"
                placeholder="Tìm mã: #..., FR-..., khách..."
                class="w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white @if($showReviewServerFilters) md:col-span-2 @endif"
            >
            @if($showReviewServerFilters)
            <select name="rating" class="hidden md:block rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Tất cả sao</option>
                @for($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected((string) $rating === (string) $i)>{{ $i }} sao</option>
                @endfor
            </select>
            <select name="food_branch_id" class="hidden md:block rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Tất cả chi nhánh</option>
                @foreach($branches as $br)
                    <option value="{{ $br->id }}" @selected((int) $branchId === (int) $br->id)>{{ $br->name }}</option>
                @endforeach
            </select>
            <input type="date" name="from_date" value="{{ $fromDate }}" class="hidden md:block rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <input type="date" name="to_date" value="{{ $toDate }}" class="hidden md:block rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <div class="flex gap-2 md:col-span-6">
                <button type="submit" class="hidden rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 md:inline-flex">Lọc</button>
                <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 md:hidden">Lọc</button>
            </div>
            @else
            <button type="submit" class="shrink-0 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Tìm</button>
            @endif
        </form>
    </div>

    <div class="min-w-0 space-y-2">
        @forelse($reviews as $r)
            <div class="review-item min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        <span class="break-all font-mono text-xs font-bold text-gray-900 dark:text-gray-100">{{ $r->review_code }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $r->review_time_text ?? ($r->review_date?->format('d/m/Y') ?? '—') }}</span>
                        @php
                            $displayRating = $r->displayRating();
                            $defaultFiveStar = $r->isDefaultFiveStarRating();
                        @endphp
                        <span class="inline-flex items-center gap-1 rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:border-amber-500/40 dark:bg-amber-900/30 dark:text-amber-200" title="{{ $defaultFiveStar ? '5 sao mặc định (chờ import xác nhận)' : ($displayRating.'/5') }}">
                                <span class="inline-flex items-center gap-px" aria-hidden="true">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="h-3 w-3 {{ $i <= $displayRating ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    @endfor
                                </span>
                        </span>
                        @if($defaultFiveStar)
                            <span class="rounded border border-dashed border-amber-400 bg-amber-50/80 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:border-amber-500/50 dark:bg-amber-900/20 dark:text-amber-200">Mặc định 5⭐</span>
                        @endif
                        @if($r->isGiftPendingVerification())
                            <span class="rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:border-amber-500/40 dark:bg-amber-900/30 dark:text-amber-200">Quà chờ xác minh</span>
                        @elseif($r->isGiftRevoked())
                            <span class="rounded border border-slate-300 bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">Quà đã huỷ</span>
                        @elseif($r->isGiftVerified() && !empty($r->gift_code))
                            <span class="rounded border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-900/30 dark:text-emerald-200">Quà đã xác minh</span>
                        @endif
                    </div>
                    <div class="shrink-0 text-right text-xs text-gray-600 dark:text-gray-300">
                        <p class="max-w-[8rem] truncate sm:max-w-none">{{ $r->branch?->name ?? '—' }}</p>
                    </div>
                </div>
                @if(!empty($r->customer_name))
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">Khách: <span class="font-medium text-gray-900 dark:text-gray-100">{{ $r->customer_name }}</span></p>
                @endif
                @if(!empty($r->gift_code))
                    <div class="mt-1 flex min-w-0 flex-wrap items-center gap-2 text-xs">
                        <span class="break-all rounded border border-brand-300 bg-brand-50 px-2 py-0.5 font-mono font-semibold text-brand-700 dark:border-brand-500/40 dark:bg-brand-900/30 dark:text-brand-200">{{ $r->gift_code }}</span>
                        @if(!empty($r->gift_item_name))
                            <span class="rounded border border-emerald-300 bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-900/30 dark:text-emerald-200">Tặng {{ $r->gift_item_name }}</span>
                        @endif
                        @if(($r->gift_status ?? 'chua_thuong') === 'chua_thuong' && $canMarkRewarded)
                            <form method="POST" action="{{ route('food.reviews.mark-rewarded', $r) }}" class="inline">
                                @csrf
                                <button type="submit" class="rounded border border-[#1877F2] bg-[#1877F2] px-2 py-0.5 font-medium text-white hover:bg-[#166FE5] dark:border-[#1877F2] dark:bg-[#1877F2] dark:text-white">Xác nhận thưởng</button>
                            </form>
                        @elseif(($r->gift_status ?? 'chua_thuong') === 'da_thuong')
                            <span class="inline-flex flex-wrap items-center gap-1.5">
                                <span class="rounded border border-emerald-300 bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-900/30 dark:text-emerald-200">Đã thưởng</span>
                                @if($r->rewardedByUser)
                                    @php
                                        $rewardedByName = trim((string) $r->rewardedByUser->name);
                                        $rewardedInitial = mb_strtoupper(mb_substr($rewardedByName !== '' ? $rewardedByName : 'U', 0, 1));
                                        $rewardedTitle = 'Xác nhận bởi '.$rewardedByName;
                                        if ($r->gift_rewarded_at) {
                                            $rewardedTitle .= ' · '.$r->gift_rewarded_at->format('d/m/Y H:i');
                                        }
                                    @endphp
                                    <span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-white px-1.5 py-0.5 dark:border-emerald-500/30 dark:bg-gray-900/40" title="{{ $rewardedTitle }}">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white">{{ $rewardedInitial }}</span>
                                        <span class="max-w-[8rem] truncate font-medium text-emerald-800 dark:text-emerald-200">{{ $rewardedByName }}</span>
                                    </span>
                                @endif
                                @if(auth()->user()?->is_admin)
                                    <form id="form-unmark-rewarded-{{ $r->id }}" method="POST" action="{{ route('food.reviews.unmark-rewarded', $r) }}" class="inline">
                                        @csrf
                                    </form>
                                    <button type="button"
                                        @click="$dispatch('confirm-delete-open', { formId: 'form-unmark-rewarded-{{ $r->id }}', message: 'Trả lại trạng thái chưa thưởng cho đánh giá {{ $r->review_code }}?' })"
                                        class="rounded border border-amber-300 bg-amber-50 px-2 py-0.5 font-medium text-amber-800 hover:bg-amber-100 dark:border-amber-500/40 dark:bg-amber-900/30 dark:text-amber-200 dark:hover:bg-amber-900/50">
                                        Hoàn tác
                                    </button>
                                @endif
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="py-6 text-center text-xs text-gray-500 dark:text-gray-400">{{ $q !== '' ? 'Không tìm thấy đánh giá phù hợp.' : 'Chưa có đánh giá.' }}</p>
        @endforelse
    </div>

    <div class="min-w-0 overflow-x-auto">
        {{ $reviews->links() }}
    </div>
</div>
@endsection

