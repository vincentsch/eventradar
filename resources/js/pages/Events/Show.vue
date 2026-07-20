<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BellRing,
    CalendarDays,
    Check,
    Expand,
    MapPin,
    Ticket,
    UserRound,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ImageLightbox from '@/components/public/ImageLightbox.vue';
import type { Auth } from '@/types';

interface EventImage {
    role: string;
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
    formatted_address: string | null;
    address_line_1: string | null;
    starts_at: string;
    ends_at: string;
    timezone: string;
    starts_on_local: string;
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

const location = computed(() =>
    props.event.formatted_address
        ? `${props.event.venue_name}, ${props.event.formatted_address}`
        : [
              props.event.venue_name,
              props.event.locality,
              props.event.region,
              props.event.country,
          ]
              .filter(Boolean)
              .join(', '),
);

const typeLabel = computed(
    () => props.event.type.charAt(0).toUpperCase() + props.event.type.slice(1),
);

const lightboxImages = computed(() =>
    props.event.images.map((image) => ({ src: image.path, alt: image.alt })),
);
const lightboxIndex = ref<number | null>(null);

// With an odd number of photos the first one becomes a full-width hero,
// so a single image and any trailing pair both fill the grid cleanly.
const heroSpansRow = computed(() => props.event.images.length % 2 === 1);

const galleryItemClasses = (index: number) =>
    heroSpansRow.value && index === 0
        ? 'aspect-[2/1] sm:col-span-2'
        : 'aspect-[4/3]';
</script>

<template>
    <Head :title="event.title" />

    <article
        class="mx-auto w-full max-w-5xl px-4 pt-6 pb-20 sm:px-6 lg:px-8 lg:pt-8"
    >
        <Link
            href="/"
            class="inline-flex items-center gap-1.5 rounded-full py-1 text-sm font-bold text-stone-600 transition-colors hover:text-stone-900 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Back to events
        </Link>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <button
                v-for="(image, index) in event.images"
                :key="image.path"
                type="button"
                :aria-label="`View photo ${index + 1} full size`"
                class="group relative cursor-zoom-in overflow-hidden rounded-2xl bg-stone-200 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                :class="galleryItemClasses(index)"
                @click="lightboxIndex = index"
            >
                <img
                    :src="image.path"
                    :alt="image.alt"
                    :width="image.width"
                    :height="image.height"
                    :loading="index === 0 ? 'eager' : 'lazy'"
                    decoding="async"
                    class="h-full w-full object-cover"
                />
                <span
                    class="absolute right-3 bottom-3 grid size-9 place-items-center rounded-full bg-stone-950/50 text-white opacity-80 backdrop-blur-sm transition-opacity group-hover:opacity-100"
                    aria-hidden="true"
                >
                    <Expand class="size-4" />
                </span>
            </button>
        </div>

        <header class="mt-8 max-w-3xl">
            <p class="flex flex-wrap items-center gap-2">
                <span
                    class="text-[11px] font-extrabold tracking-widest text-blue-700 uppercase"
                >
                    {{ typeLabel }}
                </span>
                <span
                    v-if="event.status === 'sold_out'"
                    class="rounded-full bg-orange-600/10 px-2 py-0.5 text-[10px] font-black tracking-widest text-orange-700 uppercase"
                >
                    Sold out
                </span>
            </p>
            <h1
                class="mt-2 text-4xl font-extrabold tracking-tight text-stone-900 sm:text-5xl"
            >
                {{ event.title }}
            </h1>
            <p class="mt-4 text-base leading-relaxed text-stone-600 sm:text-lg">
                {{ event.description }}
            </p>
            <ul
                v-if="event.tags.length"
                class="mt-4 flex flex-wrap gap-1.5"
                aria-label="Tags"
            >
                <li
                    v-for="tag in event.tags"
                    :key="tag"
                    class="rounded-full px-2.5 py-1 text-[11px] font-bold text-stone-600 ring-1 ring-stone-900/10"
                >
                    {{ tag }}
                </li>
            </ul>
        </header>

