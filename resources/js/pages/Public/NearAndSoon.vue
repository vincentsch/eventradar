<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { publicEventFixtures } from '@/fixtures/public-events';

const events = publicEventFixtures;
</script>

<template>
    <Head title="Near and soon" />

    <section class="grid min-h-[calc(100vh-4rem)] lg:grid-cols-[24rem_1fr]">
        <aside class="border-b p-4 sm:p-6 lg:border-r lg:border-b-0">
            <p class="text-sm font-medium text-muted-foreground">
                Public design surface
            </p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                Near &amp; soon
            </h1>
            <p class="mt-3 text-sm text-muted-foreground">
                Design the chronological agenda and its selected state here.
            </p>

            <div class="mt-6 space-y-3">
                <article
                    v-for="event in events"
                    :key="event.id"
                    class="grid grid-cols-[4.5rem_1fr] gap-3 rounded-xl border bg-card p-3"
                >
                    <img
                        :src="event.image.src"
                        :alt="event.image.alt"
                        class="aspect-square rounded-lg object-cover"
                    />
                    <div class="min-w-0">
                        <p class="text-xs text-muted-foreground">
                            {{ event.dateLabel }} · {{ event.timeLabel }}
                        </p>
                        <h2 class="truncate font-semibold">
                            {{ event.title }}
                        </h2>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ event.locationLabel }}
                        </p>
                    </div>
                </article>
            </div>
        </aside>

        <div
            data-map-canvas
            class="relative flex min-h-[32rem] items-center justify-center bg-muted p-6 text-center"
        >
            <div
                class="max-w-sm rounded-xl border bg-background/90 p-6 shadow-sm"
            >
                <h2 class="font-semibold">Mapbox canvas</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    The live map, clusters, pins and selected-event card will
                    mount in this region during backend integration.
                </p>
            </div>
        </div>
    </section>
</template>
