<?php

namespace Tests\Unit;

use App\Services\Bioimpedance\ApprovedObservationContext;
use Tests\TestCase;

class ApprovedObservationSnapshotTest extends TestCase
{
    public function test_json_key_order_and_numeric_id_types_do_not_change_the_approval_identity(): void
    {
        $current = $this->snapshot();
        $stored = array_reverse($current, true);
        $stored['output_id'] = '12';
        $stored['approved_by_user_id'] = '7';
        $stored['findings'] = [['text' => 'Valor registrado.', 'id' => 'metric:weight']];

        $this->assertTrue((new ApprovedObservationContext)->matchesSnapshot($stored, $current));
    }

    public function test_reapproval_text_or_author_changes_invalidate_the_snapshot(): void
    {
        $guard = new ApprovedObservationContext;
        $snapshot = $this->snapshot();
        foreach (['output_id' => 13, 'approved_by_user_id' => 8, 'approved_at' => '2026-11-03T12:10:00+00:00', 'text_sha256' => hash('sha256', 'Texto alterado.')] as $field => $value) {
            $this->assertFalse($guard->matchesSnapshot([...$snapshot, $field => $value], $snapshot));
        }
        $this->assertFalse($guard->matchesSnapshot(null, $snapshot));
        $this->assertFalse($guard->matchesSnapshot([], $snapshot));
    }

    private function snapshot(): array
    {
        return [
            'output_id' => 12, 'approved_at' => '2026-11-03T12:00:00+00:00',
            'approved_by_user_id' => 7, 'text_sha256' => hash('sha256', 'Observação aprovada.'),
            'findings' => [['id' => 'metric:weight', 'text' => 'Valor registrado.']],
        ];
    }
}
