<?php

use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return ['message' => 'API route working'];
});

require __DIR__.'/../routes/auth.php';
