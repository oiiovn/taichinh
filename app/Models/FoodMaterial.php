<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodMaterial extends Model
{
    public const TYPE_NGUYEN_LIEU = 'nguyen_lieu';

    public const TYPE_BAO_BI = 'bao_bi';

    protected $table = 'food_materials';

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'type',
        'unit',
        'order_qty',
        'last_unit_cost',
        'note',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'order_qty' => 'decimal:4',
            'last_unit_cost' => 'integer',
            'active' => 'boolean',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_NGUYEN_LIEU => 'Nguyên liệu',
            self::TYPE_BAO_BI => 'Bao bì',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(FoodMaterialStock::class, 'food_material_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(FoodProductRecipe::class, 'food_material_id');
    }

    public function templateItems(): HasMany
    {
        return $this->hasMany(FoodRecipeTemplateItem::class, 'food_material_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FoodMaterialStockMovement::class, 'food_material_id')->orderByDesc('id');
    }

    /** Tồn / điểm ĐH của chi nhánh đang xem (gắn từ controller qua setRelation('branchStock', …)). */
    public function branchStockQty(): float
    {
        return (float) ($this->branchStock?->stock_on_hand ?? 0);
    }

    public function branchReorderPoint(): float
    {
        return (float) ($this->branchStock?->reorder_point ?? 0);
    }

    public function needsReorder(): bool
    {
        if ($this->relationLoaded('branchStock') && $this->branchStock) {
            return $this->branchStock->needsReorder();
        }

        return false;
    }

    public function isStockChecked(): bool
    {
        return $this->branchStock?->stock_checked_at !== null;
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }
}
