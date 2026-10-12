<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KaspiCategoryOverride extends Model
{
    protected $table        = 'kaspi_category_overrides';
    protected $primaryKey   = 'category_id';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'category_id',
        'display_name',
        'display_order',
        'seo_title',
        'meta_description',
        'h1',
        'seo_text',
        'custom_image',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(KaspiCategory::class, 'category_id');
    }
}
