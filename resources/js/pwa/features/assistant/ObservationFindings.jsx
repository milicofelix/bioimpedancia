import React from 'react';

export default function ObservationFindings({ findings = [] }) {
	if (!findings.length) return null;
	return <details className="mt-4 rounded-xl border border-slate-200 p-3 text-sm">
		<summary className="pwa-focus min-h-11 cursor-pointer rounded-lg py-2 font-bold text-slate-800">Dados e limites considerados ({findings.length})</summary>
		<ul className="mt-2 space-y-3">{findings.map((finding) => <li key={finding.id} className="rounded-lg bg-slate-50 p-3">
			<p className="font-semibold text-slate-800">{finding.text}</p>
			{finding.priority === 'review' ? <p className="mt-1 text-xs font-bold text-amber-800">Conferir na revisão profissional</p> : null}
			<p className="mt-1 text-xs leading-5 text-slate-600">{(finding.limitations ?? []).join(' ')}</p>
		</li>)}</ul>
	</details>;
}
