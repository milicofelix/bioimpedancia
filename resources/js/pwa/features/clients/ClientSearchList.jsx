import React, { useMemo, useState } from 'react';
import { formatIssueDate } from '../../../shared/formatters';

function searchable(value) {
	return String(value ?? '')
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLowerCase();
}

export default function ClientSearchList({ clients, canCreate, onCreate, onSelect }) {
	const [query, setQuery] = useState('');
	const [showUnavailable, setShowUnavailable] = useState(false);

	const filteredClients = useMemo(() => {
		const term = searchable(query.trim());
		const digits = query.replace(/\D/g, '');

		return [...clients]
			.filter((client) => showUnavailable || (client.is_active && !client.is_anonymized))
			.filter((client) => {
				if (!term) return true;

				const textMatch = [client.full_name, client.phone, client.email, client.cpf]
					.some((value) => searchable(value).includes(term));
				const digitMatch = digits.length > 0 && [client.phone, client.cpf]
					.some((value) => String(value ?? '').replace(/\D/g, '').includes(digits));

				return textMatch || digitMatch;
			})
			.sort((first, second) => {
				const firstDate = first.last_assessment_at ? new Date(first.last_assessment_at).getTime() : 0;
				const secondDate = second.last_assessment_at ? new Date(second.last_assessment_at).getTime() : 0;

				return secondDate - firstDate || first.full_name.localeCompare(second.full_name, 'pt-BR');
			});
	}, [clients, query, showUnavailable]);

	const visibleClients = query.trim() ? filteredClients : filteredClients.slice(0, 12);

	return (
		<section aria-labelledby="clients-title">
			<div className="flex items-start justify-between gap-3">
				<div>
					<p className="text-xs font-bold uppercase tracking-wide text-emerald-700">Atendimento</p>
					<h2 id="clients-title" className="mt-1 text-xl font-bold text-slate-950">Clientes</h2>
				</div>
				{canCreate ? (
					<button type="button" onClick={onCreate} className="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm">Cadastrar</button>
				) : null}
			</div>

			<label className="mt-4 block">
				<span className="sr-only">Buscar clientes</span>
				<input
					type="search"
					value={query}
					onChange={(event) => setQuery(event.target.value)}
					placeholder="Nome, telefone, e-mail ou CPF"
					className="min-h-12 w-full rounded-2xl border border-slate-200 bg-white px-4 text-base outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
				/>
			</label>

			<label className="mt-3 flex items-center gap-2 text-xs font-semibold text-slate-600">
				<input type="checkbox" checked={showUnavailable} onChange={(event) => setShowUnavailable(event.target.checked)} className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
				Mostrar clientes inativos ou anonimizados
			</label>

			<div className="mt-5 flex items-center justify-between">
				<h3 className="text-sm font-bold text-slate-800">{query.trim() ? 'Resultados da busca' : 'Clientes recentes'}</h3>
				<span className="text-xs text-slate-500">{filteredClients.length} encontrado(s)</span>
			</div>

			<div className="mt-3 space-y-2">
				{visibleClients.map((client) => (
					<button type="button" key={client.id} onClick={() => onSelect(client)} className="block w-full rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50/40">
						<span className="flex items-start justify-between gap-3">
							<span className="font-bold text-slate-950">{client.full_name}</span>
							{client.is_anonymized ? (
								<span className="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase text-amber-700">Anonimizado</span>
							) : !client.is_active ? (
								<span className="rounded-full bg-slate-200 px-2 py-1 text-[10px] font-bold uppercase text-slate-600">Inativo</span>
							) : null}
						</span>
						<span className="mt-1 block text-sm text-slate-600">{client.phone || client.email || 'Sem contato cadastrado'}</span>
						<span className="mt-2 block text-xs text-slate-400">Última avaliação: {formatIssueDate(client.last_assessment_at)}</span>
					</button>
				))}
			</div>

			{!visibleClients.length ? (
				<div className="mt-3 rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center">
					<p className="font-semibold text-slate-800">{clients.length ? 'Nenhum cliente encontrado.' : 'Nenhum cliente cadastrado.'}</p>
					<p className="mt-1 text-sm text-slate-500">{canCreate ? 'Use o botão Cadastrar para iniciar o primeiro atendimento.' : 'Seu perfil possui acesso somente aos registros existentes.'}</p>
				</div>
			) : null}
		</section>
	);
}
