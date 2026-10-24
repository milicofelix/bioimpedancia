<?php

namespace App\Services\Bioimpedance;

use App\Models\AppAudit;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceClient;
use App\Models\Bioimpedance\BioimpedanceReportShare;

class BioimpedanceAdminDashboard
{
    public function payload(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $today = $now->copy()->startOfDay();
        $upcomingLimit = $today->copy()->addDays(14);

        $monthlyAssessments = BioimpedanceAssessment::query()
            ->whereNull('canceled_at')
            ->whereBetween('evaluated_at', [$monthStart, $monthEnd]);

        $clientsWithAssessmentThisMonth = BioimpedanceAssessment::query()
            ->whereNull('canceled_at')
            ->whereBetween('evaluated_at', [$monthStart, $monthEnd])
            ->distinct()
            ->pluck('bioimpedance_client_id');

        $returningClientsThisMonth = BioimpedanceClient::query()
            ->whereIn('id', $clientsWithAssessmentThisMonth)
            ->whereHas('assessments', fn ($query) => $query
                ->whereNull('canceled_at')
                ->where('evaluated_at', '<', $monthStart))
            ->count();

        $clientsWithoutReturnQuery = BioimpedanceClient::query()
            ->whereNull('inactivated_at')
            ->whereNull('anonymized_at')
            ->whereNotNull('next_assessment_at')
            ->whereDate('next_assessment_at', '<', $today)
            ->whereDoesntHave('assessments', fn ($query) => $query
                ->whereNull('canceled_at')
                ->whereColumn('evaluated_at', '>=', 'bioimpedance_clients.next_assessment_at'))
            ->orderBy('next_assessment_at');

        $clientsWithoutReturnCount = (clone $clientsWithoutReturnQuery)->count();
        $clientsWithoutReturn = $clientsWithoutReturnQuery->limit(5)->get();

        $upcomingReassessmentsQuery = BioimpedanceClient::query()
            ->whereNull('inactivated_at')
            ->whereNull('anonymized_at')
            ->whereBetween('next_assessment_at', [$today->toDateString(), $upcomingLimit->toDateString()])
            ->orderBy('next_assessment_at');

        $upcomingReassessmentsCount = (clone $upcomingReassessmentsQuery)->count();
        $upcomingReassessments = $upcomingReassessmentsQuery->limit(5)->get();

        $professionalCounts = BioimpedanceAssessment::query()
            ->with('professional')
            ->whereNull('canceled_at')
            ->whereBetween('evaluated_at', [$monthStart, $monthEnd])
            ->get()
            ->groupBy(fn (BioimpedanceAssessment $assessment) => $assessment->professional?->name ?? 'Sem profissional')
            ->map(fn ($assessments, string $name) => [
                'name' => $name,
                'assessments_count' => $assessments->count(),
            ])
            ->values()
            ->sortByDesc('assessments_count')
            ->values()
            ->all();

        $recentAssessments = BioimpedanceAssessment::query()
            ->with(['client', 'professional'])
            ->whereNull('canceled_at')
            ->latest('evaluated_at')
            ->limit(6)
            ->get()
            ->map(fn (BioimpedanceAssessment $assessment) => [
                'id' => $assessment->id,
                'client_name' => $assessment->client?->full_name,
                'professional_name' => $assessment->professional?->name ?? 'Sem profissional',
                'evaluated_at' => $assessment->evaluated_at?->toIso8601String(),
                'weight_kg' => (float) $assessment->weight_kg,
                'calculated_bmi' => (float) $assessment->calculated_bmi,
            ])
            ->all();

        return [
            'period_label' => $now->translatedFormat('F/Y'),
            'generated_at' => $now->toIso8601String(),
            'cards' => [
                'assessments_this_month' => (clone $monthlyAssessments)->count(),
                'clients_new_this_month' => BioimpedanceClient::query()->whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                'returning_clients_this_month' => $returningClientsThisMonth,
                'clients_without_return' => $clientsWithoutReturnCount,
                'upcoming_reassessments' => $upcomingReassessmentsCount,
                'evolutions_registered' => BioimpedanceClient::query()
                    ->whereNull('inactivated_at')
                    ->whereNull('anonymized_at')
                    ->has('assessments', '>=', 2, 'and', fn ($query) => $query->whereNull('canceled_at'))
                    ->count(),
                'reports_issued_this_month' => AppAudit::query()
                    ->where('action', 'bioimpedance_assessment.report_downloaded')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count(),
                'shares_sent_this_month' => BioimpedanceReportShare::query()
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count(),
            ],
            'average_assessments_per_professional' => count($professionalCounts)
                ? round((clone $monthlyAssessments)->count() / count($professionalCounts), 1)
                : 0,
            'professionals' => $professionalCounts,
            'clients_without_return' => $clientsWithoutReturn
                ->map(fn (BioimpedanceClient $client) => $this->reminderPayload($client))
                ->all(),
            'upcoming_reassessments' => $upcomingReassessments
                ->map(fn (BioimpedanceClient $client) => $this->reminderPayload($client))
                ->all(),
            'recent_assessments' => $recentAssessments,
        ];
    }

    private function reminderPayload(BioimpedanceClient $client): array
    {
        return [
            'id' => $client->id,
            'full_name' => $client->full_name,
            'phone' => $client->phone,
            'email' => $client->email,
            'next_assessment_at' => $client->next_assessment_at?->toDateString(),
        ];
    }
}
