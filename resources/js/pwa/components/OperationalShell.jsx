import React from 'react';
import InstallGuide from './InstallGuide';
import PwaIcon from './ui/PwaIcon';
import useOnlineStatus from '../hooks/useOnlineStatus';

const navigation = [
	{ section: 'clientes', label: 'Clientes', icon: 'users' },
	{ section: 'avaliacao', label: 'Avaliar', icon: 'clipboard' },
	{ section: 'assistente', label: 'Assistente', icon: 'sparkles' },
	{ section: 'relatorio', label: 'Relatório', icon: 'fileText' },
];

function userInitial(name) {
	return String(name ?? 'P').trim().charAt(0).toUpperCase();
}

export default function OperationalShell({ activeSection, clinic, currentUser, onLogout, onNavigate, children }) {
	const isOnline = useOnlineStatus();

	return (
		<div className="pwa-shell min-h-dvh pb-28 text-ricosty-ink">
			<header className="pwa-safe-top sticky top-0 z-20 border-b border-ricosty-200/70 bg-white/90 px-4 pb-3 backdrop-blur-xl">
				<div className="mx-auto flex max-w-xl items-center justify-between gap-3">
					<div className="flex min-w-0 items-center gap-3">
						<div className="flex h-11 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-ricosty-50 px-2 ring-1 ring-ricosty-100">
							<img src={clinic?.logo_url ?? '/images/brand/ricosty-logo.png'} alt={clinic?.display_name ?? 'Ricosty'} className="max-h-9 w-full object-contain" />
						</div>
						<div className="min-w-0">
							<p className="truncate text-sm font-bold text-ricosty-950">{clinic?.display_name ?? 'Ricosty'}</p>
							<p className="truncate text-[11px] font-medium text-slate-500">Atendimento operacional</p>
						</div>
					</div>
					<div className="flex items-center gap-1.5">
						<span title={isOnline ? 'Conectado' : 'Sem conexão'} className={`flex h-9 items-center gap-1.5 rounded-full px-2.5 text-[10px] font-bold ${isOnline ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100' : 'bg-rose-50 text-rose-700 ring-1 ring-rose-100'}`}>
							<PwaIcon name={isOnline ? 'wifi' : 'wifiOff'} size={15} />
							<span className="hidden sm:inline">{isOnline ? 'Online' : 'Offline'}</span>
						</span>
						<a href="/dashboard" aria-label="Abrir painel completo" title="Painel completo" className="pwa-focus flex h-10 w-10 items-center justify-center rounded-full border border-ricosty-200 bg-white text-ricosty-700 transition hover:bg-ricosty-50"><PwaIcon name="grid" size={18} /></a>
						<button type="button" onClick={onLogout} aria-label="Sair da conta" title="Sair" className="pwa-focus flex h-10 w-10 items-center justify-center rounded-full border border-ricosty-200 bg-white text-ricosty-700 transition hover:bg-ricosty-50"><PwaIcon name="logOut" size={18} /></button>
					</div>
				</div>
			</header>

			<main className="mx-auto max-w-xl px-4 py-5">
				{!isOnline ? <div role="status" className="mb-4 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800"><PwaIcon name="wifiOff" className="mt-0.5 shrink-0" />Consultas, avaliações e relatórios ficam indisponíveis até a reconexão.</div> : null}

				<div className="relative mb-5 overflow-hidden rounded-[1.6rem] bg-gradient-to-br from-ricosty-800 via-ricosty-700 to-ricosty-500 p-5 text-white shadow-[0_18px_45px_rgba(114,42,62,0.22)]">
					<div aria-hidden="true" className="absolute -right-8 -top-12 h-36 w-36 rounded-full border-[28px] border-white/10" />
					<div aria-hidden="true" className="absolute -bottom-16 right-20 h-28 w-28 rounded-full bg-ricosty-gold/25 blur-2xl" />
					<div className="relative flex items-center gap-4">
						<div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-white/25 bg-white/15 text-lg font-black shadow-inner backdrop-blur">{userInitial(currentUser?.name)}</div>
						<div className="min-w-0">
							<p className="text-[11px] font-bold uppercase tracking-[0.2em] text-ricosty-100">Sua melhor versão, em cada detalhe</p>
							<h1 className="mt-1 truncate text-xl font-bold">Olá, {currentUser?.name ?? 'profissional'}</h1>
							<p className="mt-1 text-sm text-ricosty-100">Tudo que você precisa para conduzir o atendimento.</p>
						</div>
					</div>
				</div>

				<InstallGuide />
				{children}
			</main>

			<nav aria-label="Navegação operacional" className="fixed inset-x-0 bottom-0 z-30 px-3 pb-[max(0.6rem,env(safe-area-inset-bottom))] pt-2">
				<div className="mx-auto grid max-w-xl grid-cols-4 gap-1 rounded-[1.35rem] border border-ricosty-200/80 bg-white/95 p-1.5 shadow-[0_14px_40px_rgba(97,38,55,0.16)] backdrop-blur-xl">
					{navigation.map((item) => {
						const active = item.section === activeSection;
						return <button type="button" key={item.section} onClick={() => onNavigate(item.section)} aria-current={active ? 'page' : undefined} className={`pwa-focus relative flex min-h-14 flex-col items-center justify-center gap-1 rounded-2xl px-1 text-[10px] font-bold transition ${active ? 'bg-ricosty-50 text-ricosty-700' : 'text-slate-500 hover:bg-ricosty-50/60 hover:text-ricosty-700'}`}><span className={`flex h-7 w-7 items-center justify-center rounded-xl transition ${active ? 'bg-gradient-to-br from-ricosty-500 to-ricosty-700 text-white shadow-md shadow-ricosty-200' : 'text-slate-500'}`}><PwaIcon name={item.icon} size={18} /></span><span>{item.label}</span>{active ? <span aria-hidden="true" className="absolute -bottom-0.5 h-1 w-5 rounded-full bg-ricosty-500" /> : null}</button>;
					})}
				</div>
			</nav>
		</div>
	);
}
