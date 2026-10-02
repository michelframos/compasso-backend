<?php

namespace App\Modules\Academico\UseCases\Matricula;

use App\Modules\Academico\Models\Matricula;
use App\Modules\Core\Contracts\GerarMensalidadesPort;

class GerarMensalidadesDaMatriculaUseCase
{
    public function __construct(
        private readonly GerarMensalidadesPort $gerarMensalidades,
    ) {}

    /**
     * @param  array{valor: float|int|string, quantidade_parcelas: int, dia_vencimento: int, data_inicio: string, id_categoria?: int|null, observacoes?: string|null}  $dados
     * @return array<int, object> contas geradas
     */
    public function execute(Matricula $matricula, array $dados): array
    {
        $matricula->loadMissing('aluno.usuario');

        return $this->gerarMensalidades->execute([
            'valor' => $dados['valor'],
            'quantidade_parcelas' => $dados['quantidade_parcelas'],
            'dia_vencimento' => $dados['dia_vencimento'],
            'data_inicio' => $dados['data_inicio'],
            'id_categoria' => $dados['id_categoria'] ?? 1,
            'observacoes' => $dados['observacoes'] ?? "Geração automática via matrícula #{$matricula->id}",
            'nome_aluno' => $matricula->aluno->usuario->nome,
            'id_aluno' => $matricula->id_aluno,
            'id_matricula' => $matricula->id,
            'id_instituicao' => $matricula->id_instituicao,
        ]);
    }
}
