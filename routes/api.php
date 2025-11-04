<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\userInfoController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::get('/user-info', [userInfoController::class, 'userInfo']);
Route::post('/user/add-address', [AddressController::class, 'addAddress']);
Route::get('/user/get-addresses', [AddressController::class, 'getAddresses']);
Route::put('/user/update-address', [AddressController::class, 'updateAddress']);