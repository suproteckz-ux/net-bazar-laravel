<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'parent_id', 'h1', 'description',
        'seo_title', 'seo_description', 'image_path',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_manual_categories',
            'manual_category_id',
            'sku'
        )->withPivot('sort_order');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ManualCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ManualCategory::class, 'parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
