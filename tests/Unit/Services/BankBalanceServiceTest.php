<?php

use App\Services\BankBalanceService;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

test('getBalancesForAccountNumbers với mảng rỗng trả mảng rỗng', function () {
    $service = app(BankBalanceService::class);
    expect($service->getBalancesForAccountNumbers([]))->toBe([]);
});

test('getPay2sAccountsForAccountNumbers với mảng rỗng trả collection rỗng', function () {
    $service = app(BankBalanceService::class);
    $out = $service->getPay2sAccountsForAccountNumbers([]);
    expect($out)->toBeInstanceOf(Collection::class)
        ->and($out->isEmpty())->toBeTrue();
});

test('getBalancesForPay2sAccounts với collection rỗng trả mảng rỗng', function () {
    $service = app(BankBalanceService::class);
    expect($service->getBalancesForPay2sAccounts(collect()))->toBe([]);
});
