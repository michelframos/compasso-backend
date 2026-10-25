<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaterialTurma extends Model
{
    use PertenceAInstituicao;

    protected $table = 'materiais_turmas';
    /** Extensões aceitas no upload (validadas pelo conteúdo do arquivo). */
    public const EXTENSOES_PERMITIDAS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'txt', 'rtf', 'csv',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'mp3', 'mpga', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'mid', 'midi',
        'mp4', 'mov', 'webm', 'zip', 'xml',
    ];

    protected $fillable = ['id_instituicao', 'id_turma', 'titulo', 'descricao', 'file_path', 'file_type', 'link', 'publico'];

    protected function casts(): array
    {
        return ['publico' => 'boolean'];
    }

    public function turma()
    {
        return $this->belongsTo(Turma::class, 'id_turma');
    }

    public function scopeVisivelPara(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'professor' => $query->whereHas('turma', fn (Builder $q) => $q->where('id_professor', $user->professor?->id ?? 0)),
            'aluno' => $query
                ->where('publico', true)
                ->whereHas('turma.matriculas', fn (Builder $q) => $q->where('id_aluno', $user->aluno?->id ?? 0)),
            default => $query,
        };
    }
}
