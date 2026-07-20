<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface EventImage {
    role: string;
    path: string;
    width: number;
    height: number;
    alt: string;
}

interface AdminEvent {
    id: string;
    title: string;
    description: string;
    organizer_name: string;
    venue_name: string;
    starts_at: string;
    ends_at: string;
    timezone: string;
    starts_on_local: string;
    location_key: string;
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
    payload_bytes: number;
    created_at: string | null;
    updated_at: string | null;
    images: EventImage[];
}

const props = defineProps<{ event: AdminEvent }>();

const formatDateTime = (value: string) =>
    new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
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
    <Head :title="`Inspect ${event.title}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="space-y-3">
            <Link href="/admin/events" class="text-sm font-medium text-primary hover:underline">
                ← Back to all events
            </Link>
            <div class="flex flex-wrap items-center gap-2">
                <Badge>{{ event.status }}</Badge>
                <Badge variant="outline">{{ event.type }}</Badge>
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ event.title }}</h1>
                <p class="mt-1 font-mono text-xs text-muted-foreground">{{ event.id }}</p>
            </div>
        </header>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader><CardTitle>Normalized event</CardTitle></CardHeader>
                    <CardContent class="space-y-6">
                        <p class="leading-7 text-muted-foreground">{{ event.description }}</p>
                        <dl class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium text-muted-foreground uppercase">Starts</dt>
                                <dd class="mt-1 text-sm">{{ formatDateTime(event.starts_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-muted-foreground uppercase">Ends</dt>
                                <dd class="mt-1 text-sm">{{ formatDateTime(event.ends_at) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-muted-foreground uppercase">Location</dt>
                                <dd class="mt-1 text-sm">{{ location }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-muted-foreground uppercase">Organizer</dt>
                                <dd class="mt-1 text-sm">{{ event.organizer_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-muted-foreground uppercase">Coordinates</dt>
                                <dd class="mt-1 font-mono text-sm">{{ event.latitude ?? '—' }}, {{ event.longitude ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-muted-foreground uppercase">Local key</dt>
                                <dd class="mt-1 font-mono text-sm">{{ event.location_key }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Local image set</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <figure v-for="image in event.images" :key="image.role" class="space-y-2">
                            <img :src="image.path" :alt="image.alt" :width="image.width" :height="image.height" class="aspect-[4/3] w-full rounded-lg object-cover" />
                            <figcaption class="text-xs text-muted-foreground">{{ image.role }} · {{ image.width }}×{{ image.height }}</figcaption>
                        </figure>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader><CardTitle>Catalogue metadata</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="space-y-4 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Local date</dt><dd>{{ event.starts_on_local }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Timezone</dt><dd class="font-mono text-xs">{{ event.timezone }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Price from</dt><dd>{{ event.minimum_price ?? '—' }} {{ event.currency_code ?? '' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Capacity</dt><dd>{{ event.capacity?.toLocaleString() ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-muted-foreground">Raw provenance</dt><dd>{{ event.payload_bytes.toLocaleString() }} bytes</dd></div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Tags</CardTitle></CardHeader>
                    <CardContent class="flex flex-wrap gap-2">
                        <Badge v-for="tag in event.tags" :key="tag" variant="secondary">{{ tag }}</Badge>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
