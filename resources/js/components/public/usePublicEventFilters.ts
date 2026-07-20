import { CalendarDate, getLocalTimeZone, today } from '@internationalized/date';
import { computed, ref } from 'vue';
import {
    anyDateValue,
    customDateValue,
} from '@/components/public/publicEventDisplay';
import { useCustomDateRange } from '@/components/public/useCustomDateRange';
import type {
    PublicEventFilterOptions,
    PublicEventQuery,
} from '@/types/public-events';

export function usePublicEventFilters(
    query: PublicEventQuery,
    filters: PublicEventFilterOptions,
    dateSelectId: string,
) {
    const searchTerm = ref(query.q ?? '');
    const locationChoice = ref(query.location ?? '');
    const categoryChoice = ref(query.type ?? '');
    const date = useCustomDateRange(dateSelectId, query);

    const locationOptions = computed(() => [
        { value: '', label: 'Anywhere' },
        ...filters.locations,
    ]);
    const categoryOptions = computed(() => [
        { value: '', label: 'All categories' },
        ...filters.types,
    ]);
    const hasFilters = computed(
        () =>
            searchTerm.value.trim() !== '' ||
            locationChoice.value !== '' ||
            categoryChoice.value !== '' ||
            date.dateChoice.value !== anyDateValue,
    );

    function parameters(): Record<string, string> {
        const parameters: Record<string, string> = {};
        const search = searchTerm.value.trim();

        if (search !== '') {
            parameters.q = search;
        }

        if (locationChoice.value !== '') {
            parameters.location = locationChoice.value;
        }

        if (categoryChoice.value !== '') {
            parameters.type = categoryChoice.value;
        }

        Object.assign(parameters, dateParameters());

        return parameters;
    }

    function reset(): void {
        searchTerm.value = '';
        locationChoice.value = '';
        categoryChoice.value = '';
        date.clearDateRange();
    }

    function dateParameters(): Record<string, string> {
        if (date.dateChoice.value === anyDateValue) {
            return {};
        }

        if (date.dateChoice.value === customDateValue) {
            return {
                ...(date.dateRange.value.start
                    ? { from: date.dateRange.value.start.toString() }
                    : {}),
                ...(date.dateRange.value.end
                    ? { to: date.dateRange.value.end.toString() }
                    : {}),
            };
        }

        const localTimezone = getLocalTimeZone();
        const current = today(localTimezone);
        let start: CalendarDate = current;
        let end: CalendarDate = current;

        switch (date.dateChoice.value) {
            case 'tomorrow':
                start = current.add({ days: 1 });
                end = start;
                break;
            case 'weekend': {
                const weekday = current.toDate(localTimezone).getDay();
                const daysUntilSaturday =
                    weekday === 0 ? 0 : (6 - weekday + 7) % 7;
                start =
                    weekday === 0
                        ? current
                        : current.add({ days: daysUntilSaturday });
                end = weekday === 0 ? current : start.add({ days: 1 });
                break;
            }
            case 'next-seven-days':
                end = current.add({ days: 6 });
                break;
            case 'month':
                start = new CalendarDate(current.year, current.month, 1);
                end = start.add({ months: 1 }).subtract({ days: 1 });
                break;
        }

        return { from: start.toString(), to: end.toString() };
    }

    return {
        ...date,
        searchTerm,
        locationChoice,
        categoryChoice,
        locationOptions,
        categoryOptions,
        hasFilters,
        parameters,
        reset,
    };
}
