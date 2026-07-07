<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'unique:users,dni'],
            'direccion' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $usuario = User::create([
            'nombres' => $request->nombres,
            'apellidos' => $request->apellidos,
            'dni' => $request->dni,
            'direccion' => $request->direccion,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rol' => 'usuario',
            'estado' => 'activo',
        ]);

        try {
            $usuario->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de verificación.', ['error' => $e->getMessage()]);
        }

        // No se emite token: la cuenta requiere activación (verificar correo) antes de poder iniciar sesión.
        return response()->json([
            'message' => 'Cuenta creada correctamente. Revisa tu correo para activarla antes de iniciar sesión.',
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $usuario = User::where('email', $request->email)->first();

        if (! $usuario || ! Hash::check($request->password, $usuario->password)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        if ($usuario->estado !== 'activo') {
            return response()->json(['message' => 'Tu cuenta se encuentra inactiva.'], 403);
        }

        if (! $usuario->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Debes activar tu cuenta antes de iniciar sesión. Revisa el correo que te enviamos.',
                'requiere_verificacion' => true,
            ], 403);
        }

        $token = $usuario->createToken('manual-pets')->plainTextToken;

        return response()->json([
            'usuario' => $usuario,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    public function usuario(Request $request)
    {
        return response()->json($request->user());
    }

    public function actualizarPerfil(Request $request)
    {
        $usuario = $request->user();

        // Nombres, apellidos, DNI y correo son inmutables desde el perfil del
        // usuario (solo un administrador podría corregirlos manualmente).
        $validator = Validator::make($request->all(), [
            'direccion' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $request->only(['direccion']);

        if ($request->filled('password')) {
            $datos['password'] = Hash::make($request->password);
        }

        $usuario->update($datos);

        return response()->json($usuario->fresh());
    }

    /**
     * Reenvía el correo de verificación al usuario autenticado.
     */
    public function reenviarVerificacion(Request $request)
    {
        $usuario = $request->user();

        if ($usuario->hasVerifiedEmail()) {
            return response()->json(['message' => 'Tu correo ya está verificado.']);
        }

        $usuario->sendEmailVerificationNotification();

        return response()->json(['message' => 'Te enviamos un nuevo correo de verificación.']);
    }

    /**
     * Reenvía el correo de activación sin necesidad de estar autenticado
     * (el usuario aún no puede loguearse porque no ha verificado su cuenta).
     */
    public function reenviarVerificacionPublico(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $usuario = User::where('email', $request->email)->first();

        if ($usuario && ! $usuario->hasVerifiedEmail()) {
            $usuario->sendEmailVerificationNotification();
        }

        // Mensaje genérico siempre (no revelar si el correo existe o ya está verificado).
        return response()->json([
            'message' => 'Si el correo existe y no está activado, te enviamos un nuevo enlace de activación.',
        ]);
    }

    /**
     * Envía el correo con el enlace para restablecer la contraseña.
     */
    public function olvidePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Password::sendResetLink($request->only('email'));

        // Mensaje genérico siempre, exista o no el correo (no revelar cuentas registradas).
        return response()->json([
            'message' => 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.',
        ]);
    }

    /**
     * Restablece la contraseña usando el token recibido por correo.
     */
    public function restablecerPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8), 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $usuario, string $password) {
                $usuario->update(['password' => Hash::make($password)]);
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'El enlace no es válido o ya expiró.'], 400);
        }

        return response()->json(['message' => 'Tu contraseña se actualizó correctamente.']);
    }
}
