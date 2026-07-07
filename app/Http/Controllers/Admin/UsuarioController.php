<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::withCount(['compras' => fn ($query) => $query->where('estado', 'pagado')])
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(fn ($q) => $q->where('nombres', 'like', "%{$buscar}%")
                    ->orWhere('apellidos', 'like', "%{$buscar}%")
                    ->orWhere('dni', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%"));
            })
            ->latest()
            ->get();

        return response()->json($usuarios);
    }

    public function update(Request $request, string $id)
    {
        $usuario = User::findOrFail($id);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'rol' => ['sometimes', Rule::in(['usuario', 'admin'])],
            'estado' => ['sometimes', Rule::in(['activo', 'inactivo'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $usuario->update($request->only(['rol', 'estado']));

        return response()->json($usuario->fresh());
    }
}
