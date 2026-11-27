<?php

// A01 · Конфигурация проекта TSOY.IN Banq (не секретная; секреты — в .env).
return [
    'version' => '0.1.0',
    // Файл-признак установки (null → storage/app/installed.json)
    'install_lock' => env('BANQ_INSTALL_LOCK'),
    // A36 · Экранная типографика (неразрывные пробелы) для HTML-ответов — App\Http\Middleware\Typography
    'typograph' => env('BANQ_TYPOGRAPH', true),
    'currencies' => ['KZT', 'USD', 'EUR', 'RUB', 'CNY'],
    'terms' => [1, 3, 6, 9, 12, 18, 24, 36],
    // A21 · Лимиты гарантии вкладов перенесены в страну: countries.deposit_guarantee.limits (Country::guaranteeLimit).
    // A18 · Рейтинги банков. Формула объективного — App\Services\Ratings\ObjectiveRating, описание — /ratings#method
    'ratings' => [
        'objective_weights' => [
            'rates' => 0.40,       // ставки относительно рынка по каждому типу вклада
            'reliability' => 0.30, // КФГД, возраст, размер активов, капитал
            'range' => 0.15,       // широта линейки: сколько типов вкладов есть
            'terms' => 0.15,       // удобство: онлайн-открытие, вклад со снятием
        ],
        // Народный: байесовское среднее — пока отзывов мало, оценка тянется к среднему по рынку
        'people_prior_weight' => 5,
        'people_prior_mean' => 3.5,
        'review_min_length' => 80,
    ],
    // A30 · Карты: подписи особенностей (cards.features), кошельков (payment_systems.wallets)
    'cards' => [
        'features' => [
            'contactless' => 'Бесконтактная оплата',
            'apple_pay' => 'Apple Pay',
            'google_pay' => 'Google Pay',
            'samsung_pay' => 'Samsung Pay',
            'multicurrency' => 'Мультивалютная',
            'installments' => 'Рассрочка',
            'cashback_categories' => 'Кэшбэк по категориям',
            'lounge' => 'Бизнес-залы',
            'travel_insurance' => 'Страхование путешествий',
            'concierge' => 'Консьерж',
            'metal' => 'Металлическая',
            'custom_design' => 'Свой дизайн',
            'free_transfers' => 'Бесплатные переводы',
            'kids' => 'Для детей',
        ],
        'wallets' => [
            'apple_pay' => 'Apple Pay', 'google_pay' => 'Google Pay', 'samsung_pay' => 'Samsung Pay', 'garmin_pay' => 'Garmin Pay',
            'huawei_pay' => 'Huawei Pay', 'mir_pay' => 'Mir Pay',
        ],
        'compare_limit' => 4,
        // A33 · «Оценка Banq» карты (App\Services\Cards\CardScore): 1 + 4 × Σ(вес × доля от лучшего на рынке)
        'score' => [
            'debit' => ['cashback' => 0.35, 'fee' => 0.30, 'balance' => 0.20, 'features' => 0.15],
            'credit' => ['grace' => 0.35, 'rate' => 0.25, 'fee' => 0.25, 'cashback' => 0.15],
        ],
    ],
];
