import React from 'react';
import PwaIcon from '../pwa/components/ui/PwaIcon';

function HeaderAction({ icon, children, onClick, disabled = false, primary = false }) {
	return (
		<button
			type="button"
			onClick={onClick}
			disabled={disabled}
			className={`inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border px-4 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto ${primary ? 'border-emerald-700 bg-emerald-700 text-white hover:bg-emerald-800' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}`}
		>
			<PwaIcon name={icon} size={18} />{children}
		</button>
	);
}

export default function ClientAssessmentHeader({
	assessmentCount,
	canStartAssessment,
	hasReport,
	onStartAssessment,
	onOpenEvolution,
	onOpenReport,
}) {
	return (
		<header className="no-print rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-sky-50 p-4 shadow-sm sm:p-5">
			<div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
				<div>
					<p className="flex items-center gap-2 text-xs font-extrabold uppercase tracking-[0.14em] text-emerald-700"><PwaIcon name="clipboard" size={17} />Avaliação corporal</p>
					<h2 className="mt-1 text-xl font-extrabold text-slate-950">Bioimpedância e evolução corporal</h2>
					<p className="mt-1 max-w-2xl text-sm leading-6 text-slate-600">Medições, evolução, orientações associadas e relatórios corporais ficam reunidos exclusivamente neste módulo.</p>
					<p className="mt-2 text-xs font-bold text-slate-500">{assessmentCount} avaliação(ões) registrada(s)</p>
				</div>
				<div className="grid w-full gap-2 sm:grid-cols-3 lg:w-auto lg:flex">
					<HeaderAction icon="plus" onClick={onStartAssessment} disabled={!canStartAssessment} primary>Nova avaliação</HeaderAction>
					{onOpenEvolution ? <HeaderAction icon="trendUp" onClick={onOpenEvolution} disabled={!hasReport}>Ver evolução</HeaderAction> : null}
					{onOpenReport ? <HeaderAction icon="fileText" onClick={onOpenReport} disabled={!hasReport}>Relatório corporal</HeaderAction> : null}
				</div>
			</div>
		</header>
	);
}
