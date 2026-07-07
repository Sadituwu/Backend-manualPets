<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CulqiService
{
    /**
     * Crea un cargo en Culqi a partir del token generado por Culqi.js/Checkout
     * en el frontend. El monto se recibe en soles y se convierte a céntimos.
     *
     * @return array{exito: bool, id: ?string, mensaje: ?string}
     */
    public function crearCargo(string $tokenId, float $monto, string $email, string $descripcion): array
    {
        $secretKey = config('culqi.secret_key');

        if (empty($secretKey)) {
            Log::warning('CulqiService: falta configurar CULQI_SECRET_KEY.');

            return [
                'exito' => false,
                'id' => null,
                'mensaje' => 'La pasarela de pagos no está configurada todavía.',
            ];
        }

        try {
            $respuesta = Http::withToken($secretKey)
                ->post(config('culqi.base_url').'/charges', [
                    'amount' => (int) round($monto * 100),
                    'currency_code' => 'PEN',
                    'email' => $email,
                    'source_id' => $tokenId,
                    'description' => $descripcion,
                    'capture' => true,
                ]);

            $cuerpo = $respuesta->json();
            Log::info('CulqiService: respuesta de /charges.', ['status' => $respuesta->status(), 'body' => $cuerpo]);

            $esCargoValido = $respuesta->successful()
                && ($cuerpo['object'] ?? null) === 'charge'
                && ! empty($cuerpo['id']);

            if ($esCargoValido) {
                return [
                    'exito' => true,
                    'id' => $cuerpo['id'],
                    'mensaje' => null,
                ];
            }

            Log::error('CulqiService: cargo rechazado o respuesta inesperada.', ['respuesta' => $cuerpo]);

            return [
                'exito' => false,
                'id' => null,
                'mensaje' => $cuerpo['user_message'] ?? 'El pago fue rechazado.',
            ];
        } catch (\Throwable $e) {
            Log::error('CulqiService: error al crear el cargo.', ['error' => $e->getMessage()]);

            return [
                'exito' => false,
                'id' => null,
                'mensaje' => 'No se pudo procesar el pago en este momento.',
            ];
        }
    }
}
