import React from 'react';
import PwaIcon from '../../components/ui/PwaIcon';

export const bodyscanLayoutPresentation = {
	fitdays_technical_report: {
		label: 'Relatório técnico completo',
		application: 'Fitdays',
		badge: 'Recomendado',
		tone: 'recommended',
	},
	fitdays_vertical_report: {
		label: 'Relatório vertical',
		application: 'Fitdays',
		badge: 'Compatível',
		tone: 'compatible',
	},
	relaxfit_vertical_report: {
		label: 'Relatório vertical',
		application: 'RelaxFit',
		badge: 'Compatível',
		tone: 'compatible',
	},
};

export function isVerticalBodyscanLayout(layout) {
	return ['fitdays_vertical_report', 'relaxfit_vertical_report'].includes(layout);
}

export default function BodyscanReportFormatGuide({ compact = false }) {
	return (
		<section className="rounded-2xl border border-sky-200 bg-sky-50/60 p-4" aria-labelledby="bodyscan-report-format-title">
			<div className="flex items-start gap-3">
				<div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-800"><PwaIcon name="clipboard" size={20} /></div>
				<div>
					<p className="text-xs font-bold uppercase tracking-wide text-sky-700">Formato do relatório</p>
					<h2 id="bodyscan-report-format-title" className="mt-0.5 font-bold text-slate-950">Escolha o relatório disponível no aplicativo</h2>
					<p className="mt-1 text-xs leading-5 text-slate-600">Os dois formatos são aceitos em JPG ou JPEG. O técnico oferece mais dados para conferência, mas o vertical continua compatível.</p>
				</div>
			</div>
			<div className={`mt-3 grid gap-3 ${compact ? '' : 'sm:grid-cols-2'}`}>
				<div className="rounded-xl border-2 border-emerald-300 bg-white p-3">
					<strong className="text-sm text-slate-950">Relatório técnico completo — recomendado</strong>
					<p className="mt-1 text-xs leading-5 text-slate-600">Contém perfil utilizado, faixas apresentadas pelo aplicativo, impedância bioelétrica e dados segmentares.</p>
				</div>
				<div className="rounded-xl border border-slate-200 bg-white p-3">
					<strong className="text-sm text-slate-950">Relatório vertical — compatível</strong>
					<p className="mt-1 text-xs leading-5 text-slate-600">Pode ser enviado normalmente. Quando não trouxer a tabela de impedância, a avaliação seguirá sem esse dado técnico.</p>
				</div>
			</div>
		</section>
	);
}
