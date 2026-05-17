<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;

use App\Http\Controllers\Api\AdminController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/user/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/update-pin', [AuthController::class, 'updatePin']);
    Route::post('/user/update-kyc', [AuthController::class, 'updateKyc']);

    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'getBalance']);
        Route::post('/transfer', [WalletController::class, 'transfer']);
        Route::get('/transactions', [WalletController::class, 'getTransactions']);
    });

    Route::prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'getStats']);
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::post('/users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::get('/kyc/pending', [AdminController::class, 'getPendingKyc']);
        Route::post('/kyc/{id}/validate', [AdminController::class, 'validateKyc']);
        Route::get('/transactions', [AdminController::class, 'getTransactions']);
        Route::post('/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus']);

        // Banners
        Route::get('/banners', [AdminController::class, 'getBanners']);
        Route::post('/banners', [AdminController::class, 'createBanner']);
        Route::patch('/banners/{id}', [AdminController::class, 'updateBanner']);
        Route::delete('/banners/{id}', [AdminController::class, 'deleteBanner']);
        Route::get('/settings', [AdminController::class, 'getSettings']);
        Route::post('/settings', [AdminController::class, 'saveSettings']);
    });

    // Public Banners for Client
    Route::get('/public/banners', function () {
        return App\Models\Banner::where('is_active', true)->orderBy('order')->get();
    });
});
