import React from 'react';
import { canCreateReportShare } from '../clients/clientAccess';
import PwaCard from '../../components/ui/PwaCard';
import PwaAlert from '../../components/ui/PwaAlert';
import PwaIcon from '../../components/ui/PwaIcon';
import PwaSectionHeader from '../../components/ui/PwaSectionHeader';
import ReportSharing from './ReportSharing';

export default function ReportExportPanel({ assessment, pdfEnabled, currentUser, onAssessmentUpdated }) {
	const pdfUrl = `/bioimpedance/assessments/${assessment.id}/pdf`;
	const canShare = canCreateReportShare(currentUser);

	return (
		<div className="space-y-4">
			<div id="report-export-scroll-top-trigger" className="h-px" aria-hidden="true" />
			{assessment.is_canceled ? (
				<PwaAlert tone="error" title="Compartilhamento indisponível">O compartilhamento está desabilitado porque esta avaliação foi cancelada.</PwaAlert>
			) : (
				<ReportSharing assessment={assessment} canCreate={canShare} canRevoke={canShare} onAssessmentUpdated={onAssessmentUpdated} />
			)}

			{pdfEnabled ? <PwaCard className="p-5">
				<PwaSectionHeader icon="fileText" eyebrow="Documento final" title="Relatório em PDF" description="Abra para conferir o documento final ou faça o download no aparelho." />
				<div className="mt-4 grid gap-3 sm:grid-cols-2">
					<a href={`${pdfUrl}?inline=1`} target="_blank" rel="noreferrer" className="pwa-focus flex min-h-20 items-center gap-3 rounded-2xl border border-ricosty-200 bg-white p-4 text-left text-ricosty-900 transition hover:bg-ricosty-50"><span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-ricosty-50"><PwaIcon name="eye" size={19} /></span><span><strong className="block text-sm">Conferir PDF</strong><span className="mt-1 block text-xs text-slate-600">Abre o documento em uma nova tela.</span></span></a>
					<a href={pdfUrl} className="pwa-focus flex min-h-20 items-center gap-3 rounded-2xl bg-gradient-to-r from-ricosty-700 to-ricosty-900 p-4 text-left text-white shadow-lg shadow-ricosty-100 transition hover:brightness-105"><span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10"><PwaIcon name="download" size={19} /></span><span><strong className="block text-sm">Baixar PDF</strong><span className="mt-1 block text-xs text-white/80">Salva uma cópia neste aparelho.</span></span></a>
				</div>
			</PwaCard> : <PwaAlert tone="info" title="PDF desativado">A geração de PDF está desativada nas configurações da clínica. Os links disponíveis podem ser consultados acima.</PwaAlert>}
		</div>
	);
}
