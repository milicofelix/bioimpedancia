import React from 'react';
import PwaButton from '../../components/ui/PwaButton';
import PwaIcon from '../../components/ui/PwaIcon';

export default function ObservationSuggestion({ assistant, draft, saving, online, error, onDraftChange, onApprove, onDiscard }) {
	const generated = assistant.status === 'generated' && assistant.validation_status === 'passed';
	const edited = generated && draft !== assistant.suggestion;

	return (
		<section className="pwa-card p-5" aria-busy={saving}>
			<div className="flex flex-wrap items-center justify-between gap-2">
				<div>
					<p className="text-xs font-bold uppercase tracking-wide text-violet-700">Sugestão para revisão</p>
					<h2 className="mt-1 text-lg font-bold text-slate-950">Revise antes de aprovar</h2>
				</div>
				<div className="flex gap-2"><span className={`rounded-full px-3 py-1 text-xs font-bold ${generated ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800'}`}>{generated ? 'Validada' : 'Indisponível'}</span>{edited ? <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-bold text-violet-700">Texto editado</span> : null}</div>
			</div>

			{generated ? (
				<>
					<label className="mt-4 block text-sm font-semibold text-slate-700">
						Observação profissional
						<textarea rows="8" maxLength={2000} value={draft} onChange={(event) => onDraftChange(event.target.value)} className="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 font-normal leading-6 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-200" />
						<span className="mt-1 block text-right text-xs font-normal text-slate-400">{draft.length}/2000</span>
					</label>

					<details className="mt-4 rounded-xl bg-slate-50 p-4" open>
						<summary className="flex cursor-pointer list-none items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500"><PwaIcon name="fileText" size={15} />Fontes utilizadas ({assistant.sources?.length ?? 0})</summary>
						<ul className="mt-2 space-y-2 text-xs leading-5 text-slate-600">
							{(assistant.sources ?? []).map((source) => (
								<li key={source.id} className="border-l-2 border-violet-200 pl-2">
									<strong className="text-slate-800">{source.document}</strong><br />
									{source.section}{source.page ? ` · página ${source.page}` : ''}
								</li>
							))}
						</ul>
						{!assistant.sources?.length ? <p className="mt-2 text-xs text-amber-700">Nenhuma fonte recuperada foi informada.</p> : null}
					</details>
				</>
			) : (
				<div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
					<p className="font-bold">Não foi possível liberar uma sugestão.</p>
					<p className="mt-1">{assistant.summary ?? 'A IA ou as referências necessárias estão indisponíveis no momento.'}</p>
					{assistant.validation_errors?.length ? <ul className="mt-2 list-disc pl-5">{assistant.validation_errors.map((item) => <li key={item}>{item}</li>)}</ul> : null}
				</div>
			)}

			{assistant.blocked_provider_errors?.length ? <p className="mt-3 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800">A resposta externa foi bloqueada pela validação e substituída por uma alternativa segura.</p> : null}
			{assistant.notice ? <p className="mt-3 text-xs leading-5 text-slate-500">{assistant.notice}</p> : null}
			{error ? <p role="alert" className="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{error}</p> : null}

			<div className="mt-5 grid gap-3 sm:grid-cols-2">
				<PwaButton type="button" onClick={onDiscard} disabled={saving} variant="secondary">Descartar</PwaButton>
				{generated ? <PwaButton type="button" onClick={onApprove} disabled={saving || !online || draft.trim().length < 20} icon="check">{saving ? 'Adicionando...' : 'Aprovar e adicionar'}</PwaButton> : null}
			</div>
		</section>
	);
}
