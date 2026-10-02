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
        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->char('sigla', 2);
            $table->integer('codigo_ibge')->nullable();
            $table->timestamps();
        });

        Schema::create('cidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->unsignedBigInteger('id_estado');
            $table->integer('codigo_ibge')->nullable();
            $table->integer('codigo_siafi')->nullable();
            $table->string('ddd', 4)->nullable(); // Changed to string for flexibility (e.g. "11,12") or just int
            $table->timestamps();

            $table->foreign('id_estado')->references('id')->on('estados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cidades');
        Schema::dropIfExists('estados');
    }
};
