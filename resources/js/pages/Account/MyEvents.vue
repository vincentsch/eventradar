<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Trash2 } from '@lucide/vue';

interface EventCard {
    id: string;
    title: string;
    type: string;
    starts_at: string;
    ends_at: string;
    timezone: string;
    venue_name: string;
    formatted_address: string | null;
    locality: string;
    region: string | null;
    country: string;
    cover: {
        path: string;
        width: number;
        height: number;
        alt: string;
    } | null;
}

interface Attendance {
    intent: 'interested' | 'going';
    event: EventCard;
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

defineProps<{
    attendances: Pagination<Attendance>;
}>();

const formatStart = (event: EventCard) =>
    new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: event.timezone,
        timeZoneName: 'short',
    }).format(new Date(event.starts_at));
</script>

<template>
    <Head title="My events" />

    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
        <header class="max-w-2xl">
            <p
                class="text-xs font-black tracking-[0.18em] text-orange-700 uppercase"
            >
                Your calendar
            </p>
            <h1 class="mt-2 text-4xl font-extrabold tracking-tight sm:text-5xl">
                Events worth remembering.
            </h1>
            <p class="mt-4 text-base leading-relaxed text-stone-600">
                Update your status or leave an event list at any time. We send
                confirmation and reminder emails to your account address.
            </p>
        </header>

        <div
            v-if="attendances.data.length === 0"
            class="mt-10 rounded-3xl border border-stone-900/10 bg-white/70 p-10 text-center"
        >
            <CalendarDays class="mx-auto size-9 text-stone-400" />
            <h2 class="mt-4 text-xl font-extrabold">Nothing saved yet</h2>
            <p class="mt-2 text-sm text-stone-600">
                Find an event and choose Interested or Going to add it here.
            </p>
            <Link
                href="/"
                class="mt-6 inline-flex rounded-full bg-stone-900 px-5 py-3 text-sm font-bold text-white"
            >
                Discover events
            </Link>
        </div>

        <ul v-else class="mt-10 grid gap-5 md:grid-cols-2">
            <li
                v-for="attendance in attendances.data"
                :key="attendance.event.id"
                class="overflow-hidden rounded-3xl border border-stone-900/10 bg-[#fffdf8] shadow-sm"
            >
                <div class="grid h-full sm:grid-cols-[11rem_minmax(0,1fr)]">
                    <img
                        v-if="attendance.event.cover"
                        :src="attendance.event.cover.path"
                        :alt="attendance.event.cover.alt"
                        :width="attendance.event.cover.width"
                        :height="attendance.event.cover.height"
                        class="aspect-[16/10] h-full min-h-40 w-full object-cover sm:aspect-auto"
                    />
                    <div
                        v-else
                        class="min-h-40 bg-gradient-to-br from-orange-200 to-blue-200"
                    ></div>

                    <div class="flex min-w-0 flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-[11px] font-black tracking-widest text-blue-700 capitalize uppercase"
                                >
                                    {{ attendance.event.type }}
                                </p>
                                <Link
                                    :href="`/events/${attendance.event.id}`"
                                    class="mt-1 block rounded-sm text-xl leading-tight font-extrabold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    {{ attendance.event.title }}
                                </Link>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-black uppercase"
                                :class="
                                    attendance.intent === 'going'
                                        ? 'bg-lime-200 text-stone-900'
                                        : 'bg-stone-900/10 text-stone-700'
                                "
                            >
                                {{ attendance.intent }}
                            </span>
                        </div>

                        <div class="mt-4 space-y-2 text-sm text-stone-600">
                            <p class="flex gap-2">
                                <CalendarDays class="mt-0.5 size-4 shrink-0" />
                                {{ formatStart(attendance.event) }}
                            </p>
                            <p class="flex gap-2">
                                <MapPin class="mt-0.5 size-4 shrink-0" />
                                {{ attendance.event.venue_name }},
                                {{
                                    attendance.event.formatted_address ??
                                    `${attendance.event.locality}, ${attendance.event.country}`
                                }}
                            </p>
                        </div>

                        <div
                            class="mt-auto flex flex-wrap items-center gap-2 pt-5"
                        >
                            <Form
                                :action="`/events/${attendance.event.id}/attendance`"
                                method="put"
                            >
                                <input
                                    type="hidden"
                                    name="intent"
                                    value="interested"
                                />
                                <button
                                    type="submit"
                                    class="cursor-pointer rounded-full px-3 py-2 text-xs font-bold ring-1 ring-stone-900/15 transition-colors hover:bg-white focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                                    :class="
                                        attendance.intent === 'interested'
                                            ? 'bg-stone-900 text-white hover:bg-stone-800'
                                            : ''
                                    "
                                >
                                    Interested
                                </button>
                            </Form>
                            <Form
                                :action="`/events/${attendance.event.id}/attendance`"
                                method="put"
                            >
                                <input
                                    type="hidden"
                                    name="intent"
                                    value="going"
                                />
                                <button
                                    type="submit"
                                    class="cursor-pointer rounded-full px-3 py-2 text-xs font-bold ring-1 ring-stone-900/15 transition-colors hover:bg-white focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                                    :class="
                                        attendance.intent === 'going'
                                            ? 'bg-lime-300 text-stone-900 ring-lime-300 hover:bg-lime-200'
                                            : ''
                                    "
                                >
                                    Going
                                </button>
                            </Form>
                            <Form
                                :action="`/events/${attendance.event.id}/attendance`"
                                method="delete"
                                class="ml-auto"
                            >
                                <button
                                    type="submit"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-3 py-2 text-xs font-bold text-red-700 transition-colors hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
                                >
                                    <Trash2
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    Leave list
                                </button>
                            </Form>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <nav
            v-if="attendances.last_page > 1"
            class="mt-8 flex items-center justify-between"
            aria-label="My events pages"
        >
            <Link
                v-if="attendances.prev_page_url"
                :href="attendances.prev_page_url"
                class="rounded-full bg-white px-4 py-2 text-sm font-bold ring-1 ring-stone-900/10 transition-colors hover:ring-stone-900/30 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
            >
                Previous
            </Link>
            <span v-else></span>
            <span class="text-sm text-stone-600">
                Page {{ attendances.current_page }} of
                {{ attendances.last_page }}
            </span>
            <Link
                v-if="attendances.next_page_url"
                :href="attendances.next_page_url"
                class="rounded-full bg-white px-4 py-2 text-sm font-bold ring-1 ring-stone-900/10 transition-colors hover:ring-stone-900/30 focus-visible:ring-2 focus-visible:ring-stone-900 focus-visible:outline-none"
            >
                Next
            </Link>
            <span v-else></span>
        </nav>
    </section>
</template>
