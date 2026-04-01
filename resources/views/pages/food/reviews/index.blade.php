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
<div class="space-y-4" x-data="{ q: '', hasMatch() { const items = this.$root.querySelectorAll('.review-item'); return Array.from(items).some((el) => el.style.display !== 'none'); } }">
    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Quản lý đánh giá</h2>
        @if(auth()->user()?->is_admin)
            <a href="{{ route('food.reviews.import') }}" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700">Nhập đánh giá</a>
        @endif
    </div>

    <form method="GET" class="grid grid-cols-1 gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 md:grid-cols-6 dark:border-gray-700 dark:bg-gray-800/50">
        <input type="text" x-model.debounce.120ms="q" placeholder="Tìm tức thì: #..., FR-..., khách..." class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm md:col-span-2 dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <select name="rating" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <option value="">Tất cả sao</option>
            @for($i = 5; $i >= 1; $i--)
                <option value="{{ $i }}" @selected((string) $rating === (string) $i)>{{ $i }} sao</option>
            @endfor
        </select>
        <select name="food_branch_id" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            <option value="">Tất cả chi nhánh</option>
            @foreach($branches as $br)
                <option value="{{ $br->id }}" @selected((int) $branchId === (int) $br->id)>{{ $br->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from_date" value="{{ $fromDate }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <input type="date" name="to_date" value="{{ $toDate }}" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <div class="md:col-span-6">
            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Lọc</button>
        </div>
    </form>

    <div class="space-y-2">
        @forelse($reviews as $r)
            @php
                $searchText = mb_strtolower(trim(implode(' ', [
                    (string) ($r->review_code ?? ''),
                    (string) ($r->gift_code ?? ''),
                    (string) ($r->customer_name ?? ''),
                    (string) ($r->review_content ?? ''),
                    (string) ($r->branch?->name ?? ''),
                ])));
            @endphp
            <div x-show="!q || ($el.dataset.search || '').includes(q.toLowerCase())" data-search="{{ $searchText }}" class="review-item rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-xs font-bold text-gray-900 dark:text-gray-100">{{ $r->review_code }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $r->review_time_text ?? ($r->review_date?->format('d/m/Y') ?? '—') }}</span>
                        @php $displayRating = (int) ($r->rating ?? 5); @endphp
                        <span class="inline-flex items-center gap-1 rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:border-amber-500/40 dark:bg-amber-900/30 dark:text-amber-200" title="{{ (float) $displayRating }}/5">
                                <span class="inline-flex items-center gap-px" aria-hidden="true">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="h-3 w-3 {{ $i <= (int) round((float) $displayRating) ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600' }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.176 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    @endfor
                                </span>
                        </span>
                    </div>
                    <div class="text-right text-xs text-gray-600 dark:text-gray-300">
                        <p>{{ $r->branch?->name ?? '—' }}</p>
                    </div>
                </div>
                @if(!empty($r->customer_name))
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">Khách: <span class="font-medium text-gray-900 dark:text-gray-100">{{ $r->customer_name }}</span></p>
                @endif
                @if(!empty($r->gift_code))
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded border border-brand-300 bg-brand-50 px-2 py-0.5 font-mono font-semibold text-brand-700 dark:border-brand-500/40 dark:bg-brand-900/30 dark:text-brand-200">{{ $r->gift_code }}</span>
                        @if(!empty($r->gift_item_name))
                            <span class="rounded border border-emerald-300 bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-900/30 dark:text-emerald-200">Tặng {{ $r->gift_item_name }}</span>
                        @endif
                        @if(($r->gift_status ?? 'chua_thuong') === 'chua_thuong' && auth()->user()?->is_admin)
                            <form method="POST" action="{{ route('food.reviews.mark-rewarded', $r) }}" class="inline">
                                @csrf
                                <button type="submit" class="rounded border border-[#1877F2] bg-[#1877F2] px-2 py-0.5 font-medium text-white hover:bg-[#166FE5] dark:border-[#1877F2] dark:bg-[#1877F2] dark:text-white">Xác nhận thưởng</button>
                            </form>
                        @else
                            <span class="rounded border border-emerald-300 bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-900/30 dark:text-emerald-200">Đã thưởng</span>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="py-6 text-center text-xs text-gray-500 dark:text-gray-400">Chưa có đánh giá.</p>
        @endforelse
        <p x-show="q && !hasMatch()" class="py-4 text-center text-xs text-gray-500 dark:text-gray-400">Không có kết quả phù hợp.</p>
    </div>

    <div>
        {{ $reviews->links() }}
    </div>
</div>
@endsection

