<?php

use App\Http\Controllers\MatchingController;
use App\Http\Controllers\StaffProfileController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\Administrator;
use App\Http\Middleware\WorkspaceAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'matching.index' : 'login'))->name('home');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('matching.index'))->name('dashboard');
    Route::middleware(Administrator::class)->group(function () {
        Route::get('/users/{user}/delete-confirm', [UserController::class, 'confirmDelete'])->name('users.confirm-delete');
        Route::resource('users', UserController::class);
    });
    Route::get('/profile', [StaffProfileController::class, 'edit'])->name('staff-profile.edit');
    Route::put('/profile', [StaffProfileController::class, 'update'])->name('staff-profile.update');
    Route::get('/profile/password', [StaffProfileController::class, 'password'])->name('staff-profile.password');
    Route::put('/profile/password', [StaffProfileController::class, 'updatePassword'])->name('staff-profile.update-password');
    Route::middleware(WorkspaceAccess::class)->group(function () {
        Route::get('/matching', [MatchingController::class, 'index'])->name('matching.index');
        Route::post('/matching', [MatchingController::class, 'store'])->name('matching.store');
        Route::get('/matching-sample', [MatchingController::class, 'sample'])->name('matching.sample');
        Route::get('/matching/{batch}/edit', [MatchingController::class, 'edit'])->name('matching.edit');
        Route::put('/matching/{batch}', [MatchingController::class, 'update'])->name('matching.update');
        Route::get('/matching/{batch}/delete-confirm', [MatchingController::class, 'confirmDelete'])->name('matching.confirm-delete');
        Route::delete('/matching/{batch}', [MatchingController::class, 'destroy'])->name('matching.destroy');
        Route::get('/matching/{batch}', [MatchingController::class, 'show'])->name('matching.show');
        Route::post('/matching/{batch}/posts', [MatchingController::class, 'post'])->name('matching.post');
        Route::post('/matching/{batch}/posts/{post}/upload', [MatchingController::class, 'upload'])->name('matching.upload');
        Route::get('/matching/{batch}/posts/{post}/records', [MatchingController::class, 'records'])->name('matching.records');
        Route::delete('/matching/{batch}/posts/{post}/data', [MatchingController::class, 'reset'])->name('matching.reset');
        Route::get('/matching/{batch}/review', [MatchingController::class, 'review'])->name('matching.review');
        Route::post('/matching/{batch}/review/{record}', [MatchingController::class, 'decide'])->name('matching.decide');
        Route::get('/matching/{batch}/export', [MatchingController::class, 'export'])->name('matching.export');
        Route::get('/matching/{batch}/posts/{post}/export', [MatchingController::class, 'export'])->name('matching.post-export');
    });
});
