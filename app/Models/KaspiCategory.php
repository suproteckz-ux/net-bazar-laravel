<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KaspiCategory extends Model
{
    protected $table      = 'kaspi_categories';
    public    $timestamps = false;
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = ['id', 'parent_id', 'name', 'path', 'source_id'];

    public function override(): HasOne
    {
        return $this->hasOne(KaspiCategoryOverride::class, 'category_id');
    }
}
