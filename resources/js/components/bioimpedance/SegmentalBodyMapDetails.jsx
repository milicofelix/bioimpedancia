import React from 'react';
import { formatSegmentalValue, segmentalBodyStates } from './segmentalBodyVisual';

export function SegmentalMapLegend() {
	return (
		<div className="mt-2 grid grid-cols-2 gap-1.5 text-[10px] sm:flex sm:flex-wrap sm:justify-center sm:gap-2 sm:text-[11px]" role="list" aria-label="Legenda das classificações">
			{Object.values(segmentalBodyStates).map((state) => <span key={state.label} role="listitem" className="rounded-full px-2 py-1 text-center font-bold" style={{ color: state.color, backgroundColor: state.fill }} title={state.label} aria-label={`${state.marker} ${state.label}`}><span aria-hidden="true">{state.marker} </span><span className="sm:hidden">{state.compactLabel}</span><span className="hidden sm:inline">{state.label}</span></span>)}
		</div>
	);
}

export function SegmentalCompactRegions({ items }) {
	return (
		<dl className="mt-3 grid min-w-0 grid-cols-2 gap-2 sm:hidden" aria-label="Resumo das regiões corporais">
			{items.map((item) => <div key={item.key} className={`min-w-0 rounded-xl border p-2.5 ${item.side === 'center' ? 'col-span-2' : ''}`} style={{ borderColor: item.state.color, backgroundColor: item.state.fill }}><dt className="break-words text-[11px] font-extrabold text-slate-800">{item.label}</dt><dd className="mt-1 break-words text-xs text-slate-700"><strong className="text-slate-950">{formatSegmentalValue(item.mass_kg, ' kg')}</strong><span className="mx-1" aria-hidden="true">·</span>{formatSegmentalValue(item.reference_percentage, '% ref.')}</dd><dd className="mt-1 break-words text-[11px] font-bold" style={{ color: item.state.color }}><span aria-hidden="true">{item.state.marker} </span>{item.state.label}</dd></div>)}
		</dl>
	);
}

export function SegmentalDataTable({ items, heading }) {
	return (
		<div className="mt-3 w-full min-w-0 max-w-full overflow-x-auto overscroll-x-contain rounded-xl focus:outline-none focus:ring-2 focus:ring-ricosty-300 focus:ring-offset-2" role="region" aria-label={`Tabela de ${heading.toLowerCase()}. Deslize horizontalmente para consultar todas as colunas.`} tabIndex="0">
			<table className="w-full min-w-[34rem] border-collapse text-left text-xs">
				<caption className="sr-only">Tabela equivalente de {heading.toLowerCase()}</caption>
				<thead><tr className="border-b border-slate-200 text-slate-600"><th className="p-2">Região</th><th className="p-2">Lado</th><th className="p-2">Massa</th><th className="p-2">Referência</th><th className="p-2">Classificação</th></tr></thead>
				<tbody>{items.map((item) => <tr key={item.key} className="border-b border-slate-100"><th scope="row" className="p-2 font-semibold text-slate-800">{item.label}</th><td className="p-2">{item.side === 'left' ? 'L — esquerdo' : item.side === 'right' ? 'R — direito' : 'Centro'}</td><td className="p-2">{formatSegmentalValue(item.mass_kg, ' kg')}</td><td className="p-2">{formatSegmentalValue(item.reference_percentage, '%')}</td><td className="p-2 font-semibold" style={{ color: item.state.color }}><span aria-hidden="true">{item.state.marker} </span>{item.state.label}</td></tr>)}</tbody>
			</table>
		</div>
	);
}
