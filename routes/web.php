<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/run-migrate', function() {
    Artisan::call('migrate', ['--force' => true]);
    return 'Migrations OK';
});
