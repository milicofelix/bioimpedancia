<?php

namespace Tests\Feature;

use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceClient;
use Database\Seeders\BioimpedanceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BioimpedanceDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_clients_with_evolution_and_regression_histories(): void
    {
        $this->seed(BioimpedanceDemoSeeder::class);

        $this->assertSame(15, BioimpedanceClient::query()->where('email', 'like', 'demo.%@ricosty.local')->count());
        $this->assertSame(75, BioimpedanceAssessment::query()
            ->whereHas('client', fn ($query) => $query->where('email', 'like', 'demo.%@ricosty.local'))
            ->count());

        $evolution = $this->firstAndLast('demo.ana.mendes@ricosty.local');
        $this->assertLessThan($evolution['first']->weight_kg, $evolution['last']->weight_kg);
        $this->assertLessThan($evolution['first']->body_fat_percentage, $evolution['last']->body_fat_percentage);
        $this->assertGreaterThan($evolution['first']->skeletal_muscle_percentage, $evolution['last']->skeletal_muscle_percentage);

        $regression = $this->firstAndLast('demo.gabriela.souza@ricosty.local');
        $this->assertGreaterThan($regression['first']->weight_kg, $regression['last']->weight_kg);
        $this->assertGreaterThan($regression['first']->body_fat_percentage, $regression['last']->body_fat_percentage);
        $this->assertLessThan($regression['first']->skeletal_muscle_percentage, $regression['last']->skeletal_muscle_percentage);

        $stable = $this->firstAndLast('demo.daniel.pereira@ricosty.local');
        $this->assertLessThanOrEqual(0.3, abs($stable['last']->weight_kg - $stable['first']->weight_kg));
        $this->assertArrayHasKey('indicators', $stable['last']->analysis);
    }

    private function firstAndLast(string $email): array
    {
        $client = BioimpedanceClient::query()->where('email', $email)->firstOrFail();

        return [
            'first' => $client->assessments()->oldest('evaluated_at')->firstOrFail(),
            'last' => $client->assessments()->latest('evaluated_at')->firstOrFail(),
        ];
    }
}
