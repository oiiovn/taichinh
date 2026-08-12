<?php

namespace App\Services\Food;

use App\Models\FoodReview;
use App\Models\User;
use Carbon\Carbon;

class FoodReviewGiftVerificationService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REVOKED = 'revoked';

    public const VERIFY_HOURS = 48;

    public function isValidOrderCodeFormat(string $normalized): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{4,}-[A-Z0-9-]{4,}$/u', $normalized);
    }

    public function normalizeReviewCode(string $inputCode): string
    {
        return '#'.strtoupper(ltrim(trim($inputCode), '#'));
    }

    public function findByNormalizedCode(string $normalized): ?FoodReview
    {
        return FoodReview::query()
            ->whereRaw("REPLACE(UPPER(review_code), '#', '') = ?", [$normalized])
            ->first();
    }

    public function findOrCreateStub(string $normalized): FoodReview
    {
        $existing = $this->findByNormalizedCode($normalized);
        if ($existing) {
            return $existing;
        }

        $reviewCode = $this->normalizeReviewCode($normalized);

        return FoodReview::query()->create([
            'user_id' => $this->systemUserId(),
            'review_code' => $reviewCode,
            'rating' => null,
            'rating_confirmed' => false,
        ]);
    }

    public function isRevoked(FoodReview $review): bool
    {
        return ($review->gift_verification_status ?? null) === self::STATUS_REVOKED;
    }

    public function canGrantGift(FoodReview $review): bool
    {
        if (($review->gift_status ?? 'chua_thuong') === 'da_thuong') {
            return false;
        }

        if ($this->isRevoked($review)) {
            return $review->hasConfirmedFiveStarRating();
        }

        return true;
    }

    public function giftExpireAt(FoodReview $review): ?Carbon
    {
        if ($review->review_date) {
            return Carbon::parse($review->review_date)->addDays(7)->endOfDay();
        }

        if ($review->gift_rendered_at) {
            return $review->gift_rendered_at->copy()->addDays(7)->endOfDay();
        }

        return null;
    }

    public function isExpired(FoodReview $review): bool
    {
        $expireAt = $this->giftExpireAt($review);

        return $expireAt !== null && now()->gt($expireAt);
    }

    public function markPending(FoodReview $review): void
    {
        if ($review->hasConfirmedFiveStarRating()) {
            $this->markVerified($review);

            return;
        }

        $review->gift_verification_status = self::STATUS_PENDING;
        $review->gift_verified_at = null;
        $review->gift_revoked_at = null;
    }

    public function markVerified(FoodReview $review): void
    {
        $review->gift_verification_status = self::STATUS_VERIFIED;
        $review->gift_verified_at = now();
        $review->gift_revoked_at = null;
        $review->save();
    }

    public function revokeGift(FoodReview $review): bool
    {
        if (($review->gift_status ?? 'chua_thuong') === 'da_thuong') {
            return false;
        }

        if (($review->gift_verification_status ?? null) === self::STATUS_REVOKED) {
            return false;
        }

        $review->gift_verification_status = self::STATUS_REVOKED;
        $review->gift_revoked_at = now();
        $review->gift_verified_at = null;
        $review->gift_code = null;
        $review->gift_item_name = null;
        $review->gift_rendered_at = null;
        $review->save();

        return true;
    }

    /**
     * @return array{verified: int, revoked: int}
     */
    public function processDueVerifications(): array
    {
        $verified = 0;
        $revoked = 0;
        $deadline = now()->subHours(self::VERIFY_HOURS);

        FoodReview::query()
            ->where('gift_verification_status', self::STATUS_PENDING)
            ->whereNotNull('gift_rendered_at')
            ->where('gift_rendered_at', '<=', $deadline)
            ->where(function ($q) {
                $q->whereNull('gift_status')->orWhere('gift_status', '!=', 'da_thuong');
            })
            ->orderBy('id')
            ->chunkById(100, function ($reviews) use (&$verified, &$revoked) {
                foreach ($reviews as $review) {
                    if ($review->hasConfirmedFiveStarRating()) {
                        $this->markVerified($review);
                        $verified++;
                    } elseif ($this->revokeGift($review)) {
                        $revoked++;
                    }
                }
            });

        return compact('verified', 'revoked');
    }

    public function syncAfterImport(FoodReview $review): void
    {
        if (($review->gift_status ?? 'chua_thuong') === 'da_thuong') {
            return;
        }

        $rating = $review->rating;

        if ($rating !== null && $rating !== 5 && $review->gift_rendered_at) {
            $this->revokeGift($review);

            return;
        }

        if ($rating === 5) {
            $review->rating_confirmed = true;
            $review->save();

            if ($review->gift_rendered_at) {
                $status = $review->gift_verification_status ?? null;
                if (in_array($status, [self::STATUS_PENDING, self::STATUS_REVOKED], true)) {
                    $this->markVerified($review);
                }
            }
        }
    }

    private function systemUserId(): int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = (int) (User::query()->where('is_admin', true)->orderBy('id')->value('id')
            ?? User::query()->orderBy('id')->value('id')
            ?? 1);

        return $cached;
    }
}
