import React, { useEffect, useState } from 'react';
import { formatDate, formatIssueDate, localDatetimeNow, numberBr } from '../../../shared/formatters';
import { cpfMask } from '../../../shared/inputMasks';
import { assessmentAccess, canUseClinicalFeatures } from './clientAccess';
import { apiErrorMessage, apiValidationErrors, updateBioimpedanceClientBirthDate } from '../../../services/bioimpedanceApi';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';
import ScreeningInvitationPanel from './ScreeningInvitationPanel';
import ClientModuleSummary from '../../../shared/ClientModuleSummary';
import ClientWorkspaceTabs from '../../../shared/ClientWorkspaceTabs';
import ClientAssessmentHeader from '../../../shared/ClientAssessmentHeader';

const sexLabels = { female: 'Feminino', male: 'Masculino' };

function initials(name) {
	return String(name ?? '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

export default function ClientDetails({ client, currentUser, scaleModel, enabledModules, onBack, onClientUpdated, onNewAssessment, onOpenAssistant, onOpenReport }) {
	const access = assessmentAccess(client, currentUser, scaleModel);
	const assessments = [...(client.assessments ?? [])].sort((left, right) => new Date(right.evaluated_at) - new Date(left.evaluated_at));
	const latestActiveAssessment = assessments.find((assessment) => !assessment.is_canceled) ?? null;
	const canAccessScreenings = ['admin', 'professional'].includes(currentUser?.role);
	const firstActiveTab = enabledModules.assessment ? 'assessment' : enabledModules.screening ? 'screenings' : 'assessment';
	const enabledModuleCount = [enabledModules.assessment, enabledModules.screening, enabledModules.reports].filter(Boolean).length;
	const needsModuleChoice = enabledModuleCount > 1;
	const opensSingleModuleDirectly = enabledModuleCount === 1 && (enabledModules.assessment || enabledModules.screening);
	const [activeTab, setActiveTab] = useState(firstActiveTab);
	const [showClientDetails, setShowClientDetails] = useState(false);
	const [editingBirthDate, setEditingBirthDate] = useState(false);
	const [birthDate, setBirthDate] = useState(client.birth_date ?? '');
	const [birthDateError, setBirthDateError] = useState('');
	const [savingBirthDate, setSavingBirthDate] = useState(false);
	const clientDetailsId = `client-profile-details-${client.id}`;
	const canEditBirthDate = canUseClinicalFeatures(currentUser) && !client.is_anonymized;

	useEffect(() => setActiveTab(firstActiveTab), [client.id, firstActiveTab]);
	useEffect(() => setShowClientDetails(false), [client.id]);
	useEffect(() => {
		setEditingBirthDate(false);
		setBirthDate(client.birth_date ?? '');
		setBirthDateError('');
	}, [client.id, client.birth_date]);

	async function saveBirthDate(event) {
		event.preventDefault();
		if (savingBirthDate || birthDate === client.birth_date) {
			setEditingBirthDate(false);
			return;
		}

		setSavingBirthDate(true);
		setBirthDateError('');
		try {
			const updatedClient = await updateBioimpedanceClientBirthDate(client.id, birthDate);
			onClientUpdated(updatedClient);
			setEditingBirthDate(false);
		} catch (error) {
			const validationErrors = apiValidationErrors(error);
			setBirthDateError(validationErrors.birth_date?.[0] ?? apiErrorMessage(error, 'Não foi possível atualizar a data de nascimento.'));
		} finally {
			setSavingBirthDate(false);
		}
	}

	function openScreenings() {
		setActiveTab('screenings');
		window.setTimeout(() => document.getElementById('client-panel-screenings')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
	}

	return (
		<section aria-labelledby="client-details-title">
			<PwaButton type="button" onClick={onBack} variant="ghost" icon="arrowLeft" className="-ml-3">Voltar para clientes</PwaButton>

			<PwaCard className="mt-3 p-5 sm:p-6">
				<div className="flex flex-wrap items-start justify-between gap-3">
					<div className="flex min-w-0 items-center gap-3">
						<span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-ricosty-100 to-ricosty-200 text-lg font-extrabold text-ricosty-800 ring-1 ring-ricosty-200">{initials(client.full_name)}</span>
						<div className="min-w-0"><p className="text-xs font-bold uppercase tracking-[0.14em] text-ricosty-600">Cliente</p>
						<h2 id="client-details-title" className="mt-1 break-words text-xl font-extrabold text-ricosty-ink">{client.full_name}</h2></div>
					</div>
					<div className="flex shrink-0 flex-col items-end gap-1">
						{client.is_anonymized ? (
							<span className="rounded-full bg-amber-100 px-2 py-1 text-[11px] font-bold uppercase text-amber-900">Anonimizado</span>
						) : !client.is_active ? (
							<span className="rounded-full bg-slate-200 px-2 py-1 text-[11px] font-bold uppercase text-slate-800">Inativo</span>
						) : (
							<span className="rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-bold uppercase text-emerald-900">Ativo</span>
						)}
						<PwaButton
							type="button"
							variant="ghost"
							aria-expanded={showClientDetails}
							aria-controls={clientDetailsId}
							onClick={() => setShowClientDetails((current) => !current)}
							className="min-h-9 px-2 text-xs"
						>
							{showClientDetails ? 'Ocultar detalhes' : 'Ver detalhes'}
							<PwaIcon name="chevronRight" size={15} className={`transition-transform ${showClientDetails ? 'rotate-90' : ''}`} />
						</PwaButton>
					</div>
				</div>

				{showClientDetails ? <div id={clientDetailsId} className="animate-[pwa-screen-enter_180ms_ease-out]">
					<dl className="mt-5 grid gap-2 text-sm sm:grid-cols-2">
						{[['Idade', `${client.age ?? '-'} anos`], ['Sexo', sexLabels[client.biological_sex] ?? '-'], ['Altura', `${numberBr(client.height_cm, 0)} cm`]].map(([label, value]) => <div key={label} className="rounded-xl bg-ricosty-cream/70 p-3"><dt className="text-[11px] font-bold uppercase tracking-wide text-slate-600">{label}</dt><dd className="mt-1 font-bold text-ricosty-ink">{value}</dd></div>)}
						<div className="rounded-xl bg-ricosty-cream/70 p-3">
							<dt className="flex items-center justify-between gap-2 text-[11px] font-bold uppercase tracking-wide text-slate-600"><span>Nascimento</span>{canEditBirthDate && !editingBirthDate ? <button type="button" onClick={() => setEditingBirthDate(true)} className="pwa-focus min-h-8 rounded-lg px-2 text-[11px] font-bold normal-case tracking-normal text-ricosty-700 hover:bg-white">Editar</button> : null}</dt>
							<dd className="mt-1 font-bold text-ricosty-ink">
								{editingBirthDate ? <form onSubmit={saveBirthDate} className="space-y-2">
									<label className="sr-only" htmlFor={`client-birth-date-${client.id}`}>Data de nascimento</label>
									<input id={`client-birth-date-${client.id}`} type="date" required max={localDatetimeNow().slice(0, 10)} value={birthDate} onChange={(event) => { setBirthDate(event.target.value); setBirthDateError(''); }} className="pwa-focus min-h-11 w-full rounded-lg border border-ricosty-200 bg-white px-2 font-normal" />
									<div className="flex flex-wrap gap-2"><button type="submit" disabled={savingBirthDate} className="pwa-focus min-h-9 rounded-lg bg-ricosty-700 px-3 text-xs font-bold text-white disabled:opacity-60">{savingBirthDate ? 'Salvando...' : 'Salvar'}</button><button type="button" disabled={savingBirthDate} onClick={() => { setEditingBirthDate(false); setBirthDate(client.birth_date ?? ''); setBirthDateError(''); }} className="pwa-focus min-h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 disabled:opacity-60">Cancelar</button></div>
									{birthDateError ? <p role="alert" className="text-xs font-semibold leading-5 text-rose-700">{birthDateError}</p> : null}
								</form> : formatIssueDate(client.birth_date)}
							</dd>
						</div>
					</dl>

					<div className="mt-4 space-y-2 border-t border-ricosty-100 pt-4 text-sm text-slate-600">
						<p className="flex items-center gap-2"><PwaIcon name="phone" size={16} className="text-ricosty-500" /><strong className="sr-only">Telefone:</strong> {client.phone || '-'}</p>
						<p className="flex items-center gap-2"><PwaIcon name="mail" size={16} className="text-ricosty-500" /><strong className="sr-only">E-mail:</strong> {client.email || '-'}</p>
						{client.cpf ? <p><strong>CPF:</strong> {cpfMask(client.cpf)}</p> : null}
						<p><strong>Próxima avaliação:</strong> {formatIssueDate(client.next_assessment_at)}</p>
					</div>
				</div> : null}

			</PwaCard>

			{!opensSingleModuleDirectly ? <div className="mt-4">
				<ClientModuleSummary
					client={client}
					canStartAssessment={access.allowed}
					canAccessScreenings={canAccessScreenings}
					onStartAssessment={() => onNewAssessment(client)}
					onOpenScreenings={openScreenings}
					onOpenReports={() => latestActiveAssessment && onOpenReport(client, latestActiveAssessment)}
					enabledModules={enabledModules}
				/>
				{access.reason ? <p className="mt-2 text-center text-xs font-semibold text-amber-700">{access.reason}</p> : null}
			</div> : access.reason && enabledModules.assessment ? <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-center text-xs font-semibold text-amber-800">{access.reason}</p> : null}

			{needsModuleChoice ? <div className="mt-4"><ClientWorkspaceTabs activeTab={activeTab} onChange={setActiveTab} enabledModules={enabledModules} sticky /></div> : null}

			<div id="client-panel-assessment" role="tabpanel" aria-labelledby="client-tab-assessment" className={enabledModules.assessment && activeTab === 'assessment' ? '' : 'hidden'}>
			<div className="mt-4">
				<ClientAssessmentHeader
					assessmentCount={assessments.length}
					canStartAssessment={access.allowed}
					hasReport={enabledModules.reports && Boolean(latestActiveAssessment)}
					onStartAssessment={() => onNewAssessment(client)}
					onOpenReport={() => latestActiveAssessment && onOpenReport(client, latestActiveAssessment)}
				/>
			</div>
			<PwaCard className="mt-4 p-5">
				<div className="flex items-center justify-between gap-3">
					<h3 className="flex items-center gap-2 font-extrabold text-ricosty-ink"><PwaIcon name="history" className="text-ricosty-600" />Histórico de bioimpedância</h3>
					<span className="text-xs text-slate-500">{assessments.length} avaliação(ões)</span>
				</div>

				<ol className="relative mt-4 space-y-3 before:absolute before:bottom-4 before:left-[9px] before:top-4 before:w-px before:bg-ricosty-200">
					{assessments.slice(0, 5).map((assessment, index) => (
						<li key={assessment.id}>
							<div className={`relative ml-5 rounded-2xl border p-4 ${assessment.is_canceled ? 'border-rose-100 bg-rose-50/50' : 'border-ricosty-100 bg-ricosty-cream/45'}`}>
								<span className={`absolute -left-[23px] top-5 flex h-4 w-4 items-center justify-center rounded-full ring-4 ring-white ${assessment.is_canceled ? 'bg-rose-400' : index === 0 ? 'bg-ricosty-600' : 'bg-ricosty-300'}`}><span className="h-1.5 w-1.5 rounded-full bg-white" /></span>
								<div className="flex items-center justify-between gap-3">
									<span className="flex items-center gap-1.5 text-sm font-bold text-slate-900"><PwaIcon name="calendar" size={15} className="text-ricosty-500" />{formatDate(assessment.evaluated_at)}</span>
									<span className="text-[10px] font-bold uppercase text-slate-500">{assessment.is_canceled ? 'Cancelada' : assessment.device_model}</span>
								</div>
								<div className="mt-3 grid gap-2 sm:grid-cols-2"><div className="rounded-lg bg-white/80 px-3 py-2"><span className="block text-[11px] font-bold uppercase text-slate-600">Peso</span><strong className="text-sm text-slate-800">{numberBr(assessment.weight_kg, 1)} kg</strong></div><div className="rounded-lg bg-white/80 px-3 py-2"><span className="block text-[11px] font-bold uppercase text-slate-600">IMC</span><strong className="text-sm text-slate-800">{numberBr(assessment.calculated_bmi ?? assessment.scale_bmi, 1)}</strong></div></div>
								<div className="mt-3 flex flex-wrap gap-2">
									<p className="w-full text-[11px] font-extrabold uppercase tracking-wide text-slate-500">Ações da avaliação</p>
									{enabledModules.reports ? <PwaButton type="button" onClick={() => onOpenReport(client, assessment)} variant="secondary" icon="fileText" className="px-3 text-xs">Relatório corporal</PwaButton> : null}
									{canUseClinicalFeatures(currentUser) ? <PwaButton type="button" onClick={() => onOpenAssistant(client, assessment)} variant="ghost" icon="sparkles" className="px-3 text-xs">Assistente</PwaButton> : null}
								</div>
							</div>
						</li>
					))}
					{!assessments.length ? <li className="rounded-xl border border-dashed border-slate-200 p-4 text-center text-sm text-slate-500">Nenhuma avaliação registrada.</li> : null}
				</ol>
			</PwaCard>
			</div>

			<div id="client-panel-screenings" role="tabpanel" aria-labelledby="client-tab-screenings" className={enabledModules.screening && activeTab === 'screenings' ? '' : 'hidden'}>
				{canAccessScreenings ? <ScreeningInvitationPanel client={client} canCreateInvitation={client.is_active && !client.is_anonymized} creationUnavailableReason={client.is_anonymized ? 'Cliente anonimizado não pode receber novas triagens. Os dados remanescentes seguem as regras de retenção e auditoria.' : 'Cliente inativo não pode receber novas triagens. O histórico permanece disponível para consulta.'} /> : (
					<PwaCard className="mt-4 border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
						<strong>Triagens indisponíveis para este perfil.</strong>
						<p className="mt-1">É necessário usar uma conta de administrador ou profissional para consultar e administrar triagens.</p>
					</PwaCard>
				)}
			</div>
		</section>
	);
}
