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
					<a href={`${pdfUrl}?inline=1`} target="_blank" rel="noreferrer" className="pwa-focus flex min-h-20 items-center gap-3 rounded-2xl border border-ricosty-200 bg-white p-4 text-left text-ricosty-900 transition hover:bg-ricosty-50"><span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-ricosty-50"><PwaIcon name="eye" size={19} /></span><span><strong className="block text-sm">Conferir PDF</strong><span className="mt-1 block text-xs text-slate-600">Abre o documento em uma nova tela.</span></span></a>
					<a href={pdfUrl} className="pwa-focus flex min-h-20 items-center gap-3 rounded-2xl bg-gradient-to-r from-ricosty-700 to-ricosty-900 p-4 text-left text-white shadow-lg shadow-ricosty-100 transition hover:brightness-105"><span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10"><PwaIcon name="download" size={19} /></span><span><strong className="block text-sm">Baixar PDF</strong><span className="mt-1 block text-xs text-white/80">Salva uma cópia neste aparelho.</span></span></a>
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
