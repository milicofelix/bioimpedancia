import React from 'react';
import PwaAlert from '../../components/ui/PwaAlert';

export default function AssistantProvenance({ assistant }) {
	const external = assistant.provider === 'openai';
	return <PwaAlert tone={assistant.fallback_used || assistant.fallback_reason ? 'attention' : 'info'} title={external ? 'Origem: OpenAI' : 'Origem: motor local de referências'} className="mt-4">
		<p>{external ? 'Integração com IA externa. Nenhum conteúdo será incluído no relatório sem aprovação profissional.' : 'Esta origem utiliza regras, dados da avaliação e referências; não é uma geração por IA externa.'}</p>
		{assistant.fallback_reason ? <p className="mt-1">O motor local foi utilizado como alternativa. {assistant.fallback_reason}</p> : null}
	</PwaAlert>;
}
