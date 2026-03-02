<?php

namespace App\Services\Classification;

use App\Models\ClassificationAccuracyBySource;
use App\Models\GlobalMerchantPattern;
use App\Models\UserRecurringPattern;

class ClassificationAccuracyRecorder
{
    public function recordUsage(int $userId, string $source, ?int $globalPatternId = null, ?int $recurringPatternId = null): void
    {
        $row = ClassificationAccuracyBySource::firstOrCreate(
            ['user_id' => $userId, 'source' => $source],
            ['usage_count' => 0, 'wrong_count' => 0]
        );
        $row->increment('usage_count');

        if ($globalPatternId !== null) {
            $p = GlobalMerchantPattern::find($globalPatternId);
            if ($p) {
                $p->increment('usage_count');
                $p->update(['last_used_at' => now()]);
                $p->update(['confidence_score' => $p->getConfidenceFromAccuracy()]);
            }
        }

        if ($recurringPatternId !== null) {
            $p = UserRecurringPattern::find($recurringPatternId);
            if ($p) {
                $p->increment('match_count');
                $p->update(['last_seen_at' => now()]);
            }
        }
    }

    public function recordWrong(int $userId, string $source, ?int $globalPatternId = null): void
    {
        $row = ClassificationAccuracyBySource::where('user_id', $userId)->where('source', $source)->first();
        if ($row) {
            $row->increment('wrong_count');
        } else {
            ClassificationAccuracyBySource::create([
                'user_id' => $userId,
                'source' => $source,
                'usage_count' => 0,
                'wrong_count' => 1,
            ]);
        }

        if ($globalPatternId !== null) {
            $p = GlobalMerchantPattern::find($globalPatternId);
            if ($p) {
                $p->increment('wrong_count');
                $p->update(['last_wrong_at' => now()]);
                $p->update(['confidence_score' => $p->getConfidenceFromAccuracy()]);
            }
        }
    }
}
