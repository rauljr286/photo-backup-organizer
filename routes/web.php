<?php

use App\Http\Controllers\Web\AlbumController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\AvatarController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\InsightsController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\PhotoController;
use App\Http\Controllers\Web\PrivateController;
use App\Http\Controllers\Web\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('terms');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register/agree', [AuthController::class, 'agreeToTerms'])->name('register.agree');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');

    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/photos', [DashboardController::class, 'upload'])->name('photos.store');
    Route::post('/backup', [DashboardController::class, 'backupNow'])->name('backup.now');
    Route::get('/archive', [DashboardController::class, 'archive'])->name('archive');
    Route::get('/photos/latest', [DashboardController::class, 'latest'])->name('photos.latest');

    Route::get('/insights', [InsightsController::class, 'show'])->name('insights');
    Route::get('/cleanup', [InsightsController::class, 'cleanup'])->name('cleanup');

    Route::get('/private', [PrivateController::class, 'index'])->name('private');
    Route::post('/private/password', [PrivateController::class, 'storePassword'])->name('private.password');
    Route::post('/private/unlock', [PrivateController::class, 'unlock'])->name('private.unlock');
    Route::post('/private/lock', [PrivateController::class, 'lock'])->name('private.lock');
    Route::post('/private/reset', [PrivateController::class, 'resetPassword'])->name('private.reset');
    Route::post('/private/photos', [PrivateController::class, 'upload'])->name('private.photos.store');

    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
    Route::get('/profile', [DashboardController::class, 'settings'])->name('profile');
    Route::get('/profile/security', [DashboardController::class, 'settingsSecurity'])->name('profile.security');
    Route::get('/profile/privacy', [DashboardController::class, 'settingsPrivacy'])->name('profile.privacy');
    Route::get('/profile/private', [DashboardController::class, 'settingsPrivate'])->name('profile.private');
    Route::patch('/settings/name', [DashboardController::class, 'updateName'])->name('settings.name');
    Route::post('/settings/avatar', [DashboardController::class, 'updateAvatar'])->name('settings.avatar');
    Route::patch('/settings/password', [DashboardController::class, 'changePassword'])->name('settings.password');
    Route::post('/profile/logout-others', [DashboardController::class, 'logoutOtherSessions'])->name('profile.logout-others');
    Route::post('/profile/deletion-request', [DashboardController::class, 'requestAccountDeletion'])->name('profile.deletion-request');
    Route::delete('/profile/deletion-request', [DashboardController::class, 'cancelAccountDeletion'])->name('profile.deletion-request.cancel');

    Route::get('/avatar/{user}', [AvatarController::class, 'show'])->name('avatar');

    Route::get('/albums', [AlbumController::class, 'index'])->name('albums.index');
    Route::post('/albums', [AlbumController::class, 'store'])->name('albums.store');
    Route::get('/albums/{album}', [AlbumController::class, 'show'])->name('albums.show');
    Route::put('/albums/{album}', [AlbumController::class, 'update'])->name('albums.update');
    Route::delete('/albums/{album}', [AlbumController::class, 'destroy'])->name('albums.destroy');
    Route::post('/albums/{album}/photos', [AlbumController::class, 'addPhoto'])->name('albums.photos.add');
    Route::delete('/albums/{album}/photos', [AlbumController::class, 'removePhoto'])->name('albums.photos.remove');
    Route::get('/albums/{album}/download', [AlbumController::class, 'download'])->name('albums.download');

    Route::get('/photos/{photo}', [PhotoController::class, 'show'])->name('photos.show');
    Route::get('/photos/{photo}/raw', [PhotoController::class, 'raw'])->name('photos.raw');
    Route::get('/photos/{photo}/thumbnail', [PhotoController::class, 'thumbnail'])->name('photos.thumbnail');
    Route::get('/photos/{photo}/download', [PhotoController::class, 'download'])->name('photos.download');
    Route::post('/photos/bulk-update', [PhotoController::class, 'updateBulkMeta'])->name('photos.bulk-update');
    Route::post('/photos/download-selected', [PhotoController::class, 'downloadSelected'])->name('photos.download-selected');
    Route::patch('/photos/{photo}', [PhotoController::class, 'updateDiskMeta'])->name('photos.update');
    Route::post('/photos/{photo}/rotate', [PhotoController::class, 'rotate'])->name('photos.rotate');
    Route::delete('/photos/{photo}', [PhotoController::class, 'archive'])->name('photos.archive');
    Route::post('/photos/{photo}/unarchive', [PhotoController::class, 'unarchive'])->name('photos.unarchive');
});
