import React, { useEffect, useRef, useState } from 'react';
import { formatDate } from '../../../shared/formatters';
import { apiErrorMessage, createAssessmentShare, revokeAssessmentShare } from '../../../services/bioimpedanceApi';
import useOnlineStatus from '../../hooks/useOnlineStatus';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';
import PwaToast from '../../components/ui/PwaToast';
import PwaConfirmSheet from '../../components/ui/PwaConfirmSheet';
import ShareLinkSkeleton from './ShareLinkSkeleton';
import useActionLock from '../../../hooks/useActionLock';
import PwaAlert from '../../components/ui/PwaAlert';
import PwaSectionHeader from '../../components/ui/PwaSectionHeader';
import PwaState from '../../components/ui/PwaState';

function shareStatus(share) {
	if (share.revoked_at) return { label: 'Revogado', icon: 'minus', badge: 'bg-slate-200 text-slate-700', card: 'border-slate-200 bg-slate-50' };
	if (!share.is_active || new Date(share.expires_at).getTime() <= Date.now()) return { label: 'Expirado', icon: 'clock', badge: 'bg-amber-100 text-amber-800', card: 'border-amber-200 bg-amber-50/40' };
	return { label: 'Ativo', icon: 'check', badge: 'bg-emerald-100 text-emerald-800', card: 'border-emerald-200 bg-emerald-50/40' };
}

