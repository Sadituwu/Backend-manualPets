<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'descripcion', 'precio', 'portada_url', 'estado'])]
class Lote extends Model
{
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Manual, $this>
     */
    public function manuales(): HasMany
    {
        return $this->hasMany(Manual::class);
    }

    /**
     * @return HasMany<Compra, $this>
     */
    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }
}
