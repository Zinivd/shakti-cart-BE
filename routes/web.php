<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/razorpay-test', function () {
    return view('razorpay-test');
});