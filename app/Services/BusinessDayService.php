<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BusinessDayService
{
    // Feriados nacionais fixos — formato MM-DD
    private array $holidays = [
        '01-01', // Ano Novo
        '04-21', // Tiradentes
        '05-01', // Dia do Trabalho
        '09-07', // Independência
        '10-12', // Nossa Senhora Aparecida
        '11-02', // Finados
        '11-15', // Proclamação da República
        '12-25', // Natal
    ];

    // Retorna o 5º dia útil do mês atual
    public function getFifthBusinessDay(?Carbon $reference = null): Carbon
    {
        $date  = ($reference ?? now())->copy()->startOfMonth();
        $count = 0;

        while ($count < 5) {
            if ($this->isBusinessDay($date)) {
                $count++;
                if ($count === 5) break;
            }
            $date->addDay();
        }

        Log::info("5º dia útil calculado: {$date->format('d/m/Y')}");

        return $date;
    }

    // Verifica se hoje é o 5º dia útil
    public function isFifthBusinessDayToday(): bool
    {
        $fifth = $this->getFifthBusinessDay();
        return $fifth->isToday();
    }

    // Verifica se uma data é dia útil
    public function isBusinessDay(Carbon $date): bool
    {
        // Fim de semana
        if ($date->isWeekend()) return false;

        // Feriado nacional
        if ($this->isHoliday($date)) return false;

        return true;
    }

    private function isHoliday(Carbon $date): bool
    {
        return in_array($date->format('m-d'), $this->holidays);
    }

    // Retorna o mês de referência do fechamento
    // Ex: fechamento em março → referência é fevereiro
    public function getReferenceMonth(?Carbon $reference = null): string
    {
        return ($reference ?? now())->copy()->subMonth()->format('Y-m-01');
    }
}
