<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assinaturas_cobranca', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_instituicao')->constrained('instituicoes');
            $table->foreignId('id_plano_assinatura')->constrained('planos_assinatura');
            $table->string('gateway_subscription_uuid', 64)->nullable();
            $table->string('method', 20);
            $table->string('cycle', 20)->default('MONTHLY');
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pending');
            $table->text('invoice_url')->nullable();
            $table->date('next_due_date')->nullable();
            $table->string('customer_email')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_instituicao');
            $table->index('status');
            $table->index('gateway_subscription_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assinaturas_cobranca');
    }
};
