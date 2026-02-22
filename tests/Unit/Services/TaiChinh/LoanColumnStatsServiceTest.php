<?php

use App\Services\TaiChinh\LoanColumnStatsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->service = app(LoanColumnStatsService::class);
});

test('build với collection rỗng trả tổng 0 và nearest_due null', function () {
    $out = $this->service->build(collect());
    expect($out['total_principal'])->toBe(0)
        ->and($out['total_unpaid_interest'])->toBe(0)
        ->and($out['nearest_due'])->toBeNull()
        ->and($out['nearest_due_name'])->toBeNull()
        ->and($out['avg_interest_rate_year'])->toBe(0.0);
});

test('build với một item có outstanding và interest', function () {
    $entity = (object) ['interest_rate' => 12, 'interest_unit' => 'yearly'];
    $item = (object) [
        'outstanding' => 100_000_000,
        'unpaid_interest' => 2_000_000,
        'due_date' => Carbon::today()->addDays(30),
        'name' => 'Khoản A',
        'entity' => $entity,
    ];
    $items = collect([$item]);
    $out = $this->service->build($items);
    expect($out['total_principal'])->toEqual(100_000_000)
        ->and($out['total_unpaid_interest'])->toEqual(2_000_000)
        ->and($out['nearest_due_name'])->toBe('Khoản A')
        ->and($out['avg_interest_rate_year'])->toBe(12.0);
});

test('getNearestDueInWindow với item ngoài window trả null', function () {
    $item = (object) ['due_date' => Carbon::today()->addDays(200)];
    $items = collect([$item]);
    expect($this->service->getNearestDueInWindow($items, 90))->toBeNull();
});
