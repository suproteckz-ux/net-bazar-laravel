<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'NetBazar — бытовая техника, кресла, кулеры, химия и автоаксессуары')</title>

    {{-- Fonts: Montserrat (display) + Manrope (text), Cyrillic subset --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Montserrat:wght@700;800;900&display=swap&subset=cyrillic" rel="stylesheet">

    <link rel="stylesheet" href="/css/netbazar.css">
</head>
<body>

{{-- ═══════════════════════ DESKTOP HEADER (≥1280px) ═══════════════════════════ --}}
<header class="nb-header" role="banner">
    <a href="{{ route('home') }}" class="nb-logo" aria-label="NetBazar — главная">NET<span>BAZAR</span></a>

    <nav class="nb-nav" aria-label="Основная навигация">
        <a href="{{ route('catalog.index') }}">Каталог
            <svg width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true" style="display:inline;margin-left:5px;vertical-align:middle;color:var(--nb-accent)">
                <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
        <a href="#">О компании</a>
        <a href="#">Доставка и оплата</a>
        <a href="#">Контакты</a>
    </nav>

    <div class="nb-header-actions">
        <form class="nb-search" action="{{ route('catalog.index') }}" method="GET" role="search">
            <input type="search" name="q" placeholder="Поиск товаров..." aria-label="Поиск по каталогу"
                   value="{{ request('q', '') }}">
            <button type="submit" aria-label="Найти">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                    <circle cx="7.5" cy="7.5" r="5.5" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M12 12l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>
        </form>

        <a href="/account" class="nb-icon-link" aria-label="Личный кабинет">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2.2">
                <circle cx="11" cy="8" r="3.5"/><path d="M4 19c0-3.866 3.134-7 7-7h0c3.866 0 7 3.134 7 7" stroke-linecap="round"/>
            </svg>
        </a>
        <a href="/wishlist" class="nb-icon-link" aria-label="Избранное">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M11 18.5S3 13 3 7.5A4 4 0 0111 5.4 4 4 0 0119 7.5C19 13 11 18.5 11 18.5z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
        <a href="{{ route('cart.index') }}" class="nb-icon-link" aria-label="Корзина">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2.2">
                <path d="M2 2h2l2.5 11h10l2.5-8H6" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="9" cy="18" r="1.5"/><circle cx="16" cy="18" r="1.5"/>
            </svg>
            @if(($cartCount ?? 0) > 0)
                <span class="nb-badge" aria-label="{{ $cartCount }} товаров в корзине">{{ $cartCount }}</span>
            @endif
        </a>
    </div>
</header>

{{-- ══════════════════ COMPACT HEADER (≤1279px) + drawer trigger ════════════════ --}}
<header class="nb-mheader" role="banner">
    <button class="nb-sq nb-sq--accent" aria-label="Открыть меню категорий" data-drawer-open>
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="M2 4h14M2 9h14M2 14h14"/>
        </svg>
    </button>

    <a href="{{ route('home') }}" class="nb-logo">NET<span>BAZAR</span></a>

    {{-- Inline search — visible ≥768 --}}
    <form class="nb-search" action="{{ route('catalog.index') }}" method="GET" role="search" style="flex:1;margin-left:12px;">
        <input type="search" name="q" placeholder="Поиск товаров..." aria-label="Поиск"
               value="{{ request('q', '') }}">
        <button type="submit" aria-label="Найти">
            <svg width="16" height="16" viewBox="0 0 18 18" fill="none">
                <circle cx="7.5" cy="7.5" r="5.5" stroke="currentColor" stroke-width="1.6"/>
                <path d="M12 12l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </button>
    </form>

    {{-- Search icon (mobile only) --}}
    <a href="{{ route('catalog.index') }}" class="nb-sq nb-sq--search" aria-label="Поиск">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <circle cx="7.5" cy="7.5" r="5.5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M12 12l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
    </a>

    <a href="{{ route('cart.index') }}" class="nb-sq" aria-label="Корзина" style="position:relative;">
        <svg width="18" height="18" viewBox="0 0 22 22" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M2 2h2l2.5 11h10l2.5-8H6" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="9" cy="18" r="1.5"/><circle cx="16" cy="18" r="1.5"/>
        </svg>
        @if(($cartCount ?? 0) > 0)
            <span class="nb-badge" style="font-size:10px;">{{ $cartCount }}</span>
        @endif
    </a>
</header>

{{-- ════════════════════ MOBILE CATEGORY DRAWER ════════════════════════════════ --}}
<dialog id="nb-drawer" class="nb-drawer" aria-label="Категории товаров">
    <div class="nb-drawer__panel">
        <div class="nb-drawer__head">
            <span style="font-family:var(--nb-font-display);font-weight:800;font-size:18px;color:#fff;">
                NET<span style="color:var(--nb-accent)">BAZAR</span>
            </span>
            <button class="nb-sq" data-drawer-close aria-label="Закрыть">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M2 2l14 14M16 2L2 16"/>
                </svg>
            </button>
        </div>

        <a href="{{ route('catalog.index') }}"
           class="nb-tree__all{{ request('category') === '' && !request('q') ? ' is-active' : '' }}"
           style="display:flex;justify-content:space-between;margin-bottom:8px;"
           onclick="document.getElementById('nb-drawer')?.close()">
            Все товары
        </a>

        @if(isset($categoryTree) && $categoryTree)
            <x-category-tree :nodes="$categoryTree" :active-path="$activeBranchIds ?? []" :depth="1" />
        @else
            {{-- Drawer shown on homepage — load tree minimally --}}
            @php
                try {
                    $drawerTree = app(\App\Services\CategoryTreeService::class);
                    $drawerFlat = $drawerTree->getPublicCategories();
                    $drawerNodes = $drawerTree->buildTree($drawerFlat);
                } catch (\Throwable $e) {
                    $drawerNodes = [];
                }
            @endphp
            @if(!empty($drawerNodes))
                <x-category-tree :nodes="$drawerNodes" :active-path="[]" :depth="1" />
            @endif
        @endif
    </div>
</dialog>

{{-- ══════════════════════════ PAGE CONTENT ════════════════════════════════════ --}}
@yield('content')

{{-- ════════════════════════════ FOOTER ═══════════════════════════════════════ --}}
<footer class="nb-footer" role="contentinfo">
    <div class="nb-footer__cols">
        <div class="nb-footer__col">
            <a href="{{ route('home') }}" class="nb-logo" style="font-size:22px;">NET<span>BAZAR</span></a>
            <p class="nb-footer__note" style="margin-top:16px;">Техника, мебель и товары для дома.<br>Реальные цены и наличие.</p>
            <p class="nb-footer__legal">© Net-Bazar.kz</p>
        </div>
        <div class="nb-footer__col">
            <h3 class="nb-overline" style="margin-bottom:16px;">Каталог</h3>
            <ul>
                <li><a href="{{ route('catalog.index', ['category' => '34e9b5d0f75a8dd2a4437425']) }}">Бытовая техника</a></li>
                <li><a href="{{ route('catalog.index', ['category' => '6de353f47065ba6c7f96a205']) }}">Кресла</a></li>
                <li><a href="{{ route('catalog.index', ['category' => '3b50c632c5fcf97505984a51']) }}">Кулеры для воды</a></li>
                <li><a href="{{ route('catalog.index', ['category' => '1361c12588ddb7a4ed9d4074']) }}">Химия</a></li>
                <li><a href="{{ route('catalog.index', ['category' => '6bede016037a3d7e79338836']) }}">Автотовары</a></li>
            </ul>
        </div>
        <div class="nb-footer__col">
            <h3 class="nb-overline" style="margin-bottom:16px;">Компания</h3>
            <ul>
                <li><a href="#">О компании</a></li>
                <li><a href="#">Доставка и оплата</a></li>
                <li><a href="#">Контакты</a></li>
            </ul>
        </div>
        <div class="nb-footer__col">
            <h3 class="nb-overline" style="margin-bottom:16px;">Покупателям</h3>
            <ul>
                <li><a href="{{ route('catalog.index') }}">Весь каталог</a></li>
                <li><a href="#">Kaspi рассрочка</a></li>
            </ul>
        </div>
    </div>
</footer>

<script src="/js/storefront.js" defer></script>
</body>
</html>
