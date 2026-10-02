<?php

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\AulaTurma;
use App\Modules\Academico\Models\AvisoTurma;
use App\Modules\Academico\Models\AvisoTurmaDestinatario;
use App\Modules\Academico\Models\Turma;
use App\Modules\Core\Contracts\EnviarAvisoTurmaPort;
use App\Modules\Core\Domain\Notificacoes\Notificacao;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\InstituicaoContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Grava o aviso com seus destinatários e enfileira o envio depois do commit. */
class AvisoTurmaService
{
    public function __construct(
        private readonly DestinatariosAvisoService $destinatarios,
        private readonly EnviarAvisoTurmaPort $envio,
    ) {}

    /** @return list<string> */
    public function canaisDisponiveis(): array
    {
        return array_keys(array_filter($this->envio->canaisDisponiveis()));
    }

    /**
     * @param  array{titulo: string, mensagem: string, publico: string, canais: list<string>}  $dados
     * @param  Collection<int, \App\Models\Aluno>  $alunos
     */
    public function registrar(
        User $autor,
        array $dados,
        Collection $alunos,
        ?Turma $turma,
        ?int $idProfessor,
        string $origem = AvisoTurma::ORIGEM_MANUAL,
        ?AulaTurma $aula = null,
    ): AvisoTurma {
        $aviso = AvisoTurma::create([
            'id_turma' => $turma?->id,
            'id_professor' => $idProfessor,
            'id_usuario_autor' => $autor->id,
            'id_aula_turma' => $aula?->id,
            'origem' => $origem,
            'titulo' => $dados['titulo'],
            'mensagem' => $dados['mensagem'],
            'publico' => $dados['publico'],
            'canais' => array_values($dados['canais']),
            'enviado_em' => now(),
        ]);

        $assinatura = $this->assinatura($autor, $turma);
        $mensagens = [];
        foreach ($this->destinatarios->montar($alunos, $dados['publico'], $dados['canais']) as $linha) {
            $destinatario = $aviso->destinatarios()->create($linha + [
                'status' => $linha['destino'] ? AvisoTurmaDestinatario::STATUS_PENDENTE : AvisoTurmaDestinatario::STATUS_SEM_CONTATO,
            ]);

            if ($destinatario->destino) {
                $mensagens[] = ['canal' => $destinatario->canal, 'notificacao' => $this->notificacao($aviso, $destinatario, $assinatura)];
            }
        }

        DB::afterCommit(fn () => $this->envio->enviar($mensagens));

        return $aviso;
    }

    private function notificacao(AvisoTurma $aviso, AvisoTurmaDestinatario $destinatario, string $assinatura): Notificacao
    {
        $mensagem = $destinatario->canal === EnviarAvisoTurmaPort::WHATSAPP
            ? "*{$aviso->titulo}*\n\n{$aviso->mensagem}\n\n— {$assinatura}"
            : "{$aviso->mensagem}\n\n— {$assinatura}";

        return new Notificacao(
            destinatario: $destinatario->nome,
            destino: $destinatario->destino,
            mensagem: $mensagem,
            referenciaType: AvisoTurmaDestinatario::class,
            referenciaId: $destinatario->id,
            assunto: $aviso->titulo,
        );
    }

    private function assinatura(User $autor, ?Turma $turma): string
    {
        $turma?->loadMissing(['curso', 'nivel']);
        $escola = Instituicao::query()->find(InstituicaoContext::id())?->nome_fantasia;

        return collect([$autor->nome, $turma?->apelido(), $escola])->filter()->implode(' · ');
    }
}
