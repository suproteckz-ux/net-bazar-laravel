<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'sku',
        'name',
        'brand',
        'price_kzt',
        'qty',
        'subtotal_kzt',
    ];

    protected function casts(): array
    {
        return [
            'price_kzt'    => 'decimal:2',
            'subtotal_kzt' => 'decimal:2',
            'qty'          => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
