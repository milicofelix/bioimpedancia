export const defaultClinicModules = {
	assessment: true,
	screening: true,
	reports: true,
};

export function clinicModules(clinic) {
	return {
		...defaultClinicModules,
		...(clinic?.enabled_modules ?? {}),
	};
}
