import React from 'react';

const categoryLabels = {
	assessment_context: 'Contexto da avaliação',
	nutrition_education: 'Educação alimentar',
	movement_education: 'Movimento e função',
	professional_follow_up: 'Acompanhamento profissional',
	safety_limit: 'Limites de segurança',
};

const confidenceLabels = {
	high: 'Segurança alta',
	moderate: 'Segurança moderada',
	low: 'Segurança baixa',
};

export default function CareGuidanceEvidence({ assistant }) {
	const sourcesById = Object.fromEntries((assistant.sources ?? []).map((source) => [source.id, source]));

	return (
		<div className="space-y-4">
			<div className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">
				<p><strong>Provedor utilizado:</strong> {assistant.provider === 'openai' ? 'OpenAI' : 'Motor local de referências'}</p>
				{assistant.fallback_used ? <p className="mt-1 text-amber-800"><strong>Fallback local:</strong> {assistant.fallback_reason}</p> : null}
			</div>

			<div>
				<h3 className="text-xs font-bold uppercase text-slate-600">Afirmações validadas</h3>
				<div className="mt-2 space-y-2">
					{(assistant.claims ?? []).map((claim) => (
						<details key={claim.id} className="rounded-xl border border-teal-100 bg-teal-50/50 p-3 text-xs" open>
							<summary className="cursor-pointer font-bold text-teal-950">{categoryLabels[claim.category] ?? claim.category}</summary>
							<p className="mt-2 leading-5 text-slate-700">{claim.text}</p>
							<p className="mt-2 font-semibold text-slate-600">{confidenceLabels[claim.confidence] ?? claim.confidence} · Revisão profissional obrigatória</p>
							<ul className="mt-2 space-y-1 text-slate-600">
								{claim.source_ids.map((sourceId) => {
									const source = sourcesById[sourceId];
									return <li key={sourceId}>Fonte: {source ? `${source.document} — ${source.section}` : internalSourceLabel(sourceId)}</li>;
								})}
							</ul>
						</details>
					))}
				</div>
			</div>

			{assistant.blocked_claims?.length ? (
				<details className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900">
					<summary className="cursor-pointer font-bold">{assistant.blocked_claims.length} afirmação(ões) bloqueada(s)</summary>
					<ul className="mt-2 space-y-2">{assistant.blocked_claims.map((claim, index) => <li key={`${claim.id}-${index}`}><strong>{claim.text || 'Afirmação sem texto válido'}</strong><br />{claim.blocked_reasons?.join(' ')}</li>)}</ul>
				</details>
			) : null}
		</div>
	);
}

function internalSourceLabel(sourceId) {
	return {
		'assessment:current': 'Dados da avaliação atual',
		'assessment:history': 'Histórico de avaliações',
		'observation:approved': 'Observação aprovada pelo profissional',
	}[sourceId] ?? sourceId;
}
