<?php

declare(strict_types=1);

use App\Http\Controllers\Account\BindAvatarController;
use App\Http\Controllers\Account\UpdatePasswordController;
use App\Http\Controllers\Account\UpdateProfileController;
use App\Http\Controllers\Auth\AuthenticatedUserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/user', AuthenticatedUserController::class);

    /*
        Kullanıcı kendi hesabını kendi onarır (`docs/83`).

        Şifre yolu HIZ SINIRLI: mevcut şifre burada doğrulanıyor ve sınırsız
        deneme, oturumu açık bırakılmış bir makinede şifre tahmin etmenin
        yolu olurdu.
    */
    Route::put('/user/profile', UpdateProfileController::class);
    // Profil fotoğrafı bir medya varlığına bağlanır (`docs/49`), ayrı dosya değil.
    Route::put('/user/avatar', BindAvatarController::class);
    Route::put('/user/password', UpdatePasswordController::class)->middleware('throttle:6,1');
});
