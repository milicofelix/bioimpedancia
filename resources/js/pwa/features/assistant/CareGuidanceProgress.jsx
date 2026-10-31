import React from 'react';
import PwaIcon from '../../components/ui/PwaIcon';

export default function CareGuidanceProgress() {
	return (
		<section aria-live="polite" aria-busy="true" className="pwa-card relative overflow-hidden border-teal-200 p-5">
			<div className="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-teal-100/70 blur-2xl" aria-hidden="true" />
			<div className="pwa-screen-enter relative flex items-center gap-3">
				<span className="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-500 to-emerald-600 text-white shadow-lg shadow-teal-200"><PwaIcon name="sparkles" size={22} /><span className="absolute -right-1 -top-1 h-3 w-3 animate-ping rounded-full bg-teal-400" aria-hidden="true" /></span>
				<div className="min-w-0"><p className="text-xs font-bold uppercase tracking-wide text-teal-700">Agente preparando orientações</p><h2 className="mt-1 font-bold text-slate-950">Gerando próximos cuidados</h2></div>
			</div>
			<p className="pwa-screen-enter relative mt-3 text-sm leading-6 text-slate-600">O servidor está consultando as referências, preparando as orientações e validando a segurança da resposta.</p>
			<div className="mt-4 h-2 overflow-hidden rounded-full bg-teal-100" aria-hidden="true"><div className="h-full w-2/5 animate-pulse rounded-full bg-gradient-to-r from-teal-500 to-emerald-500" /></div>
			<p className="mt-2 text-xs font-semibold leading-5 text-teal-700">A conclusão será exibida somente quando o servidor responder. O tempo pode variar conforme o serviço de IA.</p>
		</section>
	);
}
