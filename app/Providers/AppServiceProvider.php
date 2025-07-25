<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->loadHelpers();
    }

  
    public function boot(): void
    {
        //
    }

    private function loadHelpers()
    {
        $helperDir = app_path('Helpers');
        
        if (is_dir($helperDir)) {
            $helperFiles = glob($helperDir . '/*.php');
            
            foreach ($helperFiles as $file) {
                require_once $file;
            }
        }
    }
}