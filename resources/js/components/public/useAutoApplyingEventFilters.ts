import type { DateValue } from '@internationalized/date';
import type { DateRange } from 'reka-ui';
import type { Ref, ShallowRef } from 'vue';
import { onBeforeUnmount, watch } from 'vue';
import { customDateValue } from '@/components/public/publicEventDisplay';
import type { PublicEventParameters } from '@/types/public-events';

interface AutoApplyingEventFilters {
    searchTerm: Ref<string>;
    locationChoices: Ref<string[]>;
    categoryChoices: Ref<string[]>;
    upcomingOnly: Ref<boolean>;
    dateChoice: Ref<string>;
    dateRange: ShallowRef<DateRange>;
    parameters: () => PublicEventParameters;
    apply: () => void;
}

/**
 * Keeps the public filters responsive without issuing a request for every
 * keystroke. Discrete choices apply immediately, completed custom ranges apply
 * once, and free-text search waits briefly for the user to finish typing.
 */
export function useAutoApplyingEventFilters({
    searchTerm,
    locationChoices,
    categoryChoices,
    upcomingOnly,
    dateChoice,
    dateRange,
    parameters,
    apply,
}: AutoApplyingEventFilters) {
    let searchTimer: ReturnType<typeof setTimeout> | null = null;
    let suspended = false;
    let lastAppliedParameters = serialize(parameters());

    function cancelPendingSearch(): void {
        if (searchTimer !== null) {
            clearTimeout(searchTimer);
            searchTimer = null;
        }
    }

    function applyNow(): void {
        if (suspended) {
            return;
        }

        cancelPendingSearch();
        const nextParameters = serialize(parameters());

        if (nextParameters === lastAppliedParameters) {
            return;
        }

        lastAppliedParameters = nextParameters;
        apply();
    }

    function queueSearch(): void {
        if (suspended) {
            return;
        }

        cancelPendingSearch();
        searchTimer = setTimeout(() => {
            searchTimer = null;
            applyNow();
        }, 350);
    }

    watch(searchTerm, queueSearch, { flush: 'sync' });
    watch([locationChoices, categoryChoices, upcomingOnly], applyNow, {
        flush: 'sync',
    });
    watch(
        dateChoice,
        (choice) => {
            if (choice !== customDateValue) {
                applyNow();
            }
        },
        { flush: 'sync' },
    );
    watch(
        dateRange,
        (range: DateRange) => {
            if (
                dateChoice.value === customDateValue &&
                isDateValue(range.start) &&
                isDateValue(range.end)
            ) {
                applyNow();
            }
        },
        { flush: 'sync' },
    );

    function resetWithoutApplying(reset: () => void): void {
        suspended = true;
        cancelPendingSearch();
        reset();
        lastAppliedParameters = serialize(parameters());
        suspended = false;
    }

    onBeforeUnmount(cancelPendingSearch);

    return { applyNow, cancelPendingSearch, resetWithoutApplying };
}

function serialize(parameters: PublicEventParameters): string {
    const serialized = new URLSearchParams();

    for (const [key, value] of Object.entries(parameters)) {
        for (const item of Array.isArray(value) ? value : [value]) {
            serialized.append(key, item);
        }
    }

    return serialized.toString();
}

function isDateValue(value: DateValue | undefined): value is DateValue {
    return value !== undefined;
}
