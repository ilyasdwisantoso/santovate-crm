<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\FollowUpTemplateController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\SalesTargetController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login',[AuthenticatedSessionController::class,'create'])->name('login');
    Route::post('/login',[AuthenticatedSessionController::class,'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout',[AuthenticatedSessionController::class,'destroy'])->name('logout');

    Route::get('/profile',[ProfileController::class,'edit'])->name('profile.edit');
    Route::patch('/profile',[ProfileController::class,'update'])->name('profile.update');

    Route::get('/',DashboardController::class)->name('dashboard');
    Route::patch('/prospects/{prospect}/status',[ProspectController::class,'updateStatus'])->name('prospects.status');
    Route::resource('prospects',ProspectController::class);
    Route::post('/prospects/{prospect}/activities',[ActivityController::class,'store'])->name('prospects.activities.store');

    Route::get('/pipeline',PipelineController::class)->name('pipeline');

    Route::get('/follow-ups',[FollowUpController::class,'index'])->name('follow-ups.index');
    Route::post('/follow-ups/{prospect}/customer-reply',[FollowUpController::class,'markCustomerReply'])->name('follow-ups.customer-reply');
    Route::post('/follow-ups/{prospect}/sent',[FollowUpController::class,'markSent'])->name('follow-ups.sent');
    Route::post('/follow-ups/{prospect}/feedback',[FollowUpController::class,'markFeedback'])->name('follow-ups.feedback');
    Route::post('/follow-ups/{prospect}/snooze',[FollowUpController::class,'snooze'])->name('follow-ups.snooze');

    Route::get('/targets',[SalesTargetController::class,'index'])->name('targets.index');

    Route::post('/notifications/{notification}/read',[NotificationController::class,'read'])->name('notifications.read');
    Route::post('/notifications/read-all',[NotificationController::class,'readAll'])->name('notifications.read-all');

    Route::middleware('role:admin')->group(function () {
        Route::put('/targets/{user}',[SalesTargetController::class,'update'])->name('targets.update');
        Route::put('/follow-up-templates/{template}',[FollowUpTemplateController::class,'update'])->name('follow-up-templates.update');

        Route::get('/imports',[ImportController::class,'index'])->name('imports.index');
        Route::get('/imports/template',[ImportController::class,'template'])->name('imports.template');
        Route::get('/imports/sample',[ImportController::class,'sample'])->name('imports.sample');
        Route::post('/imports/preview',[ImportController::class,'preview'])->name('imports.preview');
        Route::post('/imports/commit',[ImportController::class,'commit'])->name('imports.commit');
        Route::get('/imports/{batch}',[ImportController::class,'show'])->name('imports.show');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('users',UserController::class)->except(['show','destroy']);
        });
    });
});
