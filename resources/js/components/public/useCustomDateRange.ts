import type { DateRange } from 'reka-ui';
import { computed, ref, shallowRef, watch } from 'vue';
import {
    customDateValue,
    dateFilterOptions,
    formatDateRangeLabel,
} from '@/components/public/publicEventDisplay';

/**
 * Shared presentation state for the date filter and its custom range
 * panel. The range locks in automatically as soon as both ends are
 * picked; real query wiring arrives with the backend integration.
 */
export function useCustomDateRange(selectId?: string) {
    const dateChoice = ref(dateFilterOptions[0]);
    // shallowRef keeps the reka-ui calendar date class instances intact;
    // the picker always replaces the whole range object.
    const dateRange = shallowRef<DateRange>({
        start: undefined,
        end: undefined,
    });
    const rangePanelOpen = ref(false);

    const customRangeLabel = computed(
        () =>
            formatDateRangeLabel(dateRange.value.start, dateRange.value.end) ??
            'Custom range…',
    );

    const dateOptions = computed(() => [
        ...dateFilterOptions,
        { value: customDateValue, label: customRangeLabel.value },
    ]);

    const rangeHint = computed(() => {
        if (!dateRange.value.start) {
            return 'Pick a start date';
        }

        return `${customRangeLabel.value} · pick an end date`;
    });

    watch(dateChoice, (choice) => {
        if (choice === customDateValue) {
            rangePanelOpen.value = true;

            return;
        }

        rangePanelOpen.value = false;
        dateRange.value = { start: undefined, end: undefined };
    });

    // Lock in as soon as the second date completes the range; there is no
    // manual confirmation step. Returning focus to the select keeps
    // keyboard users oriented when the panel closes itself.
    watch(dateRange, (range) => {
        if (!range.start || !range.end || !rangePanelOpen.value) {
            return;
        }

        rangePanelOpen.value = false;

        if (selectId) {
            document.getElementById(selectId)?.focus();
        }
    });

    function openRangePanel() {
        rangePanelOpen.value = true;
    }

    function dismissRangePanel() {
        rangePanelOpen.value = false;

        if (!dateRange.value.start || !dateRange.value.end) {
            clearDateRange();
        }
    }

    function clearDateRange() {
        dateRange.value = { start: undefined, end: undefined };
        dateChoice.value = dateFilterOptions[0];
    }

    return {
        dateChoice,
        dateRange,
        rangePanelOpen,
        customRangeLabel,
        dateOptions,
        rangeHint,
        openRangePanel,
        dismissRangePanel,
        clearDateRange,
    };
}
