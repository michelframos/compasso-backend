<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_cobrancas_gateway', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_conta')->constrained('contas')->cascadeOnDelete();
            $table->foreignId('id_instituicao')->constrained('instituicoes')->cascadeOnDelete();
            $table->string('gateway_payment_uuid', 64)->nullable();
            $table->string('method', 20);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->text('invoice_url')->nullable();
            $table->text('pix_copy_paste')->nullable();
            $table->text('pix_qr_code')->nullable();
            $table->text('last_error')->nullable();
            $table->foreignId('id_conta_pagamento')->nullable()->constrained('conta_pagamentos')->nullOnDelete();
            $table->timestamps();

            $table->index('gateway_payment_uuid');
            $table->index('id_conta');
            $table->index('id_instituicao');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_cobrancas_gateway');
    }
};
