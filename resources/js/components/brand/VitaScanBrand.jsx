import React from 'react';

const sizes = {
	compact: {
		mark: 'h-10 w-9',
		name: 'text-xl',
		subtitle: 'text-[7px] tracking-[0.16em]',
		tagline: 'text-[7px]',
	},
	default: {
		mark: 'h-16 w-14 sm:h-20 sm:w-[4.5rem]',
		name: 'text-3xl sm:text-4xl',
		subtitle: 'text-[9px] sm:text-[11px] tracking-[0.2em]',
		tagline: 'text-[9px] sm:text-[10px]',
	},
	large: {
		mark: 'h-20 w-16 sm:h-40 sm:w-36',
		name: 'text-3xl sm:text-6xl',
		subtitle: 'text-xs sm:text-sm tracking-[0.22em]',
		tagline: 'text-xs sm:text-sm',
	},
};

export default function VitaScanBrand({
	className = '',
	headingLevel = 'h2',
	showTagline = true,
	size = 'default',
}) {
	const Heading = headingLevel;
	const styles = sizes[size] ?? sizes.default;

	return (
		<div className={`inline-flex min-w-0 items-center gap-3 ${className}`}>
			<img
				src="/images/brand/bioflow-mark.png"
				alt=""
				aria-hidden="true"
				className={`${styles.mark} shrink-0 object-contain`}
			/>
			<div className="min-w-0 leading-none">
				<Heading className={`${styles.name} whitespace-nowrap font-black tracking-[-0.05em]`}>
					<span className="text-[#052f59]">Vita </span>
					<span className="bg-gradient-to-r from-[#159fbd] to-[#55ce83] bg-clip-text text-transparent">Scan</span>
				</Heading>
				<p className={`mt-1 whitespace-nowrap font-medium uppercase text-[#526172] ${styles.subtitle}`}>Análise clínica Biológica</p>
				{showTagline ? <p className={`mt-2 max-w-sm leading-snug text-[#526172] ${styles.tagline}`}>Conhecimento do seu corpo, em todas as fases.</p> : null}
			</div>
		</div>
	);
}
