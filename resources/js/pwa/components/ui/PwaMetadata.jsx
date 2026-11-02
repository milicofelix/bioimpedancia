import React from 'react';
import PwaIcon from './PwaIcon';

export function PwaMetadataItem({ icon = 'info', label, value, className = '' }) {
	return <div className={`flex min-w-0 items-start gap-2 ${className}`}><PwaIcon name={icon} size={17} className="mt-0.5 shrink-0" /><div className="min-w-0"><span className="block text-[10px] font-bold uppercase tracking-wide opacity-70">{label}</span><strong className="mt-0.5 block break-words text-sm">{value ?? '-'}</strong></div></div>;
}

export default function PwaMetadata({ items = [], columns = 3, className = '' }) {
	const grids = { 1: 'grid-cols-1', 2: 'grid-cols-1 sm:grid-cols-2', 3: 'grid-cols-1 sm:grid-cols-3', 4: 'grid-cols-2 sm:grid-cols-4' };
	return <dl className={`grid gap-3 ${grids[columns] ?? grids[3]} ${className}`}>{items.map((item) => <div key={item.key ?? item.label}><dt className="sr-only">{item.label}</dt><dd><PwaMetadataItem {...item} /></dd></div>)}</dl>;
}
