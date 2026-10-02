<?php

namespace App\Modules\Relatorios\Services\Dashboard;

use Carbon\Carbon;

class DashboardPeriod
{
    public Carbon $today;

    public Carbon $start;

    public Carbon $end;

    public Carbon $previousStart;

    public Carbon $previousEnd;

    public static function fromQuery(?string $mes): self
    {
        $today = Carbon::today();

        try {
            $ref = $mes
                ? Carbon::createFromFormat('Y-m', $mes)->startOfMonth()
                : $today->copy()->startOfMonth();
        } catch (\Throwable) {
            $ref = $today->copy()->startOfMonth();
        }

        $self = new self();
        $self->today = $today;
        $self->start = $ref->copy()->startOfMonth();
        $self->end = $ref->copy()->endOfMonth();
        $self->previousStart = $ref->copy()->subMonthNoOverflow()->startOfMonth();
        $self->previousEnd = $ref->copy()->subMonthNoOverflow()->endOfMonth();

        return $self;
    }

    public function monthKey(): string
    {
        return $this->start->format('Y-m');
    }
}
