export function xsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const value = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');

    if (!value) {
        return '';
    }

    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}
