import React from 'react';
import { canCreateReportShare, canUseClinicalFeatures } from '../clients/clientAccess';
import ReportSharing from './ReportSharing';
import ResponsiveAssessmentReport from './ResponsiveAssessmentReport';
import { previousAssessment } from './reportMetrics';
import PwaCard from '../../components/ui/PwaCard';
import PwaButton from '../../components/ui/PwaButton';
import PwaAlert from '../../components/ui/PwaAlert';
import PwaIcon from '../../components/ui/PwaIcon';
import PwaSectionHeader from '../../components/ui/PwaSectionHeader';
import PwaState from '../../components/ui/PwaState';

export default function AssessmentReportFeature({ client, assessment, pdfEnabled = true, currentUser, onBackToClients, onAssessmentUpdated }) {
	if (!client || !assessment) {
		return (
			<PwaState icon="fileText" title="Selecione uma avaliação" description="Abra o histórico de um cliente e escolha o relatório que deseja visualizar." action={<PwaButton type="button" onClick={onBackToClients} icon="arrowLeft">Ir para clientes</PwaButton>} />
		);
	}

	const previous = previousAssessment(client, assessment);
	const pdfUrl = `/bioimpedance/assessments/${assessment.id}/pdf`;

	return (
		<div className="space-y-4">
			<ResponsiveAssessmentReport client={client} assessment={assessment} previous={previous} />

			{pdfEnabled ? <PwaCard className="p-5">
				<PwaSectionHeader icon="fileText" eyebrow="Documento final" title="Relatório em PDF" description="Abra para conferir o documento final ou faça o download no aparelho." />
				<div className="mt-4 grid gap-3 sm:grid-cols-2">
					<a href={`${pdfUrl}?inline=1`} target="_blank" rel="noreferrer" className="pwa-button-secondary pwa-focus inline-flex min-h-12 items-center justify-center gap-2 px-4 text-center text-sm"><PwaIcon name="eye" size={18} />Abrir PDF</a>
					<a href={pdfUrl} className="pwa-button-primary pwa-focus inline-flex min-h-12 items-center justify-center gap-2 px-4 text-center text-sm"><PwaIcon name="download" size={18} />Baixar PDF</a>
				</div>
			</PwaCard> : null}

			{assessment.is_canceled ? (
				<PwaAlert tone="error" title="Compartilhamento indisponível">O compartilhamento está desabilitado porque esta avaliação foi cancelada.</PwaAlert>
			) : (
				<ReportSharing assessment={assessment} canCreate={canCreateReportShare(currentUser)} canRevoke={canUseClinicalFeatures(currentUser)} onAssessmentUpdated={onAssessmentUpdated} />
			)}
		</div>
	);
}
