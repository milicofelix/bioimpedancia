import React from 'react';
import PwaIcon from './PwaIcon';

export default function PwaFormSection({ icon = 'clipboard', title, description, children, tone = 'neutral', className = '' }) {
	const tones = {
		neutral: 'border-slate-200 bg-slate-50/70 text-slate-700',
		brand: 'border-ricosty-200 bg-ricosty-50/60 text-ricosty-800',
		teal: 'border-teal-200 bg-teal-50/60 text-teal-800',
	};

	return (
		<fieldset className={`rounded-2xl border p-4 ${tones[tone] ?? tones.neutral} ${className}`}>
			<legend className="px-1">
				<span className="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm font-extrabold text-slate-900 shadow-sm"><PwaIcon name={icon} size={17} className="text-current" />{title}</span>
			</legend>
			{description ? <p className="mb-3 text-xs leading-5 text-slate-600">{description}</p> : null}
			<div className="space-y-3 text-slate-700">{children}</div>
		</fieldset>
	);
}
