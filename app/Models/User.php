<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'password_plain',
        'is_admin',
        'can_manage_food_employees',
        'can_manage_food_cham_cong',
        'can_manage_food_xin_nghi',
        'can_manage_food_ung_luong',
        'can_manage_food_luong',
        'can_manage_food_tong_quan',
        'can_manage_food_doanh_so',
        'can_manage_food_san_pham',
        'can_manage_food_bao_cao',
        'can_manage_food_thong_ke_buff',
        'can_create_food_buff_order',
        'can_manage_food_reviews',
        'can_use_food_employee',
        'can_use_qr_cham_cong',
        'allowed_features',
        'behavior_events_consent',
        'low_balance_threshold',
        'balance_change_amount_threshold',
        'spend_spike_ratio',
        'week_anomaly_pct',
        'volatility_score_income',
        'volatility_score_expense',
        'avg_transaction_size',
        'median_daily_spend',
        'income_stability_index',
        'threshold_metrics_computed_at',
        'plan',
        'plan_expires_at',
        'food_tongquan_settings',
        'food_buff_assigned_employees',
        'phone',
        'bio',
        'facebook_url',
        'x_url',
        'linkedin_url',
        'instagram_url',
        'country',
        'city_state',
        'postal_code',
        'tax_id',
        'avatar',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }
        return asset('storage/' . $this->avatar);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_plain',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'can_manage_food_employees' => 'boolean',
            'can_manage_food_cham_cong' => 'boolean',
            'can_manage_food_xin_nghi' => 'boolean',
            'can_manage_food_ung_luong' => 'boolean',
            'can_manage_food_luong' => 'boolean',
            'can_manage_food_tong_quan' => 'boolean',
            'can_manage_food_doanh_so' => 'boolean',
            'can_manage_food_san_pham' => 'boolean',
            'can_manage_food_bao_cao' => 'boolean',
            'can_manage_food_thong_ke_buff' => 'boolean',
            'can_create_food_buff_order' => 'boolean',
            'can_manage_food_reviews' => 'boolean',
            'can_use_food_employee' => 'boolean',
            'can_use_qr_cham_cong' => 'boolean',
            'allowed_features' => 'array',
            'behavior_events_consent' => 'boolean',
            'low_balance_threshold' => 'integer',
            'threshold_metrics_computed_at' => 'datetime',
            'food_tongquan_settings' => 'array',
            'food_buff_assigned_employees' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function canManageFoodEmployees(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_employees;
    }

    public function canManageFoodChamCong(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_cham_cong;
    }

    public function canManageFoodXinNghi(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_xin_nghi;
    }

    public function canManageFoodUngLuong(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_ung_luong;
    }

    public function canManageFoodLuong(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_luong;
    }

    /** Được dùng phần nhân viên (chấm công, xin nghỉ, ứng lương, lương của tôi). Cần có bản ghi Employee + quyền này. */
    public function canUseFoodEmployee(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_use_food_employee;
    }

    /** Chỉ được mở trang QR chấm công (hiển thị mã QR cho nhân viên quét). */
    public function canUseQrChamCong(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_use_qr_cham_cong;
    }

    public function canManageFoodTongQuan(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_tong_quan;
    }

    public function canManageFoodDoanhSo(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_doanh_so;
    }

    public function canManageFoodSanPham(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_san_pham;
    }

    public function canManageFoodBaoCao(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_bao_cao;
    }

    public function canManageFoodThongKeBuff(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_thong_ke_buff;
    }

    public function canCreateFoodBuffOrder(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_create_food_buff_order;
    }

    public function canManageFoodReviews(): bool
    {
        return (bool) $this->is_admin || (bool) $this->can_manage_food_reviews;
    }

    public function getFoodBuffAssignedEmployees(): array
    {
        $raw = $this->food_buff_assigned_employees;
        if (! is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $name) {
            if (! is_string($name)) {
                continue;
            }
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $result[] = $name;
        }

        return array_values(array_unique($result));
    }

    /** Có ít nhất một quyền quản lý Food (để vào được khu Food và thấy menu). */
    public function canManageAnyFood(): bool
    {
        return (bool) $this->is_admin
            || (bool) $this->can_manage_food_tong_quan
            || (bool) $this->can_manage_food_doanh_so
            || (bool) $this->can_manage_food_san_pham
            || (bool) $this->can_manage_food_bao_cao
            || (bool) $this->can_manage_food_thong_ke_buff
            || (bool) $this->can_create_food_buff_order
            || (bool) $this->can_manage_food_reviews
            || (bool) $this->can_manage_food_employees
            || (bool) $this->can_manage_food_cham_cong
            || (bool) $this->can_manage_food_xin_nghi
            || (bool) $this->can_manage_food_ung_luong
            || (bool) $this->can_manage_food_luong;
    }

    public function isFoodThongKeBuffOnlyUser(): bool
    {
        return ! $this->is_admin
            && $this->canManageFoodThongKeBuff()
            && ! $this->canCreateFoodBuffOrder()
            && ! $this->canManageFoodTongQuan()
            && ! $this->canManageFoodDoanhSo()
            && ! $this->canManageFoodSanPham()
            && ! $this->canManageFoodBaoCao()
            && ! $this->canManageFoodReviews()
            && ! $this->canManageFoodEmployees()
            && ! $this->canManageFoodChamCong()
            && ! $this->canManageFoodXinNghi()
            && ! $this->canManageFoodUngLuong()
            && ! $this->canManageFoodLuong()
            && ! $this->canUseQrChamCong();
    }

    public function isFoodBuffOrderOnlyUser(): bool
    {
        return ! $this->is_admin
            && $this->canCreateFoodBuffOrder()
            && ! $this->canManageFoodThongKeBuff()
            && ! $this->canManageFoodTongQuan()
            && ! $this->canManageFoodDoanhSo()
            && ! $this->canManageFoodSanPham()
            && ! $this->canManageFoodBaoCao()
            && ! $this->canManageFoodReviews()
            && ! $this->canManageFoodEmployees()
            && ! $this->canManageFoodChamCong()
            && ! $this->canManageFoodXinNghi()
            && ! $this->canManageFoodUngLuong()
            && ! $this->canManageFoodLuong()
            && ! $this->canUseFoodEmployee()
            && ! $this->canUseQrChamCong();
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Kiểm tra user có được cấp quyền dùng tính năng không.
     * null = chưa cấu hình (tương thích cũ) → cho phép tất cả.
     * [] = đã cấu hình nhưng không bật gì → không được dùng.
     */
    public function canUseFeature(string $key): bool
    {
        $allowed = $this->allowed_features;
        if ($allowed === null || ! is_array($allowed)) {
            return true;
        }
        return in_array($key, $allowed, true);
    }

    public function userBankAccounts(): HasMany
    {
        return $this->hasMany(UserBankAccount::class);
    }

    public function userCategories(): HasMany
    {
        return $this->hasMany(UserCategory::class);
    }

    public function userTongquanStatistics(): HasMany
    {
        return $this->hasMany(UserTongquanStatistic::class)->orderBy('created_at', 'desc');
    }

    public function userLiabilities(): HasMany
    {
        return $this->hasMany(UserLiability::class);
    }

    public function loanContractsAsLender(): HasMany
    {
        return $this->hasMany(LoanContract::class, 'lender_user_id');
    }

    public function loanContractsAsBorrower(): HasMany
    {
        return $this->hasMany(LoanContract::class, 'borrower_user_id');
    }

    public function tribeosGroupMembers(): HasMany
    {
        return $this->hasMany(TribeosGroupMember::class);
    }

    public function tribeosGroups(): BelongsToMany
    {
        return $this->belongsToMany(TribeosGroup::class, 'tribeos_group_members', 'user_id', 'tribeos_group_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tribeosInvitationsReceived(): HasMany
    {
        return $this->hasMany(TribeosGroupInvitation::class, 'invitee_user_id');
    }

    public function tribeosPosts(): HasMany
    {
        return $this->hasMany(TribeosPost::class);
    }

    public function incomeSources(): HasMany
    {
        return $this->hasMany(IncomeSource::class);
    }

    public function estimatedIncomes(): HasMany
    {
        return $this->hasMany(EstimatedIncome::class);
    }

    public function estimatedExpenses(): HasMany
    {
        return $this->hasMany(EstimatedExpense::class);
    }

    public function ownedHouseholds(): HasMany
    {
        return $this->hasMany(Household::class, 'owner_user_id');
    }

    public function householdMembers(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_members', 'user_id', 'household_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function broadcasts(): BelongsToMany
    {
        return $this->belongsToMany(Broadcast::class, 'broadcast_user')
            ->withPivot('read_at')
            ->withTimestamps();
    }

}
