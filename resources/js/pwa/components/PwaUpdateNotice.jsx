import React from 'react';
import PwaButton from './ui/PwaButton';
import PwaIcon from './ui/PwaIcon';

export default function PwaUpdateNotice({ visible, applying, onApply, onDismiss }) {
	if (!visible) return null;

	return (
		<aside role="status" aria-live="polite" className="pwa-update-notice pwa-status-enter fixed z-[60] rounded-2xl border border-ricosty-200 bg-white p-3 shadow-[0_18px_45px_rgba(56,16,29,0.2)]">
			<div className="flex items-start gap-3">
				<span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-ricosty-100 text-ricosty-800"><PwaIcon name="refresh" size={19} /></span>
				<div className="min-w-0 flex-1">
					<p className="text-sm font-extrabold text-ricosty-950">Atualização disponível</p>
					<p className="mt-0.5 text-xs leading-5 text-slate-700">Atualize quando concluir o que está preenchendo.</p>
				</div>
				<button type="button" onClick={onDismiss} aria-label="Lembrar da atualização depois" className="pwa-focus flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-xl text-slate-600 hover:bg-slate-100">×</button>
			</div>
			<PwaButton type="button" onClick={onApply} disabled={applying} icon="refresh" className="mt-2 w-full text-xs">{applying ? 'Atualizando...' : 'Atualizar agora'}</PwaButton>
		</aside>
	);
}
