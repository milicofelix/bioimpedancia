import React, { useState } from 'react';
import { formatDate } from '../../../shared/formatters';
import { apiErrorMessage, createAssessmentShare, revokeAssessmentShare } from '../../../services/bioimpedanceApi';

export default function ReportSharing({ assessment, canCreate, canRevoke, onAssessmentUpdated }) {
	const [expiresInDays, setExpiresInDays] = useState('7');
	const [creating, setCreating] = useState(false);
	const [revokingId, setRevokingId] = useState(null);
	const [shareResult, setShareResult] = useState(null);
	const [feedback, setFeedback] = useState('');
	const [error, setError] = useState('');

	async function createLink() {
		if (!canCreate || creating) return;
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

	async function revoke(share) {
		if (!canRevoke || !share.is_active || revokingId) return;
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
		<section className="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
			<div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
				<strong>Dados sensíveis:</strong> o link dá acesso ao relatório clínico do cliente. Compartilhe somente com a pessoa correta e revogue quando não for mais necessário.
			</div>

			<h2 className="mt-5 text-lg font-bold text-slate-950">Compartilhamento temporário</h2>
			<div className="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]">
				<label className="text-sm font-semibold text-slate-700">Validade do link<select value={expiresInDays} onChange={(event) => setExpiresInDays(event.target.value)} disabled={!canCreate || creating} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-3"><option value="1">1 dia</option><option value="3">3 dias</option><option value="7">7 dias</option><option value="15">15 dias</option><option value="30">30 dias</option></select></label>
				<button type="button" onClick={createLink} disabled={!canCreate || creating} className="self-end rounded-xl bg-sky-600 px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">{creating ? 'Criando...' : 'Criar link temporário'}</button>
			</div>
			{!canCreate ? <p className="mt-2 text-xs font-semibold text-amber-700">Seu perfil não pode criar links de compartilhamento.</p> : null}

			{shareResult ? <div className="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4"><p className="break-all text-xs text-sky-900">{shareResult.url}</p><p className="mt-2 text-xs font-bold text-sky-800">Expira em {formatDate(shareResult.expires_at)}</p><div className="mt-3 grid gap-2 sm:grid-cols-2"><button type="button" onClick={nativeShare} className="rounded-lg bg-sky-700 px-3 py-2 text-xs font-bold text-white">Compartilhar no dispositivo</button><button type="button" onClick={copyLink} className="rounded-lg border border-sky-300 bg-white px-3 py-2 text-xs font-bold text-sky-800">Copiar link</button></div></div> : null}
			{feedback ? <p role="status" className="mt-3 text-sm font-semibold text-emerald-700">{feedback}</p> : null}
			{error ? <p role="alert" className="mt-3 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-700">{error}</p> : null}

			{assessment.shares?.length ? <div className="mt-5"><h3 className="text-sm font-bold text-slate-900">Links criados</h3><div className="mt-2 space-y-2">{assessment.shares.map((share) => <div key={share.id} className="rounded-xl border border-slate-200 p-3"><div className="flex items-start justify-between gap-3"><div><p className="text-xs font-bold text-slate-700">Expira em {formatDate(share.expires_at)}</p><p className="mt-1 text-xs text-slate-500">{share.is_active ? 'Ativo' : 'Revogado ou expirado'} · {share.view_count} visualização(ões)</p></div>{share.is_active && canRevoke ? <button type="button" onClick={() => revoke(share)} disabled={revokingId === share.id} className="rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 disabled:opacity-50">{revokingId === share.id ? 'Revogando...' : 'Revogar'}</button> : null}</div></div>)}</div></div> : null}
		</section>
	);
}
