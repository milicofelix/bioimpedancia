import React, { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import PwaButton from './PwaButton';
import PwaIcon from './PwaIcon';

const tones = {
	warning: 'bg-amber-100 text-amber-800',
	danger: 'bg-rose-100 text-rose-700',
};

export default function PwaConfirmSheet({ open, title, description, detail, icon = 'shield', tone = 'warning', confirmLabel = 'Confirmar', confirmIcon, confirmVariant = 'primary', onCancel, onConfirm }) {
	const cancelReference = useRef(null);
	const dialogReference = useRef(null);
	const previousFocusReference = useRef(null);
	const onCancelReference = useRef(onCancel);
	onCancelReference.current = onCancel;

	useEffect(() => {
		if (!open) return undefined;
		previousFocusReference.current = document.activeElement;
		cancelReference.current?.focus();
		const closeOnEscape = (event) => {
			if (event.key === 'Escape') onCancelReference.current?.();
			if (event.key !== 'Tab') return;
			const focusable = [...(dialogReference.current?.querySelectorAll('button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])') ?? [])];
			if (!focusable.length) return;
			const first = focusable[0];
			const last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		};
		window.addEventListener('keydown', closeOnEscape);
		return () => {
			window.removeEventListener('keydown', closeOnEscape);
			previousFocusReference.current?.focus?.();
		};
	}, [open]);

	if (!open || typeof document === 'undefined') return null;
	const titleId = `confirm-sheet-title-${tone}`;
	const descriptionId = `confirm-sheet-description-${tone}`;

	return createPortal(
		<div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/45 p-4 backdrop-blur-sm sm:items-center" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onCancel(); }}>
			<div ref={dialogReference} role="dialog" aria-modal="true" aria-labelledby={titleId} aria-describedby={descriptionId} className="pwa-screen-enter w-full max-w-sm rounded-[1.5rem] bg-white p-5 shadow-2xl">
				<div className="mx-auto h-1 w-12 rounded-full bg-slate-200 sm:hidden" aria-hidden="true" />
				<div className={`mt-3 flex h-12 w-12 items-center justify-center rounded-2xl ${tones[tone] ?? tones.warning}`}><PwaIcon name={icon} /></div>
				<h2 id={titleId} className="mt-4 text-lg font-bold text-slate-950">{title}</h2>
				<p id={descriptionId} className="mt-2 text-sm leading-6 text-slate-600">{description}</p>
				{detail ? <div className="mt-4 rounded-xl bg-ricosty-50 p-3 text-sm text-ricosty-950">{detail}</div> : null}
				<div className="mt-5 grid gap-3 sm:grid-cols-2">
					<PwaButton ref={cancelReference} type="button" variant="secondary" onClick={onCancel}>Cancelar</PwaButton>
					<PwaButton type="button" variant={confirmVariant} icon={confirmIcon} onClick={onConfirm}>{confirmLabel}</PwaButton>
				</div>
			</div>
		</div>,
		document.body,
	);
}
