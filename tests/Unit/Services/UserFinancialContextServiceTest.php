<?php

use App\Models\User;
use App\Services\BankBalanceService;
use App\Services\UserCategorySyncService;
use App\Services\UserFinancialContextService;

test('getLinkedAccountNumbers với user null trả mảng rỗng', function () {
    $bankBalance = Mockery::mock(BankBalanceService::class);
    $categorySync = Mockery::mock(UserCategorySyncService::class);
    $service = new UserFinancialContextService($bankBalance, $categorySync);
    expect($service->getLinkedAccountNumbers(null))->toBe([]);
});

test('getContext với user null trả cấu trúc rỗng', function () {
    $bankBalance = Mockery::mock(BankBalanceService::class);
    $categorySync = Mockery::mock(UserCategorySyncService::class);
    $service = new UserFinancialContextService($bankBalance, $categorySync);
    $out = $service->getContext(null);
    expect($out)->toHaveKeys(['userBankAccounts', 'linkedAccountNumbers', 'accounts', 'accountBalances'])
        ->and($out['linkedAccountNumbers'])->toBe([])
        ->and($out['accountBalances'])->toBe([]);
});

test('getUserBankAccounts với user null trả collection rỗng', function () {
    $bankBalance = Mockery::mock(BankBalanceService::class);
    $categorySync = Mockery::mock(UserCategorySyncService::class);
    $service = new UserFinancialContextService($bankBalance, $categorySync);
    expect($service->getUserBankAccounts(null)->isEmpty())->toBeTrue();
});
