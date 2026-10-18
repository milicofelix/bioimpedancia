import React, { useEffect, useState } from 'react';
import OperationalShell from '../pwa/components/OperationalShell';
import OperationalModulePlaceholder from '../pwa/components/OperationalModulePlaceholder';
import ClientsFeature from '../pwa/features/clients/ClientsFeature';
import RelaxmedicAssessmentFeature from '../pwa/features/assessment/RelaxmedicAssessmentFeature';
import AssessmentAssistantFeature from '../pwa/features/assistant/AssessmentAssistantFeature';
import useOperationalSection from '../pwa/hooks/useOperationalSection';
import { apiErrorMessage, loadOperationalWorkspace, logout } from '../services/bioimpedanceApi';
import { registerOperationalServiceWorker } from '../pwa/services/registerServiceWorker';

export default function OperationalAppPage() {
	const [workspace, setWorkspace] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState('');
	const [selectedClient, setSelectedClient] = useState(null);
	const [selectedAssessment, setSelectedAssessment] = useState(null);
	const { section, navigate } = useOperationalSection();

	useEffect(() => {
		registerOperationalServiceWorker();

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

	function handleClientCreated(client) {
		setWorkspace((current) => ({
			...current,
			clients: [client, ...(current.clients ?? []).filter((item) => item.id !== client.id)],
		}));
	}

	function startAssessment(client) {
		setSelectedClient(client);
		setSelectedAssessment(null);
		navigate('avaliacao');
	}

	function assessmentCreated(client, assessment) {
		updateAssessmentState(client, assessment);
	}

	function updateAssessmentState(client, assessment) {
		setWorkspace((current) => ({
			...current,
			clients: (current.clients ?? []).map((item) => (item.id === client.id ? client : item)),
		}));
		setSelectedClient(client);
		setSelectedAssessment(assessment);
	}

	function openAssistant(client, assessment) {
		setSelectedClient(client);
		setSelectedAssessment(assessment);
		navigate('assistente');
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

	const scaleModel = workspace.clinic?.scale_model;

	return (
		<OperationalShell activeSection={section} clinic={workspace.clinic} currentUser={workspace.current_user} onLogout={handleLogout} onNavigate={navigate}>
			{section === 'clientes' ? (
				<ClientsFeature
					clients={workspace.clients ?? []}
					currentUser={workspace.current_user}
					scaleModel={scaleModel}
					onClientCreated={handleClientCreated}
					onNewAssessment={startAssessment}
					onOpenAssistant={openAssistant}
				/>
			) : section === 'avaliacao' ? (
				<RelaxmedicAssessmentFeature
					client={selectedClient}
					currentUser={workspace.current_user}
					scaleModel={scaleModel}
					onBackToClients={() => navigate('clientes')}
					onCreated={assessmentCreated}
					onOpenAssistant={() => navigate('assistente')}
				/>
			) : section === 'assistente' ? (
				<AssessmentAssistantFeature
					client={selectedClient}
					assessment={selectedAssessment}
					currentUser={workspace.current_user}
					onBackToClients={() => navigate('clientes')}
					onApproved={updateAssessmentState}
				/>
			) : (
				<OperationalModulePlaceholder section={section} selectedClient={selectedClient} selectedAssessment={selectedAssessment} onBackToClients={() => navigate('clientes')} />
			)}
		</OperationalShell>
	);
}
