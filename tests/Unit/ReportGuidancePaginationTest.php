<?php

namespace Tests\Unit;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\Fixtures\BodyReportPresentation;
use Tests\TestCase;

class ReportGuidancePaginationTest extends TestCase
{
    public function test_both_profiles_and_historical_texts_do_not_create_a_limits_only_page(): void
    {
        foreach ([true, false] as $profile) {
            foreach ([false, true] as $historical) {
                $data = BodyReportPresentation::data($profile, $historical);
                $html = view('bioimpedance.report-pdf', $data)->render();
                $this->assertStringContainsString('Responsável e limites do relatório', $html);
                $this->assertStringContainsString($data['clinic']['technical_notice'], $html);
                $this->assertStringNotContainsString('<div class="section-title" style="color: #052f59;">Acompanhamento</div>', $html);
                $pdf = Pdf::loadHTML($html)->setPaper('a4');
                $pdf->getDomPDF()->getOptions()->setIsRemoteEnabled(false);
                $pageTexts = [];
                $pdf->getDomPDF()->setCallbacks([[
                    'event' => 'end_frame',
                    'f' => function ($frame, $canvas) use (&$pageTexts): void {
                        if ($frame->get_node()->nodeName === '#text') {
                            $page = $canvas->get_page_number();
                            $pageTexts[$page] = ($pageTexts[$page] ?? '').' '.$frame->get_node()->nodeValue;
                        }
                    },
                ]]);
                $binary = $pdf->output();
                $directory = getenv('VITA_SCAN_PDF_QA_DIR');
                if ($directory && is_dir($directory)) {
                    file_put_contents($directory.'/profile-'.($profile ? 'a' : 'b').($historical ? '-historical' : '-new').'.pdf', $binary);
                }
                $this->assertSame(6, $pdf->getDomPDF()->getCanvas()->get_page_count(), 'Perfil '.($profile ? 'A' : 'B').', histórico '.(int) $historical);
                $limitsPages = array_filter($pageTexts, fn ($text) => str_contains($text, 'Limites do relatório'));
                $this->assertCount(1, $limitsPages);
                $this->assertStringContainsString('Profissional de teste', array_values($limitsPages)[0]);
                $this->assertStringContainsString('Acompanhamento', array_values($limitsPages)[0]);
            }
        }
    }
}
