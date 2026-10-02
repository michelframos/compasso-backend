<?php

namespace App\Modules\Financeiro\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Conta",
    description: "Modelo de Conta (Receber/Pagar)",
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "id_categoria", type: "integer", example: 1),
        new OA\Property(property: "descricao", type: "string", example: "Mensalidade Março"),
        new OA\Property(property: "valor", type: "number", format: "float", example: 150.00),
        new OA\Property(property: "data_vencimento", type: "string", format: "date", example: "2024-03-10"),
        new OA\Property(property: "status", type: "string", example: "pendente"),
        new OA\Property(property: "tipo", type: "string", example: "receber")
    ]
)]
class Conta extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $fillable = [
        'id_instituicao',
        'id_categoria', 'descricao', 'valor', 'data_vencimento', 'data_pagamento',
        'status', 'observacoes', 'numero_parcela', 'quantidade_parcelas',
        'id_aluno', 'id_professor', 'id_matricula', 'tipo',
        'id_aula_turma', 'notificar', 'mes_referencia', 'ano_referencia'
    ];

    protected $casts = [
        'notificar' => 'boolean',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
    ];

    // public $timestamps = false; // Timestamps habilitados agora

    public function categoria() { return $this->belongsTo(CategoriaConta::class, 'id_categoria'); }

    public function aluno() { return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno'); }

    public function professor() { return $this->belongsTo(\App\Models\Professor::class, 'id_professor'); }

    public function matricula() { return $this->belongsTo(\App\Models\Matricula::class, 'id_matricula'); }

    public function pagamentos() { return $this->hasMany(ContaPagamento::class, 'id_conta'); }

    public function aula_turma() { return $this->belongsTo(\App\Models\AulaTurma::class, 'id_aula_turma'); }

    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'aluno' => $query->where('id_aluno', $user->aluno?->id),
            'responsavel' => $query->whereHas('aluno.responsaveis', fn (Builder $q) => $q
                ->where('responsaveis_alunos.id_responsavel', $user->responsavel?->id)),
            default => $query,
        };
    }

    public function totalPago(): float
    {
        return round((float) $this->pagamentos()->sum('valor_pago'), 2);
    }

    public function saldoDevedor(): float
    {
        return round((float) $this->valor - $this->totalPago(), 2);
    }

    /**
     * Recalcula o status a partir dos pagamentos registrados.
     */
    public function recalcularStatus(): void
    {
        $totalPago = $this->totalPago();

        $status = match (true) {
            $totalPago >= (float) $this->valor => 'pago',
            $totalPago > 0 => 'pago_parcialmente',
            $this->data_vencimento->lt(today()) => 'vencido',
            default => 'pendente',
        };

        $this->update(['status' => $status]);
    }
}
