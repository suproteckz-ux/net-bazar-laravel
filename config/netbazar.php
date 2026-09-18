<?php

return [

    // ── Kaspi integration ────────────────────────────────────────────────────
    // Original Python: kaspi.PARTNER_ID = 'Avtoximiya'
    // Used to construct merchant-specific URL: {kaspi_lookup.url}?m=Avtoximiya
    'kaspi_partner_id' => env('KASPI_PARTNER_ID', 'Avtoximiya'),

    // Feed URL for netbazar:catalog-import (the hourly MarketRadar Kaspi XML)
    'market_radar_feed_url' => env('MARKET_RADAR_FEED_URL', ''),

    // ── Home page category tiles ─────────────────────────────────────────────
    // category_id values are kaspi_categories.id from the live DB.
    // label and image are editorial (not from DB).
    'home_tiles' => [
        [
            'category_id' => '6bede016037a3d7e79338836',
            'label'       => 'Автотовары',
            'image'       => '/images/categories/auto.png',
        ],
        [
            'category_id' => '8d536ca215bb111d3f040ae0',
            'label'       => 'Кресла',
            'image'       => '/images/categories/chairs.png',
        ],
        [
            'category_id' => 'd71c72bf8bd183bc8aca5a01',
            'label'       => 'Товары для дома',
            'image'       => '/images/categories/coolers.png',
        ],
        [
            'category_id' => '978f1bd96be215ea108babf8',
            'label'       => 'Красота и здоровье',
            'image'       => '/images/categories/chem.png',
        ],
        [
            'category_id' => '34e9b5d0f75a8dd2a4437425',
            'label'       => 'Бытовая техника',
            'image'       => '/images/categories/tech.png',
        ],
    ],

    // ── Home page benefit strip ──────────────────────────────────────────────
    // Allowed icons: truck, map-pin, shield-check, briefcase, file-text, credit-card
    'benefits' => [
        [
            'icon' => 'truck',
            'text' => "Доставка\nпо Алматы",
        ],
        [
            'icon' => 'map-pin',
            'text' => "Самовывоз\nв Алматы",
        ],
        [
            'icon' => 'shield-check',
            'text' => "Официальная\nгарантия",
        ],
        [
            'icon' => 'briefcase',
            'text' => "Работаем\nс юр. лицами",
        ],
        [
            'icon' => 'file-text',
            'text' => "Документы\nи НДС",
        ],
    ],

];
