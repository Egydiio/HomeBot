<?php

namespace App\Console\Commands;

use App\Jobs\SendPixCharge;
use App\Models\Group;
use App\Services\BalanceService;
use App\Services\BusinessDayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseMonthCommand extends Command
{
    protected $signature   = 'homebot:close-month {--force : Forçar fechamento independente do dia}';
    protected $description = 'Fecha o mês e dispara cobranças no 5º dia útil';

    public function __construct(
        protected BusinessDayService $businessDayService,
        protected BalanceService     $balanceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = $this->option('force');

        // Verifica se é o 5º dia útil
        if (!$force && !$this->businessDayService->isFifthBusinessDayToday()) {
            $this->info('Hoje não é o 5º dia útil. Use --force para forçar.');
            return Command::SUCCESS;
        }

        $referenceMonth = $this->businessDayService->getReferenceMonth();
        $this->info("Fechando mês de referência: {$referenceMonth}");

        $groups = Group::where('active', true)->with('members')->get();

        if ($groups->isEmpty()) {
            $this->info('Nenhum grupo ativo encontrado.');
            return Command::SUCCESS;
        }

        foreach ($groups as $group) {
            $this->processGroup($group, $referenceMonth);
        }

        $this->info('Fechamento concluído!');
        return Command::SUCCESS;
    }

    private function processGroup($group, string $referenceMonth): void
    {
        $this->info("Processando grupo: {$group->name}");

        $members = $group->members()->where('active', true)->get();

        foreach ($members as $member) {
            $summary = $this->balanceService->getMemberSummary($member, $referenceMonth);

            // Só processa quem tem dívida
            if ($summary['total_debt'] <= 0) continue;

            foreach ($summary['debts'] as $debt) {
                // Evita criar fechamento duplicado
                $alreadyClosed = \App\Models\MonthlyClose::where('group_id', $group->id)
                    ->where('debtor_id', $debt->debtor_id)
                    ->where('creditor_id', $debt->creditor_id)
                    ->where('reference_month', $referenceMonth)
                    ->exists();

                if ($alreadyClosed) {
                    $this->info("  Já fechado: {$member->name} → {$debt->creditor->name}");
                    continue;
                }

                // Cria o registro de fechamento
                $close = \App\Models\MonthlyClose::create([
                    'group_id'        => $group->id,
                    'reference_month' => $referenceMonth,
                    'status'          => 'pending',
                    'amount'          => $debt->amount,
                    'debtor_id'       => $debt->debtor_id,
                    'creditor_id'     => $debt->creditor_id,
                    'charged_at'      => null,
                    'paid_at'         => null,
                ]);

                $this->info("  Cobrança criada: {$member->name} deve R$ " .
                    number_format($debt->amount, 2, ',', '.') .
                    " pra {$debt->creditor->name}");

                // Dispara o job de envio do Pix na fila
                SendPixCharge::dispatch($close->id);

                Log::info("MonthlyClose criado", [
                    'group'   => $group->name,
                    'debtor'  => $member->name,
                    'amount'  => $debt->amount,
                    'close_id'=> $close->id,
                ]);
            }
        }
    }
}
