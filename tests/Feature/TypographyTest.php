<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A36 · Middleware типографики включается настройкой banq.typograph и не ломает view-ответ. */
class TypographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_pages_get_non_breaking_spaces(): void
    {
        $this->markInstalled();
        config(['banq.typograph' => true]);

        $this->get('/deposits')->assertOk()->assertViewIs('deposits.index')
            ->assertSee("ставки и\u{00A0}ГЭСВ по\u{00A0}всем", false);
    }
}
