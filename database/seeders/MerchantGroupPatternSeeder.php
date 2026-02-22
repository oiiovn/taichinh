<?php

namespace Database\Seeders;

use App\Models\MerchantGroupPattern;
use Illuminate\Database\Seeder;

class MerchantGroupPatternSeeder extends Seeder
{
    public function run(): void
    {
        $patterns = [
            [
                'name' => 'RZ GD (chuyển khoản ref)',
                'pattern_type' => MerchantGroupPattern::TYPE_REGEX,
                'pattern' => '/\bRZ[A-Z0-9]+\s+GD\b/i',
                'merchant_key' => 'rz_gd',
                'merchant_group' => 'bank_ref',
                'priority' => 100,
            ],
            [
                'name' => 'RZ + GD 6047...',
                'pattern_type' => MerchantGroupPattern::TYPE_REGEX,
                'pattern' => '/(?=.*\bRZ[A-Z0-9]+)(?=.*\bGD\s+\d{4}[A-Z0-9]+)/is',
                'merchant_key' => 'rz_gd',
                'merchant_group' => 'bank_ref',
                'priority' => 95,
            ],
            [
                'name' => 'IB RZ',
                'pattern_type' => MerchantGroupPattern::TYPE_REGEX,
                'pattern' => '/\bIB\s+RZ/i',
                'merchant_key' => 'ib_rz',
                'merchant_group' => 'bank_ref',
                'priority' => 90,
            ],
            [
                'name' => 'QR RZ',
                'pattern_type' => MerchantGroupPattern::TYPE_REGEX,
                'pattern' => '/\bQR\s*[-]?\s*RZ/i',
                'merchant_key' => 'qr_rz',
                'merchant_group' => 'bank_ref',
                'priority' => 85,
            ],
            [
                'name' => 'MBVCB',
                'pattern_type' => MerchantGroupPattern::TYPE_REGEX,
                'pattern' => '/\bMBVCB\./i',
                'merchant_key' => 'mbvcb',
                'merchant_group' => 'bank_ref',
                'priority' => 80,
            ],
            [
                'name' => 'FT26',
                'pattern_type' => MerchantGroupPattern::TYPE_REGEX,
                'pattern' => '/\bFT\s*26/i',
                'merchant_key' => 'ft_ref',
                'merchant_group' => 'bank_ref',
                'priority' => 75,
            ],
            [
                'name' => 'FT ref',
                'pattern_type' => MerchantGroupPattern::TYPE_REGEX,
                'pattern' => '/\bFT\d{2}\d+/i',
                'merchant_key' => 'ft_ref',
                'merchant_group' => 'bank_ref',
                'priority' => 74,
            ],
            [
                'name' => 'RZ ref dài',
                'pattern_type' => MerchantGroupPattern::TYPE_REGEX,
                'pattern' => '/\bRZ\s*\d+/i',
                'merchant_key' => 'rz_ref',
                'merchant_group' => 'bank_ref',
                'priority' => 70,
                'match_conditions' => ['min_length' => 20],
            ],
        ];

        foreach ($patterns as $p) {
            MerchantGroupPattern::updateOrCreate(
                [
                    'pattern' => $p['pattern'],
                    'pattern_type' => $p['pattern_type'],
                ],
                array_merge($p, ['is_active' => true])
            );
        }
    }
}
