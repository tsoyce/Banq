<?php

namespace App\Services\WordPress;

/** A43 · Шаг переноса исчерпал отведённое время — состояние сохранено, следующий шаг продолжит с того же места. */
class WpImportPaused extends \RuntimeException {}
