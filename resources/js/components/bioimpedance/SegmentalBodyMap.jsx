import React, { useId, useMemo } from 'react';
import SegmentalBodyFigure from './SegmentalBodyFigure';
import { segmentalBodyRegions, segmentalBodyStates, segmentalBodyViewBox, segmentalState } from './segmentalBodyVisual';

function number(value, suffix) {
	if (value === null || value === undefined || value === '') return '—';
	const numericValue = Number(value);
	if (!Number.isFinite(numericValue)) return '—';
	return `${numericValue.toLocaleString('pt-BR', { maximumFractionDigits: 2 })}${suffix}`;
}

export default function SegmentalBodyMap({ measurements = [], type = 'fat', title }) {
	const titleId = useId();
	const descriptionId = useId();
	const items = useMemo(() => segmentalBodyRegions.map((visualRegion) => {
		const measurement = measurements.find((item) => item.type === type && item.region === visualRegion.region && item.side === visualRegion.side) ?? {};
		return { ...visualRegion, ...measurement, state: segmentalState(measurement.classification) };
	}), [measurements, type]);
	const classifications = useMemo(() => Object.fromEntries(items.map((item) => [item.key, item.classification])), [items]);
	const heading = title ?? (type === 'fat' ? 'Gordura segmentar' : 'Equilíbrio muscular');

	return (
		<figure className="rounded-2xl border border-ricosty-100 bg-gradient-to-b from-white to-ricosty-50/40 p-3 print:break-inside-avoid" aria-labelledby={titleId} aria-describedby={descriptionId}>
			<figcaption id={titleId} className="text-center font-bold text-slate-950">{heading}</figcaption>
			<p id={descriptionId} className="mt-1 text-center text-xs text-slate-600">Vista frontal · L = esquerdo · R = direito</p>
			<svg viewBox={segmentalBodyViewBox} className="mx-auto mt-2 h-auto w-full max-w-md" role="img" aria-labelledby={`${titleId} ${descriptionId}`}>
				<SegmentalBodyFigure classifications={classifications} />
				{items.map((item) => {
					const left = item.side === 'left';
					const { x: textX, y: textY, lineEndX } = item.labelPosition;
					return <g key={`${item.region}-${item.side}`}>
						<path d={`M${item.anchor.x} ${item.anchor.y} L${lineEndX} ${textY + 3}`} stroke={item.state.color} strokeWidth="1.5" fill="none" />
						<circle cx={item.anchor.x} cy={item.anchor.y} r="4.5" fill={item.state.color} stroke="#fff" strokeWidth="1.5" />
						<text x={textX} y={textY - 22} textAnchor={left ? 'start' : 'end'} fill="#334155" fontSize="9" fontWeight="700">{item.label}</text>
						<text x={textX} y={textY - 8} textAnchor={left ? 'start' : 'end'} fill="#0f172a" fontSize="11" fontWeight="700">{number(item.mass_kg, ' kg')}</text>
						<text x={textX} y={textY + 7} textAnchor={left ? 'start' : 'end'} fill="#475569" fontSize="9">{number(item.reference_percentage, '% ref.')}</text>
						<text x={textX} y={textY + 22} textAnchor={left ? 'start' : 'end'} fill={item.state.color} fontSize="9" fontWeight="700">{item.state.marker} {item.state.label}</text>
					</g>;
				})}
			</svg>
			<div className="mt-2 flex flex-wrap justify-center gap-2 text-[11px]">{Object.values(segmentalBodyStates).map((state) => <span key={state.label} className="rounded-full px-2 py-1 font-bold" style={{ color: state.color, backgroundColor: state.fill }}>{state.marker} {state.label}</span>)}</div>
			<div className="mt-3 overflow-x-auto"><table className="w-full min-w-[34rem] border-collapse text-left text-xs"><caption className="sr-only">Tabela equivalente de {heading.toLowerCase()}</caption><thead><tr className="border-b border-slate-200 text-slate-600"><th className="p-2">Região</th><th className="p-2">Lado</th><th className="p-2">Massa</th><th className="p-2">Referência</th><th className="p-2">Classificação</th></tr></thead><tbody>{items.map((item) => <tr key={`${item.region}-${item.side}`} className="border-b border-slate-100"><th scope="row" className="p-2 font-semibold text-slate-800">{item.label}</th><td className="p-2">{item.side === 'left' ? 'L — esquerdo' : item.side === 'right' ? 'R — direito' : 'Centro'}</td><td className="p-2">{number(item.mass_kg, ' kg')}</td><td className="p-2">{number(item.reference_percentage, '%')}</td><td className="p-2 font-semibold" style={{ color: item.state.color }}>{item.state.marker} {item.state.label}</td></tr>)}</tbody></table></div>
		</figure>
	);
}
