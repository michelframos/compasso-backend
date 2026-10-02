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
        Schema::table('niveis', function (Blueprint $table) {
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            $table->integer('ordem')->nullable();
            $table->unsignedInteger('idade_minima')->nullable();
            $table->unsignedInteger('idade_maxima')->nullable();
            $table->string('cor_identificacao', 10)->nullable();
            $table->text('expectativas_aprendizado')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('niveis', function (Blueprint $table) {
            $table->dropForeign(['curso_id']);
            $table->dropColumn([
                'curso_id',
                'ordem',
                'idade_minima',
                'idade_maxima',
                'cor_identificacao',
                'expectativas_aprendizado'
            ]);
        });
    }
};
