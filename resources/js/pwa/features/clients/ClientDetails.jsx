import React, { useEffect, useState } from 'react';
import { formatDate, formatIssueDate, localDatetimeNow, numberBr } from '../../../shared/formatters';
import { cpfMask, heightMask } from '../../../shared/inputMasks';
import { assessmentAccess, canUseClinicalFeatures } from './clientAccess';
import { apiErrorMessage, apiValidationErrors, updateBioimpedanceClientProfile } from '../../../services/bioimpedanceApi';
import PwaButton from '../../components/ui/PwaButton';
import PwaCard from '../../components/ui/PwaCard';
import PwaIcon from '../../components/ui/PwaIcon';
import PwaAlert from '../../components/ui/PwaAlert';
import PwaClientIdentity from '../../components/ui/PwaClientIdentity';
import PwaState from '../../components/ui/PwaState';
import PwaSectionHeader from '../../components/ui/PwaSectionHeader';
import ScreeningInvitationPanel from './ScreeningInvitationPanel';
import ClientModuleSummary from '../../../shared/ClientModuleSummary';
import ClientWorkspaceTabs from '../../../shared/ClientWorkspaceTabs';
import ClientAssessmentHeader from '../../../shared/ClientAssessmentHeader';

const sexLabels = { female: 'Feminino', male: 'Masculino' };

