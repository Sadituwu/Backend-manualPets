<?php

namespace App\Http\Controllers;

use App\Models\Manual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ManualController extends Controller
{
    /**
     * Catálogo público de manuales (gratis y premium) activos.
     */
    public function index(Request $request)
    {
        $manuales = Manual::with('lote:id,nombre,precio')
            ->where('estado', 'activo')
            ->latest()
            ->get(['id', 'lote_id', 'titulo', 'descripcion', 'tipo', 'precio', 'portada_url', 'estado', 'created_at']);

        $usuario = $request->user('sanctum');
        $lotesComprados = $usuario
            ? $usuario->compras()->where('estado', 'pagado')->pluck('lote_id')
            : collect();
        $manualesComprados = $usuario
            ? $usuario->compras()->where('estado', 'pagado')->pluck('manual_id')
            : collect();

        $manuales->each(function ($manual) use ($lotesComprados, $manualesComprados) {
            if ($manual->tipo !== 'premium') {
                $manual->bloqueado = false;
            } elseif ($manual->lote_id !== null) {
                $manual->bloqueado = ! $lotesComprados->contains($manual->lote_id);
            } else {
                $manual->bloqueado = ! $manualesComprados->contains($manual->id);
            }
        });

        return response()->json($manuales);
    }

    public function show(Request $request, string $id)
    {
        $manual = Manual::with('lote:id,nombre,precio')->findOrFail($id);
        $manual->bloqueado = $this->estaBloqueado($request, $manual);

        return response()->json($manual);
    }

    /**
     * Descarga/visualización del PDF, verificando acceso.
     */
    public function descargar(Request $request, string $id)
    {
        $manual = Manual::findOrFail($id);

        if ($this->estaBloqueado($request, $manual)) {
            abort(403, 'Debes adquirir el lote premium para acceder a este manual.');
        }

        if (! Storage::disk('local')->exists($manual->archivo_pdf)) {
            abort(404, 'El archivo no está disponible.');
        }

        return Storage::disk('local')->response($manual->archivo_pdf, "{$manual->titulo}.pdf");
    }

    private function estaBloqueado(Request $request, Manual $manual): bool
    {
        if ($manual->tipo !== 'premium') {
            return false;
        }

        $usuario = $request->user('sanctum');

        if (! $usuario) {
            return true;
        }

        if ($manual->lote_id !== null) {
            return ! $usuario->compras()
                ->where('lote_id', $manual->lote_id)
                ->where('estado', 'pagado')
                ->exists();
        }

        return ! $usuario->compras()
            ->where('manual_id', $manual->id)
            ->where('estado', 'pagado')
            ->exists();
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'tipo' => ['required', 'in:gratis,premium'],
            'lote_id' => ['nullable', 'integer', 'exists:lotes,id'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'archivo_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'portada' => ['nullable', 'image', 'max:4096'],
            'estado' => ['sometimes', 'in:activo,inactivo'],
        ]);

        $loteId = $request->filled('lote_id') ? $request->input('lote_id') : null;
        $precio = $request->filled('precio') ? $request->input('precio') : null;

        $this->validarPrecioVsLote($validator, $request->input('tipo'), $loteId, $precio);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $request->only(['titulo', 'descripcion', 'tipo', 'estado']);
        $datos['lote_id'] = $loteId;
        $datos['precio'] = $datos['tipo'] === 'premium' && ! $loteId ? $precio : null;
        $datos['archivo_pdf'] = $request->file('archivo_pdf')->store('manuales', 'local');

        if ($request->hasFile('portada')) {
            $datos['portada_url'] = Storage::disk('public')->url(
                $request->file('portada')->store('manuales-portadas', 'public')
            );
        }

        $manual = Manual::create($datos);

        return response()->json($manual, 201);
    }

    /**
     * Un manual premium debe pertenecer a un lote O tener precio propio, no ambos ni ninguno.
     * Se usa ->after() porque agregar el error directo al MessageBag antes de fails()
     * se pierde: fails() vuelve a ejecutar la validación desde cero.
     */
    private function validarPrecioVsLote($validator, ?string $tipo, $loteId, $precio): void
    {
        $validator->after(function ($validator) use ($tipo, $loteId, $precio) {
            if ($tipo === 'premium' && empty($loteId) && ! $precio) {
                $validator->errors()->add('precio', 'Debes indicar un precio o vincular el manual a un lote.');
            }
        });
    }

    public function update(Request $request, string $id)
    {
        $manual = Manual::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'titulo' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'tipo' => ['sometimes', 'in:gratis,premium'],
            'lote_id' => ['nullable', 'integer', 'exists:lotes,id'],
            'precio' => ['nullable', 'numeric', 'min:0'],
            'archivo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'portada' => ['nullable', 'image', 'max:4096'],
            'estado' => ['sometimes', 'in:activo,inactivo'],
        ]);

        $tipo = $request->input('tipo', $manual->tipo);
        $loteId = $request->filled('lote_id') ? $request->input('lote_id') : null;
        $precio = $request->filled('precio') ? $request->input('precio') : null;

        $this->validarPrecioVsLote($validator, $tipo, $loteId, $precio);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $request->only(['titulo', 'descripcion', 'estado']);
        $datos['tipo'] = $tipo;
        $datos['lote_id'] = $loteId;
        $datos['precio'] = $tipo === 'premium' && ! $loteId ? $precio : null;

        if ($request->hasFile('archivo_pdf')) {
            Storage::disk('local')->delete($manual->archivo_pdf);
            $datos['archivo_pdf'] = $request->file('archivo_pdf')->store('manuales', 'local');
        }

        if ($request->hasFile('portada')) {
            $datos['portada_url'] = Storage::disk('public')->url(
                $request->file('portada')->store('manuales-portadas', 'public')
            );
        }

        $manual->update($datos);

        return response()->json($manual->fresh());
    }

    public function destroy(string $id)
    {
        $manual = Manual::findOrFail($id);
        $manual->update(['estado' => 'inactivo']);

        return response()->json(['message' => 'Manual desactivado correctamente.']);
    }
}
