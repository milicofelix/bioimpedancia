import React, { useEffect, useState } from 'react';

const DISMISSED_KEY = 'ricosty:pwa-install-guide-security-v2-dismissed';

function wasDismissed() {
	try {
		return window.localStorage.getItem(DISMISSED_KEY) === 'true';
	} catch {
		return false;
	}
}

function isStandalone() {
	return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function isIosDevice() {
	return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
}

export default function InstallGuide() {
	const [installPrompt, setInstallPrompt] = useState(null);
	const [installed, setInstalled] = useState(isStandalone);
	const [dismissed, setDismissed] = useState(wasDismissed);
	const [personalDeviceConfirmed, setPersonalDeviceConfirmed] = useState(false);

	useEffect(() => {
		const handleBeforeInstall = (event) => {
			event.preventDefault();
			setInstallPrompt(event);
		};
		const handleInstalled = () => {
			setInstalled(true);
			setInstallPrompt(null);
		};

		window.addEventListener('beforeinstallprompt', handleBeforeInstall);
		window.addEventListener('appinstalled', handleInstalled);

		return () => {
			window.removeEventListener('beforeinstallprompt', handleBeforeInstall);
			window.removeEventListener('appinstalled', handleInstalled);
		};
	}, []);

	async function requestInstall() {
		if (!installPrompt || !personalDeviceConfirmed) return;

		await installPrompt.prompt();
		const choice = await installPrompt.userChoice;
		if (choice.outcome === 'accepted') setInstalled(true);
		setInstallPrompt(null);
	}

	function dismiss() {
		try {
			window.localStorage.setItem(DISMISSED_KEY, 'true');
		} catch {
			// O fechamento continua válido para a sessão quando o armazenamento estiver bloqueado.
		}
		setDismissed(true);
	}

	if (installed || dismissed) return null;

	const ios = isIosDevice();

	return (
		<aside className="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-slate-700" aria-labelledby="install-guide-title">
			<div className="flex items-start justify-between gap-3">
				<div>
					<p className="text-xs font-bold uppercase tracking-wide text-[#b96f7d]">Instalar no celular</p>
					<h2 id="install-guide-title" className="mt-1 font-bold text-slate-950">Use como um aplicativo</h2>
				</div>
				<button type="button" onClick={dismiss} aria-label="Fechar orientação de instalação" className="rounded-lg px-2 py-1 text-lg leading-none text-slate-500">×</button>
			</div>
			<div className="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs leading-5 text-amber-950">
				<strong>Atenção:</strong> não instale a PWA em celular compartilhado. A instalação mantém acesso rápido à tela de login e pode expor a sessão se você permanecer conectado.
			</div>
			<label className="mt-3 flex items-start gap-2 text-xs font-semibold leading-5 text-slate-700">
				<input type="checkbox" checked={personalDeviceConfirmed} onChange={(event) => setPersonalDeviceConfirmed(event.target.checked)} className="mt-0.5 h-4 w-4 rounded border-rose-300 text-[#b96f7d]" />
				<span>Confirmo que este é um aparelho pessoal e protegido por senha ou biometria.</span>
			</label>

			{!personalDeviceConfirmed ? (
				<p className="mt-3 text-xs font-semibold text-amber-800">Confirme que o aparelho é pessoal para visualizar as instruções de instalação.</p>
			) : installPrompt ? (
				<>
					<p className="mt-2 leading-5">Instale a área operacional na tela inicial para abrir em modo aplicativo.</p>
					<button type="button" onClick={requestInstall} className="mt-3 rounded-xl bg-[#b96f7d] px-4 py-2 font-bold text-white">Instalar agora</button>
				</>
			) : ios ? (
				<ol className="mt-2 list-decimal space-y-1 pl-5 leading-5">
					<li>Abra esta página no Safari.</li>
					<li>Toque em Compartilhar.</li>
					<li>Escolha “Adicionar à Tela de Início”.</li>
				</ol>
			) : (
				<p className="mt-2 leading-5">No Android, abra o menu do navegador e escolha “Instalar aplicativo” ou “Adicionar à tela inicial”.</p>
			)}
		</aside>
	);
}
