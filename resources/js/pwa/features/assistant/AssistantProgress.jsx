import React from 'react';
import { assistantProcessingSteps } from './assistantSteps';
import PwaIcon from '../../components/ui/PwaIcon';

export default function AssistantProgress({ currentStep }) {
	const activeStep = assistantProcessingSteps[currentStep] ?? assistantProcessingSteps[0];
	const progress = Math.round(((currentStep + 1) / assistantProcessingSteps.length) * 100);

	return (
		<section aria-live="polite" aria-busy="true" className="pwa-card overflow-hidden p-5">
			<div className="flex items-center gap-3">
				<span className="h-7 w-7 animate-spin rounded-full border-4 border-violet-200 border-t-violet-600" aria-hidden="true" />
				<div>
					<p className="text-xs font-bold uppercase tracking-wide text-violet-700">Assistente processando</p>
					<h2 className="mt-1 font-bold text-slate-950">{activeStep.title}</h2>
				</div>
			</div>
			<p className="mt-3 text-sm leading-6 text-slate-600">{activeStep.description}</p>
			<div className="mt-4 h-2 overflow-hidden rounded-full bg-violet-100"><div className="h-full rounded-full bg-gradient-to-r from-violet-500 to-ricosty-500 transition-all duration-500" style={{ width: `${progress}%` }} /></div>
			<p className="mt-2 text-right text-xs font-bold text-violet-700">{progress}%</p>
			<ol className="mt-4 space-y-2">
				{assistantProcessingSteps.map((step, index) => {
					const done = index < currentStep;
					const active = index === currentStep;
					return (
						<li key={step.title} className={`flex items-center gap-2 text-xs font-semibold ${done ? 'text-emerald-700' : active ? 'text-violet-700' : 'text-slate-400'}`}>
							<span className={`flex h-5 w-5 items-center justify-center rounded-full ${done ? 'bg-emerald-100' : active ? 'bg-violet-100' : 'bg-slate-100'}`}>{done ? <PwaIcon name="check" size={12} /> : index + 1}</span>
							{step.title}
						</li>
					);
				})}
			</ol>
		</section>
	);
}
