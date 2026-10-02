<?php

namespace App\Enums;

enum TurmaStatus: string
{
    case PLANEJAMENTO = 'planejamento';
    case ABERTA = 'aberta';
    case EM_ANDAMENTO = 'em_andamento';
    case PAUSADA = 'pausada';
    case CONCLUIDA = 'concluida';
    case CANCELADA = 'cancelada';
}
