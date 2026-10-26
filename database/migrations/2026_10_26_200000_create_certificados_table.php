<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_matricula')->constrained('matriculas');
            $table->string('codigo_verificacao', 32)->unique();
            $table->timestamp('emitido_em');
            $table->decimal('carga_horaria', 8, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['id_instituicao', 'id']);
            $table->index('id_matricula');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados');
    }
};
