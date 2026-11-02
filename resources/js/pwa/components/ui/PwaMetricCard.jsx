import React from 'react';
import PwaIcon from './PwaIcon';

const tones = {
	brand: 'border-ricosty-200 from-ricosty-50 to-white text-ricosty-900',
	teal: 'border-teal-200 from-teal-50 to-white text-teal-900',
	blue: 'border-sky-200 from-sky-50 to-white text-sky-900',
	neutral: 'border-slate-200 from-slate-50 to-white text-slate-900',
};

const classificationTones = {
	good: 'bg-emerald-100 text-emerald-800',
	warning: 'bg-amber-100 text-amber-900',
	danger: 'bg-rose-100 text-rose-800',
	attention: 'bg-sky-100 text-sky-800',
	pending: 'bg-slate-100 text-slate-700',
	neutral: 'bg-slate-100 text-slate-700',
};

export default function PwaMetricCard({ icon = 'activity', label, value, classification, classificationTone = 'neutral', type, note, tone = 'brand', compact = false, className = '' }) {
	return (
		<article className={`relative min-w-0 overflow-hidden rounded-2xl border bg-gradient-to-br shadow-sm ${compact ? 'p-3' : 'p-4'} ${tones[tone] ?? tones.brand} ${className}`}>
			<span className="absolute -right-5 -top-6 h-16 w-16 rounded-full bg-current opacity-[0.06]" aria-hidden="true" />
			<div className="relative flex items-center gap-2 text-[11px] font-bold uppercase tracking-wide">
				<PwaIcon name={icon} size={17} className="shrink-0" />
				<span className="min-w-0 break-words">{label}</span>
			</div>
			<p className={`relative mt-2 break-words font-extrabold ${compact ? 'text-lg' : 'text-2xl'}`}>{value}</p>
			{classification ? <p className={`relative mt-2 inline-flex rounded-full px-2 py-1 text-[10px] font-extrabold uppercase tracking-wide ${classificationTones[classificationTone] ?? classificationTones.neutral}`}>{classification}</p> : null}
			{type ? <p className="relative mt-2 text-[10px] font-bold uppercase tracking-wide text-sky-700">{type}</p> : null}
			{note ? <p className="relative mt-1 text-xs leading-5 text-slate-600">{note}</p> : null}
		</article>
	);
}
