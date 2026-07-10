<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['usuario_id', 'lote_id', 'manual_id', 'monto', 'estado', 'culqi_charge_id', 'metodo_pago', 'fecha_pago'])]
class Compra extends Model
{
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * @return BelongsTo<Lote, $this>
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    /**
     * @return BelongsTo<Manual, $this>
     */
    public function manual(): BelongsTo
    {
        return $this->belongsTo(Manual::class);
    }

    public function estaPagada(): bool
    {
        return $this->estado === 'pagado';
    }
}
