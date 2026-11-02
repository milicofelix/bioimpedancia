import React, { useEffect, useState } from 'react';
import OperationalShell from '../pwa/components/OperationalShell';
import ClientsFeature from '../pwa/features/clients/ClientsFeature';
import RelaxmedicAssessmentFeature from '../pwa/features/assessment/RelaxmedicAssessmentFeature';
import AssessmentAssistantFeature from '../pwa/features/assistant/AssessmentAssistantFeature';
import AssessmentReportFeature from '../pwa/features/report/AssessmentReportFeature';
import useOperationalSection from '../pwa/hooks/useOperationalSection';
import { apiErrorMessage, loadOperationalWorkspace, logout } from '../services/bioimpedanceApi';
import { registerOperationalServiceWorker } from '../pwa/services/registerServiceWorker';
import { observeMobileViewport } from '../pwa/services/mobileViewport';
import { clearOperationalCaches } from '../security/browserSession';
import PwaButton from '../pwa/components/ui/PwaButton';
import PwaIcon from '../pwa/components/ui/PwaIcon';
import useActionLock from '../hooks/useActionLock';
import { clinicModules } from '../shared/clinicModules';

export default function OperationalAppPage() {
	const runAction = useActionLock();
	const [workspace, setWorkspace] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState('');
	const [selectedClient, setSelectedClient] = useState(null);
	const [selectedAssessment, setSelectedAssessment] = useState(null);
	const { section, navigate } = useOperationalSection();
	const enabledModules = clinicModules(workspace?.clinic);

	useEffect(() => {
		registerOperationalServiceWorker();
		const stopObservingViewport = observeMobileViewport();

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
			stopObservingViewport();
		};
	}, []);

	useEffect(() => {
		if (!workspace) return;
		if (['avaliacao', 'assistente'].includes(section) && !enabledModules.assessment) navigate('clientes');
		if (section === 'relatorio' && !enabledModules.reports) navigate('clientes');
	}, [workspace, section, enabledModules.assessment, enabledModules.reports]);

	async function handleLogout() {
		return runAction('logout', async () => {
			try {
				await logout();
			} finally {
				await clearOperationalCaches().catch(() => {});
				window.location.assign('/login');
			}
		});
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

	function openReport(client, assessment) {
		setSelectedClient(client);
		setSelectedAssessment(assessment);
		navigate('relatorio');
	}

	function updateSelectedAssessment(assessment) {
		const client = (workspace.clients ?? []).find((item) => item.id === assessment.bioimpedance_client_id) ?? selectedClient;
		if (!client) return;

		updateAssessmentState({
			...client,
			assessments: (client.assessments ?? []).map((item) => (item.id === assessment.id ? assessment : item)),
		}, assessment);
	}

	if (loading) {
		return <OperationalLoading />;
	}

	if (error) {
		return (
			<div className="pwa-safe-inline pwa-safe-top pwa-safe-bottom pwa-shell pwa-native-shell flex items-center justify-center">
				<div role="alert" className="pwa-card w-full max-w-sm p-6 text-center">
					<div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-100 text-rose-700"><PwaIcon name="alertTriangle" /></div>
					<h1 className="mt-3 font-bold text-slate-950">Não foi possível abrir a área operacional</h1>
					<p className="mt-2 text-sm text-rose-700">{error}</p>
					<PwaButton type="button" icon="refresh" onClick={() => window.location.reload()} className="mt-5">Tentar novamente</PwaButton>
				</div>
			</div>
		);
	}

	const scaleModel = workspace.clinic?.scale_model;

	return (
		<OperationalShell activeSection={section} currentUser={workspace.current_user} enabledModules={enabledModules} onLogout={handleLogout} onNavigate={navigate}>
			{section === 'clientes' ? (
				<ClientsFeature
					clients={workspace.clients ?? []}
					currentUser={workspace.current_user}
					scaleModel={scaleModel}
					enabledModules={enabledModules}
					onClientCreated={handleClientCreated}
					onNewAssessment={startAssessment}
					onOpenAssistant={openAssistant}
					onOpenReport={openReport}
				/>
			) : section === 'avaliacao' ? (
				<RelaxmedicAssessmentFeature
					client={selectedClient}
					currentUser={workspace.current_user}
					scaleModel={scaleModel}
					onBackToClients={() => navigate('clientes')}
					onCreated={assessmentCreated}
					onOpenAssistant={() => navigate('assistente')}
					onOpenReport={() => navigate('relatorio')}
					canOpenReport={enabledModules.reports}
				/>
			) : section === 'assistente' ? (
				<AssessmentAssistantFeature
					client={selectedClient}
					assessment={selectedAssessment}
					currentUser={workspace.current_user}
					onBackToClients={() => navigate('clientes')}
					onApproved={updateAssessmentState}
					onOpenReport={() => navigate('relatorio')}
					canOpenReport={enabledModules.reports}
				/>
			) : section === 'relatorio' ? (
				<AssessmentReportFeature
					client={selectedClient}
					assessment={selectedAssessment}
					pdfEnabled={workspace.clinic.assessment_pdf_enabled !== false}
					currentUser={workspace.current_user}
					onBackToClients={() => navigate('clientes')}
					onAssessmentUpdated={updateSelectedAssessment}
				/>
			) : (
				<OperationalUnavailable onBack={() => navigate('clientes')} />
			)}
		</OperationalShell>
	);
}

