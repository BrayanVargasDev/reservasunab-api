<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SamlTestController;

Route::get('/', function () {
    return view('welcome');
});
