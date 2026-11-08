<?php

namespace Tests\Unit;

use App\Domain\Bioimpedance\BodyFatReportContext;
use App\Models\Bioimpedance\BioimpedanceAssessment;
use App\Models\Bioimpedance\BioimpedanceExtendedMetric;
use App\Services\Bioimpedance\AssessmentFindings;
use App\Services\Bioimpedance\AssessmentNarrative;
use App\Services\Bioimpedance\BioimpedanceAssistantContext;
use App\Services\Bioimpedance\BodyBalanceReportFormatter;
use Tests\TestCase;

class ReportIndicatorCoherenceTest extends TestCase
{
    public function test_assistant_uses_printed_body_fat_classification_without_applying_other_device_ranges(): void
    {
        $assessment = new BioimpedanceAssessment([
            'device_model' => 'Bodyscan Pro', 'body_fat_percentage' => 27.2,
            'analysis' => ['indicators' => ['body_fat' => ['pending' => true]]],
        ]);
        $assessment->setRelation('extendedMetrics', collect([new BioimpedanceExtendedMetric([
            'metric_code' => 'body_fat_percentage', 'numeric_value' => 27.2,
            'unit' => '%', 'application_classification' => 'Muito alto',
        ])]));
        $metrics = (new \ReflectionMethod(BioimpedanceAssistantContext::class, 'generalMetrics'))
            ->invoke(app(BioimpedanceAssistantContext::class), $assessment);
        $fat = collect($metrics)->firstWhere('code', 'body_fat_percentage');

        $this->assertSame('Muito alto', $fat['classification']);
        $this->assertSame('equipment_report', $fat['classification_origin']);
        $text = (new AssessmentNarrative)->composition(['general_metrics' => $metrics]);
        $this->assertStringContainsString('com classificação Muito alto', $text);
        $this->assertStringNotContainsString('sem classificação textual', $text);
        $findings = (new AssessmentFindings)->from(['general_metrics' => $metrics]);
        $this->assertStringContainsString('no relatório do equipamento', $findings[0]['text']);

        $assessment->setRelation('extendedMetrics', collect());
        $missing = (new \ReflectionMethod(BioimpedanceAssistantContext::class, 'generalMetrics'))
            ->invoke(app(BioimpedanceAssistantContext::class), $assessment);
        $this->assertNull(collect($missing)->firstWhere('code', 'body_fat_percentage')['classification']);
    }

    public function test_old_observation_is_flagged_but_not_rewritten(): void
    {
        $notes = 'A gordura corporal foi estimada em 27,2%, sem classificação textual disponível no contexto.';
        $original = $notes;
        $metrics = [['metric_code' => 'body_fat_percentage', 'application_classification' => 'Muito alto']];
        $this->assertStringContainsString('Muito alto', BodyFatReportContext::observationReviewNotice($notes, $metrics));
        $this->assertSame($original, $notes);
        $this->assertNull(BodyFatReportContext::observationReviewNotice($notes, []));
        $this->assertNull(BodyFatReportContext::observationReviewNotice('Gordura corporal com classificação Muito alto.', $metrics));
    }

    public function test_balance_values_have_one_separator_and_preserve_classifications(): void
    {
        $items = BodyBalanceReportFormatter::items('Superior: : Equilibrado; Mais baixo: Equilibrado; Parte superior: Ligeiramente desequilibrado');
        $this->assertSame([
            ['label' => 'Superior', 'value' => 'Equilibrado'],
            ['label' => 'Mais baixo', 'value' => 'Equilibrado'],
            ['label' => 'Parte superior', 'value' => 'Ligeiramente desequilibrado'],
        ], $items);
        $this->assertSame([['label' => 'Parte superior', 'value' => 'Equilibrado']], BodyBalanceReportFormatter::items('Parte superior: Equilibrado'));
        $this->assertSame([], BodyBalanceReportFormatter::items(''));
    }
}
