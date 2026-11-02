import React from 'react';
import PwaIcon from './PwaIcon';

export default function PwaInfoCard({ icon = 'info', title, children, className = '' }) {
	return (
		<section className={`rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 ${className}`}>
			<div className="flex items-start gap-3">
				<span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm" aria-hidden="true"><PwaIcon name={icon} size={18} /></span>
				<div className="min-w-0 flex-1">{title ? <h3 className="font-extrabold text-slate-900">{title}</h3> : null}<div className={`${title ? 'mt-1' : ''} leading-6`}>{children}</div></div>
			</div>
		</section>
	);
}
