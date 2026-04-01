<?php

namespace App\Http\Controllers\Food;

use App\Http\Controllers\Controller;
use App\Models\FoodGiftConfig;
use App\Models\FoodReview;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicReviewGiftController extends Controller
{
    public function show(): View
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
            return back()->withInput()->with('error', 'Không tìm thấy mã đơn 5 sao hợp lệ.');
        }

        $giftConfig = FoodGiftConfig::getConfig();
        if ($review->review_date) {
            $expireAt = Carbon::parse($review->review_date)->addDays(7)->endOfDay();
            if (now()->gt($expireAt)) {
                return back()
                    ->with('gift_expired_popup', true)
                    ->with('gift_code', $review->gift_code)
                    ->with('gift_item_name', $review->gift_item_name ?: (string) $giftConfig->item_name)
                    ->with('gift_branch_name', $review->branch?->name ?? null)
                    ->with('gift_expire_date', $expireAt->format('d/m/Y'));
            }
        }

        if (($review->gift_status ?? 'chua_thuong') === 'da_thuong') {
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
        return back()
            ->with('gift_popup', true)
            ->with('gift_code', $renderCode)
            ->with('gift_item_name', (string) $giftConfig->item_name)
            ->with('gift_item_value', (int) $giftConfig->item_value)
            ->with('gift_item_image', $giftConfig->item_image_path ? asset('storage/'.$giftConfig->item_image_path) : null)
            ->with('gift_branch_name', $review->branch?->name ?? null)
            ->with('gift_branch_link', $review->branch?->branch_link ?? null);
    }

    private function generateNumericGiftCode(): string
    {
        do {
            $code = 'FR-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $exists = FoodReview::query()->where('gift_code', $code)->exists();
        } while ($exists);

        return $code;
    }
}

