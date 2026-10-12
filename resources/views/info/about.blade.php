@extends('layouts.app')

@section('title', 'О компании NetBazar — бытовая техника, кресла и автотовары в Алматы')
@section('meta_description', 'NetBazar — интернет-магазин из Алматы. Работаем с 2021 года, более 20 000 продаж. Бытовая техника, офисные кресла, автохимия и автотовары. Работа с юридическими лицами и доставка по Казахстану.')

@section('content')
<div class="nb-page">

    {{-- Breadcrumb --}}
    <nav class="nb-crumbs" aria-label="Путь">
        <a href="{{ route('home') }}">Главная</a>
        <span class="nb-sep" aria-hidden="true">›</span>
        <span aria-current="page">О компании</span>
    </nav>

    <div class="nb-info-page">

        <h1 class="nb-h1">О компании NetBazar</h1>
        <div class="nb-h2-rule"></div>

        {{-- Intro --}}
        <div class="nb-section">
            <p class="nb-body" style="font-size:16.5px;line-height:1.8;color:var(--nb-text-2);max-width:780px;">
                <strong style="color:#fff;">NetBazar — интернет-магазин бытовой техники, офисных кресел и товаров для автомобиля в Алматы.</strong>
                Компания работает с 2021 года. За это время мы выполнили более 20&nbsp;000 продаж и накопили опыт в подборе товаров, организации доставки и обслуживании покупателей.
            </p>
            <p class="nb-body" style="margin-top:14px;max-width:780px;">
                Мы работаем с физическими и юридическими лицами. Помогаем подобрать товары для дома, офиса и автомобиля.
            </p>
        </div>

        {{-- Assortment --}}
        <div class="nb-section">
            <h2 class="nb-h2">Наш ассортимент</h2>
            <div class="nb-h2-rule"></div>
            <div class="nb-info-cards" style="margin-top:20px;">
                <div class="nb-info-card">
                    <div class="nb-info-card__icon">
                        <svg width="28" height="28" viewBox="0 0 36 36" fill="none" stroke="var(--nb-accent)" stroke-width="1.6" aria-hidden="true">
                            <rect x="6" y="13" width="24" height="17" rx="2"/>
                            <path d="M13 13V9a2 2 0 012-2h6a2 2 0 012 2v4"/>
                        </svg>
                    </div>
                    <div class="nb-info-card__title">Офисные и компьютерные кресла</div>
                    <p class="nb-info-card__body">Кресла для руководителей, сотрудников и домашнего офиса. Модели с различными механизмами регулировки.</p>
                </div>
                <div class="nb-info-card">
                    <div class="nb-info-card__icon">
                        <svg width="28" height="28" viewBox="0 0 36 36" fill="none" stroke="var(--nb-accent)" stroke-width="1.6" aria-hidden="true">
                            <rect x="4" y="9" width="28" height="18" rx="3"/>
                            <path d="M4 15h28"/>
                        </svg>
                    </div>
                    <div class="nb-info-card__title">Бытовая техника</div>
                    <p class="nb-info-card__body">Холодильники, стиральные и посудомоечные машины и другая техника для дома.</p>
                </div>
                <div class="nb-info-card">
                    <div class="nb-info-card__icon">
                        <svg width="28" height="28" viewBox="0 0 36 36" fill="none" stroke="var(--nb-accent)" stroke-width="1.6" aria-hidden="true">
                            <path d="M18 6v24M10 10h16M8 18h20M10 26h16"/>
                        </svg>
                    </div>
                    <div class="nb-info-card__title">Кулеры для воды</div>
                    <p class="nb-info-card__body">Оборудование для подачи питьевой воды дома и в офисе.</p>
                </div>
                <div class="nb-info-card">
                    <div class="nb-info-card__icon">
                        <svg width="28" height="28" viewBox="0 0 36 36" fill="none" stroke="var(--nb-accent)" stroke-width="1.6" aria-hidden="true">
                            <path d="M3 24h22V10H3v14z"/>
                            <path d="M25 15h4l4 5v4h-8V15z"/>
                            <circle cx="9" cy="27" r="2.5" stroke-width="1.4"/>
                            <circle cx="28" cy="27" r="2.5" stroke-width="1.4"/>
                        </svg>
                    </div>
                    <div class="nb-info-card__title">Автохимия и автотовары</div>
                    <p class="nb-info-card__body">Средства для ухода за кузовом, автосигнализации и сопутствующие товары для автомобиля.</p>
                </div>
            </div>
        </div>

        {{-- Why us --}}
        <div class="nb-section">
            <h2 class="nb-h2">Почему выбирают NetBazar</h2>
            <div class="nb-h2-rule"></div>
            <ul class="nb-why-list">
                <li>Работаем с 2021 года</li>
                <li>Более 20&nbsp;000 продаж</li>
                <li>Помогаем выбрать товар</li>
                <li>Работаем с физическими и юридическими лицами</li>
                <li>Предоставляем документы с НДС</li>
                <li>Организуем доставку по Алматы и Казахстану</li>
                <li>Предоставляем информацию о гарантии и поддержку после покупки</li>
            </ul>
        </div>

        {{-- How to order --}}
        <div class="nb-section">
            <h2 class="nb-h2">Как оформить заказ</h2>
            <div class="nb-h2-rule"></div>
            <ol class="nb-steps">
                <li><span class="nb-steps__num">1</span><span>Выберите товар в каталоге</span></li>
                <li><span class="nb-steps__num">2</span><span>Добавьте товар в корзину</span></li>
                <li><span class="nb-steps__num">3</span><span>Заполните контактные данные</span></li>
                <li><span class="nb-steps__num">4</span><span>Менеджер подтвердит наличие, доставку и способ оплаты</span></li>
                <li><span class="nb-steps__num">5</span><span>Получите заказ самовывозом или с доставкой</span></li>
            </ol>
        </div>

        {{-- CTA --}}
        <div class="nb-section">
            <h2 class="nb-h2">Остались вопросы?</h2>
            <div class="nb-h2-rule"></div>
            <p class="nb-body">Позвоните по номеру <a href="{{ config('netbazar.contact.phone_href') }}" style="color:var(--nb-text-2);font-weight:600;">{{ config('netbazar.contact.phone') }}</a> или напишите нам в WhatsApp.</p>
            <div class="nb-contact-btns" style="margin-top:20px;">
                <a href="{{ route('catalog.index') }}" class="nb-btn nb-btn--primary">
                    Перейти в каталог
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M3 8h10M8 3l5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <a href="{{ config('netbazar.contact.whatsapp_url') }}"
                   class="nb-btn nb-btn--whatsapp"
                   target="_blank" rel="noopener noreferrer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Написать в WhatsApp
                </a>
            </div>
        </div>

        {{-- Requisites --}}
        <div class="nb-section">
            <h2 class="nb-h2">Реквизиты</h2>
            <div class="nb-h2-rule"></div>
            <dl class="nb-requisites">
                <div class="nb-req-item">
                    <dt>Компания</dt>
                    <dd>{{ config('netbazar.contact.company') }}</dd>
                </div>
                <div class="nb-req-item">
                    <dt>БИН</dt>
                    <dd>{{ config('netbazar.contact.bin') }}</dd>
                </div>
                <div class="nb-req-item">
                    <dt>Адрес</dt>
                    <dd>{{ config('netbazar.contact.address_full') }}</dd>
                </div>
                <div class="nb-req-item">
                    <dt>Телефон</dt>
                    <dd><a href="{{ config('netbazar.contact.phone_href') }}" style="color:inherit;">{{ config('netbazar.contact.phone') }}</a></dd>
                </div>
            </dl>
        </div>

    </div>
</div>
@endsection
