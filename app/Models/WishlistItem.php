<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends Model
{
    use HasFactory, HasSpanishAliases;

    protected $table = 'favoritos';

    protected $fillable = [
        'user_id',
        'product_id',
        'producto_id',
    ];

    protected function aliases(): array
    {
        return [
            'product_id' => 'producto_id',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }
}
