import React, { useEffect, useMemo, useState } from 'react';

const emptyClient = {
	full_name: '',
	birth_date: '',
	biological_sex: 'female',
	height_cm: '',
	phone: '',
	email: '',
	notes: '',
};

const emptyAssessment = {
	evaluated_at: new Date().toISOString().slice(0, 16),
	weight_kg: '',
	scale_bmi: '',
	body_fat_percentage: '',
	skeletal_muscle_percentage: '',
	resting_metabolism_kcal: '',
	body_age: '',
	visceral_fat_level: '',
	notes: '',
};

const sexLabels = {
	female: 'Feminino',
	male: 'Masculino',
};

function Field({ label, children }) {
	return (
		<label className="block">
			<span className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</span>
			{children}
		</label>
	);
}

function inputClass() {
	return 'mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100';
}

function formatDate(value) {
	if (!value) return '-';
	return new Intl.DateTimeFormat('pt-BR', {
		dateStyle: 'short',
		timeStyle: 'short',
	}).format(new Date(value));
}

function formatIssueDate(value) {
	if (!value) return '-';
	return new Intl.DateTimeFormat('pt-BR').format(new Date(value));
}

function numberBr(value, decimals = 1) {
	if (value === null || value === undefined || value === '') return '-';

	return new Intl.NumberFormat('pt-BR', {
		minimumFractionDigits: decimals,
		maximumFractionDigits: decimals,
	}).format(Number(value));
}

function assessmentNumber(id) {
	return String(id ?? 1).padStart(4, '0');
}

function professionalName(name) {
	return name === 'Milico Felix' ? 'Milico Félix' : name;
}

function bmiToneClass(classification) {
	if (!classification) return 'bg-slate-100 text-slate-600';
	if (classification === 'Eutrofia') return 'bg-emerald-100 text-emerald-700';
	if (classification === 'Sobrepeso') return 'bg-amber-100 text-amber-700';
	return 'bg-rose-100 text-rose-700';
}

function indicatorBadgeClass(tone) {
	return {
		good: 'border-emerald-100 bg-emerald-50 text-emerald-700',
		warning: 'border-amber-100 bg-amber-50 text-amber-700',
		danger: 'border-rose-100 bg-rose-50 text-rose-700',
		attention: 'border-sky-100 bg-sky-50 text-sky-700',
		pending: 'border-slate-200 bg-slate-50 text-slate-500',
	}[tone] ?? 'border-slate-200 bg-slate-50 text-slate-500';
}

function normalizeDecimal(value) {
	if (value === null || value === undefined || value === '') return '';
	return String(value).replace(',', '.').replace(/[^\d.]/g, '');
}

function normalizeInteger(value) {
	return String(value ?? '').replace(/\D/g, '');
}

function normalizeHeightToCentimeters(value) {
	const normalized = Number(normalizeDecimal(value));

	if (!normalized) return '';
	return normalized <= 3 ? String(Math.round(normalized * 1000) / 10) : String(normalized);
}

function decimalMask(value, precision = 1) {
	const cleaned = String(value ?? '')
		.replace(/[^\d,.]/g, '')
		.replace('.', ',');
	const [integer = '', decimal = ''] = cleaned.split(',');

	return decimal.length || cleaned.includes(',')
		? `${integer.slice(0, 4)},${decimal.slice(0, precision)}`
		: integer.slice(0, 4);
}

function heightMask(value) {
	const cleaned = String(value ?? '').replace(/[^\d,.]/g, '').replace('.', ',');

	if (cleaned.includes(',')) {
		const [meters = '', centimeters = ''] = cleaned.split(',');
		return `${meters.slice(0, 1)},${centimeters.slice(0, 2)}`;
	}

	return cleaned.slice(0, 3);
}

