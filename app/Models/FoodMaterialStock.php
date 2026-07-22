<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodMaterialStock extends Model
{
    protected $table = 'food_material_stocks';

    protected $fillable = [
        'food_material_id',
        'food_branch_id',
        'stock_on_hand',
        'reorder_point',
    ];

    protected function casts(): array
    {
        return [
            'stock_on_hand' => 'decimal:4',
            'reorder_point' => 'decimal:4',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(FoodMaterial::class, 'food_material_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(FoodBranch::class, 'food_branch_id');
    }

    public function needsReorder(): bool
    {
        return (float) $this->stock_on_hand <= (float) $this->reorder_point;
    }

    public static function forMaterialBranch(int $materialId, int $branchId): self
    {
        return self::query()->firstOrCreate(
            [
                'food_material_id' => $materialId,
                'food_branch_id' => $branchId,
            ],
            [
                'stock_on_hand' => 0,
                'reorder_point' => 0,
            ]
        );
    }
}
