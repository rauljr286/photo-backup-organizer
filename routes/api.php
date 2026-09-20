<?php

use App\Http\Controllers\Api\AlbumController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PhotoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes require a valid Sanctum token (Bearer). Register, login and
| the raw photo URL are the only publicly reachable endpoints.
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/photos', [PhotoController::class, 'index']);
    Route::post('/photos', [PhotoController::class, 'store']);
    Route::get('/photos/{photo}', [PhotoController::class, 'show']);
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
    Route::post('/photos/{photo}/restore', [PhotoController::class, 'restore']);
    // Web-only convenience route so the browser can display the binary:
    Route::get('/photos/{photo}/raw', [PhotoController::class, 'raw'])->middleware('auth:sanctum');

    Route::get('/albums', [AlbumController::class, 'index']);
    Route::post('/albums', [AlbumController::class, 'store']);
    Route::put('/albums/{album}', [AlbumController::class, 'update']);
    Route::delete('/albums/{album}', [AlbumController::class, 'destroy']);
    Route::post('/albums/{album}/photos/{photo}', [AlbumController::class, 'addPhoto']);
    Route::delete('/albums/{album}/photos/{photo}', [AlbumController::class, 'removePhoto']);
});
