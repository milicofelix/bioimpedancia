import React, { useRef } from 'react';
import { relaxmedicMaxImageBytes } from '../../../domain/bioimpedance/relaxmedic';
import PwaButton from '../../components/ui/PwaButton';
import PwaIcon from '../../components/ui/PwaIcon';

export function validateRelaxmedicImage(file) {
	if (!file) return 'Selecione uma imagem JPEG ou JPG.';
	if (!/\.jpe?g$/i.test(file.name) || file.type !== 'image/jpeg') return 'Use uma imagem com extensão JPEG/JPG e tipo JPEG válido.';
	if (file.size <= 0 || file.size > relaxmedicMaxImageBytes) return 'A imagem deve possuir conteúdo e ter no máximo 10 MB.';

	return '';
}

export default function RelaxmedicImagePicker({ previewUrl, fileName, disabled, error, onSelect }) {
	const cameraInput = useRef(null);
	const galleryInput = useRef(null);

	function select(event) {
		onSelect(event.target.files?.[0] ?? null, () => {
			if (cameraInput.current) cameraInput.current.value = '';
			if (galleryInput.current) galleryInput.current.value = '';
		});
	}

	return (
		<section className="pwa-card p-5">
			<div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-ricosty-100 text-ricosty-700"><PwaIcon name="camera" size={23} /></div>
			<p className="mt-4 text-xs font-bold uppercase tracking-wide text-ricosty-600">1. Relatório Relaxmedic</p>
			<h2 className="mt-2 text-lg font-bold text-slate-950">Envie a imagem do relatório</h2>
			<p className="mt-1 text-sm leading-6 text-slate-600">Use a foto completa, nítida e sem reflexos. Formato JPEG/JPG, até 10 MB.</p>

			<input ref={cameraInput} type="file" accept="image/jpeg,.jpg,.jpeg" capture="environment" onChange={select} className="sr-only" tabIndex={-1} />
			<input ref={galleryInput} type="file" accept="image/jpeg,.jpg,.jpeg" onChange={select} className="sr-only" tabIndex={-1} />
			<div className="mt-4 grid gap-3 sm:grid-cols-2">
				<PwaButton type="button" icon="camera" disabled={disabled} onClick={() => cameraInput.current?.click()}>Fotografar relatório</PwaButton>
				<PwaButton type="button" icon="image" variant="secondary" disabled={disabled} onClick={() => galleryInput.current?.click()}>Escolher da galeria</PwaButton>
			</div>

			{error ? <p role="alert" className="pwa-status-enter mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{error}</p> : null}
			{previewUrl ? (
				<div className="pwa-screen-enter mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-3">
					<img src={previewUrl} alt="Prévia do relatório Relaxmedic selecionado" className="mx-auto max-h-80 rounded-lg object-contain" />
					<p className="mt-2 truncate text-center text-xs text-slate-500">{fileName}</p>
				</div>
			) : null}
		</section>
	);
}
