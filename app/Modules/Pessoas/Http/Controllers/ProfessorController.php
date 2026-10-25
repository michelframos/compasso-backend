<?php

namespace App\Modules\Pessoas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use App\Modules\Pessoas\Http\Requests\Professor\StoreProfessorRequest;
use App\Modules\Pessoas\Http\Requests\Professor\UpdateProfessorRequest;
use App\Modules\Pessoas\Http\Resources\ProfessorResource;
use App\Modules\Pessoas\Models\Professor;
use App\Modules\Pessoas\UseCases\Professor\CreateProfessorUseCase;
use App\Modules\Pessoas\UseCases\Professor\DeleteProfessorUseCase;
use App\Modules\Pessoas\UseCases\Professor\EnviarAcessoProfessorUseCase;
use App\Modules\Pessoas\UseCases\Professor\UpdateProfessorUseCase;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[OA\Tag(name: 'Pessoas', description: 'Gerenciamento de Professores')]
class ProfessorController extends Controller
{
    use ResolvesSorting;

    public function __construct(
        private readonly CreateProfessorUseCase $createProfessor,
        private readonly UpdateProfessorUseCase $updateProfessor,
        private readonly DeleteProfessorUseCase $deleteProfessor,
        private readonly EnviarAcessoProfessorUseCase $enviarAcessoProfessor,
    ) {}

    #[OA\Get(path: '/api/professores', summary: 'Listar professores', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index()
    {
        $search = request('search');

        $query = Professor::select('professores.*')
            ->join('usuarios', 'professores.id_usuario', '=', 'usuarios.id')
            ->with('usuario')
            ->where('usuarios.role', 'professor')
            ->whereNull('usuarios.deleted_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('usuarios.nome', 'like', "%{$search}%")
                    ->orWhere('usuarios.email', 'like', "%{$search}%");
            });
        }

        $professores = $this->applySorting($query, [
            'usuarios.nome' => 'usuarios.nome',
            'usuarios.email' => 'usuarios.email',
            'comissao' => 'professores.comissao',
        ], 'usuarios.nome')->paginate(request('per_page', 15));

        return ProfessorResource::collection($professores);
    }

    #[OA\Post(path: '/api/professores', summary: 'Criar novo professor', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function store(StoreProfessorRequest $request)
    {
        $professor = $this->createProfessor->execute($request->validated());

        return response()->json($professor, 201);
    }

    #[OA\Get(path: '/api/professores/{id}', summary: 'Exibir professor específico', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(Professor $professor)
    {
        return response()->json($professor->load([
            'usuario',
            'turmas.curso',
            'turmas.nivel',
            'turmas.horarios',
            'turmas.matriculas.aluno.usuario',
        ]));
    }

    #[OA\Put(path: '/api/professores/{id}', summary: 'Atualizar professor', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function update(UpdateProfessorRequest $request, Professor $professor)
    {
        $professor = $this->updateProfessor->execute($professor, $request->validated());

        return response()->json($professor);
    }

    #[OA\Delete(path: '/api/professores/{id}', summary: 'Excluir professor', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function destroy(Professor $professor)
    {
        try {
            $this->deleteProfessor->execute($professor);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/professores/{id}/enviar-acesso',
        summary: 'Enviar ao professor o código para definir/redefinir a senha de acesso',
        security: [['sanctum' => []]],
        tags: ['Pessoas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Código de acesso enviado por e-mail'),
            new OA\Response(response: 403, description: 'Perfil sem permissão'),
            new OA\Response(response: 404, description: 'Professor não encontrado'),
            new OA\Response(response: 422, description: 'Professor sem e-mail cadastrado'),
        ]
    )]
    public function enviarAcesso(Professor $professor)
    {
        try {
            $email = $this->enviarAcessoProfessor->execute($professor);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['message' => "Código de acesso enviado para {$email}."]);
    }
}
