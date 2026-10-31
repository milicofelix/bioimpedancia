import React, { useEffect, useState } from 'react';
import PwaIcon from './ui/PwaIcon';
import PwaButton from './ui/PwaButton';
import useActionLock from '../../hooks/useActionLock';

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
	const runAction = useActionLock();
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
		return runAction('install-pwa', async () => {
			if (!installPrompt || !personalDeviceConfirmed) return;

			await installPrompt.prompt();
			const choice = await installPrompt.userChoice;
			if (choice.outcome === 'accepted') setInstalled(true);
			setInstallPrompt(null);
		});
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
		<aside className="pwa-card pwa-screen-enter mb-5 p-4 text-sm text-slate-700" aria-labelledby="install-guide-title">
			<div className="flex items-start justify-between gap-3">
				<div className="flex items-center gap-3">
					<span className="flex h-10 w-10 items-center justify-center rounded-xl bg-ricosty-100 text-ricosty-700"><PwaIcon name="shield" size={20} /></span>
					<div><p className="text-xs font-bold uppercase tracking-wide text-ricosty-600">Instalar no celular</p>
					<h2 id="install-guide-title" className="mt-1 font-bold text-slate-950">Use como um aplicativo</h2>
					</div>
				</div>
				<button type="button" onClick={dismiss} aria-label="Fechar orientação de instalação" className="pwa-focus pwa-pressable flex h-11 w-11 items-center justify-center rounded-xl text-xl leading-none text-slate-500 hover:bg-ricosty-50">×</button>
			</div>
			<div className="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs leading-5 text-amber-950">
				<strong>Atenção:</strong> não instale o aplicativo em celular compartilhado. A instalação mantém acesso rápido à tela de login e pode expor a sessão se você permanecer conectado.
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
					<PwaButton type="button" onClick={requestInstall} className="mt-3">Instalar agora</PwaButton>
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
