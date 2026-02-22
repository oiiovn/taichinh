<?php

use App\Services\TaiChinh\PositionRiskScoringService;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->service = app(PositionRiskScoringService::class);
});

test('score với unifiedLoans rỗng và summary cân bằng trả low', function () {
    $summary = ['total_payable' => 0, 'total_receivable' => 0];
    $out = $this->service->score($summary, collect());
    expect($out['risk_level'])->toBe('low')
        ->and($out['risk_label'])->toBe('Thấp')
        ->and($out['risk_color'])->toBe('green');
});

test('score với net âm lớn (vượt ngưỡng warning) tăng raw_score', function () {
    config(['financial_brain.position_risk.leverage_vnd.warning' => 10_000_000]);
    $summary = ['total_payable' => 50_000_000, 'total_receivable' => 0];
    $out = $this->service->score($summary, collect());
    expect($out)->toHaveKeys(['risk_level', 'risk_label', 'risk_color', 'raw_score'])
        ->and($out['raw_score'])->toBeGreaterThan(0);
});

test('score trả đúng keys', function () {
    $summary = ['total_payable' => 0, 'total_receivable' => 0];
    $out = $this->service->score($summary, collect());
    expect($out)->toHaveKeys(['risk_level', 'risk_label', 'risk_color', 'raw_score']);
});
