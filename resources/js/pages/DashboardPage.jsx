import React, { useEffect, useMemo, useRef, useState } from 'react';

const emptyClient = {
	full_name: '',
	birth_date: '',
	biological_sex: 'female',
	height_cm: '',
	phone: '',
	email: '',
	cpf: '',
	address: '',
	emergency_contact_name: '',
	emergency_contact_phone: '',
	consent_accepted: false,
	next_assessment_at: '',
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

const defaultClinic = {
	display_name: 'Ricosty Emagrecimento e Estética',
	legal_name: 'Ricosty Emagrecimento e Estética',
	document: '',
	phone: '',
	whatsapp: '',
	email: '',
	address: '',
	instagram: '',
	website: '',
	primary_color: '#d88b9a',
	secondary_color: '#4a4a4a',
	logo_url: '/images/brand/ricosty-logo.png',
	contact: 'Avaliação corporal e acompanhamento estético',
	footer_text: 'Ricosty Emagrecimento e Estética - Avaliação corporal e acompanhamento estético',
	technical_notice: 'Os resultados de bioimpedância são estimativas e podem variar conforme hidratação, alimentação, ciclo hormonal, medicamentos e condições de medição. Este documento não substitui avaliação médica ou nutricional.',
};

const emptyUserForm = {
	name: '',
	email: '',
	password: '',
	role: 'professional',
};

const sexLabels = {
	female: 'Feminino',
	male: 'Masculino',
};

const roleLabels = {
	admin: 'Administrador',
	professional: 'Profissional',
	reception: 'Recepção',
	viewer: 'Visualização',
};

const evolutionMetrics = [
	{ key: 'weight_kg', label: 'Peso', unit: 'kg', decimals: 1, lowerIsBetter: true },
	{ key: 'calculated_bmi', label: 'IMC', unit: 'kg/m²', decimals: 1, lowerIsBetter: true },
	{ key: 'body_fat_percentage', label: 'Gordura', unit: 'p.p.', valueUnit: '%', decimals: 1, lowerIsBetter: true },
	{ key: 'skeletal_muscle_percentage', label: 'Músculo', unit: 'p.p.', valueUnit: '%', decimals: 1, lowerIsBetter: false },
	{ key: 'visceral_fat_level', label: 'Visceral', unit: 'níveis', valueUnit: 'nível', decimals: 0, lowerIsBetter: true },
	{ key: 'body_age', label: 'Idade corporal', unit: 'anos', decimals: 0, lowerIsBetter: true },
];

const evolutionPeriods = [
	{ key: '30', label: '30 dias', days: 30 },
	{ key: '90', label: '90 dias', days: 90 },
	{ key: '180', label: '180 dias', days: 180 },
	{ key: 'all', label: 'Tudo', days: null },
];

function Field({ label, children }) {
	return (
		<label className="block">
			<span className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</span>
			{children}
		</label>
	);
}

function inputClass() {
	return 'mt-2 min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 sm:text-sm';
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

function toDatetimeLocal(value) {
	if (!value) return new Date().toISOString().slice(0, 16);

	const date = new Date(value);
	const timezoneOffset = date.getTimezoneOffset() * 60000;

	return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16);
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

function sortAssessmentsAscending(assessments = []) {
	return [...assessments]
		.filter((assessment) => !assessment.is_canceled)
		.sort((a, b) => new Date(a.evaluated_at) - new Date(b.evaluated_at));
}

function metricValue(assessment, key) {
	const value = Number(assessment?.[key]);
	return Number.isFinite(value) ? value : null;
}

function signedNumber(value, decimals = 1) {
	if (value === null || value === undefined || Number.isNaN(value)) return '-';
	const signal = value > 0 ? '+' : '';

	return `${signal}${numberBr(value, decimals)}`;
}

function deltaTone(metric, delta) {
	if (delta === null || delta === 0) return 'neutral';
	const improved = metric.lowerIsBetter ? delta < 0 : delta > 0;

	return improved ? 'good' : 'attention';
}

function deltaClass(tone) {
	return {
		good: 'bg-emerald-50 text-emerald-700',
		attention: 'bg-rose-50 text-rose-700',
		neutral: 'bg-slate-100 text-slate-600',
	}[tone] ?? 'bg-slate-100 text-slate-600';
}

function formatMetricCurrent(assessment, metric) {
	const value = metricValue(assessment, metric.key);
	if (value === null) return '-';

	return `${numberBr(value, metric.decimals)} ${metric.valueUnit ?? metric.unit}`;
}

function formatMetricDelta(delta, metric) {
	if (delta === null) return '-';

	return `${signedNumber(delta, metric.decimals)} ${metric.unit}`;
}

function comparisonSummary(previous, current) {
	if (!previous || !current) {
		return 'Cadastre pelo menos duas avaliações para gerar a síntese comparativa.';
	}

	const metricDelta = (key) => {
		const previousValue = metricValue(previous, key);
		const currentValue = metricValue(current, key);

		return previousValue !== null && currentValue !== null ? currentValue - previousValue : null;
	};
	const weightDelta = metricDelta('weight_kg');
	const fatDelta = metricDelta('body_fat_percentage');
	const muscleDelta = metricDelta('skeletal_muscle_percentage');
	const parts = [];

	if (Number.isFinite(weightDelta)) {
		parts.push(`${weightDelta < 0 ? 'redução' : weightDelta > 0 ? 'aumento' : 'manutenção'} de ${numberBr(Math.abs(weightDelta), 1)} kg no peso`);
	}

	if (Number.isFinite(fatDelta)) {
		parts.push(`${fatDelta < 0 ? 'redução' : fatDelta > 0 ? 'aumento' : 'manutenção'} de ${numberBr(Math.abs(fatDelta), 1)} ponto percentual na gordura corporal`);
	}

	if (Number.isFinite(muscleDelta)) {
		parts.push(`${muscleDelta > 0 ? 'aumento' : muscleDelta < 0 ? 'redução' : 'manutenção'} de ${numberBr(Math.abs(muscleDelta), 1)} ponto percentual no músculo esquelético`);
	}

	return parts.length
		? `Desde a avaliação anterior, houve ${parts.join(', ')}.`
		: 'Não há indicadores suficientes para gerar a síntese comparativa.';
}

function sparklinePoints(values, width = 150, height = 42) {
	const validValues = values.filter((value) => Number.isFinite(value));
	if (!validValues.length) return '';
	if (validValues.length === 1) return `0,${height / 2} ${width},${height / 2}`;

	const min = Math.min(...validValues);
	const max = Math.max(...validValues);
	const range = max - min || 1;

	return validValues.map((value, index) => {
		const x = (index / (validValues.length - 1)) * width;
		const y = height - ((value - min) / range) * height;

		return `${x.toFixed(1)},${y.toFixed(1)}`;
	}).join(' ');
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

function cpfMask(value) {
	const digits = normalizeInteger(value).slice(0, 11);

	if (digits.length <= 3) return digits;
	if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`;
	if (digits.length <= 9) return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;

	return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
}

function clientFormFromClient(client) {
	if (!client) return emptyClient;

	return {
		full_name: client.full_name ?? '',
		birth_date: client.birth_date ?? '',
		biological_sex: client.biological_sex ?? 'female',
		height_cm: client.height_cm ? numberBr(client.height_cm, 0) : '',
		phone: client.phone ?? '',
		email: client.email ?? '',
		cpf: client.cpf ? cpfMask(client.cpf) : '',
		address: client.address ?? '',
		emergency_contact_name: client.emergency_contact_name ?? '',
		emergency_contact_phone: client.emergency_contact_phone ? phoneMask(client.emergency_contact_phone) : '',
		consent_accepted: Boolean(client.consent_accepted_at),
		next_assessment_at: client.next_assessment_at ?? '',
		notes: client.notes ?? '',
	};
}

function assessmentFormFromAssessment(assessment) {
	if (!assessment) return emptyAssessment;

	return {
		evaluated_at: toDatetimeLocal(assessment.evaluated_at),
		weight_kg: numberBr(assessment.weight_kg, 1),
		scale_bmi: assessment.scale_bmi == null ? '' : numberBr(assessment.scale_bmi, 1),
		body_fat_percentage: assessment.body_fat_percentage == null ? '' : numberBr(assessment.body_fat_percentage, 1),
		skeletal_muscle_percentage: assessment.skeletal_muscle_percentage == null ? '' : numberBr(assessment.skeletal_muscle_percentage, 1),
		resting_metabolism_kcal: assessment.resting_metabolism_kcal ?? '',
		body_age: assessment.body_age ?? '',
		visceral_fat_level: assessment.visceral_fat_level == null ? '' : String(Math.trunc(assessment.visceral_fat_level)),
		notes: assessment.notes ?? '',
	};
}

function assessmentDraftKey(clientId) {
	return clientId ? `ricosty:assessment-draft:${clientId}` : null;
}

function hasAssessmentDraftData(form) {
	return [
		'weight_kg',
		'scale_bmi',
		'body_fat_percentage',
		'skeletal_muscle_percentage',
		'resting_metabolism_kcal',
		'body_age',
		'visceral_fat_level',
		'notes',
	].some((field) => String(form[field] ?? '').trim() !== '');
}

function focusNextFormField(event) {
	if (event.key !== 'Enter' || event.target.tagName === 'TEXTAREA') return;

	const form = event.currentTarget;
	const fields = Array.from(form.querySelectorAll('input, select, textarea, button'))
		.filter((field) => !field.disabled && field.type !== 'hidden');
	const currentIndex = fields.indexOf(event.target);
	const nextField = fields[currentIndex + 1];

	if (nextField) {
		event.preventDefault();
		nextField.focus();
	}
}

function clinicFormFromClinic(clinic) {
	return {
		...defaultClinic,
		...Object.fromEntries(Object.entries(clinic ?? {}).map(([key, value]) => [key, value ?? ''])),
	};
}

export default function DashboardPage({ userName }) {
	const assessmentSectionRef = useRef(null);
	const [clients, setClients] = useState([]);
	const [clinic, setClinic] = useState(defaultClinic);
	const [clinicForm, setClinicForm] = useState(defaultClinic);
	const [currentUser, setCurrentUser] = useState(null);
	const [users, setUsers] = useState([]);
	const [auditEvents, setAuditEvents] = useState([]);
	const [adminDashboard, setAdminDashboard] = useState(null);
	const [userForm, setUserForm] = useState(emptyUserForm);
	const [selectedClientId, setSelectedClientId] = useState(null);
	const [selectedAssessmentId, setSelectedAssessmentId] = useState(null);
	const [clientForm, setClientForm] = useState(emptyClient);
	const [assessmentForm, setAssessmentForm] = useState(emptyAssessment);
	const [assessmentMode, setAssessmentMode] = useState('create');
	const [editingAssessmentId, setEditingAssessmentId] = useState(null);
	const [assessmentChangeReason, setAssessmentChangeReason] = useState('');
	const [query, setQuery] = useState('');
	const [showInactive, setShowInactive] = useState(false);
	const [clientMode, setClientMode] = useState('create');
	const [editingClientId, setEditingClientId] = useState(null);
	const [loading, setLoading] = useState(true);
	const [savingClient, setSavingClient] = useState(false);
	const [savingAssessment, setSavingAssessment] = useState(false);
	const [savingClinic, setSavingClinic] = useState(false);
	const [savingUser, setSavingUser] = useState(false);
	const [sharingAssessment, setSharingAssessment] = useState(false);
	const [shareResult, setShareResult] = useState(null);
	const [refreshingDashboard, setRefreshingDashboard] = useState(false);
	const [isOnline, setIsOnline] = useState(() => navigator.onLine);
	const [assessmentDraftSavedAt, setAssessmentDraftSavedAt] = useState(null);
	const [errors, setErrors] = useState({});
	const [clinicErrors, setClinicErrors] = useState({});
	const [userErrors, setUserErrors] = useState({});

	useEffect(() => {
		window.axios.get('/bioimpedance').then(({ data }) => {
			setClients(data.clients);
			setClinic(data.clinic);
			setClinicForm(clinicFormFromClinic(data.clinic));
			setCurrentUser(data.current_user);
			setUsers(data.users ?? []);
			setAuditEvents(data.audit_events ?? []);
			setAdminDashboard(data.admin_dashboard ?? null);
			setSelectedClientId(data.clients[0]?.id ?? null);
			setSelectedAssessmentId(data.clients[0]?.assessments?.[0]?.id ?? null);
		}).finally(() => setLoading(false));
	}, []);

	useEffect(() => {
		const updateConnectionStatus = () => setIsOnline(navigator.onLine);

		window.addEventListener('online', updateConnectionStatus);
		window.addEventListener('offline', updateConnectionStatus);

		return () => {
			window.removeEventListener('online', updateConnectionStatus);
			window.removeEventListener('offline', updateConnectionStatus);
		};
	}, []);

	useEffect(() => {
		if (!selectedClientId || assessmentMode !== 'create') return;

		const key = assessmentDraftKey(selectedClientId);
		const storedDraft = key ? window.localStorage.getItem(key) : null;
		if (!storedDraft) {
			setAssessmentDraftSavedAt(null);
			return;
		}

		try {
			const parsedDraft = JSON.parse(storedDraft);
			if (parsedDraft?.form && hasAssessmentDraftData(parsedDraft.form)) {
				setAssessmentForm(parsedDraft.form);
				setAssessmentDraftSavedAt(parsedDraft.saved_at ?? null);
			}
		} catch {
			window.localStorage.removeItem(key);
			setAssessmentDraftSavedAt(null);
		}
	}, [assessmentMode, selectedClientId]);

	useEffect(() => {
		if (!selectedClientId || assessmentMode !== 'create') return;

		const key = assessmentDraftKey(selectedClientId);
		if (!key) return;

		if (!hasAssessmentDraftData(assessmentForm)) {
			window.localStorage.removeItem(key);
			setAssessmentDraftSavedAt(null);
			return;
		}

		const timeout = window.setTimeout(() => {
			const savedAt = new Date().toISOString();
			window.localStorage.setItem(key, JSON.stringify({ form: assessmentForm, saved_at: savedAt }));
			setAssessmentDraftSavedAt(savedAt);
		}, 500);

		return () => window.clearTimeout(timeout);
	}, [assessmentForm, assessmentMode, selectedClientId]);

	useEffect(() => {
		const warnBeforeExit = (event) => {
			if (assessmentMode !== 'create' || !hasAssessmentDraftData(assessmentForm)) return;

			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener('beforeunload', warnBeforeExit);

		return () => window.removeEventListener('beforeunload', warnBeforeExit);
	}, [assessmentForm, assessmentMode]);

	const selectedClient = clients.find((client) => client.id === selectedClientId) ?? null;
	const selectedAssessment = selectedClient?.assessments?.find((assessment) => assessment.id === selectedAssessmentId)
		?? selectedClient?.assessments?.[0]
		?? null;

	const filteredClients = useMemo(() => {
		const term = query.trim().toLowerCase();
		const source = showInactive ? clients : clients.filter((client) => client.is_active);
		if (!term) return source;

		return source.filter((client) => [
			client.full_name,
			client.phone,
			client.email,
			client.cpf,
		].some((value) => String(value ?? '').toLowerCase().includes(term)));
	}, [clients, query, showInactive]);

	const calculatedBmi = useMemo(() => {
		const heightCm = selectedClient?.height_cm || normalizeHeightToCentimeters(clientForm.height_cm);
		const height = Number(heightCm) / 100;
		const weight = Number(normalizeDecimal(assessmentForm.weight_kg));

		if (!height || !weight) return null;
		return (weight / (height * height)).toFixed(1);
	}, [assessmentForm.weight_kg, clientForm.height_cm, selectedClient]);
	const canWrite = currentUser?.role !== 'viewer';
	const canAdmin = Boolean(currentUser?.is_admin);

	async function handleLogout() {
		await window.axios.post('/logout', {}, { headers: { Accept: 'application/json' } });
		window.location.assign('/login');
	}

	async function refreshAdminDashboard() {
		if (!canAdmin || refreshingDashboard) return;

		setRefreshingDashboard(true);
		try {
			const { data } = await window.axios.get('/bioimpedance/admin-dashboard');
			setAdminDashboard(data.admin_dashboard ?? null);
		} finally {
			setRefreshingDashboard(false);
		}
	}

	function updateClient(field, value) {
		const maskedValue = field === 'height_cm'
			? heightMask(value)
			: field === 'phone'
				? phoneMask(value)
				: field === 'emergency_contact_phone'
					? phoneMask(value)
					: field === 'cpf'
						? cpfMask(value)
						: value;

		setClientForm((current) => ({ ...current, [field]: maskedValue }));
	}

	function startNewClient(prefillName = '') {
		setClientMode('create');
		setEditingClientId(null);
		setClientForm({ ...emptyClient, full_name: prefillName });
		setErrors({});
	}

	function startEditClient(client) {
		setClientMode('edit');
		setEditingClientId(client.id);
		setClientForm(clientFormFromClient(client));
		setErrors({});
	}

	function selectClient(client) {
		setSelectedClientId(client.id);
		setSelectedAssessmentId(client.assessments?.[0]?.id ?? null);
		setShareResult(null);
		setAssessmentMode('create');
		setEditingAssessmentId(null);
		setAssessmentForm({ ...emptyAssessment, evaluated_at: new Date().toISOString().slice(0, 16) });
		setAssessmentChangeReason('');
		window.setTimeout(() => assessmentSectionRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80);
	}

	function selectClientById(clientId) {
		const client = clients.find((item) => item.id === clientId);
		if (client) {
			selectClient(client);
		}
	}

	function startNewAssessment() {
		setAssessmentMode('create');
		setEditingAssessmentId(null);
		setShareResult(null);
		setAssessmentForm({ ...emptyAssessment, evaluated_at: new Date().toISOString().slice(0, 16) });
		setAssessmentChangeReason('');
		setAssessmentDraftSavedAt(null);
		setErrors({});
	}

	function clearAssessmentDraft() {
		const key = assessmentDraftKey(selectedClientId);
		if (key) {
			window.localStorage.removeItem(key);
		}
		setAssessmentForm({ ...emptyAssessment, evaluated_at: new Date().toISOString().slice(0, 16) });
		setAssessmentDraftSavedAt(null);
	}

	function startEditAssessment(assessment) {
		if (!assessment || assessment.is_canceled) return;

		setAssessmentMode('edit');
		setEditingAssessmentId(assessment.id);
		setAssessmentForm(assessmentFormFromAssessment(assessment));
		setAssessmentChangeReason('');
		setErrors({});
	}

	function duplicateAssessment(assessment) {
		if (!assessment) return;

		setAssessmentMode('create');
		setEditingAssessmentId(null);
		setAssessmentForm({
			...assessmentFormFromAssessment(assessment),
			evaluated_at: new Date().toISOString().slice(0, 16),
			notes: '',
		});
		setAssessmentChangeReason('');
		setErrors({});
	}

	function updateAssessment(field, value) {
		const integerFields = ['resting_metabolism_kcal', 'body_age', 'visceral_fat_level'];
		const decimalFields = ['weight_kg', 'scale_bmi', 'body_fat_percentage', 'skeletal_muscle_percentage'];
		const maskedValue = integerFields.includes(field)
			? normalizeInteger(value).slice(0, 4)
			: decimalFields.includes(field)
				? decimalMask(value)
				: value;

		setAssessmentForm((current) => ({ ...current, [field]: maskedValue }));
	}

	function updateClinic(field, value) {
		const maskedValue = ['phone', 'whatsapp'].includes(field) ? phoneMask(value) : value;

		setClinicForm((current) => ({ ...current, [field]: maskedValue }));
	}

	function updateUserForm(field, value) {
		setUserForm((current) => ({ ...current, [field]: value }));
	}

	async function submitClient(event) {
		event.preventDefault();
		if (!canWrite) return;
		setSavingClient(true);
		setErrors({});

		const payload = {
			...clientForm,
			height_cm: normalizeHeightToCentimeters(clientForm.height_cm),
		};

		try {
			const { data } = clientMode === 'edit' && editingClientId
				? await window.axios.put(`/bioimpedance/clients/${editingClientId}`, payload)
				: await window.axios.post('/bioimpedance/clients', payload);
			setClients((current) => {
				const others = current.filter((client) => client.id !== data.client.id);
				return [...others, data.client].sort((a, b) => a.full_name.localeCompare(b.full_name));
			});
			setSelectedClientId(data.client.id);
			setClientForm(clientMode === 'edit' ? clientFormFromClient(data.client) : emptyClient);
			setClientMode(clientMode === 'edit' ? 'edit' : 'create');
		} catch (error) {
			setErrors(error.response?.data?.errors ?? {});
		} finally {
			setSavingClient(false);
		}
	}

	async function inactivateClient(client) {
		if (!canWrite) return;
		if (!client || !window.confirm(`Inativar ${client.full_name}? O histórico será preservado.`)) return;

		const { data } = await window.axios.patch(`/bioimpedance/clients/${client.id}/inactivate`);
		setClients((current) => current.map((item) => (item.id === data.client.id ? data.client : item)));
		setSelectedClientId(data.client.id);
	}

	function exportClientPrivacyData(client) {
		if (!canAdmin || !client) return;
		window.open(`/bioimpedance/clients/${client.id}/privacy-export`, '_blank', 'noopener,noreferrer');
	}

	async function anonymizeClient(client) {
		if (!canAdmin || !client) return;
		const reason = window.prompt(`Informe o motivo da anonimização de ${client.full_name}:`);
		if (!reason) return;

		const { data } = await window.axios.patch(`/bioimpedance/clients/${client.id}/anonymize`, {
			anonymization_reason: reason,
		});
		setClients((current) => current.map((item) => (item.id === data.client.id ? data.client : item)));
		setAuditEvents(data.audit_events ?? auditEvents);
		setSelectedClientId(data.client.id);
	}

	async function submitClinic(event) {
		event.preventDefault();
		setSavingClinic(true);
		setClinicErrors({});

		try {
			const { data } = await window.axios.patch('/bioimpedance/clinic', clinicForm);
			setClinic(data.clinic);
			setClinicForm(clinicFormFromClinic(data.clinic));
			setAuditEvents(data.audit_events ?? auditEvents);
		} catch (error) {
			setClinicErrors(error.response?.data?.errors ?? {});
		} finally {
			setSavingClinic(false);
		}
	}

	async function submitUser(event) {
		event.preventDefault();
		setSavingUser(true);
		setUserErrors({});

		try {
			const { data } = await window.axios.post('/bioimpedance/users', userForm);
			setUsers(data.users ?? []);
			setAuditEvents(data.audit_events ?? []);
			setUserForm(emptyUserForm);
		} catch (error) {
			setUserErrors(error.response?.data?.errors ?? {});
		} finally {
			setSavingUser(false);
		}
	}

	async function updateUserRole(user, role) {
		setUserErrors({});

		try {
			const { data } = await window.axios.put(`/bioimpedance/users/${user.id}`, {
				name: user.name,
				email: user.email,
				role,
				password: '',
			});
			setUsers(data.users ?? []);
			setAuditEvents(data.audit_events ?? []);
		} catch (error) {
			setUserErrors(error.response?.data?.errors ?? {});
		}
	}

	async function inactivateUser(user) {
		if (!user || !window.confirm(`Inativar o usuário ${user.name}?`)) return;
		setUserErrors({});

		try {
			const { data } = await window.axios.patch(`/bioimpedance/users/${user.id}/inactivate`);
			setUsers(data.users ?? []);
			setAuditEvents(data.audit_events ?? []);
		} catch (error) {
			setUserErrors(error.response?.data?.errors ?? {});
		}
	}

	async function submitAssessment(event) {
		event.preventDefault();
		if (!selectedClient || !canWrite) return;

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

		if (assessmentMode === 'edit') {
			payload.change_reason = assessmentChangeReason;
		}

		try {
			const { data } = assessmentMode === 'edit' && editingAssessmentId
				? await window.axios.put(`/bioimpedance/assessments/${editingAssessmentId}`, payload)
				: await window.axios.post('/bioimpedance/assessments', payload);
			setClients((current) => current.map((client) => (client.id === data.client.id ? data.client : client)));
			setSelectedAssessmentId(data.assessment.id);
			const key = assessmentDraftKey(selectedClient.id);
			if (key) {
				window.localStorage.removeItem(key);
			}
			setAssessmentDraftSavedAt(null);
			startNewAssessment();
			window.setTimeout(() => document.getElementById('relatorio')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 120);
		} catch (error) {
			setErrors(error.response?.data?.errors ?? {});
		} finally {
			setSavingAssessment(false);
		}
	}

	async function cancelAssessment(assessment) {
		if (!canWrite) return;
		if (!assessment || assessment.is_canceled) return;

		const reason = window.prompt('Informe o motivo do cancelamento da avaliação:');
		if (!reason) return;

		const { data } = await window.axios.patch(`/bioimpedance/assessments/${assessment.id}/cancel`, {
			cancellation_reason: reason,
		});
		setClients((current) => current.map((client) => (client.id === data.client.id ? data.client : client)));
		setSelectedAssessmentId(data.assessment.id);
	}

	function updateAssessmentInState(assessment) {
		setClients((current) => current.map((client) => {
			if (client.id !== assessment.bioimpedance_client_id) return client;

			return {
				...client,
				assessments: client.assessments.map((item) => (item.id === assessment.id ? assessment : item)),
			};
		}));
	}

	async function createShareLink(channel = 'whatsapp') {
		if (!selectedAssessment || sharingAssessment) return;

		setSharingAssessment(true);
		try {
			const { data } = await window.axios.post(`/bioimpedance/assessments/${selectedAssessment.id}/shares`, {
				channel,
				recipient: selectedClient?.phone || selectedClient?.email || '',
				expires_in_days: 7,
			});
			setShareResult(data.share);
			updateAssessmentInState(data.assessment);
			setAuditEvents(data.audit_events ?? auditEvents);
		} finally {
			setSharingAssessment(false);
		}
	}

	async function copyShareMessage() {
		if (!shareResult?.message) return;
		await navigator.clipboard?.writeText(shareResult.message);
	}

	async function revokeShare(share) {
		if (!share || !window.confirm('Revogar este link temporário?')) return;

		const { data } = await window.axios.patch(`/bioimpedance/report-shares/${share.id}/revoke`);
		updateAssessmentInState(data.assessment);
		setAuditEvents(data.audit_events ?? auditEvents);
		if (shareResult?.id === share.id) {
			setShareResult(null);
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
						<span className={`rounded-full px-3 py-1 text-xs font-bold ${isOnline ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
							{isOnline ? 'Online' : 'Offline'}
						</span>
						<span className="text-sm text-slate-500">Profissional: <strong className="text-slate-800">{userName}</strong></span>
						<button type="button" onClick={handleLogout} className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
							Sair
						</button>
					</div>
				</div>
			</header>

			<nav className="no-print sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-4 py-2 backdrop-blur lg:hidden">
				<div className="grid grid-cols-3 gap-2">
					<a href="#clientes" className="rounded-xl border border-slate-200 px-3 py-2 text-center text-xs font-bold text-slate-700">Clientes</a>
					<a href="#avaliacao" className="rounded-xl border border-emerald-200 px-3 py-2 text-center text-xs font-bold text-emerald-700">Avaliação</a>
					<a href="#relatorio" className="rounded-xl border border-slate-200 px-3 py-2 text-center text-xs font-bold text-slate-700">Relatório</a>
				</div>
			</nav>

			<div className="mx-auto grid max-w-7xl gap-5 px-4 py-5 sm:px-6 lg:grid-cols-[360px_1fr] lg:px-8">
				<aside className="no-print space-y-5">
					<section id="clientes" className="scroll-mt-20 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
						<div className="flex items-center justify-between">
							<h2 className="text-base font-semibold text-slate-950">Clientes</h2>
							<span className="text-xs text-slate-500">{filteredClients.length} cadastro(s)</span>
						</div>
						<input
							value={query}
							onChange={(event) => setQuery(event.target.value)}
							placeholder="Pesquisar nome, telefone, e-mail ou CPF"
							className={inputClass()}
						/>
						<label className="mt-3 flex items-center gap-2 text-xs font-semibold text-slate-500">
							<input type="checkbox" checked={showInactive} onChange={(event) => setShowInactive(event.target.checked)} className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
							Mostrar clientes inativos
						</label>
						<div className="mt-3 max-h-64 space-y-2 overflow-auto pr-1">
							{loading ? <p className="text-sm text-slate-500">Carregando...</p> : null}
							{filteredClients.map((client) => (
								<button
									type="button"
									key={client.id}
									onClick={() => selectClient(client)}
									className={`w-full rounded-xl border px-3 py-3 text-left transition ${selectedClientId === client.id ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-white hover:bg-slate-50'}`}
								>
									<span className="flex items-center justify-between gap-2 text-sm font-semibold text-slate-900">
										{client.full_name}
										{client.is_active ? null : <span className="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] uppercase text-slate-600">Inativo</span>}
									</span>
									<span className="mt-1 block text-xs text-slate-500">{client.age} anos - {sexLabels[client.biological_sex]} - {numberBr(client.height_cm, 0)} cm</span>
									<span className="mt-1 block text-xs text-slate-400">{client.phone || client.email || 'Sem contato cadastrado'}</span>
								</button>
							))}
							{!loading && !filteredClients.length ? (
								<div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-3">
									<p className="text-sm text-slate-600">Cliente não encontrado.</p>
									<button type="button" onClick={() => startNewClient(query)} className="mt-2 text-sm font-bold text-emerald-700">
										Fazer cadastro rápido
									</button>
								</div>
							) : null}
						</div>
					</section>

					<section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
						<div className="flex items-center justify-between gap-2">
							<h2 className="text-base font-semibold text-slate-950">{clientMode === 'edit' ? 'Editar cliente' : 'Cadastro rápido'}</h2>
							<div className="flex gap-2">
								<button type="button" onClick={() => startNewClient()} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600">Novo</button>
								{selectedClient ? <button type="button" disabled={!canWrite || selectedClient.is_anonymized} onClick={() => startEditClient(selectedClient)} className="rounded-lg border border-emerald-200 px-3 py-1.5 text-xs font-bold text-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Editar</button> : null}
							</div>
						</div>
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
							<Field label="CPF opcional">
								<input inputMode="numeric" placeholder="000.000.000-00" value={clientForm.cpf} onChange={(event) => updateClient('cpf', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Endereço opcional">
								<input value={clientForm.address} onChange={(event) => updateClient('address', event.target.value)} className={inputClass()} />
							</Field>
							<div className="grid grid-cols-2 gap-3">
								<Field label="Contato emergência">
									<input value={clientForm.emergency_contact_name} onChange={(event) => updateClient('emergency_contact_name', event.target.value)} className={inputClass()} />
								</Field>
								<Field label="Telefone emergência">
									<input inputMode="tel" value={clientForm.emergency_contact_phone} onChange={(event) => updateClient('emergency_contact_phone', event.target.value)} className={inputClass()} />
								</Field>
							</div>
							<Field label="Próxima avaliação">
								<input type="date" value={clientForm.next_assessment_at} onChange={(event) => updateClient('next_assessment_at', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Observações">
								<textarea value={clientForm.notes} onChange={(event) => updateClient('notes', event.target.value)} rows="3" className={inputClass()} />
							</Field>
							<label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
								<input type="checkbox" checked={clientForm.consent_accepted} onChange={(event) => updateClient('consent_accepted', event.target.checked)} className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600" />
								<span>Cliente autorizou o registro dos dados para acompanhamento corporal.</span>
							</label>
							<button type="submit" disabled={!canWrite || savingClient} className="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
								{savingClient ? 'Salvando...' : clientMode === 'edit' ? 'Salvar alterações' : 'Salvar cliente'}
							</button>
							{clientMode === 'edit' && selectedClient?.is_active && !selectedClient?.is_anonymized ? (
								<button type="button" disabled={!canWrite} onClick={() => inactivateClient(selectedClient)} className="w-full rounded-xl border border-rose-200 px-4 py-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50">
									Inativar cliente
								</button>
							) : null}
						</form>
					</section>

					{currentUser?.is_admin ? (
						<section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
						<div className="flex items-center justify-between gap-3">
							<div>
								<h2 className="text-base font-semibold text-slate-950">Configurações da clínica</h2>
								<p className="mt-1 text-xs text-slate-500">Identidade usada no dashboard e no PDF.</p>
							</div>
							<div className="flex gap-1">
								<span className="h-5 w-5 rounded-full border border-slate-200" style={{ backgroundColor: clinicForm.primary_color }}></span>
								<span className="h-5 w-5 rounded-full border border-slate-200" style={{ backgroundColor: clinicForm.secondary_color }}></span>
							</div>
						</div>
						<form onSubmit={submitClinic} className="mt-4 space-y-3">
							<Field label="Nome comercial">
								<input value={clinicForm.display_name} onChange={(event) => updateClinic('display_name', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Nome jurídico">
								<input value={clinicForm.legal_name} onChange={(event) => updateClinic('legal_name', event.target.value)} className={inputClass()} />
							</Field>
							<div className="grid grid-cols-2 gap-3">
								<Field label="Documento">
									<input value={clinicForm.document} onChange={(event) => updateClinic('document', event.target.value)} className={inputClass()} />
								</Field>
								<Field label="Instagram">
									<input value={clinicForm.instagram} onChange={(event) => updateClinic('instagram', event.target.value)} className={inputClass()} />
								</Field>
							</div>
							<div className="grid grid-cols-2 gap-3">
								<Field label="Telefone">
									<input inputMode="tel" value={clinicForm.phone} onChange={(event) => updateClinic('phone', event.target.value)} className={inputClass()} />
								</Field>
								<Field label="WhatsApp">
									<input inputMode="tel" value={clinicForm.whatsapp} onChange={(event) => updateClinic('whatsapp', event.target.value)} className={inputClass()} />
								</Field>
							</div>
							<Field label="E-mail">
								<input type="email" value={clinicForm.email} onChange={(event) => updateClinic('email', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Endereço">
								<input value={clinicForm.address} onChange={(event) => updateClinic('address', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Site">
								<input value={clinicForm.website} onChange={(event) => updateClinic('website', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Logo">
								<input value={clinicForm.logo_url} onChange={(event) => updateClinic('logo_url', event.target.value)} className={inputClass()} />
							</Field>
							<div className="grid grid-cols-2 gap-3">
								<Field label="Cor principal">
									<input type="color" value={clinicForm.primary_color} onChange={(event) => updateClinic('primary_color', event.target.value)} className="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white p-1" />
								</Field>
								<Field label="Cor secundária">
									<input type="color" value={clinicForm.secondary_color} onChange={(event) => updateClinic('secondary_color', event.target.value)} className="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white p-1" />
								</Field>
							</div>
							<Field label="Descrição curta">
								<input value={clinicForm.contact} onChange={(event) => updateClinic('contact', event.target.value)} className={inputClass()} />
							</Field>
							<Field label="Rodapé do relatório">
								<textarea value={clinicForm.footer_text} onChange={(event) => updateClinic('footer_text', event.target.value)} rows="2" className={inputClass()} />
							</Field>
							<Field label="Aviso técnico">
								<textarea value={clinicForm.technical_notice} onChange={(event) => updateClinic('technical_notice', event.target.value)} rows="3" className={inputClass()} />
							</Field>
							{Object.keys(clinicErrors).length ? (
								<div className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
									Confira os dados da clínica antes de salvar.
								</div>
							) : null}
							<button type="submit" disabled={savingClinic} className="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
								{savingClinic ? 'Salvando...' : 'Salvar configurações'}
							</button>
						</form>
						</section>
					) : null}

					{currentUser?.is_admin ? (
						<section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
							<div>
								<h2 className="text-base font-semibold text-slate-950">Usuários e auditoria</h2>
								<p className="mt-1 text-xs text-slate-500">Controle de acesso e eventos recentes.</p>
							</div>
							<form onSubmit={submitUser} className="mt-4 space-y-3">
								<Field label="Nome">
									<input value={userForm.name} onChange={(event) => updateUserForm('name', event.target.value)} className={inputClass()} />
								</Field>
								<Field label="E-mail">
									<input type="email" value={userForm.email} onChange={(event) => updateUserForm('email', event.target.value)} className={inputClass()} />
								</Field>
								<div className="grid grid-cols-2 gap-3">
									<Field label="Senha inicial">
										<input type="password" value={userForm.password} onChange={(event) => updateUserForm('password', event.target.value)} className={inputClass()} />
									</Field>
									<Field label="Perfil">
										<select value={userForm.role} onChange={(event) => updateUserForm('role', event.target.value)} className={inputClass()}>
											{Object.entries(roleLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
										</select>
									</Field>
								</div>
								{Object.keys(userErrors).length ? (
									<div className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
										Confira os dados do usuário antes de salvar.
									</div>
								) : null}
								<button type="submit" disabled={savingUser} className="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
									{savingUser ? 'Salvando...' : 'Cadastrar usuário'}
								</button>
							</form>

							<div className="mt-5 space-y-2">
								{users.map((user) => (
									<div key={user.id} className={`rounded-xl border p-3 ${user.is_active ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50 opacity-70'}`}>
										<div className="flex items-start justify-between gap-3">
											<div>
												<p className="text-sm font-bold text-slate-900">{user.name}</p>
												<p className="mt-1 text-xs text-slate-500">{user.email}</p>
												<p className="mt-1 text-xs text-slate-400">Último acesso: {formatDate(user.last_login_at)}</p>
											</div>
											<span className={`rounded-full px-2 py-1 text-[10px] font-bold uppercase ${user.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-600'}`}>
												{user.is_active ? 'Ativo' : 'Inativo'}
											</span>
										</div>
										<div className="mt-3 grid grid-cols-[1fr_auto] gap-2">
											<select value={user.role} disabled={!user.is_active} onChange={(event) => updateUserRole(user, event.target.value)} className={inputClass()}>
												{Object.entries(roleLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
											</select>
											<button type="button" disabled={!user.is_active || user.id === currentUser.id} onClick={() => inactivateUser(user)} className="mt-2 rounded-xl border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50">
												Inativar
											</button>
										</div>
									</div>
								))}
							</div>

							<div className="mt-5 border-t border-slate-200 pt-4">
								<h3 className="text-xs font-bold uppercase tracking-wide text-slate-500">Auditoria recente</h3>
								<div className="mt-3 max-h-72 space-y-2 overflow-auto pr-1">
									{auditEvents.map((event) => (
										<div key={event.id} className="rounded-xl bg-slate-50 p-3">
											<p className="text-xs font-bold text-slate-800">{event.description ?? event.action}</p>
											<p className="mt-1 text-[11px] text-slate-500">{event.user_name} • {formatDate(event.created_at)} • {event.ip_address ?? '-'}</p>
										</div>
									))}
									{!auditEvents.length ? <p className="text-sm text-slate-500">Nenhum evento registrado.</p> : null}
								</div>
							</div>
						</section>
					) : null}
				</aside>

				<section className="space-y-5">
					{canAdmin ? (
						<AdminDashboard
							dashboard={adminDashboard}
							onRefresh={refreshAdminDashboard}
							refreshing={refreshingDashboard}
							onSelectClient={selectClientById}
						/>
					) : null}

					{selectedClient ? (
						<div className="no-print rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
							<div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
								<div>
									<p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Histórico de avaliações</p>
									<h2 className="mt-1 text-lg font-semibold text-slate-950">{selectedClient.assessments?.length ?? 0} registro(s)</h2>
								</div>
								<button type="button" disabled={!canWrite} onClick={startNewAssessment} className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
									Nova avaliação
								</button>
							</div>
							<div className="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
								{selectedClient.assessments?.map((assessment) => (
									<button
										type="button"
										key={assessment.id}
										onClick={() => setSelectedAssessmentId(assessment.id)}
										className={`rounded-xl border p-3 text-left transition ${selectedAssessment?.id === assessment.id ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 hover:bg-slate-50'} ${assessment.is_canceled ? 'opacity-70' : ''}`}
									>
										<span className="flex items-center justify-between gap-2 text-sm font-bold text-slate-900">
											{formatDate(assessment.evaluated_at)}
											{assessment.is_canceled ? <span className="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] uppercase text-rose-700">Cancelada</span> : null}
										</span>
										<span className="mt-2 block text-xs text-slate-500">Peso {numberBr(assessment.weight_kg, 1)} kg • IMC {numberBr(assessment.calculated_bmi, 1)}</span>
										<span className="mt-1 block text-xs text-slate-400">{assessment.correction_count ? `${assessment.correction_count} correção(ões)` : 'Sem correções'}</span>
									</button>
								))}
							</div>
							{selectedAssessment ? (
								<div className="mt-4 flex flex-wrap gap-2">
									<button type="button" disabled={!canWrite} onClick={() => duplicateAssessment(selectedAssessment)} className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50">
										Duplicar como base
									</button>
									<button type="button" disabled={!canWrite || selectedAssessment.is_canceled} onClick={() => startEditAssessment(selectedAssessment)} className="rounded-xl border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50">
										Corrigir avaliação
									</button>
									<button type="button" disabled={!canWrite || selectedAssessment.is_canceled} onClick={() => cancelAssessment(selectedAssessment)} className="rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50">
										Cancelar avaliação
									</button>
									{canAdmin ? (
										<>
											<button type="button" onClick={() => exportClientPrivacyData(selectedClient)} className="rounded-xl border border-sky-200 px-4 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-50">
												Exportar dados LGPD
											</button>
											<button type="button" disabled={selectedClient.is_anonymized} onClick={() => anonymizeClient(selectedClient)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50">
												Anonimizar cliente
											</button>
										</>
									) : null}
								</div>
							) : null}
							{selectedAssessment && !selectedAssessment.is_canceled ? (
								<div className="mt-4 rounded-2xl border border-sky-100 bg-sky-50 p-4">
									<div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
										<div>
											<p className="text-xs font-bold uppercase tracking-wide text-sky-700">Comunicação com o cliente</p>
											<p className="mt-1 text-sm text-slate-600">Link temporário sem resultados clínicos na mensagem.</p>
										</div>
										<button type="button" disabled={!canWrite || sharingAssessment} onClick={() => createShareLink('whatsapp')} className="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50">
											{sharingAssessment ? 'Gerando...' : 'Gerar link WhatsApp'}
										</button>
									</div>
									{shareResult ? (
										<div className="mt-3 rounded-xl bg-white p-3">
											<p className="text-sm leading-6 text-slate-700">{shareResult.message}</p>
											<div className="mt-3 flex flex-wrap gap-2">
												<button type="button" onClick={copyShareMessage} className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700">Copiar mensagem</button>
												<a href={shareResult.whatsapp_url} target="_blank" rel="noreferrer" className="rounded-xl border border-emerald-200 px-3 py-2 text-xs font-bold text-emerald-700">Abrir WhatsApp</a>
												<a href={shareResult.url} target="_blank" rel="noreferrer" className="rounded-xl border border-sky-200 px-3 py-2 text-xs font-bold text-sky-700">Ver link</a>
											</div>
										</div>
									) : null}
									{selectedAssessment.shares?.length ? (
										<div className="mt-3 space-y-2">
											{selectedAssessment.shares.map((share) => (
												<div key={share.id} className="flex flex-col gap-2 rounded-xl bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
													<div>
														<p className="text-xs font-bold uppercase text-slate-500">{share.channel} • expira {formatDate(share.expires_at)}</p>
														<p className="mt-1 text-xs text-slate-500">Visualizações: {share.view_count} • {share.is_active ? 'Ativo' : 'Revogado/expirado'}</p>
													</div>
													<button type="button" disabled={!share.is_active} onClick={() => revokeShare(share)} className="rounded-xl border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 disabled:cursor-not-allowed disabled:opacity-50">
														Revogar
													</button>
												</div>
											))}
										</div>
									) : null}
								</div>
							) : null}
						</div>
					) : null}

					<EvolutionPanel client={selectedClient} selectedAssessment={selectedAssessment} />

						<div id="avaliacao" ref={assessmentSectionRef} className="no-print scroll-mt-20 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
							<div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
								<div>
									<p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">{assessmentMode === 'edit' ? 'Corrigir avaliação' : 'Registrar nova avaliação'}</p>
									<h1 className="mt-1 text-2xl font-semibold text-slate-950">{selectedClient?.full_name ?? 'Selecione um cliente'}</h1>
									<p className="mt-1 text-sm text-slate-500">{assessmentMode === 'edit' ? 'A correção exige justificativa e gera auditoria.' : 'Digite os valores exibidos na balança Omron antiga após a pesagem.'}</p>
								</div>
								<div className="flex flex-col gap-2 sm:items-end">
									<div className="rounded-xl bg-slate-100 px-3 py-2 text-sm text-slate-600">
										IMC calculado: <strong className="text-slate-950">{calculatedBmi ?? '-'}</strong>
									</div>
									{assessmentDraftSavedAt ? (
										<div className="flex items-center gap-2 text-xs text-slate-500">
											<span>Rascunho salvo {formatDate(assessmentDraftSavedAt)}</span>
											<button type="button" onClick={clearAssessmentDraft} className="font-bold text-rose-600">Descartar</button>
										</div>
									) : null}
								</div>
							</div>

						<form onSubmit={submitAssessment} onKeyDownCapture={focusNextFormField} className="mt-5 grid gap-3 md:grid-cols-4">
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
							{assessmentMode === 'edit' ? (
								<div className="md:col-span-4">
									<Field label="Justificativa da correção">
										<textarea value={assessmentChangeReason} onChange={(event) => setAssessmentChangeReason(event.target.value)} rows="2" className={inputClass()} />
									</Field>
								</div>
							) : null}
							{Object.keys(errors).length ? (
								<div className="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 md:col-span-4">
									Confira os campos obrigatórios e valores digitados.
								</div>
							) : null}
						<button type="submit" disabled={!canWrite || !selectedClient || !selectedClient.is_active || savingAssessment} className="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 md:col-span-4">
							{savingAssessment ? 'Salvando...' : assessmentMode === 'edit' ? 'Salvar correção e gerar relatório' : 'Salvar avaliação e gerar relatório'}
						</button>
						</form>
					</div>

					<div id="relatorio" className="scroll-mt-20">
						<Report clinic={clinic} client={selectedClient} assessment={selectedAssessment} professional={userName} />
					</div>
				</section>
			</div>
		</main>
	);
}

function AdminDashboard({ dashboard, onRefresh, refreshing, onSelectClient }) {
	if (!dashboard) {
		return (
			<section className="no-print rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-500">
				Indicadores administrativos indisponíveis.
			</section>
		);
	}

	const cards = [
		{ key: 'assessments_this_month', label: 'Avaliações no mês', tone: 'emerald' },
		{ key: 'clients_new_this_month', label: 'Clientes novos', tone: 'sky' },
		{ key: 'returning_clients_this_month', label: 'Clientes recorrentes', tone: 'violet' },
		{ key: 'clients_without_return', label: 'Sem retorno', tone: 'rose' },
		{ key: 'upcoming_reassessments', label: 'Próximas reavaliações', tone: 'amber' },
		{ key: 'evolutions_registered', label: 'Com evolução', tone: 'emerald' },
		{ key: 'reports_issued_this_month', label: 'Relatórios emitidos', tone: 'slate' },
		{ key: 'shares_sent_this_month', label: 'Envios realizados', tone: 'sky' },
	];

	return (
		<section className="no-print rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
			<div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
				<div>
					<p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Administração</p>
					<h2 className="mt-1 text-xl font-semibold text-slate-950">Indicadores de {dashboard.period_label}</h2>
					<p className="mt-1 text-xs text-slate-500">Atualizado em {formatDate(dashboard.generated_at)}</p>
				</div>
				<button type="button" onClick={onRefresh} disabled={refreshing} className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50">
					{refreshing ? 'Atualizando...' : 'Atualizar indicadores'}
				</button>
			</div>

			<div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
				{cards.map((card) => (
					<AdminMetric key={card.key} label={card.label} value={dashboard.cards?.[card.key] ?? 0} tone={card.tone} />
				))}
			</div>

			<div className="mt-5 grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
				<div className="rounded-2xl border border-slate-200">
					<div className="border-b border-slate-100 px-4 py-3">
						<h3 className="text-sm font-bold uppercase tracking-wide text-slate-600">Produção por profissional</h3>
					</div>
					<div className="divide-y divide-slate-100">
						{dashboard.professionals?.length ? dashboard.professionals.map((professional) => (
							<div key={professional.name} className="grid grid-cols-[1fr_auto] items-center gap-3 px-4 py-3">
								<div>
									<p className="text-sm font-bold text-slate-900">{professionalName(professional.name)}</p>
									<p className="mt-1 text-xs text-slate-500">Média geral: {numberBr(dashboard.average_assessments_per_professional, 1)} por profissional</p>
								</div>
								<span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{professional.assessments_count}</span>
							</div>
						)) : (
							<p className="px-4 py-4 text-sm text-slate-500">Nenhuma avaliação no mês.</p>
						)}
					</div>
				</div>

				<div className="grid gap-4 md:grid-cols-2">
					<ReminderList title="Clientes sem retorno" clients={dashboard.clients_without_return ?? []} emptyText="Nenhum retorno vencido." onSelectClient={onSelectClient} />
					<ReminderList title="Próximas reavaliações" clients={dashboard.upcoming_reassessments ?? []} emptyText="Nenhuma reavaliação nos próximos 14 dias." onSelectClient={onSelectClient} />
				</div>
			</div>

			<div className="mt-5 overflow-hidden rounded-2xl border border-slate-200">
				<table className="w-full text-left text-sm">
					<thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
						<tr>
							<th className="px-4 py-3 font-bold">Avaliação recente</th>
							<th className="px-4 py-3 font-bold">Profissional</th>
							<th className="px-4 py-3 font-bold">Peso</th>
							<th className="px-4 py-3 font-bold">IMC</th>
						</tr>
					</thead>
					<tbody className="divide-y divide-slate-100">
						{dashboard.recent_assessments?.length ? dashboard.recent_assessments.map((assessment) => (
							<tr key={assessment.id}>
								<td className="px-4 py-3">
									<p className="font-semibold text-slate-900">{assessment.client_name}</p>
									<p className="mt-1 text-xs text-slate-500">{formatDate(assessment.evaluated_at)}</p>
								</td>
								<td className="px-4 py-3 text-slate-600">{professionalName(assessment.professional_name)}</td>
								<td className="px-4 py-3 text-slate-900">{numberBr(assessment.weight_kg, 1)} kg</td>
								<td className="px-4 py-3 text-slate-900">{numberBr(assessment.calculated_bmi, 1)} kg/m²</td>
							</tr>
						)) : (
							<tr>
								<td colSpan="4" className="px-4 py-5 text-center text-sm text-slate-500">Nenhuma avaliação registrada.</td>
							</tr>
						)}
					</tbody>
				</table>
			</div>
		</section>
	);
}

function AdminMetric({ label, value, tone }) {
	const classes = {
		emerald: 'bg-emerald-50 text-emerald-700',
		sky: 'bg-sky-50 text-sky-700',
		violet: 'bg-violet-50 text-violet-700',
		rose: 'bg-rose-50 text-rose-700',
		amber: 'bg-amber-50 text-amber-700',
		slate: 'bg-slate-100 text-slate-700',
	}[tone] ?? 'bg-slate-100 text-slate-700';

	return (
		<div className={`rounded-2xl p-4 ${classes}`}>
			<p className="text-xs font-bold uppercase tracking-wide opacity-80">{label}</p>
			<p className="mt-3 text-3xl font-bold leading-none">{value}</p>
		</div>
	);
}

function ReminderList({ title, clients, emptyText, onSelectClient }) {
	return (
		<div className="rounded-2xl border border-slate-200">
			<div className="border-b border-slate-100 px-4 py-3">
				<h3 className="text-sm font-bold uppercase tracking-wide text-slate-600">{title}</h3>
			</div>
			<div className="divide-y divide-slate-100">
				{clients.length ? clients.map((client) => (
					<button type="button" key={client.id} onClick={() => onSelectClient(client.id)} className="block w-full px-4 py-3 text-left transition hover:bg-slate-50">
						<span className="block text-sm font-bold text-slate-900">{client.full_name}</span>
						<span className="mt-1 block text-xs text-slate-500">Prevista: {formatIssueDate(client.next_assessment_at)} • {client.phone || client.email || 'Sem contato'}</span>
					</button>
				)) : (
					<p className="px-4 py-4 text-sm text-slate-500">{emptyText}</p>
				)}
			</div>
		</div>
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
	const assessmentAge = assessment?.age_at_assessment ?? client.age;
	const assessmentHeight = assessment?.height_cm_at_assessment ?? client.height_cm;
	const assessmentSex = assessment?.biological_sex_at_assessment ?? client.biological_sex;
	const bodyAgeDelta = assessment?.body_age != null && assessmentAge != null ? assessment.body_age - assessmentAge : null;
	const validationTitle = warnings.length ? 'Dados com alertas' : 'Dados validados';
	const validationText = warnings.length ? warnings[0] : 'Nenhum alerta automático identificado.';
	const formattedProfessional = professionalName(professional);

	return (
		<article className="report overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm print:border-0 print:shadow-none">
			<div className="no-print mb-4 flex flex-wrap gap-2">
				{assessment ? (
					<a href={`/bioimpedance/assessments/${assessment.id}/pdf`} target="_blank" rel="noreferrer" className="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">
						Baixar PDF oficial
					</a>
				) : null}
				<button type="button" onClick={() => window.print()} className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
					Imprimir / baixar PDF
				</button>
				<button type="button" onClick={() => navigator.share?.({ title: 'Relatório de bioimpedância', text: client.full_name })} className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
					Compartilhar
				</button>
			</div>

			<header className="report-header relative flex flex-col gap-5 overflow-hidden border-b border-rose-200 bg-gradient-to-br from-rose-50 via-white to-stone-100 px-8 py-8 text-slate-900 sm:flex-row sm:items-center sm:justify-between">
				<div className="pointer-events-none absolute inset-x-0 top-0 h-2" style={{ background: `linear-gradient(90deg, ${clinic.primary_color}, #f2c7cf, ${clinic.secondary_color})` }}></div>
				<div className="flex items-center gap-5">
					<img src={clinic.logo_url} alt={clinic.display_name} className="report-logo h-36 w-96 object-contain" />
				</div>
				<div className="text-left sm:text-right">
					<p className="text-xl font-bold uppercase tracking-wide" style={{ color: clinic.secondary_color }}>Relatório de bioimpedância</p>
					<p className="mt-5 text-sm text-slate-600">Avaliação Nº {assessmentNumber(assessment?.id)} | {assessment ? formatDate(assessment.evaluated_at) : 'Aguardando avaliação'}</p>
					<p className="mt-4 text-sm text-slate-600">Responsável: {formattedProfessional}</p>
				</div>
			</header>

			<div className="report-body bg-[#fbf8f8] px-8 py-7">
				{assessment?.is_canceled ? (
					<div className="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
						<strong>Avaliação cancelada.</strong> {assessment.cancellation_reason ?? 'Registro preservado apenas para histórico.'}
					</div>
				) : null}
				<section className="report-client-grid grid gap-4 rounded-2xl border border-rose-100 bg-white p-5 shadow-sm sm:grid-cols-[2fr_1fr_1fr_1fr]">
					<div>
						<p className="text-xs font-bold uppercase tracking-wide text-[#b96f7d]">Cliente</p>
						<h2 className="mt-2 text-2xl font-bold text-slate-950">{client.full_name}</h2>
					</div>
					<MiniMetric label="Idade" value={`${assessmentAge ?? '-'} anos`} />
					<MiniMetric label="Sexo" value={sexLabels[assessmentSex] ?? '-'} />
					<MiniMetric label="Altura" value={`${numberBr(assessmentHeight, 0)} cm`} />
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
					<p className="mt-3">{clinic.technical_notice}</p>
					<div className="mt-7 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
						<div>
						<p className="font-bold text-slate-900">{clinic.display_name}</p>
						<p className="mt-1">{clinic.contact}</p>
					</div>
					<p>{clinic.footer_text} • Relatório nº {assessmentNumber(assessment?.id)} • Emitido em {assessment ? formatIssueDate(assessment.evaluated_at) : '-'}</p>
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

function EvolutionPanel({ client, selectedAssessment }) {
	const [period, setPeriod] = useState('all');

	if (!client) return null;

	const timeline = sortAssessmentsAscending(client.assessments);
	const current = selectedAssessment && !selectedAssessment.is_canceled
		? selectedAssessment
		: timeline[timeline.length - 1] ?? null;
	const currentIndex = timeline.findIndex((assessment) => assessment.id === current?.id);
	const previous = currentIndex > 0 ? timeline[currentIndex - 1] : null;
	const selectedPeriod = evolutionPeriods.find((item) => item.key === period) ?? evolutionPeriods[evolutionPeriods.length - 1];
	const periodStart = current && selectedPeriod.days
		? new Date(new Date(current.evaluated_at).getTime() - selectedPeriod.days * 24 * 60 * 60 * 1000)
		: null;
	const visibleTimeline = periodStart
		? timeline.filter((assessment) => new Date(assessment.evaluated_at) >= periodStart && new Date(assessment.evaluated_at) <= new Date(current.evaluated_at))
		: timeline;
	const first = visibleTimeline[0] ?? null;

	return (
		<section className="no-print rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
			<div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
				<div>
					<p className="text-xs font-semibold uppercase tracking-wide text-emerald-700">Evolução corporal</p>
					<h2 className="mt-1 text-xl font-semibold text-slate-950">{timeline.length} avaliação(ões) válidas</h2>
				</div>
				<div className="flex flex-col gap-2 sm:items-end">
					<div className="rounded-xl bg-slate-100 px-3 py-2 text-sm text-slate-600">
						Selecionada: <strong className="text-slate-950">{current ? formatDate(current.evaluated_at) : '-'}</strong>
					</div>
					<div className="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1">
						{evolutionPeriods.map((item) => (
							<button
								type="button"
								key={item.key}
								onClick={() => setPeriod(item.key)}
								className={`rounded-lg px-3 py-1.5 text-xs font-bold transition ${period === item.key ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'}`}
							>
								{item.label}
							</button>
						))}
					</div>
				</div>
			</div>

			{timeline.length ? (
				<>
					<div className="mt-5 grid gap-3 md:grid-cols-3">
						{evolutionMetrics.slice(0, 3).map((metric) => (
							<TrendCard key={metric.key} metric={metric} assessments={visibleTimeline} current={current} first={first} />
						))}
					</div>
					<div className="mt-3 grid gap-3 md:grid-cols-3">
						{evolutionMetrics.slice(3).map((metric) => (
							<TrendCard key={metric.key} metric={metric} assessments={visibleTimeline} current={current} first={first} />
						))}
					</div>

					<div className="mt-5 grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
						<div className="overflow-hidden rounded-2xl border border-slate-200">
							<table className="w-full text-left text-sm">
								<thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
									<tr>
										<th className="px-4 py-3 font-bold">Indicador</th>
										<th className="px-4 py-3 font-bold">Anterior</th>
										<th className="px-4 py-3 font-bold">Atual</th>
										<th className="px-4 py-3 font-bold">Variação</th>
									</tr>
								</thead>
								<tbody className="divide-y divide-slate-100">
									{evolutionMetrics.map((metric) => {
										const previousValue = metricValue(previous, metric.key);
										const currentValue = metricValue(current, metric.key);
										const delta = previousValue !== null && currentValue !== null ? currentValue - previousValue : null;
										const tone = deltaTone(metric, delta);

										return (
											<tr key={metric.key}>
												<td className="px-4 py-3 font-semibold text-slate-800">{metric.label}</td>
												<td className="px-4 py-3 text-slate-600">{formatMetricCurrent(previous, metric)}</td>
												<td className="px-4 py-3 text-slate-900">{formatMetricCurrent(current, metric)}</td>
												<td className="px-4 py-3">
													<span className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ${deltaClass(tone)}`}>
														{formatMetricDelta(delta, metric)}
													</span>
												</td>
											</tr>
										);
									})}
								</tbody>
							</table>
						</div>

						<div className="rounded-2xl border border-emerald-100 bg-emerald-50 p-5">
							<h3 className="text-sm font-bold uppercase tracking-wide text-emerald-800">Síntese comparativa</h3>
							<p className="mt-4 text-sm leading-6 text-slate-700">{comparisonSummary(previous, current)}</p>
							<p className="mt-4 text-xs leading-5 text-slate-500">A leitura de evolução considera composição corporal, não apenas redução de peso.</p>
						</div>
					</div>
				</>
			) : (
				<div className="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
					Cadastre avaliações para visualizar histórico, comparações e tendência corporal.
				</div>
			)}
		</section>
	);
}

function TrendCard({ metric, assessments, current, first }) {
	const values = assessments.map((assessment) => metricValue(assessment, metric.key));
	const firstValue = metricValue(first, metric.key);
	const currentValue = metricValue(current, metric.key);
	const totalDelta = firstValue !== null && currentValue !== null ? currentValue - firstValue : null;
	const tone = deltaTone(metric, totalDelta);
	const points = sparklinePoints(values);

	return (
		<div className="rounded-2xl border border-slate-200 bg-white p-4">
			<div className="flex items-start justify-between gap-3">
				<div>
					<p className="text-xs font-bold uppercase tracking-wide text-slate-500">{metric.label}</p>
					<p className="mt-2 text-2xl font-bold text-slate-950">{formatMetricCurrent(current, metric)}</p>
				</div>
				<span className={`rounded-full px-3 py-1 text-xs font-bold ${deltaClass(tone)}`}>
					{formatMetricDelta(totalDelta, metric)}
				</span>
			</div>
			<svg className="mt-4 h-12 w-full" viewBox="0 0 150 42" preserveAspectRatio="none" aria-hidden="true">
				<polyline points={points} fill="none" stroke="#10b981" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" />
			</svg>
			<div className="mt-2 flex justify-between text-[11px] font-semibold uppercase text-slate-400">
				<span>{first ? formatIssueDate(first.evaluated_at) : '-'}</span>
				<span>{current ? formatIssueDate(current.evaluated_at) : '-'}</span>
			</div>
		</div>
	);
}