        <dl
            class="mt-8 grid gap-6 rounded-3xl border border-stone-900/10 bg-[#fffdf8] p-6 shadow-sm shadow-stone-900/5 sm:grid-cols-2 sm:p-8"
        >
            <div class="flex gap-3.5">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#f4f0e8]"
                    aria-hidden="true"
                >
                    <CalendarDays class="size-4 text-stone-600" />
                </span>
                <div class="min-w-0">
                    <dt
                        class="text-[11px] font-black tracking-widest text-stone-500 uppercase"
                    >
                        When
                    </dt>
                    <dd
                        class="mt-1 text-sm leading-relaxed font-semibold text-stone-900"
                    >
                        {{ formatDateTime(event.starts_at) }}
                    </dd>
                </div>
            </div>
            <div class="flex gap-3.5">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#f4f0e8]"
                    aria-hidden="true"
                >
                    <MapPin class="size-4 text-stone-600" />
                </span>
                <div class="min-w-0">
                    <dt
                        class="text-[11px] font-black tracking-widest text-stone-500 uppercase"
                    >
                        Where
                    </dt>
                    <dd
                        class="mt-1 text-sm leading-relaxed font-semibold text-stone-900"
                    >
                        {{ location }}
                    </dd>
                </div>
            </div>
            <div class="flex gap-3.5">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#f4f0e8]"
                    aria-hidden="true"
                >
                    <UserRound class="size-4 text-stone-600" />
                </span>
                <div class="min-w-0">
                    <dt
                        class="text-[11px] font-black tracking-widest text-stone-500 uppercase"
                    >
                        Organizer
                    </dt>
                    <dd
                        class="mt-1 text-sm leading-relaxed font-semibold text-stone-900"
                    >
                        {{ event.organizer_name }}
                    </dd>
                </div>
            </div>
            <div v-if="event.minimum_price !== null" class="flex gap-3.5">
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-[#f4f0e8]"
                    aria-hidden="true"
                >
                    <Ticket class="size-4 text-stone-600" />
                </span>
                <div class="min-w-0">
                    <dt
                        class="text-[11px] font-black tracking-widest text-stone-500 uppercase"
                    >
                        Price from
                    </dt>
                    <dd
                        class="mt-1 text-sm leading-relaxed font-semibold text-stone-900"
                    >
                        {{ event.minimum_price }} {{ event.currency_code }}
                    </dd>
                </div>
            </div>
        </dl>

        <section
            class="mt-8 grid gap-8 rounded-3xl bg-stone-900 p-6 text-white sm:p-8 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.75fr)]"
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
                            class="inline-flex cursor-pointer items-center gap-2 rounded-full px-5 py-3 text-sm font-bold ring-1 ring-white/30 transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
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
                            class="inline-flex cursor-pointer items-center gap-2 rounded-full px-5 py-3 text-sm font-bold ring-1 ring-white/30 transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
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
                            class="cursor-pointer rounded-full px-4 py-3 text-sm font-bold text-stone-300 transition-colors hover:text-white focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                        >
                            Leave list
                        </button>
                    </Form>
                </div>

                <Link
                    v-else
                    :href="`/events/${event.id}/attendance`"
                    class="mt-6 inline-flex items-center gap-2 rounded-full bg-lime-300 px-5 py-3 text-sm font-extrabold text-stone-900 transition-colors hover:bg-lime-200 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                >
                    <BellRing class="size-4" aria-hidden="true" />
                    Log in to join the list
                </Link>
            </div>

            <div class="rounded-2xl bg-white/8 p-5 ring-1 ring-white/10">
                <div class="flex items-center justify-between gap-4">
                    <p class="flex items-center gap-2 font-bold">
                        <Users class="size-4" aria-hidden="true" />
                        Attendee list
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
                        <span class="truncate">{{ attendee.name }}</span>
                        <span class="shrink-0 text-xs text-stone-400">
                            {{ attendee.intent }}
                        </span>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-stone-400">
                    Be the first person on the list.
                </p>
            </div>
        </section>

        <ImageLightbox v-model="lightboxIndex" :images="lightboxImages" />
    </article>
</template>
