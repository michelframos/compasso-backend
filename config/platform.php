<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Período de trial padrão (dias)
    |--------------------------------------------------------------------------
    |
    | Usado ao cadastrar escolas sem trial customizado no painel platform.
    |
    */

    'default_trial_days' => (int) env('PLATFORM_DEFAULT_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Módulos do app (entitlements por plano)
    |--------------------------------------------------------------------------
    |
    | Catálogo fixo de features opcionais. Chaves salvas em planos_assinatura.modulos.
    | Recursos core (dashboard, agenda, alunos, acadêmico, config) ficam sempre liberados.
    |
    */

    'modulos_app' => [
        'leads' => [
            'label' => 'Leads',
            'descricao' => 'Captação e acompanhamento comercial',
        ],
        'financeiro' => [
            'label' => 'Financeiro',
            'descricao' => 'Contas, PIX, contratos e mensalidades',
        ],
        'espetaculos' => [
            'label' => 'Espetáculos',
            'descricao' => 'Espetáculos e apresentações',
        ],
        'instrumentos' => [
            'label' => 'Instrumentos',
            'descricao' => 'Inventário e empréstimos',
        ],
        'relatorios' => [
            'label' => 'Relatórios avançados',
            'descricao' => 'Relatórios financeiros, pedagógicos e comerciais',
        ],
    ],

];
