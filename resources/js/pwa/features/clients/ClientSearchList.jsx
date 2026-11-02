import React, { useMemo, useState } from 'react';
import { formatIssueDate } from '../../../shared/formatters';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';
import PwaSectionHeader from '../../components/ui/PwaSectionHeader';
import PwaState from '../../components/ui/PwaState';

function searchable(value) {
	return String(value ?? '')
		.normalize('NFD')
		.replace(/[\u0300-\u036f]/g, '')
		.toLowerCase();
}

function initials(name) {
	return String(name ?? '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

function clientStatus(client) {
	if (client.is_anonymized) return { label: 'Anonimizado', badge: 'bg-amber-100 text-amber-900', avatar: 'from-amber-100 to-amber-200 text-amber-900 ring-amber-200', border: 'border-l-amber-500' };
	if (!client.is_active) return { label: 'Inativo', badge: 'bg-slate-200 text-slate-800', avatar: 'from-slate-100 to-slate-200 text-slate-700 ring-slate-300', border: 'border-l-slate-500' };
	return { label: 'Ativo', badge: 'bg-emerald-100 text-emerald-900', avatar: 'from-ricosty-100 to-ricosty-200 text-ricosty-800 ring-ricosty-200', border: 'border-l-emerald-600' };
}

export default function ClientSearchList({ clients, canCreate, onCreate, onSelect }) {
	const [query, setQuery] = useState('');
	const [statusFilter, setStatusFilter] = useState('active');
	const counts = useMemo(() => ({
		all: clients.length,
		active: clients.filter((client) => client.is_active && !client.is_anonymized).length,
		unavailable: clients.filter((client) => !client.is_active || client.is_anonymized).length,
	}), [clients]);

	const filteredClients = useMemo(() => {
		const term = searchable(query.trim());
		const digits = query.replace(/\D/g, '');

		return [...clients]
			.filter((client) => statusFilter === 'all' || (statusFilter === 'active' ? client.is_active && !client.is_anonymized : !client.is_active || client.is_anonymized))
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
	}, [clients, query, statusFilter]);

	const visibleClients = query.trim() ? filteredClients : filteredClients.slice(0, 12);

	return (
		<section aria-labelledby="clients-title">
			<PwaSectionHeader
				icon="users"
				eyebrow="Atendimento"
				title="Clientes"
				headingId="clients-title"
				description="Localize rapidamente quem será atendido."
				action={canCreate ? <PwaButton type="button" icon="plus" onClick={onCreate} className="min-h-11 px-3">Cadastrar</PwaButton> : null}
			/>

			<PwaCard className="mt-5 p-3">
				<label className="relative block">
					<span className="sr-only">Buscar clientes</span>
					<PwaIcon name="search" size={20} className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ricosty-500" />
					<input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Nome, telefone, e-mail ou CPF" className="pwa-focus min-h-13 w-full rounded-xl border border-ricosty-100 bg-ricosty-cream/50 py-3 pl-12 pr-12 text-base transition placeholder:text-slate-500 focus:border-ricosty-400 focus:bg-white" />
					{query ? <button type="button" onClick={() => setQuery('')} aria-label="Limpar busca de clientes" className="pwa-focus absolute right-1.5 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full text-slate-600 hover:bg-ricosty-50"><PwaIcon name="x" size={18} /></button> : null}
				</label>
				<div className="mt-3 grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1" role="group" aria-label="Filtrar clientes por situação">
					{[{ key: 'active', label: 'Ativos' }, { key: 'unavailable', label: 'Indisponíveis' }, { key: 'all', label: 'Todos' }].map((filter) => <button type="button" key={filter.key} onClick={() => setStatusFilter(filter.key)} aria-pressed={statusFilter === filter.key} aria-label={`${filter.label}: ${counts[filter.key]} clientes`} className={`pwa-focus flex min-h-11 min-w-0 flex-col items-center justify-center rounded-lg px-1 text-[11px] font-bold leading-tight transition min-[380px]:flex-row min-[380px]:text-xs ${statusFilter === filter.key ? 'bg-white text-ricosty-800 shadow-sm' : 'text-slate-600 hover:text-slate-800'}`}><span className="max-w-full break-words">{filter.label}</span><span className="mt-0.5 min-[380px]:ml-1 min-[380px]:mt-0" aria-hidden="true">{counts[filter.key]}</span></button>)}
				</div>
			</PwaCard>

			<div className="mt-5 flex items-center justify-between">
				<h3 className="text-sm font-bold text-slate-800">{query.trim() ? 'Resultados da busca' : 'Clientes recentes'}</h3>
				<span aria-live="polite" className="text-xs text-slate-500">{filteredClients.length} encontrado(s)</span>
			</div>

			<div className="mt-3 space-y-2">
				{visibleClients.map((client) => {
					const status = clientStatus(client);
					const contact = client.phone || client.email;
					return <PwaCard as="button" type="button" key={client.id} onClick={() => onSelect(client)} aria-label={`Abrir cliente ${client.full_name}, ${status.label}`} className={`pwa-focus pwa-pressable block w-full touch-manipulation border-l-4 p-4 text-left transition hover:-translate-y-0.5 hover:border-ricosty-300 hover:shadow-[0_16px_36px_rgba(97,38,55,0.11)] ${status.border}`}>
						<span className="flex flex-wrap items-center gap-3">
							<span className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-sm font-black ring-1 ${status.avatar}`}>{initials(client.full_name)}</span>
							<span className="min-w-0 flex-1"><span className="block break-words font-bold text-ricosty-950">{client.full_name}</span><span className="mt-1 flex items-center gap-1.5 break-all text-sm text-slate-600"><PwaIcon name={client.phone ? 'phone' : client.email ? 'mail' : 'alertTriangle'} size={14} className="shrink-0 text-ricosty-500" />{contact || 'Sem contato cadastrado'}</span></span>
							<span className={`shrink-0 rounded-full px-2 py-1 text-[11px] font-bold uppercase ${status.badge}`}>{status.label}</span>
						</span>
						<span className="mt-3 flex items-center justify-between border-t border-ricosty-100 pt-2 text-xs text-slate-600"><span className="flex items-center gap-1.5"><PwaIcon name="calendar" size={14} className="text-ricosty-500" /><span>Última avaliação: <strong className="font-semibold text-slate-700">{formatIssueDate(client.last_assessment_at)}</strong></span></span><span className="flex h-8 w-8 items-center justify-center rounded-full bg-ricosty-50"><PwaIcon name="chevronRight" size={17} className="text-ricosty-600" /></span></span>
					</PwaCard>;
				})}
			</div>

			{!visibleClients.length ? <PwaState className="mt-3" icon={clients.length ? 'search' : 'users'} title={clients.length ? 'Nenhum cliente encontrado' : 'Nenhum cliente cadastrado'} description={canCreate ? 'Use o botão Cadastrar para iniciar o primeiro atendimento.' : 'Seu perfil possui acesso somente aos registros existentes.'} /> : null}
		</section>
	);
}
