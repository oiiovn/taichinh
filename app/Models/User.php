<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
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
            'allowed_features' => 'array',
            'behavior_events_consent' => 'boolean',
            'low_balance_threshold' => 'integer',
            'threshold_metrics_computed_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
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

    public function broadcasts(): BelongsToMany
    {
        return $this->belongsToMany(Broadcast::class, 'broadcast_user')
            ->withPivot('read_at')
            ->withTimestamps();
    }

}
