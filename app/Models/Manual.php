<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lote_id', 'titulo', 'descripcion', 'tipo', 'precio', 'archivo_pdf', 'portada_url', 'estado'])]
class Manual extends Model
{
    protected $table = 'manuales';

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Lote, $this>
     */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(Lote::class);
    }

    public function esGratis(): bool
    {
        return $this->tipo === 'gratis';
    }

    public function esVentaIndividual(): bool
    {
        return $this->tipo === 'premium' && $this->lote_id === null;
    }
}
