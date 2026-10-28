import React, { useEffect, useRef } from 'react';
import InstallGuide from './InstallGuide';
import PwaIcon from './ui/PwaIcon';
import useOnlineStatus from '../hooks/useOnlineStatus';
import useServiceWorkerUpdate from '../hooks/useServiceWorkerUpdate';
import OfflineNotice from './OfflineNotice';
import PwaUpdateNotice from './PwaUpdateNotice';
import PwaButton from './ui/PwaButton';
import BioFlowBrand from '../../components/brand/BioFlowBrand';

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
	const update = useServiceWorkerUpdate();
	const mainReference = useRef(null);

	useEffect(() => {
		mainReference.current?.focus({ preventScroll: true });
	}, [activeSection]);

	return (
		<div className="pwa-shell pwa-native-shell pb-28 text-ricosty-ink">
			<a href="#operational-content" className="pwa-focus fixed left-3 top-3 z-50 -translate-y-24 rounded-xl bg-ricosty-950 px-4 py-3 text-sm font-bold text-white transition focus:translate-y-0">Pular para o conteúdo</a>
			<header className="pwa-safe-top pwa-safe-inline sticky top-0 z-20 border-b border-ricosty-200/70 bg-white/90 pb-3 backdrop-blur-xl">
				<div className="mx-auto flex max-w-xl items-center justify-between gap-3">
					<div className="flex min-w-0 items-center gap-2.5">
						<BioFlowBrand headingLevel="h1" size="compact" showTagline={false} className="shrink-0" />
						<div className="hidden min-w-0 border-l border-slate-200 pl-2.5 sm:block">
							<p className="break-words text-xs font-bold leading-tight text-ricosty-950">{clinic?.display_name ?? 'Ricosty'}</p>
							<p className="break-words text-[10px] font-medium leading-tight text-slate-600">Atendimento operacional</p>
						</div>
					</div>
					<div className="flex items-center gap-1.5">
						<span role="status" aria-live="polite" title={isOnline ? 'Conectado' : 'Sem conexão'} className={`pwa-status-enter flex min-h-11 items-center gap-1.5 rounded-full px-2.5 text-[11px] font-bold ${isOnline ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-rose-50 text-rose-800 ring-1 ring-rose-200'}`}>
							<PwaIcon name={isOnline ? 'wifi' : 'wifiOff'} size={15} />
							<span className="hidden sm:inline">{isOnline ? 'Online' : 'Offline'}</span>
						</span>
						<a href="/dashboard" aria-label="Abrir painel completo" title="Painel completo" className="pwa-focus flex h-11 w-11 items-center justify-center rounded-full border border-ricosty-200 bg-white text-ricosty-700 transition hover:bg-ricosty-50"><PwaIcon name="grid" size={18} /></a>
						<PwaButton type="button" onClick={onLogout} aria-label="Sair da conta" title="Sair" variant="ghost" icon="logOut" className="h-11 w-11 rounded-full border border-ricosty-200 bg-white px-0 hover:bg-ricosty-50"><span className="sr-only">Sair da conta</span></PwaButton>
					</div>
				</div>
			</header>

			<main id="operational-content" ref={mainReference} tabIndex="-1" className="pwa-safe-inline mx-auto max-w-xl py-5 outline-none">
				{!isOnline ? <OfflineNotice detail="Consultas e ações que enviam dados ficam bloqueadas até a reconexão. Nenhum dado clínico é armazenado para uso offline." className="mb-4" /> : null}

				<div className="relative mb-5 overflow-hidden rounded-[1.6rem] bg-gradient-to-br from-ricosty-800 via-ricosty-700 to-ricosty-500 p-5 text-white shadow-[0_18px_45px_rgba(114,42,62,0.22)]">
					<div aria-hidden="true" className="absolute -right-8 -top-12 h-36 w-36 rounded-full border-[28px] border-white/10" />
					<div aria-hidden="true" className="absolute -bottom-16 right-20 h-28 w-28 rounded-full bg-ricosty-gold/25 blur-2xl" />
					<div className="relative flex items-center gap-4">
						<div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-white/25 bg-white/15 text-lg font-black shadow-inner backdrop-blur">{userInitial(currentUser?.name)}</div>
						<div className="min-w-0">
							<p className="text-[11px] font-bold uppercase tracking-[0.2em] text-ricosty-100">Sua melhor versão, em cada detalhe</p>
							<h1 className="mt-1 break-words text-xl font-bold">Olá, {currentUser?.name ?? 'profissional'}</h1>
							<p className="mt-1 text-sm text-ricosty-100">Tudo que você precisa para conduzir o atendimento.</p>
						</div>
					</div>
				</div>

				<InstallGuide />
				<div key={activeSection} className="pwa-screen-enter">{children}</div>
			</main>

			<PwaUpdateNotice visible={update.available} applying={update.applying} onApply={update.apply} onDismiss={update.dismiss} />

			<nav aria-label="Navegação operacional" className="pwa-bottom-navigation fixed inset-x-0 bottom-0 z-30 pt-2">
				<div className="mx-auto grid max-w-xl grid-cols-4 gap-1 rounded-[1.35rem] border border-ricosty-200/80 bg-white/95 p-1.5 shadow-[0_14px_40px_rgba(97,38,55,0.16)] backdrop-blur-xl">
					{navigation.map((item) => {
						const active = item.section === activeSection;
						return <button type="button" key={item.section} onClick={() => onNavigate(item.section)} aria-current={active ? 'page' : undefined} aria-label={`${item.label}${active ? ', seção atual' : ''}`} className={`pwa-focus pwa-pressable relative flex min-h-14 touch-manipulation flex-col items-center justify-center gap-1 rounded-2xl px-1 text-[11px] font-bold transition ${active ? 'bg-ricosty-50 text-ricosty-800' : 'text-slate-600 hover:bg-ricosty-50/60 hover:text-ricosty-800'}`}><span className={`flex h-7 w-7 items-center justify-center rounded-xl transition ${active ? 'bg-gradient-to-br from-ricosty-500 to-ricosty-700 text-white shadow-md shadow-ricosty-200' : 'text-slate-600'}`}><PwaIcon name={item.icon} size={18} /></span><span>{item.label}</span>{active ? <span aria-hidden="true" className="absolute -bottom-0.5 h-1 w-5 rounded-full bg-ricosty-500" /> : null}</button>;
					})}
				</div>
			</nav>
		</div>
	);
}
