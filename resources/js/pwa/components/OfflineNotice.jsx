import React from 'react';
import PwaIcon from './ui/PwaIcon';

export default function OfflineNotice({ detail = 'As ações que dependem da internet ficam bloqueadas até a reconexão.', className = '' }) {
	return (
		<div role="status" aria-live="polite" className={`pwa-offline-notice flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 ${className}`}>
			<PwaIcon name="wifiOff" className="mt-0.5 shrink-0" />
			<div>
				<p className="font-extrabold">Você está offline</p>
				<p className="mt-0.5 leading-5">{detail}</p>
			</div>
		</div>
	);
}
