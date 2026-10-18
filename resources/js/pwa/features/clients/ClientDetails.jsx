import React from 'react';
import { formatDate, formatIssueDate, numberBr } from '../../../shared/formatters';
import { cpfMask } from '../../../shared/inputMasks';
import { assessmentAccess, canUseClinicalFeatures } from './clientAccess';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';

const sexLabels = { female: 'Feminino', male: 'Masculino' };

function initials(name) {
	return String(name ?? '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

export default function ClientDetails({ client, currentUser, scaleModel, onBack, onNewAssessment, onOpenAssistant, onOpenReport }) {
	const access = assessmentAccess(client, currentUser, scaleModel);
	const assessments = [...(client.assessments ?? [])].sort((left, right) => new Date(right.evaluated_at) - new Date(left.evaluated_at));

	return (
		<section aria-labelledby="client-details-title">
			<PwaButton type="button" onClick={onBack} variant="ghost" icon="arrowLeft" className="-ml-3">Voltar para clientes</PwaButton>

			<PwaCard className="mt-3 p-5 sm:p-6">
				<div className="flex items-start justify-between gap-3">
					<div className="flex min-w-0 items-center gap-3">
						<span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-ricosty-100 to-ricosty-200 text-lg font-extrabold text-ricosty-800 ring-1 ring-ricosty-200">{initials(client.full_name)}</span>
						<div className="min-w-0"><p className="text-xs font-bold uppercase tracking-[0.14em] text-ricosty-600">Cliente</p>
						<h2 id="client-details-title" className="mt-1 truncate text-xl font-extrabold text-ricosty-ink">{client.full_name}</h2></div>
					</div>
					{client.is_anonymized ? (
						<span className="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase text-amber-700">Anonimizado</span>
					) : !client.is_active ? (
						<span className="rounded-full bg-slate-200 px-2 py-1 text-[10px] font-bold uppercase text-slate-600">Inativo</span>
					) : (
						<span className="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold uppercase text-emerald-700">Ativo</span>
					)}
				</div>

				<dl className="mt-5 grid grid-cols-2 gap-2 text-sm">
					{[['Idade', `${client.age ?? '-'} anos`], ['Sexo', sexLabels[client.biological_sex] ?? '-'], ['Altura', `${numberBr(client.height_cm, 0)} cm`], ['Nascimento', formatIssueDate(client.birth_date)]].map(([label, value]) => <div key={label} className="rounded-xl bg-ricosty-cream/70 p-3"><dt className="text-[10px] font-bold uppercase tracking-wide text-slate-400">{label}</dt><dd className="mt-1 font-bold text-ricosty-ink">{value}</dd></div>)}
				</dl>

				<div className="mt-4 space-y-2 border-t border-ricosty-100 pt-4 text-sm text-slate-600">
					<p className="flex items-center gap-2"><PwaIcon name="phone" size={16} className="text-ricosty-500" /><strong className="sr-only">Telefone:</strong> {client.phone || '-'}</p>
					<p className="flex items-center gap-2"><PwaIcon name="mail" size={16} className="text-ricosty-500" /><strong className="sr-only">E-mail:</strong> {client.email || '-'}</p>
					{client.cpf ? <p><strong>CPF:</strong> {cpfMask(client.cpf)}</p> : null}
					<p><strong>Próxima avaliação:</strong> {formatIssueDate(client.next_assessment_at)}</p>
				</div>

				<div className="mt-5 rounded-2xl bg-gradient-to-br from-ricosty-50 to-ricosty-cream p-3 ring-1 ring-ricosty-100">
					<div className="mb-3 flex items-center gap-2 px-1 text-xs text-ricosty-800"><PwaIcon name="clipboard" size={16} /><span><strong>Pronto para atender?</strong> Inicie o fluxo Relaxmedic.</span></div>
					<PwaButton type="button" disabled={!access.allowed} onClick={() => onNewAssessment(client)} icon="plus" className="w-full text-base">Nova avaliação</PwaButton>
				</div>
				{access.reason ? <p className="mt-2 text-center text-xs font-semibold text-amber-700">{access.reason}</p> : null}
			</PwaCard>

			<PwaCard className="mt-4 p-5">
				<div className="flex items-center justify-between gap-3">
					<h3 className="flex items-center gap-2 font-extrabold text-ricosty-ink"><PwaIcon name="history" className="text-ricosty-600" />Histórico resumido</h3>
					<span className="text-xs text-slate-500">{assessments.length} avaliação(ões)</span>
				</div>

				<ol className="relative mt-4 space-y-3 before:absolute before:bottom-4 before:left-[9px] before:top-4 before:w-px before:bg-ricosty-200">
					{assessments.slice(0, 5).map((assessment, index) => (
						<li key={assessment.id}>
							<div className={`relative ml-5 rounded-2xl border p-4 ${assessment.is_canceled ? 'border-rose-100 bg-rose-50/50' : 'border-ricosty-100 bg-ricosty-cream/45'}`}>
								<span className={`absolute -left-[23px] top-5 flex h-4 w-4 items-center justify-center rounded-full ring-4 ring-white ${assessment.is_canceled ? 'bg-rose-400' : index === 0 ? 'bg-ricosty-600' : 'bg-ricosty-300'}`}><span className="h-1.5 w-1.5 rounded-full bg-white" /></span>
								<div className="flex items-center justify-between gap-3">
									<span className="flex items-center gap-1.5 text-sm font-bold text-slate-900"><PwaIcon name="calendar" size={15} className="text-ricosty-500" />{formatDate(assessment.evaluated_at)}</span>
									<span className="text-[10px] font-bold uppercase text-slate-500">{assessment.is_canceled ? 'Cancelada' : assessment.device_model}</span>
								</div>
								<div className="mt-3 grid grid-cols-2 gap-2"><div className="rounded-lg bg-white/80 px-3 py-2"><span className="block text-[10px] font-bold uppercase text-slate-400">Peso</span><strong className="text-sm text-slate-800">{numberBr(assessment.weight_kg, 1)} kg</strong></div><div className="rounded-lg bg-white/80 px-3 py-2"><span className="block text-[10px] font-bold uppercase text-slate-400">IMC</span><strong className="text-sm text-slate-800">{numberBr(assessment.calculated_bmi ?? assessment.scale_bmi, 1)}</strong></div></div>
								<div className="mt-3 flex flex-wrap gap-2">
									<PwaButton type="button" onClick={() => onOpenReport(client, assessment)} variant="secondary" icon="fileText" className="min-h-9 px-3 text-xs">Relatório</PwaButton>
									{canUseClinicalFeatures(currentUser) ? <PwaButton type="button" onClick={() => onOpenAssistant(client, assessment)} variant="ghost" icon="sparkles" className="min-h-9 px-3 text-xs">Assistente</PwaButton> : null}
								</div>
							</div>
						</li>
					))}
					{!assessments.length ? <li className="rounded-xl border border-dashed border-slate-200 p-4 text-center text-sm text-slate-500">Nenhuma avaliação registrada.</li> : null}
				</ol>
			</PwaCard>
		</section>
	);
}
