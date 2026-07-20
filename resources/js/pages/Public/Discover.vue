<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { publicEventFixtures } from '@/fixtures/public-events';

const events = publicEventFixtures;
</script>

<template>
    <Head title="Discover events" />

    <section class="mx-auto max-w-screen-2xl px-4 py-10 sm:px-6 lg:px-8">
        <header class="max-w-3xl">
            <p class="text-sm font-medium text-muted-foreground">
                Public design surface
            </p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-6xl">
                Discover events
            </h1>
            <p class="mt-4 text-base text-muted-foreground sm:text-lg">
                This is the real Visual 1 component. Its fixture data will be
                replaced by the shared MySQL and Meilisearch result contract.
            </p>
        </header>

        <div class="mt-8 rounded-xl border bg-card p-4">
            <p class="text-sm text-muted-foreground">
                Search, date, location and category controls belong here.
            </p>
        </div>

        <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="event in events"
                :key="event.id"
                class="overflow-hidden rounded-xl border bg-card"
            >
                <img
                    :src="event.image.src"
                    :alt="event.image.alt"
                    class="aspect-[4/3] w-full object-cover"
                />
                <div class="space-y-3 p-5">
                    <p class="text-xs font-medium tracking-wide uppercase">
                        {{ event.category }} · {{ event.dateLabel }} ·
                        {{ event.timeLabel }} {{ event.timezoneLabel }}
                    </p>
                    <h2 class="text-xl font-semibold">{{ event.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ event.description }}
                    </p>
                    <p class="text-sm">
                        {{ event.venue }} · {{ event.locationLabel }}
                    </p>
                </div>
            </article>
        </div>

        <div class="mt-8 flex justify-center">
            <button
                type="button"
                class="rounded-md border bg-background px-5 py-3 text-sm font-medium"
            >
                Load more
            </button>
        </div>
    </section>
</template>
