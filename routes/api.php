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
Route::get('/customers-list', [userInfoController::class, 'getAllUsers']); 
Route::get('/customers-list-byId', [userInfoController::class, 'getUserById']);

Route::get('/user/info', [userInfoController::class, 'userInfo']);
Route::put('/user/update', [userInfoController::class, 'updateUserInfo']);
Route::delete('/user/delete', [userInfoController::class, 'deleteUser']);
Route::post('/address/add', [AddressController::class, 'addAddress']);
Route::get('/address/list', [AddressController::class, 'getAddresses']);
Route::put('/address/update', [AddressController::class, 'updateAddress']);
Route::delete('/address/delete', [AddressController::class, 'deleteAddress']);
Route::get('/address/by-user', [AddressController::class, 'getAddressByUserId']);


Route::post('/category/create', [ProductCategoryController::class, 'createCategory']);
Route::post('/subcategory/create', [ProductCategoryController::class, 'createSubCategory']);
Route::get('/category/all', [ProductCategoryController::class, 'getAllCategories']);
Route::get('/subcategories', [ProductCategoryController::class, 'getAllSubCategories']);
Route::get('/subcategories/by-category', [ProductCategoryController::class, 'getSubCategoriesByCategory']);
Route::put('/category/update', [ProductCategoryController::class, 'updateCategory']);
Route::delete('/category/delete', [ProductCategoryController::class, 'deleteCategory']);
Route::put('/subcategories/update', [ProductCategoryController::class, 'updateSubCategory']);
Route::delete('/subcategories/delete', [ProductCategoryController::class, 'deleteSubCategory']);

Route::post('/product/create', [ProductController::class, 'createProduct']);
Route::get('/product/all', [ProductController::class, 'getAllProducts']);
Route::get('/products/by-category', [ProductController::class, 'getProductsByCategory']);
Route::get('/products/by-subcategory', [ProductController::class, 'getProductsBySubCategory']);
Route::get('/products/filter', [ProductController::class, 'getProductsFiltered']);
Route::post('/product/update', [ProductController::class, 'updateProduct']);
Route::delete('/product/delete', [ProductController::class, 'deleteProduct']);
Route::get('/product/by-id', [ProductController::class, 'getProductById']);

Route::post('/cart/add', [CartController::class, 'addToCart']);
Route::get('/cart/list', [CartController::class, 'getCartItems']);
Route::post('/cart/remove', [CartController::class, 'removeCartItem']);

Route::post('/wishlist/add', [WishlistController::class, 'addToWishlist']);
Route::get('/wishlist/list', [WishlistController::class, 'getWishlistItems']);
Route::post('/wishlist/remove', [WishlistController::class, 'removeWishlistItem']);

Route::post('/order/place', [OrderController::class, 'placeOrder']);
Route::post('/order/payment', [OrderController::class, 'initiatePayment']);
Route::post('/payment/callback', [OrderController::class, 'paymentCallback']);
Route::put('/order/update-status', [OrderController::class, 'updateOrderStatus']); // admin
Route::get('/order/list', [OrderController::class, 'orderList']); // user
Route::get('/orders', [OrderController::class, 'getUserOrders']);
Route::get('/orders_byorderId', [OrderController::class, 'getOrderByOrderId']);