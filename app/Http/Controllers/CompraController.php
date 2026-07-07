<?php

namespace App\Http\Controllers;

use App\Mail\CompraConfirmada;
use App\Models\Compra;
use App\Models\Lote;
use App\Services\CulqiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class CompraController extends Controller
{
    public function __construct(private readonly CulqiService $culqi) {}

    /**
     * Compras del usuario autenticado.
     */
    public function index(Request $request)
    {
        $compras = Compra::with('lote')
            ->where('usuario_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($compras);
    }

    public function show(Request $request, string $id)
    {
        $compra = Compra::with('lote')->findOrFail($id);

        if ($compra->usuario_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json($compra);
    }

    /**
     * Procesa el checkout del carrito: uno o varios lotes en un solo cobro de Culqi.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lote_ids' => ['required', 'array', 'min:1'],
            'lote_ids.*' => ['integer', 'exists:lotes,id'],
            'culqi_token' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $usuario = $request->user();
        $loteIds = array_unique($request->lote_ids);

        $yaComprados = Compra::where('usuario_id', $usuario->id)
            ->whereIn('lote_id', $loteIds)
            ->where('estado', 'pagado')
            ->pluck('lote_id');

        if ($yaComprados->isNotEmpty()) {
            return response()->json(['message' => 'Ya adquiriste uno o más de los lotes seleccionados.'], 409);
        }

        $lotes = Lote::whereIn('id', $loteIds)->get();
        $montoTotal = (float) $lotes->sum('precio');

        $compras = DB::transaction(function () use ($usuario, $lotes) {
            return $lotes->map(fn ($lote) => Compra::create([
                'usuario_id' => $usuario->id,
                'lote_id' => $lote->id,
                'monto' => $lote->precio,
                'estado' => 'pendiente',
            ]));
        });

        $descripcion = $lotes->count() === 1
            ? "Manual-Pets - Lote: {$lotes->first()->nombre}"
            : "Manual-Pets - Carrito ({$lotes->count()} lotes)";

        $resultado = $this->culqi->crearCargo($request->culqi_token, $montoTotal, $usuario->email, $descripcion);

        if ($resultado['exito']) {
            foreach ($compras as $compra) {
                $compra->update([
                    'estado' => 'pagado',
                    'culqi_charge_id' => $resultado['id'],
                    'metodo_pago' => 'culqi',
                    'fecha_pago' => now(),
                ]);
            }

            $comprasConfirmadas = $compras->fresh('lote');

            try {
                Mail::to($usuario->email)->send(new CompraConfirmada($usuario, $comprasConfirmadas));
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar el correo de confirmación de compra.', ['error' => $e->getMessage()]);
            }

            return response()->json($comprasConfirmadas, 201);
        }

        foreach ($compras as $compra) {
            $compra->update(['estado' => 'fallido']);
        }

        return response()->json([
            'message' => $resultado['mensaje'] ?? 'No se pudo procesar el pago.',
        ], 402);
    }
}
