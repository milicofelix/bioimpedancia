import React from 'react';
import PwaIcon from './PwaIcon';

const tones = {
	brand: 'bg-ricosty-100 text-ricosty-800',
	teal: 'bg-teal-100 text-teal-800',
	blue: 'bg-sky-100 text-sky-800',
	neutral: 'bg-slate-100 text-slate-700',
};

export default function PwaSectionHeader({ icon = 'clipboard', eyebrow, title, description, action, headingLevel: Heading = 'h2', headingId, tone = 'brand', divided = false, className = '' }) {
	return (
		<header className={`flex min-w-0 items-start justify-between gap-3 ${divided ? 'border-b border-ricosty-100 pb-3 sm:border-b-0 sm:pb-0' : ''} ${className}`}>
			<div className="flex min-w-0 items-start gap-3">
				<span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${tones[tone] ?? tones.brand}`} aria-hidden="true"><PwaIcon name={icon} size={20} /></span>
				<div className="min-w-0">
					{eyebrow ? <p className="text-[11px] font-extrabold uppercase tracking-[0.16em] text-ricosty-600">{eyebrow}</p> : null}
					<Heading id={headingId} className="break-words text-lg font-extrabold leading-tight text-ricosty-950">{title}</Heading>
					{description ? <p className="mt-1 text-sm leading-5 text-slate-600">{description}</p> : null}
				</div>
			</div>
			{divided ? <span className="mt-5 hidden h-px min-w-8 flex-1 bg-gradient-to-r from-ricosty-200 to-transparent sm:block" aria-hidden="true" /> : null}
			{action ? <div className="shrink-0">{action}</div> : null}
		</header>
	);
}
