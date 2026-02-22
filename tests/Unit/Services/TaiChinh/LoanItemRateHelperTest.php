<?php

use App\Services\TaiChinh\LoanItemRateHelper;

test('annualRate yearly trả đúng lãi suất năm', function () {
    $entity = (object) ['interest_rate' => 12, 'interest_unit' => 'yearly'];
    expect(LoanItemRateHelper::annualRate($entity))->toBe(12.0);
});

test('annualRate monthly quy đổi ra năm', function () {
    $entity = (object) ['interest_rate' => 1, 'interest_unit' => 'monthly'];
    expect(LoanItemRateHelper::annualRate($entity))->toBe(12.0);
});

test('annualRate daily quy đổi ra năm', function () {
    $entity = (object) ['interest_rate' => 0.1, 'interest_unit' => 'daily'];
    expect(LoanItemRateHelper::annualRate($entity))->toBe(36.5);
});

test('annualRate mặc định unit yearly khi thiếu', function () {
    $entity = (object) ['interest_rate' => 10];
    expect(LoanItemRateHelper::annualRate($entity))->toBe(10.0);
});

test('annualRate entity không có interest_rate trả 0', function () {
    $entity = (object) ['interest_unit' => 'yearly'];
    expect(LoanItemRateHelper::annualRate($entity))->toBe(0.0);
});
