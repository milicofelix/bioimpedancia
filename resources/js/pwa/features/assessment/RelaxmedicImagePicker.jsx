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
			<div className="flex items-start gap-3"><div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-ricosty-100 text-ricosty-700"><PwaIcon name="camera" size={24} /></div><div><p className="text-xs font-bold uppercase tracking-wide text-ricosty-600">Relatório Relaxmedic</p><h2 className="mt-1 text-lg font-bold text-slate-950">Como deseja adicionar?</h2></div></div>
			<p className="mt-1 text-sm leading-6 text-slate-600">Use a foto completa, nítida e sem reflexos. Formato JPEG/JPG, até 10 MB.</p>

			<input ref={cameraInput} type="file" accept="image/jpeg,.jpg,.jpeg" capture="environment" onChange={select} className="sr-only" tabIndex={-1} />
			<input ref={galleryInput} type="file" accept="image/jpeg,.jpg,.jpeg" onChange={select} className="sr-only" tabIndex={-1} />
			<div className="mt-4 grid grid-cols-2 gap-3">
				<PwaButton type="button" icon="camera" disabled={disabled} onClick={() => cameraInput.current?.click()} className="min-h-24 flex-col px-2"><span className="text-center">Fotografar<span className="mt-1 block text-[10px] font-medium text-white/75">Abrir câmera traseira</span></span></PwaButton>
				<PwaButton type="button" icon="image" variant="secondary" disabled={disabled} onClick={() => galleryInput.current?.click()} className="min-h-24 flex-col px-2"><span className="text-center">Galeria<span className="mt-1 block text-[10px] font-medium text-slate-500">Usar foto existente</span></span></PwaButton>
			</div>
			<div className="mt-3 grid grid-cols-3 gap-2 text-center text-[10px] font-semibold text-slate-500"><span className="rounded-lg bg-slate-50 px-2 py-2">Página inteira</span><span className="rounded-lg bg-slate-50 px-2 py-2">Sem reflexos</span><span className="rounded-lg bg-slate-50 px-2 py-2">Texto legível</span></div>

			{error ? <p role="alert" className="pwa-status-enter mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{error}</p> : null}
			{previewUrl ? (
				<div className="pwa-screen-enter mt-4 overflow-hidden rounded-2xl border border-ricosty-200 bg-ricosty-cream/50 p-3">
					<img src={previewUrl} alt="Prévia do relatório Relaxmedic selecionado" className="mx-auto max-h-80 rounded-lg object-contain" />
					<div className="mt-3 flex items-center justify-center gap-2 text-xs text-slate-600"><span className="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><PwaIcon name="check" size={12} /></span><span className="max-w-[80%] truncate">{fileName}</span></div>
				</div>
			) : null}
		</section>
	);
}
