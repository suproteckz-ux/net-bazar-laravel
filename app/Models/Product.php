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

    // Fields safe to edit in the admin without breaking the import pipeline.
    // present, site_category_override, editorial_* are not overwritten by catalog_sync.
    // name/brand/price_kzt ARE overwritten on next hourly import — shown with warning in form.
    protected $fillable = [
        'present',
        'site_category_override',
        'editorial_description',
        'editorial_photos_json',
    ];

    protected $casts = [
        'present'   => 'boolean',
        'available' => 'boolean',
        'quantity'  => 'integer',
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
