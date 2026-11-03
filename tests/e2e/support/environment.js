export function e2eCredentials() {
    const email = process.env.PLAYWRIGHT_USER_EMAIL;
    const password = process.env.PLAYWRIGHT_USER_PASSWORD;

    if (!email || !password) {
        throw new Error('Defina PLAYWRIGHT_USER_EMAIL e PLAYWRIGHT_USER_PASSWORD para executar os testes E2E.');
    }

    return { email, password };
}
