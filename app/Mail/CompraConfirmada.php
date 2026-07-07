<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class CompraConfirmada extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\Compra>  $compras
     */
    public function __construct(
        public User $usuario,
        public Collection $compras,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Tu compra en Manual-Pets fue confirmada!',
        );
    }

    public function content(): Content
    {
        $primeraCompra = $this->compras->first();

        return new Content(
            view: 'emails.compra_confirmada',
            with: [
                'usuario' => $this->usuario,
                'compras' => $this->compras,
                'total' => (float) $this->compras->sum('monto'),
                'codigoOperacion' => $primeraCompra?->culqi_charge_id ?? '—',
                'fecha' => $primeraCompra?->fecha_pago?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'urlMisCompras' => config('app.frontend_url').'/mis-compras',
            ],
        );
    }
}
