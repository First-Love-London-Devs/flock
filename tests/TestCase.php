<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function refreshInMemoryDatabase(): void
    {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    protected function refreshTestDatabase(): void
    {
        if (!RefreshDatabaseState::$migrated) {
            Artisan::call('migrate:fresh', [
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
            ]);
            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}
