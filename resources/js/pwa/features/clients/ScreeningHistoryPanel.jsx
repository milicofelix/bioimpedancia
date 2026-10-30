import React, { useState } from 'react';
import { formatDate } from '../../../shared/formatters';
import PwaButton from '../../components/ui/PwaButton';
import ScreeningProfessionalDetails from './ScreeningProfessionalDetails';

const statusPresentation = (session) => {
	if (session.revoked_at) return { label: 'Revogado', classes: 'bg-slate-200 text-slate-700' };
	if (session.status === 'pending') return { label: 'Pendente', classes: 'bg-amber-100 text-amber-900' };
	if (session.status === 'finalized') return { label: 'Finalizado', classes: 'bg-emerald-100 text-emerald-800' };
	if (session.status === 'cancelled') return { label: 'Cancelado', classes: 'bg-rose-100 text-rose-800' };

	return { label: 'Iniciado', classes: 'bg-sky-100 text-sky-800' };
};

function Comparison({ comparison }) {
	if (!comparison) return null;

	const classes = comparison.state === 'incompatible_version'
		? 'border-amber-200 bg-amber-50 text-amber-950'
		: 'border-slate-200 bg-slate-50 text-slate-700';

	return <div className={`mt-3 rounded-xl border p-3 text-xs leading-5 ${classes}`}>
		<p className="font-extrabold">{comparison.state === 'comparable' ? 'Comparação com a aplicação anterior da mesma versão' : comparison.state === 'incompatible_version' ? 'Comparação indisponível entre versões' : 'Contexto longitudinal'}</p>
		{comparison.state === 'comparable' ? <p className="mt-1 font-semibold">{comparison.previous_score} → {comparison.current_score} pontos</p> : null}
		<p className="mt-1">{comparison.message}</p>
	</div>;
}

export default function ScreeningHistoryPanel({ sessions, busyAction, onNewApplication, onResend, onRevoke }) {
	const [selectedSessionId, setSelectedSessionId] = useState(null);

	return <div className="mt-4">
		<div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h4 className="font-extrabold text-slate-900">Histórico de triagens</h4><p className="text-xs leading-5 text-slate-600">Aplicações em ordem cronológica, sem interpretação automática de melhora ou piora clínica.</p></div><PwaButton type="button" onClick={onNewApplication} icon="plus" className="w-full shrink-0 sm:w-auto">Nova aplicação</PwaButton></div>

		{sessions.length ? <ol className="mt-4 space-y-3">{sessions.map((session) => {
			const status = statusPresentation(session);

			return <li key={session.id} className="rounded-2xl border border-slate-200 bg-white p-4">
				<div className="flex flex-wrap items-start justify-between gap-2"><div><p className="text-sm font-extrabold text-slate-900">{session.questionnaire_name}</p><p className="mt-1 text-xs text-slate-500">{session.questionnaire_version} · criada em {formatDate(session.created_at)}</p><p className="mt-1 text-xs text-slate-600">Solicitada por {session.requested_by_name || 'Profissional não informado'}{session.is_minor_at_application ? ' · menor com responsável legal' : ''}</p></div><span className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${status.classes}`}>{status.label}</span></div>

				{session.status === 'finalized' ? <div className="mt-3 rounded-xl bg-teal-50 p-3"><p className="text-sm font-extrabold text-teal-900">{session.questionnaire_code === 'VITA-TAA' ? 'Resultado visual descritivo' : `${session.total_score} pontos · ${session.result_description}`}</p><p className="mt-1 text-xs text-teal-800">Concluída em {formatDate(session.finalized_at)}</p></div> : null}
				<Comparison comparison={session.comparison} />

				{session.status === 'pending' ? <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3"><p className="text-[11px] font-extrabold uppercase tracking-wide text-slate-500">Ações do convite</p><p className="mt-2 text-xs font-semibold leading-5 text-slate-700">{session.revoked_at ? 'Este acesso foi revogado. Você pode gerar um novo link para retomar o preenchimento.' : `Aguardando preenchimento. O link atual expira em ${formatDate(session.expires_at)}.`}</p><p className="mt-1 text-[11px] leading-5 text-slate-500">Link gerado {session.send_count || 1} vez(es). Gerar novamente invalida imediatamente o link anterior; nenhuma mensagem é enviada automaticamente.</p><div className="mt-3 grid gap-2 sm:flex sm:flex-wrap"><PwaButton type="button" onClick={() => onResend(session)} disabled={Boolean(busyAction)} loading={busyAction === `resend-${session.id}`} variant="secondary" icon="refresh" className="w-full text-xs sm:w-auto">{session.revoked_at ? 'Criar novo acesso' : 'Gerar novo link'}</PwaButton>{!session.revoked_at ? <PwaButton type="button" onClick={() => onRevoke(session)} disabled={Boolean(busyAction)} loading={busyAction === `revoke-${session.id}`} variant="danger" icon="minus" className="w-full text-xs sm:w-auto">Revogar acesso</PwaButton> : null}</div></div> : null}

				{session.status === 'finalized' ? <div className="mt-3 rounded-xl border border-teal-100 bg-teal-50/40 p-3"><p className="text-[11px] font-extrabold uppercase tracking-wide text-teal-800">Resultado e documentos</p><div className="mt-2 grid gap-2 sm:grid-cols-2"><PwaButton type="button" onClick={() => setSelectedSessionId(session.id)} variant="secondary" icon="fileText" className="w-full text-xs">Abrir resultado</PwaButton><a href={`/screening/sessions/${session.id}/client-pdf`} target="_blank" rel="noopener noreferrer" className="pwa-focus inline-flex min-h-11 items-center justify-center rounded-xl bg-teal-700 px-4 text-xs font-bold text-white hover:bg-teal-800">Abrir PDF da triagem</a></div></div> : null}
			</li>;
		})}</ol> : <div className="mt-4 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center"><p className="font-bold text-slate-700">Nenhuma triagem registrada.</p><p className="mt-1 text-sm text-slate-500">Crie a primeira aplicação para iniciar a linha de base.</p></div>}

		{selectedSessionId ? <ScreeningProfessionalDetails sessionId={selectedSessionId} onClose={() => setSelectedSessionId(null)} /> : null}
	</div>;
}
