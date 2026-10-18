import React from 'react';

const navigation = [
	{ href: '#clientes', label: 'Clientes', symbol: 'C' },
	{ href: '#avaliacao', label: 'Avaliar', symbol: '+' },
	{ href: '#assistente', label: 'Assistente', symbol: 'A' },
	{ href: '#relatorio', label: 'Relatório', symbol: 'R' },
];

export default function OperationalShell({ clinic, currentUser, onLogout, children }) {
	return (
		<div className="min-h-dvh bg-slate-100 pb-24">
			<header className="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur">
				<div className="mx-auto flex max-w-lg items-center justify-between gap-3">
					<div className="min-w-0">
						<p className="truncate text-sm font-bold text-slate-950">{clinic?.display_name ?? 'Ricosty'}</p>
						<p className="truncate text-xs text-slate-500">Atendimento operacional</p>
					</div>
					<div className="flex items-center gap-2">
						<a href="/dashboard" className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600">Painel</a>
						<button type="button" onClick={onLogout} className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600">Sair</button>
					</div>
				</div>
			</header>

			<main className="mx-auto max-w-lg px-4 py-5">
				<div className="mb-5 rounded-2xl bg-slate-950 p-5 text-white shadow-sm">
					<p className="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-300">Área móvel</p>
					<h1 className="mt-2 text-xl font-bold">Olá, {currentUser?.name ?? 'profissional'}</h1>
					<p className="mt-1 text-sm text-slate-300">Acesso rápido às etapas do atendimento.</p>
				</div>
				{children}
			</main>

			<nav aria-label="Navegação operacional" className="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur">
				<div className="mx-auto grid max-w-lg grid-cols-4 gap-1">
					{navigation.map((item) => (
						<a key={item.href} href={item.href} className="flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-400">
							<span aria-hidden="true" className="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-100 text-xs font-black text-emerald-700">{item.symbol}</span>
							{item.label}
						</a>
					))}
				</div>
			</nav>
		</div>
	);
}
