<?php

namespace App\Console\Commands;

use App\Data\PersonaTimelineDefinition;
use App\Models\SimulationPersona;
use App\Models\User;
use App\Services\SimulationRunnerService;
use Illuminate\Console\Command;

/**
 * Chạy simulation cho persona: N cycle (mặc định 24), mỗi cycle = cuối tháng, chạy pipeline + learning + ghi drift log.
 *
 * php artisan simulate:persona persona_1
 * php artisan simulate:persona persona_2 --cycles=12
 * php artisan simulate:persona all
 */
class SimulatePersonaCommand extends Command
{
    protected $signature = 'simulate:persona
                            {persona : persona_1 .. persona_7 hoặc all}
                            {--cycles=24 : Số cycle (tháng) chạy}';

    protected $description = 'Chạy môi trường huấn luyện não cho persona (24 tháng timeline, snapshot, forecast error, compliance, drift log)';

    public function handle(SimulationRunnerService $runner): int
    {
        $personaArg = $this->argument('persona');
        $cycles = (int) $this->option('cycles');
        $cycles = max(1, min(24, $cycles));

        if (strtolower($personaArg) === 'all') {
            $users = SimulationPersona::with('user')->get()->pluck('user');
            if ($users->isEmpty()) {
                $this->warn('Chưa có persona nào. Chạy: php artisan db:seed --class=SimulationPersonaSeeder');

                return self::FAILURE;
            }
            foreach ($users as $user) {
                $this->info('Chạy persona user_id=' . $user->id . ' (' . $user->email . ')');
                $result = $runner->runForUser($user, $cycles);
                $this->line('  Cycles: ' . $result['cycles_run'] . ', logs: ' . count($result['logs']));
            }

            return self::SUCCESS;
        }

        $persona = SimulationPersona::where('persona_key', $personaArg)->with('user')->first();
        if (! $persona) {
            $user = User::where('email', $personaArg . '@sim.local')->first();
            if (! $user) {
                $this->warn('Không tìm thấy persona "' . $personaArg . '". Chạy: php artisan db:seed --class=SimulationPersonaSeeder');

                return self::FAILURE;
            }
        } else {
            $user = $persona->user;
        }

        $this->info('Chạy simulation: ' . $personaArg . ', user_id=' . $user->id . ', cycles=' . $cycles);
        $result = $runner->runForUser($user, $cycles);
        $this->table(
            ['cycle', 'snapshot_date', 'forecast_error', 'brain_params'],
            array_map(fn ($l) => [
                $l['cycle'],
                $l['snapshot_date'],
                $l['forecast_error'] ?? '-',
                json_encode($l['brain_params'] ?? []),
            ], $result['logs'])
        );

        return self::SUCCESS;
    }
}
