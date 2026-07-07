<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Brevo (smtp-relay.brevo.com) en Sudamérica a veces redirige la conexión
        // a un servidor regional cuyo certificado SSL no coincide con el host
        // configurado. Se reconstruye el transporte SMTP desactivando la
        // verificación estricta del certificado para evitar el corte de conexión.
        Mail::extend('smtp', function (array $config) {
            $factory = new EsmtpTransportFactory;

            $scheme = $config['scheme'] ?? (($config['port'] == 465) ? 'smtps' : 'smtp');

            $transport = $factory->create(new Dsn(
                $scheme,
                $config['host'],
                $config['username'] ?? null,
                $config['password'] ?? null,
                $config['port'] ?? null,
                $config
            ));

            $stream = $transport->getStream();

            if ($stream instanceof SocketStream) {
                $stream->setStreamOptions([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ]);
            }

            return $transport;
        });

        // Enlace de recuperación de contraseña apuntando al frontend (Vue),
        // en vez de a una vista Blade tradicional.
        ResetPassword::createUrlUsing(fn ($user, string $token) => config('app.frontend_url')
            .'/restablecer-password?token='.$token.'&email='.urlencode($user->email));

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = config('app.frontend_url')
                .'/restablecer-password?token='.$token.'&email='.urlencode($notifiable->email);

            return (new MailMessage)
                ->subject('Recupera tu contraseña - Manual-Pets')
                ->view('emails.recuperar_contrasena', [
                    'usuario' => $notifiable,
                    'url' => $url,
                    'minutos' => config('auth.passwords.users.expire', 60),
                ]);
        });

        // Correo de verificación de cuenta con plantilla propia. La URL firmada
        // sigue apuntando a la ruta web 'verification.verify' (backend), que
        // valida la firma y redirige al frontend con el resultado.
        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verifica tu correo - Manual-Pets')
                ->view('emails.verificar_correo', [
                    'usuario' => $notifiable,
                    'url' => $url,
                ]);
        });

        $respuestaLimiteExcedido = fn (string $mensaje) => fn ($request, array $headers) => response()->json([
            'message' => $mensaje,
        ], 429, $headers);

        // Login: 5 intentos por minuto, combinando IP + correo (para no bloquear
        // a todos los usuarios detrás de la misma IP si uno solo falla mucho).
        RateLimiter::for('login', function (Request $request) use ($respuestaLimiteExcedido) {
            $llave = strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($llave)->response($respuestaLimiteExcedido(
                'Demasiados intentos de inicio de sesión. Espera un minuto e intenta nuevamente.'
            ));
        });

        // Endpoints públicos que disparan un envío de correo real (registro,
        // olvidé mi contraseña, reenviar activación): 3 por minuto por IP,
        // para evitar spam/acoso a terceros y no agotar la cuota de Brevo.
        RateLimiter::for('correos-publicos', function (Request $request) use ($respuestaLimiteExcedido) {
            return Limit::perMinute(3)->by($request->ip())->response($respuestaLimiteExcedido(
                'Demasiadas solicitudes. Espera un minuto antes de intentarlo de nuevo.'
            ));
        });
    }
}
