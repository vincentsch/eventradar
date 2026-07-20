<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

interface EventImage {
    role: 'cover' | 'detail';
    path: string;
    width: number;
    height: number;
    alt: string;
}

interface EventDetail {
    id: string;
    title: string;
    description: string;
    organizer_name: string;
    venue_name: string;
    starts_at: string;
    ends_at: string;
    timezone: string;
    starts_on_local: string;
    locality: string;
    region: string | null;
    country: string;
    country_code: string;
    latitude: number | null;
    longitude: number | null;
    status: string;
    type: string;
    tags: string[];
    minimum_price: string | null;
    currency_code: string | null;
    capacity: number | null;
    images: EventImage[];
}

const props = defineProps<{ event: EventDetail }>();

const formatDateTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: props.event.timezone,
        timeZoneName: 'short',
    }).format(new Date(value));

const location = [
    props.event.venue_name,
    props.event.locality,
    props.event.region,
    props.event.country,
]
    .filter(Boolean)
    .join(', ');
</script>

<template>
    <Head :title="event.title" />

    <article class="mx-auto flex max-w-5xl flex-col gap-8 p-4 sm:p-8">
        <Link href="/events" class="text-sm text-primary hover:underline">
            ← Back to events
        </Link>

        <div class="grid gap-4 sm:grid-cols-2">
            <img
                v-for="image in event.images"
                :key="image.role"
                :src="image.path"
                :alt="image.alt"
                :width="image.width"
                :height="image.height"
                class="aspect-[4/3] h-full w-full rounded-xl object-cover"
            />
        </div>

        <header class="space-y-4">
            <p
                class="text-sm font-medium tracking-wide text-muted-foreground uppercase"
            >
                {{ event.type }} · {{ event.status }}
            </p>
            <h1 class="text-4xl font-semibold tracking-tight">
                {{ event.title }}
            </h1>
            <p class="max-w-3xl text-lg leading-8 text-muted-foreground">
                {{ event.description }}
            </p>
        </header>

        <dl class="grid gap-6 rounded-xl border p-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium">When</dt>
                <dd class="mt-1 text-sm text-muted-foreground">
                    {{ formatDateTime(event.starts_at) }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium">Where</dt>
                <dd class="mt-1 text-sm text-muted-foreground">
                    {{ location }}
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium">Organizer</dt>
                <dd class="mt-1 text-sm text-muted-foreground">
                    {{ event.organizer_name }}
                </dd>
            </div>
            <div v-if="event.minimum_price !== null">
                <dt class="text-sm font-medium">Price from</dt>
                <dd class="mt-1 text-sm text-muted-foreground">
                    {{ event.minimum_price }} {{ event.currency_code }}
                </dd>
            </div>
        </dl>
    </article>
</template>
