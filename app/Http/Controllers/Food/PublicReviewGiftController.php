<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodGiftConfig;
use App\Models\FoodReview;
use App\Models\FoodReviewGiftAttempt;
use App\Services\Food\FoodReviewGiftVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicReviewGiftController extends Controller
{
    public function __construct(
        private FoodReviewGiftVerificationService $verification
    ) {}

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

        if (! $this->verification->isValidOrderCodeFormat($normalized)) {
            $this->logAttempt($request, $inputCode, $normalized, null, FoodReviewGiftAttempt::RESULT_NOT_FOUND, 'Mã đơn không đúng định dạng.');

            return back()->withInput()->with('error', 'Mã đơn không đúng định dạng (ví dụ: #01046-444399361).');
        }

        $review = $this->verification->findOrCreateStub($normalized);

        if ($this->verification->isRevoked($review) && ! $review->hasConfirmedFiveStarRating()) {
            $this->logAttempt($request, $inputCode, $normalized, $review, FoodReviewGiftAttempt::RESULT_REVOKED, 'Quà đã bị huỷ do chưa xác minh đánh giá 5 sao.');

            return back()->withInput()->with('error', 'Không tìm thấy mã đơn 5 sao hợp lệ.');
        }

        if (! $this->verification->canGrantGift($review)) {
            if (($review->gift_status ?? 'chua_thuong') === 'da_thuong') {
                return $this->alreadyRewardedResponse($request, $inputCode, $normalized, $review);
            }

            $this->logAttempt($request, $inputCode, $normalized, $review, FoodReviewGiftAttempt::RESULT_NOT_FOUND, 'Không thể phát quà cho mã đơn này.');

            return back()->withInput()->with('error', 'Không tìm thấy mã đơn 5 sao hợp lệ.');
        }

        $giftConfig = FoodGiftConfig::getConfig();

        if ($this->verification->isExpired($review)) {
            return $this->expiredResponse($request, $inputCode, $normalized, $review, $giftConfig);
        }

        if (($review->gift_status ?? 'chua_thuong') === 'da_thuong') {
            return $this->alreadyRewardedResponse($request, $inputCode, $normalized, $review);
        }

        $renderCode = $review->gift_code;
        if (! is_string($renderCode) || ! preg_match('/^FR-\d{4}$/', $renderCode)) {
            $renderCode = $this->generateNumericGiftCode();
        }

        if (! $review->gift_rendered_at) {
            $review->gift_code = $renderCode;
            $review->gift_item_name = (string) $giftConfig->item_name;
            $review->gift_status = 'chua_thuong';
            $review->gift_rendered_at = now();
            $this->verification->markPending($review);
            $review->save();
        } elseif (empty($review->gift_item_name)) {
            $review->gift_item_name = (string) $giftConfig->item_name;
            $review->save();
            $renderCode = $review->gift_code ?? $renderCode;
        } else {
            $renderCode = $review->gift_code ?? $renderCode;
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

    private function expiredResponse(
        Request $request,
        string $inputCode,
        string $normalized,
        FoodReview $review,
        FoodGiftConfig $giftConfig
    ): RedirectResponse {
        $expireAt = $this->verification->giftExpireAt($review);

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
            ->with('gift_expire_date', $expireAt?->format('d/m/Y'));
    }

    private function alreadyRewardedResponse(
        Request $request,
        string $inputCode,
        string $normalized,
        FoodReview $review
    ): RedirectResponse {
        $giftConfig = FoodGiftConfig::getConfig();

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
