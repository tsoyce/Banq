<?php

// A01 · Конфигурация проекта TSOY.IN Banq (не секретная; секреты — в .env).
return [
    'version' => '0.1.0',
    // Файл-признак установки (null → storage/app/installed.json)
    'install_lock' => env('BANQ_INSTALL_LOCK'),
    // A29 · Экранная типографика (неразрывные пробелы) для HTML-ответов — App\Http\Middleware\Typography
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
];
