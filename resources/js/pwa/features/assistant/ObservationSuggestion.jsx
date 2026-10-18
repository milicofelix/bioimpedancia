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
				<div className="flex flex-wrap gap-2"><span className={`rounded-full px-3 py-1 text-xs font-bold ${generated ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'}`}>{generated ? 'Validada' : 'Indisponível'}</span>{edited ? <span role="status" className="rounded-full bg-violet-100 px-3 py-1 text-xs font-bold text-violet-800">Texto editado pelo profissional</span> : null}</div>
			</div>

			{generated ? (
				<>
					<div className="mt-4 flex items-start gap-3 rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 to-ricosty-50 p-4 text-sm text-slate-700">
						<span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-violet-700 shadow-sm"><PwaIcon name="shield" size={19} /></span>
						<div><p className="font-bold text-violet-950">Aprovação humana obrigatória</p><p className="mt-1 text-xs leading-5">Esta é uma sugestão da IA. Leia, corrija quando necessário e aprove somente se o conteúdo representar sua avaliação profissional.</p></div>
					</div>
					<label className="mt-4 block text-sm font-semibold text-slate-700">
						<span className="flex items-center justify-between gap-2"><span>Observação profissional</span>{edited ? <span className="inline-flex items-center gap-1 text-xs font-bold text-violet-700"><PwaIcon name="user" size={14} />Editada pelo profissional</span> : null}</span>
						<textarea rows="8" maxLength={2000} value={draft} onChange={(event) => onDraftChange(event.target.value)} className={`mt-2 w-full rounded-xl border px-3 py-3 font-normal leading-6 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-200 ${edited ? 'border-violet-300 bg-violet-50/30' : 'border-slate-300 bg-white'}`} />
						<span className="mt-1 flex flex-wrap items-center justify-between gap-1 text-xs font-normal"><span className={edited ? 'font-semibold text-violet-800' : 'text-slate-600'}>{edited ? 'A versão editada será registrada na auditoria.' : 'Texto original sugerido pela IA.'}</span><span className="text-slate-600">{draft.length}/2000</span></span>
					</label>
					{edited ? <details className="mt-3 rounded-xl border border-violet-100 bg-white px-3 py-2"><summary className="pwa-focus min-h-11 cursor-pointer rounded-lg py-2 text-xs font-bold text-violet-800">Comparar com a sugestão original</summary><p className="mt-2 whitespace-pre-wrap border-l-2 border-violet-200 pl-3 text-xs leading-5 text-slate-600">{assistant.suggestion}</p><button type="button" onClick={() => onDraftChange(assistant.suggestion)} className="pwa-focus mt-3 min-h-11 rounded-lg bg-violet-50 px-3 py-2 text-xs font-bold text-violet-800">Restaurar texto original</button></details> : null}

					<div className="mt-5">
						<div className="flex items-center justify-between gap-2"><h3 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500"><PwaIcon name="fileText" size={15} />Fontes utilizadas</h3><span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-500">{assistant.sources?.length ?? 0}</span></div>
						<div className="mt-2 space-y-2">
							{(assistant.sources ?? []).map((source, index) => (
								<details key={source.id ?? `${source.document}-${index}`} className="group rounded-xl border border-slate-200 bg-slate-50/70 open:bg-white">
									<summary className="pwa-focus flex min-h-12 cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-800"><span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-800"><PwaIcon name="fileText" size={16} /></span><span className="min-w-0 flex-1 break-words">{source.document || `Fonte ${index + 1}`}</span><span className="text-violet-700 transition group-open:rotate-90"><PwaIcon name="chevronRight" size={16} /></span></summary>
									<div className="border-t border-slate-100 px-4 py-3 text-xs leading-5 text-slate-600"><p><strong className="text-slate-700">Seção:</strong> {source.section || 'Não informada'}</p>{source.page ? <p><strong className="text-slate-700">Página:</strong> {source.page}</p> : null}{source.excerpt ? <p className="mt-2 border-l-2 border-violet-200 pl-2 italic">{source.excerpt}</p> : null}</div>
								</details>
							))}
							{!assistant.sources?.length ? <p className="rounded-xl bg-amber-50 p-3 text-xs text-amber-700">Nenhuma fonte recuperada foi informada.</p> : null}
						</div>
					</div>
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
				{generated ? <PwaButton type="button" onClick={onApprove} disabled={saving || !online || draft.trim().length < 20} loading={saving} icon="check">{saving ? 'Adicionando...' : edited ? 'Aprovar versão editada' : 'Aprovar e adicionar'}</PwaButton> : null}
			</div>
		</section>
	);
}
