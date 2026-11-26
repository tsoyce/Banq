<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected string $lock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lock = sys_get_temp_dir().'/banq-installed-'.uniqid().'.json';
        config(['banq.install_lock' => $this->lock]);
    }

    protected function tearDown(): void
    {
        @unlink($this->lock);
        parent::tearDown();
    }

    protected function markInstalled(): void
    {
        file_put_contents($this->lock, '{}');
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
