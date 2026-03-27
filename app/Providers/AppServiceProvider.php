<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        // Fix for cURL error 77: SSL certificate problem on Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $paths = [
                'C:\php-8.3.4\extras\ssl\cacert.pem',
                'C:\php\extras\ssl\cacert.pem',
                'D:\xampp\php\extras\ssl\cacert.pem'
            ];
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    ini_set('curl.cainfo', $path);
                    ini_set('openssl.cafile', $path);
                    break;
                }
            }
        }
    }
}
