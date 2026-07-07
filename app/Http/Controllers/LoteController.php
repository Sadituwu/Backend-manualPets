<?php

namespace App\Http\Controllers;

use App\Models\Lote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LoteController extends Controller
{
    /**
     * Catálogo público de lotes premium activos.
     */
    public function index(Request $request)
    {
        $lotes = Lote::where('estado', 'activo')
            ->withCount('manuales')
            ->latest()
            ->get();

        if ($usuario = $request->user('sanctum')) {
            $comprados = $usuario->compras()
                ->where('estado', 'pagado')
                ->pluck('lote_id');

            $lotes->each(fn ($lote) => $lote->comprado = $comprados->contains($lote->id));
        }

        return response()->json($lotes);
    }

    public function show(Request $request, string $id)
    {
        $lote = Lote::withCount('manuales')->findOrFail($id);
        $lote->manuales = $lote->manuales()->where('estado', 'activo')->get(['id', 'lote_id', 'titulo', 'descripcion', 'portada_url']);

        if ($usuario = $request->user('sanctum')) {
            $lote->comprado = $usuario->compras()
                ->where('lote_id', $lote->id)
                ->where('estado', 'pagado')
                ->exists();
        }

        return response()->json($lote);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['required', 'numeric', 'min:0'],
            'portada' => ['nullable', 'image', 'max:4096'],
            'estado' => ['sometimes', 'in:activo,inactivo'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $request->only(['nombre', 'descripcion', 'precio', 'estado']);

        if ($request->hasFile('portada')) {
            $datos['portada_url'] = Storage::disk('public')->url(
                $request->file('portada')->store('lotes', 'public')
            );
        }

        $lote = Lote::create($datos);

        return response()->json($lote, 201);
    }

    public function update(Request $request, string $id)
    {
        $lote = Lote::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'precio' => ['sometimes', 'numeric', 'min:0'],
            'portada' => ['nullable', 'image', 'max:4096'],
            'estado' => ['sometimes', 'in:activo,inactivo'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $datos = $request->only(['nombre', 'descripcion', 'precio', 'estado']);

        if ($request->hasFile('portada')) {
            $datos['portada_url'] = Storage::disk('public')->url(
                $request->file('portada')->store('lotes', 'public')
            );
        }

        $lote->update($datos);

        return response()->json($lote->fresh());
    }

    public function destroy(string $id)
    {
        $lote = Lote::findOrFail($id);
        $lote->update(['estado' => 'inactivo']);

        return response()->json(['message' => 'Lote desactivado correctamente.']);
    }
}
