import React from 'react';
import { assistantProcessingSteps } from './assistantSteps';
import PwaIcon from '../../components/ui/PwaIcon';

export default function AssistantProgress({ currentStep }) {
	const activeStep = assistantProcessingSteps[currentStep] ?? assistantProcessingSteps[0];
	const progress = Math.round(((currentStep + 1) / assistantProcessingSteps.length) * 100);

	return (
		<section aria-live="polite" aria-busy="true" className="pwa-card relative overflow-hidden p-5">
			<div className="absolute -right-12 -top-12 h-36 w-36 rounded-full bg-violet-100/70 blur-2xl" aria-hidden="true" />
			<div key={activeStep.title} className="pwa-screen-enter relative flex items-center gap-3">
				<span className="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-ricosty-600 text-white shadow-lg shadow-violet-200"><PwaIcon name={activeStep.icon} size={22} /><span className="absolute -right-1 -top-1 h-3 w-3 animate-ping rounded-full bg-violet-400" aria-hidden="true" /></span>
				<div className="min-w-0">
					<p className="text-xs font-bold uppercase tracking-wide text-violet-700">Assistente processando</p>
					<h2 className="mt-1 font-bold text-slate-950">{activeStep.title}</h2>
				</div>
			</div>
			<p key={activeStep.description} className="pwa-screen-enter relative mt-3 text-sm leading-6 text-slate-600">{activeStep.description}</p>
			<div className="mt-4 h-2 overflow-hidden rounded-full bg-violet-100"><div className="h-full rounded-full bg-gradient-to-r from-violet-500 to-ricosty-500 transition-all duration-500" style={{ width: `${progress}%` }} /></div>
			<p className="mt-2 text-right text-xs font-bold text-violet-700">{progress}%</p>
			<ol className="mt-4 grid gap-2 sm:grid-cols-5">
				{assistantProcessingSteps.map((step, index) => {
					const done = index < currentStep;
					const active = index === currentStep;
					return (
						<li key={step.title} className={`flex items-center gap-2 rounded-xl px-2 py-2 text-xs font-semibold transition sm:flex-col sm:text-center ${done ? 'bg-emerald-50 text-emerald-800' : active ? 'bg-violet-100 text-violet-900 ring-1 ring-violet-300' : 'text-slate-600'}`}>
							<span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full ${done ? 'bg-emerald-100' : active ? 'bg-white shadow-sm' : 'bg-slate-100'}`}>{done ? <PwaIcon name="check" size={13} /> : <PwaIcon name={step.icon} size={13} />}</span>
							<span className="sm:text-[10px]">{step.title}</span>
							<span className="sr-only">{done ? 'Concluída' : active ? 'Em andamento' : 'Aguardando'}</span>
						</li>
					);
				})}
			</ol>
		</section>
	);
}