function clientProfile(client) {
	return {
		birth_date: client.birth_date ?? '',
		biological_sex: client.biological_sex ?? '',
		height_cm: client.height_cm ? String(client.height_cm).replace('.', ',') : '',
	};
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
	const [editingProfile, setEditingProfile] = useState(false);
	const [profile, setProfile] = useState(() => clientProfile(client));
	const [profileErrors, setProfileErrors] = useState({});
	const [savingProfile, setSavingProfile] = useState(false);
	const clientDetailsId = `client-profile-details-${client.id}`;
	const canEditProfile = canUseClinicalFeatures(currentUser) && !client.is_anonymized;

	useEffect(() => setActiveTab(firstActiveTab), [client.id, firstActiveTab]);
	useEffect(() => setShowClientDetails(false), [client.id]);
	useEffect(() => {
		setEditingProfile(false);
		setProfile(clientProfile(client));
		setProfileErrors({});
	}, [client.id, client.birth_date, client.biological_sex, client.height_cm]);

	function updateProfileField(field, value) {
		setProfile((current) => ({ ...current, [field]: field === 'height_cm' ? heightMask(value) : value }));
		setProfileErrors((current) => ({ ...current, [field]: undefined }));
	}

	async function saveProfile(event) {
		event.preventDefault();
		if (savingProfile) return;

		setSavingProfile(true);
		setProfileErrors({});
		try {
			const updatedClient = await updateBioimpedanceClientProfile(client.id, profile);
			onClientUpdated(updatedClient);
			setEditingProfile(false);
		} catch (error) {
			const validationErrors = apiValidationErrors(error);
			setProfileErrors({ ...validationErrors, form: apiErrorMessage(error, 'Não foi possível atualizar os dados do cliente.') });
		} finally {
			setSavingProfile(false);
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
				<PwaClientIdentity
					name={client.full_name}
					headingId="client-details-title"
					status={client.is_anonymized ? 'anonymized' : client.is_active ? 'active' : 'inactive'}
					action={<PwaButton
							type="button"
							variant="ghost"
							aria-expanded={showClientDetails}
							aria-controls={clientDetailsId}
							onClick={() => setShowClientDetails((current) => !current)}
							className="min-h-9 px-2 text-xs"
						>
							{showClientDetails ? 'Ocultar detalhes' : 'Ver detalhes'}
							<PwaIcon name="chevronRight" size={15} className={`transition-transform ${showClientDetails ? 'rotate-90' : ''}`} />
						</PwaButton>}
				/>

				{showClientDetails ? <div id={clientDetailsId} className="animate-[pwa-screen-enter_180ms_ease-out]">
					<PwaSectionHeader className="mt-5" icon={editingProfile ? 'clipboard' : 'eye'} title={editingProfile ? 'Editando dados pessoais' : 'Dados pessoais'} description={editingProfile ? 'Revise as alterações antes de salvar. A idade continuará sendo calculada automaticamente.' : 'Modo de consulta. Use Editar dados somente quando precisar corrigir o cadastro.'} headingLevel="h3" action={canEditProfile && !editingProfile ? <PwaButton type="button" variant="secondary" icon="clipboard" onClick={() => setEditingProfile(true)} className="min-h-10 px-3 text-xs">Editar dados</PwaButton> : null} />
					{editingProfile ? <form onSubmit={saveProfile} className="mt-3 grid gap-2 rounded-2xl border border-ricosty-200 bg-ricosty-50/50 p-3 text-sm sm:grid-cols-2">
						<div className="rounded-xl bg-ricosty-cream/70 p-3"><span className="text-[11px] font-bold uppercase tracking-wide text-slate-600">Idade</span><strong className="mt-2 block text-ricosty-ink">{client.age ?? '-'} anos</strong><span className="mt-1 block text-[11px] text-slate-500">Calculada automaticamente</span></div>
						<label className="rounded-xl bg-ricosty-cream/70 p-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">Sexo biológico<select required value={profile.biological_sex} onChange={(event) => updateProfileField('biological_sex', event.target.value)} className="pwa-focus mt-2 min-h-11 w-full rounded-lg border border-ricosty-200 bg-white px-2 text-sm font-normal normal-case tracking-normal text-slate-900"><option value="female">Feminino</option><option value="male">Masculino</option></select>{profileErrors.biological_sex?.[0] ? <span className="mt-1 block text-xs normal-case tracking-normal text-rose-700">{profileErrors.biological_sex[0]}</span> : null}</label>
						<label className="rounded-xl bg-ricosty-cream/70 p-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">Altura<input required inputMode="decimal" placeholder="1,74 ou 174" value={profile.height_cm} onChange={(event) => updateProfileField('height_cm', event.target.value)} className="pwa-focus mt-2 min-h-11 w-full rounded-lg border border-ricosty-200 bg-white px-2 text-sm font-normal normal-case tracking-normal text-slate-900" />{profileErrors.height_cm?.[0] ? <span className="mt-1 block text-xs normal-case tracking-normal text-rose-700">{profileErrors.height_cm[0]}</span> : null}</label>
						<label className="rounded-xl bg-ricosty-cream/70 p-3 text-[11px] font-bold uppercase tracking-wide text-slate-600">Nascimento<input type="date" required max={localDatetimeNow().slice(0, 10)} value={profile.birth_date} onChange={(event) => updateProfileField('birth_date', event.target.value)} className="pwa-focus mt-2 min-h-11 w-full rounded-lg border border-ricosty-200 bg-white px-2 text-sm font-normal normal-case tracking-normal text-slate-900" />{profileErrors.birth_date?.[0] ? <span className="mt-1 block text-xs normal-case tracking-normal text-rose-700">{profileErrors.birth_date[0]}</span> : null}</label>
						<div className="flex flex-wrap gap-2 sm:col-span-2"><PwaButton type="submit" disabled={savingProfile} busy={savingProfile} icon="check" className="flex-1 sm:flex-none">{savingProfile ? 'Salvando...' : 'Salvar alterações'}</PwaButton><PwaButton type="button" disabled={savingProfile} variant="ghost" onClick={() => { setEditingProfile(false); setProfile(clientProfile(client)); setProfileErrors({}); }}>Cancelar edição</PwaButton></div>
						{profileErrors.form && !profileErrors.birth_date && !profileErrors.biological_sex && !profileErrors.height_cm ? <p role="alert" className="text-xs font-semibold leading-5 text-rose-700 sm:col-span-2">{profileErrors.form}</p> : null}
					</form> : <dl className="mt-3 grid gap-2 text-sm sm:grid-cols-2">{[['Idade', `${client.age ?? '-'} anos`, 'clock'], ['Sexo', sexLabels[client.biological_sex] ?? '-', 'user'], ['Altura', `${numberBr(client.height_cm, 0)} cm`, 'activity'], ['Nascimento', formatIssueDate(client.birth_date), 'calendar']].map(([label, value, icon]) => <div key={label} className="flex items-start gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3"><PwaIcon name={icon} size={17} className="mt-0.5 shrink-0 text-ricosty-500" /><div><dt className="text-[11px] font-bold uppercase tracking-wide text-slate-600">{label}</dt><dd className="mt-1 font-bold text-ricosty-ink">{value}</dd></div></div>)}</dl>}

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
				{access.reason ? <PwaAlert tone="attention" className="mt-2" title="Avaliação indisponível">{access.reason}</PwaAlert> : null}
			</div> : access.reason && enabledModules.assessment ? <PwaAlert tone="attention" className="mt-4" title="Avaliação indisponível">{access.reason}</PwaAlert> : null}

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
				<PwaSectionHeader icon="history" title="Histórico de bioimpedância" description="Consulte resultados anteriores ou abra as ações de cada avaliação." headingLevel="h3" action={<span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{assessments.length}</span>} />

				<ol className="relative mt-4 space-y-3 before:absolute before:bottom-4 before:left-[9px] before:top-4 before:w-px before:bg-ricosty-200">
					{assessments.slice(0, 5).map((assessment, index) => (
						<li key={assessment.id}>
							<div className={`relative ml-5 rounded-2xl border p-4 ${assessment.is_canceled ? 'border-rose-100 bg-rose-50/50' : 'border-ricosty-100 bg-ricosty-cream/45'}`}>
								<span className={`absolute -left-[23px] top-5 flex h-4 w-4 items-center justify-center rounded-full ring-4 ring-white ${assessment.is_canceled ? 'bg-rose-400' : index === 0 ? 'bg-ricosty-600' : 'bg-ricosty-300'}`}><span className="h-1.5 w-1.5 rounded-full bg-white" /></span>
								<div className="flex items-center justify-between gap-3">
									<span className="flex items-center gap-1.5 text-sm font-bold text-slate-900"><PwaIcon name="calendar" size={15} className="text-ricosty-500" />{formatDate(assessment.evaluated_at)}</span>
									<span className="text-[10px] font-bold uppercase text-slate-500">{assessment.is_canceled ? 'Cancelada' : assessment.device_model}</span>
								</div>
								<div className="mt-3 grid grid-cols-2 gap-2"><div className="flex items-center gap-2 rounded-lg bg-white/80 px-3 py-2"><PwaIcon name="scale" size={17} className="text-ricosty-500" /><span><span className="block text-[10px] font-bold uppercase text-slate-600">Peso</span><strong className="text-sm text-slate-800">{numberBr(assessment.weight_kg, 1)} kg</strong></span></div><div className="flex items-center gap-2 rounded-lg bg-white/80 px-3 py-2"><PwaIcon name="gauge" size={17} className="text-sky-600" /><span><span className="block text-[10px] font-bold uppercase text-slate-600">IMC</span><strong className="text-sm text-slate-800">{numberBr(assessment.calculated_bmi ?? assessment.scale_bmi, 1)}</strong></span></div></div>
								<div className="mt-3 flex flex-wrap gap-2">
									<p className="w-full text-[11px] font-extrabold uppercase tracking-wide text-slate-500">Ações da avaliação</p>
									{enabledModules.reports ? <PwaButton type="button" onClick={() => onOpenReport(client, assessment)} variant="secondary" icon="fileText" className="px-3 text-xs">Abrir relatório</PwaButton> : null}
									{canUseClinicalFeatures(currentUser) ? <PwaButton type="button" onClick={() => onOpenAssistant(client, assessment)} variant="ghost" icon="sparkles" className="px-2 text-xs text-slate-600">Assistente</PwaButton> : null}
								</div>
							</div>
						</li>
					))}
					{!assessments.length ? <li><PwaState icon="history" title="Nenhuma avaliação registrada" description="A primeira avaliação corporal aparecerá aqui após ser concluída." /></li> : null}
				</ol>
			</PwaCard>
			</div>

			<div id="client-panel-screenings" role="tabpanel" aria-labelledby="client-tab-screenings" className={enabledModules.screening && activeTab === 'screenings' ? '' : 'hidden'}>
				{canAccessScreenings ? <ScreeningInvitationPanel client={client} canCreateInvitation={client.is_active && !client.is_anonymized} creationUnavailableReason={client.is_anonymized ? 'Cliente anonimizado não pode receber novas triagens. Os dados remanescentes seguem as regras de retenção e auditoria.' : 'Cliente inativo não pode receber novas triagens. O histórico permanece disponível para consulta.'} /> : (
					<PwaAlert className="mt-4" tone="attention" title="Triagens indisponíveis para este perfil">É necessário usar uma conta de administrador ou profissional para consultar e administrar triagens.</PwaAlert>
				)}
			</div>
		</section>
	);
}
