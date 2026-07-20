<script setup lang="ts">
import { Head, InfiniteScroll, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, LoaderCircle, SearchX } from '@lucide/vue';
import { computed, ref } from 'vue';
import EventCard from '@/components/public/EventCard.vue';
import EventDetailModal from '@/components/public/EventDetailModal.vue';
import EventFilterBar from '@/components/public/EventFilterBar.vue';
import ViewNavigation from '@/components/public/ViewNavigation.vue';
import type {
    PublicEvent,
    PublicEventCollection,
    PublicEventFilterOptions,
    PublicEventQuery,
} from '@/types/public-events';

interface DiscoveryState {
    mode: 'feed' | 'search';
    status: 'ready' | 'unavailable';
    providerCount: number | null;
    totalCount: number;
    totalCountIsCapped: boolean;
    hydratedCount: number | null;
    processingTimeMs: number | null;
}

const props = defineProps<{
    events: PublicEventCollection;
    query: PublicEventQuery;
    discovery: DiscoveryState;
    filters: PublicEventFilterOptions;
}>();
const page = usePage();
const detailEvent = ref<PublicEvent | null>(null);
const filterBar = ref<{ resetFilters: () => void } | null>(null);
const filtering = ref(false);
let latestFilterVisit = 0;
const eventRows = computed(() => props.events.data);
const currentPath = computed(() =>
    page.url.startsWith('/events-visual-1') ? '/events-visual-1' : '/',
);

const formattedTotalCount = computed(() =>
    new Intl.NumberFormat('en-US').format(props.discovery.totalCount),
);

function applyFilters(parameters: Record<string, string>) {
    const visit = ++latestFilterVisit;

    router.get(currentPath.value, parameters, {
        replace: true,
        preserveState: true,
        reset: ['events'],
        onStart: () => {
            if (visit === latestFilterVisit) {
                filtering.value = true;
            }
        },
        onFinish: () => {
            if (visit === latestFilterVisit) {
                filtering.value = false;
            }
        },
    });
}

function clearFilters() {
    filterBar.value?.resetFilters();
    applyFilters({});
}
</script>

<template>
    <Head title="Discover events" />

    <section
        class="mx-auto w-full max-w-screen-2xl px-4 pt-12 pb-20 sm:px-6 lg:px-8 lg:pt-16"
    >
        <div class="relative">
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-24 left-0 -z-10 h-64 w-64 rounded-full bg-orange-400/15 blur-3xl sm:h-80 sm:w-80"
            ></div>

            <div
                class="grid gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] lg:items-end lg:gap-16"
            >
                <h1
                    class="max-w-3xl text-5xl leading-none font-extrabold tracking-tighter text-stone-900 sm:text-6xl lg:text-7xl"
                >
                    Find something
                    <em class="font-serif font-medium text-blue-700 italic">
                        worth
                    </em>
                    leaving home for.
                </h1>
                <p
                    class="max-w-md text-base leading-relaxed text-stone-600 lg:pb-2"
                >
                    <strong class="font-bold text-stone-900">
                        Concerts, exhibitions, workshops and unexpected local
                        moments.
                    </strong>
                    Browse what's on around the world by place, date and
                    category, with every time in the event's own local timezone.
                </p>
            </div>
        </div>

        <EventFilterBar
            ref="filterBar"
            :query="query"
            :filters="filters"
            :processing="filtering"
            class="mt-10"
            @apply="applyFilters"
            @clear="clearFilters"
        />

        <div
            class="mt-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-3"
        >
            <p class="text-sm text-stone-600">
                <strong class="font-bold text-stone-900">
                    Showing {{ eventRows.length }} of {{ formattedTotalCount
                    }}{{ discovery.totalCountIsCapped ? '+' : '' }}
                    {{ discovery.mode === 'search' ? 'matching ' : '' }}events
                </strong>
                <span class="text-stone-500">
                    · times shown in each event's local time
                </span>
            </p>
            <ViewNavigation active="grid" />
        </div>

        <div
            v-if="discovery.status === 'unavailable'"
            role="status"
            class="mt-8 rounded-2xl border border-orange-700/20 bg-orange-50 p-6 text-center"
        >
            <SearchX
                class="mx-auto size-6 text-orange-700"
                aria-hidden="true"
            />
            <h2 class="mt-3 text-lg font-bold text-stone-900">
                Search is temporarily unavailable
            </h2>
            <p class="mt-1 text-sm text-stone-600">
                Clear the filters to continue browsing the database-backed event
                feed.
            </p>
            <button
                type="button"
                class="mt-4 rounded-full bg-stone-900 px-5 py-2.5 text-sm font-bold text-white"
                @click="clearFilters"
            >
                Browse all events
            </button>
        </div>

        <div
            v-else-if="eventRows.length === 0"
            class="mt-8 rounded-2xl border border-stone-900/10 bg-white/60 p-8 text-center"
        >
            <SearchX class="mx-auto size-6 text-stone-500" aria-hidden="true" />
            <h2 class="mt-3 text-lg font-bold text-stone-900">
                No events found
            </h2>
            <p class="mt-1 text-sm text-stone-600">
                Try a broader place, date range, or category.
            </p>
            <button
                type="button"
                class="mt-4 rounded-full bg-stone-900 px-5 py-2.5 text-sm font-bold text-white"
                @click="clearFilters"
            >
                Clear filters
            </button>
        </div>

        <InfiniteScroll
            v-else
            data="events"
            as="ul"
            manual
            only-next
            :preserve-url="false"
            class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3"
        >
            <li v-for="(event, index) in eventRows" :key="event.id">
                <EventCard
                    :event="event"
                    :image-loading="index < 3 ? 'eager' : 'lazy'"
                    @open="detailEvent = event"
                />
            </li>

            <template #next="{ fetch, loading, hasMore }">
                <div class="mt-12 flex justify-center">
                    <button
                        v-if="hasMore"
                        type="button"
                        :disabled="loading || filtering"
                        class="inline-flex h-12 items-center gap-2 rounded-full bg-stone-900 px-7 text-sm font-bold text-white shadow-lg shadow-stone-900/20 transition duration-200 hover:bg-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-60 motion-safe:hover:-translate-y-0.5"
                        @click="fetch"
                    >
                        <LoaderCircle
                            v-if="loading"
                            class="size-4 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        <ArrowDown v-else class="size-4" aria-hidden="true" />
                        {{ loading ? 'Loading events' : 'Load more events' }}
                    </button>
                    <p v-else class="text-sm font-semibold text-stone-500">
                        You have reached the end of these results.
                    </p>
                </div>
            </template>
        </InfiniteScroll>

        <EventDetailModal :event="detailEvent" @close="detailEvent = null" />
    </section>
</template>
