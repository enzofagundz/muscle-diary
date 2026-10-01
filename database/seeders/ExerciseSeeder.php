<?php

namespace Database\Seeders;

use App\Enums\LoadUnit;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $exercise) {
            Exercise::withTrashed()->updateOrCreate(
                ['user_id' => null, 'name' => $exercise['name']],
                [
                    'muscle_group' => $exercise['group'],
                    'unit_default' => $exercise['unit'],
                    'deleted_at' => null,
                ],
            );
        }
    }

    /**
     * The shared catalog. Exercises loaded on a pin machine default to plates;
     * everything else defaults to kilograms.
     *
     * @return array<int, array{name: string, group: string, unit: LoadUnit}>
     */
    private function catalog(): array
    {
        $peito = MuscleGroup::Peito->value;
        $costas = MuscleGroup::Costas->value;
        $ombros = MuscleGroup::Ombros->value;
        $trapezio = MuscleGroup::Trapezio->value;
        $biceps = MuscleGroup::Biceps->value;
        $triceps = MuscleGroup::Triceps->value;
        $antebraco = MuscleGroup::Antebraco->value;
        $quadriceps = MuscleGroup::Quadriceps->value;
        $posteriores = MuscleGroup::Posteriores->value;
        $gluteos = MuscleGroup::Gluteos->value;
        $panturrilha = MuscleGroup::Panturrilha->value;
        $abdomen = MuscleGroup::Abdomen->value;

        $kg = LoadUnit::Kilograms;
        $placa = LoadUnit::Plates;
        $corporal = LoadUnit::Bodyweight;

        $catalog = [
            $peito => [
                ['Supino reto com barra', $kg],
                ['Supino inclinado com barra', $kg],
                ['Supino inclinado com halteres', $kg],
                ['Supino declinado', $kg],
                ['Supino máquina', $placa],
                ['Supino articulado', $placa],
                ['Crucifixo reto com halteres', $kg],
                ['Crucifixo inclinado com halteres', $kg],
                ['Crucifixo na máquina', $placa],
                ['Crossover no cabo', $kg],
                ['Peck deck', $placa],
                ['Flexão de braço', $corporal],
            ],
            $costas => [
                ['Barra fixa', $corporal],
                ['Puxada frente', $placa],
                ['Puxada supinada', $placa],
                ['Puxada pegada neutra', $placa],
                ['Remada curvada com barra', $kg],
                ['Remada cavalinho', $placa],
                ['Remada máquina', $placa],
                ['Remada baixa no cabo', $placa],
                ['Remada unilateral com halter', $kg],
                ['Pulldown no cabo', $kg],
                ['Pullover na máquina', $placa],
                ['Pullover com halter', $kg],
            ],
            $ombros => [
                ['Desenvolvimento com barra', $kg],
                ['Desenvolvimento com halteres', $kg],
                ['Desenvolvimento máquina', $placa],
                ['Desenvolvimento Arnold', $kg],
                ['Elevação lateral com halteres', $kg],
                ['Elevação lateral na máquina', $placa],
                ['Elevação lateral no cabo', $kg],
                ['Elevação frontal', $kg],
                ['Elevação posterior na máquina', $placa],
                ['Crucifixo inverso com halteres', $kg],
                ['Face pull', $kg],
            ],
            $trapezio => [
                ['Encolhimento com barra', $kg],
                ['Encolhimento com halteres', $kg],
                ['Encolhimento na máquina', $placa],
                ['Remada alta', $kg],
            ],
            $biceps => [
                ['Rosca direta com barra', $kg],
                ['Rosca direta com barra W', $kg],
                ['Rosca alternada', $kg],
                ['Rosca martelo', $kg],
                ['Rosca Scott', $kg],
                ['Rosca Scott máquina', $placa],
                ['Rosca no cabo', $kg],
                ['Rosca concentrada', $kg],
            ],
            $triceps => [
                ['Tríceps testa', $kg],
                ['Tríceps pulley', $kg],
                ['Tríceps corda', $kg],
                ['Tríceps francês', $kg],
                ['Tríceps coice', $kg],
                ['Tríceps banco', $corporal],
                ['Mergulho nas paralelas', $corporal],
                ['Tríceps máquina', $placa],
            ],
            $antebraco => [
                ['Rosca de punho', $kg],
                ['Rosca inversa de punho', $kg],
                ['Rosca inversa', $kg],
            ],
            $quadriceps => [
                ['Agachamento livre', $kg],
                ['Agachamento no Smith', $kg],
                ['Agachamento frontal', $kg],
                ['Agachamento hack', $placa],
                ['Agachamento búlgaro', $kg],
                ['Leg press', $placa],
                ['Leg press 45', $placa],
                ['Cadeira extensora', $placa],
                ['Passada com halteres', $kg],
            ],
            $posteriores => [
                ['Levantamento terra', $kg],
                ['Levantamento terra romeno', $kg],
                ['Stiff com barra', $kg],
                ['Mesa flexora', $placa],
                ['Cadeira flexora', $placa],
                ['Flexora unilateral', $placa],
                ['Bom dia', $kg],
            ],
            $gluteos => [
                ['Elevação pélvica', $kg],
                ['Hip thrust máquina', $placa],
                ['Cadeira abdutora', $placa],
                ['Cadeira adutora', $placa],
                ['Glúteo no cabo', $kg],
                ['Coice na máquina', $placa],
            ],
            $panturrilha => [
                ['Panturrilha em pé', $placa],
                ['Panturrilha sentado', $placa],
                ['Panturrilha no leg press', $placa],
                ['Panturrilha no Smith', $kg],
            ],
            $abdomen => [
                ['Abdominal máquina', $placa],
                ['Abdominal no cabo', $kg],
                ['Abdominal supra', $corporal],
                ['Abdominal infra', $corporal],
                ['Elevação de pernas suspenso', $corporal],
                ['Prancha', $corporal],
            ],
        ];

        return collect($catalog)
            ->flatMap(fn (array $exercises, string $group): array => array_map(
                fn (array $exercise): array => [
                    'name' => $exercise[0],
                    'group' => $group,
                    'unit' => $exercise[1],
                ],
                $exercises,
            ))
            ->all();
    }
}
