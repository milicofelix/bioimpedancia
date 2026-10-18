import React, { useState } from 'react';
import { createBioimpedanceClient, apiErrorMessage, apiValidationErrors } from '../../../services/bioimpedanceApi';
import { cpfMask, heightMask, phoneMask } from '../../../shared/inputMasks';
import { localDatetimeNow } from '../../../shared/formatters';

const emptyForm = {
	full_name: '',
	birth_date: '',
	biological_sex: 'female',
	height_cm: '',
	phone: '',
	email: '',
	cpf: '',
	consent_accepted: false,
};

function FieldError({ errors, field }) {
	const messages = errors[field];
	if (!Array.isArray(messages) || !messages.length) return null;

	return <p className="mt-1 text-xs font-semibold text-rose-600">{messages[0]}</p>;
}

function fieldClass(hasError) {
	return `mt-1 min-h-12 w-full rounded-xl border bg-white px-3 text-base outline-none transition focus:ring-4 ${hasError ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200 focus:border-emerald-400 focus:ring-emerald-100'}`;
}

export default function ClientCreateForm({ onCancel, onCreated }) {
	const [form, setForm] = useState(emptyForm);
	const [errors, setErrors] = useState({});
	const [message, setMessage] = useState('');
	const [saving, setSaving] = useState(false);

	function update(field, value) {
		const maskedValue = field === 'height_cm'
			? heightMask(value)
			: field === 'phone'
				? phoneMask(value)
				: field === 'cpf'
					? cpfMask(value)
					: value;

		setForm((current) => ({ ...current, [field]: maskedValue }));
		setErrors((current) => ({ ...current, [field]: undefined }));
	}

	async function submit(event) {
		event.preventDefault();
		if (saving) return;

		setSaving(true);
		setErrors({});
		setMessage('');

		try {
			const client = await createBioimpedanceClient(form);
			onCreated(client);
		} catch (error) {
			const validationErrors = apiValidationErrors(error);
			setErrors({
				...validationErrors,
				phone: validationErrors.phone ?? validationErrors.phone_digits,
			});
			setMessage(apiErrorMessage(error, 'Não foi possível cadastrar o cliente. Confira os campos informados.'));
		} finally {
			setSaving(false);
		}
	}

	return (
		<section aria-labelledby="new-client-title">
			<button type="button" onClick={onCancel} className="text-sm font-bold text-emerald-700">← Voltar para clientes</button>
			<div className="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
				<p className="text-xs font-bold uppercase tracking-wide text-emerald-700">Cadastro simplificado</p>
				<h2 id="new-client-title" className="mt-1 text-xl font-bold text-slate-950">Novo cliente</h2>
				<p className="mt-2 text-sm leading-5 text-slate-500">Informe os dados essenciais para iniciar o acompanhamento.</p>

				<form onSubmit={submit} className="mt-5 space-y-4">
					<label className="block text-sm font-semibold text-slate-700">
						Nome completo
						<input autoComplete="name" value={form.full_name} onChange={(event) => update('full_name', event.target.value)} className={fieldClass(errors.full_name)} />
						<FieldError errors={errors} field="full_name" />
					</label>

					<div className="grid grid-cols-2 gap-3">
						<label className="block text-sm font-semibold text-slate-700">
							Nascimento
							<input type="date" max={localDatetimeNow().slice(0, 10)} value={form.birth_date} onChange={(event) => update('birth_date', event.target.value)} className={fieldClass(errors.birth_date)} />
							<FieldError errors={errors} field="birth_date" />
						</label>
						<label className="block text-sm font-semibold text-slate-700">
							Sexo biológico
							<select value={form.biological_sex} onChange={(event) => update('biological_sex', event.target.value)} className={fieldClass(errors.biological_sex)}>
								<option value="female">Feminino</option>
								<option value="male">Masculino</option>
							</select>
							<FieldError errors={errors} field="biological_sex" />
						</label>
					</div>

					<label className="block text-sm font-semibold text-slate-700">
						Altura
						<input inputMode="decimal" placeholder="1,65 ou 165" value={form.height_cm} onChange={(event) => update('height_cm', event.target.value)} className={fieldClass(errors.height_cm)} />
						<FieldError errors={errors} field="height_cm" />
					</label>

					<label className="block text-sm font-semibold text-slate-700">
						Telefone
						<input type="tel" inputMode="tel" autoComplete="tel" placeholder="(00) 00000-0000" value={form.phone} onChange={(event) => update('phone', event.target.value)} className={fieldClass(errors.phone)} />
						<FieldError errors={errors} field="phone" />
					</label>

					<label className="block text-sm font-semibold text-slate-700">
						E-mail
						<input type="email" inputMode="email" autoComplete="email" value={form.email} onChange={(event) => update('email', event.target.value)} className={fieldClass(errors.email)} />
						<FieldError errors={errors} field="email" />
					</label>

					<label className="block text-sm font-semibold text-slate-700">
						CPF opcional
						<input inputMode="numeric" autoComplete="off" placeholder="000.000.000-00" value={form.cpf} onChange={(event) => update('cpf', event.target.value)} className={fieldClass(errors.cpf)} />
						<FieldError errors={errors} field="cpf" />
					</label>

					<label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
						<input type="checkbox" checked={form.consent_accepted} onChange={(event) => update('consent_accepted', event.target.checked)} className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600" />
						<span>O cliente autorizou o registro dos dados para acompanhamento corporal.</span>
					</label>

					{message ? <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700">{message}</div> : null}

					<button type="submit" disabled={saving} className="min-h-12 w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">
						{saving ? 'Cadastrando...' : 'Cadastrar cliente'}
					</button>
				</form>
			</div>
		</section>
	);
}
