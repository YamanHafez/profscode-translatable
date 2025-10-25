<?php

namespace Profscode\Translatable;

use Illuminate\Support\ServiceProvider;

class TranslatableServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // FOR MIGRATION
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register(): void
    {
        //
    }
}
