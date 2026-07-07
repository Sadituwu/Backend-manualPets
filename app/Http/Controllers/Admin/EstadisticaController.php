<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\Lote;
use App\Models\Manual;
use App\Models\User;

class EstadisticaController extends Controller
{
    public function index()
    {
        return response()->json([
            'usuarios_total' => User::count(),
            'usuarios_activos' => User::where('estado', 'activo')->count(),
            'lotes_total' => Lote::count(),
            'manuales_total' => Manual::count(),
            'manuales_gratis' => Manual::where('tipo', 'gratis')->count(),
            'manuales_premium' => Manual::where('tipo', 'premium')->count(),
            'compras_pagadas' => Compra::where('estado', 'pagado')->count(),
            'ingresos_totales' => (float) Compra::where('estado', 'pagado')->sum('monto'),
        ]);
    }
}
