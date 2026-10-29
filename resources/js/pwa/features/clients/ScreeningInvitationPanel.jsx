import React, { useEffect, useMemo, useState } from 'react';
import { formatDate } from '../../../shared/formatters';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';

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

export default function ScreeningInvitationPanel({ client }) {
	const [loading, setLoading] = useState(true);
	const [busyAction, setBusyAction] = useState(null);
	const [questionnaires, setQuestionnaires] = useState([]);
	const [sessions, setSessions] = useState([]);
	const [questionnaireId, setQuestionnaireId] = useState('');
	const [expiresInMinutes, setExpiresInMinutes] = useState(1440);
	const [accessMode, setAccessMode] = useState('remote');
	const [invitation, setInvitation] = useState(null);
	const [feedback, setFeedback] = useState('');
	const [error, setError] = useState('');

	const availableQuestionnaires = useMemo(() => questionnaires.filter((item) => item.is_available), [questionnaires]);

	useEffect(() => {
		let active = true;
		setLoading(true);
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

	async function createInvitation() {
		if (!questionnaireId || busyAction) return;
		setBusyAction('create'); setError(''); setFeedback(''); setInvitation(null);
		try {
			const { data } = await window.axios.post('/screening/invitations', {
				bioimpedance_client_id: client.id,
				screening_questionnaire_id: Number(questionnaireId),
				expires_in_minutes: Number(expiresInMinutes),
				access_mode: accessMode,
			});
			setInvitation(data.invitation);
			setSessions((current) => [data.invitation, ...current]);
			setFeedback(accessMode === 'reception' ? 'Acesso de recepção criado. Abra o link no tablet sem manter a conta profissional aberta.' : 'Convite temporário criado.');
		} catch (requestError) { setError(errorMessage(requestError)); }
		finally { setBusyAction(null); }
	}

	async function resend(session) {
		if (busyAction) return;
		setBusyAction(`resend-${session.id}`); setError(''); setFeedback('');
		try {
			const { data } = await window.axios.post(`/screening/invitations/${session.id}/resend`, { expires_in_minutes: Number(expiresInMinutes) });
			setInvitation(data.invitation);
			setSessions((current) => current.map((item) => item.id === session.id ? data.invitation : item));
			setFeedback('Novo link criado. O link anterior deixou de funcionar.');
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

	if (loading) return <PwaCard className="mt-4 p-5"><div className="h-5 w-44 animate-pulse rounded bg-slate-200" /><div className="mt-4 h-24 animate-pulse rounded-2xl bg-slate-100" /></PwaCard>;

	return (
		<PwaCard className="mt-4 p-5" aria-labelledby="screening-invitation-title">
			<div className="flex items-start gap-3"><span className="rounded-xl bg-teal-50 p-2 text-teal-700"><PwaIcon name="clipboard" /></span><div><h3 id="screening-invitation-title" className="font-extrabold text-ricosty-ink">Questionário de triagem</h3><p className="mt-1 text-sm text-slate-600">Crie um acesso temporário para preenchimento fora do sistema profissional.</p></div></div>

			{error ? <p className="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800" role="alert">{error}</p> : null}
			{feedback ? <p className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800" role="status">{feedback}</p> : null}

			<div className="mt-4 grid gap-3 sm:grid-cols-2">
				<label className="text-sm font-bold text-slate-700 sm:col-span-2">Questionário<select value={questionnaireId} onChange={(event) => setQuestionnaireId(event.target.value)} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><option value="">Selecione</option>{questionnaires.map((item) => <option key={item.id} value={item.id} disabled={!item.is_available}>{item.name} · {item.version}{item.is_available ? '' : ' (aguardando aprovação)'}</option>)}</select></label>
				<label className="text-sm font-bold text-slate-700">Validade<select value={expiresInMinutes} onChange={(event) => setExpiresInMinutes(Number(event.target.value))} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3">{durationOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></label>
				<label className="text-sm font-bold text-slate-700">Modo<select value={accessMode} onChange={(event) => setAccessMode(event.target.value)} className="pwa-focus mt-1 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><option value="remote">Enviar ao cliente</option><option value="reception">Tablet da recepção</option></select></label>
			</div>

			{!availableQuestionnaires.length ? <p className="mt-3 rounded-xl bg-amber-50 p-3 text-xs font-semibold leading-5 text-amber-900">Nenhuma versão está ativa. O VITA-ACEA-1.0 permanece bloqueado até registrar a aprovação de um profissional habilitado.</p> : null}
			<PwaButton type="button" onClick={createInvitation} disabled={!questionnaireId || Boolean(busyAction)} loading={busyAction === 'create'} icon="share" className="mt-4 w-full">Criar link temporário</PwaButton>

			{invitation ? <div className="mt-4 rounded-2xl border border-teal-200 bg-teal-50/70 p-4"><p className="text-xs font-bold uppercase text-teal-800">Link exibido somente agora</p><p className="mt-2 break-all rounded-lg bg-white p-2 text-xs text-slate-700">{invitation.url}</p><p className="mt-2 text-xs text-slate-600">Expira em {formatDate(invitation.expires_at)}. O mesmo endereço é o conteúdo seguro para gerar o QR Code localmente.</p><div className="mt-3 grid gap-2 sm:grid-cols-2"><PwaButton type="button" onClick={copyLink} icon="copy" variant="secondary">Copiar link</PwaButton><PwaButton type="button" onClick={shareLink} icon="share">Compartilhar</PwaButton></div></div> : null}

			{sessions.length ? <div className="mt-5 border-t border-slate-100 pt-4"><h4 className="text-sm font-extrabold text-slate-800">Aplicações recentes</h4><div className="mt-2 space-y-2">{sessions.map((session) => <div key={session.id} className="rounded-xl border border-slate-200 p-3"><div className="flex flex-wrap items-center justify-between gap-2"><div><p className="text-sm font-bold text-slate-800">{session.questionnaire_name}</p><p className="text-xs text-slate-500">{session.access_mode === 'reception' ? 'Recepção' : 'Remoto'} · expira {formatDate(session.expires_at)}</p></div><span className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${session.revoked_at ? 'bg-slate-200 text-slate-700' : session.status === 'pending' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-800'}`}>{session.revoked_at ? 'Revogado' : session.status === 'pending' ? 'Pendente' : 'Iniciado'}</span></div>{session.status === 'pending' ? <div className="mt-3 flex flex-wrap gap-2"><PwaButton type="button" onClick={() => resend(session)} disabled={Boolean(busyAction)} loading={busyAction === `resend-${session.id}`} variant="secondary" icon="refresh" className="text-xs">Reenviar</PwaButton>{!session.revoked_at ? <PwaButton type="button" onClick={() => revoke(session)} disabled={Boolean(busyAction)} loading={busyAction === `revoke-${session.id}`} variant="danger" icon="minus" className="text-xs">Revogar</PwaButton> : null}</div> : null}</div>)}</div></div> : null}
		</PwaCard>
	);
}
