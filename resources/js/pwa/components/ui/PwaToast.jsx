import React, { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import PwaIcon from './PwaIcon';

const tones = {
	success: { icon: 'check', classes: 'border-emerald-200 bg-emerald-950 text-white' },
	error: { icon: 'alertTriangle', classes: 'border-rose-200 bg-rose-950 text-white' },
	info: { icon: 'sparkles', classes: 'border-ricosty-200 bg-ricosty-950 text-white' },
};

export default function PwaToast({ message, tone = 'info', duration = 4000, onDismiss }) {
	const dismissReference = useRef(onDismiss);
	dismissReference.current = onDismiss;

	useEffect(() => {
		if (!message || !duration) return undefined;
		const timeout = window.setTimeout(() => dismissReference.current?.(), duration);
		return () => window.clearTimeout(timeout);
	}, [message, duration]);

	if (!message || typeof document === 'undefined') return null;
	const style = tones[tone] ?? tones.info;

	return createPortal(
		<div className="pointer-events-none fixed inset-x-0 top-[max(1rem,env(safe-area-inset-top))] z-[70] flex justify-center px-4">
			<div role={tone === 'error' ? 'alert' : 'status'} aria-live={tone === 'error' ? 'assertive' : 'polite'} className={`pwa-status-enter pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border px-4 py-3 shadow-2xl ${style.classes}`}>
				<span className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white/15"><PwaIcon name={style.icon} size={16} /></span>
				<p className="min-w-0 flex-1 text-sm font-semibold leading-6">{message}</p>
				<button type="button" onClick={onDismiss} aria-label="Fechar mensagem" className="pwa-focus flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xl text-white/80 hover:bg-white/10">×</button>
			</div>
		</div>,
		document.body,
	);
}
