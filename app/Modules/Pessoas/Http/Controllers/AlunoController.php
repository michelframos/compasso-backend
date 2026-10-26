<?php

namespace App\Modules\Pessoas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Concerns\ResolvesSorting;
use App\Models\AlunoContrato;
use App\Modules\Pessoas\Http\Requests\Aluno\StoreAlunoRequest;
use App\Modules\Pessoas\Http\Requests\Aluno\UpdateAlunoRequest;
use App\Modules\Pessoas\Http\Resources\AlunoResource;
use App\Modules\Pessoas\Models\Aluno;
use App\Modules\Pessoas\UseCases\Aluno\CreateAlunoUseCase;
use App\Modules\Pessoas\UseCases\Aluno\DeleteAlunoUseCase;
use App\Modules\Pessoas\UseCases\Aluno\EnviarAcessoAlunoUseCase;
use App\Modules\Pessoas\UseCases\Aluno\UpdateAlunoUseCase;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[OA\Tag(name: 'Pessoas', description: 'Gerenciamento de Alunos')]
class AlunoController extends Controller
{
    use ResolvesSorting;

    public function __construct(
        private readonly CreateAlunoUseCase $createAluno,
        private readonly UpdateAlunoUseCase $updateAluno,
        private readonly DeleteAlunoUseCase $deleteAluno,
        private readonly EnviarAcessoAlunoUseCase $enviarAcessoAluno,
    ) {}

    #[OA\Get(path: '/api/alunos/list', summary: 'Listar todos os alunos (simplificado)', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function list()
    {
        $alunos = Aluno::select('alunos.id', 'usuarios.nome')
            ->join('usuarios', 'alunos.id_usuario', '=', 'usuarios.id')
            ->where('usuarios.role', 'aluno')
            ->whereNull('usuarios.deleted_at')
            ->orderBy('usuarios.nome', 'asc')
            ->get();

        return response()->json($alunos);
    }

    #[OA\Get(path: '/api/alunos', summary: 'Listar alunos', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index()
    {
        $search = request('search');

        $query = Aluno::select('alunos.*')
            ->join('usuarios', 'alunos.id_usuario', '=', 'usuarios.id')
            ->with('usuario')
            ->where('usuarios.role', 'aluno')
            ->whereNull('usuarios.deleted_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('usuarios.nome', 'like', "%{$search}%")
                    ->orWhere('usuarios.email', 'like', "%{$search}%");
            });
        }

        $alunos = $this->applySorting($query, [
            'usuarios.nome' => 'usuarios.nome',
            'usuarios.email' => 'usuarios.email',
            'cpf' => 'usuarios.cpf',
        ], 'usuarios.nome')->paginate(request('per_page', 15));

        return AlunoResource::collection($alunos);
    }

    #[OA\Post(path: '/api/alunos', summary: 'Criar novo aluno', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function store(StoreAlunoRequest $request)
    {
        $aluno = $this->createAluno->execute($request->validated());

        return response()->json($aluno, 201);
    }

    #[OA\Get(path: '/api/alunos/{id}', summary: 'Exibir aluno específico', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function show(Aluno $aluno)
    {
        if ($aluno->usuario->role !== 'aluno') {
            return response()->json(['message' => 'Aluno não encontrado'], 404);
        }

        return new AlunoResource($aluno->load([
            'usuario',
            'matriculas.turma.curso',
            'matriculas.turma.nivel',
            'matriculas.turma.horarios',
            'matriculas.turma.professor.usuario',
            'matriculas.curso',
            'matriculas.nivel',
            'matriculas.professor.usuario',
            'matriculas.contas',
            'contas',
            'instrumentos',
            'apresentacoes.espetaculo.contrato',
            'contratosAvulsos.contrato',
            'responsaveis.usuario',
        ]));
    }

    #[OA\Put(path: '/api/alunos/{id}', summary: 'Atualizar aluno', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function update(UpdateAlunoRequest $request, Aluno $aluno)
    {
        if ($aluno->usuario->role !== 'aluno') {
            return response()->json(['message' => 'Aluno não encontrado'], 404);
        }

        $aluno = $this->updateAluno->execute($aluno, $request->validated());

        return response()->json($aluno);
    }

    #[OA\Delete(path: '/api/alunos/{id}', summary: 'Excluir aluno', security: [['sanctum' => []]], tags: ['Pessoas'],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function destroy(Aluno $aluno)
    {
        try {
            $this->deleteAluno->execute($aluno);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(null, 204);
    }

    public function storeContratoAvulso(Request $request, Aluno $aluno)
    {
        if ($aluno->usuario->role !== 'aluno') {
            return response()->json(['message' => 'Aluno não encontrado'], 404);
        }

        $request->validate([
            'contrato_id' => 'required|exists:contratos,id',
            'contrato_gerado' => 'required|string',
        ]);

        $contratoAvulso = $aluno->contratosAvulsos()->create([
            'contrato_id' => $request->contrato_id,
            'contrato_gerado' => $request->contrato_gerado,
        ]);

        return response()->json($contratoAvulso->load('contrato'), 201);
    }

    public function destroyContratoAvulso(Aluno $aluno, AlunoContrato $alunoContrato)
    {
        if ($aluno->usuario->role !== 'aluno') {
            return response()->json(['message' => 'Aluno não encontrado'], 404);
        }

        if ($alunoContrato->aluno_id !== $aluno->id) {
            return response()->json(['message' => 'Este contrato não pertence ao aluno informado.'], 403);
        }

        $alunoContrato->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/alunos/{id}/enviar-acesso',
        summary: 'Enviar ao aluno o código para definir/redefinir a senha de acesso',
        security: [['sanctum' => []]],
        tags: ['Pessoas'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Código de acesso enviado por e-mail'),
            new OA\Response(response: 403, description: 'Perfil sem permissão'),
            new OA\Response(response: 404, description: 'Aluno não encontrado'),
            new OA\Response(response: 422, description: 'Aluno sem e-mail cadastrado'),
        ]
    )]
    public function enviarAcesso(Aluno $aluno)
    {
        try {
            $email = $this->enviarAcessoAluno->execute($aluno);
        } catch (HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json(['message' => "Código de acesso enviado para {$email}."]);
    }
}
