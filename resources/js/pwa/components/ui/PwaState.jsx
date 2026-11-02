import React from 'react';
import PwaIcon from './PwaIcon';

export default function PwaState({ type = 'empty', icon, title, description, action, className = '' }) {
	const loading = type === 'loading';
	const resolvedIcon = icon ?? (loading ? 'refresh' : type === 'error' ? 'alertTriangle' : 'clipboard');
	return (
		<div role={type === 'error' ? 'alert' : 'status'} aria-live={type === 'error' ? 'assertive' : 'polite'} aria-busy={loading || undefined} className={`rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center ${className}`}>
			<span className="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-white text-ricosty-700 shadow-sm" aria-hidden="true"><PwaIcon name={resolvedIcon} size={21} className={loading ? 'animate-spin' : ''} /></span>
			<p className="mt-3 font-extrabold text-slate-800">{title ?? (loading ? 'Carregando informações' : 'Nenhum registro disponível')}</p>
			{description ? <p className="mx-auto mt-1 max-w-sm text-sm leading-5 text-slate-600">{description}</p> : null}
			{action ? <div className="mt-4 flex justify-center">{action}</div> : null}
		</div>
	);
}
