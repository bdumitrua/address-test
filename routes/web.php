<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AddressController;

Route::get('/', [AddressController::class, 'index']);
Route::post('/get-info', [AddressController::class, 'getInfo']);
