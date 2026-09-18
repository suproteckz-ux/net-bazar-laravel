<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'status',
        'customer_name',
        'phone',
        'city',
        'comment',
        'subtotal_kzt',
        'total_kzt',
        'internal_note',
    ];

    protected function casts(): array
    {
        return [
            'status'       => OrderStatus::class,
            'subtotal_kzt' => 'decimal:2',
            'total_kzt'    => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Set order_number from auto-increment id immediately after insert
        static::created(function (Order $order) {
            $order->updateQuietly([
                'order_number' => 'NB-' . now()->format('Ymd') . '-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
