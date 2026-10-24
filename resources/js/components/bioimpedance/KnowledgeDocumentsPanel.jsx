import React, { useEffect, useState } from 'react';
import useActionLock from '../../hooks/useActionLock';

const statusLabels = { draft: 'Rascunho', reviewed: 'Revisada', approved: 'Aprovada', published: 'Publicada', superseded: 'Substituída' };
const nextActions = {
	draft: { action: 'review', label: 'Marcar como revisada' },
	reviewed: { action: 'approve', label: 'Aprovar referência' },
	approved: { action: 'publish', label: 'Publicar versão' },
};

const emptyForm = { title: '', document_type: 'clinical_reference', version: '', source_url: '', section: '', content: '', tags: '' };

export default function KnowledgeDocumentsPanel() {
	const runAction = useActionLock();
	const [documents, setDocuments] = useState([]);
	const [form, setForm] = useState(emptyForm);
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [transitioningId, setTransitioningId] = useState(null);
	const [error, setError] = useState('');

	useEffect(() => {
		let active = true;
		window.axios.get('/bioimpedance/knowledge-documents')
			.then(({ data }) => { if (active) setDocuments(data.documents ?? []); })
			.catch(() => { if (active) setError('Não foi possível carregar as referências editoriais.'); })
			.finally(() => { if (active) setLoading(false); });
		return () => { active = false; };
	}, []);

	async function create(event) {
		event.preventDefault();
		return runAction('create-knowledge-document', async () => {
			if (saving) return;
			setSaving(true);
			setError('');
			try {
				const { data } = await window.axios.post('/bioimpedance/knowledge-documents', {
					title: form.title,
					document_type: form.document_type,
					version: form.version,
					source_url: form.source_url || null,
					chunks: [{ section: form.section, content: form.content, language: 'pt-BR', tags: form.tags.split(',').map((tag) => tag.trim()).filter(Boolean) }],
				});
				setDocuments(data.documents ?? []);
				setForm(emptyForm);
			} catch (requestError) {
				setError(requestError.response?.data?.message ?? Object.values(requestError.response?.data?.errors ?? {})?.[0]?.[0] ?? 'Não foi possível cadastrar a referência.');
			} finally {
				setSaving(false);
			}
		});
	}

	async function transition(document, action, replacementDocumentId = null) {
		return runAction(`knowledge-${document.id}-${action}`, async () => {
			if (transitioningId) return;
			setTransitioningId(document.id);
			setError('');
			try {
				const { data } = await window.axios.patch(`/bioimpedance/knowledge-documents/${document.id}/transition`, { action, replacement_document_id: replacementDocumentId });
				setDocuments(data.documents ?? []);
			} catch (requestError) {
				setError(requestError.response?.data?.message ?? Object.values(requestError.response?.data?.errors ?? {})?.[0]?.[0] ?? 'Não foi possível alterar o estado editorial.');
			} finally {
				setTransitioningId(null);
			}
		});
	}

	return (
		<section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
			<h2 className="text-base font-semibold text-slate-950">Base de referências</h2>
			<p className="mt-1 text-xs leading-5 text-slate-500">Somente versões publicadas podem fundamentar o assistente. Depois da publicação, o conteúdo permanece imutável e uma nova versão deve ser criada.</p>
			<form onSubmit={create} className="mt-4 grid gap-3 md:grid-cols-2">
				<label className="text-sm font-semibold text-slate-700">Título<input required maxLength={255} value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} className={inputClass()} /></label>
				<label className="text-sm font-semibold text-slate-700">Versão<input required maxLength={64} value={form.version} onChange={(event) => setForm({ ...form, version: event.target.value })} className={inputClass()} /></label>
				<label className="text-sm font-semibold text-slate-700">Tipo<select value={form.document_type} onChange={(event) => setForm({ ...form, document_type: event.target.value })} className={inputClass()}><option value="clinical_reference">Referência clínica</option><option value="manufacturer_manual">Manual do fabricante</option><option value="clinic_protocol">Protocolo da clínica</option></select></label>
				<label className="text-sm font-semibold text-slate-700">URL da fonte<input type="url" value={form.source_url} onChange={(event) => setForm({ ...form, source_url: event.target.value })} className={inputClass()} /></label>
				<label className="text-sm font-semibold text-slate-700 md:col-span-2">Seção<input required maxLength={255} value={form.section} onChange={(event) => setForm({ ...form, section: event.target.value })} className={inputClass()} /></label>
				<label className="text-sm font-semibold text-slate-700 md:col-span-2">Conteúdo da referência<textarea required minLength={30} maxLength={10000} rows="5" value={form.content} onChange={(event) => setForm({ ...form, content: event.target.value })} className={inputClass()} /></label>
				<label className="text-sm font-semibold text-slate-700 md:col-span-2">Tags, separadas por vírgula<input value={form.tags} onChange={(event) => setForm({ ...form, tags: event.target.value })} className={inputClass()} /></label>
				<button type="submit" disabled={saving} aria-busy={saving} className="min-h-11 rounded-xl bg-slate-950 px-4 py-3 text-sm font-bold text-white disabled:opacity-50 md:col-span-2">{saving ? 'Criando rascunho...' : 'Cadastrar como rascunho'}</button>
			</form>
			{error ? <p role="alert" className="mt-3 rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-800">{error}</p> : null}
			<div className="mt-5 space-y-3">
				{loading ? <div className="h-24 animate-pulse rounded-xl bg-slate-100" aria-label="Carregando referências" /> : null}
				{documents.map((document) => {
					const next = nextActions[document.status];
					const replacements = documents.filter((candidate) => candidate.id !== document.id && candidate.title === document.title && candidate.status === 'published');
					return <article key={document.id} className="rounded-xl border border-slate-200 p-3"><div className="flex flex-wrap items-start justify-between gap-2"><div><p className="font-bold text-slate-900">{document.title}</p><p className="text-xs text-slate-500">Versão {document.version} · {document.chunks.length} seção(ões)</p></div><span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase text-slate-700">{statusLabels[document.status] ?? document.status}</span></div><p className="mt-2 text-xs text-slate-600">Criada por {document.created_by ?? 'sistema'}{document.published_at ? ` · publicada em ${new Date(document.published_at).toLocaleString('pt-BR')}` : ''}</p>{next ? <button type="button" disabled={Boolean(transitioningId)} aria-busy={transitioningId === document.id} onClick={() => { if (next.action !== 'publish' || window.confirm('Após a publicação, esta versão ficará imutável e poderá fundamentar novas orientações. Deseja continuar?')) transition(document, next.action); }} className="mt-3 min-h-11 rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-50">{transitioningId === document.id ? 'Processando...' : next.label}</button> : null}{document.status === 'published' && replacements.length ? <select disabled={Boolean(transitioningId)} aria-label={`Substituir versão ${document.version}`} defaultValue="" onChange={(event) => { if (event.target.value && window.confirm('Esta versão deixará de ser usada em novas gerações. Deseja continuar?')) transition(document, 'supersede', Number(event.target.value)); event.target.value = ''; }} className={`${inputClass()} mt-3 disabled:opacity-50`}><option value="">Substituir por outra versão...</option>{replacements.map((replacement) => <option key={replacement.id} value={replacement.id}>Versão {replacement.version}</option>)}</select> : null}</article>;
				})}
				{!loading && !documents.length ? <p className="text-sm text-slate-500">Nenhuma referência cadastrada.</p> : null}
			</div>
		</section>
	);
}

function inputClass() {
	return 'mt-2 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 font-normal text-slate-900 outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200';
}
