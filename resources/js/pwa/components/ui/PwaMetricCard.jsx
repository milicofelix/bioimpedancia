import React from 'react';
import PwaIcon from './PwaIcon';

const tones = {
	brand: 'border-ricosty-200 from-ricosty-50 to-white text-ricosty-900',
	teal: 'border-teal-200 from-teal-50 to-white text-teal-900',
	blue: 'border-sky-200 from-sky-50 to-white text-sky-900',
	neutral: 'border-slate-200 from-slate-50 to-white text-slate-900',
};

export default function PwaMetricCard({ icon = 'activity', label, value, type, note, tone = 'brand', className = '' }) {
	return (
		<article className={`relative min-w-0 overflow-hidden rounded-2xl border bg-gradient-to-br p-4 shadow-sm ${tones[tone] ?? tones.brand} ${className}`}>
			<span className="absolute -right-5 -top-6 h-16 w-16 rounded-full bg-current opacity-[0.06]" aria-hidden="true" />
			<div className="relative flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide">
				<PwaIcon name={icon} size={17} className="shrink-0" />
				<span className="min-w-0 break-words">{label}</span>
			</div>
			<p className="relative mt-2 break-words text-2xl font-extrabold">{value}</p>
			{type ? <p className="relative mt-2 text-[10px] font-bold uppercase tracking-wide text-sky-700">{type}</p> : null}
			{note ? <p className="relative mt-1 text-xs leading-5 text-slate-600">{note}</p> : null}
		</article>
	);
}
