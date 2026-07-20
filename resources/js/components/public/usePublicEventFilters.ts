import { CalendarDate, getLocalTimeZone, today } from '@internationalized/date';
import { computed, ref } from 'vue';
import {
    anyDateValue,
    customDateValue,
} from '@/components/public/publicEventDisplay';
import { useCustomDateRange } from '@/components/public/useCustomDateRange';
import type {
    PublicEventFilterOptions,
    PublicEventParameters,
    PublicEventQuery,
} from '@/types/public-events';

export function usePublicEventFilters(
    query: PublicEventQuery,
    filters: PublicEventFilterOptions,
    dateSelectId: string,
) {
    const searchTerm = ref(query.q ?? '');
    const locationChoices = ref([...query.location]);
    const categoryChoices = ref([...query.type]);
    const upcomingOnly = ref(!query.ongoing);
    const date = useCustomDateRange(dateSelectId, query);

    const locationOptions = computed(() => filters.locations);
    const categoryOptions = computed(() => filters.types);
    const hasClearableFilters = computed(
        () =>
            searchTerm.value.trim() !== '' ||
            locationChoices.value.length > 0 ||
            categoryChoices.value.length > 0 ||
            date.dateChoice.value !== anyDateValue,
    );

    function parameters(): PublicEventParameters {
        const parameters: PublicEventParameters = {};
        const search = searchTerm.value.trim();

        if (search !== '') {
            parameters.q = search;
        }

        if (locationChoices.value.length > 0) {
            parameters.location = locationChoices.value;
        }

        if (categoryChoices.value.length > 0) {
            parameters.type = categoryChoices.value;
        }

        if (!upcomingOnly.value) {
            parameters.ongoing = '1';
        }

        Object.assign(parameters, dateParameters());

        return parameters;
    }

    function reset(): void {
        searchTerm.value = '';
        locationChoices.value = [];
        categoryChoices.value = [];
        upcomingOnly.value = true;
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
        locationChoices,
        categoryChoices,
        upcomingOnly,
        locationOptions,
        categoryOptions,
        hasClearableFilters,
        parameters,
        reset,
    };
}
