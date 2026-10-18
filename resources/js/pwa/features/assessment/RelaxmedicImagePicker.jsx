import React, { useRef } from 'react';
import { relaxmedicMaxImageBytes } from '../../../domain/bioimpedance/relaxmedic';

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
		<section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
			<p className="text-xs font-bold uppercase tracking-wide text-emerald-700">1. Relatório Relaxmedic</p>
			<h2 className="mt-2 text-lg font-bold text-slate-950">Envie a imagem do relatório</h2>
			<p className="mt-1 text-sm leading-6 text-slate-600">Use a foto completa, nítida e sem reflexos. Formato JPEG/JPG, até 10 MB.</p>

			<input ref={cameraInput} type="file" accept="image/jpeg,.jpg,.jpeg" capture="environment" onChange={select} className="sr-only" tabIndex={-1} />
			<input ref={galleryInput} type="file" accept="image/jpeg,.jpg,.jpeg" onChange={select} className="sr-only" tabIndex={-1} />
			<div className="mt-4 grid gap-3 sm:grid-cols-2">
				<button type="button" disabled={disabled} onClick={() => cameraInput.current?.click()} className="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Fotografar relatório</button>
				<button type="button" disabled={disabled} onClick={() => galleryInput.current?.click()} className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50">Escolher da galeria</button>
			</div>

			{error ? <p role="alert" className="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{error}</p> : null}
			{previewUrl ? (
				<div className="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-3">
					<img src={previewUrl} alt="Prévia do relatório Relaxmedic selecionado" className="mx-auto max-h-80 rounded-lg object-contain" />
					<p className="mt-2 truncate text-center text-xs text-slate-500">{fileName}</p>
				</div>
			) : null}
		</section>
	);
}
