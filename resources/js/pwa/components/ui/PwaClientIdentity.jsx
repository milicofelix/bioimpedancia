import React from 'react';

function initials(name) {
	return String(name ?? '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

const statuses = {
	active: { label: 'Ativo', badge: 'bg-emerald-100 text-emerald-900', avatar: 'from-ricosty-100 to-ricosty-200 text-ricosty-800 ring-ricosty-200' },
	inactive: { label: 'Inativo', badge: 'bg-slate-200 text-slate-800', avatar: 'from-slate-100 to-slate-200 text-slate-700 ring-slate-300' },
	anonymized: { label: 'Anonimizado', badge: 'bg-amber-100 text-amber-900', avatar: 'from-amber-100 to-amber-200 text-amber-900 ring-amber-200' },
};

export default function PwaClientIdentity({ name, status = 'active', eyebrow = 'Cliente', subtitle, headingLevel: Heading = 'h2', headingId, compact = false, action, className = '' }) {
	const appearance = statuses[status] ?? statuses.active;
	return (
		<div className={`flex min-w-0 items-start justify-between gap-3 ${className}`}>
			<div className="flex min-w-0 items-center gap-3">
				<span className={`flex shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br font-extrabold ring-1 ${appearance.avatar} ${compact ? 'h-11 w-11 text-sm' : 'h-14 w-14 text-lg'}`} aria-hidden="true">{initials(name)}</span>
				<div className="min-w-0"><p className="text-[11px] font-bold uppercase tracking-[0.14em] text-ricosty-600">{eyebrow}</p><Heading id={headingId} className={`${compact ? 'text-base' : 'text-xl'} mt-1 truncate font-extrabold text-ricosty-950`} title={name}>{name}</Heading>{subtitle ? <p className="mt-0.5 truncate text-sm text-slate-600">{subtitle}</p> : null}</div>
			</div>
			<div className="flex shrink-0 flex-col items-end gap-1"><span className={`rounded-full px-2 py-1 text-[11px] font-bold uppercase ${appearance.badge}`}>{appearance.label}</span>{action}</div>
		</div>
	);
}
