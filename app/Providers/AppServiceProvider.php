<?php

namespace App\Providers;

use App\Models\PropertyGroup;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.base', function ($view): void {
            $groups = collect();
            try {
                if (Schema::hasTable('groups')) $groups = PropertyGroup::orderBy('name')->get();
            } catch (\Throwable) {
                // A aplicação também precisa renderizar antes da primeira migração.
            }
            $view->with('navGroups', $groups);
        });
    }
}
