import React from 'react';

export default function PwaCard({ as: Element = 'section', soft = false, className = '', children, ...props }) {
	return <Element className={`${soft ? 'pwa-card-soft' : 'pwa-card'} ${className}`} {...props}>{children}</Element>;
}
