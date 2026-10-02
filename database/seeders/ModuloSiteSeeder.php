<?php

namespace Database\Seeders;

use App\Modules\Core\Models\SiteModulo;
use Illuminate\Database\Seeder;

class ModuloSiteSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'nome' => 'Alunos e professores',
                'descricao' => 'Cadastro de pessoas da escola, com responsáveis e dados de remuneração.',
                'recursos' => ['Cadastro de alunos', 'Responsáveis', 'Cadastro de professores', 'Remuneração'],
                'icone' => 'Users',
                'ordem' => 1,
            ],
            [
                'nome' => 'Turmas, matrículas e agenda',
                'descricao' => 'Estrutura acadêmica, horários de aula, presença e materiais da turma.',
                'recursos' => ['Cursos e níveis', 'Turmas e horários', 'Matrículas', 'Presença e materiais'],
                'icone' => 'GraduationCap',
                'ordem' => 2,
            ],
            [
                'nome' => 'Financeiro',
                'descricao' => 'Contas, pagamentos, PIX e contratos ligados à operação da escola.',
                'recursos' => ['Contas e pagamentos', 'Mensalidades', 'PIX', 'Contratos'],
                'icone' => 'Wallet',
                'ordem' => 3,
            ],
            [
                'nome' => 'Leads',
                'descricao' => 'Captação e acompanhamento de interessados na escola.',
                'recursos' => ['Cadastro de leads', 'Acompanhamento comercial'],
                'icone' => 'UserPlus',
                'ordem' => 4,
            ],
            [
                'nome' => 'Espetáculos',
                'descricao' => 'Eventos da escola, apresentações e elenco.',
                'recursos' => ['Espetáculos', 'Apresentações', 'Elenco'],
                'icone' => 'Star',
                'ordem' => 5,
            ],
            [
                'nome' => 'Instrumentos',
                'descricao' => 'Controle do acervo e histórico de uso.',
                'recursos' => ['Inventário', 'Histórico de instrumentos'],
                'icone' => 'Music',
                'ordem' => 6,
            ],
            [
                'nome' => 'Relatórios',
                'descricao' => 'Visões prontas para gestão pedagógica, financeira e comercial.',
                'recursos' => ['Inadimplência', 'Fluxo de caixa', 'Frequência', 'Funil de vendas', 'Aniversariantes'],
                'icone' => 'BarChart3',
                'ordem' => 7,
            ],
            [
                'nome' => 'WhatsApp',
                'descricao' => 'Lembretes e comunicados configurados na escola.',
                'recursos' => ['Lembretes', 'Comunicados', 'Configuração nas preferências'],
                'icone' => 'MessageCircle',
                'ordem' => 8,
            ],
        ];

        foreach ($items as $item) {
            SiteModulo::query()->updateOrCreate(
                ['nome' => $item['nome']],
                [
                    ...$item,
                    'aprovado' => true,
                    'aprovado_em' => now(),
                ],
            );
        }
    }
}