export default function ReportSharing({ assessment, canCreate, canRevoke, onAssessmentUpdated }) {
	const runAction = useActionLock();
	const [expiresInDays, setExpiresInDays] = useState('7');
	const [creating, setCreating] = useState(false);
	const [revokingId, setRevokingId] = useState(null);
	const [shareResult, setShareResult] = useState(null);
	const [feedback, setFeedback] = useState('');
	const [error, setError] = useState('');
	const [pendingShareAction, setPendingShareAction] = useState(null);
	const [pendingRevoke, setPendingRevoke] = useState(null);
	const [copied, setCopied] = useState(false);
	const copyFeedbackTimeout = useRef(null);
	const isOnline = useOnlineStatus();

	useEffect(() => () => window.clearTimeout(copyFeedbackTimeout.current), []);

	async function createLink() {
		return runAction('create-share-link', async () => {
			if (!canCreate || creating || !isOnline) return;
			setCreating(true);
			setError('');
			setFeedback('');
			setCopied(false);
			setShareResult(null);

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
		});
	}

	async function copyLink() {
		if (!shareResult?.url) return;
		setFeedback('');
		setError('');
		try {
			await navigator.clipboard.writeText(shareResult.url);
			setFeedback('Link copiado para a área de transferência.');
			setError('');
			setCopied(true);
			window.clearTimeout(copyFeedbackTimeout.current);
			copyFeedbackTimeout.current = window.setTimeout(() => setCopied(false), 2500);
		} catch {
			setCopied(false);
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
		return runAction(`revoke-share-${share.id}`, async () => {
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
		});
	}

	async function confirmRevoke() {
		const share = pendingRevoke;
		setPendingRevoke(null);
		if (share) await revoke(share);
	}

	return (
		<PwaCard className="p-5">
			<PwaToast message={error || feedback} tone={error ? 'error' : 'success'} onDismiss={() => { setError(''); setFeedback(''); }} />
			<PwaSectionHeader icon="share" eyebrow="Compartilhamento" title="Acesso temporário ao relatório" description="Crie um link com prazo definido para enviar ao cliente." tone="blue" />
			<PwaAlert tone="attention" title="Proteja os dados do cliente" className="mt-4">Confirme o destinatário antes de enviar e revogue o link quando ele não for mais necessário.</PwaAlert>
			{!isOnline ? <PwaAlert tone="info" title="Sem conexão" className="mt-3">A criação e a revogação de links estarão disponíveis quando a conexão for restabelecida.</PwaAlert> : null}

			<div className="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]">
				<label className="text-sm font-semibold text-slate-700">Validade do link<select value={expiresInDays} onChange={(event) => setExpiresInDays(event.target.value)} disabled={!canCreate || creating || !isOnline} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-3"><option value="1">1 dia</option><option value="3">3 dias</option><option value="7">7 dias</option><option value="15">15 dias</option><option value="30">30 dias</option></select></label>
				<PwaButton type="button" onClick={createLink} disabled={!canCreate || creating || !isOnline} loading={creating} icon="share" className="self-end">{creating ? 'Criando...' : 'Criar link temporário'}</PwaButton>
			</div>
			{!canCreate ? <PwaAlert tone="info" title="Criação indisponível para este perfil" className="mt-3">Você ainda pode consultar a situação dos links já criados.</PwaAlert> : null}
			{creating ? <ShareLinkSkeleton /> : null}

			{shareResult ? <div className={`mt-4 rounded-2xl border p-4 transition ${copied ? 'border-emerald-300 bg-emerald-50 ring-2 ring-emerald-100' : 'border-ricosty-200 bg-ricosty-50'}`}><div className="flex flex-wrap items-center justify-between gap-2"><span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-bold uppercase text-emerald-900"><PwaIcon name="check" size={12} />Ativo</span><p className="flex items-center gap-1 text-xs font-bold text-ricosty-900"><PwaIcon name="clock" size={14} />Expira em {formatDate(shareResult.expires_at)}</p></div><p className="mt-3 break-all rounded-lg bg-white/80 p-2 text-sm text-ricosty-950">{shareResult.url}</p><div className="mt-3 grid gap-2 sm:grid-cols-2"><PwaButton type="button" onClick={() => setPendingShareAction('native')} icon="share" className="text-xs">Compartilhar no dispositivo</PwaButton><PwaButton type="button" onClick={() => setPendingShareAction('copy')} icon={copied ? 'check' : 'copy'} variant="secondary" className={`text-xs ${copied ? 'border-emerald-300 text-emerald-800' : ''}`}>{copied ? 'Link copiado!' : 'Copiar link'}</PwaButton></div></div> : null}

			{assessment.shares?.length ? <div className="mt-5"><div className="flex items-center justify-between"><h3 className="flex items-center gap-2 text-sm font-bold text-slate-900"><PwaIcon name="history" size={16} />Histórico de links</h3><span className="text-xs text-slate-600">{assessment.shares.length} no total</span></div><div className="mt-2 space-y-2">{assessment.shares.map((share) => {
				const status = shareStatus(share);
				return <div key={share.id} className={`rounded-xl border p-3 ${status.card}`}><div className="flex flex-wrap items-start justify-between gap-3"><div className="min-w-0"><span className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-[11px] font-bold uppercase ${status.badge}`}><PwaIcon name={status.icon} size={12} />{status.label}</span><p className="mt-2 text-xs font-bold text-slate-800">Expira em {formatDate(share.expires_at)}</p><p className="mt-1 text-xs text-slate-600">{share.view_count} visualização(ões){share.revoked_at ? ` · revogado em ${formatDate(share.revoked_at)}` : ''}</p></div>{status.label === 'Ativo' && canRevoke ? <button type="button" aria-label={`Revogar link que expira em ${formatDate(share.expires_at)}`} onClick={() => setPendingRevoke(share)} disabled={revokingId === share.id || !isOnline} className="pwa-focus min-h-11 min-w-11 rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-bold text-rose-800 disabled:opacity-50">{revokingId === share.id ? 'Revogando...' : 'Revogar'}</button> : null}</div></div>;
			})}</div></div> : !creating && !shareResult ? <PwaState icon="share" title="Nenhum link criado" description="Quando um link for criado, seu prazo e estado aparecerão aqui." className="mt-5 p-4" /> : null}

			<PwaConfirmSheet open={Boolean(pendingShareAction)} title="Confirmar compartilhamento" description="Este link contém dados clínicos sensíveis. Confirme a identidade do destinatário e evite enviar para grupos ou aparelhos compartilhados." detail={<><strong>Validade:</strong> até {formatDate(shareResult?.expires_at)}</>} confirmIcon={pendingShareAction === 'copy' ? 'copy' : 'share'} onCancel={() => setPendingShareAction(null)} onConfirm={confirmShare} />
			<PwaConfirmSheet open={Boolean(pendingRevoke)} title="Revogar este link?" description="O cliente perderá o acesso imediatamente. Esta ação não pode ser desfeita; se necessário, será preciso criar um novo link." icon="alertTriangle" tone="danger" confirmLabel="Revogar link" confirmVariant="danger" onCancel={() => setPendingRevoke(null)} onConfirm={confirmRevoke} />
		</PwaCard>
	);
}
