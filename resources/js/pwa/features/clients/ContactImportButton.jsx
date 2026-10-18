import React, { useState } from 'react';
import useActionLock from '../../../hooks/useActionLock';
import { pickClientContact, supportsContactPicker } from '../../services/contactPicker';
import PwaButton from '../../components/ui/PwaButton';

function contactPickerError(error) {
	if (error?.name === 'AbortError') return '';
	if (error?.name === 'SecurityError' || error?.message === 'CONTACT_PICKER_UNSUPPORTED') {
		return 'A agenda só pode ser aberta em um navegador compatível, usando conexão HTTPS.';
	}
	return 'Não foi possível importar o contato. Você pode preencher os dados manualmente.';
}

export default function ContactImportButton({ onImport }) {
	const runAction = useActionLock();
	const [importing, setImporting] = useState(false);
	const [message, setMessage] = useState('');
	const supported = supportsContactPicker();

	if (!supported) {
		return <p className="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-5 text-slate-600">A importação da agenda não está disponível neste navegador. Preencha os dados manualmente.</p>;
	}

	async function importContact() {
		return runAction('pick-client-contact', async () => {
			setImporting(true);
			setMessage('');
			try {
				const contact = await pickClientContact();
				if (!contact) return;
				onImport(contact);
				setMessage('Contato importado. Confira os dados antes de cadastrar.');
			} catch (error) {
				setMessage(contactPickerError(error));
			} finally {
				setImporting(false);
			}
		});
	}

	return (
		<div>
			<PwaButton type="button" variant="secondary" icon="users" onClick={importContact} disabled={importing} loading={importing} className="w-full">
				{importing ? 'Abrindo agenda...' : 'Importar contato do celular'}
			</PwaButton>
			<p className="mt-2 text-xs leading-5 text-slate-600">Você escolherá uma pessoa e quais dados deseja compartilhar. Nada será salvo antes da confirmação do cadastro.</p>
			{message ? <p role="status" aria-live="polite" className="mt-2 rounded-xl bg-ricosty-50 p-3 text-xs font-semibold text-ricosty-900">{message}</p> : null}
		</div>
	);
}
