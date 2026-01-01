<?php

namespace Profscode\Translatable;

use Illuminate\Support\ServiceProvider;

/**
 * Class TranslatableServiceProvider
 * 
 * The service provider for the Profscode Translatable package.
 * It handles migration loading and other package initialization.
 * 
 * @package Profscode\Translatable
 */
class TranslatableServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     * 
     * @return void
     */
    public function boot(): void
    {
        // Load the migrations for the translatable table
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
     * Register any application services.
     * 
     * @return void
     */
    public function register(): void
    {
        //
    }
}
