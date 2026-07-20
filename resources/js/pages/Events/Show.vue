<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { BellRing, Check, Users } from '@lucide/vue';
import type { Auth } from '@/types';

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

interface AttendanceProps {
    viewer_intent: 'interested' | 'going' | null;
    counts: {
        going: number;
        interested: number;
        total: number;
    };
    attendees: Array<{
        name: string;
        intent: 'interested' | 'going';
    }>;
}

const props = defineProps<{
    event: EventDetail;
    attendance: AttendanceProps;
}>();

const page = usePage<{ auth: Auth }>();

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
        <Link href="/" class="text-sm text-primary hover:underline">
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

        <dl
            class="grid gap-6 rounded-2xl border border-stone-900/10 bg-white/70 p-6 sm:grid-cols-2"
        >
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

        <section
            class="grid gap-8 rounded-3xl bg-stone-900 p-6 text-white sm:p-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.75fr)]"
        >
            <div>
                <p
                    class="text-xs font-black tracking-widest text-orange-400 uppercase"
                >
                    Keep this one close
                </p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight">
                    Interested or going?
                </h2>
                <p class="mt-3 max-w-xl text-sm leading-relaxed text-stone-300">
                    Join the attendee list and we will email your confirmation,
                    then remind you three days and 24 hours before the event.
                </p>

                <div
                    v-if="page.props.auth.user"
                    class="mt-6 flex flex-wrap gap-3"
                >
                    <Form
                        :action="`/events/${event.id}/attendance`"
                        method="put"
                    >
                        <input type="hidden" name="intent" value="interested" />
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-bold ring-1 ring-white/30 transition hover:bg-white/10"
                            :class="
                                attendance.viewer_intent === 'interested'
                                    ? 'bg-white text-stone-900 hover:bg-stone-100'
                                    : ''
                            "
                        >
                            <Check
                                v-if="attendance.viewer_intent === 'interested'"
                                class="size-4"
                            />
                            Interested
                        </button>
                    </Form>
                    <Form
                        :action="`/events/${event.id}/attendance`"
                        method="put"
                    >
                        <input type="hidden" name="intent" value="going" />
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-bold ring-1 ring-white/30 transition hover:bg-white/10"
                            :class="
                                attendance.viewer_intent === 'going'
                                    ? 'bg-lime-300 text-stone-900 ring-0 hover:bg-lime-200'
                                    : ''
                            "
                        >
                            <Check
                                v-if="attendance.viewer_intent === 'going'"
                                class="size-4"
                            />
                            Going
                        </button>
                    </Form>
                    <Form
                        v-if="attendance.viewer_intent"
                        :action="`/events/${event.id}/attendance`"
                        method="delete"
                    >
                        <button
                            type="submit"
                            class="rounded-full px-4 py-3 text-sm font-bold text-stone-300 hover:text-white"
                        >
                            Leave list
                        </button>
                    </Form>
                </div>

                <Link
                    v-else
                    :href="`/events/${event.id}/attendance`"
                    class="mt-6 inline-flex items-center gap-2 rounded-full bg-lime-300 px-5 py-3 text-sm font-extrabold text-stone-900 hover:bg-lime-200"
                >
                    <BellRing class="size-4" />
                    Log in to join the list
                </Link>
            </div>

            <div class="rounded-2xl bg-white/8 p-5 ring-1 ring-white/10">
                <div class="flex items-center justify-between gap-4">
                    <p class="flex items-center gap-2 font-bold">
                        <Users class="size-4" /> Attendee list
                    </p>
                    <span class="text-sm text-stone-300">
                        {{ attendance.counts.total }} total
                    </span>
                </div>
                <p class="mt-2 text-xs text-stone-400">
                    {{ attendance.counts.going }} going ·
                    {{ attendance.counts.interested }} interested
                </p>
                <ul v-if="attendance.attendees.length" class="mt-4 space-y-2">
                    <li
                        v-for="attendee in attendance.attendees"
                        :key="`${attendee.name}-${attendee.intent}`"
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span>{{ attendee.name }}</span>
                        <span class="text-xs text-stone-400">{{
                            attendee.intent
                        }}</span>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-stone-400">
                    Be the first person on the list.
                </p>
            </div>
        </section>
    </article>
</template>
