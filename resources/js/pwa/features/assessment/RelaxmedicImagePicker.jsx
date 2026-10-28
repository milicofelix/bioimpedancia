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

export default function RelaxmedicImagePicker({ previewUrl, fileName, reportName = 'Relaxmedic', disabled, error, onSelect }) {
	const galleryInput = useRef(null);

	function select(event) {
		onSelect(event.target.files?.[0] ?? null, () => {
			if (galleryInput.current) galleryInput.current.value = '';
		});
	}

	return (
		<section className="pwa-card p-5">
			<div className="flex items-start gap-3"><div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-ricosty-100 text-ricosty-700"><PwaIcon name="image" size={24} /></div><div><p className="text-xs font-bold uppercase tracking-wide text-ricosty-600">Relatório {reportName}</p><h2 className="mt-1 text-lg font-bold text-slate-950">Adicionar relatório</h2></div></div>
			<p className="mt-1 text-sm leading-6 text-slate-600">Selecione a imagem completa, nítida e sem reflexos. Formato JPEG/JPG, até 10 MB.</p>

			<input ref={galleryInput} type="file" accept="image/jpeg,.jpg,.jpeg" onChange={select} className="sr-only" tabIndex={-1} />
			<PwaButton type="button" icon="image" disabled={disabled} onClick={() => galleryInput.current?.click()} className="mt-4 min-h-20 w-full px-4"><span className="text-center">Escolher imagem<span className="mt-1 block text-[10px] font-medium text-white/75">Selecionar um relatório JPEG da galeria</span></span></PwaButton>
			<div className="mt-3 grid gap-2 text-center text-xs font-semibold text-slate-600 sm:grid-cols-3"><span className="rounded-lg bg-slate-50 px-2 py-2">Página inteira</span><span className="rounded-lg bg-slate-50 px-2 py-2">Sem reflexos</span><span className="rounded-lg bg-slate-50 px-2 py-2">Texto legível</span></div>

			{error ? <p role="alert" className="pwa-status-enter mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{error}</p> : null}
			{previewUrl ? (
				<div className="pwa-screen-enter mt-4 overflow-hidden rounded-2xl border border-ricosty-200 bg-ricosty-cream/50 p-3">
					<div className={`${reportName === 'Bodyscan Pro' ? 'max-h-[70vh] overflow-y-auto' : 'max-h-80'} rounded-lg bg-white`}><img src={previewUrl} alt={`Prévia do relatório ${reportName} selecionado`} className="mx-auto h-auto w-full rounded-lg object-contain" /></div>
					<div className="mt-3 flex items-start justify-center gap-2 text-xs text-slate-700"><span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><PwaIcon name="check" size={12} /></span><span className="break-all text-center">{fileName}</span></div>
				</div>
			) : null}
		</section>
	);
}
