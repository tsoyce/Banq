<?php

namespace App\Services\Exchange;

use RuntimeException;

/** A22 · Ошибка в строке импорта: строка пропускается, причина попадает в отчёт. */
class RowError extends RuntimeException {}
