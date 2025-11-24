<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\userInfoController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\OrderController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::get('/user-info', [userInfoController::class, 'userInfo']);
Route::post('/user/add-address', [AddressController::class, 'addAddress']);
Route::get('/user/get-addresses', [AddressController::class, 'getAddresses']);
Route::put('/user/update-address', [AddressController::class, 'updateAddress']);


Route::post('/category/create', [ProductCategoryController::class, 'createCategory']);
Route::post('/subcategory/create', [ProductCategoryController::class, 'createSubCategory']);
Route::get('/category/all', [ProductCategoryController::class, 'getAllCategories']);


Route::post('/product/create', [ProductController::class, 'createProduct']);
Route::get('/product/all', [ProductController::class, 'getAllProducts']);

Route::post('/cart/add', [CartController::class, 'addToCart']);
Route::get('/cart/list', [CartController::class, 'getCartItems']);
Route::post('/cart/remove', [CartController::class, 'removeCartItem']);

Route::post('/wishlist/add', [WishlistController::class, 'addToWishlist']);
Route::get('/wishlist/list', [WishlistController::class, 'getWishlistItems']);
Route::post('/wishlist/remove', [WishlistController::class, 'removeWishlistItem']);


Route::post('/order/place', [OrderController::class, 'placeOrder']);
Route::post('/order/payment', [OrderController::class, 'initiatePayment']);
Route::post('/payment/callback', [OrderController::class, 'paymentCallback']);
Route::post('/order/update-status', [OrderController::class, 'updateOrderStatus']); // admin
Route::get('/order/list', [OrderController::class, 'orderList']); // user