<?php

namespace App\Services\Bioimpedance;

use Illuminate\Support\Str;

class ClinicalTextGuard
{
    public function contains(string $text, string $expression): bool
    {
        $text = Str::lower($text);
        // Only explicit disclaimers are exempt. Other diagnoses, prescriptions and
        // disease assertions still go through exactly the same forbidden list.
        if (in_array($expression, ['diagnóstico de', 'diagnostico de'], true)) {
            $text = preg_replace('/não (?:representa(?:m)?|constitu(?:i|em)|estabelece(?:m)?|confirma(?:m)?|comprova(?:m)?) (?:um )?diagn[oó]stico de/iu', '', $text);
        }

        // "tome" must not match "retome". Keep the phrase boundary Unicode-aware.
        return preg_match('/(?<![\p{L}\p{N}_])'.preg_quote($expression, '/').'/u', $text) === 1;
    }
}
