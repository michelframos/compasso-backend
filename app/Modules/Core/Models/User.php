<?php

namespace App\Modules\Core\Models;

use App\Models\Aluno;
use App\Models\Professor;
use App\Models\Responsavel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'User',
    description: 'User model',
    xml: new OA\Xml(name: 'User'),
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true, example: 1),
        new OA\Property(property: 'nome', type: 'string', description: 'Nome do usuário', example: 'João Silva'),
        new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Email do usuário', example: 'joao@email.com'),
        new OA\Property(property: 'cpf', type: 'string', description: 'CPF do usuário', example: '123.456.789-00'),
        new OA\Property(property: 'role', type: 'string', description: 'Função do usuário no sistema', example: 'admin'),
        new OA\Property(property: 'deve_trocar_senha', type: 'boolean', description: 'Exige troca de senha no próximo acesso', example: false),
        new OA\Property(property: 'telefone', type: 'string', description: 'Telefone', example: '(11) 99999-9999'),
        new OA\Property(property: 'whatsapp', type: 'string', description: 'Whatsapp', example: '(11) 99999-9999'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true, readOnly: true),
    ]
)]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = [
        'foto',
        'nome',
        'cpf',
        'data_aniversario',
        'email',
        'senha',
        'deve_trocar_senha',
        'observacoes',
        'rua',
        'numero',
        'complemento',
        'bairro',
        'cep',
        'id_estado',
        'id_cidade',
        'telefone',
        'whatsapp',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function aluno()
    {
        return $this->hasOne(Aluno::class, 'id_usuario');
    }

    public function professor()
    {
        return $this->hasOne(Professor::class, 'id_usuario');
    }

    public function responsavel()
    {
        return $this->hasOne(Responsavel::class, 'id_usuario');
    }

    public function instituicoes(): BelongsToMany
    {
        return $this->belongsToMany(Instituicao::class, 'instituicoes_usuarios', 'id_usuario', 'id_instituicao')
            ->using(InstituicaoUsuario::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function instituicoesAtivas(): BelongsToMany
    {
        return $this->instituicoes()
            ->wherePivot('status', InstituicaoUsuario::STATUS_ATIVO)
            ->where('instituicoes.status', Instituicao::STATUS_ATIVO);
    }

    public function getAuthPassword()
    {
        return $this->senha;
    }

    public function fotoUrl(): ?string
    {
        if (! $this->foto) {
            return null;
        }

        return Str::startsWith($this->foto, ['http://', 'https://']) ? $this->foto : url('storage/' . $this->foto);
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'senha' => 'hashed',
            'is_super_admin' => 'boolean',
            'deve_trocar_senha' => 'boolean',
        ];
    }
}
