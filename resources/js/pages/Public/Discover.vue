<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import EventCard from '@/components/public/EventCard.vue';
import EventDetailModal from '@/components/public/EventDetailModal.vue';
import EventFilterBar from '@/components/public/EventFilterBar.vue';
import ViewNavigation from '@/components/public/ViewNavigation.vue';
import { publicEventFixtures } from '@/fixtures/public-events';
import type { PublicEventVisualFixture } from '@/types/public-events';

const events = publicEventFixtures;

const detailEvent = ref<PublicEventVisualFixture | null>(null);

const cityCount = computed(
    () => new Set(events.map((event) => event.locationLabel)).size,
);
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

        <EventFilterBar class="mt-10" />

        <div
            class="mt-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-3"
        >
            <p class="text-sm text-stone-600">
                <strong class="font-bold text-stone-900">
                    {{ events.length }} events
                </strong>
                in {{ cityCount }} cities worldwide
                <span class="text-stone-500">
                    · times shown are each event's own
                </span>
            </p>
            <ViewNavigation active="grid" />
        </div>

        <ul class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <li v-for="(event, index) in events" :key="event.id">
                <EventCard
                    :event="event"
                    :image-loading="index < 3 ? 'eager' : 'lazy'"
                    @open="detailEvent = event"
                />
            </li>
        </ul>

        <div class="mt-12 flex justify-center">
            <button
                type="button"
                class="inline-flex h-12 items-center gap-2 rounded-full bg-stone-900 px-7 text-sm font-bold text-white shadow-lg shadow-stone-900/20 transition duration-200 hover:bg-stone-800 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none motion-safe:hover:-translate-y-0.5"
            >
                Load more events
                <ArrowDown class="size-4" aria-hidden="true" />
            </button>
        </div>

        <EventDetailModal :event="detailEvent" @close="detailEvent = null" />
    </section>
</template>
