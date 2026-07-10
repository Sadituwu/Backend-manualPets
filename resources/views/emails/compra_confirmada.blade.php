@component('emails.layout')
    <h2 style="margin:0 0 4px; font-size:18px; color:#111827;">¡Gracias por tu compra, {{ $usuario->nombres }}! 🎉</h2>
    <p style="margin:0 0 20px; font-size:14px; color:#6b7280;">
        Tu pago fue procesado correctamente. Aquí está el resumen de tu compra:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eef0f3; border-radius:8px; margin-bottom:20px;">
        @foreach ($compras as $compra)
            <tr>
                <td style="padding:12px 16px; border-bottom:1px solid #eef0f3; font-size:14px; color:#374151;">
                    {{ $compra->lote->nombre ?? $compra->manual->titulo }}
                </td>
                <td style="padding:12px 16px; border-bottom:1px solid #eef0f3; font-size:14px; color:#374151; text-align:right; white-space:nowrap;">
                    S/ {{ number_format((float) $compra->monto, 2) }}
                </td>
            </tr>
        @endforeach
        <tr>
            <td style="padding:12px 16px; font-size:14px; font-weight:700; color:#111827;">Total</td>
            <td style="padding:12px 16px; font-size:14px; font-weight:700; color:#111827; text-align:right;">
                S/ {{ number_format($total, 2) }}
            </td>
        </tr>
    </table>

    <p style="margin:0 0 4px; font-size:13px; color:#9ca3af;">N.º de operación: {{ $codigoOperacion }}</p>
    <p style="margin:0 0 20px; font-size:13px; color:#9ca3af;">Fecha: {{ $fecha }}</p>

    <p style="margin:0 0 20px; font-size:14px; color:#374151;">
        Ya puedes acceder a tus manuales desde la sección <strong>"Mis Compras"</strong> en la plataforma.
    </p>

    <a href="{{ $urlMisCompras }}" style="display:inline-block; background-color:#4f46e5; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:8px; font-size:14px; font-weight:600;">
        Ver mis compras
    </a>
@endcomponent
