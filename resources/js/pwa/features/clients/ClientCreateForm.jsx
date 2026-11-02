import React, { useState } from 'react';
import { createBioimpedanceClient, apiErrorMessage, apiValidationErrors } from '../../../services/bioimpedanceApi';
import { cpfMask, heightMask, phoneMask } from '../../../shared/inputMasks';
import { localDatetimeNow } from '../../../shared/formatters';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaAlert from '../../components/ui/PwaAlert';
import PwaFormSection from '../../components/ui/PwaFormSection';
import PwaSectionHeader from '../../components/ui/PwaSectionHeader';
import useActionLock from '../../../hooks/useActionLock';
import ContactImportButton from './ContactImportButton';

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
	return `pwa-focus mt-1 min-h-12 w-full rounded-xl border bg-white px-3 text-base outline-none transition ${hasError ? 'border-rose-300' : 'border-ricosty-100 focus:border-ricosty-400'}`;
}

export default function ClientCreateForm({ onCancel, onCreated }) {
	const runAction = useActionLock();
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

	function importContact(contact) {
		setForm((current) => ({
			...current,
			full_name: contact.full_name || current.full_name,
			phone: contact.phone ? phoneMask(contact.phone) : current.phone,
			email: contact.email || current.email,
		}));
		setErrors((current) => ({ ...current, full_name: undefined, phone: undefined, email: undefined }));
		setMessage('');
	}

	async function submit(event) {
		event.preventDefault();
		return runAction('create-client', async () => {
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
		});
	}

	return (
		<section aria-labelledby="new-client-title">
			<PwaButton type="button" onClick={onCancel} variant="ghost" icon="arrowLeft" className="-ml-3">Voltar para clientes</PwaButton>
			<PwaCard className="mt-3 p-5 sm:p-6">
				<PwaSectionHeader icon="user" eyebrow="Cadastro simplificado" title="Novo cliente" headingId="new-client-title" description="Informe os dados essenciais para iniciar o acompanhamento." />
				<div className="mt-4"><ContactImportButton onImport={importContact} /></div>

				<form onSubmit={submit} className="mt-5 space-y-4">
					<PwaFormSection icon="user" title="Dados pessoais" description="Informações usadas na identificação e nas referências da avaliação." tone="brand">
						<label className="block text-sm font-semibold">Nome completo<input autoComplete="name" value={form.full_name} onChange={(event) => update('full_name', event.target.value)} className={fieldClass(errors.full_name)} /><FieldError errors={errors} field="full_name" /></label>
						<div className="grid gap-3 sm:grid-cols-2"><label className="block text-sm font-semibold">Nascimento<input type="date" max={localDatetimeNow().slice(0, 10)} value={form.birth_date} onChange={(event) => update('birth_date', event.target.value)} className={fieldClass(errors.birth_date)} /><FieldError errors={errors} field="birth_date" /></label><label className="block text-sm font-semibold">Sexo biológico<select value={form.biological_sex} onChange={(event) => update('biological_sex', event.target.value)} className={fieldClass(errors.biological_sex)}><option value="female">Feminino</option><option value="male">Masculino</option></select><FieldError errors={errors} field="biological_sex" /></label></div>
						<label className="block text-sm font-semibold">Altura<input inputMode="decimal" placeholder="1,65 ou 165" value={form.height_cm} onChange={(event) => update('height_cm', event.target.value)} className={fieldClass(errors.height_cm)} /><FieldError errors={errors} field="height_cm" /></label>
					</PwaFormSection>

					<PwaFormSection icon="phone" title="Contato" description="Use ao menos um canal válido para facilitar o acompanhamento.">
						<label className="block text-sm font-semibold">Telefone<input type="tel" inputMode="tel" autoComplete="tel" placeholder="(00) 00000-0000" value={form.phone} onChange={(event) => update('phone', event.target.value)} className={fieldClass(errors.phone)} /><FieldError errors={errors} field="phone" /></label>
						<label className="block text-sm font-semibold">E-mail<input type="email" inputMode="email" autoComplete="email" value={form.email} onChange={(event) => update('email', event.target.value)} className={fieldClass(errors.email)} /><FieldError errors={errors} field="email" /></label>
						<label className="block text-sm font-semibold">CPF opcional<input inputMode="numeric" autoComplete="off" placeholder="000.000.000-00" value={form.cpf} onChange={(event) => update('cpf', event.target.value)} className={fieldClass(errors.cpf)} /><FieldError errors={errors} field="cpf" /></label>
					</PwaFormSection>

					<PwaFormSection icon="shield" title="Consentimento" tone="teal"><label className="flex items-start gap-3 rounded-xl border border-teal-200 bg-white p-3 text-sm text-slate-700"><input type="checkbox" checked={form.consent_accepted} onChange={(event) => update('consent_accepted', event.target.checked)} className="mt-0.5 h-5 w-5 rounded border-teal-300 text-emerald-600" /><span>O cliente autorizou o registro dos dados para acompanhamento corporal.</span></label><FieldError errors={errors} field="consent_accepted" /></PwaFormSection>

					{message ? <PwaAlert tone="error" title="Não foi possível cadastrar">{message}</PwaAlert> : null}

					<PwaButton type="submit" disabled={saving} busy={saving} icon="check" className="w-full">{saving ? 'Cadastrando...' : 'Cadastrar cliente'}</PwaButton>
				</form>
			</PwaCard>
		</section>
	);
}
