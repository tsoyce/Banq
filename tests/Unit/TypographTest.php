<?php

namespace Tests\Unit;

use App\Support\Typograph;
use PHPUnit\Framework\TestCase;

/** A36 · Экранная типографика: неразрывные пробелы только в тексте, не в разметке и скриптах. */
class TypographTest extends TestCase
{
    private const NB = "\u{00A0}";

    public function test_dash_sticks_to_previous_word(): void
    {
        $this->assertSame('Одна таблица'.self::NB.'— лучшая', Typograph::text('Одна таблица — лучшая'));
    }

    public function test_short_words_stick_to_next_word(): void
    {
        $this->assertSame('ставки и'.self::NB.'в'.self::NB.'банке', Typograph::text('ставки и в банке'));
        $this->assertSame('В'.self::NB.'каталоге', Typograph::text('В каталоге'));
    }

    public function test_number_sticks_to_unit(): void
    {
        $this->assertSame('12'.self::NB.'мес и'.self::NB.'27'.self::NB.'ноября', Typograph::text('12 мес и 27 ноября'));
        $this->assertSame('Ставка 1 000'.self::NB.'₸', Typograph::text('Ставка 1 000 ₸'));
    }

    public function test_markup_scripts_and_textareas_are_untouched(): void
    {
        $html = '<a title="в банк — да" href="/x">в банк</a><script>let a = "в банк";</script><textarea>и в</textarea><p>и в</p>';
        $out = Typograph::html($html);

        $this->assertStringContainsString('title="в банк — да"', $out);
        $this->assertStringContainsString('<script>let a = "в банк";</script>', $out);
        $this->assertStringContainsString('<textarea>и в</textarea>', $out);
        $this->assertStringContainsString('>в'.self::NB.'банк</a>', $out);
        $this->assertStringContainsString('<p>и'.self::NB.'в</p>', $out);
    }

    public function test_words_that_only_start_like_short_ones_are_kept(): void
    {
        $this->assertSame('вклад наш', Typograph::text('вклад наш'));
    }
}
