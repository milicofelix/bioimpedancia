import React from 'react';
import { canCreateReportShare, canUseClinicalFeatures } from '../clients/clientAccess';
import ReportSharing from './ReportSharing';
import ResponsiveAssessmentReport from './ResponsiveAssessmentReport';
import { previousAssessment } from './reportMetrics';

export default function AssessmentReportFeature({ client, assessment, currentUser, onBackToClients, onAssessmentUpdated }) {
	if (!client || !assessment) {
		return (
			<section className="rounded-2xl border border-amber-200 bg-white p-6 text-center shadow-sm">
				<p className="text-xs font-bold uppercase tracking-wide text-amber-700">Relatório</p>
				<h2 className="mt-2 text-xl font-bold text-slate-950">Selecione uma avaliação</h2>
				<p className="mt-2 text-sm leading-6 text-slate-600">Abra o histórico de um cliente e escolha o relatório que deseja visualizar.</p>
				<button type="button" onClick={onBackToClients} className="mt-5 rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white">Ir para clientes</button>
			</section>
		);
	}

	const previous = previousAssessment(client, assessment);
	const pdfUrl = `/bioimpedance/assessments/${assessment.id}/pdf`;

	return (
		<div className="space-y-4">
			<ResponsiveAssessmentReport client={client} assessment={assessment} previous={previous} />

			<section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
				<h2 className="text-lg font-bold text-slate-950">Documento PDF</h2>
				<p className="mt-1 text-sm leading-6 text-slate-600">Abra para conferir o documento final ou faça o download no aparelho.</p>
				<div className="mt-4 grid gap-3 sm:grid-cols-2">
					<a href={`${pdfUrl}?inline=1`} target="_blank" rel="noreferrer" className="rounded-xl border border-slate-300 px-4 py-3 text-center text-sm font-bold text-slate-700">Abrir PDF</a>
					<a href={pdfUrl} className="rounded-xl bg-slate-950 px-4 py-3 text-center text-sm font-bold text-white">Baixar PDF</a>
				</div>
			</section>

			{assessment.is_canceled ? (
				<section className="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm font-semibold leading-6 text-rose-800">O compartilhamento está desabilitado porque esta avaliação foi cancelada.</section>
			) : (
				<ReportSharing assessment={assessment} canCreate={canCreateReportShare(currentUser)} canRevoke={canUseClinicalFeatures(currentUser)} onAssessmentUpdated={onAssessmentUpdated} />
			)}
		</div>
	);
}
