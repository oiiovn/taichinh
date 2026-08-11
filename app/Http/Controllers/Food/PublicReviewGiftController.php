<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodGiftConfig;
use App\Models\FoodReview;
use App\Models\FoodReviewGiftAttempt;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicReviewGiftController extends Controller
{
    public function show(Request $request): View
    {
        return view('pages.food.public-review-gift', [
            'title' => 'Nhận quà đánh giá 5 sao',
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_code' => ['required', 'string', 'max:80'],
        ], [
            'order_code.required' => 'Vui lòng nhập mã đơn hàng.',
        ]);

        $inputCode = strtoupper(trim((string) $validated['order_code']));
        $normalized = ltrim($inputCode, '#');

        $review = FoodReview::query()
            ->whereRaw("REPLACE(UPPER(review_code), '#', '') = ?", [$normalized])
            ->where('rating', 5)
            ->first();

        if (! $review) {
            $this->logAttempt($request, $inputCode, $normalized, null, FoodReviewGiftAttempt::RESULT_NOT_FOUND, 'Không tìm thấy mã đơn 5 sao hợp lệ.');

            return back()->withInput()->with('error', 'Không tìm thấy mã đơn 5 sao hợp lệ.');
        }

        $giftConfig = FoodGiftConfig::getConfig();
        if ($review->review_date) {
            $expireAt = Carbon::parse($review->review_date)->addDays(7)->endOfDay();
            if (now()->gt($expireAt)) {
                $this->logAttempt(
                    $request,
                    $inputCode,
                    $normalized,
                    $review,
                    FoodReviewGiftAttempt::RESULT_EXPIRED,
                    'Mã quà đã hết hạn (sau 7 ngày kể từ ngày đánh giá).',
                    $review->gift_code
                );

                return back()
                    ->with('gift_expired_popup', true)
                    ->with('gift_code', $review->gift_code)
                    ->with('gift_item_name', $review->gift_item_name ?: (string) $giftConfig->item_name)
                    ->with('gift_branch_name', $review->branch?->name ?? null)
                    ->with('gift_expire_date', $expireAt->format('d/m/Y'));
            }
        }

        if (($review->gift_status ?? 'chua_thuong') === 'da_thuong') {
            $this->logAttempt(
                $request,
                $inputCode,
                $normalized,
                $review,
                FoodReviewGiftAttempt::RESULT_ALREADY_REWARDED,
                'Mã đơn đã được thưởng trước đó.',
                $review->gift_code
            );

            return back()
                ->with('gift_used_popup', true)
                ->with('gift_code', $review->gift_code)
                ->with('gift_item_name', $review->gift_item_name ?: (string) $giftConfig->item_name)
                ->with('gift_branch_name', $review->branch?->name ?? null);
        }

        $renderCode = $review->gift_code;
        if (! is_string($renderCode) || ! preg_match('/^FR-\d{4}$/', $renderCode)) {
            $renderCode = $this->generateNumericGiftCode();
            $review->gift_code = $renderCode;
        }

        if (! $review->gift_rendered_at) {
            $review->gift_code = $renderCode;
            $review->gift_item_name = (string) $giftConfig->item_name;
            $review->gift_status = 'chua_thuong';
            $review->gift_rendered_at = now();
            $review->save();
        } elseif (empty($review->gift_item_name)) {
            $review->gift_item_name = (string) $giftConfig->item_name;
            $review->save();
        }

        $this->logAttempt(
            $request,
            $inputCode,
            $normalized,
            $review,
            FoodReviewGiftAttempt::RESULT_SUCCESS,
            'Hiển thị mã quà thành công.',
            $renderCode
        );

        return back()
            ->with('gift_popup', true)
            ->with('gift_code', $renderCode)
            ->with('gift_item_name', (string) $giftConfig->item_name)
            ->with('gift_item_value', (int) $giftConfig->item_value)
            ->with('gift_item_image', $giftConfig->item_image_path ? asset('storage/'.$giftConfig->item_image_path) : null)
            ->with('gift_branch_name', $review->branch?->name ?? null)
            ->with('gift_branch_link', $review->branch?->branch_link ?? null);
    }

    private function logAttempt(
        Request $request,
        string $inputCode,
        string $normalized,
        ?FoodReview $review,
        string $result,
        string $message,
        ?string $giftCode = null
    ): void {
        FoodReviewGiftAttempt::query()->create([
            'order_code_input' => $inputCode !== '' ? $inputCode : '(mở link QR)',
            'order_code_normalized' => $normalized !== '' ? $normalized : null,
            'food_review_id' => $review?->id,
            'result' => $result,
            'result_message' => $message,
            'gift_code' => $giftCode,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
        ]);
    }

    private function generateNumericGiftCode(): string
    {
        do {
            $code = 'FR-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $exists = FoodReview::query()->where('gift_code', $code)->exists();
        } while ($exists);

        return $code;
    }
}
