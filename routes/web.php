<?php

use App\Http\Controllers\SamlCustomController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SamlTestController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/custom-saml/start', [SamlCustomController::class, 'start']);
