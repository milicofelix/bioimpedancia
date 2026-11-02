import React from 'react';
import PwaIcon from './PwaIcon';

const tones = {
	attention: { icon: 'alertTriangle', role: 'status', classes: 'border-amber-200 bg-amber-50 text-amber-950', iconClasses: 'bg-amber-100 text-amber-800' },
	error: { icon: 'alertTriangle', role: 'alert', classes: 'border-rose-200 bg-rose-50 text-rose-900', iconClasses: 'bg-rose-100 text-rose-800' },
	success: { icon: 'check', role: 'status', classes: 'border-emerald-200 bg-emerald-50 text-emerald-900', iconClasses: 'bg-emerald-100 text-emerald-800' },
	info: { icon: 'info', role: 'status', classes: 'border-sky-200 bg-sky-50 text-sky-950', iconClasses: 'bg-sky-100 text-sky-800' },
};

export default function PwaAlert({ tone = 'attention', title, children, className = '' }) {
	const style = tones[tone] ?? tones.attention;
	return (
		<div role={style.role} aria-live={tone === 'error' ? 'assertive' : 'polite'} className={`rounded-2xl border p-4 text-sm ${style.classes} ${className}`}>
			<div className="flex items-start gap-3">
				<span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-xl ${style.iconClasses}`} aria-hidden="true"><PwaIcon name={style.icon} size={17} /></span>
				<div className="min-w-0 flex-1">{title ? <strong className="block font-extrabold">{title}</strong> : null}<div className={`${title ? 'mt-1' : ''} leading-5`}>{children}</div></div>
			</div>
		</div>
	);
}
