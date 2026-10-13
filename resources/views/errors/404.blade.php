@extends('layouts.app')

@section('title', '404 — Страница не найдена | NetBazar')

@section('meta_robots')
<meta name="robots" content="noindex, follow">
@endsection

@section('content')
<main aria-label="Страница не найдена" style="
    min-height: calc(100vh - var(--nb-header-h) - 120px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 60px var(--nb-gutter);
    text-align: center;
">
    <div style="max-width: 480px; width: 100%;">

        {{-- Big 404 --}}
        <div aria-hidden="true" style="
            font-family: var(--nb-font-display);
            font-weight: 900;
            font-size: clamp(96px, 18vw, 160px);
            line-height: 1;
            color: var(--nb-accent);
            letter-spacing: -0.02em;
        ">404</div>

        {{-- Heading --}}
        <h1 style="
            font-family: var(--nb-font-display);
            font-weight: 800;
            font-size: clamp(20px, 4vw, 28px);
            color: var(--nb-text);
            margin: 16px 0 0;
            line-height: 1.25;
        ">Упс! Что-то пошло не так.</h1>

        {{-- Subtitle --}}
        <p style="
            font-family: var(--nb-font-text);
            font-size: 16px;
            color: var(--nb-muted);
            margin: 16px 0 0;
            line-height: 1.6;
        ">Похоже, такой страницы больше нет или ссылка устарела.</p>

        {{-- Explanation --}}
        <p style="
            font-family: var(--nb-font-text);
            font-size: 15px;
            color: var(--nb-dim);
            margin: 8px 0 0;
            line-height: 1.6;
        ">Не переживайте — нужные товары можно найти в нашем каталоге.</p>

        {{-- Actions --}}
        <div style="
            margin-top: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
        ">
            <a href="/catalog" class="nb-btn nb-btn--primary" style="min-width: 220px; text-decoration: none;">
                Перейти в каталог
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M3 8h10M8 3l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>

            <a href="/" style="
                font-family: var(--nb-font-text);
                font-size: 15px;
                color: var(--nb-dim);
                text-decoration: underline;
                text-underline-offset: 3px;
            ">На главную</a>
        </div>

    </div>
</main>
@endsection
