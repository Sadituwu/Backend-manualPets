<?php

namespace Database\Seeders;

use App\Models\Lote;
use App\Models\Manual;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ManualSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pdfPlaceholder = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n";

        $loteBotellas = Lote::first();
        $loteTelas = Lote::skip(1)->first();

        Manual::create([
            'titulo' => 'Pelota Rebotadora con Tapas de Botella',
            'descripcion' => 'Manual gratuito: cómo hacer una pelota resistente con tapas recicladas.',
            'tipo' => 'gratis',
            'lote_id' => null,
            'archivo_pdf' => $this->guardarPlaceholder($pdfPlaceholder),
            'estado' => 'activo',
        ]);

        Manual::create([
            'titulo' => 'Dispensador de Snacks con Botella PET',
            'descripcion' => 'Aprende a construir un dispensador de premios para tu mascota.',
            'tipo' => 'premium',
            'lote_id' => $loteBotellas->id,
            'archivo_pdf' => $this->guardarPlaceholder($pdfPlaceholder),
            'estado' => 'activo',
        ]);

        Manual::create([
            'titulo' => 'Mordedor Trenzado con Botellas',
            'descripcion' => 'Un mordedor resistente hecho con plástico reciclado y nudos de cuerda.',
            'tipo' => 'premium',
            'lote_id' => $loteBotellas->id,
            'archivo_pdf' => $this->guardarPlaceholder($pdfPlaceholder),
            'estado' => 'activo',
        ]);

        Manual::create([
            'titulo' => 'Collar Trenzado con Retazos de Tela',
            'descripcion' => 'Elabora un collar ajustable y resistente con telas en desuso.',
            'tipo' => 'premium',
            'lote_id' => $loteTelas->id,
            'archivo_pdf' => $this->guardarPlaceholder($pdfPlaceholder),
            'estado' => 'activo',
        ]);
    }

    private function guardarPlaceholder(string $contenido): string
    {
        $ruta = 'manuales/'.Str::random(40).'.pdf';
        Storage::disk('local')->put($ruta, $contenido);

        return $ruta;
    }
}
