import { usePage } from '@inertiajs/vue3';

export function usePublicViewLinks() {
    const page = usePage();

    function publicViewHref(path: string): string {
        const current = new URLSearchParams(page.url.split('?', 2)[1] ?? '');
        const shared = new URLSearchParams();

        for (const key of ['q', 'from', 'to', 'ongoing']) {
            const value = current.get(key);

            if (value) {
                shared.set(key, value);
            }
        }

        for (const key of ['type', 'location']) {
            for (const [parameter, value] of current.entries()) {
                if (
                    parameter === key ||
                    new RegExp(`^${key}\\[\\d*\\]$`).test(parameter)
                ) {
                    shared.append(`${key}[]`, value);
                }
            }
        }

        const encoded = shared.toString();

        return encoded === '' ? path : `${path}?${encoded}`;
    }

    return { publicViewHref };
}
