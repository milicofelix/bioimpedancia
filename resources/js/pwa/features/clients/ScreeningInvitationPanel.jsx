import React, { useEffect, useState } from 'react';
import { localDatetimeNow } from '../../../shared/formatters';
import useActionLock from '../../../hooks/useActionLock';
import useOnlineStatus from '../../hooks/useOnlineStatus';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';
import ScreeningApprovalForm from './ScreeningApprovalForm';
import ScreeningHistoryPanel from './ScreeningHistoryPanel';
import ScreeningWorkspaceTabs from './ScreeningWorkspaceTabs';

function errorMessage(error) {
	const errors = error?.response?.data?.errors;
	return errors ? Object.values(errors).flat()[0] : error?.response?.data?.message || 'Não foi possível concluir a ação. Confira o histórico antes de repetir.';
}

export default function ScreeningInvitationPanel({ client, canCreateInvitation = true, creationUnavailableReason = '' }) {
	const online = useOnlineStatus();
	const runAction = useActionLock();
	const [loading, setLoading] = useState(true);
	const [busyAction, setBusyAction] = useState(null);
	const [questionnaires, setQuestionnaires] = useState([]);
	const [sessions, setSessions] = useState([]);
	const [questionnaireId, setQuestionnaireId] = useState('');
	const [paperDate, setPaperDate] = useState(() => localDatetimeNow().slice(0, 10));
	const [guardianName, setGuardianName] = useState('');
	const [guardianRelationship, setGuardianRelationship] = useState('');
	const [application, setApplication] = useState(null);
	const [feedback, setFeedback] = useState('');
	const [error, setError] = useState('');
	const [activeTab, setActiveTab] = useState('new');
	const available = questionnaires.filter((item) => item.is_available);
	const pending = questionnaires.filter((item) => item.status === 'pending_professional_approval');
	const birth = client.birth_date?.slice(0, 10);
	const isMinor = birth ? paperDate < `${Number(birth.slice(0, 4)) + 18}${birth.slice(4)}` : Number(client.age) < 18;

	useEffect(() => {
		let active = true;
		setLoading(true); setError(''); setFeedback(''); setApplication(null);
		setActiveTab(canCreateInvitation ? 'new' : 'history');
		setGuardianName(''); setGuardianRelationship(''); setPaperDate(localDatetimeNow().slice(0, 10));
		const url = `/screening/clients/${client.id}/invitations`;
		window.axios.get(url).then(({ data }) => {
			if (!active) return;
			setQuestionnaires(data.questionnaires ?? []); setSessions(data.sessions ?? []);
			setQuestionnaireId(String((data.questionnaires ?? []).find((item) => item.is_available)?.id ?? ''));
		}).catch((requestError) => { if (active) setError(errorMessage(requestError)); })
			.finally(() => { if (active) setLoading(false); });
		function refreshHistory() {
			if (document.visibilityState === 'hidden' || !navigator.onLine) return;
			window.axios.get(url).then(({ data }) => { if (active) setSessions(data.sessions ?? []); })
				.catch((requestError) => { if (active) setError(errorMessage(requestError)); });
		}
		window.addEventListener('focus', refreshHistory);
		document.addEventListener('visibilitychange', refreshHistory);
		return () => { active = false; window.removeEventListener('focus', refreshHistory); document.removeEventListener('visibilitychange', refreshHistory); };
	}, [canCreateInvitation, client.id]);

	useEffect(() => {
		if (application && sessions.some((session) => session.id === application.id && session.status === 'finalized')) {
			setApplication(null); setActiveTab('history');
			setFeedback('Transcrição concluída. Abra o resultado ou o PDF no histórico para conferir e entregar ao cliente.');
		}
	}, [application, sessions]);

	async function createApplication() {
		return runAction('create-paper-screening', async () => {
			if (!canCreateInvitation || !questionnaireId || busyAction || !online) return;
			setBusyAction('create'); setError(''); setFeedback(''); setApplication(null);
			try {
				const { data } = await window.axios.post('/screening/paper-applications', {
					bioimpedance_client_id: client.id, screening_questionnaire_id: Number(questionnaireId),
					paper_completed_on: paperDate, guardian_name: isMinor ? guardianName : null,
					guardian_relationship: isMinor ? guardianRelationship : null,
				});
				setApplication(data.application); setSessions((current) => [data.application, ...current]);
				setFeedback('Lançamento criado. Abra o questionário para registrar as respostas do papel. Nenhum convite foi enviado ou criado.');
			} catch (requestError) { setError(errorMessage(requestError)); }
			finally { setBusyAction(null); }
		});
	}

	async function cancelPaper(session) {
		if (busyAction || !online) return;
		const reason = window.prompt('Cancelar este lançamento pendente? O registro será preservado. Informe o motivo (mínimo de 5 caracteres):');
		if (reason === null) return;
		if (reason.trim().length < 5) { setError('Informe um motivo com pelo menos 5 caracteres.'); return; }
		return runAction(`cancel-paper-${session.id}`, async () => {
			setBusyAction(`cancel-${session.id}`); setError('');
			try {
				const { data } = await window.axios.patch(`/screening/sessions/${session.id}/transcription/cancel`, { reason: reason.trim() });
				setSessions((current) => current.map((item) => item.id === session.id ? data.session : item));
				if (application?.id === session.id) setApplication(null);
				setFeedback('Lançamento cancelado. O histórico foi preservado.');
			} catch (requestError) { setError(errorMessage(requestError)); }
			finally { setBusyAction(null); }
		});
	}

	async function revokeLegacy(session) {
		if (busyAction || !online || !window.confirm('Revogar o convite antigo? O link público deixará de funcionar. O histórico será preservado.')) return;
		setBusyAction(`revoke-${session.id}`); setError('');
		try {
			const { data } = await window.axios.patch(`/screening/invitations/${session.id}/revoke`);
			setSessions((current) => current.map((item) => item.id === session.id ? data.session : item));
			setFeedback('Convite antigo revogado. Para registrar o papel, crie uma nova aplicação interna.');
		} catch (requestError) { setError(errorMessage(requestError)); }
		finally { setBusyAction(null); }
	}

	function questionnaireApproved(approved) {
		setQuestionnaires((current) => current.map((item) => item.id === approved.id ? { ...item, ...approved }
			: item.code === approved.code && item.status === 'active' ? { ...item, status: 'retired', is_available: false } : item));
		setQuestionnaireId(String(approved.id)); setFeedback('Versão aprovada. Confira se corresponde ao formulário respondido em papel.'); setError('');
	}

	if (loading) return <PwaCard className="mt-4 p-5" role="status" aria-label="Carregando triagens"><div className="h-5 w-44 animate-pulse rounded bg-slate-200" /><div className="mt-4 h-24 animate-pulse rounded-2xl bg-slate-100" /></PwaCard>;
	return <PwaCard className="mt-4 p-4 sm:p-5" aria-labelledby="screening-invitation-title">
		<div className="flex items-start gap-3"><span className="rounded-xl bg-teal-50 p-2 text-teal-700"><PwaIcon name="clipboard" /></span><div><h3 id="screening-invitation-title" className="font-extrabold text-ricosty-ink">Triagens e questionários</h3><p className="mt-1 text-sm text-slate-600">Registre as respostas preenchidas em papel na clínica, confira o resultado e gere o PDF para entregar ao cliente.</p></div></div>
		{!online ? <p className="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900" role="status">Conecte-se à internet para registrar e consultar as triagens. Respostas não são armazenadas neste aparelho.</p> : null}
		{error ? <p className="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800" role="alert">{error}</p> : null}
		{feedback ? <p className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800" role="status">{feedback}</p> : null}
		<ScreeningWorkspaceTabs activeTab={activeTab} historyCount={sessions.length} onChange={setActiveTab} />
		<div id="screening-panel-new" role="tabpanel" aria-labelledby="screening-tab-new" hidden={activeTab !== 'new'}>
			{!canCreateInvitation ? <p className="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">{creationUnavailableReason || 'Novos lançamentos estão indisponíveis. O histórico permanece disponível.'}</p> : null}
			<div className="mt-4 grid gap-3 sm:grid-cols-2">
				<label className="text-sm font-bold text-slate-700">Questionário do papel<select value={questionnaireId} disabled={!canCreateInvitation} onChange={(event) => setQuestionnaireId(event.target.value)} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><option value="">Selecione a mesma versão</option>{questionnaires.map((item) => <option key={item.id} value={item.id} disabled={!item.is_available}>{item.name} · {item.version}{!item.is_available ? ' (indisponível)' : ''}</option>)}</select></label>
				<label className="text-sm font-bold text-slate-700">Data em que o cliente respondeu no papel<input type="date" value={paperDate} max={localDatetimeNow().slice(0, 10)} disabled={!canCreateInvitation} onChange={(event) => setPaperDate(event.target.value)} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3" /></label>
			</div>
			{isMinor ? <div className="mt-3 rounded-xl bg-amber-50 p-4"><p className="text-sm font-bold text-amber-950">Responsável legal na data do formulário</p><p className="mt-1 text-xs text-amber-900">Confira a identificação e o consentimento no documento original.</p><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="text-sm font-bold">Nome do responsável<input value={guardianName} maxLength={160} onChange={(event) => setGuardianName(event.target.value)} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3" /></label><label className="text-sm font-bold">Vínculo com o menor<input value={guardianRelationship} maxLength={100} onChange={(event) => setGuardianRelationship(event.target.value)} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3" /></label></div></div> : null}
			{!available.length ? <p className="mt-3 rounded-xl bg-amber-50 p-3 text-xs text-amber-900">Nenhuma versão aprovada está ativa para lançamento.</p> : null}
			{pending.map((questionnaire) => <ScreeningApprovalForm key={questionnaire.id} questionnaire={questionnaire} onApproved={questionnaireApproved} />)}
			<PwaButton type="button" onClick={createApplication} disabled={!canCreateInvitation || !questionnaireId || !paperDate || !online || Boolean(busyAction) || (isMinor && (!guardianName.trim() || !guardianRelationship.trim()))} loading={busyAction === 'create'} icon="clipboard" className="mt-4 w-full">Registrar respostas do papel</PwaButton>
			{application?.transcription_url ? <div className="mt-4 rounded-xl border border-teal-200 bg-teal-50 p-4" role="status"><p className="text-sm font-bold text-teal-950">Questionário pronto para lançamento interno</p><p className="mt-1 text-sm text-slate-700">Abra para transcrever. Se sair antes de finalizar, retome pelo histórico; as escolhas ainda não enviadas não são salvas.</p><a href={application.transcription_url} target="_blank" rel="noopener noreferrer" className="pwa-focus mt-3 inline-flex min-h-11 items-center rounded-xl bg-teal-700 px-4 text-sm font-bold text-white">Abrir questionário</a></div> : null}
		</div>
		<div id="screening-panel-history" role="tabpanel" aria-labelledby="screening-tab-history" hidden={activeTab !== 'history'}>
			<ScreeningHistoryPanel key={client.id} sessions={sessions} busyAction={busyAction} canCreateInvitation={canCreateInvitation && online} onNewApplication={() => setActiveTab('new')} onRevoke={revokeLegacy} onCancelPaper={cancelPaper} />
		</div>
	</PwaCard>;
}
