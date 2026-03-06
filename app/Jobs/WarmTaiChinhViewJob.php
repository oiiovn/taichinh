<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TaiChinh\TaiChinhIndexViewDataBuilder;
use App\Services\TaiChinh\TaiChinhViewCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WarmTaiChinhViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }
        try {
            $request = Request::create(route('tai-chinh', ['tab' => 'chien-luoc']), 'GET');
            $request->setUserResolver(fn () => $user);
            $viewData = app(TaiChinhIndexViewDataBuilder::class)->build($request);
            $ttl = TaiChinhViewCache::ttlWithJitter(TaiChinhViewCache::TTL_SECONDS);
            TaiChinhViewCache::putSafe(TaiChinhViewCache::key($this->userId), $viewData, $ttl);
            if (($viewData['insufficientData'] ?? true) === false) {
                TaiChinhViewCache::putStale($this->userId, $viewData);
            }
        } catch (\Throwable $e) {
            Log::warning('WarmTaiChinhViewJob failed: ' . $e->getMessage(), ['user_id' => $this->userId]);
        }
    }
}
