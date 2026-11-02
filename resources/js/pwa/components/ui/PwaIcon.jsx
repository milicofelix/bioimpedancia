import React from 'react';

const icons = {
	users: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></>,
	clipboard: <><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5V3h6v1.5M9 11h6M9 15h6"/></>,
	sparkles: <><path d="m12 3-1.2 3.8L7 8l3.8 1.2L12 13l1.2-3.8L17 8l-3.8-1.2L12 3Z"/><path d="m5 14-.8 2.2L2 17l2.2.8L5 20l.8-2.2L8 17l-2.2-.8L5 14ZM19 13l-.8 2.2L16 16l2.2.8L19 19l.8-2.2L22 16l-2.2-.8L19 13Z"/></>,
	fileText: <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h6"/></>,
	grid: <><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></>,
	logOut: <><path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></>,
	wifi: <><path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="20" r="1" fill="currentColor" stroke="none"/></>,
	wifiOff: <><path d="m3 3 18 18M8.5 16a5 5 0 0 1 4.5-1.4M5 12.5a10 10 0 0 1 4-2.2M14.5 10.3a10 10 0 0 1 4.5 2.2"/><circle cx="12" cy="20" r="1" fill="currentColor" stroke="none"/></>,
	plus: <path d="M12 5v14M5 12h14"/>,
	search: <><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></>,
	chevronRight: <path d="m9 18 6-6-6-6"/>,
	user: <><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></>,
	camera: <><path d="M4 7h3l2-3h6l2 3h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"/><circle cx="12" cy="13" r="4"/></>,
	image: <><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 15-4-4L5 20"/></>,
	check: <path d="m5 12 4 4L19 6"/>,
	arrowLeft: <path d="m15 18-6-6 6-6"/>,
	download: <><path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/></>,
	share: <><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/></>,
	copy: <><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></>,
	shield: <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>,
	calendar: <><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></>,
	clock: <><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></>,
	mail: <><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></>,
	phone: <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.4 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>,
	history: <><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></>,
	alertTriangle: <><path d="M10.3 3.7 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></>,
	eye: <><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></>,
	refresh: <><path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.1 9a7 7 0 0 1 11.7-2.6L20 9M4 15l2.2 2.6A7 7 0 0 0 17.9 15"/></>,
	trendUp: <path d="m3 17 6-6 4 4 8-8M15 7h6v6"/>,
	trendDown: <path d="m3 7 6 6 4-4 8 8M15 17h6v-6"/>,
	minus: <path d="M5 12h14"/>,
	info: <><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></>,
	activity: <><path d="M3 12h4l2-6 4 12 2-6h6"/></>,
	x: <><path d="m6 6 12 12M18 6 6 18"/></>,
	scale: <><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 10a4 4 0 0 1 8 0M12 10l2-2"/></>,
	gauge: <><path d="M4 17a8 8 0 1 1 16 0"/><path d="m12 13 4-4M6 17h12"/></>,
	body: <><circle cx="12" cy="4" r="2"/><path d="M8 22l1-7-2-4 2-4h6l2 4-2 4 1 7M9 15h6"/></>,
	droplets: <><path d="M8 3S4 8 4 12a4 4 0 0 0 8 0C12 8 8 3 8 3Z"/><path d="M17 8s-3 3.8-3 6.5a3 3 0 0 0 6 0C20 11.8 17 8 17 8Z"/></>,
	flame: <path d="M12 22c4 0 7-3 7-7 0-3-1.5-5.5-4-8 .2 3-1.2 4.5-2.5 5.5.2-4-1.8-7-4.5-9.5.2 4-3 6-3 10 0 5 3 9 7 9Z"/>,
	bone: <path d="M7.5 8.5 15.5 16.5M5 10a3 3 0 1 1 2-5 3 3 0 1 1 3 3M19 14a3 3 0 1 1-2 5 3 3 0 1 1-3-3"/>,
};

export default function PwaIcon({ name, size = 20, className = '', label }) {
	return (
		<svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" focusable="false" className={className} aria-hidden={label ? undefined : true} aria-label={label || undefined} role={label ? 'img' : undefined}>
			{label ? <title>{label}</title> : null}
			{icons[name] ?? icons.sparkles}
		</svg>
	);
}
