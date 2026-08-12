<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodBranch;
use App\Models\FoodReview;
use App\Models\FoodReviewGiftAttempt;
use App\Services\Food\FoodReviewGiftVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FoodReviewController extends Controller
{
    public function markRewarded(Request $request, FoodReview $review): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin && ! $user->canManageFoodReviews()) {
            abort(403, 'Bạn không có quyền cập nhật trạng thái thưởng.');
        }

        $review->update([
            'gift_status' => 'da_thuong',
            'gift_rewarded_by_user_id' => $user->id,
            'gift_rewarded_at' => now(),
        ]);

        return back()->with('success', 'Đã xác nhận thưởng.');
    }

    public function unmarkRewarded(Request $request, FoodReview $review): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin) {
            abort(403, 'Chỉ admin mới được trả lại trạng thái thưởng.');
        }

        $review->update([
            'gift_status' => 'chua_thuong',
            'gift_rewarded_by_user_id' => null,
            'gift_rewarded_at' => null,
        ]);

        return back()->with('success', 'Đã trả lại trạng thái chưa thưởng.');
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->is_admin && ! $user->canManageFoodReviews()) {
            abort(403, 'Bạn không có quyền xem đánh giá seeding.');
        }

        $q = trim((string) $request->input('q', ''));
        $rating = $request->input('rating');
        $branchId = $request->input('food_branch_id');
        $branchId = $branchId !== null && $branchId !== '' ? (int) $branchId : null;
        $from = $request->input('from_date');
        $to = $request->input('to_date');

        $query = FoodReview::query()->with(['branch', 'rewardedByUser'])->orderByDesc('review_date')->orderByDesc('id');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('review_code', 'like', "%{$q}%")
                    ->orWhere('gift_code', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('review_content', 'like', "%{$q}%");
            });
        }
        if ($rating !== null && $rating !== '') {
            $query->where('rating', (int) $rating);
        }
        if ($branchId) {
            $query->where('food_branch_id', $branchId);
        }
        if ($from) {
            $query->whereDate('review_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('review_date', '<=', $to);
        }

        $reviews = $query->paginate(30)->appends($request->query());
        $branches = FoodBranch::query()->orderBy('name')->get();

        return view('pages.food.reviews.index', [
            'title' => 'Quản lý đánh giá',
            'reviews' => $reviews,
            'branches' => $branches,
            'q' => $q,
            'rating' => $rating,
            'branchId' => $branchId,
            'fromDate' => $from,
            'toDate' => $to,
        ]);
    }

    public function giftAttempts(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->canAccessFoodReviewsSubpages()) {
            abort(403, 'Bạn không có quyền xem lịch sử nhận quà.');
        }

        $q = trim((string) $request->input('q', ''));
        $result = trim((string) $request->input('result', ''));
        $from = $request->input('from_date');
        $to = $request->input('to_date');

        $query = FoodReviewGiftAttempt::query()
            ->with(['review.branch'])
            ->where('result', '!=', FoodReviewGiftAttempt::RESULT_PAGE_OPEN)
            ->orderByDesc('id');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('order_code_input', 'like', "%{$q}%")
                    ->orWhere('order_code_normalized', 'like', "%{$q}%")
                    ->orWhere('gift_code', 'like', "%{$q}%")
                    ->orWhere('ip_address', 'like', "%{$q}%");
            });
        }
        if ($result !== '' && array_key_exists($result, FoodReviewGiftAttempt::trackedResultLabels())) {
            $query->where('result', $result);
        }
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $allAttempts = $query->get();
        $groups = $this->groupGiftAttemptsByIp($allAttempts);
        $dailyStats = $this->buildGiftAttemptDailyStats($allAttempts);

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;
        $groupsPage = new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $groups->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('pages.food.reviews.gift-attempts', [
            'title' => 'Lịch sử QR nhận quà',
            'groups' => $groupsPage,
            'resultLabels' => FoodReviewGiftAttempt::trackedResultLabels(),
            'q' => $q,
            'result' => $result,
            'fromDate' => $from,
            'toDate' => $to,
            'dailyStats' => $dailyStats,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FoodReviewGiftAttempt>  $attempts
     * @return \Illuminate\Support\Collection<int, array{
     *     key: string,
     *     ip: ?string,
     *     attempts: \Illuminate\Support\Collection<int, FoodReviewGiftAttempt>,
     *     count: int,
     *     primary: FoodReviewGiftAttempt,
     *     latest_at: \Carbon\Carbon|null,
     *     has_success: bool
     * }>
     */
    protected function groupGiftAttemptsByIp($attempts)
    {
        return $attempts
            ->groupBy(fn (FoodReviewGiftAttempt $a) => filled($a->ip_address) ? $a->ip_address : '__no_ip__')
            ->map(function ($items, $ipKey) {
                $sorted = $items->sortByDesc(fn (FoodReviewGiftAttempt $a) => $a->created_at?->timestamp ?? 0)->values();
                $success = $sorted->firstWhere('result', FoodReviewGiftAttempt::RESULT_SUCCESS);
                $primary = $success ?? $sorted->first();

                return [
                    'key' => md5((string) $ipKey),
                    'ip' => $ipKey === '__no_ip__' ? null : (string) $ipKey,
                    'attempts' => $sorted,
                    'count' => $sorted->count(),
                    'primary' => $primary,
                    'latest_at' => $sorted->max('created_at'),
                    'has_success' => $success !== null,
                ];
            })
            ->sortByDesc(fn (array $g) => $g['latest_at']?->timestamp ?? 0)
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FoodReviewGiftAttempt>  $attempts
     * @return array{
     *     days: list<string>,
     *     dayLabels: list<string>,
     *     rows: array<string, array<string, int>>,
     *     series: list<array{name: string, key: string, data: list<int>}>,
     *     totals: array<string, int>,
     *     grandTotal: int,
     *     successRate: float|null,
     *     uniqueIps: int,
     *     resultColors: array<string, string>
     * }
     */
    protected function buildGiftAttemptDailyStats($attempts): array
    {
        $resultKeys = array_keys(FoodReviewGiftAttempt::trackedResultLabels());
        $resultColors = [
            FoodReviewGiftAttempt::RESULT_NOT_FOUND => '#9ca3af',
            FoodReviewGiftAttempt::RESULT_EXPIRED => '#f59e0b',
            FoodReviewGiftAttempt::RESULT_ALREADY_REWARDED => '#ef4444',
            FoodReviewGiftAttempt::RESULT_REVOKED => '#64748b',
            FoodReviewGiftAttempt::RESULT_SUCCESS => '#10b981',
        ];

        $rows = [];
        $uniqueIps = [];

        foreach ($attempts as $attempt) {
            $day = $attempt->created_at?->format('Y-m-d');
            if (! $day) {
                continue;
            }

            if (! isset($rows[$day])) {
                $rows[$day] = array_fill_keys($resultKeys, 0);
                $rows[$day]['total'] = 0;
            }

            $result = $attempt->result;
            if (array_key_exists($result, $rows[$day])) {
                $rows[$day][$result]++;
            }
            $rows[$day]['total']++;

            if (filled($attempt->ip_address)) {
                $uniqueIps[$attempt->ip_address] = true;
            }
        }

        ksort($rows);
        $days = array_keys($rows);
        $dayLabels = array_map(
            fn (string $d) => \Carbon\Carbon::parse($d)->format('d/m'),
            $days
        );

        $series = [];
        foreach ($resultKeys as $key) {
            $series[] = [
                'name' => FoodReviewGiftAttempt::resultLabels()[$key],
                'key' => $key,
                'data' => array_map(fn (string $d) => (int) ($rows[$d][$key] ?? 0), $days),
            ];
        }

        $totals = array_fill_keys($resultKeys, 0);
        $grandTotal = 0;
        foreach ($rows as $row) {
            foreach ($resultKeys as $key) {
                $totals[$key] += (int) ($row[$key] ?? 0);
            }
            $grandTotal += (int) ($row['total'] ?? 0);
        }

        $successes = (int) ($totals[FoodReviewGiftAttempt::RESULT_SUCCESS] ?? 0);
        $successRate = $grandTotal > 0 ? round($successes / $grandTotal * 100, 1) : null;

        return [
            'days' => $days,
            'dayLabels' => $dayLabels,
            'rows' => $rows,
            'series' => $series,
            'totals' => $totals,
            'grandTotal' => $grandTotal,
            'successRate' => $successRate,
            'uniqueIps' => count($uniqueIps),
            'resultColors' => $resultColors,
        ];
    }

    public function showImport(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Vui lòng đăng nhập.');
        }
        if (! $user->canManageFoodReviews()) {
            abort(403, 'Bạn không có quyền nhập đánh giá.');
        }

        return view('pages.food.reviews.import', [
            'title' => 'Nhập đánh giá',
            'branches' => FoodBranch::query()->orderBy('name')->get(),
            'branchId' => $request->input('food_branch_id'),
        ]);
    }

    public function importText(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canManageFoodReviews()) {
            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        $validated = $request->validate([
            'reviews_text' => ['required', 'string', 'max:200000'],
            'food_branch_id' => ['nullable', 'integer', Rule::exists('food_branches', 'id')],
        ], [
            'reviews_text.required' => 'Vui lòng dán nội dung đánh giá.',
        ]);

        $branchId = isset($validated['food_branch_id']) && $validated['food_branch_id'] !== ''
            ? (int) $validated['food_branch_id']
            : null;

        $rows = $this->parseReviewsFromText((string) $validated['reviews_text']);
        if ($rows === []) {
            return redirect()->route('food.reviews.import')->with('error', 'Không tìm thấy mã đánh giá dạng #xxxx-xxxx.');
        }

        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $model = FoodReview::query()->firstOrNew(['review_code' => $row['review_code']]);
            $isNew = ! $model->exists;

            $model->fill([
                'user_id' => $model->user_id ?: $user->id,
                'food_branch_id' => $branchId ?: $model->food_branch_id,
                'review_date' => $row['review_date'] ?: $model->review_date,
                'review_time_text' => $row['review_time_text'] ?: $model->review_time_text,
                'customer_name' => $row['customer_name'] ?: $model->customer_name,
                'rating' => $row['rating'] ?? $model->rating ?? 5,
                'rating_confirmed' => true,
                'review_content' => $row['review_content'] ?: $model->review_content,
                'raw_chunk' => $row['raw_chunk'] ?: $model->raw_chunk,
            ]);
            $model->save();

            app(FoodReviewGiftVerificationService::class)->syncAfterImport($model->fresh());

            $isNew ? $created++ : $updated++;
        }

        return redirect()->route('food.reviews.import')
            ->with('success', "Đã nhập đánh giá: thêm mới {$created}, cập nhật {$updated}.");
    }

    /**
     * @return array<int, array{review_code:string,review_date:?string,review_time_text:?string,customer_name:?string,rating:?int,review_content:?string,raw_chunk:?string}>
     */
    private function parseReviewsFromText(string $text): array
    {
        preg_match_all('/#([A-Za-z0-9]{4,}-[A-Za-z0-9-]{4,})/u', $text, $matches, PREG_OFFSET_CAPTURE);
        if (empty($matches[0])) {
            return [];
        }

        $rows = [];
        $all = $matches[0];
        $count = count($all);
        for ($i = 0; $i < $count; $i++) {
            $code = $all[$i][0];
            $start = (int) $all[$i][1];
            $end = $i + 1 < $count ? (int) $all[$i + 1][1] : strlen($text);
            $chunk = trim(substr($text, $start, $end - $start));
            if ($chunk === '') {
                continue;
            }

            $reviewTimeText = null;
            if (preg_match('/\b(\d{1,2}\/\d{1,2}\/\d{4}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?)\b/u', $chunk, $m)) {
                $reviewTimeText = trim($m[1]);
            }
            $reviewDate = null;
            if ($reviewTimeText && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/u', $reviewTimeText, $d)) {
                $reviewDate = sprintf('%04d-%02d-%02d', (int) $d[3], (int) $d[2], (int) $d[1]);
            }

            $customerName = null;
            if (preg_match('/(?:khách(?:\s*hàng)?|người\s*mua)\s*[:\-]\s*([^\n\r]+)/iu', $chunk, $m)) {
                $customerName = trim($m[1]);
            }

            $rating = null;
            if (preg_match('/(?<!\d)([1-5])(?:\.0)?(?!\d)/u', $chunk, $m)) {
                $rating = (int) $m[1];
            } elseif (preg_match('/\b([1-5])\s*sao\b/iu', $chunk, $m)) {
                $rating = (int) $m[1];
            }
            if ($rating === null) {
                // Dữ liệu seeding hiện tại đều là 5 sao, fallback để tránh bản ghi bị trống sao.
                $rating = 5;
            }

            $reviewContent = null;
            if (preg_match('/(?:đánh\s*giá|nhận\s*xét|nội\s*dung)\s*[:\-]\s*([\s\S]+)/iu', $chunk, $m)) {
                $reviewContent = trim($m[1]);
            }
            if ($reviewContent === null) {
                $lines = preg_split('/\R/u', $chunk) ?: [];
                $clean = [];
                foreach ($lines as $line) {
                    $line = trim((string) $line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}/u', $line)) {
                        continue;
                    }
                    if (preg_match('/\b[1-5]\s*sao\b/iu', $line)) {
                        continue;
                    }
                    if (preg_match('/^(khách(?:\s*hàng)?|người\s*mua)\s*[:\-]/iu', $line)) {
                        continue;
                    }
                    $clean[] = $line;
                }
                $joined = trim(implode(' ', $clean));
                $reviewContent = $joined !== '' ? $joined : null;
            }

            $normalized = '#'.strtoupper(ltrim($code, '#'));
            $rows[$normalized] = [
                'review_code' => $normalized,
                'review_date' => $reviewDate,
                'review_time_text' => $reviewTimeText,
                'customer_name' => $customerName,
                'rating' => $rating,
                'review_content' => $reviewContent ? mb_substr($reviewContent, 0, 5000) : null,
                'raw_chunk' => mb_substr($chunk, 0, 5000),
            ];
        }

        return array_values($rows);
    }
}

