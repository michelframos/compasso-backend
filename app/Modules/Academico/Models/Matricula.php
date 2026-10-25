<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Matrícula",
    description: "Modelo de Matrícula",
    xml: new OA\Xml(name: "Matricula"),
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "id_aluno", type: "integer", description: "ID do aluno", example: 1),
        new OA\Property(property: "tipo", type: "string", description: "Tipo da matrícula: turma ou curso", example: "turma"),
        new OA\Property(property: "id_turma", type: "integer", description: "ID da turma (quando tipo=turma)", nullable: true, example: 1),
        new OA\Property(property: "id_curso", type: "integer", description: "ID do curso (quando tipo=curso)", nullable: true, example: 1),
        new OA\Property(property: "id_nivel", type: "integer", description: "ID do nível (quando tipo=curso)", nullable: true, example: 1),
        new OA\Property(property: "id_professor", type: "integer", description: "ID do professor (quando tipo=curso)", nullable: true, example: 1),
        new OA\Property(property: "data", type: "string", format: "date", description: "Data da matrícula", example: "2023-11-05"),
        new OA\Property(property: "status", type: "string", description: "Status da matrícula", nullable: true, example: "ativa"),
        new OA\Property(property: "observacoes", type: "string", nullable: true, example: "Observações da matrícula"),
        new OA\Property(property: "id_lead", type: "integer", nullable: true, example: 1),
        new OA\Property(property: "deleted_at", type: "string", format: "date-time", nullable: true, readOnly: true)
    ]
)]
class Matricula extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    /** Status de matrículas que não vinculam mais o aluno à turma. */
    public const STATUS_ENCERRADOS = ['cancelada', 'transferida'];

    protected $table = 'matriculas';
    protected $fillable = [
        'id_instituicao',
        'id_aluno', 'id_turma', 'id_lead', 'data', 'status', 'observacoes',
        'contrato_id', 'contrato_gerado',
        'tipo', 'id_curso', 'id_nivel', 'id_professor',
    ];
    protected $casts = [
        'data' => 'date',
    ];
    public $timestamps = false;

    public function aluno()
    {
        return $this->belongsTo(\App\Models\Aluno::class, 'id_aluno');
    }
    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }

    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'professor' => $this->doProfessor($query, $user->professor?->id ?? 0),
            'aluno' => $query->where('id_aluno', $user->aluno?->id ?? 0),
            'responsavel' => $query->whereHas('aluno.responsaveis', fn (Builder $q) => $q
                ->where('responsaveis_alunos.id_responsavel', $user->responsavel?->id ?? 0)),
            default => $query,
        };
    }

    /** Matrículas em turmas do professor ou matrículas por curso (aulas individuais) atribuídas a ele. */
    private function doProfessor(Builder $query, int $professorId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereHas('turma', fn (Builder $t) => $t->where('id_professor', $professorId))
            ->orWhere(fn (Builder $c) => $c->where('tipo', 'curso')->where('id_professor', $professorId)));
    }

    /** Professor responsável pela matrícula: o da turma ou, na matrícula por curso, o atribuído diretamente. */
    public function idProfessorResponsavel(): ?int
    {
        return $this->tipo === 'curso' ? $this->id_professor : $this->turma?->id_professor;
    }

    public function idCursoAtual(): ?int
    {
        return $this->tipo === 'curso' ? $this->id_curso : $this->turma?->id_curso;
    }

    public function idNivelAtual(): ?int
    {
        return $this->tipo === 'curso' ? $this->id_nivel : $this->turma?->id_nivel;
    }

    public function estaVigente(): bool
    {
        return ! in_array($this->status, self::STATUS_ENCERRADOS, true);
    }

    /** Usado pelo histórico gerado no evento `updated` ao trocar de turma. */
    public ?string $motivoTransferencia = null;

    public function transferirParaTurma(int $idTurma, string $motivo): void
    {
        $this->motivoTransferencia = $motivo;
        $this->update(['id_turma' => $idTurma]);
        $this->motivoTransferencia = null;
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('status')
            ->orWhereNotIn('status', self::STATUS_ENCERRADOS));
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class, 'id_curso');
    }

    public function nivel()
    {
        return $this->belongsTo(Nivel::class, 'id_nivel');
    }

    public function professor()
    {
        return $this->belongsTo(\App\Models\Professor::class, 'id_professor');
    }

    public function historicos()
    {
        return $this->hasMany(MatriculaHistorico::class, 'id_matricula');
    }

    public function contas()
    {
        return $this->hasMany(\App\Models\Conta::class, 'id_matricula');
    }

    public function contrato()
    {
        return $this->belongsTo(\App\Models\Contrato::class, 'contrato_id');
    }

    public function lead()
    {
        return $this->belongsTo(\App\Models\Lead::class, 'id_lead');
    }

    protected static function booted()
    {
        static::created(function ($matricula) {
            // Histórico de transferência só se aplica a matrículas em turma
            if ($matricula->tipo === 'turma' && $matricula->id_turma) {
                MatriculaHistorico::create([
                    'id_matricula' => $matricula->id,
                    'id_turma_origem' => null,
                    'id_turma_destino' => $matricula->id_turma,
                    'data_transferencia' => now(),
                    'motivo' => 'Matrícula inicial'
                ]);
            }

            // Comercial: marcar lead como matriculado (via port — adapter legado na Fase 5)
            if ($matricula->id_lead) {
                app(\App\Modules\Core\Contracts\MarcarLeadMatriculadoPort::class)
                    ->execute((int) $matricula->id_lead);
            }
        });

        static::updated(function ($matricula) {
            // Transferência de turma somente para matrículas do tipo turma
            if ($matricula->tipo === 'turma' && $matricula->isDirty('id_turma')) {
                MatriculaHistorico::create([
                    'id_matricula' => $matricula->id,
                    'id_turma_origem' => $matricula->getOriginal('id_turma'),
                    'id_turma_destino' => $matricula->id_turma,
                    'data_transferencia' => now(),
                    'motivo' => $matricula->motivoTransferencia ?? request('motivo_transferencia') ?? 'Transferência de turma'
                ]);
            }
        });
    }
}
