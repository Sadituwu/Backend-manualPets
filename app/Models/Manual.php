<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lote_id', 'titulo', 'descripcion', 'tipo', 'archivo_pdf', 'portada_url', 'estado'])]
class Manual extends Model
{
    protected $table = 'manuales';

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
}
