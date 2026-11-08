import React from 'react';
import PwaAlert from '../../components/ui/PwaAlert';

const kilograms = (value) => `${Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 2 })} kg`;

export default function BodyscanConsistencyNotice({ warnings = [], reviewedAt = null }) {
	const differences = warnings.filter((warning) => warning.code === 'segmental_fat_total_difference');
	if (!differences.length) return null;

	return <PwaAlert tone="attention" className="mt-3" title="Conferência dos valores do documento">
		{differences.map((warning) => <p key={warning.code}>
			Na leitura original, a soma dos cinco segmentos de gordura é {kilograms(warning.segmental_sum_kg)} e a massa gorda total é {kilograms(warning.total_fat_mass_kg)}. Diferença: <strong>{kilograms(warning.difference_kg)}</strong>.
		</p>)}
		{reviewedAt ? <p className="mt-2 font-semibold">Conferência profissional registrada em {reviewedAt}. Os números acima correspondem à extração original; eventuais correções permanecem no histórico da avaliação.</p> : <>
			<p className="mt-2">Essa diferença, por si só, não indica erro de leitura. Compare os números com a imagem: se estiverem iguais aos impressos, mantenha-os. Não altere valores apenas para fechar a soma.</p>
			<p className="mt-2 font-semibold">A confirmação final também registra que você conferiu essa divergência. Ela não impede a criação da avaliação após a conferência.</p>
		</>}
	</PwaAlert>;
}
