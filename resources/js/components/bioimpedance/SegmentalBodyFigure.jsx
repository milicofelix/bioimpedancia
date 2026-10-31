import React from 'react';
import bodyAssetUrl from '../../../images/bioimpedance/vita-scan-segmental-body-front.svg';
import { segmentalBodyNeutralPalette, segmentalBodyRegions, segmentalSideMarkers, segmentalState } from './segmentalBodyVisual';

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

export default function SegmentalBodyFigure({ classifications = {} }) {
	return (
		<g data-component="vita-scan-segmental-body">
			<use href={assetReference('body-context')} style={visualStyle()} />
			{segmentalBodyRegions.map((region) => {
				const state = segmentalState(classifications[region.key]);
				return <use key={region.key} href={assetReference(region.pathId)} style={visualStyle(state.fill, state.color)} data-region={region.region} data-side={region.side} data-classification={classifications[region.key] ?? 'unknown'} />;
			})}
			<use href={assetReference('region-separators')} style={visualStyle()} />
			<use href={assetReference('body-details')} style={visualStyle()} />
			{segmentalSideMarkers.map((marker) => <text key={marker.label} x={marker.x} y={marker.y} fill={segmentalBodyNeutralPalette.sideMarker} fontSize="15" fontWeight="800" textAnchor="middle">{marker.label}</text>)}
		</g>
	);
}
