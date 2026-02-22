<?php

use App\Services\TaiChinh\FinancialPositionService;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

test('build trả đủ keys và net_leverage từ summary', function () {
    $service = app(FinancialPositionService::class);
    $summary = ['total_payable' => 10_000_000, 'total_receivable' => 5_000_000];
    $out = $service->build($summary, collect(), 20_000_000.0);
    expect($out)->toHaveKeys([
        'net_leverage', 'debt_exposure', 'receivable_exposure', 'liquid_balance',
        'risk_level', 'risk_label', 'risk_color',
    ])
        ->and($out['net_leverage'])->toBe(-5_000_000.0)
        ->and($out['debt_exposure'])->toBe(10_000_000.0)
        ->and($out['receivable_exposure'])->toBe(5_000_000.0)
        ->and($out['liquid_balance'])->toBe(20_000_000.0);
});

test('build risk_level nằm trong [low, medium, high]', function () {
    $service = app(FinancialPositionService::class);
    $out = $service->build(['total_payable' => 0, 'total_receivable' => 0], collect(), 0);
    expect($out['risk_level'])->toBeIn(['low', 'medium', 'high']);
});
