import React from 'react';
import PwaButton from '../../components/ui/PwaButton';
import PwaIcon from '../../components/ui/PwaIcon';
import PwaAlert from '../../components/ui/PwaAlert';
import PwaSectionHeader from '../../components/ui/PwaSectionHeader';

function groupSourcesByDocument(sources) {
	return [...sources.reduce((groups, source) => {
		const key = [source.document, source.version, source.manufacturer, source.device_model].map((value) => value ?? '').join('|');
		if (!groups.has(key)) {
			groups.set(key, {
				key,
				document: source.document,
				version: source.version,
				manufacturer: source.manufacturer,
				deviceModel: source.device_model,
				sections: [],
			});
		}
		groups.get(key).sections.push({ id: source.id, section: source.section, page: source.page, excerpt: source.excerpt });
		return groups;
	}, new Map()).values()];
}

export default function ObservationSuggestion({ assistant, draft, saving, online, error, onDraftChange, onApprove, onDiscard }) {
	const generated = assistant.status === 'generated' && assistant.validation_status === 'passed';
	const edited = generated && draft !== assistant.suggestion;
	const sourceDocuments = groupSourcesByDocument(assistant.sources ?? []);

	return (
		<section className="pwa-card border-violet-200 p-5" aria-busy={saving}>
			<div className="flex flex-wrap items-center justify-between gap-2">
				<PwaSectionHeader icon="eye" eyebrow="Observação" title="Revise antes de aprovar" description="Confira o texto e as fontes que sustentam a sugestão." />
				<div className="flex flex-wrap gap-2"><span className={`rounded-full px-3 py-1 text-xs font-bold ${generated ? 'bg-amber-100 text-amber-900' : 'bg-rose-100 text-rose-800'}`}>{generated ? 'Pendente de aprovação' : 'Bloqueada'}</span>{edited ? <span role="status" className="rounded-full bg-violet-100 px-3 py-1 text-xs font-bold text-violet-800">Texto editado pelo profissional</span> : null}</div>
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
						<div className="flex items-center justify-between gap-2"><h3 className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-slate-500"><PwaIcon name="fileText" size={15} />Fontes utilizadas</h3><span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-500">{sourceDocuments.length} {sourceDocuments.length === 1 ? 'documento' : 'documentos'}</span></div>
						<div className="mt-2 space-y-2">
							{sourceDocuments.map((source, index) => (
								<details key={source.key} className="group rounded-xl border border-slate-200 bg-slate-50/70 open:bg-white">
									<summary className="pwa-focus flex min-h-12 cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-800"><span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-800"><PwaIcon name="fileText" size={16} /></span><span className="min-w-0 flex-1 break-words">{source.document || `Fonte ${index + 1}`}<span className="mt-0.5 block text-[11px] font-normal text-slate-500">{source.sections.length} {source.sections.length === 1 ? 'trecho utilizado' : 'trechos utilizados'}</span></span><span className="text-violet-700 transition group-open:rotate-90"><PwaIcon name="chevronRight" size={16} /></span></summary>
									<div className="border-t border-slate-100 px-4 py-3 text-xs leading-5 text-slate-600">{source.version ? <p><strong className="text-slate-700">Versão:</strong> {source.version}</p> : null}{source.deviceModel ? <p><strong className="text-slate-700">Equipamento:</strong> {source.deviceModel}</p> : null}<ul className="mt-2 space-y-2">{source.sections.map((section) => <li key={section.id} className="rounded-lg bg-slate-50 p-2"><strong className="text-slate-700">Seção:</strong> {section.section || 'Não informada'}{section.page ? ` · página ${section.page}` : ''}{section.excerpt ? <p className="mt-1 border-l-2 border-violet-200 pl-2 italic">{section.excerpt}</p> : null}</li>)}</ul></div>
								</details>
							))}
							{!sourceDocuments.length ? <PwaAlert tone="info" title="Fontes não exibidas">Não há documentos disponíveis para consulta nesta sugestão.</PwaAlert> : null}
						</div>
					</div>
				</>
			) : (
				<PwaAlert tone="error" title="Sugestão bloqueada pela validação" className="mt-4">
					<p>{assistant.summary ?? 'O conteúdo não atendeu aos critérios necessários para revisão.'}</p>
					{assistant.validation_errors?.length ? <ul className="mt-2 list-disc pl-5">{assistant.validation_errors.map((item) => <li key={item}>{item}</li>)}</ul> : null}
				</PwaAlert>
			)}

			{assistant.blocked_provider_errors?.length ? <PwaAlert tone="attention" title="Conteúdo descartado por segurança" className="mt-3">Nenhum trecho bloqueado será incluído na observação.</PwaAlert> : null}
			{error ? <PwaAlert tone="error" title="Não foi possível concluir a aprovação" className="mt-3">{error}</PwaAlert> : null}

			<div className="mt-5 grid gap-3 sm:grid-cols-2">
				<PwaButton type="button" onClick={onDiscard} disabled={saving} variant="secondary">Descartar</PwaButton>
				{generated ? <PwaButton type="button" onClick={onApprove} disabled={saving || !online || draft.trim().length < 20} loading={saving} icon="check">{saving ? 'Adicionando...' : edited ? 'Aprovar versão editada' : 'Aprovar e adicionar'}</PwaButton> : null}
			</div>
		</section>
	);
}
