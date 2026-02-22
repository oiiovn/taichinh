<?php

namespace App\Console\Commands;

use App\Services\ForecastLearningService;
use Illuminate\Console\Command;

class ForecastLearnCommand extends Command
{
    protected $signature = 'forecast:learn';

    protected $description = 'Cập nhật forecast_error cho snapshot 30+ ngày (actual vs projected).';

    public function handle(ForecastLearningService $service): int
    {
        $this->info('Đang cập nhật forecast_error...');
        $service->runForAllUsers();
        $this->info('Xong.');
        return self::SUCCESS;
    }
}
