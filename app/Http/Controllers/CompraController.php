<?php

namespace App\Http\Controllers;

use App\Mail\CompraConfirmada;
use App\Models\Compra;
use App\Models\Lote;
use App\Models\Manual;
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
        $compras = Compra::with(['lote', 'manual'])
            ->where('usuario_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($compras);
    }

    public function show(Request $request, string $id)
    {
        $compra = Compra::with(['lote', 'manual'])->findOrFail($id);

        if ($compra->usuario_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json($compra);
    }

    /**
     * Procesa el checkout del carrito: lotes y/o manuales sueltos en un solo cobro de Culqi.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lote_ids' => ['nullable', 'array'],
            'lote_ids.*' => ['integer', 'exists:lotes,id'],
            'manual_ids' => ['nullable', 'array'],
            'manual_ids.*' => ['integer', 'exists:manuales,id'],
            'culqi_token' => ['required', 'string'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if (empty($request->input('lote_ids')) && empty($request->input('manual_ids'))) {
                $validator->errors()->add('lote_ids', 'El carrito está vacío.');
            }
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $usuario = $request->user();
        $loteIds = array_unique($request->input('lote_ids', []));
        $manualIds = array_unique($request->input('manual_ids', []));

        $lotesYaComprados = Compra::where('usuario_id', $usuario->id)
            ->whereIn('lote_id', $loteIds)
            ->where('estado', 'pagado')
            ->exists();

        $manualesYaComprados = Compra::where('usuario_id', $usuario->id)
            ->whereIn('manual_id', $manualIds)
            ->where('estado', 'pagado')
            ->exists();

        if ($lotesYaComprados || $manualesYaComprados) {
            return response()->json(['message' => 'Ya adquiriste uno o más de los productos seleccionados.'], 409);
        }

        $lotes = Lote::whereIn('id', $loteIds)->get();
        $manuales = Manual::whereIn('id', $manualIds)->where('tipo', 'premium')->whereNull('lote_id')->get();

        if ($manuales->count() !== count($manualIds)) {
            return response()->json(['message' => 'Uno o más manuales seleccionados no están disponibles para venta individual.'], 422);
        }

        $montoTotal = (float) $lotes->sum('precio') + (float) $manuales->sum('precio');

        $compras = DB::transaction(function () use ($usuario, $lotes, $manuales) {
            $comprasLotes = $lotes->map(fn ($lote) => Compra::create([
                'usuario_id' => $usuario->id,
                'lote_id' => $lote->id,
                'monto' => $lote->precio,
                'estado' => 'pendiente',
            ]));

            $comprasManuales = $manuales->map(fn ($manual) => Compra::create([
                'usuario_id' => $usuario->id,
                'manual_id' => $manual->id,
                'monto' => $manual->precio,
                'estado' => 'pendiente',
            ]));

            return $comprasLotes->concat($comprasManuales);
        });

        $totalItems = $lotes->count() + $manuales->count();
        $descripcion = $totalItems === 1
            ? 'Manual-Pets - '.($lotes->first()->nombre ?? $manuales->first()->titulo)
            : "Manual-Pets - Carrito ({$totalItems} productos)";

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

            $comprasConfirmadas = $compras->fresh(['lote', 'manual']);

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
