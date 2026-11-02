import React from 'react';
import PwaIcon from '../../components/ui/PwaIcon';

export default function AssistantProgress() {
	return (
		<section aria-live="polite" aria-busy="true" className="pwa-card relative overflow-hidden p-5">
			<div className="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-violet-100/70 blur-2xl" aria-hidden="true" />
			<div className="pwa-screen-enter relative flex items-center gap-3">
				<span className="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-ricosty-600 text-white shadow-lg shadow-violet-200"><PwaIcon name="sparkles" size={22} /><span className="absolute -right-1 -top-1 h-3 w-3 animate-ping rounded-full bg-violet-400" aria-hidden="true" /></span>
				<div className="min-w-0">
					<p className="text-xs font-bold uppercase tracking-wide text-violet-700">Assistente processando</p>
					<h2 className="mt-1 font-bold text-slate-950">Gerando observação para revisão</h2>
				</div>
			</div>
			<p className="pwa-screen-enter relative mt-3 text-sm leading-6 text-slate-600">Estamos preparando uma sugestão segura com os dados e as fontes disponíveis.</p>
			<div className="relative mt-4 grid gap-2 sm:grid-cols-3">
				<ProgressStep icon="check" label="Dados reunidos" state="done" />
				<ProgressStep icon="refresh" label="Texto em elaboração" state="active" />
				<ProgressStep icon="shield" label="Revisão de segurança" />
			</div>
			<div className="mt-4 h-2 overflow-hidden rounded-full bg-violet-100" aria-hidden="true"><div className="h-full w-2/5 animate-pulse rounded-full bg-gradient-to-r from-violet-500 to-ricosty-500" /></div>
			<p className="mt-2 text-xs font-semibold leading-5 text-violet-700">A tela avançará assim que a análise estiver concluída.</p>
		</section>
	);
}

function ProgressStep({ icon, label, state = 'pending' }) {
	const classes = state === 'done' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : state === 'active' ? 'border-violet-200 bg-violet-50 text-violet-800' : 'border-slate-200 bg-slate-50 text-slate-500';
	return <span className={`flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold ${classes}`}><PwaIcon name={icon} size={14} className={state === 'active' ? 'animate-spin' : ''} />{label}</span>;
}