function OperationalLoading() {
	return (
		<div role="status" aria-live="polite" aria-label="Carregando área operacional" className="pwa-launch-screen pwa-shell px-4 py-5">
			<div className="mx-auto max-w-xl">
				<div className="mb-6 flex flex-col items-center pt-[max(1rem,env(safe-area-inset-top))] text-center">
					<img src="/pwa/icons/icon-192.png" alt="" className="h-20 w-20 rounded-[1.4rem] shadow-[0_14px_35px_rgba(114,42,62,0.2)]" />
					<p className="mt-3 text-lg font-extrabold text-ricosty-950">Vita Scan</p>
					<p className="mt-1 text-sm text-slate-600">Carregando o atendimento…</p>
				</div>
				<div className="flex items-center justify-between"><span className="pwa-skeleton h-11 w-28 rounded-xl" /><span className="pwa-skeleton h-10 w-24 rounded-full" /></div>
				<div className="pwa-skeleton mt-6 h-36 rounded-[1.6rem]" />
				<div className="mt-6 flex items-end justify-between"><div className="space-y-2"><span className="pwa-skeleton block h-3 w-20 rounded" /><span className="pwa-skeleton block h-8 w-36 rounded-lg" /></div><span className="pwa-skeleton h-11 w-24 rounded-xl" /></div>
				<div className="mt-5 space-y-3">{[1, 2, 3].map((item) => <div key={item} className="pwa-card flex items-center gap-3 p-4"><span className="pwa-skeleton h-11 w-11 shrink-0 rounded-full" /><span className="flex-1 space-y-2"><span className="pwa-skeleton block h-4 w-2/3 rounded" /><span className="pwa-skeleton block h-3 w-1/2 rounded" /></span></div>)}</div>
				<span className="sr-only">Carregando área operacional...</span>
			</div>
		</div>
	);
}

function OperationalUnavailable({ onBack }) {
	return (
		<div role="alert" className="pwa-card p-6 text-center">
			<div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-800"><PwaIcon name="alertTriangle" /></div>
			<h1 className="mt-3 text-xl font-bold text-slate-950">Seção indisponível</h1>
			<p className="mt-2 text-sm leading-6 text-slate-600">Não foi possível abrir esta área. Retorne aos clientes e selecione novamente a ação desejada.</p>
			<PwaButton type="button" onClick={onBack} icon="arrowLeft" className="mt-5">Voltar para clientes</PwaButton>
		</div>
	);
}
