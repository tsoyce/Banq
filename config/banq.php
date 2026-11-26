<?php

// A01 · Конфигурация проекта TSOY.IN Banq (не секретная; секреты — в .env).
return [
    'version' => '0.1.0',
    // Файл-признак установки (null → storage/app/installed.json)
    'install_lock' => env('BANQ_INSTALL_LOCK'),
    'currencies' => ['KZT', 'USD', 'EUR', 'RUB', 'CNY'],
    'terms' => [1, 3, 6, 9, 12, 18, 24, 36],
];
