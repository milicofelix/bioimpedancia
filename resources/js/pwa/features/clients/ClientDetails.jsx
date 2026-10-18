import React from 'react';
import { formatDate, formatIssueDate, numberBr } from '../../../shared/formatters';
import { cpfMask } from '../../../shared/inputMasks';
import { assessmentAccess } from './clientAccess';

const sexLabels = { female: 'Feminino', male: 'Masculino' };

export default function ClientDetails({ client, currentUser, scaleModel, onBack, onNewAssessment }) {
	const access = assessmentAccess(client, currentUser, scaleModel);
	const assessments = client.assessments ?? [];

	return (
		<section aria-labelledby="client-details-title">
			<button type="button" onClick={onBack} className="text-sm font-bold text-emerald-700">← Voltar para clientes</button>

			<div className="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
				<div className="flex items-start justify-between gap-3">
					<div>
						<p className="text-xs font-bold uppercase tracking-wide text-emerald-700">Cliente</p>
						<h2 id="client-details-title" className="mt-1 text-xl font-bold text-slate-950">{client.full_name}</h2>
					</div>
					{client.is_anonymized ? (
						<span className="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase text-amber-700">Anonimizado</span>
					) : !client.is_active ? (
						<span className="rounded-full bg-slate-200 px-2 py-1 text-[10px] font-bold uppercase text-slate-600">Inativo</span>
					) : (
						<span className="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-bold uppercase text-emerald-700">Ativo</span>
					)}
				</div>

				<dl className="mt-5 grid grid-cols-2 gap-3 text-sm">
					<div><dt className="text-xs font-bold uppercase text-slate-400">Idade</dt><dd className="mt-1 font-semibold text-slate-800">{client.age ?? '-'} anos</dd></div>
					<div><dt className="text-xs font-bold uppercase text-slate-400">Sexo</dt><dd className="mt-1 font-semibold text-slate-800">{sexLabels[client.biological_sex] ?? '-'}</dd></div>
					<div><dt className="text-xs font-bold uppercase text-slate-400">Altura</dt><dd className="mt-1 font-semibold text-slate-800">{numberBr(client.height_cm, 0)} cm</dd></div>
					<div><dt className="text-xs font-bold uppercase text-slate-400">Nascimento</dt><dd className="mt-1 font-semibold text-slate-800">{formatIssueDate(client.birth_date)}</dd></div>
				</dl>

				<div className="mt-4 space-y-1 border-t border-slate-100 pt-4 text-sm text-slate-600">
					<p><strong>Telefone:</strong> {client.phone || '-'}</p>
					<p><strong>E-mail:</strong> {client.email || '-'}</p>
					{client.cpf ? <p><strong>CPF:</strong> {cpfMask(client.cpf)}</p> : null}
					<p><strong>Próxima avaliação:</strong> {formatIssueDate(client.next_assessment_at)}</p>
				</div>

				<button type="button" disabled={!access.allowed} onClick={() => onNewAssessment(client)} className="mt-5 min-h-12 w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:bg-slate-300">
					Nova avaliação
				</button>
				{access.reason ? <p className="mt-2 text-center text-xs font-semibold text-amber-700">{access.reason}</p> : null}
			</div>

			<div className="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
				<div className="flex items-center justify-between gap-3">
					<h3 className="font-bold text-slate-950">Histórico resumido</h3>
					<span className="text-xs text-slate-500">{assessments.length} avaliação(ões)</span>
				</div>

				<div className="mt-3 space-y-2">
					{assessments.slice(0, 5).map((assessment) => (
						<div key={assessment.id} className={`rounded-xl border p-3 ${assessment.is_canceled ? 'border-rose-100 bg-rose-50/50' : 'border-slate-100 bg-slate-50'}`}>
							<div className="flex items-center justify-between gap-3">
								<span className="text-sm font-bold text-slate-900">{formatDate(assessment.evaluated_at)}</span>
								<span className="text-[10px] font-bold uppercase text-slate-500">{assessment.is_canceled ? 'Cancelada' : assessment.device_model}</span>
							</div>
							<p className="mt-1 text-xs text-slate-600">Peso {numberBr(assessment.weight_kg, 1)} kg • IMC {numberBr(assessment.calculated_bmi, 1)}</p>
						</div>
					))}
					{!assessments.length ? <p className="rounded-xl border border-dashed border-slate-200 p-4 text-center text-sm text-slate-500">Nenhuma avaliação registrada.</p> : null}
				</div>
			</div>
		</section>
	);
}
