import React, { useId } from 'react';
import bodyAssetUrl from '../../../images/bioimpedance/vita-scan-segmental-body-front.svg';
import { segmentalBodyNeutralPalette, segmentalBodyRegions, segmentalBodyStates, segmentalPresentation, segmentalSideMarkers, segmentalState } from './segmentalBodyVisual';

function assetReference(fragment) {
	return `${bodyAssetUrl}#${fragment}`;
}

function visualStyle(fill = segmentalBodyNeutralPalette.fill, stroke = segmentalBodyNeutralPalette.stroke) {
	return {
		'--vita-body-fill': fill,
		'--vita-body-stroke': stroke,
		'--vita-body-detail': segmentalBodyNeutralPalette.detail,
		'--vita-body-separator': segmentalBodyNeutralPalette.separator,
	};
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

	return (
		<g data-component="vita-scan-segmental-body">
			{presentation.visualMode === 'texture' ? <FatTextureDefinitions idPrefix={patternPrefix} /> : null}
			<use href={assetReference('body-context')} style={visualStyle()} />
			{segmentalBodyRegions.map((region) => {
				const requestedClassification = classifications[region.key];
				const classification = Object.prototype.hasOwnProperty.call(segmentalBodyStates, requestedClassification) ? requestedClassification : 'unknown';
				const state = segmentalState(classification);
				const regionFill = presentation.visualMode === 'texture' ? `url(#${patternPrefix}-${classification})` : state.muscleFill;
				return <use key={region.key} href={assetReference(region.pathId)} style={visualStyle(regionFill, state.color)} data-region={region.region} data-side={region.side} data-classification={classification} data-visual-mode={presentation.visualMode} />;
			})}
			<use href={assetReference('region-separators')} style={visualStyle()} />
			<use href={assetReference('body-details')} style={visualStyle()} />
			{segmentalSideMarkers.map((marker) => <text key={marker.label} x={marker.x} y={marker.y} fill={segmentalBodyNeutralPalette.sideMarker} fontSize="15" fontWeight="800" textAnchor="middle">{marker.label}</text>)}
		</g>
	);
}
