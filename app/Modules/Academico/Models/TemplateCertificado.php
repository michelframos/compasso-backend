<?php

namespace App\Modules\Academico\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'TemplateCertificado',
    description: 'Template reutilizável de certificado de conclusão',
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true),
        new OA\Property(property: 'nome', type: 'string'),
        new OA\Property(property: 'conteudo', type: 'string'),
        new OA\Property(property: 'fundo_path', type: 'string', nullable: true),
    ]
)]
class TemplateCertificado extends Model
{
    use PertenceAInstituicao, SoftDeletes;

    public const DISCO_FUNDO = 'public';

    protected $table = 'templates_certificados';

    protected $fillable = [
        'id_instituicao',
        'nome',
        'conteudo',
        'fundo_path',
    ];

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'id_template_certificado');
    }

    public function fundoUrl(): ?string
    {
        if (! $this->fundo_path) {
            return null;
        }

        return Storage::disk(self::DISCO_FUNDO)->url($this->fundo_path);
    }
}
