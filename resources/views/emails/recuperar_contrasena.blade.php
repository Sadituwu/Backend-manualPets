@component('emails.layout')
    <h2 style="margin:0 0 4px; font-size:18px; color:#111827;">Recupera tu contraseña</h2>
    <p style="margin:0 0 20px; font-size:14px; color:#6b7280;">
        Recibimos una solicitud para restablecer la contraseña de tu cuenta en Manual-Pets
        ({{ $usuario->email }}). Si no fuiste tú, puedes ignorar este correo.
    </p>

    <a href="{{ $url }}" style="display:inline-block; background-color:#4f46e5; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:8px; font-size:14px; font-weight:600;">
        Restablecer contraseña
    </a>

    <p style="margin:24px 0 0; font-size:13px; color:#9ca3af;">
        Este enlace vence en {{ $minutos }} minutos. Si el botón no funciona, copia y pega este enlace en tu navegador:
    </p>
    <p style="margin:8px 0 0; font-size:13px; color:#4f46e5; word-break:break-all;">{{ $url }}</p>
@endcomponent
