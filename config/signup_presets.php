<?php

return [
    /*
    | Đăng ký qua URL có chữ ký (QR): key => thuộc tính user mặc định.
    | Preset food_thong_ke_buff: chỉ module Food + quyền Thống kê seeding (không gói, không admin).
    */
    'presets' => [
        'food_thong_ke_buff' => [
            'label' => 'Đăng ký — Food, chỉ Thống kê seeding',
            'redirect_route' => 'food.thong-ke-buff',
            'attributes' => [
                'allowed_features' => ['food'],
                'is_admin' => false,
                'plan' => null,
                'plan_expires_at' => null,
                'can_manage_food_tong_quan' => false,
                'can_manage_food_doanh_so' => false,
                'can_manage_food_san_pham' => false,
                'can_manage_food_bao_cao' => false,
                'can_manage_food_thong_ke_buff' => true,
                'can_manage_food_employees' => false,
                'can_manage_food_cham_cong' => false,
                'can_manage_food_xin_nghi' => false,
                'can_manage_food_ung_luong' => false,
                'can_manage_food_luong' => false,
                'food_buff_assigned_employees' => [],
                'can_use_food_employee' => false,
                'can_use_qr_cham_cong' => false,
            ],
        ],
    ],
];
