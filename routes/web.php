<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/cron/run', function () {
    if (request('key') !== 'secret123') {
        abort(403);
    }

    \Artisan::call('schedule:run');
    return 'Cron executed';
});
