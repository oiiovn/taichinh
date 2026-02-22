<?php

/**
 * Feature test UserFinancialContextService với DB.
 * Cần chạy migrate đầy đủ (vd: php artisan migrate --env=testing) trước khi chạy.
 */
use App\Models\User;
use App\Models\UserBankAccount;
use App\Services\UserFinancialContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('getLinkedAccountNumbers với user có 1 tài khoản liên kết trả đúng STK', function () {
    $user = User::factory()->create();
    UserBankAccount::create([
        'user_id' => $user->id,
        'bank_code' => 'BIDV',
        'account_type' => 'ca_nhan',
        'api_type' => 'pay2s',
        'account_number' => ' 1234567890 ',
    ]);

    $service = app(UserFinancialContextService::class);
    $linked = $service->getLinkedAccountNumbers($user);

    expect($linked)->toHaveCount(1)
        ->and($linked[0])->toBe('1234567890');
});

test('getContext với user không có tài khoản trả linkedAccountNumbers rỗng và accountBalances rỗng', function () {
    $user = User::factory()->create();
    $service = app(UserFinancialContextService::class);
    $out = $service->getContext($user);

    expect($out['linkedAccountNumbers'])->toBe([])
        ->and($out['accountBalances'])->toBe([])
        ->and($out['userBankAccounts'])->toHaveCount(0);
});

test('getContext với user có 2 STK trả linkedAccountNumbers 2 phần tử', function () {
    $user = User::factory()->create();
    UserBankAccount::create([
        'user_id' => $user->id,
        'bank_code' => 'BIDV',
        'account_type' => 'ca_nhan',
        'api_type' => 'pay2s',
        'account_number' => '111',
    ]);
    UserBankAccount::create([
        'user_id' => $user->id,
        'bank_code' => 'ACB',
        'account_type' => 'ca_nhan',
        'api_type' => 'pay2s',
        'account_number' => '222',
    ]);

    $service = app(UserFinancialContextService::class);
    $out = $service->getContext($user);

    expect($out['linkedAccountNumbers'])->toHaveCount(2)
        ->and($out['linkedAccountNumbers'])->toContain('111')
        ->and($out['linkedAccountNumbers'])->toContain('222');
});
