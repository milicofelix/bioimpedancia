import React, { useEffect, useState } from 'react';
import OperationalShell from '../pwa/components/OperationalShell';
import OperationalOverview from '../pwa/features/home/OperationalOverview';
import { apiErrorMessage, loadOperationalWorkspace, logout } from '../services/bioimpedanceApi';

export default function OperationalAppPage() {
	const [workspace, setWorkspace] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState('');

	useEffect(() => {
		let active = true;

		loadOperationalWorkspace()
			.then((data) => {
				if (active) setWorkspace(data);
			})
			.catch((requestError) => {
				if (active) setError(apiErrorMessage(requestError, 'Não foi possível carregar a área operacional.'));
			})
			.finally(() => {
				if (active) setLoading(false);
			});

		return () => {
			active = false;
		};
	}, []);

	async function handleLogout() {
		await logout();
		window.location.assign('/login');
	}

	if (loading) {
		return <div className="flex min-h-dvh items-center justify-center bg-slate-100 px-6 text-sm font-semibold text-slate-600">Carregando área operacional...</div>;
	}

	if (error) {
		return (
			<div className="flex min-h-dvh items-center justify-center bg-slate-100 px-6">
				<div className="w-full max-w-sm rounded-2xl border border-rose-200 bg-white p-5 text-center shadow-sm">
					<h1 className="font-bold text-slate-950">Não foi possível abrir a área operacional</h1>
					<p className="mt-2 text-sm text-rose-700">{error}</p>
					<button type="button" onClick={() => window.location.reload()} className="mt-4 rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">Tentar novamente</button>
				</div>
			</div>
		);
	}

	return (
		<OperationalShell clinic={workspace.clinic} currentUser={workspace.current_user} onLogout={handleLogout}>
			<OperationalOverview
				clientsCount={workspace.clients?.length ?? 0}
				scaleModel={workspace.clinic?.scale_model_name ?? workspace.clinic?.scale_model}
			/>
		</OperationalShell>
	);
}
