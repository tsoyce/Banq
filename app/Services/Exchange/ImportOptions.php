<?php

namespace App\Services\Exchange;

/**
 * A22 · Параметры прогона импорта.
 *  - mode: upsert (создать и обновить), create (только новые), update (только существующие);
 *  - dryRun: всё считается в транзакции и откатывается — честный предпросмотр с диффом;
 *  - country: страна по умолчанию для строк без поля country (код ISO, например KZ);
 *  - source: откуда пришло — admin | cli | api.
 */
final class ImportOptions
{
    public const MODES = ['upsert' => 'Создать и обновить', 'create' => 'Только новые', 'update' => 'Только обновить существующие'];

    public function __construct(
        public readonly string $mode = 'upsert',
        public readonly bool $dryRun = false,
        public readonly ?string $country = null,
        public readonly string $source = 'admin',
        public readonly ?int $userId = null,
        public readonly ?int $tokenId = null,
        public readonly ?string $filename = null,
        public readonly ?string $format = null,
    ) {}

    public static function fromArray(array $a): self
    {
        $mode = (string) ($a['mode'] ?? 'upsert');

        return new self(
            mode: array_key_exists($mode, self::MODES) ? $mode : 'upsert',
            dryRun: filter_var($a['dry_run'] ?? false, FILTER_VALIDATE_BOOL),
            country: ($a['country'] ?? null) ?: null,
            source: (string) ($a['source'] ?? 'admin'),
            userId: $a['user_id'] ?? null,
            tokenId: $a['token_id'] ?? null,
            filename: $a['filename'] ?? null,
            format: $a['format'] ?? null,
        );
    }
}
