/**
 * Small JSON writes that should not trigger an Inertia page visit (board
 * drags). Sends Laravel's CSRF token from its cookie.
 */

function xsrfToken(): string | null {
    const match = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return match ? decodeURIComponent(match.split('=')[1]) : null;
}

export class HttpError extends Error {
    constructor(
        public status: number,
        message: string,
        public errors: Record<string, string[]> = {},
    ) {
        super(message);
    }
}

export async function sendJson<T>(
    method: 'POST' | 'PATCH' | 'PUT' | 'DELETE',
    url: string,
    body?: Record<string, unknown>,
): Promise<T> {
    const token = xsrfToken();

    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        let message = 'Something went wrong. Please try again.';
        let errors: Record<string, string[]> = {};

        try {
            const data = (await response.json()) as {
                message?: string;
                errors?: Record<string, string[]>;
            };
            errors = data.errors ?? {};
            message = Object.values(errors)[0]?.[0] ?? data.message ?? message;
        } catch {
            // Not JSON; keep the generic message.
        }

        if (response.status === 403) {
            message = 'You do not have permission to do that.';
        } else if (response.status === 419) {
            message = 'Your session expired. Reload the page and try again.';
        }

        throw new HttpError(response.status, message, errors);
    }

    return (await response.json()) as T;
}
