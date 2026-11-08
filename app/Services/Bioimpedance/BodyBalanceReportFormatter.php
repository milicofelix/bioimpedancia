<?php

namespace App\Services\Bioimpedance;

final class BodyBalanceReportFormatter
{
    public static function items(string $text): array
    {
        $labels = ['superior' => 'Superior', 'mais baixo' => 'Mais baixo', 'parte superior' => 'Parte superior'];
        preg_match_all('/(?<!\p{L})(Parte superior|Mais baixo|Superior)(?!\p{L})\s*[:：]*/iu', $text, $matches, PREG_OFFSET_CAPTURE);
        $items = [];
        foreach ($matches[0] as $index => [$heading, $start]) {
            // Regex offsets and substr both use bytes, preserving UTF-8 boundaries.
            $valueStart = $start + strlen($heading);
            $nextStart = $matches[0][$index + 1][1] ?? null;
            $value = substr($text, $valueStart, $nextStart === null ? null : $nextStart - $valueStart);
            $value = preg_replace('/^[\s:：;]+|[\s;]+$/u', '', $value) ?? trim($value);
            if ($value !== '') {
                $items[] = ['label' => $labels[mb_strtolower($matches[1][$index][0])], 'value' => $value];
            }
        }

        return $items;
    }
}
