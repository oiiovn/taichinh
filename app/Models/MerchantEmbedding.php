<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantEmbedding extends Model
{
    protected $table = 'merchant_embeddings';

    protected $fillable = [
        'merchant_key',
        'vector',
        'dimension',
    ];

    protected $casts = [
        'vector' => 'array',
        'dimension' => 'integer',
    ];
}
