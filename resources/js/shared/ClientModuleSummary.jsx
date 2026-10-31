import React from 'react';
import PwaIcon from '../pwa/components/ui/PwaIcon';
import { formatDate } from './formatters';
import { defaultClinicModules } from './clinicModules';

const moduleStyles = {
	assessment: {
		icon: 'clipboard',
		iconClasses: 'bg-emerald-100 text-emerald-700',
		buttonClasses: 'border-emerald-200 text-emerald-800 hover:bg-emerald-50',
	},
	screening: {
		icon: 'check',
		iconClasses: 'bg-teal-100 text-teal-700',
		buttonClasses: 'border-teal-200 text-teal-800 hover:bg-teal-50',
	},
	reports: {
		icon: 'fileText',
		iconClasses: 'bg-sky-100 text-sky-700',
		buttonClasses: 'border-sky-200 text-sky-800 hover:bg-sky-50',
	},
};

function ModuleCard({ type, title, description, status, actionLabel, onAction, disabled = false, disabledReason = '' }) {
	const style = moduleStyles[type];

	return (
		<article role="listitem" className="flex min-h-full w-[82vw] max-w-sm shrink-0 snap-start flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:w-auto md:max-w-none md:shrink">
			<div className={`flex h-11 w-11 items-center justify-center rounded-xl ${style.iconClasses}`} aria-hidden="true">
				<PwaIcon name={style.icon} />
			</div>
			<h3 className="mt-4 text-base font-extrabold text-slate-950">{title}</h3>
			<p className="mt-1 flex-1 text-sm leading-6 text-slate-600">{description}</p>
			<p className="mt-3 text-xs font-bold text-slate-500">{status}</p>
			<button
				type="button"
				onClick={onAction}
				disabled={disabled}
				aria-describedby={disabled && disabledReason ? `${type}-module-disabled-reason` : undefined}
				className={`mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400 ${style.buttonClasses}`}
			>
				{actionLabel}<PwaIcon name="chevronRight" size={17} />
			</button>
			{disabled && disabledReason ? <p id={`${type}-module-disabled-reason`} className="mt-2 text-xs leading-5 text-amber-700">{disabledReason}</p> : null}
		</article>
	);
}

export default function ClientModuleSummary({
	client,
	canStartAssessment,
	canAccessScreenings,
	onStartAssessment,
	onOpenScreenings,
	onOpenReports,
	enabledModules = defaultClinicModules,
}) {
	const assessments = [...(client?.assessments ?? [])]
		.filter((assessment) => !assessment.is_canceled)
		.sort((left, right) => new Date(right.evaluated_at) - new Date(left.evaluated_at));
	const latestAssessment = assessments[0] ?? null;
	const clientUnavailable = !client?.is_active || client?.is_anonymized;
	const hasEnabledModules = enabledModules.assessment || enabledModules.screening || enabledModules.reports;
	const enabledCount = [enabledModules.assessment, enabledModules.screening, enabledModules.reports].filter(Boolean).length;
	const enabledLabels = [
		enabledModules.assessment ? 'avaliações corporais' : null,
		enabledModules.screening ? 'triagens' : null,
		enabledModules.reports ? 'relatórios corporais' : null,
	].filter(Boolean);
	const heading = enabledCount > 1
		? 'Escolha o módulo para continuar'
		: enabledModules.assessment
			? 'Avaliação corporal'
			: enabledModules.screening
				? 'Triagens'
				: enabledModules.reports
					? 'Relatórios corporais'
					: 'Atendimentos indisponíveis';
	const introduction = enabledCount > 1
		? `Módulos ativos: ${enabledLabels.join(', ')}.`
		: enabledCount === 1
			? 'Acesse abaixo o módulo disponível para este cliente.'
			: 'Os módulos de atendimento podem ser definidos nas configurações da clínica.';

	return (
		<section aria-labelledby="client-modules-title" className="no-print rounded-2xl border border-slate-200 bg-slate-50/80 p-4 shadow-sm sm:p-5">
			<div>
				<p className="text-xs font-bold uppercase tracking-[0.14em] text-teal-700">Atendimentos do cliente</p>
				<h2 id="client-modules-title" className="mt-1 text-xl font-extrabold text-slate-950">{heading}</h2>
				<p className="mt-1 text-sm leading-6 text-slate-600">{introduction}</p>
			</div>

			{enabledCount > 1 ? <p className="mt-3 text-xs font-semibold text-slate-500 md:hidden">Deslize para ver os módulos ativos.</p> : null}
			<div role="list" aria-label="Módulos disponíveis para o cliente" className={`-mx-1 mt-3 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-1 pb-2 md:mx-0 md:grid md:overflow-visible md:px-0 md:pb-0 ${enabledCount === 1 ? 'md:grid-cols-1' : enabledCount === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3'}`}>
				{enabledModules.assessment ? (
				<ModuleCard
					type="assessment"
					title="Avaliação corporal"
					description="Registre uma bioimpedância e acompanhe a evolução das medidas corporais."
					status={latestAssessment ? `Última avaliação: ${formatDate(latestAssessment.evaluated_at)}` : 'Nenhuma avaliação registrada'}
					actionLabel="Nova avaliação"
					onAction={onStartAssessment}
					disabled={!canStartAssessment}
					disabledReason={clientUnavailable ? 'Disponível somente para clientes ativos e identificados.' : 'Seu perfil ou o equipamento atual não permite iniciar esta avaliação.'}
				/>
				) : null}
				{enabledModules.screening ? (
				<ModuleCard
					type="screening"
					title="Triagens"
					description="Aplique questionários e acompanhe convites, resultados e devolutivas descritivas."
					status="Instrumentos independentes da bioimpedância"
					actionLabel="Abrir triagens"
					onAction={onOpenScreenings}
					disabled={!canAccessScreenings || clientUnavailable}
					disabledReason={clientUnavailable ? 'Disponível somente para clientes ativos e identificados.' : 'Somente administradores e profissionais podem acessar triagens.'}
				/>
				) : null}
				{enabledModules.reports ? (
				<ModuleCard
					type="reports"
					title="Relatórios corporais"
					description="Abra os documentos das avaliações corporais sem misturá-los aos resultados de triagens."
					status={`${assessments.length} relatório(s) corporal(is) disponível(is)`}
					actionLabel="Abrir relatório recente"
					onAction={onOpenReports}
					disabled={!latestAssessment}
					disabledReason="O primeiro relatório ficará disponível após uma avaliação corporal."
				/>
				) : null}
			</div>
			{!hasEnabledModules ? <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">Nenhum módulo de atendimento está ativo. Um administrador pode habilitá-los nas configurações do painel web.</p> : null}
		</section>
	);
}
