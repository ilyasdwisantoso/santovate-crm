<?php
use App\Http\Controllers\IpaymuWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;
Route::post('/ipaymu/callback',IpaymuWebhookController::class)->name('api.ipaymu.callback');
Route::get('/whatsapp/webhook',[WhatsAppWebhookController::class,'verify'])->name('api.whatsapp.verify');
Route::post('/whatsapp/webhook',[WhatsAppWebhookController::class,'receive'])->name('api.whatsapp.receive');
