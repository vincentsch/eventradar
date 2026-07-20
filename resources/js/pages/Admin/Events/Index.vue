<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { reactive, watch } from 'vue';
import AdminDateRangePicker from '@/components/admin/AdminDateRangePicker.vue';
import IndexPagination from '@/components/admin/IndexPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface EventRow {
    id: string;
    title: string;
    type: string;
    status: string;
    starts_at: string;
    starts_on_local: string;
    timezone: string;
    venue_name: string;
    locality: string;
    region: string | null;
    country: string;
    country_code: string;
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Filters {
    q: string | null;
    status: string | null;
    type: string | null;
    country_code: string | null;
    from: string | null;
    to: string | null;
}

const props = defineProps<{
    events: Pagination<EventRow>;
    filters: Filters;
    options: {
        statuses: string[];
        types: string[];
        countries: Array<{ code: string; name: string }>;
    };
}>();

const form = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    country_code: props.filters.country_code ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

function applyFilters() {
    const query = Object.fromEntries(
        Object.entries(form).filter(([, value]) => value !== ''),
    );

    router.get('/admin/events', query, {
        preserveState: true,
        replace: true,
    });
}

// Filters apply themselves: selects and dates immediately, the search box
// after a short pause while typing.
watch(
    () => [form.status, form.type, form.country_code, form.from, form.to],
    applyFilters,
);
watchDebounced(() => form.q, applyFilters, { debounce: 350 });

function onDateRange(value: { from: string | null; to: string | null }) {
    form.from = value.from ?? '';
    form.to = value.to ?? '';
}

function clearFilters() {
    Object.assign(form, {
        q: '',
        status: '',
        type: '',
        country_code: '',
        from: '',
        to: '',
    });
}

function openEvent(id: string) {
    router.visit(`/admin/events/${id}`);
}

const statusVariant = (status: string) => {
    if (status === 'published') {
        return 'default';
    }

    if (status === 'cancelled') {
        return 'destructive';
    }

    if (status === 'sold_out') {
        return 'secondary';
    }

    return 'outline';
};

const formatStart = (event: EventRow) =>
    new Intl.DateTimeFormat(undefined, {
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
    <Head title="Admin events" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-muted-foreground">Admin</p>
                <h1 class="text-2xl font-semibold tracking-tight">Events</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Browse every event directly from MySQL. 50 rows per page,
                    without infinite scroll.
                </p>
            </div>
            <Button as-child
                ><Link href="/admin/events/create"
                    ><Plus class="size-4" /> Create event</Link
                ></Button
            >
        </header>

        <form
            class="grid gap-3 rounded-xl border bg-card p-4 lg:grid-cols-6"
            @submit.prevent="applyFilters"
        >
            <label class="space-y-1.5 lg:col-span-2">
                <span class="text-xs font-medium text-muted-foreground"
                    >Title prefix or UUID</span
                >
                <Input
                    v-model="form.q"
                    name="q"
                    placeholder="Search catalogue"
                />
            </label>
            <label class="space-y-1.5">
                <span class="text-xs font-medium text-muted-foreground"
                    >Status</span
                >
                <select
                    v-model="form.status"
                    name="status"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">All statuses</option>
                    <option
                        v-for="status in options.statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ status }}
                    </option>
                </select>
            </label>
            <label class="space-y-1.5">
                <span class="text-xs font-medium text-muted-foreground"
                    >Type</span
                >
                <select
                    v-model="form.type"
                    name="type"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">All types</option>
                    <option
                        v-for="type in options.types"
                        :key="type"
                        :value="type"
                    >
                        {{ type }}
                    </option>
                </select>
            </label>
            <label class="space-y-1.5 lg:col-span-2">
                <span class="text-xs font-medium text-muted-foreground"
                    >Country</span
                >
                <select
                    v-model="form.country_code"
                    name="country_code"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">All countries</option>
                    <option
                        v-for="country in options.countries"
                        :key="country.code"
                        :value="country.code"
                    >
                        {{ country.name }} ({{ country.code }})
                    </option>
                </select>
            </label>
            <div class="space-y-1.5 lg:col-span-2">
                <span class="text-xs font-medium text-muted-foreground">
                    Local dates
                </span>
                <AdminDateRangePicker
                    :from="form.from || null"
                    :to="form.to || null"
                    @change="onDateRange"
                />
            </div>
            <div class="flex items-center justify-between gap-3 lg:col-span-4">
                <p class="text-xs text-muted-foreground">
                    Filters apply automatically.
                </p>
                <Button
                    type="button"
                    variant="outline"
                    class="cursor-pointer"
                    @click="clearFilters"
                >
                    <X class="size-4" /> Clear filters
                </Button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="border-b bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Event</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Location</th>
                            <th class="px-4 py-3 font-medium">
                                Starts locally
                            </th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="event in events.data"
                            :key="event.id"
                            class="cursor-pointer border-b transition-colors last:border-0 hover:bg-muted/50"
                            @click="openEvent(event.id)"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ event.title }}</p>
                                <p
                                    class="mt-1 font-mono text-xs text-muted-foreground"
                                >
                                    {{ event.id }}
                                </p>
                            </td>
                            <td class="px-4 py-3 capitalize">
                                {{ event.type }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="statusVariant(event.status)">{{
                                    event.status
                                }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ event.locality }}, {{ event.country_code }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ formatStart(event) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/admin/events/${event.id}`"
                                    class="rounded-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    @click.stop
                                >
                                    Inspect
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="events.data.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                No events match these filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <IndexPagination
                :page="events.current_page"
                :last-page="events.last_page"
                :from="events.from"
                :to="events.to"
                :total="events.total"
                :prev-url="events.prev_page_url"
                :next-url="events.next_page_url"
            />
        </div>
    </div>
</template>
