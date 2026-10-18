import React, { useMemo, useState } from 'react';
import { formatIssueDate } from '../../../shared/formatters';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';

function searchable(value) {
	return String(value ?? '')
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLowerCase();
}

function initials(name) {
	return String(name ?? '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
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
					<p className="text-xs font-bold uppercase tracking-[0.16em] text-ricosty-600">Atendimento</p>
					<h2 id="clients-title" className="mt-1 text-2xl font-bold text-ricosty-950">Clientes</h2>
					<p className="mt-1 text-sm text-slate-500">Localize rapidamente quem será atendido.</p>
				</div>
				{canCreate ? <PwaButton type="button" icon="plus" onClick={onCreate} className="min-h-11 px-3">Cadastrar</PwaButton> : null}
			</div>

			<label className="relative mt-5 block">
				<span className="sr-only">Buscar clientes</span>
				<PwaIcon name="search" size={20} className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ricosty-400" />
				<input
					type="search"
					value={query}
					onChange={(event) => setQuery(event.target.value)}
					placeholder="Nome, telefone, e-mail ou CPF"
					className="pwa-focus min-h-13 w-full rounded-2xl border border-ricosty-200 bg-white/90 py-3 pl-12 pr-12 text-base shadow-sm transition placeholder:text-slate-400 focus:border-ricosty-400"
				/>
				{query ? <button type="button" onClick={() => setQuery('')} aria-label="Limpar busca" className="pwa-focus absolute right-2 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full text-xl text-slate-500 hover:bg-ricosty-50">×</button> : null}
			</label>

			<label className="mt-3 flex items-center gap-2 text-xs font-semibold text-slate-600">
				<input type="checkbox" checked={showUnavailable} onChange={(event) => setShowUnavailable(event.target.checked)} className="h-4 w-4 rounded border-ricosty-300 text-ricosty-600 focus:ring-ricosty-300" />
				Mostrar clientes inativos ou anonimizados
			</label>

			<div className="mt-5 flex items-center justify-between">
				<h3 className="text-sm font-bold text-slate-800">{query.trim() ? 'Resultados da busca' : 'Clientes recentes'}</h3>
				<span aria-live="polite" className="text-xs text-slate-500">{filteredClients.length} encontrado(s)</span>
			</div>

			<div className="mt-3 space-y-2">
				{visibleClients.map((client) => (
					<PwaCard as="button" type="button" key={client.id} onClick={() => onSelect(client)} aria-label={`Abrir cliente ${client.full_name}`} className="pwa-focus pwa-pressable block w-full touch-manipulation p-4 text-left transition hover:-translate-y-0.5 hover:border-ricosty-300 hover:shadow-[0_16px_36px_rgba(97,38,55,0.11)]">
						<span className="flex items-center gap-3">
							<span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-ricosty-100 to-ricosty-200 text-sm font-black text-ricosty-700 ring-1 ring-ricosty-200">{initials(client.full_name)}</span>
							<span className="min-w-0 flex-1"><span className="block truncate font-bold text-ricosty-950">{client.full_name}</span><span className="mt-0.5 block truncate text-sm text-slate-600">{client.phone || client.email || 'Sem contato cadastrado'}</span></span>
							{client.is_anonymized ? (
								<span className="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase text-amber-700">Anonimizado</span>
							) : !client.is_active ? (
								<span className="rounded-full bg-slate-200 px-2 py-1 text-[10px] font-bold uppercase text-slate-600">Inativo</span>
							) : <PwaIcon name="chevronRight" size={18} className="text-ricosty-400" />}
						</span>
						<span className="mt-3 block border-t border-ricosty-100 pt-2 text-xs text-slate-400">Última avaliação: {formatIssueDate(client.last_assessment_at)}</span>
					</PwaCard>
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
