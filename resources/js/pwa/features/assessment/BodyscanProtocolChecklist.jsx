import React from 'react';

const confirmations = [
	['profile_confirmed', 'Perfil, sexo, idade e altura conferidos.'],
	['athlete_mode_confirmed', 'Modo atleta conferido.'],
	['electrodes_contact_confirmed', 'Medição realizada com mãos e pés em contato integral com os oito eletrodos.'],
	['comparable_conditions_confirmed', 'Condições semelhantes à avaliação anterior ou ao protocolo de referência, se for a primeira.'],
];

export default function BodyscanProtocolChecklist({ value, onChange, disabled = false }) {
	return (
		<section className="rounded-2xl border border-amber-200 bg-amber-50/60 p-4">
			<p className="text-xs font-bold uppercase tracking-wide text-amber-800">Antes de importar</p>
			<h2 className="mt-1 text-base font-bold text-slate-950">Protocolo da medição</h2>
			<p className="mt-1 text-xs leading-5 text-slate-600">Confirme as condições informadas pelo profissional. Elas serão registradas na auditoria da avaliação.</p>
			<div className="mt-3 space-y-2">
				{confirmations.map(([key, label]) => (
					<label key={key} className="flex min-h-11 items-start gap-3 rounded-xl border border-amber-100 bg-white px-3 py-3 text-sm font-semibold text-slate-800">
						<input type="checkbox" checked={value?.[key] === true} disabled={disabled} onChange={(event) => onChange(key, event.target.checked)} className="mt-0.5 h-5 w-5 shrink-0 accent-amber-700" />
						<span>{label}</span>
					</label>
				))}
			</div>
			<details className="mt-3 text-xs leading-5 text-slate-600">
				<summary className="min-h-11 cursor-pointer py-3 font-bold text-amber-900">Orientações completas do fabricante</summary>
				<ul className="list-disc space-y-1 pl-5"><li>Use superfície dura, seca e plana, com mãos e pés secos e descalços.</li><li>Mantenha contato integral com os eletrodos e segure a barra até o término, cerca de 15 segundos.</li><li>Evite exercício ou sauna nas oito horas anteriores e mantenha jejum mínimo de duas horas.</li><li>Quando possível, meça no mesmo horário, preferencialmente pela manhã e em jejum.</li></ul>
			</details>
		</section>
	);
}
