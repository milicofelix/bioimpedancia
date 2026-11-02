export const activeShare = {
    id: 901,
    bioimpedance_assessment_id: 501,
    channel: 'copy',
    expires_at: '2030-10-30T14:00:00.000Z',
    revoked_at: null,
    viewed_at: null,
    view_count: 0,
    is_active: true,
};

export const assessment = {
    id: 501,
    bioimpedance_client_id: 301,
    evaluated_at: '2026-10-31T17:36:00.000Z',
    weight_kg: 97,
    scale_bmi: 32,
    calculated_bmi: 32,
    body_fat_percentage: 28,
    skeletal_muscle_percentage: 41.3,
    body_water_percentage: 52.7,
    resting_metabolism_kcal: 1877,
    visceral_fat_level: 11,
    device_model: 'Relaxmedic',
    is_canceled: false,
    shares: [],
    extended_metrics: [],
    segmental_measurements: [],
    impedance_readings: [],
};

export const client = {
    id: 301,
    full_name: 'Cliente E2E Vita Scan',
    birth_date: '1990-05-10',
    biological_sex: 'female',
    height_cm: 165,
    age: 36,
    phone: '(11) 90000-0301',
    email: 'cliente.e2e@example.test',
    is_active: true,
    is_anonymized: false,
    last_assessment_at: assessment.evaluated_at,
    assessments: [assessment],
};

export const workspace = {
    current_user: {
        id: 101,
        name: 'Profissional E2E',
        email: 'profissional.e2e@example.test',
        role: 'professional',
    },
    clinic: {
        name: 'Vita Scan E2E',
        scale_model: 'relaxmedic',
        assessment_pdf_enabled: true,
        enabled_modules: {
            assessment: true,
            screening: true,
            reports: true,
        },
    },
    clients: [client],
};

export const relaxmedicExtraction = {
    measured_at: '2026-10-31T17:36',
    metrics: {
        weight_kg: 97,
        scale_bmi: 32,
        body_fat_percentage: 28,
        muscle_rate_percentage: 67.1,
        lean_body_mass_kg: 69.8,
        subcutaneous_fat_percentage: 20,
        visceral_fat_level: 11,
        body_water_percentage: 52.7,
        skeletal_muscle_percentage: 41.3,
        muscle_mass_kg: 65.1,
        bone_mass_kg: 4.7,
        protein_percentage: 14.4,
        resting_metabolism_kcal: 1877,
        body_age: 47,
        fat_mass_kg: 27.2,
        water_weight_kg: 51.1,
        protein_mass_kg: 14,
        ideal_body_weight_kg: 66.6,
        obesity_level: 'Muito alto',
        body_type: 'Padrão',
    },
    missing_fields: [],
    unrecognized_fields: [],
    suspicious_values: [],
    allowed_values: {
        obesity_level: ['Baixo', 'Padrão', 'Alto', 'Muito alto'],
    },
    image: {
        application: 'relaxfit',
        layout: 'relaxfit_vertical_report',
        extraction_profile_version: '1.0.0',
    },
};

export function assessmentResponse(overrides = {}) {
    const createdAssessment = { ...assessment, id: 502, shares: [], ...overrides };

    return {
        assessment: createdAssessment,
        client: {
            ...client,
            assessments: [createdAssessment, ...client.assessments],
        },
    };
}
