import React, { useId, useMemo } from 'react';
import SegmentalBodyFigure from './SegmentalBodyFigure';
import { SegmentalCompactRegions, SegmentalDataTable, SegmentalMapLegend } from './SegmentalBodyMapDetails';
import { formatSegmentalValue, segmentalBodyRegions, segmentalBodyViewBox, segmentalPresentation, segmentalState } from './segmentalBodyVisual';

export default function SegmentalBodyMap({ measurements = [], type = 'fat', title }) {
	const titleId = useId();
	const descriptionId = useId();
	const items = useMemo(() => segmentalBodyRegions.map((visualRegion) => {
		const measurement = measurements.find((item) => item.type === type && item.region === visualRegion.region && item.side === visualRegion.side) ?? {};
		return { ...visualRegion, ...measurement, state: segmentalState(measurement.classification) };
	}), [measurements, type]);
	const classifications = useMemo(() => Object.fromEntries(items.map((item) => [item.key, item.classification])), [items]);
	const presentation = segmentalPresentation(type);
	const heading = title ?? presentation.label;

	return (
		<figure className="w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-ricosty-100 bg-gradient-to-b from-white to-ricosty-50/40 p-3 print:break-inside-avoid" aria-labelledby={titleId} aria-describedby={descriptionId}>
			<figcaption id={titleId} className="text-center font-bold text-slate-950">{heading}</figcaption>
			<p id={descriptionId} className="mx-auto mt-1 max-w-2xl break-words text-center text-xs leading-5 text-slate-600">Vista frontal · L = esquerdo · R = direito. {presentation.description}</p>
			<svg viewBox="42 0 276 640" className="mx-auto mt-2 h-auto max-h-[28rem] w-full max-w-xs sm:hidden" aria-hidden="true" focusable="false">
				<SegmentalBodyFigure classifications={classifications} type={type} />
			</svg>
			<SegmentalCompactRegions items={items} />
			<svg viewBox={segmentalBodyViewBox} className="mx-auto mt-2 hidden h-auto w-full max-w-md sm:block" role="img" aria-labelledby={`${titleId} ${descriptionId}`}>
				<SegmentalBodyFigure classifications={classifications} type={type} />
				{items.map((item) => {
					const left = item.side === 'left';
					const { x: textX, y: textY, lineEndX } = item.labelPosition;
					return <g key={`${item.region}-${item.side}`}>
						<path d={`M${item.anchor.x} ${item.anchor.y} L${lineEndX} ${textY + 3}`} stroke={item.state.color} strokeWidth="1.5" fill="none" />
						<circle cx={item.anchor.x} cy={item.anchor.y} r="4.5" fill={item.state.color} stroke="#fff" strokeWidth="1.5" />
						<text x={textX} y={textY - 22} textAnchor={left ? 'start' : 'end'} fill="#334155" fontSize="9" fontWeight="700">{item.label}</text>
						<text x={textX} y={textY - 8} textAnchor={left ? 'start' : 'end'} fill="#0f172a" fontSize="11" fontWeight="700">{formatSegmentalValue(item.mass_kg, ' kg')}</text>
						<text x={textX} y={textY + 7} textAnchor={left ? 'start' : 'end'} fill="#475569" fontSize="9">{formatSegmentalValue(item.reference_percentage, '% ref.')}</text>
						<text x={textX} y={textY + 22} textAnchor={left ? 'start' : 'end'} fill={item.state.color} fontSize="9" fontWeight="700">{item.state.marker} {item.state.label}</text>
					</g>;
				})}
			</svg>
			<SegmentalMapLegend />
			<p className="mt-2 text-center text-[11px] leading-5 text-slate-500">As cores reproduzem a classificação apresentada pelo aplicativo e não representam diagnóstico. A forma dos membros não varia conforme os valores.</p>
			<SegmentalDataTable items={items} heading={heading} />
		</figure>
	);
}
