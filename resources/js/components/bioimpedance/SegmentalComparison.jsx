import React from 'react';

const types = [
	{ key: 'fat', label: 'Gordura segmentar' },
	{ key: 'muscle', label: 'Equilíbrio muscular' },
];

const regions = [
	{ region: 'arm', side: 'left', label: 'Braço esquerdo' },
	{ region: 'arm', side: 'right', label: 'Braço direito' },
	{ region: 'trunk', side: 'center', label: 'Tronco' },
	{ region: 'leg', side: 'left', label: 'Perna esquerda' },
	{ region: 'leg', side: 'right', label: 'Perna direita' },
];

const number = (value, decimals = 1) => value === null || value === undefined
	? '—'
	: new Intl.NumberFormat('pt-BR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(Number(value));

const find = (measurements, type, region, side) => measurements?.find((item) => item.type === type && item.region === region && item.side === side);

function Difference({ value, unit }) {
	if (value === null) return null;
	const sign = value > 0 ? '+' : '';
	return <span className="whitespace-nowrap rounded-full bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-700">Variação {sign}{number(value, unit === 'kg' ? 2 : 1)} {unit}</span>;
}

export default function SegmentalComparison({ current = [], previous = [], previousDate }) {
	if (!current.length) return null;
	const hasPrevious = previous.length > 0;

	return (
		<section className="mt-5 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5" aria-labelledby="segmental-comparison-title">
			<div className="flex flex-wrap items-start justify-between gap-2">
				<div><h3 id="segmental-comparison-title" className="font-bold text-slate-950">Evolução segmentar</h3><p className="mt-1 text-xs leading-5 text-slate-600">Comparação descritiva entre avaliações Bodyscan Pro; as diferenças não representam, isoladamente, um problema clínico.</p></div>
				<span className="rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-sky-800">{hasPrevious ? `Anterior: ${previousDate ?? 'avaliação anterior'}` : 'Linha de base'}</span>
			</div>

			{!hasPrevious ? <p className="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-950">Esta é a primeira avaliação segmentar deste modelo. Os valores atuais serão usados como referência nas próximas comparações.</p> : null}

			<div className="mt-4 grid gap-4 xl:grid-cols-2">
				{types.map((type) => (
					<div key={type.key} className="overflow-hidden rounded-xl border border-slate-200">
						<h4 className="bg-slate-50 px-3 py-2 text-sm font-bold text-slate-900">{type.label}</h4>
						<div className="divide-y divide-slate-100">
							{regions.map((region) => {
								const currentItem = find(current, type.key, region.region, region.side);
								const previousItem = find(previous, type.key, region.region, region.side);
								if (!currentItem) return null;
								const massDelta = previousItem ? Number(currentItem.mass_kg) - Number(previousItem.mass_kg) : null;
								const referenceDelta = previousItem ? Number(currentItem.reference_percentage) - Number(previousItem.reference_percentage) : null;

								return <div key={`${type.key}-${region.region}-${region.side}`} className="p-3">
									<p className="text-xs font-bold text-slate-800">{region.label}</p>
									<div className="mt-2 grid gap-2 text-xs sm:grid-cols-2">
										<div><span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">Massa</span><span className="mt-1 flex flex-wrap items-center gap-1 text-slate-700">{hasPrevious ? <><span>{number(previousItem?.mass_kg, 2)} kg</span><span aria-hidden="true">→</span></> : null}<strong>{number(currentItem.mass_kg, 2)} kg</strong></span>{hasPrevious ? <span className="mt-1 block"><Difference value={massDelta} unit="kg" /></span> : null}</div>
										<div><span className="block text-[10px] font-bold uppercase tracking-wide text-slate-500">Referência</span><span className="mt-1 flex flex-wrap items-center gap-1 text-slate-700">{hasPrevious ? <><span>{number(previousItem?.reference_percentage)}%</span><span aria-hidden="true">→</span></> : null}<strong>{number(currentItem.reference_percentage)}%</strong></span>{hasPrevious ? <span className="mt-1 block"><Difference value={referenceDelta} unit="p.p." /></span> : null}</div>
									</div>
								</div>;
							})}
						</div>
					</div>
				))}
			</div>

			<div className="mt-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
				{types.flatMap((type) => ['arm', 'leg', 'trunk'].map((region) => <RegionIndicator key={`${type.key}-${region}`} type={type} region={region} current={current} previous={previous} />))}
			</div>
			<div className="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
				{types.flatMap((type) => ['arm', 'leg'].map((region) => <Laterality key={`${type.key}-${region}`} type={type} region={region} current={current} previous={previous} />))}
			</div>
		</section>
	);
}

function RegionIndicator({ type, region, current, previous }) {
	const currentItems = current.filter((item) => item.type === type.key && item.region === region);
	const previousItems = previous.filter((item) => item.type === type.key && item.region === region);
	if (!currentItems.length) return null;
	const average = (items) => items.reduce((sum, item) => sum + Number(item.mass_kg), 0) / items.length;
	const currentAverage = average(currentItems);
	const previousAverage = previousItems.length ? average(previousItems) : null;
	const delta = previousAverage === null ? null : currentAverage - previousAverage;

	return <div className="rounded-xl border border-slate-200 bg-white p-3 text-xs">
		<p className="font-bold text-slate-900">{{ arm: 'Braços', leg: 'Pernas', trunk: 'Tronco' }[region]}</p>
		<p className="mt-0.5 text-[10px] uppercase tracking-wide text-slate-500">{type.key === 'fat' ? 'Gordura' : 'Músculo'}</p>
		<div className="mt-3 flex items-end gap-1" aria-hidden="true"><span className="w-1/2 rounded-t bg-slate-300" style={{ height: previousAverage === null ? '8px' : `${Math.max(8, Math.min(32, previousAverage * 2))}px` }} /><span className="w-1/2 rounded-t bg-sky-500" style={{ height: `${Math.max(8, Math.min(32, currentAverage * 2))}px` }} /></div>
		<p className="mt-2 font-bold text-slate-800">{previousAverage !== null ? `${number(previousAverage, 2)} → ` : ''}{number(currentAverage, 2)} kg</p>
		{delta !== null ? <p className="mt-1 text-slate-500">Variação {delta > 0 ? '+' : ''}{number(delta, 2)} kg</p> : <p className="mt-1 text-slate-500">Base atual</p>}
	</div>;
}

function Laterality({ type, region, current, previous }) {
	const currentLeft = find(current, type.key, region, 'left');
	const currentRight = find(current, type.key, region, 'right');
	const previousLeft = find(previous, type.key, region, 'left');
	const previousRight = find(previous, type.key, region, 'right');
	if (!currentLeft || !currentRight) return null;
	const currentDifference = Math.abs(Number(currentLeft.mass_kg) - Number(currentRight.mass_kg));
	const previousDifference = previousLeft && previousRight ? Math.abs(Number(previousLeft.mass_kg) - Number(previousRight.mass_kg)) : null;
	const currentReferenceDifference = Math.abs(Number(currentLeft.reference_percentage) - Number(currentRight.reference_percentage));
	const previousReferenceDifference = previousLeft && previousRight ? Math.abs(Number(previousLeft.reference_percentage) - Number(previousRight.reference_percentage)) : null;
	const total = Number(currentLeft.mass_kg) + Number(currentRight.mass_kg);
	const leftWidth = total > 0 ? (Number(currentLeft.mass_kg) / total) * 100 : 50;

	return <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs">
		<p className="font-bold text-slate-900">{region === 'arm' ? 'Braços' : 'Pernas'} · {type.key === 'fat' ? 'gordura' : 'músculo'}</p>
		<p className="mt-2 text-slate-600">Esquerdo <strong className="text-slate-900">{number(currentLeft.mass_kg, 2)} kg</strong> · Direito <strong className="text-slate-900">{number(currentRight.mass_kg, 2)} kg</strong></p>
		<p className="mt-1 text-slate-700">Diferença absoluta: <strong>{number(currentDifference, 2)} kg</strong> · <strong>{number(currentReferenceDifference)} p.p.</strong>{previousDifference !== null ? <span className="block text-slate-500">Anterior: {number(previousDifference, 2)} kg · {number(previousReferenceDifference)} p.p.</span> : null}</p>
		<div className="mt-3 flex h-2 overflow-hidden rounded-full bg-slate-200" aria-hidden="true"><span className="bg-sky-400" style={{ width: `${leftWidth}%` }} /><span className="flex-1 bg-violet-400" /></div>
		<p className="mt-1 text-[10px] text-slate-500">Indicador proporcional E/D, sem classificação clínica.</p>
	</div>;
}
