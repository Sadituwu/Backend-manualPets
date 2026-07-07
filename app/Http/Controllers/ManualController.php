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
            ->get(['id', 'lote_id', 'titulo', 'descripcion', 'tipo', 'portada_url', 'estado', 'created_at']);

        $usuario = $request->user('sanctum');
        $comprados = $usuario
            ? $usuario->compras()->where('estado', 'pagado')->pluck('lote_id')
            : collect();

        $manuales->each(function ($manual) use ($comprados) {
            $manual->bloqueado = $manual->tipo === 'premium' && ! $comprados->contains($manual->lote_id);
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

        return ! $usuario->compras()
            ->where('lote_id', $manual->lote_id)
            ->where('estado', 'pagado')
            ->exists();
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'tipo' => ['required', 'in:gratis,premium'],
            'lote_id' => ['required_if:tipo,premium', 'nullable', 'integer', 'exists:lotes,id'],
            'archivo_pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'portada' => ['nullable', 'image', 'max:4096'],
            'estado' => ['sometimes', 'in:activo,inactivo'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $request->only(['titulo', 'descripcion', 'tipo', 'lote_id', 'estado']);
        $datos['archivo_pdf'] = $request->file('archivo_pdf')->store('manuales', 'local');

        if ($request->hasFile('portada')) {
            $datos['portada_url'] = Storage::disk('public')->url(
                $request->file('portada')->store('manuales-portadas', 'public')
            );
        }

        $manual = Manual::create($datos);

        return response()->json($manual, 201);
    }

    public function update(Request $request, string $id)
    {
        $manual = Manual::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'titulo' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'tipo' => ['sometimes', 'in:gratis,premium'],
            'lote_id' => ['nullable', 'integer', 'exists:lotes,id'],
            'archivo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'portada' => ['nullable', 'image', 'max:4096'],
            'estado' => ['sometimes', 'in:activo,inactivo'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $request->only(['titulo', 'descripcion', 'tipo', 'lote_id', 'estado']);

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
