<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ExternalLink,
    MapPin,
    Pencil,
    Trash2,
    Users,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    formatted_address: string | null;
    address_line_1: string | null;
    starts_at: string;
    ends_at: string;
    timezone: string;
    starts_on_local: string;
    location_key: string;
    locality: string;
    region: string | null;
    postal_code: string | null;
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

const props = defineProps<{
    event: AdminEvent;
    attendance: {
        going: number;
        interested: number;
        total: number;
    };
}>();

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

function confirmDraftDeletion(clickEvent: MouseEvent) {
    if (!window.confirm('Permanently delete this draft event?')) {
        clickEvent.preventDefault();
    }
}
</script>

<template>
    <Head :title="`Inspect ${event.title}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="space-y-4">
            <Link
                href="/admin/events"
                class="inline-flex items-center gap-1.5 rounded-sm text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Back to all events
            </Link>
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge class="capitalize">
                            {{ event.status.replace('_', ' ') }}
                        </Badge>
                        <Badge variant="outline" class="capitalize">
                            {{ event.type }}
                        </Badge>
                    </div>
                    <h1
                        class="mt-2 text-2xl font-semibold tracking-tight text-balance"
                    >
                        {{ event.title }}
                    </h1>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <CalendarDays class="size-4" aria-hidden="true" />
                            {{ formatDateTime(event.starts_at) }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <MapPin class="size-4" aria-hidden="true" />
                            {{ event.locality }}, {{ event.country }}
                        </span>
                    </div>
                    <p class="mt-2 font-mono text-xs text-muted-foreground">
                        {{ event.id }}
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <Button as-child variant="outline">
                        <Link :href="`/events/${event.id}`">
                            <ExternalLink class="size-4" /> Public page
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="`/admin/events/${event.id}/edit`">
                            <Pencil class="size-4" /> Edit event
                        </Link>
                    </Button>
                </div>
            </div>
        </header>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]">
            <div class="space-y-6">
                <Card>
                    <CardHeader
                        ><CardTitle>Normalized event</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-6">
                        <p class="leading-7 text-muted-foreground">
                            {{ event.description }}
                        </p>
                        <dl class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt
                                    class="text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Starts
                                </dt>
                                <dd class="mt-1 text-sm">
                                    {{ formatDateTime(event.starts_at) }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Ends
                                </dt>
                                <dd class="mt-1 text-sm">
                                    {{ formatDateTime(event.ends_at) }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Location
                                </dt>
                                <dd class="mt-1 text-sm">{{ location }}</dd>
                                <dd
                                    v-if="event.formatted_address"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ event.formatted_address }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Organizer
                                </dt>
                                <dd class="mt-1 text-sm">
                                    {{ event.organizer_name }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Coordinates
                                </dt>
                                <dd class="mt-1 font-mono text-sm">
                                    {{ event.latitude ?? 'Not set' }},
                                    {{ event.longitude ?? 'Not set' }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-medium text-muted-foreground uppercase"
                                >
                                    Local key
                                </dt>
                                <dd class="mt-1 font-mono text-sm">
                                    {{ event.location_key }}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        ><CardTitle>Local image set</CardTitle></CardHeader
                    >
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <figure
                            v-for="image in event.images"
                            :key="image.path"
                            class="space-y-2"
                        >
                            <img
                                :src="image.path"
                                :alt="image.alt"
                                :width="image.width"
                                :height="image.height"
                                class="aspect-[4/3] w-full rounded-lg object-cover"
                            />
                            <figcaption class="text-xs text-muted-foreground">
                                {{ image.role }} · {{ image.width }}×{{
                                    image.height
                                }}
                            </figcaption>
                        </figure>
                    </CardContent>
                </Card>

                <Card
                    v-if="event.status === 'draft' && attendance.total === 0"
                    class="border-destructive/40"
                >
                    <CardHeader><CardTitle>Danger zone</CardTitle></CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-sm text-muted-foreground">
                            This draft has no attendance and can be removed
                            permanently.
                        </p>
                        <Form
                            :action="`/admin/events/${event.id}`"
                            method="delete"
                            #default="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="destructive"
                                :disabled="processing"
                                @click="confirmDraftDeletion"
                            >
                                <Trash2 class="size-4" /> Delete draft
                            </Button>
                        </Form>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader
                        ><CardTitle>Catalogue metadata</CardTitle></CardHeader
                    >
                    <CardContent>
                        <dl class="space-y-4 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">
                                    Local date
                                </dt>
                                <dd>{{ event.starts_on_local }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Timezone</dt>
                                <dd class="font-mono text-xs">
                                    {{ event.timezone }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">
                                    Price from
                                </dt>
                                <dd>
                                    {{ event.minimum_price ?? 'Not set' }}
                                    {{ event.currency_code ?? '' }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">Capacity</dt>
                                <dd>
                                    {{
                                        event.capacity?.toLocaleString() ??
                                        'Not set'
                                    }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">
                                    Raw provenance
                                </dt>
                                <dd>
                                    {{ event.payload_bytes.toLocaleString() }}
                                    bytes
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Attendance</CardTitle></CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-lg bg-muted p-3">
                                <p class="text-2xl font-semibold tabular-nums">
                                    {{ attendance.total.toLocaleString() }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Total
                                </p>
                            </div>
                            <div class="rounded-lg bg-muted p-3">
                                <p class="text-2xl font-semibold tabular-nums">
                                    {{ attendance.going.toLocaleString() }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Going
                                </p>
                            </div>
                            <div class="rounded-lg bg-muted p-3">
                                <p class="text-2xl font-semibold tabular-nums">
                                    {{ attendance.interested.toLocaleString() }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Interested
                                </p>
                            </div>
                        </div>
                        <Button as-child variant="outline" class="w-full">
                            <Link :href="`/admin/events/${event.id}/attendees`">
                                <Users class="size-4" /> View attendee list
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Tags</CardTitle></CardHeader>
                    <CardContent class="flex flex-wrap gap-2">
                        <Badge
                            v-for="tag in event.tags"
                            :key="tag"
                            variant="secondary"
                            >{{ tag }}</Badge
                        >
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
