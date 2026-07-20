import { usePage } from '@inertiajs/vue3';

const sharedKeys = ['q', 'type', 'location', 'from', 'to'];

export function usePublicViewLinks() {
    const page = usePage();

    function publicViewHref(path: string): string {
        const queryString = page.url.split('?', 2)[1] ?? '';
        const current = new URLSearchParams(queryString);
        const shared = new URLSearchParams();

        for (const key of sharedKeys) {
            const value = current.get(key);

            if (value !== null && value !== '') {
                shared.set(key, value);
            }
        }

        const encoded = shared.toString();

        return encoded === '' ? path : `${path}?${encoded}`;
    }

    return { publicViewHref };
}
