import React, { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import PwaIcon from '../../components/ui/PwaIcon';

export default function ScreeningQrCode({ payload, questionnaireVersion }) {
	const [imageUrl, setImageUrl] = useState('');
	const [error, setError] = useState('');

	useEffect(() => {
		let active = true;
		setImageUrl(''); setError('');
		QRCode.toDataURL(payload, {
			errorCorrectionLevel: 'H',
			margin: 2,
			width: 320,
			color: { dark: '#053c61ff', light: '#ffffffff' },
		}).then((generatedImage) => {
			if (active) setImageUrl(generatedImage);
		}).catch(() => {
			if (active) setError('Não foi possível gerar o QR Code neste aparelho. Use o botão de copiar link.');
		});

		return () => { active = false; };
	}, [payload]);

	if (error) return <p className="mt-3 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-900" role="alert">{error}</p>;
	if (!imageUrl) return <div className="mt-3 flex min-h-44 items-center justify-center rounded-xl bg-white text-sm font-semibold text-slate-500" role="status"><PwaIcon name="refresh" className="mr-2 animate-spin" />Gerando QR Code local…</div>;

	return (
		<div className="mt-3 rounded-2xl bg-white p-4 text-center">
			<img src={imageUrl} alt={`QR Code para acessar o questionário ${questionnaireVersion}`} className="mx-auto h-56 w-56 max-w-full rounded-xl" />
			<p className="mt-2 text-xs font-semibold text-slate-600">Aponte a câmera do celular para abrir o acesso temporário.</p>
			<a href={imageUrl} download={`triagem-${questionnaireVersion}.png`} className="pwa-focus mt-3 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700"><PwaIcon name="download" size={16} />Salvar QR Code</a>
		</div>
	);
}
