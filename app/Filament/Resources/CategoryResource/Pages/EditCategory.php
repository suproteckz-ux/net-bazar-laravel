<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\KaspiCategoryOverride;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * Merge override fields into form data before the form is populated.
     * The $data array starts with KaspiCategory attributes (id, name, path, …).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $override = KaspiCategoryOverride::find($data['id']);

        if ($override) {
            $data['display_name']     = $override->display_name;
            $data['display_order']    = $override->display_order;
            $data['seo_title']        = $override->seo_title;
            $data['meta_description'] = $override->meta_description;
            $data['h1']               = $override->h1;
            $data['seo_text']         = $override->seo_text;
            $data['custom_image']     = $override->custom_image;
        }

        return $data;
    }

    /**
     * Write only to kaspi_category_overrides. Never update kaspi_categories.
     */
    protected function handleRecordUpdate(
        \Illuminate\Database\Eloquent\Model $record,
        array $data
    ): \Illuminate\Database\Eloquent\Model {
        KaspiCategoryOverride::updateOrCreate(
            ['category_id' => $record->id],
            [
                'display_name'     => $data['display_name']     ?? null,
                'display_order'    => isset($data['display_order']) && $data['display_order'] !== ''
                                        ? (int) $data['display_order']
                                        : null,
                'seo_title'        => $data['seo_title']        ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'h1'               => $data['h1']               ?? null,
                'seo_text'         => $data['seo_text']         ?? null,
                'custom_image'     => $data['custom_image']     ?? null,
            ]
        );

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
