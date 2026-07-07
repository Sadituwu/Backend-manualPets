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
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'nombres');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('apellidos')->default('')->after('nombres');
            $table->string('dni', 8)->nullable()->unique()->after('apellidos');
            $table->string('direccion')->nullable()->after('dni');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['apellidos', 'dni', 'direccion']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('nombres', 'name');
        });
    }
};
