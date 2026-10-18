import React from 'react';

export default function ShareLinkSkeleton() {
	return (
		<div role="status" aria-live="polite" className="mt-4 rounded-2xl border border-ricosty-100 bg-ricosty-50/50 p-4">
			<span className="sr-only">Criando link temporário...</span>
			<div className="flex items-center justify-between"><span className="pwa-skeleton h-6 w-16 rounded-full" /><span className="pwa-skeleton h-4 w-28 rounded" /></div>
			<span className="pwa-skeleton mt-3 block h-9 w-full rounded-lg" />
			<div className="mt-3 grid grid-cols-2 gap-2"><span className="pwa-skeleton h-10 rounded-xl" /><span className="pwa-skeleton h-10 rounded-xl" /></div>
		</div>
	);
}
