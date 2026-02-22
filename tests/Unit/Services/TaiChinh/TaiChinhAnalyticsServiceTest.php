<?php

use App\Services\TaiChinh\TaiChinhAnalyticsService;

beforeEach(function () {
    $this->service = new TaiChinhAnalyticsService;
});

test('strategySummary tính avg_thu, avg_chi, net_avg, burn_ratio', function () {
    $monthlyResult = ['summary' => ['avg_thu' => 50_000_000, 'avg_chi' => 30_000_000]];
    $out = $this->service->strategySummary($monthlyResult, 12);
    expect($out['avg_thu'])->toBe(50_000_000.0)
        ->and($out['avg_chi'])->toBe(30_000_000.0)
        ->and($out['net_avg'])->toBe(20_000_000.0)
        ->and($out['burn_ratio'])->toBe(60.0)
        ->and($out['months'])->toBe(12);
});

test('strategySummary burn_ratio null khi avg_thu = 0', function () {
    $monthlyResult = ['summary' => ['avg_thu' => 0, 'avg_chi' => 10]];
    $out = $this->service->strategySummary($monthlyResult, 6);
    expect($out['burn_ratio'])->toBeNull();
});

test('healthStatus trả danger khi burn_ratio > 100', function () {
    $summary = [];
    $strategySummary = ['burn_ratio' => 110, 'net_avg' => 0];
    $out = $this->service->healthStatus($summary, $strategySummary);
    expect($out['key'])->toBe('danger')
        ->and($out['label'])->toBe('Nguy cơ thâm hụt cấu trúc');
});

test('healthStatus trả warning khi burn 70–100 hoặc net_avg âm', function () {
    $strategySummary = ['burn_ratio' => 85, 'net_avg' => 0];
    expect($this->service->healthStatus([], $strategySummary)['key'])->toBe('warning');

    $strategySummary2 = ['burn_ratio' => 50, 'net_avg' => -1_000_000];
    expect($this->service->healthStatus([], $strategySummary2)['key'])->toBe('warning');
});

test('healthStatus trả stable khi ổn định', function () {
    $strategySummary = ['burn_ratio' => 60, 'net_avg' => 5_000_000];
    $out = $this->service->healthStatus([], $strategySummary);
    expect($out['key'])->toBe('stable')
        ->and($out['label'])->toBe('Tài chính ổn định');
});
