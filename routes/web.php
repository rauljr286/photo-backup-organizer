<?php

use App\Http\Controllers\Web\AlbumController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\PhotoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('terms');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register/agree', [AuthController::class, 'agreeToTerms'])->name('register.agree');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/photos', [DashboardController::class, 'upload'])->name('photos.store');
    Route::post('/backup', [DashboardController::class, 'backupNow'])->name('backup.now');
    Route::get('/trash', [DashboardController::class, 'trash'])->name('trash');

    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
    Route::patch('/settings/name', [DashboardController::class, 'updateName'])->name('settings.name');
    Route::post('/settings/avatar', [DashboardController::class, 'updateAvatar'])->name('settings.avatar');
    Route::patch('/settings/password', [DashboardController::class, 'changePassword'])->name('settings.password');
    Route::delete('/settings/account', [DashboardController::class, 'deleteAccount'])->name('settings.delete-account');

    Route::get('/albums', [AlbumController::class, 'index'])->name('albums.index');
    Route::post('/albums', [AlbumController::class, 'store'])->name('albums.store');
    Route::get('/albums/{album}', [AlbumController::class, 'show'])->name('albums.show');
    Route::put('/albums/{album}', [AlbumController::class, 'update'])->name('albums.update');
    Route::delete('/albums/{album}', [AlbumController::class, 'destroy'])->name('albums.destroy');
    Route::post('/albums/{album}/photos', [AlbumController::class, 'addPhoto'])->name('albums.photos.add');
    Route::delete('/albums/{album}/photos', [AlbumController::class, 'removePhoto'])->name('albums.photos.remove');
    Route::get('/albums/{album}/download', [AlbumController::class, 'download'])->name('albums.download');

    Route::get('/photos/{photo}', [PhotoController::class, 'show'])->withTrashed()->name('photos.show');
    Route::get('/photos/{photo}/raw', [PhotoController::class, 'raw'])->withTrashed()->name('photos.raw');
    Route::get('/photos/{photo}/thumbnail', [PhotoController::class, 'thumbnail'])->withTrashed()->name('photos.thumbnail');
    Route::get('/photos/{photo}/download', [PhotoController::class, 'download'])->withTrashed()->name('photos.download');
    Route::post('/photos/download-selected', [PhotoController::class, 'downloadSelected'])->name('photos.download-selected');
    Route::patch('/photos/{photo}', [PhotoController::class, 'updateDiskMeta'])->withTrashed()->name('photos.update');
    Route::delete('/photos/{photo}', [PhotoController::class, 'trash'])->withTrashed()->name('photos.trash');
    Route::post('/photos/{photo}/restore', [PhotoController::class, 'restore'])->withTrashed()->name('photos.restore');
    Route::delete('/photos/{photo}/purge', [PhotoController::class, 'purge'])->withTrashed()->name('photos.purge');
});
