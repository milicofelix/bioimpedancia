import React, { useId, useMemo } from 'react';

const regionOrder = [
	['arm', 'left', 'Braço esquerdo', 38, 128, 116, 150],
	['arm', 'right', 'Braço direito', 322, 128, 244, 150],
	['trunk', 'center', 'Tronco', 322, 236, 218, 236],
	['leg', 'left', 'Perna esquerda', 38, 382, 145, 340],
	['leg', 'right', 'Perna direita', 322, 382, 215, 340],
];

const states = {
	low: { label: 'Abaixo da referência', marker: '↓', color: '#2563eb', background: '#dbeafe' },
	standard: { label: 'Padrão', marker: '●', color: '#047857', background: '#d1fae5' },
	high: { label: 'Alto', marker: '↑', color: '#be123c', background: '#ffe4e6' },
	unknown: { label: 'Não identificado', marker: '?', color: '#475569', background: '#e2e8f0' },
};

function number(value, suffix) {
	if (value === null || value === undefined || value === '') return '—';
	return `${Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}${suffix}`;
}

export default function SegmentalBodyMap({ measurements = [], type = 'fat', title }) {
	const titleId = useId();
	const descriptionId = useId();
	const gradientId = `${titleId.replace(/[^a-zA-Z0-9_-]/g, '')}-body`;
	const items = useMemo(() => regionOrder.map(([region, side, label, textX, textY, lineX, lineY]) => {
		const measurement = measurements.find((item) => item.type === type && item.region === region && item.side === side) ?? {};
		return { ...measurement, region, side, label, textX, textY, lineX, lineY, state: states[measurement.classification] ?? states.unknown };
	}), [measurements, type]);
	const heading = title ?? (type === 'fat' ? 'Gordura segmentar' : 'Equilíbrio muscular');

	return (
		<figure className="rounded-2xl border border-ricosty-100 bg-gradient-to-b from-white to-ricosty-50/40 p-3 print:break-inside-avoid" aria-labelledby={titleId} aria-describedby={descriptionId}>
			<figcaption id={titleId} className="text-center font-bold text-slate-950">{heading}</figcaption>
			<p id={descriptionId} className="mt-1 text-center text-xs text-slate-600">Vista frontal · L = esquerdo · R = direito</p>
			<svg viewBox="0 0 360 470" className="mx-auto mt-2 h-auto w-full max-w-md" role="img" aria-labelledby={`${titleId} ${descriptionId}`}>
				<defs><linearGradient id={gradientId} x1="0" x2="1"><stop stopColor="#f8e8eb"/><stop offset="1" stopColor="#ead5da"/></linearGradient></defs>
				<g fill={`url(#${gradientId})`} stroke="#9f5364" strokeWidth="1.7">
					<circle cx="180" cy="55" r="27" />
					<path d="M154 87 C160 80 200 80 206 87 L222 177 C217 206 207 230 204 247 L156 247 C153 230 143 206 138 177 Z" />
					<path d="M145 94 C128 105 119 129 115 158 L102 247 C100 261 108 268 116 255 L133 176 L158 107 Z" />
					<path d="M215 94 C232 105 241 129 245 158 L258 247 C260 261 252 268 244 255 L227 176 L202 107 Z" />
					<path d="M158 245 C151 273 145 315 143 363 L138 443 C139 456 153 456 158 444 L174 363 L180 270 L180 247 Z" />
					<path d="M202 245 C209 273 215 315 217 363 L222 443 C221 456 207 456 202 444 L186 363 L180 270 L180 247 Z" />
				</g>
				<path d="M180 88V247M144 170H216M180 247V365" stroke="#c98998" strokeDasharray="4 4" fill="none" />
				<text x="122" y="276" fill="#7f1d3f" fontSize="13" fontWeight="700">L</text><text x="230" y="276" fill="#7f1d3f" fontSize="13" fontWeight="700">R</text>
				{items.map((item) => {
					const left = item.side === 'left';
					return <g key={`${item.region}-${item.side}`}>
						<path d={`M${item.lineX} ${item.lineY} L${left ? item.textX + 55 : item.textX - 55} ${item.textY + 3}`} stroke={item.state.color} strokeWidth="1.4" fill="none" />
						<circle cx={item.lineX} cy={item.lineY} r="4" fill={item.state.color} stroke="#fff" strokeWidth="1.5" />
						<text x={item.textX} y={item.textY - 22} textAnchor={left ? 'start' : 'end'} fill="#334155" fontSize="9" fontWeight="700">{item.label}</text>
						<text x={item.textX} y={item.textY - 8} textAnchor={left ? 'start' : 'end'} fill="#0f172a" fontSize="11" fontWeight="700">{number(item.mass_kg, ' kg')}</text>
						<text x={item.textX} y={item.textY + 7} textAnchor={left ? 'start' : 'end'} fill="#475569" fontSize="9">{number(item.reference_percentage, '% ref.')}</text>
						<text x={item.textX} y={item.textY + 22} textAnchor={left ? 'start' : 'end'} fill={item.state.color} fontSize="9" fontWeight="700">{item.state.marker} {item.state.label}</text>
					</g>;
				})}
			</svg>
			<div className="mt-2 flex flex-wrap justify-center gap-2 text-[11px]">{Object.values(states).map((state) => <span key={state.label} className="rounded-full px-2 py-1 font-bold" style={{ color: state.color, backgroundColor: state.background }}>{state.marker} {state.label}</span>)}</div>
			<div className="mt-3 overflow-x-auto"><table className="w-full min-w-[34rem] border-collapse text-left text-xs"><caption className="sr-only">Tabela equivalente de {heading.toLowerCase()}</caption><thead><tr className="border-b border-slate-200 text-slate-600"><th className="p-2">Região</th><th className="p-2">Lado</th><th className="p-2">Massa</th><th className="p-2">Referência</th><th className="p-2">Classificação</th></tr></thead><tbody>{items.map((item) => <tr key={`${item.region}-${item.side}`} className="border-b border-slate-100"><th scope="row" className="p-2 font-semibold text-slate-800">{item.label}</th><td className="p-2">{item.side === 'left' ? 'L — esquerdo' : item.side === 'right' ? 'R — direito' : 'Centro'}</td><td className="p-2">{number(item.mass_kg, ' kg')}</td><td className="p-2">{number(item.reference_percentage, '%')}</td><td className="p-2 font-semibold" style={{ color: item.state.color }}>{item.state.marker} {item.state.label}</td></tr>)}</tbody></table></div>
		</figure>
	);
}
