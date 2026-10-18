import React from 'react';
import PwaIcon from './PwaIcon';

export default function PwaStepIndicator({ steps, current }) {
	return (
		<ol className="grid grid-cols-4 gap-1 rounded-2xl border border-ricosty-100 bg-white p-2 shadow-sm" aria-label="Etapas da jornada">
			{steps.map((label, index) => {
				const number = index + 1;
				const done = number < current;
				const active = number === current;
				return (
					<li key={label} aria-current={active ? 'step' : undefined} className={`relative flex min-w-0 flex-col items-center rounded-xl px-1 py-2 text-center ${active ? 'bg-ricosty-50' : ''}`}>
						<span className={`relative z-10 flex h-7 w-7 items-center justify-center rounded-full text-xs font-extrabold ${done ? 'bg-ricosty-600 text-white' : active ? 'ring-4 ring-ricosty-100 bg-ricosty-700 text-white' : 'bg-slate-100 text-slate-400'}`}>{done ? <PwaIcon name="check" size={14} /> : number}</span>
						<span className={`mt-2 truncate text-[10px] font-bold sm:text-xs ${active || done ? 'text-ricosty-800' : 'text-slate-400'}`}>{label}</span>
					</li>
				);
			})}
		</ol>
	);
}
