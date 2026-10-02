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
        Schema::create('conta_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_conta');
            $table->decimal('valor_pago', 10, 2);
            $table->date('data_pagamento');
            $table->string('forma_pagamento');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_conta')->references('id')->on('contas')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conta_pagamentos');
    }
};
