@extends('layouts.app')

@section('title', 'NetBazar — бытовая техника, кресла, кулеры, химия и автоаксессуары в Казахстане')
@section('body-class', 'home-page')

@section('content')

{{-- ═══════════════════════════════════════ HERO ══════════════════════════════════════ --}}
<section class="hero" aria-label="Главный баннер">
    <div class="hero-inner">

        {{-- Left: text block --}}
        <div class="hero-left">
            <p class="hero-eyebrow">
                <span class="hero-eyebrow-line" aria-hidden="true"></span>
                МАГАЗИН NETBAZAR
            </p>

            <h1 class="hero-h1">
                Бытовая техника,<br>
                <em>кресла, кулеры,</em><br>
                химия и автоаксессуары
            </h1>

            <p class="hero-sub">
                Всё, что нужно для дома, офиса и вашего авто — в одном магазине.
            </p>

            <a href="{{ route('catalog.index') }}" class="hero-cta">
                Перейти в каталог <span aria-hidden="true">→</span>
            </a>
        </div>

        {{--
            Center: hero product collage.
            Drop hero image at: public/images/hero/collage.webp (or .jpg / .png)
            Spec: ~870px × 460px, transparent-bg WebP preferred.
            When absent, dark gradient placeholder is shown.
        --}}
        <div class="hero-collage" aria-hidden="true">
            @if($heroImage)
                <img class="hero-collage-img" src="{{ $heroImage }}" alt="">
            @else
                <div class="hero-collage-placeholder"></div>
            @endif

            {{-- Right overlay: quality/guarantee text — positioned inside collage --}}
            <div class="hero-right" aria-label="Преимущества">
                <ul>
                    <li>КАЧЕСТВО</li>
                    <li>ГАРАНТИЯ</li>
                    <li>ДОСТАВКА</li>
                    <li class="accent">ПО ВСЕМУ</li>
                    <li class="accent">КАЗАХСТАНУ</li>
                </ul>
            </div>
        </div>

    </div>
</section>

{{-- ════════════════════════════════ CATEGORY CARDS ══════════════════════════════════ --}}
<section class="categories-section" aria-label="Категории товаров">
    <div class="categories-inner">
        @foreach($categories as $cat)
        <a href="{{ route('catalog.show', ['slug' => $cat['slug']]) }}" class="cat-card" aria-label="{{ $cat['name'] }}">
            <div class="cat-card-image">
                @if($cat['photo'])
                    <img src="{{ $cat['photo'] }}" alt="{{ $cat['name'] }}" loading="lazy">
                @endif
            </div>
            <div class="cat-card-footer">
                <span class="cat-card-name">{{ $cat['name'] }}</span>
                <span class="cat-card-arrow" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                        <path d="M3 7h8M7 3l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
            </div>
        </a>
        @endforeach
    </div>
</section>

{{-- ════════════════════════════════ BENEFITS STRIP ══════════════════════════════════ --}}
<section class="benefits-strip" aria-label="Наши преимущества">
    <div class="benefits-inner">

        <div class="benefit-item">
            <span class="benefit-icon" aria-hidden="true">
                <svg width="34" height="34" viewBox="0 0 36 36" fill="none">
                    <path d="M3 24h22V10H3v14z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    <path d="M25 15h4l4 5v4h-8V15z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    <circle cx="9" cy="27" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                    <circle cx="28" cy="27" r="2.5" stroke="currentColor" stroke-width="1.4"/>
                </svg>
            </span>
            <span class="benefit-text">Доставка по всему<br>Казахстану</span>
        </div>

        <div class="benefit-divider" aria-hidden="true"></div>

        <div class="benefit-item">
            <span class="benefit-icon" aria-hidden="true">
                <svg width="34" height="34" viewBox="0 0 36 36" fill="none">
                    <path d="M18 4C12.477 4 8 8.477 8 14c0 8 10 18 10 18s10-10 10-18c0-5.523-4.477-10-10-10z" stroke="currentColor" stroke-width="1.6"/>
                    <circle cx="18" cy="14" r="3.5" stroke="currentColor" stroke-width="1.4"/>
                </svg>
            </span>
            <span class="benefit-text">Бесплатная доставка<br>в Алматы</span>
        </div>

        <div class="benefit-divider" aria-hidden="true"></div>

        <div class="benefit-item">
            <span class="benefit-icon" aria-hidden="true">
                <svg width="34" height="34" viewBox="0 0 36 36" fill="none">
                    <path d="M18 4L6 9v10c0 7.18 5.28 12.89 12 14 6.72-1.11 12-6.82 12-14V9L18 4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    <path d="M12 18l4 4 8-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <span class="benefit-text">Гарантия<br>на товары</span>
        </div>

        <div class="benefit-divider" aria-hidden="true"></div>

        <div class="benefit-item">
            <span class="benefit-icon" aria-hidden="true">
                <svg width="34" height="34" viewBox="0 0 36 36" fill="none">
                    <circle cx="18" cy="18" r="13" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M12 24l12-12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    <circle cx="13.5" cy="14" r="2.5" stroke="currentColor" stroke-width="1.3"/>
                    <circle cx="22.5" cy="22" r="2.5" stroke="currentColor" stroke-width="1.3"/>
                </svg>
            </span>
            <span class="benefit-text">Работаем<br>с юр. лицами</span>
        </div>

        <div class="benefit-divider" aria-hidden="true"></div>

        <div class="benefit-item">
            <span class="benefit-icon" aria-hidden="true">
                <svg width="34" height="34" viewBox="0 0 36 36" fill="none">
                    <rect x="8" y="5" width="20" height="26" rx="2" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M13 12h10M13 17h10M13 22h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="benefit-text">НДС и полный пакет<br>документов</span>
        </div>

        <div class="benefit-divider" aria-hidden="true"></div>

        <div class="benefit-item">
            <span class="benefit-icon" aria-hidden="true">
                <svg width="34" height="34" viewBox="0 0 36 36" fill="none">
                    <rect x="4" y="9" width="28" height="18" rx="3" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M4 15h28" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M10 22h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
            </span>
            <span class="benefit-text">Kaspi рассрочка<br>до 24 месяцев</span>
        </div>

    </div>
</section>

@endsection
