import React, { useId, useMemo } from 'react';
import bodyAssetMarkup from '../../../images/bioimpedance/vita-scan-segmental-body-front.svg?raw';
import { segmentalBodyNeutralPalette, segmentalBodyRegions, segmentalBodyStates, segmentalPresentation, segmentalSideMarkers, segmentalState } from './segmentalBodyVisual';

function assetBody(markup) {
	const svgStart = markup.indexOf('<svg');
	const start = svgStart >= 0 ? markup.indexOf('>', svgStart) : -1;
	const end = markup.lastIndexOf('</svg>');
	return start >= 0 && end > start ? markup.slice(start + 1, end) : '';
}

function scopedBodyMarkup({ classifications, idPrefix, presentation }) {
	let markup = assetBody(bodyAssetMarkup).replace(/\bid="([^"]+)"/g, (_match, id) => `id="${idPrefix}-${id}"`);

	segmentalBodyRegions.forEach((region) => {
		const requestedClassification = classifications[region.key];
		const classification = Object.prototype.hasOwnProperty.call(segmentalBodyStates, requestedClassification) ? requestedClassification : 'unknown';
		const state = segmentalState(classification);
		const fill = presentation.visualMode === 'texture' ? `url(#${idPrefix}-${classification})` : state.muscleFill;
		const style = [
			`--vita-body-fill:${fill}`,
			`--vita-body-stroke:${state.color}`,
			`--vita-body-detail:${segmentalBodyNeutralPalette.detail}`,
			`--vita-body-separator:${segmentalBodyNeutralPalette.separator}`,
			state.strokeDasharray ? `stroke-dasharray:${state.strokeDasharray}` : null,
		].filter(Boolean).join(';');
		const pathId = `${idPrefix}-${region.pathId}`;

		markup = markup.replace(`id="${pathId}"`, `id="${pathId}" style="${style}" data-classification="${classification}" data-visual-mode="${presentation.visualMode}"`);
	});

	return markup;
}

function FatTextureDefinitions({ idPrefix }) {
	return (
		<defs>
			{Object.entries(segmentalBodyStates).map(([classification, state]) => (
				<pattern key={classification} id={`${idPrefix}-${classification}`} width="14" height="14" patternUnits="userSpaceOnUse" patternTransform="rotate(-12)">
					<rect width="14" height="14" fill={state.fill} />
					<path d="M0 3.5H14M0 10.5H14" stroke={state.color} strokeWidth="0.7" opacity="0.18" />
					<circle cx="3.5" cy="7" r="1.15" fill={state.color} opacity="0.24" />
					<circle cx="10.5" cy="14" r="1.15" fill={state.color} opacity="0.24" />
				</pattern>
			))}
		</defs>
	);
}

export default function SegmentalBodyFigure({ classifications = {}, type = 'fat' }) {
	const generatedId = useId().replace(/[^a-zA-Z0-9_-]/g, '');
	const patternPrefix = `vita-segmental-${generatedId}`;
	const presentation = segmentalPresentation(type);
	const bodyMarkup = useMemo(() => scopedBodyMarkup({ classifications, idPrefix: patternPrefix, presentation }), [classifications, patternPrefix, presentation]);

	return (
		<g data-component="vita-scan-segmental-body">
			{presentation.visualMode === 'texture' ? <FatTextureDefinitions idPrefix={patternPrefix} /> : null}
			<g dangerouslySetInnerHTML={{ __html: bodyMarkup }} />
			{segmentalSideMarkers.map((marker) => <text key={marker.label} x={marker.x} y={marker.y} fill={segmentalBodyNeutralPalette.sideMarker} fontSize="15" fontWeight="800" textAnchor="middle">{marker.label}</text>)}
		</g>
	);
}
