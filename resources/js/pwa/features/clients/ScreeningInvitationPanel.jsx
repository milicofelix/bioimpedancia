import React, { useEffect, useMemo, useState } from 'react';
import { formatDate } from '../../../shared/formatters';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';
import ScreeningApprovalForm from './ScreeningApprovalForm';
import ScreeningQrCode from './ScreeningQrCode';
import ScreeningHistoryPanel from './ScreeningHistoryPanel';
import ScreeningWorkspaceTabs from './ScreeningWorkspaceTabs';

const durationOptions = [
	{ value: 60, label: '1 hora' },
	{ value: 1440, label: '24 horas' },
	{ value: 4320, label: '3 dias' },
	{ value: 10080, label: '7 dias' },
];

function errorMessage(error) {
	const errors = error?.response?.data?.errors;
	return errors ? Object.values(errors).flat()[0] : error?.response?.data?.message || 'Não foi possível concluir a ação.';
}

function questionnaireAvailabilityLabel(questionnaire) {
	if (questionnaire.is_available) return '';
	return questionnaire.status === 'retired' ? ' (descontinuado)' : ' (aguardando aprovação)';
}

export default function ScreeningInvitationPanel({ client }) {
	const [loading, setLoading] = useState(true);
	const [busyAction, setBusyAction] = useState(null);
	const [questionnaires, setQuestionnaires] = useState([]);
	const [sessions, setSessions] = useState([]);
	const [questionnaireId, setQuestionnaireId] = useState('');
	const [expiresInMinutes, setExpiresInMinutes] = useState(1440);
	const [accessMode, setAccessMode] = useState('remote');
	const [guardianName, setGuardianName] = useState('');
	const [guardianRelationship, setGuardianRelationship] = useState('');
	const [invitation, setInvitation] = useState(null);
	const [feedback, setFeedback] = useState('');
	const [error, setError] = useState('');
	const [activeTab, setActiveTab] = useState('new');

	const availableQuestionnaires = useMemo(() => questionnaires.filter((item) => item.is_available), [questionnaires]);
	const pendingQuestionnaires = questionnaires.filter((item) => item.status === 'pending_professional_approval');
	const isMinor = client.age !== null && client.age !== undefined && Number(client.age) < 18;

	useEffect(() => {
		let active = true;
		setLoading(true);
		setActiveTab('new');
		setGuardianName('');
		setGuardianRelationship('');
		window.axios.get(`/screening/clients/${client.id}/invitations`).then(({ data }) => {
			if (!active) return;
			setQuestionnaires(data.questionnaires ?? []);
			setSessions(data.sessions ?? []);
			setQuestionnaireId(String((data.questionnaires ?? []).find((item) => item.is_available)?.id ?? ''));
		}).catch((requestError) => {
			if (active) setError(errorMessage(requestError));
		}).finally(() => {
			if (active) setLoading(false);
		});

		return () => { active = false; };
	}, [client.id]);

	useEffect(() => {
		if (!invitation?.url || activeTab !== 'new') return;

		window.requestAnimationFrame(() => {
			const result = document.getElementById('screening-generated-link');
			result?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			result?.focus({ preventScroll: true });
		});
	}, [activeTab, invitation]);

	async function createInvitation() {
		if (!questionnaireId || busyAction) return;
		setBusyAction('create'); setError(''); setFeedback(''); setInvitation(null);
		try {
			const { data } = await window.axios.post('/screening/invitations', {
				bioimpedance_client_id: client.id,
				screening_questionnaire_id: Number(questionnaireId),
				expires_in_minutes: Number(expiresInMinutes),
				access_mode: accessMode,
				guardian_name: isMinor ? guardianName : null,
				guardian_relationship: isMinor ? guardianRelationship : null,
			});
			setInvitation(data.invitation);
			setSessions((current) => [data.invitation, ...current]);
			setFeedback(accessMode === 'reception' ? 'Acesso de recepção criado. Abra o link no tablet sem manter a conta profissional aberta.' : 'Convite temporário criado.');
		} catch (requestError) { setError(errorMessage(requestError)); }
		finally { setBusyAction(null); }
	}

	async function resend(session) {
		if (busyAction) return;
		const confirmation = session.revoked_at
			? 'Criar um novo acesso para esta triagem? Um novo link temporário será gerado para compartilhar com o cliente.'
			: 'Gerar um novo link para esta triagem? O link anterior deixará de funcionar imediatamente e será necessário compartilhar o novo link com o cliente.';
		if (!window.confirm(confirmation)) return;
		setBusyAction(`resend-${session.id}`); setError(''); setFeedback('');
		try {
			const { data } = await window.axios.post(`/screening/invitations/${session.id}/resend`, { expires_in_minutes: Number(expiresInMinutes) });
			setInvitation(data.invitation);
			setSessions((current) => current.map((item) => item.id === session.id ? data.invitation : item));
			setActiveTab('new');
			setFeedback('Novo link criado. Copie ou compartilhe o acesso exibido abaixo; o link anterior não funciona mais.');
		} catch (requestError) { setError(errorMessage(requestError)); }
		finally { setBusyAction(null); }
	}

	async function revoke(session) {
		if (busyAction || !window.confirm('Revogar este acesso temporário? O link deixará de funcionar imediatamente.')) return;
		setBusyAction(`revoke-${session.id}`); setError(''); setFeedback('');
		try {
			const { data } = await window.axios.patch(`/screening/invitations/${session.id}/revoke`);
			setSessions((current) => current.map((item) => item.id === session.id ? data.session : item));
			if (invitation?.id === session.id) setInvitation(null);
			setFeedback('Acesso temporário revogado.');
		} catch (requestError) { setError(errorMessage(requestError)); }
		finally { setBusyAction(null); }
	}

	async function copyLink() {
		if (!invitation?.url) return;
		await navigator.clipboard.writeText(invitation.url);
		setFeedback('Link copiado. Confirme o destinatário antes de enviar.');
	}

	async function shareLink() {
		if (!invitation?.url) return;
		if (!navigator.share) return copyLink();
		try { await navigator.share({ title: invitation.questionnaire_name, text: 'Acesse seu questionário Vita Scan:', url: invitation.url }); }
		catch (shareError) { if (shareError?.name !== 'AbortError') await copyLink(); }
	}

	function questionnaireApproved(approvedQuestionnaire) {
		setQuestionnaires((current) => current.map((item) => {
			if (item.id === approvedQuestionnaire.id) return { ...item, ...approvedQuestionnaire };
			if (item.code === approvedQuestionnaire.code && item.status === 'active') return { ...item, status: 'retired', is_available: false };
			return item;
		}));
		setQuestionnaireId(String(approvedQuestionnaire.id));
		setFeedback(`${approvedQuestionnaire.name} · ${approvedQuestionnaire.version} aprovado e ativado. O questionário já pode ser enviado.`);
		setError('');
	}

	if (loading) return <PwaCard className="mt-4 p-5"><div className="h-5 w-44 animate-pulse rounded bg-slate-200" /><div className="mt-4 h-24 animate-pulse rounded-2xl bg-slate-100" /></PwaCard>;

	return (
		<PwaCard className="mt-4 p-4 sm:p-5" aria-labelledby="screening-invitation-title">
			<div className="flex items-start gap-3"><span className="rounded-xl bg-teal-50 p-2 text-teal-700"><PwaIcon name="clipboard" /></span><div><h3 id="screening-invitation-title" className="font-extrabold text-ricosty-ink">Triagens e questionários</h3><p className="mt-1 text-sm text-slate-600">Crie novas aplicações ou consulte o histórico deste cliente.</p></div></div>

			{error ? <p className="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800" role="alert">{error}</p> : null}
			{feedback ? <p className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800" role="status">{feedback}</p> : null}
			<ScreeningWorkspaceTabs activeTab={activeTab} historyCount={sessions.length} onChange={setActiveTab} />

			<div id="screening-panel-new" role="tabpanel" aria-labelledby="screening-tab-new" className={activeTab === 'new' ? '' : 'hidden'}>
			<div className="mt-4 grid gap-3 sm:grid-cols-2">
				<label className="text-sm font-bold text-slate-700 sm:col-span-2">Questionário<select value={questionnaireId} onChange={(event) => setQuestionnaireId(event.target.value)} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><option value="">Selecione</option>{questionnaires.map((item) => <option key={item.id} value={item.id} disabled={!item.is_available}>{item.name} · {item.version}{questionnaireAvailabilityLabel(item)}</option>)}</select></label>
				<label className="text-sm font-bold text-slate-700">Validade<select value={expiresInMinutes} onChange={(event) => setExpiresInMinutes(Number(event.target.value))} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3">{durationOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></label>
				<label className="text-sm font-bold text-slate-700">Modo<select value={accessMode} onChange={(event) => setAccessMode(event.target.value)} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><option value="remote">Enviar ao cliente</option><option value="reception">Tablet da recepção</option></select></label>
			</div>
			{isMinor ? <div className="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4"><p className="text-sm font-extrabold text-amber-950">Fluxo para menor de idade</p><p className="mt-1 text-xs leading-5 text-amber-900">Informe o responsável legal. Ele deverá confirmar o próprio nome e fornecer consentimento específico ao abrir o convite.</p><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="text-sm font-bold text-slate-700">Responsável legal<input type="text" value={guardianName} onChange={(event) => setGuardianName(event.target.value)} maxLength={160} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3" /></label><label className="text-sm font-bold text-slate-700">Vínculo com o menor<input type="text" value={guardianRelationship} onChange={(event) => setGuardianRelationship(event.target.value)} maxLength={100} placeholder="Ex.: mãe, pai ou tutor" className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3" /></label></div></div> : null}

			{!availableQuestionnaires.length ? <p className="mt-3 rounded-xl bg-amber-50 p-3 text-xs font-semibold leading-5 text-amber-900">Nenhuma versão está ativa. As versões editoriais permanecem bloqueadas até registrar a aprovação de um profissional habilitado.</p> : null}
			{pendingQuestionnaires.length ? <p className="mt-3 rounded-xl bg-sky-50 p-3 text-xs font-semibold leading-5 text-sky-900">Há {pendingQuestionnaires.length} instrumento(s) aguardando revisão profissional. Cada instrumento possui conteúdo e resultado independentes.</p> : null}
			{pendingQuestionnaires.map((questionnaire) => <ScreeningApprovalForm key={questionnaire.id} questionnaire={questionnaire} onApproved={questionnaireApproved} />)}
			<PwaButton type="button" onClick={createInvitation} disabled={!questionnaireId || Boolean(busyAction) || (isMinor && (!guardianName.trim() || !guardianRelationship.trim()))} loading={busyAction === 'create'} icon="share" className="mt-4 w-full">Criar link temporário</PwaButton>

			{invitation ? <div id="screening-generated-link" tabIndex={-1} className="pwa-focus mt-4 rounded-2xl border border-teal-200 bg-teal-50/70 p-4"><p className="text-xs font-bold uppercase text-teal-800">Novo link pronto para envio</p><p className="mt-1 text-sm font-semibold text-teal-950">O sistema não envia mensagens automaticamente. Escolha copiar ou compartilhar abaixo.</p><p className="mt-2 break-all rounded-lg bg-white p-2 text-xs text-slate-700">{invitation.url}</p><p className="mt-2 text-xs text-slate-600">Expira em {formatDate(invitation.expires_at)}. O QR Code abaixo é gerado localmente, sem enviar o token a terceiros.</p><ScreeningQrCode payload={invitation.qr_payload} questionnaireVersion={invitation.questionnaire_version} /><div className="mt-3 grid gap-2 sm:grid-cols-2"><PwaButton type="button" onClick={copyLink} icon="copy" variant="secondary">Copiar link</PwaButton><PwaButton type="button" onClick={shareLink} icon="share">Compartilhar</PwaButton></div></div> : null}
			</div>
			<div id="screening-panel-history" role="tabpanel" aria-labelledby="screening-tab-history" className={activeTab === 'history' ? '' : 'hidden'}>
				<ScreeningHistoryPanel key={client.id} sessions={sessions} busyAction={busyAction} onNewApplication={() => setActiveTab('new')} onResend={resend} onRevoke={revoke} />
			</div>
		</PwaCard>
	);
}
