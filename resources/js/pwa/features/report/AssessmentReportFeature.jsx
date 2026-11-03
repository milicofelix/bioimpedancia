import React, { useState } from 'react';
import { formatDate } from '../../../shared/formatters';
import ReportExportPanel from './ReportExportPanel';
import ReportViewSelector from './ReportViewSelector';
import ResponsiveAssessmentReport from './ResponsiveAssessmentReport';
import { previousAssessment } from './reportMetrics';
import PwaCard from '../../components/ui/PwaCard';
import PwaButton from '../../components/ui/PwaButton';
import PwaSectionHeader from '../../components/ui/PwaSectionHeader';
import PwaState from '../../components/ui/PwaState';
import PwaScrollToTopButton from '../../components/ui/PwaScrollToTopButton';

export default function AssessmentReportFeature({ client, assessment, pdfEnabled = true, currentUser, onBackToClients, onAssessmentUpdated }) {
	if (!client || !assessment) {
		return (
			<PwaState icon="fileText" title="Selecione uma avaliação" description="Abra o histórico de um cliente e escolha o relatório que deseja visualizar." action={<PwaButton type="button" onClick={onBackToClients} icon="arrowLeft">Ir para clientes</PwaButton>} />
		);
	}

	return <AssessmentReportContent key={assessment.id} client={client} assessment={assessment} pdfEnabled={pdfEnabled} currentUser={currentUser} onAssessmentUpdated={onAssessmentUpdated} />;
}

function AssessmentReportContent({ client, assessment, pdfEnabled, currentUser, onAssessmentUpdated }) {
	const [views, setViews] = useState({ active: null, analysis: false, export: false });
	const previous = previousAssessment(client, assessment);

	function selectView(view) {
		setViews((current) => ({ ...current, active: current.active === view ? null : view, [view]: true }));
	}

	return (
		<div className="min-w-0 space-y-4">
			<PwaCard className="p-4 sm:p-5 print:hidden">
				<PwaSectionHeader icon="fileText" eyebrow="Relatório corporal" title={client.full_name} description={`Avaliação #${assessment.id} · ${formatDate(assessment.evaluated_at)}`} />
				<div className="mt-4"><ReportViewSelector activeView={views.active} onSelect={selectView} /></div>
			</PwaCard>

			<section id="report-analysis-panel" hidden={views.active !== 'analysis'} aria-labelledby="report-analysis-button" className="min-w-0">
				{views.analysis ? <ResponsiveAssessmentReport client={client} assessment={assessment} previous={previous} /> : null}
			</section>
			<section id="report-export-panel" hidden={views.active !== 'export'} aria-labelledby="report-export-button" className="min-w-0 print:hidden">
				{views.export ? <ReportExportPanel assessment={assessment} pdfEnabled={pdfEnabled} currentUser={currentUser} onAssessmentUpdated={onAssessmentUpdated} /> : null}
			</section>

			{views.active ? <PwaScrollToTopButton key={views.active} triggerId={views.active === 'export' ? 'report-export-scroll-top-trigger' : 'report-scroll-top-trigger'} /> : null}
		</div>
	);
}
