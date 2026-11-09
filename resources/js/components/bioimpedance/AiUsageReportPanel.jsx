import React, { useEffect, useState } from 'react';
import PwaAlert from '../../pwa/components/ui/PwaAlert';
import PwaIcon from '../../pwa/components/ui/PwaIcon';

const fieldClass = 'mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-950';
const buttonClass = 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-700 disabled:opacity-50';
const costStatuses = { usage_unavailable: 'Consumo não informado', usage_invalid: 'Consumo incompleto', price_unavailable: 'Preço não cadastrado para modelo ou modalidade' };
const outcomes = { connection_unknown: 'Conexão interrompida — resultado desconhecido', http_error: 'Falha HTTP', response_received: 'Resposta recebida' };
const usd = (value) => value == null ? 'Não disponível' : new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 6, maximumFractionDigits: 8 }).format(Number(value));
const integer = (value) => value == null ? 'Não informado' : new Intl.NumberFormat('pt-BR').format(value);
const localDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

function initialPeriod() {
	const today = new Date();
	const start = new Date(today);
	start.setDate(start.getDate() - 29);
	return { from: localDate(start), to: localDate(today), operation: '' };
}

export default function AiUsageReportPanel() {
	const [draft, setDraft] = useState(initialPeriod);
	const [filters, setFilters] = useState(draft);
	const [page, setPage] = useState(1);
	const [refresh, setRefresh] = useState(0);
	const [report, setReport] = useState(null);
	const [operations, setOperations] = useState({});
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState('');

	useEffect(() => {
		const controller = new AbortController();
		setLoading(true);
		setError('');
		setReport(null);
		window.axios.get('/bioimpedance/ai-usage', { params: { ...filters, page }, signal: controller.signal })
			.then(({ data }) => {
				if (controller.signal.aborted) return;
				setReport(data);
				setOperations(data.operations);
			})
			.catch((failure) => {
				if (controller.signal.aborted) return;
				const validation = Object.values(failure.response?.data?.errors ?? {})?.[0]?.[0];
				setError(failure.response?.status === 403 ? 'Você não tem permissão para consultar estes dados.' : validation ?? 'Não foi possível carregar os custos. Verifique sua conexão e tente atualizar.');
			})
			.finally(() => { if (!controller.signal.aborted) setLoading(false); });
		return () => controller.abort();
	}, [filters, page, refresh]);

	function apply(event) {
		event.preventDefault();
		setPage(1);
		setFilters({ ...draft });
	}

	const summary = report?.summary;
	const conversion = report?.conversion;
	return (
		<section aria-labelledby="ai-usage-title" aria-busy={loading} className="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
			<h2 id="ai-usage-title" className="flex items-center gap-2 text-base font-bold text-slate-950"><PwaIcon name="activity" size={20} />Consumo e custos da IA</h2>
			<p className="mt-2 text-sm leading-6 text-slate-600">Estimativa por requisição de OCR e dos agentes, não um saldo de créditos ou comprovante de cobrança. O histórico começa após a ativação deste recurso e não inclui outros sistemas que utilizem a mesma chave.</p>
			<form onSubmit={apply} className="mt-4 grid gap-3 sm:grid-cols-2">
				<label className="text-sm font-semibold text-slate-700">De<input type="date" required className={fieldClass} value={draft.from} onChange={(event) => setDraft({ ...draft, from: event.target.value })} /></label>
				<label className="text-sm font-semibold text-slate-700">Até<input type="date" required min={draft.from} className={fieldClass} value={draft.to} onChange={(event) => setDraft({ ...draft, to: event.target.value })} /></label>
				<label className="text-sm font-semibold text-slate-700">Operação<select className={fieldClass} value={draft.operation} onChange={(event) => setDraft({ ...draft, operation: event.target.value })}><option value="">Todas</option>{Object.entries(operations).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
				<div className="flex flex-wrap items-end gap-2"><button type="submit" disabled={loading} className={buttonClass}>Filtrar</button><button type="button" disabled={loading} onClick={() => setRefresh((value) => value + 1)} className={buttonClass}>Atualizar</button></div>
			</form>
			{loading ? <p role="status" className="mt-4 text-sm text-slate-600">Carregando consumo…</p> : null}
			{error ? <PwaAlert tone="error" title="Consulta indisponível" className="mt-4">{error}</PwaAlert> : null}
			{summary ? <>
				<dl className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-3 text-sm sm:grid-cols-2">
					<div><dt className="text-slate-600">Total estimado conhecido no período</dt><dd className="break-all text-lg font-bold text-slate-950">{usd(summary.estimated_usd)}</dd></div>
					<div><dt className="text-slate-600">Requisições / sem custo calculável</dt><dd className="font-bold text-slate-950">{integer(summary.requests)} / {integer(summary.unpriced_requests)}</dd></div>
					<div><dt className="text-slate-600">Tokens informados: entrada / saída</dt><dd className="font-bold text-slate-950">{integer(summary.input_tokens)} / {integer(summary.output_tokens)}</dd></div>
					{conversion ? <div><dt className="text-slate-600">Conversão de referência ({conversion.date})</dt><dd className="font-bold text-slate-950">{summary.estimated_usd == null ? 'Não disponível' : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 4 }).format(Number(summary.estimated_usd) * conversion.usd_brl_rate)} · US$ 1 = R$ {conversion.usd_brl_rate}</dd></div> : null}
				</dl>
				{summary.unpriced_requests > 0 ? <PwaAlert className="mt-3" title="Total parcial">Há requisições sem consumo informado ou sem preço cadastrado. Elas não foram tratadas como gratuitas. Falhas de conexão podem ter gerado consumo no provedor.</PwaAlert> : null}
				<p className="mt-3 text-xs leading-5 text-slate-600">A segunda leitura segmentar é uma chamada separada. Respostas descartadas ou bloqueadas pela revisão também podem consumir tokens. O motor local não gera chamadas pagas. Tokens de raciocínio já estão incluídos na saída. Datas do filtro: {report.timezone}.</p>
				{report.records.data.length === 0 ? <p className="mt-4 text-sm text-slate-600">Nenhuma requisição registrada neste período.</p> : <ol className="mt-4 space-y-3">
					{report.records.data.map((record) => <li key={record.id} className="min-w-0 rounded-xl border border-slate-200 p-3 text-sm">
						<div className="flex flex-wrap justify-between gap-2"><strong className="text-slate-950">{operations[record.operation] ?? record.operation}</strong><strong className="break-all text-sky-900">{usd(record.estimated_cost_usd)}</strong></div>
						<p className="mt-1 break-words text-slate-600">#{record.id} · {new Date(record.created_at).toLocaleString('pt-BR')} · {record.model} · {record.service_tier}</p>
						<p className="mt-2 text-slate-700">Entrada: {integer(record.input_tokens)} · Cache de leitura: {integer(record.cached_tokens)} · Saída: {integer(record.output_tokens)}</p>
						<p className="mt-1 text-slate-600">{outcomes[record.outcome] ?? 'Resultado não informado'}{record.http_status ? ` (${record.http_status})` : ''} · {(record.duration_ms / 1000).toFixed(1)} s{record.cost_status !== 'estimated' ? ` · ${costStatuses[record.cost_status] ?? 'Custo indisponível'}` : ' · Custo estimado'}</p>
					</li>)}
				</ol>}
				<nav aria-label="Páginas do consumo de IA" className="mt-4 flex flex-wrap items-center justify-between gap-2"><button type="button" className={buttonClass} disabled={page <= 1 || loading} onClick={() => setPage((value) => value - 1)}>Anterior</button><span className="text-sm text-slate-600">Página {report.records.current_page} de {report.records.last_page}</span><button type="button" className={buttonClass} disabled={page >= report.records.last_page || loading} onClick={() => setPage((value) => value + 1)}>Próxima</button></nav>
			</> : null}
		</section>
	);
}
