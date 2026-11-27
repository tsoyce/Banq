<?php

namespace App\Services\Installer;

/**
 * A03 · Запись значений в .env без потери остальных строк.
 * bootstrap(): если .env нет — создаёт из .env.example и генерирует APP_KEY.
 */
class EnvWriter
{
    public function __construct(private ?string $path = null)
    {
        $this->path ??= app()->environmentFilePath();
    }

    public static function bootstrap(): bool
    {
        $env = app()->environmentFilePath();
        $changed = false;

        if (! is_file($env) && is_file(base_path('.env.example'))) {
            copy(base_path('.env.example'), $env);
            $changed = true;
        }
        if (is_file($env) && ! preg_match('/^APP_KEY=base64:.+$/m', (string) file_get_contents($env))) {
            (new self($env))->set(['APP_KEY' => 'base64:'.base64_encode(random_bytes(32))]);
            $changed = true;
        }

        return $changed;
    }

    public function set(array $values): void
    {
        $content = is_file($this->path) ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->quote((string) $value);
            $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $content, 1)
                : rtrim($content).PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($this->path, $content);
    }

    /** A34 · Закомментировать ключи (как в исходном .env): значение по умолчанию снова берётся из config. */
    public function forget(array $keys): void
    {
        $content = is_file($this->path) ? (string) file_get_contents($this->path) : '';
        foreach ($keys as $key) {
            $content = preg_replace('/^'.preg_quote($key, '/').'=/m', '# '.$key.'=', $content);
        }
        file_put_contents($this->path, $content);
    }

    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/+=@-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }
}
