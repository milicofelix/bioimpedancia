import React from 'react';
import PwaAlert from '../../components/ui/PwaAlert';
import PwaIcon from '../../components/ui/PwaIcon';

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
			<div>
				<h3 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-600"><PwaIcon name="fileText" size={15} />Fontes e critérios utilizados</h3>
				<div className="mt-2 space-y-2">
					{(assistant.claims ?? []).map((claim) => (
						<details key={claim.id} className="rounded-xl border border-teal-100 bg-teal-50/50 p-3 text-xs" open>
							<summary className="cursor-pointer font-bold text-teal-950">{categoryLabels[claim.category] ?? claim.category}</summary>
							<p className="mt-2 leading-5 text-slate-700">{claim.text}</p>
							<p className="mt-2 font-semibold text-slate-600">{confidenceLabels[claim.confidence] ?? claim.confidence} · Revisão profissional obrigatória</p>
							<ul className="mt-2 space-y-1 text-slate-600">
								{claim.source_ids.map((sourceId) => {
									const source = sourcesById[sourceId];
					return <li key={sourceId}>Fonte: {source ? `${source.document}${source.version ? ` · versão ${source.version}` : ''} — ${source.section}` : internalSourceLabel(sourceId)}</li>;
								})}
							</ul>
						</details>
					))}
				</div>
			</div>

			{assistant.blocked_claims?.length ? (
				<PwaAlert tone="error" title={`${assistant.blocked_claims.length} trecho(s) removido(s) por segurança`}>
					<details><summary className="pwa-focus min-h-11 cursor-pointer rounded-lg py-2 text-xs font-bold">Ver detalhes da revisão</summary><ul className="mt-2 space-y-2 text-xs">{assistant.blocked_claims.map((claim, index) => <li key={`${claim.id}-${index}`}><strong>{claim.text || 'Trecho sem conteúdo válido'}</strong><br />{claim.blocked_reasons?.join(' ')}</li>)}</ul></details>
				</PwaAlert>
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
