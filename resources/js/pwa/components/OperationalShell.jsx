import React, { useEffect, useRef } from 'react';
import InstallGuide from './InstallGuide';
import PwaIcon from './ui/PwaIcon';
import useOnlineStatus from '../hooks/useOnlineStatus';
import useServiceWorkerUpdate from '../hooks/useServiceWorkerUpdate';
import OfflineNotice from './OfflineNotice';
import PwaUpdateNotice from './PwaUpdateNotice';
import PwaButton from './ui/PwaButton';
import VitaScanBrand from '../../components/brand/VitaScanBrand';

const navigation = [
	{ section: 'clientes', label: 'Clientes', icon: 'users' },
	{ section: 'avaliacao', label: 'Avaliar', icon: 'clipboard' },
	{ section: 'assistente', label: 'Assistente', icon: 'sparkles' },
	{ section: 'relatorio', label: 'Relatório', icon: 'fileText' },
];

function userInitial(name) {
	return String(name ?? 'P').trim().charAt(0).toUpperCase();
}

export default function OperationalShell({ activeSection, currentUser, enabledModules, onLogout, onNavigate, children }) {
	const isOnline = useOnlineStatus();
	const update = useServiceWorkerUpdate();
	const mainReference = useRef(null);
	const availableNavigation = navigation.filter((item) => {
		if (['avaliacao', 'assistente'].includes(item.section)) return enabledModules.assessment;
		if (item.section === 'relatorio') return enabledModules.reports;
		return true;
	});

	useEffect(() => {
		mainReference.current?.focus({ preventScroll: true });
	}, [activeSection]);

	return (
		<div className="pwa-shell pwa-native-shell pwa-content-safe text-ricosty-ink">
			<a href="#operational-content" className="pwa-focus fixed left-3 top-3 z-50 -translate-y-24 rounded-xl bg-ricosty-950 px-4 py-3 text-sm font-bold text-white transition focus:translate-y-0">Pular para o conteúdo</a>
			<header className="pwa-safe-top pwa-safe-inline sticky top-0 z-20 border-b border-ricosty-200/70 bg-white/95 pb-2.5 shadow-[0_8px_24px_rgba(97,38,55,0.05)] backdrop-blur-xl">
				<div className="pwa-operational-header mx-auto flex max-w-xl flex-wrap items-center gap-x-2.5 gap-y-2 sm:flex-nowrap sm:gap-3 lg:max-w-4xl">
					<VitaScanBrand headingLevel="h1" size="compact" showTagline={false} className="shrink-0" />
					<div className="order-3 flex min-w-0 basis-full items-center gap-2 border-t border-ricosty-100 pt-2 sm:order-none sm:basis-auto sm:border-l sm:border-t-0 sm:py-0 sm:pl-3 sm:pt-0">
						<span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-ricosty-100 to-ricosty-200 text-sm font-black text-ricosty-800 ring-1 ring-ricosty-200" aria-hidden="true">{userInitial(currentUser?.name)}</span>
						<div className="min-w-0" title={currentUser?.name ?? 'Profissional'}>
							<p className="truncate text-sm font-extrabold leading-4 text-ricosty-950">Olá, {currentUser?.name ?? 'profissional'}</p>
							<p className="mt-0.5 text-[11px] font-medium leading-4 text-slate-500">Bem-vindo ao atendimento</p>
						</div>
					</div>
					<div className="ml-auto flex shrink-0 items-center gap-1.5">
						<span role="status" aria-live="polite" title={isOnline ? 'Conectado' : 'Sem conexão'} className={`pwa-status-enter flex min-h-10 items-center gap-1.5 rounded-full px-2.5 text-[11px] font-bold ${isOnline ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-rose-50 text-rose-800 ring-1 ring-rose-200'}`}>
							<PwaIcon name={isOnline ? 'wifi' : 'wifiOff'} size={15} />
							<span className="hidden sm:inline">{isOnline ? 'Online' : 'Offline'}</span>
						</span>
						<a href="/dashboard" aria-label="Abrir painel completo" title="Painel completo" className="pwa-focus flex h-10 w-10 items-center justify-center rounded-xl border border-ricosty-200 bg-white text-ricosty-700 transition hover:bg-ricosty-50"><PwaIcon name="grid" size={18} /></a>
						<PwaButton type="button" onClick={onLogout} aria-label="Sair da conta" title="Sair" variant="ghost" icon="logOut" className="h-10 w-10 rounded-xl border border-ricosty-200 bg-white px-0 hover:bg-ricosty-50"><span className="sr-only">Sair da conta</span></PwaButton>
					</div>
				</div>
			</header>

			<main id="operational-content" ref={mainReference} tabIndex="-1" className="pwa-safe-inline mx-auto max-w-xl py-4 outline-none lg:max-w-4xl">
				{!isOnline ? <OfflineNotice detail="Consultas e ações que enviam dados ficam bloqueadas até a reconexão. Nenhum dado clínico é armazenado para uso offline." className="mb-4" /> : null}

				<InstallGuide />
				<div key={activeSection} className="pwa-screen-enter">{children}</div>
			</main>

			<PwaUpdateNotice visible={update.available} applying={update.applying} onApply={update.apply} onDismiss={update.dismiss} />

			<nav aria-label="Navegação operacional" className="pwa-bottom-navigation fixed inset-x-0 bottom-0 z-30 pt-2">
				<div className="mx-auto grid max-w-xl gap-1 rounded-[1.35rem] border border-ricosty-200/80 bg-white/95 p-1.5 shadow-[0_14px_40px_rgba(97,38,55,0.16)] backdrop-blur-xl lg:max-w-4xl" style={{ gridTemplateColumns: `repeat(${availableNavigation.length}, minmax(0, 1fr))` }}>
					{availableNavigation.map((item) => {
						const active = item.section === activeSection;
						return <button type="button" key={item.section} onClick={() => onNavigate(item.section)} aria-current={active ? 'page' : undefined} aria-label={`${item.label}${active ? ', seção atual' : ''}`} className={`pwa-navigation-item pwa-focus pwa-pressable relative flex min-h-14 min-w-0 touch-manipulation flex-col items-center justify-center gap-1 rounded-2xl px-1 text-[11px] font-bold transition ${active ? 'bg-ricosty-50 text-ricosty-800' : 'text-slate-600 hover:bg-ricosty-50/60 hover:text-ricosty-800'}`}><span className={`flex h-7 w-7 items-center justify-center rounded-xl transition ${active ? 'bg-gradient-to-br from-ricosty-500 to-ricosty-700 text-white shadow-md shadow-ricosty-200' : 'text-slate-600'}`}><PwaIcon name={item.icon} size={18} /></span><span className="max-w-full overflow-hidden text-ellipsis whitespace-nowrap">{item.label}</span>{active ? <span aria-hidden="true" className="absolute -bottom-0.5 h-1 w-5 rounded-full bg-ricosty-500" /> : null}</button>;
					})}
				</div>
			</nav>
		</div>
	);
}
