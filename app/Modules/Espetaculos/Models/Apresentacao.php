<?php

namespace App\Modules\Espetaculos\Models;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use App\Modules\Pessoas\Support\AlunosVinculados;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Apresentacao",
    description: "Modelo de Apresentação",
    properties: [
        new OA\Property(property: "id", type: "integer", readOnly: true, example: 1),
        new OA\Property(property: "id_espetaculo", type: "integer", example: 1),
        new OA\Property(property: "id_turma", type: "integer", example: 1),
        new OA\Property(property: "titulo_musica", type: "string", example: "O Trenzinho do Caipira"),
        new OA\Property(property: "ordem_entrada", type: "integer", example: 5),
        new OA\Property(property: "duracao_estimada", type: "string", example: "00:05:00")
    ]
)]
class Apresentacao extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;

    protected $table = 'apresentacoes';
    protected $fillable = ['id_instituicao', 'id_espetaculo', 'id_turma', 'titulo_musica', 'ordem_entrada', 'duracao_estimada'];

    public function espetaculo()
    {
        return $this->belongsTo(Espetaculo::class, 'id_espetaculo');
    }
    public function turma()
    {
        return $this->belongsTo(\App\Models\Turma::class, 'id_turma');
    }
    public function alunos()
    {
        return $this->hasMany(ApresentacaoAluno::class, 'id_apresentacao');
    }
    public function ensaios()
    {
        return $this->hasMany(Ensaio::class, 'id_apresentacao');
    }

    /** Professor vê as suas; aluno e responsável só as da própria turma ou em que estão escalados. */
    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'professor' => $query->where(fn (Builder $q) => $q
                ->whereHas('turma', fn (Builder $t) => $t->where('id_professor', $user->professor?->id ?? 0))
                ->orWhereHas('alunos', fn (Builder $a) => $a->whereIn('id_aluno', self::alunosDoProfessor($user)))),
            'aluno', 'responsavel' => $query->where(fn (Builder $q) => $q
                ->whereHas('alunos', fn (Builder $a) => $a->whereIn('id_aluno', AlunosVinculados::ids($user)))
                ->orWhereHas('turma.matriculas', fn (Builder $m) => $m->whereIn('id_aluno', AlunosVinculados::ids($user)))),
            default => $query,
        };
    }

    public static function alunosDoProfessor(User $user): Builder
    {
        return Matricula::query()->visivelPara($user)->vigentes()->select('id_aluno');
    }
}
