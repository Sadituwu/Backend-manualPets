@component('emails.layout')
    <h2 style="margin:0 0 4px; font-size:18px; color:#111827;">Activa tu cuenta</h2>
    <p style="margin:0 0 20px; font-size:14px; color:#6b7280;">
        Hola{{ $usuario->nombres ? ', '.$usuario->nombres : '' }}, gracias por registrarte en Manual-Pets.
        Antes de poder iniciar sesión, confirma que este es tu correo haciendo clic en el siguiente botón:
    </p>

    <a href="{{ $url }}" style="display:inline-block; background-color:#4f46e5; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:8px; font-size:14px; font-weight:600;">
        Activar mi cuenta
    </a>

    <p style="margin:24px 0 0; font-size:13px; color:#9ca3af;">
        Si no creaste una cuenta en Manual-Pets, puedes ignorar este correo. Si el botón no funciona,
        copia y pega este enlace en tu navegador:
    </p>
    <p style="margin:8px 0 0; font-size:13px; color:#4f46e5; word-break:break-all;">{{ $url }}</p>
@endcomponent
