<?php

// A01 · Конфигурация проекта TSOY.IN Banq (не секретная; секреты — в .env).
return [
    'version' => '0.1.0',
    // Файл-признак установки (null → storage/app/installed.json)
    'install_lock' => env('BANQ_INSTALL_LOCK'),
    'currencies' => ['KZT', 'USD', 'EUR', 'RUB', 'CNY'],
    'terms' => [1, 3, 6, 9, 12, 18, 24, 36],
    // A14 · Предельная сумма гарантии КФГД по типу вклада, ₸ (сверить с kdif.kz при изменении закона)
    'kdif_limits' => [
        'savings' => 20_000_000,
        'term' => 15_000_000,
        'demand' => 10_000_000,
        'foreign' => 5_000_000, // вклады в иностранной валюте
    ],
];
