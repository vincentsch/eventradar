<script setup lang="ts">
import {
    CalendarDays,
    CalendarRange,
    ChevronDown,
    MapPin,
    Search,
    SlidersHorizontal,
    Tag,
} from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { ref } from 'vue';
import EventDateRangePicker from '@/components/public/EventDateRangePicker.vue';
import FilterSelect from '@/components/public/FilterSelect.vue';
import MultiSelectFilter from '@/components/public/MultiSelectFilter.vue';
import { customDateValue } from '@/components/public/publicEventDisplay';
import UpcomingOnlyToggle from '@/components/public/UpcomingOnlyToggle.vue';
import { useAutoApplyingEventFilters } from '@/components/public/useAutoApplyingEventFilters';
import { usePublicEventFilters } from '@/components/public/usePublicEventFilters';
import type {
    PublicEventFilterOptions,
    PublicEventParameters,
    PublicEventQuery,
} from '@/types/public-events';

const props = withDefaults(
    defineProps<{
        query: PublicEventQuery;
        filters: PublicEventFilterOptions;
        processing?: boolean;
    }>(),
    { processing: false },
);
const emit = defineEmits<{
    apply: [parameters: PublicEventParameters];
    clear: [];
}>();
const filtersOpen = ref(false);
const showsTwoMonths = useMediaQuery('(min-width: 640px)');

const {
    searchTerm,
    locationChoices,
    categoryChoices,
    upcomingOnly,
    locationOptions,
    categoryOptions,
    hasFilters,
    parameters,
    reset,
    dateChoice,
    dateRange,
    rangePanelOpen,
    customRangeLabel,
    dateOptions,
    rangeHint,
    openRangePanel,
    dismissRangePanel,
    clearDateRange,
} = usePublicEventFilters(props.query, props.filters, 'discover-date');

function applyFilters() {
    emit('apply', parameters());
}

const { applyNow, resetWithoutApplying } = useAutoApplyingEventFilters({
    searchTerm,
    locationChoices,
    categoryChoices,
    upcomingOnly,
    dateChoice,
    dateRange,
    parameters,
    apply: applyFilters,
});

function resetFilters() {
    resetWithoutApplying(reset);
}

function clearFilters() {
    resetFilters();
    emit('clear');
}

defineExpose({ resetFilters });
</script>

<template>
    <form
        role="search"
        aria-label="Search and filter events"
        class="rounded-2xl border border-stone-900/10 bg-[#fffdf8] p-2 shadow-lg shadow-stone-900/5"
        :aria-busy="processing"
        @submit.prevent="applyNow"
    >
        <p role="status" aria-live="polite" class="sr-only">
            {{ processing ? 'Updating results' : '' }}
        </p>
        <div
            class="grid gap-2 sm:grid-cols-3 lg:grid-cols-[minmax(0,1.6fr)_repeat(3,minmax(0,1fr))]"
        >
            <div class="flex gap-2 sm:col-span-3 lg:col-span-1">
                <div class="relative min-w-0 flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-stone-500"
                        aria-hidden="true"
                    />
                    <label for="discover-search" class="sr-only">
                        Search events
                    </label>
                    <input
                        id="discover-search"
                        v-model="searchTerm"
                        type="search"
                        placeholder="Search events, artists or venues"
                        class="h-12 w-full rounded-xl bg-[#f4f0e8] pr-4 pl-10 text-sm font-medium text-stone-900 placeholder:text-stone-500 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                    />
                </div>
                <button
                    type="button"
                    :aria-expanded="filtersOpen"
                    class="inline-flex h-12 shrink-0 items-center gap-2 rounded-xl bg-[#f4f0e8] px-4 text-sm font-bold text-stone-800 transition-colors hover:text-stone-950 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none sm:hidden"
                    @click="filtersOpen = !filtersOpen"
                >
                    <SlidersHorizontal class="size-4" aria-hidden="true" />
                    Filters
                    <ChevronDown
                        class="size-4 text-stone-500 transition-transform motion-safe:duration-200"
                        :class="filtersOpen ? 'rotate-180' : ''"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <FilterSelect
                id="discover-date"
                v-model="dateChoice"
                label="Date"
                :icon="CalendarDays"
                :options="dateOptions"
                :class="filtersOpen ? '' : 'hidden sm:block'"
            />
            <MultiSelectFilter
                id="discover-location"
                v-model="locationChoices"
                label="Locations"
                empty-label="Anywhere"
                :icon="MapPin"
                :options="locationOptions"
                :class="filtersOpen ? '' : 'hidden sm:block'"
            />
            <MultiSelectFilter
                id="discover-category"
                v-model="categoryChoices"
                label="Categories"
                empty-label="All categories"
                :icon="Tag"
                :options="categoryOptions"
                :class="filtersOpen ? '' : 'hidden sm:block'"
            />
        </div>

        <div
            v-if="rangePanelOpen"
            role="group"
            aria-label="Custom date range"
            class="mt-2 rounded-xl bg-[#f4f0e8]/70 p-3 sm:p-4"
            @keydown.escape="dismissRangePanel"
        >
            <EventDateRangePicker
                v-model="dateRange"
                :number-of-months="showsTwoMonths ? 2 : 1"
                class="mx-auto w-fit"
            />
            <div
                class="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-stone-900/10 pt-3"
            >
                <p class="text-xs font-semibold text-stone-600">
                    {{ rangeHint }}
                </p>
                <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-full px-3.5 text-xs font-bold text-stone-600 transition-colors hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                    @click="clearDateRange"
                >
                    Clear
                </button>
            </div>
        </div>

        <div
            v-else-if="dateChoice === customDateValue"
            class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 px-1.5 pb-1 text-xs text-stone-600"
        >
            <CalendarRange class="size-3.5 text-stone-500" aria-hidden="true" />
            <span class="font-bold text-stone-900">{{ customRangeLabel }}</span>
            <button
                type="button"
                class="rounded-full font-bold text-blue-700 underline-offset-2 transition-colors hover:text-blue-900 hover:underline focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                @click="openRangePanel"
            >
                Edit dates
            </button>
            <button
                type="button"
                class="rounded-full font-bold text-stone-500 underline-offset-2 transition-colors hover:text-stone-900 hover:underline focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                @click="clearDateRange"
            >
                Clear
            </button>
        </div>

        <div
            class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-stone-900/10 px-1 pt-2"
        >
            <p v-if="processing" class="text-xs font-semibold text-stone-500">
                Updating results…
            </p>
            <div class="ml-auto flex flex-wrap items-center justify-end gap-1">
                <UpcomingOnlyToggle v-model="upcomingOnly" />
                <button
                    v-if="hasFilters"
                    type="button"
                    class="inline-flex h-10 items-center rounded-full px-4 text-xs font-bold text-stone-600 transition-colors hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                    @click="clearFilters"
                >
                    Clear filters
                </button>
            </div>
        </div>
    </form>
</template>
