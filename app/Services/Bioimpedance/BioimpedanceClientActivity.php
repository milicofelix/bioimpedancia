<?php

namespace App\Services\Bioimpedance;

use App\Models\Bioimpedance\BioimpedanceClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BioimpedanceClientActivity
{
    private const SCREENING_DATES = [
        'screeningSessions as latest_screening_created_at' => 'created_at',
        'screeningSessions as latest_screening_updated_at' => 'updated_at',
    ];

    public function withScreeningActivity(Builder $query): Builder
    {
        foreach (self::SCREENING_DATES as $relation => $column) {
            $query->withMax($relation, $column);
        }

        return $query;
    }

    public function latest(BioimpedanceClient $client): ?string
    {
        foreach (self::SCREENING_DATES as $relation => $column) {
            $alias = explode(' as ', $relation)[1];
            if (! array_key_exists($alias, $client->getAttributes())) {
                $client->loadMax($relation, $column);
            }
        }

        // Registration/import time matters, not the date of a historical measurement.
        // Clinical dates remain unchanged and continue to order the assessment history.
        $dates = collect([
            $client->created_at,
            $client->updated_at,
            $client->latest_screening_created_at,
            $client->latest_screening_updated_at,
        ]);

        foreach ($client->assessments as $assessment) {
            $dates->push($assessment->created_at, $assessment->updated_at);
        }

        return $dates->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sortByDesc(fn (Carbon $date) => $date->getTimestamp())
            ->first()?->toIso8601String();
    }
}
