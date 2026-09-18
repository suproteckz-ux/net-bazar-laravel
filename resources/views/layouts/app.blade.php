<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'NetBazar — бытовая техника, кресла, кулеры, химия и автоаксессуары')</title>
    <link rel="stylesheet" href="/css/netbazar.css">
</head>
<body class="@yield('body-class', '')">

<header class="site-header">
    <div class="header-inner">
        <a href="{{ route('home') }}" class="logo" aria-label="NetBazar — на главную">NET<span>BAZAR</span></a>

        <nav class="header-nav" aria-label="Основная навигация">
            <ul>
                <li><a href="{{ route('catalog.index') }}">Каталог <svg class="nav-chevron" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg></a></li>
                <li><a href="#">О компании</a></li>
                <li><a href="#">Доставка и оплата</a></li>
                <li><a href="#">Контакты</a></li>
            </ul>
        </nav>

        <form class="header-search" action="{{ route('catalog.index') }}" method="GET" role="search">
            <input type="search" name="q" placeholder="Поиск товаров..." aria-label="Поиск по каталогу">
            <button type="submit" aria-label="Найти">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                    <circle cx="7.5" cy="7.5" r="5.5" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M12 12l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>
        </form>

        <div class="header-icons">
            <a href="/account" aria-label="Личный кабинет">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none" aria-hidden="true">
                    <circle cx="11" cy="8" r="3.5" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M4 19c0-3.866 3.134-7 7-7h0c3.866 0 7 3.134 7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </a>
            <a href="/wishlist" aria-label="Избранное">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none" aria-hidden="true">
                    <path d="M11 18.5S3 13 3 7.5A4 4 0 0111 5.4 4 4 0 0119 7.5C19 13 11 18.5 11 18.5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <a href="/cart" class="cart-icon-wrap" aria-label="Корзина (0 товаров)">
                <svg width="22" height="22" viewBox="0 0 22 22" fill="none" aria-hidden="true">
                    <path d="M2 2h2l2.5 11h10l2.5-8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="9" cy="18" r="1.5" stroke="currentColor" stroke-width="1.2"/>
                    <circle cx="16" cy="18" r="1.5" stroke="currentColor" stroke-width="1.2"/>
                </svg>
                <span class="cart-badge" aria-hidden="true">0</span>
            </a>
        </div>
    </div>
</header>

@yield('content')

</body>
</html>
