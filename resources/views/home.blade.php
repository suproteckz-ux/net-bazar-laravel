@extends('layouts.app')

@section('title', 'NetBazar — бытовая техника, кресла, кулеры, химия и автоаксессуары в Казахстане')

@section('content')

{{-- ══════════════════════════ HERO ══════════════════════════════════════════ --}}
<section class="nb-hero" aria-label="Главный баннер">

    {{-- Copy block: static on mobile (appears first), absolute on desktop --}}
    <div class="nb-hero__copy">
        <div class="nb-hero__kicker">
            <i aria-hidden="true"></i>
            <span>МАГАЗИН NETBAZAR</span>
        </div>
        <h1 class="nb-hero__title">
            Бытовая техника,<br>
            <em>кресла, кулеры,</em><br>
            химия и автоаксессуары
        </h1>
        <p class="nb-hero__lead">
            Всё, что нужно для дома, офиса и вашего авто — в одном магазине.
        </p>
        <div class="nb-hero__cta">
            <a href="{{ route('catalog.index') }}" class="nb-btn nb-btn--primary">
                Перейти в каталог
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M3 8h10M8 3l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
        </div>
    </div>

    {{-- Hero art: static on mobile (appears below copy), absolute on desktop --}}
    @if($heroImage)
        <img class="nb-hero__art" src="{{ $heroImage }}" alt="" aria-hidden="true">
    @endif

    {{-- Gradient overlays (desktop/tablet) --}}
    <div class="nb-hero__fade-l" aria-hidden="true"></div>
    <div class="nb-hero__fade-r" aria-hidden="true"></div>
    <div class="nb-hero__fade-b" aria-hidden="true"></div>

    {{-- Right claims stack (desktop only) --}}
    <div class="nb-hero__claims" aria-hidden="true">
        <i></i>
        <ul>
            <li>КАЧЕСТВО</li>
            <li>ГАРАНТИЯ</li>
            <li>ДОСТАВКА</li>
            <li class="is-dim">ПО ВСЕМУ</li>
            <li class="is-dim">КАЗАХСТАНУ</li>
        </ul>
    </div>

</section>

{{-- ════════════════════ MARKETING CATEGORY TILES ════════════════════════════ --}}
<nav class="nb-tiles" aria-label="Категории товаров">
    @foreach($tiles as $tile)
        <a href="{{ route('catalog.index', ['category' => $tile['category_id']]) }}"
           class="nb-tile" aria-label="{{ $tile['label'] }}">
            <img class="nb-tile__art" src="{{ $tile['image'] }}" alt="{{ $tile['label'] }}" loading="lazy">
            <div class="nb-tile__foot">
                <span class="nb-tile__label">{{ $tile['label'] }}</span>
                <span class="nb-tile__go" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M3 8h10M8 3l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </div>
        </a>
    @endforeach
</nav>

{{-- ════════════════════════ BENEFITS STRIP ══════════════════════════════════ --}}
<section class="nb-benefits" aria-label="Преимущества">
    @foreach($benefits as $benefit)
        <div class="nb-benefit">
            <svg width="26" height="26" viewBox="0 0 36 36" fill="none" stroke="currentColor" aria-hidden="true">
                @switch($benefit['icon'])
                    @case('truck')
                        <path d="M3 24h22V10H3v14z" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M25 15h4l4 5v4h-8V15z" stroke-width="1.6" stroke-linejoin="round"/>
                        <circle cx="9" cy="27" r="2.5" stroke-width="1.4"/>
                        <circle cx="28" cy="27" r="2.5" stroke-width="1.4"/>
                        @break
                    @case('map-pin')
                        <path d="M18 4C12.477 4 8 8.477 8 14c0 8 10 18 10 18s10-10 10-18c0-5.523-4.477-10-10-10z" stroke-width="1.6"/>
                        <circle cx="18" cy="14" r="3.5" stroke-width="1.4"/>
                        @break
                    @case('shield-check')
                        <path d="M18 4L6 9v10c0 7.18 5.28 12.89 12 14 6.72-1.11 12-6.82 12-14V9L18 4z" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M12 18l4 4 8-8" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        @break
                    @case('briefcase')
                        <rect x="6" y="13" width="24" height="17" rx="2" stroke-width="1.6"/>
                        <path d="M13 13V9a2 2 0 012-2h6a2 2 0 012 2v4" stroke-width="1.6"/>
                        <path d="M6 21h24" stroke-width="1.4" stroke-linecap="round"/>
                        @break
                    @case('file-text')
                        <rect x="8" y="5" width="20" height="26" rx="2" stroke-width="1.6"/>
                        <path d="M13 12h10M13 17h10M13 22h6" stroke-width="1.4" stroke-linecap="round"/>
                        @break
                    @case('credit-card')
                        <rect x="4" y="9" width="28" height="18" rx="3" stroke-width="1.6"/>
                        <path d="M4 15h28" stroke-width="1.4"/>
                        <path d="M10 22h4" stroke-width="1.4" stroke-linecap="round"/>
                        @break
                @endswitch
            </svg>
            <span>{!! nl2br(e($benefit['text'])) !!}</span>
        </div>
    @endforeach
</section>

@endsection
