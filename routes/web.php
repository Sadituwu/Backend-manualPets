<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/verificar-email/{id}/{hash}', function (Request $request, string $id, string $hash) {
    $usuario = User::findOrFail($id);

    if (! hash_equals($hash, sha1($usuario->getEmailForVerification()))) {
        return redirect(config('app.frontend_url').'/verificar-email?estado=invalido');
    }

    if (! $usuario->hasVerifiedEmail()) {
        $usuario->markEmailAsVerified();
    }

    return redirect(config('app.frontend_url').'/verificar-email?estado=ok');
})->middleware('signed')->name('verification.verify');
