<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $primaryKey  = 'sku';
    protected $keyType     = 'string';
    public    $incrementing = false;
    public    $timestamps   = false;
    protected $table       = 'products';

    protected $fillable = [
        'sku',
        'source_offer_id',
        'name',
        'brand',
        'price_kzt',
        'quantity',
        'available',
        'source_category_id',
        'source_category_path',
        'source_description',
        'source_photos_json',
        'present',
        'url_slug',
        'site_category_override',
        'editorial_description',
        'editorial_photos_json',
        'source',
        'exclude_from_feed_sync',
        'last_imported_at',
    ];

    protected $casts = [
        'present'                => 'boolean',
        'available'              => 'boolean',
        'quantity'               => 'integer',
        'exclude_from_feed_sync' => 'boolean',
    ];

    public function manualCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            ManualCategory::class,
            'product_manual_categories',
            'sku',
            'manual_category_id'
        )->withPivot('sort_order');
    }
}
