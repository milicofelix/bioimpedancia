import React, { useEffect, useRef, useState } from 'react';
import { formatDate } from '../../../shared/formatters';
import { apiErrorMessage, createAssessmentShare, revokeAssessmentShare } from '../../../services/bioimpedanceApi';
import useOnlineStatus from '../../hooks/useOnlineStatus';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';

function shareStatus(share) {
	if (share.revoked_at) return { label: 'Revogado', icon: 'minus', badge: 'bg-slate-200 text-slate-700', card: 'border-slate-200 bg-slate-50' };
	if (!share.is_active || new Date(share.expires_at).getTime() <= Date.now()) return { label: 'Expirado', icon: 'clock', badge: 'bg-amber-100 text-amber-800', card: 'border-amber-200 bg-amber-50/40' };
	return { label: 'Ativo', icon: 'check', badge: 'bg-emerald-100 text-emerald-800', card: 'border-emerald-200 bg-emerald-50/40' };
}

export default function ReportSharing({ assessment, canCreate, canRevoke, onAssessmentUpdated }) {
	const [expiresInDays, setExpiresInDays] = useState('7');
	const [creating, setCreating] = useState(false);
	const [revokingId, setRevokingId] = useState(null);
	const [shareResult, setShareResult] = useState(null);
	const [feedback, setFeedback] = useState('');
	const [error, setError] = useState('');
	const [pendingShareAction, setPendingShareAction] = useState(null);
	const cancelShareReference = useRef(null);
	const isOnline = useOnlineStatus();

	useEffect(() => {
		if (!pendingShareAction) return undefined;
		cancelShareReference.current?.focus();
		const closeOnEscape = (event) => {
			if (event.key === 'Escape') setPendingShareAction(null);
		};
		window.addEventListener('keydown', closeOnEscape);
		return () => window.removeEventListener('keydown', closeOnEscape);
	}, [pendingShareAction]);

	async function createLink() {
		if (!canCreate || creating || !isOnline) return;
		setCreating(true);
		setError('');
		setFeedback('');

		try {
			const result = await createAssessmentShare(assessment.id, {
				channel: 'copy',
				recipient: '',
				expires_in_days: Number(expiresInDays),
			});
			setShareResult(result.share);
			onAssessmentUpdated(result.assessment);
			setFeedback(`Link criado. Expira em ${formatDate(result.share.expires_at)}.`);
		} catch (requestError) {
			setError(apiErrorMessage(requestError, 'Não foi possível criar o link temporário.'));
		} finally {
			setCreating(false);
		}
	}

	async function copyLink() {
		if (!shareResult?.url) return;
		try {
			await navigator.clipboard.writeText(shareResult.url);
			setFeedback('Link copiado para a área de transferência.');
			setError('');
		} catch {
			setError('O navegador não permitiu copiar automaticamente. Toque e segure o link para copiá-lo.');
		}
	}

	async function nativeShare() {
		if (!shareResult?.url) return;
		if (!navigator.share) {
			await copyLink();
			return;
		}

		try {
			await navigator.share({ title: 'Relatório de bioimpedância', text: shareResult.message });
			setFeedback('Compartilhamento aberto no dispositivo.');
		} catch (shareError) {
			if (shareError?.name !== 'AbortError') await copyLink();
		}
	}

	async function confirmShare() {
		const action = pendingShareAction;
		setPendingShareAction(null);
		if (action === 'native') await nativeShare();
		if (action === 'copy') await copyLink();
	}

	async function revoke(share) {
		if (!canRevoke || !share.is_active || revokingId || !isOnline) return;
		setRevokingId(share.id);
		setError('');

		try {
			const result = await revokeAssessmentShare(share.id);
			onAssessmentUpdated(result.assessment);
			if (shareResult?.id === share.id) setShareResult(null);
			setFeedback('Link revogado. Ele não permite mais acesso ao relatório.');
		} catch (requestError) {
			setError(apiErrorMessage(requestError, 'Não foi possível revogar o link.'));
		} finally {
			setRevokingId(null);
		}
	}

	return (
		<PwaCard className="p-5">
			<div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
				<div className="flex items-start gap-3"><PwaIcon name="shield" className="mt-0.5 shrink-0" /><p><strong>Dados sensíveis:</strong> o link dá acesso ao relatório clínico do cliente. Compartilhe somente com a pessoa correta e revogue quando não for mais necessário.</p></div>
			</div>

			<h2 className="mt-5 text-lg font-bold text-slate-950">Compartilhamento temporário</h2>
			{!isOnline ? <p role="alert" className="mt-3 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">Sem conexão. Novos links e revogações ficam bloqueados até a internet voltar.</p> : null}
			<div className="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]">
				<label className="text-sm font-semibold text-slate-700">Validade do link<select value={expiresInDays} onChange={(event) => setExpiresInDays(event.target.value)} disabled={!canCreate || creating || !isOnline} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-3"><option value="1">1 dia</option><option value="3">3 dias</option><option value="7">7 dias</option><option value="15">15 dias</option><option value="30">30 dias</option></select></label>
				<PwaButton type="button" onClick={createLink} disabled={!canCreate || creating || !isOnline} icon="share" className="self-end">{creating ? 'Criando...' : 'Criar link temporário'}</PwaButton>
			</div>
			{!canCreate ? <p className="mt-2 text-xs font-semibold text-amber-700">Seu perfil não pode criar links de compartilhamento.</p> : null}

			{shareResult ? <div className="mt-4 rounded-2xl border border-ricosty-200 bg-ricosty-50 p-4"><div className="flex items-center justify-between gap-2"><span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold uppercase text-emerald-800"><PwaIcon name="check" size={12} />Ativo</span><p className="flex items-center gap-1 text-xs font-bold text-ricosty-800"><PwaIcon name="clock" size={14} />Expira em {formatDate(shareResult.expires_at)}</p></div><p className="mt-3 break-all rounded-lg bg-white/80 p-2 text-xs text-ricosty-900">{shareResult.url}</p><div className="mt-3 grid gap-2 sm:grid-cols-2"><PwaButton type="button" onClick={() => setPendingShareAction('native')} icon="share" className="min-h-10 text-xs">Compartilhar no dispositivo</PwaButton><PwaButton type="button" onClick={() => setPendingShareAction('copy')} icon="copy" variant="secondary" className="min-h-10 text-xs">Copiar link</PwaButton></div></div> : null}
			{feedback ? <p role="status" className="mt-3 text-sm font-semibold text-emerald-700">{feedback}</p> : null}
			{error ? <p role="alert" className="mt-3 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">{error}</p> : null}

			{assessment.shares?.length ? <div className="mt-5"><div className="flex items-center justify-between"><h3 className="text-sm font-bold text-slate-900">Links criados</h3><span className="text-xs text-slate-400">{assessment.shares.length} no total</span></div><div className="mt-2 space-y-2">{assessment.shares.map((share) => {
				const status = shareStatus(share);
				return <div key={share.id} className={`rounded-xl border p-3 ${status.card}`}><div className="flex items-start justify-between gap-3"><div className="min-w-0"><span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-bold uppercase ${status.badge}`}><PwaIcon name={status.icon} size={12} />{status.label}</span><p className="mt-2 text-xs font-bold text-slate-700">Expira em {formatDate(share.expires_at)}</p><p className="mt-1 text-xs text-slate-500">{share.view_count} visualização(ões){share.revoked_at ? ` · revogado em ${formatDate(share.revoked_at)}` : ''}</p></div>{status.label === 'Ativo' && canRevoke ? <button type="button" onClick={() => revoke(share)} disabled={revokingId === share.id || !isOnline} className="rounded-lg border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-700 disabled:opacity-50">{revokingId === share.id ? 'Revogando...' : 'Revogar'}</button> : null}</div></div>;
			})}</div></div> : null}

			{pendingShareAction ? (
				<div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/45 p-4 backdrop-blur-sm sm:items-center" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setPendingShareAction(null); }}>
					<div role="dialog" aria-modal="true" aria-labelledby="confirm-share-title" aria-describedby="confirm-share-description" className="pwa-screen-enter w-full max-w-sm rounded-[1.5rem] bg-white p-5 shadow-2xl">
						<div className="mx-auto h-1 w-12 rounded-full bg-slate-200 sm:hidden" aria-hidden="true" />
						<div className="mt-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-800"><PwaIcon name="shield" /></div>
						<h2 id="confirm-share-title" className="mt-4 text-lg font-bold text-slate-950">Confirmar compartilhamento</h2>
						<p id="confirm-share-description" className="mt-2 text-sm leading-6 text-slate-600">Este link contém dados clínicos sensíveis. Confirme a identidade do destinatário e evite enviar para grupos ou aparelhos compartilhados.</p>
						<div className="mt-4 rounded-xl bg-ricosty-50 p-3 text-xs text-ricosty-900"><strong>Validade:</strong> até {formatDate(shareResult?.expires_at)}</div>
						<div className="mt-5 grid grid-cols-2 gap-3"><PwaButton ref={cancelShareReference} type="button" variant="secondary" onClick={() => setPendingShareAction(null)}>Cancelar</PwaButton><PwaButton type="button" icon={pendingShareAction === 'copy' ? 'copy' : 'share'} onClick={confirmShare}>Confirmar</PwaButton></div>
					</div>
				</div>
			) : null}
		</PwaCard>
	);
}
