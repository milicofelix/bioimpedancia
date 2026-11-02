import { describe, expect, it } from 'vitest';
import { assessmentAccess, canCreateClient, canCreateReportShare, canUseClinicalFeatures } from './clientAccess';

describe('permissões operacionais da PWA', () => {
    it.each(['admin', 'professional', 'reception'])('permite criação de cliente e compartilhamento para %s', (role) => {
        const user = { role };
        expect(canCreateClient(user)).toBe(true);
        expect(canCreateReportShare(user)).toBe(true);
    });

    it('mantém recursos clínicos restritos ao administrador e profissional', () => {
        expect(canUseClinicalFeatures({ role: 'admin' })).toBe(true);
        expect(canUseClinicalFeatures({ role: 'professional' })).toBe(true);
        expect(canUseClinicalFeatures({ role: 'reception' })).toBe(false);
        expect(canUseClinicalFeatures({ role: 'viewer' })).toBe(false);
    });

    it('nega qualquer nova avaliação para cliente inativo ou anonimizado', () => {
        expect(assessmentAccess({ is_active: false }, { role: 'admin' }, 'manual')).toEqual(expect.objectContaining({ allowed: false }));
        expect(assessmentAccess({ is_active: true, is_anonymized: true }, { role: 'admin' }, 'manual')).toEqual(expect.objectContaining({ allowed: false }));
    });

    it('permite avaliação manual pela recepção, mas exige perfil clínico para imagem', () => {
        const client = { is_active: true, is_anonymized: false };
        expect(assessmentAccess(client, { role: 'reception' }, 'manual').allowed).toBe(true);
        expect(assessmentAccess(client, { role: 'reception' }, 'relaxmedic_bodyscan_pro')).toEqual(expect.objectContaining({ allowed: false }));
    });
});
