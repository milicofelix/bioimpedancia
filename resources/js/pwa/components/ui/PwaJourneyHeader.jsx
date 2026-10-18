import React from 'react';
import PwaIcon from './PwaIcon';

export default function PwaJourneyHeader({ icon, eyebrow, title, description, children, tone = 'brand' }) {
	const tones = {
		brand: 'from-ricosty-950 via-ricosty-900 to-ricosty-700',
		assistant: 'from-slate-950 via-violet-950 to-ricosty-900',
		report: 'from-ricosty-950 via-ricosty-900 to-amber-800',
	};

	return (
		<section className={`relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br ${tones[tone] ?? tones.brand} p-5 text-white shadow-[0_16px_40px_rgba(75,24,44,0.2)] sm:p-6`}>
			<div className="absolute -right-10 -top-12 h-36 w-36 rounded-full bg-white/10 blur-2xl" aria-hidden="true" />
			<div className="relative flex items-start gap-4">
				{icon ? <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-ricosty-100"><PwaIcon name={icon} size={24} /></span> : null}
				<div className="min-w-0 flex-1">
					<p className="text-[11px] font-extrabold uppercase tracking-[0.18em] text-ricosty-100">{eyebrow}</p>
					<h1 className="mt-1 break-words text-xl font-extrabold tracking-tight sm:text-2xl">{title}</h1>
					{description ? <p className="mt-2 text-sm leading-6 text-white/75">{description}</p> : null}
					{children ? <div className="mt-4 flex flex-wrap gap-2">{children}</div> : null}
				</div>
			</div>
		</section>
	);
}
