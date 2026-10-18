import React from 'react';
import PwaButton from './ui/PwaButton';

const content = {
	avaliacao: {
		title: 'Nova avaliação',
		description: 'O cliente selecionado está pronto para o fluxo de avaliação da próxima fase.',
	},
	assistente: {
		title: 'Assistente da avaliação',
		description: 'Este módulo será conectado às avaliações salvas em uma próxima fase.',
	},
	relatorio: {
		title: 'Relatórios',
		description: 'A consulta e o compartilhamento serão conectados em uma próxima fase.',
	},
};

export default function OperationalModulePlaceholder({ section, selectedClient, selectedAssessment, onBackToClients }) {
	const module = content[section] ?? content.avaliacao;

	return (
		<section className="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
			<p className="text-xs font-bold uppercase tracking-wide text-emerald-700">Próxima etapa</p>
			<h2 className="mt-2 text-xl font-bold text-slate-950">{module.title}</h2>
			<p className="mt-2 text-sm leading-6 text-slate-600">{module.description}</p>
			{selectedClient ? <p className="mt-4 rounded-xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">Cliente selecionado: {selectedClient.full_name}</p> : null}
			{selectedAssessment ? <p className="mt-2 text-xs font-semibold text-slate-500">Avaliação #{selectedAssessment.id} pronta para a próxima etapa.</p> : null}
			<PwaButton type="button" onClick={onBackToClients} icon="arrowLeft" className="mt-5">Voltar para clientes</PwaButton>
		</section>
	);
}