function phoneMask(value) {
	const digits = normalizeInteger(value).slice(0, 11);

	if (digits.length <= 2) return digits;
	if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;

	return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

export default function DashboardPage({ userName }) {
	const [clients, setClients] = useState([]);
	const [clinic, setClinic] = useState({
		display_name: 'Ricosty Emagrecimento e Estética',
		contact: 'Avaliação corporal e acompanhamento estético',
		logo_initials: 'RS',
		logo_url: '/images/brand/ricosty-logo.png',
	});
	const [selectedClientId, setSelectedClientId] = useState(null);
	const [clientForm, setClientForm] = useState(emptyClient);
	const [assessmentForm, setAssessmentForm] = useState(emptyAssessment);
	const [query, setQuery] = useState('');
	const [loading, setLoading] = useState(true);
	const [savingClient, setSavingClient] = useState(false);
	const [savingAssessment, setSavingAssessment] = useState(false);
	const [errors, setErrors] = useState({});

	useEffect(() => {
		window.axios.get('/bioimpedance').then(({ data }) => {
			setClients(data.clients);
			setClinic(data.clinic);
			setSelectedClientId(data.clients[0]?.id ?? null);
		}).finally(() => setLoading(false));
	}, []);

	const selectedClient = clients.find((client) => client.id === selectedClientId) ?? null;
	const latestAssessment = selectedClient?.assessments?.[0] ?? null;

	const filteredClients = useMemo(() => {
		const term = query.trim().toLowerCase();
		if (!term) return clients;

		return clients.filter((client) => client.full_name.toLowerCase().includes(term));
	}, [clients, query]);

	const calculatedBmi = useMemo(() => {
		const heightCm = selectedClient?.height_cm || normalizeHeightToCentimeters(clientForm.height_cm);
		const height = Number(heightCm) / 100;
		const weight = Number(normalizeDecimal(assessmentForm.weight_kg));

		if (!height || !weight) return null;
		return (weight / (height * height)).toFixed(1);
	}, [assessmentForm.weight_kg, clientForm.height_cm, selectedClient]);

	async function handleLogout() {
		await window.axios.post('/logout', {}, { headers: { Accept: 'application/json' } });
		window.location.assign('/login');
	}

	function updateClient(field, value) {
		const maskedValue = field === 'height_cm'
			? heightMask(value)
			: field === 'phone'
				? phoneMask(value)
				: value;

		setClientForm((current) => ({ ...current, [field]: maskedValue }));
	}

	function updateAssessment(field, value) {
		const integerFields = ['resting_metabolism_kcal', 'body_age'];
		const decimalFields = ['weight_kg', 'scale_bmi', 'body_fat_percentage', 'skeletal_muscle_percentage', 'visceral_fat_level'];
		const maskedValue = integerFields.includes(field)
			? normalizeInteger(value).slice(0, 4)
			: decimalFields.includes(field)
				? decimalMask(value)
				: value;

		setAssessmentForm((current) => ({ ...current, [field]: maskedValue }));
	}

	async function submitClient(event) {
		event.preventDefault();
		setSavingClient(true);
		setErrors({});

		const payload = {
			...clientForm,
			height_cm: normalizeHeightToCentimeters(clientForm.height_cm),
		};

		try {
			const { data } = await window.axios.post('/bioimpedance/clients', payload);
			setClients((current) => [...current, data.client].sort((a, b) => a.full_name.localeCompare(b.full_name)));
			setSelectedClientId(data.client.id);
			setClientForm(emptyClient);
		} catch (error) {
			setErrors(error.response?.data?.errors ?? {});
		} finally {
			setSavingClient(false);
		}
	}

	async function submitAssessment(event) {
		event.preventDefault();
		if (!selectedClient) return;

		setSavingAssessment(true);
		setErrors({});

		const payload = {
			...assessmentForm,
			bioimpedance_client_id: selectedClient.id,
			weight_kg: normalizeDecimal(assessmentForm.weight_kg),
			scale_bmi: normalizeDecimal(assessmentForm.scale_bmi),
			body_fat_percentage: normalizeDecimal(assessmentForm.body_fat_percentage),
			skeletal_muscle_percentage: normalizeDecimal(assessmentForm.skeletal_muscle_percentage),
			visceral_fat_level: normalizeDecimal(assessmentForm.visceral_fat_level),
		};

		try {
			const { data } = await window.axios.post('/bioimpedance/assessments', payload);
			setClients((current) => current.map((client) => (client.id === data.client.id ? data.client : client)));
			setAssessmentForm({ ...emptyAssessment, evaluated_at: new Date().toISOString().slice(0, 16) });
		} catch (error) {
			setErrors(error.response?.data?.errors ?? {});
		} finally {
			setSavingAssessment(false);
		}
	}

	return (
		<main className="min-h-screen bg-slate-50 text-slate-900">
			<header className="no-print border-b border-slate-200 bg-white">
				<div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
					<div className="flex items-center gap-3">
						<img src={clinic.logo_url} alt={clinic.display_name} className="h-14 w-28 object-contain" />
						<div>
							<p className="text-sm font-semibold text-slate-950">{clinic.display_name}</p>
							<p className="text-xs text-slate-500">{clinic.contact}</p>
						</div>
					</div>
					<div className="flex items-center gap-3">
						<span className="text-sm text-slate-500">Profissional: <strong className="text-slate-800">{userName}</strong></span>
						<button type="button" onClick={handleLogout} className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
							Sair
						</button>
					</div>
				</div>
			</header>

			<div className="mx-auto grid max-w-7xl gap-5 px-4 py-5 sm:px-6 lg:grid-cols-[360px_1fr] lg:px-8">
				<aside className="no-print space-y-5">
					<section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
						<div className="flex items-center justify-between">
							<h2 className="text-base font-semibold text-slate-950">Clientes</h2>
							<span className="text-xs text-slate-500">{clients.length} cadastro(s)</span>
						</div>
						<input
							value={query}
							onChange={(event) => setQuery(event.target.value)}
							placeholder="Localizar cliente"
							className={inputClass()}
						/>
						<div className="mt-3 max-h-64 space-y-2 overflow-auto pr-1">
							{loading ? <p className="text-sm text-slate-500">Carregando...</p> : null}
							{filteredClients.map((client) => (
								<button
									type="button"
									key={client.id}
									onClick={() => setSelectedClientId(client.id)}
									className={`w-full rounded-xl border px-3 py-3 text-left transition ${selectedClientId === client.id ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-white hover:bg-slate-50'}`}
								>
									<span className="block text-sm font-semibold text-slate-900">{client.full_name}</span>
									<span className="mt-1 block text-xs text-slate-500">{client.age} anos - {sexLabels[client.biological_sex]} - {client.height_cm} cm</span>
								</button>
							))}
						</div>
					</section>

					<section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
						<h2 className="text-base font-semibold text-slate-950">Cadastrar cliente</h2>
						<form onSubmit={submitClient} className="mt-4 space-y-3">
							<Field label="Nome completo">
								<input value={clientForm.full_name} onChange={(event) => updateClient('full_name', event.target.value)} className={inputClass()} />
							</Field>
							<div className="grid grid-cols-2 gap-3">
								<Field label="Nascimento">
									<input type="date" value={clientForm.birth_date} onChange={(event) => updateClient('birth_date', event.target.value)} className={inputClass()} />
								</Field>
								<Field label="Sexo">
									<select value={clientForm.biological_sex} onChange={(event) => updateClient('biological_sex', event.target.value)} className={inputClass()}>
										<option value="female">Feminino</option>
										<option value="male">Masculino</option>
									</select>
								</Field>
							</div>
							<Field label="Altura">
								<input inputMode="decimal" placeholder="1,74 ou 174" value={clientForm.height_cm} onChange={(event) => updateClient('height_cm', event.target.value)} className={inputClass()} />
							</Field>
							<div className="grid grid-cols-2 gap-3">
								<Field label="Telefone">
									<input inputMode="tel" placeholder="(11) 95141-0140" value={clientForm.phone} onChange={(event) => updateClient('phone', event.target.value)} className={inputClass()} />
								</Field>
								<Field label="E-mail">
									<input type="email" value={clientForm.email} onChange={(event) => updateClient('email', event.target.value)} className={inputClass()} />
								</Field>
							</div>
							<Field label="Observações">
								<textarea value={clientForm.notes} onChange={(event) => updateClient('notes', event.target.value)} rows="3" className={inputClass()} />
							</Field>
							<button type="submit" disabled={savingClient} className="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
								{savingClient ? 'Salvando...' : 'Salvar cliente'}
							</button>
						</form>
					</section>
				</aside>

				<section className="space-y-5">
					<div className="no-print rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
						<div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
							<div>
								<p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Registrar nova avaliação</p>
								<h1 className="mt-1 text-2xl font-semibold text-slate-950">{selectedClient?.full_name ?? 'Selecione um cliente'}</h1>
								<p className="mt-1 text-sm text-slate-500">Digite os valores exibidos na balança Omron antiga após a pesagem.</p>
							</div>
							<div className="rounded-xl bg-slate-100 px-3 py-2 text-sm text-slate-600">
								IMC calculado: <strong className="text-slate-950">{calculatedBmi ?? '-'}</strong>
							</div>
						</div>

						<form onSubmit={submitAssessment} className="mt-5 grid gap-3 md:grid-cols-4">
							<Field label="Data da avaliação">
								<input type="datetime-local" value={assessmentForm.evaluated_at} onChange={(event) => updateAssessment('evaluated_at', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Peso kg">
								<input inputMode="decimal" placeholder="90,2" value={assessmentForm.weight_kg} onChange={(event) => updateAssessment('weight_kg', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="IMC da balança">
								<input inputMode="decimal" placeholder="29,8" value={assessmentForm.scale_bmi} onChange={(event) => updateAssessment('scale_bmi', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Gordura %">
								<input inputMode="decimal" placeholder="28,4" value={assessmentForm.body_fat_percentage} onChange={(event) => updateAssessment('body_fat_percentage', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Músculo %">
								<input inputMode="decimal" placeholder="31,2" value={assessmentForm.skeletal_muscle_percentage} onChange={(event) => updateAssessment('skeletal_muscle_percentage', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="RM kcal">
								<input inputMode="numeric" placeholder="1780" value={assessmentForm.resting_metabolism_kcal} onChange={(event) => updateAssessment('resting_metabolism_kcal', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Idade corporal">
								<input inputMode="numeric" placeholder="47" value={assessmentForm.body_age} onChange={(event) => updateAssessment('body_age', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Gordura visceral">
								<input inputMode="decimal" placeholder="12" value={assessmentForm.visceral_fat_level} onChange={(event) => updateAssessment('visceral_fat_level', event.target.value)} className={inputClass()} />
							</Field>
							<div className="md:col-span-4">
								<Field label="Observação da avaliação">
									<textarea value={assessmentForm.notes} onChange={(event) => updateAssessment('notes', event.target.value)} rows="3" className={inputClass()} />
								</Field>
							</div>
							{Object.keys(errors).length ? (
								<div className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 md:col-span-4">
									Confira os campos obrigatórios e valores digitados.
								</div>
							) : null}
							<button type="submit" disabled={!selectedClient || savingAssessment} className="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 md:col-span-4">
								{savingAssessment ? 'Gerando relatório...' : 'Salvar avaliação e gerar relatório'}
							</button>
						</form>
					</div>

					<Report clinic={clinic} client={selectedClient} assessment={latestAssessment} professional={userName} />
				</section>
			</div>
		</main>
	);
}

function Report({ clinic, client, assessment, professional }) {
	if (!client) {
		return (
			<div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
				Selecione ou cadastre um cliente para visualizar o relatório.
			</div>
		);
	}

	const warnings = assessment?.analysis?.warnings ?? [];
	const analysisIndicators = assessment?.analysis?.indicators ?? {};
	const bmiClassification = analysisIndicators?.bmi?.classification ?? 'Classificação pendente';
	const bodyFat = analysisIndicators?.body_fat ?? {};
	const skeletalMuscle = analysisIndicators?.skeletal_muscle ?? {};
	const visceralFat = analysisIndicators?.visceral_fat ?? {};
	const bodyAgeDelta = assessment?.body_age && client.age ? assessment.body_age - client.age : null;
	const validationTitle = warnings.length ? 'Dados com alertas' : 'Dados validados';
	const validationText = warnings.length ? warnings[0] : 'Nenhum alerta automático identificado.';
	const formattedProfessional = professionalName(professional);

	return (
		<article className="report overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none">
			<div className="no-print mb-4 flex flex-wrap gap-2">
				<button type="button" onClick={() => window.print()} className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
					Imprimir / baixar PDF
				</button>
				<button type="button" onClick={() => navigator.share?.({ title: 'Relatório de bioimpedância', text: client.full_name })} className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
					Compartilhar
				</button>
			</div>

			<header className="report-header relative flex flex-col gap-5 overflow-hidden border-b border-rose-200 bg-gradient-to-br from-rose-50 via-white to-stone-100 px-8 py-8 text-slate-900 sm:flex-row sm:items-center sm:justify-between">
				<div className="pointer-events-none absolute inset-x-0 top-0 h-2 bg-gradient-to-r from-[#d88b9a] via-[#f2c7cf] to-[#4a4a4a]"></div>
				<div className="flex items-center gap-5">
					<img src={clinic.logo_url} alt={clinic.display_name} className="report-logo h-36 w-96 object-contain" />
				</div>
				<div className="text-left sm:text-right">
					<p className="text-xl font-bold uppercase tracking-wide text-[#4a4a4a]">Relatório de bioimpedância</p>
					<p className="mt-5 text-sm text-slate-600">Avaliação Nº {assessmentNumber(assessment?.id)} | {assessment ? formatDate(assessment.evaluated_at) : 'Aguardando avaliação'}</p>
					<p className="mt-4 text-sm text-slate-600">Responsável: {formattedProfessional}</p>
				</div>
			</header>

			<div className="report-body bg-[#fbf8f8] px-8 py-7">
				<section className="report-client-grid grid gap-4 rounded-2xl border border-rose-100 bg-white p-5 shadow-sm sm:grid-cols-[2fr_1fr_1fr_1fr]">
					<div>
						<p className="text-xs font-bold uppercase tracking-wide text-[#b96f7d]">Cliente</p>
						<h2 className="mt-2 text-2xl font-bold text-slate-950">{client.full_name}</h2>
					</div>
					<MiniMetric label="Idade" value={`${client.age} anos`} />
					<MiniMetric label="Sexo" value={sexLabels[client.biological_sex]} />
					<MiniMetric label="Altura" value={`${numberBr(client.height_cm, 0)} cm`} />
				</section>

				{assessment ? (
					<>
						<section className="report-hero-grid mt-6 grid gap-4 rounded-2xl border border-rose-100 bg-[#f8e8eb] p-5 md:grid-cols-[1fr_1fr_1.25fr]">
							<HeroMetric eyebrow="Visão geral" value={numberBr(assessment.weight_kg, 1)} unit="kg" />
							<div>
								<HeroMetric eyebrow="IMC calculado" value={numberBr(assessment.calculated_bmi, 1)} />
								<span className={`mt-3 inline-flex rounded-full px-5 py-2 text-sm font-bold uppercase ${bmiToneClass(bmiClassification)}`}>
									{bmiClassification}
								</span>
							</div>
							<div className="report-body-age border-slate-300 md:border-l md:pl-8">
								<div className="grid grid-cols-[1fr_auto] gap-4">
									<HeroMetric eyebrow="Idade corporal" value={assessment.body_age ?? '-'} unit="anos" compactEyebrow />
									{bodyAgeDelta !== null ? (
										<div className="pt-7 text-right">
											<p className={`text-sm font-bold leading-5 ${bodyAgeDelta > 0 ? 'text-[#b96f7d]' : 'text-emerald-700'}`}>
												{Math.abs(bodyAgeDelta)} anos {bodyAgeDelta > 0 ? 'acima' : 'abaixo'}
											</p>
											<p className="mt-1 text-xs leading-5 text-slate-500">da idade cronológica</p>
										</div>
									) : null}
								</div>
							</div>
						</section>

						<section className="mt-6">
							<h3 className="text-base font-bold uppercase tracking-wide text-[#4a4a4a]">Composição corporal</h3>
							<div className="report-composition-grid mt-4 grid gap-4 md:grid-cols-2">
								<ScaleCard
									title="Gordura corporal"
									value={numberBr(assessment.body_fat_percentage, 1)}
									unit="%"
									badge={bodyFat.classification ?? 'Classificação pendente'}
									tone={bodyFat.tone}
									position={bodyFat.scale?.position}
									labels={bodyFat.scale?.labels ?? ['Baixa', 'Normal', 'Elevada', 'Muito elevada']}
									segments={bodyFat.scale?.segments}
									pending={bodyFat.pending}
								/>
								<ScaleCard
									title="Músculo esquelético"
									value={numberBr(assessment.skeletal_muscle_percentage, 1)}
									unit="%"
									badge={skeletalMuscle.classification ?? 'Classificação pendente'}
									tone={skeletalMuscle.tone}
									position={skeletalMuscle.scale?.position}
									labels={skeletalMuscle.scale?.labels ?? ['Baixo', 'Normal', 'Alto', 'Muito alto']}
									segments={skeletalMuscle.scale?.segments}
									pending={skeletalMuscle.pending}
								/>
								<ScaleCard
									title="Gordura visceral"
									value={numberBr(assessment.visceral_fat_level, 1)}
									unit="nível"
									badge={visceralFat.classification ?? 'Classificação pendente'}
									tone={visceralFat.tone}
									position={visceralFat.scale?.position}
									labels={visceralFat.scale?.labels ?? ['Normal', 'Alta', 'Muito alta']}
									segments={visceralFat.scale?.segments}
									pending={visceralFat.pending}
								/>
								<ScaleCard
									title="Metabolismo basal"
									value={assessment.resting_metabolism_kcal ? new Intl.NumberFormat('pt-BR').format(assessment.resting_metabolism_kcal) : '-'}
									unit="kcal/dia"
									badge="Estimativa diária"
									withoutScale
								/>
							</div>
						</section>

						<section className="mt-6 rounded-2xl border border-rose-100 bg-white p-5">
							<h3 className="text-base font-bold uppercase tracking-wide text-[#b96f7d]">Síntese da avaliação</h3>
							<div className="mt-4 space-y-2 text-sm leading-6 text-slate-700">
								<p>O IMC foi calculado automaticamente com base em peso e altura: {numberBr(assessment.calculated_bmi, 1)} kg/m², classificado como {bmiClassification}.</p>
								<p>A gordura corporal foi classificada como {bodyFat.classification ?? '-'} e o músculo esquelético como {skeletalMuscle.classification ?? '-'}, conforme sexo e idade.</p>
								<p>A idade corporal estimada foi de {assessment.body_age ?? '-'} anos e a gordura visceral foi classificada como {visceralFat.classification ?? '-'}.</p>
							</div>
							{assessment.notes ? <p className="mt-3 text-sm text-slate-600"><strong>Observações:</strong> {assessment.notes}</p> : null}
						</section>

						<section className="report-protocol-grid mt-6 grid gap-4 md:grid-cols-2">
							<div className={`rounded-2xl p-5 ${warnings.length ? 'bg-amber-50' : 'bg-rose-50'}`}>
								<h3 className={`text-sm font-bold uppercase tracking-wide ${warnings.length ? 'text-amber-700' : 'text-[#b96f7d]'}`}>
									{warnings.length ? '!' : '✓'} {validationTitle}
								</h3>
								<p className="mt-4 text-sm leading-6 text-slate-700">{validationText}</p>
							</div>
							<div className="rounded-2xl bg-stone-100 p-5">
								<h3 className="text-sm font-bold uppercase tracking-wide text-slate-900">Protocolo de medição</h3>
								<p className="mt-4 text-sm leading-6 text-slate-600">Equipamento Omron | Entrada manual | Conferência automática de IMC</p>
							</div>
						</section>
					</>
				) : (
					<div className="py-10 text-center text-slate-500">Cadastre a primeira avaliação para gerar o relatório profissional.</div>
				)}

				<footer className="report-footer mt-7 border-t border-slate-200 pt-5 text-xs leading-5 text-slate-500">
					<p className="font-bold uppercase text-slate-900">Observações importantes</p>
					<p className="mt-3">Os resultados de bioimpedância são estimativas e podem variar conforme hidratação, alimentação, ciclo hormonal, medicamentos e condições de medição. Este documento não substitui avaliação médica ou nutricional.</p>
					<div className="mt-7 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
						<div>
							<p className="font-bold text-slate-900">Ricosty Emagrecimento e Estética</p>
							<p className="mt-1">Avaliação corporal e acompanhamento estético</p>
						</div>
						<p>Relatório nº {assessmentNumber(assessment?.id)} • Emitido em {assessment ? formatIssueDate(assessment.evaluated_at) : '-'}</p>
					</div>
				</footer>
			</div>
		</article>
	);
}

function HeroMetric({ eyebrow, value, unit, compactEyebrow = false }) {
	return (
		<div>
			<p className={`${compactEyebrow ? 'max-w-none' : ''} text-xs font-bold uppercase tracking-wide text-[#9f5f6b]`}>{eyebrow}</p>
			<p className="mt-3 text-5xl font-bold leading-none text-slate-900">
				{value}
				{unit ? <span className="ml-1 text-lg text-slate-500">{unit}</span> : null}
			</p>
		</div>
	);
}

function ScaleCard({ title, value, unit, badge, tone, position = 50, labels = [], segments, withoutScale = false, pending = false }) {
	const scaleSegments = segments ?? [
		{ className: 'bg-blue-500', width: 25 },
		{ className: 'bg-emerald-500', width: 25 },
		{ className: 'bg-amber-500', width: 25 },
		{ className: 'bg-rose-500', width: 25 },
	];

	return (
		<div className="rounded-2xl border border-rose-100 bg-white p-5 shadow-sm">
			<div className="flex items-start justify-between gap-4">
				<p className="text-sm font-bold uppercase tracking-wide text-slate-500">{title}</p>
				<span className={`rounded-full border px-4 py-2 text-xs font-bold ${indicatorBadgeClass(tone)}`}>{badge}</span>
			</div>
			<p className="mt-4 text-4xl font-bold leading-none text-slate-900">
				{value} <span className="text-lg text-slate-500">{unit}</span>
			</p>
			{withoutScale ? null : (
				<div className="mt-5">
					<div className="relative h-5">
						<div className={`absolute top-2 flex h-2 w-full overflow-hidden rounded-sm ${pending ? 'opacity-70 grayscale' : ''}`}>
							{scaleSegments.map((segment, index) => (
								<span
									key={`${segment.className}-${index}`}
									className={pending ? 'bg-slate-300' : segment.className}
									style={{ width: `${segment.width}%` }}
								></span>
							))}
						</div>
						{pending ? null : <span className="absolute top-0 h-0 w-0 -translate-x-1/2 border-x-[7px] border-t-[10px] border-x-transparent border-t-slate-900" style={{ left: `${position}%` }}></span>}
					</div>
					<div className="mt-1 flex text-xs text-slate-500">
						{scaleSegments.map((segment, index) => {
							const label = segment.label ?? labels[index];

							if (!label) {
								return null;
							}

							return (
								<span key={`${label}-${index}`} className="inline-flex min-w-0 items-center justify-center gap-1 px-1 text-center leading-tight" style={{ width: `${segment.width}%` }}>
									<span className={`h-1.5 w-1.5 rounded-full ${pending ? 'bg-slate-300' : scaleSegments[index]?.className ?? 'bg-slate-300'}`}></span>
									{label}
								</span>
							);
						})}
					</div>
				</div>
			)}
		</div>
	);
}

function MiniMetric({ label, value }) {
	return (
		<div>
			<p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
			<p className="mt-2 text-lg font-bold text-slate-950">{value}</p>
		</div>
	);
}
