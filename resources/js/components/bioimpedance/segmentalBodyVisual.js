export const segmentalBodyViewBox = '0 0 360 640';

export const segmentalBodyRegions = [
	{ key: 'arm|left', region: 'arm', side: 'left', label: 'Braço esquerdo', pathId: 'region-arm-left', anchor: { x: 98, y: 245 }, labelPosition: { x: 8, y: 220, lineEndX: 76 } },
	{ key: 'arm|right', region: 'arm', side: 'right', label: 'Braço direito', pathId: 'region-arm-right', anchor: { x: 262, y: 245 }, labelPosition: { x: 352, y: 220, lineEndX: 284 } },
	{ key: 'trunk|center', region: 'trunk', side: 'center', label: 'Tronco', pathId: 'region-trunk-center', anchor: { x: 180, y: 270 }, labelPosition: { x: 352, y: 325, lineEndX: 284 } },
	{ key: 'leg|left', region: 'leg', side: 'left', label: 'Perna esquerda', pathId: 'region-leg-left', anchor: { x: 145, y: 465 }, labelPosition: { x: 8, y: 500, lineEndX: 102 } },
	{ key: 'leg|right', region: 'leg', side: 'right', label: 'Perna direita', pathId: 'region-leg-right', anchor: { x: 215, y: 465 }, labelPosition: { x: 352, y: 500, lineEndX: 258 } },
];

export const segmentalBodyStates = {
	low: { label: 'Abaixo da referência', marker: '↓', color: '#2563eb', fill: '#dbeafe', muscleFill: '#93c5fd', printFill: '#d9d9d9' },
	standard: { label: 'Padrão', marker: '●', color: '#047857', fill: '#d1fae5', muscleFill: '#6ee7b7', printFill: '#bfbfbf' },
	high: { label: 'Alto', marker: '↑', color: '#be123c', fill: '#ffe4e6', muscleFill: '#fda4af', printFill: '#969696' },
	unknown: { label: 'Não identificado', marker: '?', color: '#475569', fill: '#e2e8f0', muscleFill: '#cbd5e1', printFill: '#eeeeee' },
};

export const segmentalAnalysisPresentation = {
	fat: {
		label: 'Gordura segmentar',
		description: 'O preenchimento texturizado diferencia as classificações informadas pelo aplicativo em cada região.',
		visualMode: 'texture',
	},
	muscle: {
		label: 'Equilíbrio muscular',
		description: 'O preenchimento sólido facilita a comparação visual entre os lados esquerdo e direito.',
		visualMode: 'solid',
	},
};

export const segmentalBodyNeutralPalette = {
	fill: '#f4e4e8',
	stroke: '#934c60',
	detail: '#c98d9b',
	separator: '#ffffff',
	sideMarker: '#7f1d3f',
};

export const segmentalSideMarkers = [
	{ label: 'L', x: 111, y: 396 },
	{ label: 'R', x: 241, y: 396 },
];

export function segmentalState(classification) {
	return segmentalBodyStates[classification] ?? segmentalBodyStates.unknown;
}

export function segmentalPresentation(type) {
	return segmentalAnalysisPresentation[type] ?? segmentalAnalysisPresentation.fat;
}
