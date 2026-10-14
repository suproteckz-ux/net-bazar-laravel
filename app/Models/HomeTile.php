<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeTile extends Model
{
    protected $fillable = [
        'label',
        'image_path',
        'image_alt',
        'link_type',
        'kaspi_category_id',
        'manual_category_id',
        'custom_url',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function manualCategory(): BelongsTo
    {
        return $this->belongsTo(ManualCategory::class);
    }

    public function resolveUrl(): ?string
    {
        return match ($this->link_type) {
            'kaspi_category'  => $this->kaspi_category_id
                ? '/catalog?category=' . rawurlencode($this->kaspi_category_id)
                : null,
            'manual_category' => $this->manualCategory?->is_active
                ? '/category/' . $this->manualCategory->slug
                : null,
            'custom_url'      => $this->safeCustomUrl(),
            default           => null,
        };
    }

    public function imageUrl(): string
    {
        $path = $this->image_path ?? '';
        if (!$path) {
            return '';
        }
        // Already an absolute public path (legacy /images/… or full URL)
        if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }
        // Relative path from Filament FileUpload stored in public disk
        return '/storage/' . $path;
    }

    private function safeCustomUrl(): ?string
    {
        $url = trim($this->custom_url ?? '');
        if (!$url) {
            return null;
        }
        // Allow only root-relative paths — no protocol, no external hosts, no //
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }
        return null;
    }
}
