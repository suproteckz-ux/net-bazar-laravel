<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'NetBazar — бытовая техника, кресла, кулеры, химия и автоаксессуары')</title>
    @if(trim($__env->yieldContent('meta_description')))
        <meta name="description" content="@yield('meta_description')">
    @endif
    @yield('meta_robots')
    <link rel="canonical" href="{{ rtrim(config('netbazar.contact.site_url'), '/') . request()->getPathInfo() }}">

    {{-- Fonts: Montserrat (display) + Manrope (text), Cyrillic subset --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Montserrat:wght@700;800;900&display=swap&subset=cyrillic" rel="stylesheet">

    <link rel="stylesheet" href="/css/netbazar.css?v={{ filemtime(public_path('css/netbazar.css')) }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="wa-phone" content="{{ config('netbazar.contact.whatsapp') }}">
</head>
<body>

@php $contact = config('netbazar.contact'); @endphp

{{-- ═══════════════════════ DESKTOP HEADER (≥1280px) ═══════════════════════════ --}}
<header class="nb-header" role="banner">
    <a href="{{ route('home') }}" class="nb-logo" aria-label="NetBazar — главная">NET<span>BAZAR</span></a>

    <nav class="nb-nav" aria-label="Основная навигация">
        <a href="{{ route('catalog.index') }}">Каталог
            <svg width="10" height="6" viewBox="0 0 10 6" fill="none" aria-hidden="true" style="display:inline;margin-left:5px;vertical-align:middle;color:var(--nb-accent)">
                <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
        <a href="{{ route('info.about') }}"{{ request()->routeIs('info.about') ? ' class="is-active"' : '' }}>О компании</a>
        <a href="{{ route('info.delivery') }}"{{ request()->routeIs('info.delivery') ? ' class="is-active"' : '' }}>Доставка и оплата</a>
        <a href="{{ route('info.contacts') }}"{{ request()->routeIs('info.contacts') ? ' class="is-active"' : '' }}>Контакты</a>
    </nav>

    <div class="nb-header-actions">
        <a href="{{ $contact['whatsapp_url'] }}"
           class="nb-wa-header"
           target="_blank" rel="noopener noreferrer"
           aria-label="Написать в WhatsApp">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            <span>WhatsApp</span>
        </a>

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

    {{-- WhatsApp icon (mobile) --}}
    <a href="{{ $contact['whatsapp_url'] }}"
       class="nb-sq nb-sq--wa"
       target="_blank" rel="noopener noreferrer"
       aria-label="Написать в WhatsApp">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
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

        {{-- Drawer contacts --}}
        <div class="nb-drawer__contacts">
            <a href="{{ $contact['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer" class="nb-drawer__contact-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                WhatsApp
            </a>
            <a href="{{ $contact['phone_href'] }}" class="nb-drawer__contact-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.08 1.18 2 2 0 012.07 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                </svg>
                {{ $contact['phone'] }}
            </a>
        </div>
    </div>
</dialog>

{{-- ══════════════════════════ PAGE CONTENT ════════════════════════════════════ --}}
@yield('content')

{{-- ════════════════════════════ FOOTER ═══════════════════════════════════════ --}}
<footer class="nb-footer" role="contentinfo">
    <div class="nb-footer__cols">

        {{-- Col 1: Brand --}}
        <div class="nb-footer__col">
            <a href="{{ route('home') }}" class="nb-logo" style="font-size:22px;">NET<span>BAZAR</span></a>
            <p class="nb-footer__note" style="margin-top:16px;">
                Бытовая техника, офисные кресла и товары для дома и автомобиля.<br>
                Доставка по Алматы и Казахстану.
            </p>
            <address class="nb-footer__address">
                <a href="https://go.2gis.com/mhnXg" target="_blank" rel="noopener noreferrer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Алматы, ул. Муратбаева, 138
                </a>
                <a href="{{ $contact['phone_href'] }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.08 1.18 2 2 0 012.07 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    {{ $contact['phone'] }}
                </a>
                <a href="{{ $contact['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
                <a href="{{ $contact['instagram_url'] }}" target="_blank" rel="noopener noreferrer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                    {{ $contact['instagram'] }}
                </a>
            </address>
        </div>

        {{-- Col 2: Catalog --}}
        <div class="nb-footer__col">
            <h3 class="nb-overline" style="margin-bottom:16px;">Каталог</h3>
            <ul>
                <li><a href="{{ route('catalog.index', ['category' => '34e9b5d0f75a8dd2a4437425']) }}">Бытовая техника</a></li>
                <li><a href="{{ route('catalog.index', ['category' => '6de353f47065ba6c7f96a205']) }}">Офисные кресла</a></li>
                <li><a href="{{ route('catalog.index', ['category' => '3b50c632c5fcf97505984a51']) }}">Кулеры для воды</a></li>
                <li><a href="{{ route('catalog.index', ['category' => '1361c12588ddb7a4ed9d4074']) }}">Автохимия</a></li>
                <li><a href="{{ route('catalog.index', ['category' => '6bede016037a3d7e79338836']) }}">Автотовары</a></li>
            </ul>
        </div>

        {{-- Col 3: Company --}}
        <div class="nb-footer__col">
            <h3 class="nb-overline" style="margin-bottom:16px;">Компания</h3>
            <ul>
                <li><a href="{{ route('info.about') }}">О компании</a></li>
                <li><a href="{{ route('info.contacts') }}">Контакты</a></li>
                <li><a href="{{ route('info.delivery') }}">Доставка и оплата</a></li>
            </ul>
        </div>

        {{-- Col 4: Buyers --}}
        <div class="nb-footer__col">
            <h3 class="nb-overline" style="margin-bottom:16px;">Покупателям</h3>
            <ul>
                <li><a href="{{ route('catalog.index') }}">Весь каталог</a></li>
                <li><a href="{{ route('info.delivery') }}">Условия доставки</a></li>
                <li><a href="{{ $contact['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer">Задать вопрос</a></li>
            </ul>
        </div>

    </div>

    {{-- Footer bottom --}}
    <div class="nb-footer__bottom">
        <div>
            © 2026 {{ $contact['company'] }}. Все права защищены. БИН: {{ $contact['bin'] }}.
        </div>
        <div class="nb-footer__disclaimer">
            Информация на сайте носит справочный характер и не является публичной офертой.
        </div>
    </div>
</footer>

{{-- ═══════════ OOS (out-of-stock) notification dialog ════════════════════════ --}}
{{-- Opened by storefront.js when a cart-form AJAX POST returns 409 out_of_stock --}}
<dialog id="nb-oos-dialog" class="nb-oos-dialog" aria-labelledby="nb-oos-title" aria-modal="true">
    <div class="nb-oos-dialog__panel">
        <div class="nb-oos-dialog__head">
            <h2 class="nb-oos-dialog__title" id="nb-oos-title">Товар закончился</h2>
            <button class="nb-sq" id="nb-oos-close" aria-label="Закрыть диалог">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M2 2l14 14M16 2L2 16"/>
                </svg>
            </button>
        </div>
        <p class="nb-oos-dialog__text">
            К сожалению, сейчас этого товара нет в наличии. Напишите нам в WhatsApp, и мы уточним сроки поступления.
        </p>
        <a id="nb-oos-wa-btn" href="#" class="nb-btn nb-btn--whatsapp nb-oos-dialog__wa-btn"
           target="_blank" rel="noopener noreferrer">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Написать в WhatsApp
        </a>
    </div>
</dialog>

<script src="/js/storefront.js?v={{ filemtime(public_path('js/storefront.js')) }}" defer></script>
</body>
</html>
