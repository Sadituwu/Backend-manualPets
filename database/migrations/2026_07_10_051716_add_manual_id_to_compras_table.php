<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->foreignId('lote_id')->nullable()->change();
            $table->foreignId('manual_id')->nullable()->after('lote_id')->constrained('manuales')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropForeign(['manual_id']);
            $table->dropColumn('manual_id');
            $table->foreignId('lote_id')->nullable(false)->change();
        });
    }
};
