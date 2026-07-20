<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { reactive } from 'vue';
import IndexPagination from '@/components/admin/IndexPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface Attendee {
    id: string;
    name: string;
    email: string;
    intent: string;
    cancelled_at: string | null;
    created_at: string;
    updated_at: string;
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

const props = defineProps<{
    event: { id: string; title: string };
    attendees: Pagination<Attendee>;
    filters: {
        q: string | null;
        intent: string | null;
        state: string | null;
    };
    options: { intents: string[] };
}>();

const form = reactive({
    q: props.filters.q ?? '',
    intent: props.filters.intent ?? '',
    state: props.filters.state ?? '',
});

function applyFilters() {
    router.get(
        `/admin/events/${props.event.id}/attendees`,
        Object.fromEntries(
            Object.entries(form).filter(([, value]) => value !== ''),
        ),
        { preserveState: true, replace: true },
    );
}

function clearFilters() {
    Object.assign(form, { q: '', intent: '', state: '' });
    router.get(
        `/admin/events/${props.event.id}/attendees`,
        {},
        { replace: true },
    );
}
</script>

<template>
    <Head :title="`Attendees for ${event.title}`" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <header>
            <Link
                :href="`/admin/events/${event.id}`"
                class="text-sm font-medium text-primary hover:underline"
            >
                Back to event
            </Link>
            <h1 class="mt-3 text-2xl font-semibold tracking-tight">
                Attendees
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ event.title }}</p>
        </header>

        <form
            class="grid gap-3 rounded-xl border bg-card p-4 lg:grid-cols-5"
            @submit.prevent="applyFilters"
        >
            <label class="space-y-1.5 lg:col-span-2">
                <span class="text-xs font-medium text-muted-foreground">
                    Name or email prefix
                </span>
                <Input
                    v-model="form.q"
                    name="q"
                    placeholder="Find an attendee"
                />
            </label>
            <label class="space-y-1.5">
                <span class="text-xs font-medium text-muted-foreground"
                    >Intent</span
                >
                <select
                    v-model="form.intent"
                    name="intent"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">All intents</option>
                    <option
                        v-for="intent in options.intents"
                        :key="intent"
                        :value="intent"
                    >
                        {{ intent }}
                    </option>
                </select>
            </label>
            <label class="space-y-1.5">
                <span class="text-xs font-medium text-muted-foreground"
                    >State</span
                >
                <select
                    v-model="form.state"
                    name="state"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">All states</option>
                    <option value="active">Active</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </label>
            <div class="flex items-end gap-2">
                <Button type="button" variant="outline" @click="clearFilters">
                    <X class="size-4" /> Clear
                </Button>
                <Button type="submit"><Search class="size-4" /> Apply</Button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border bg-card">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="border-b bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Person</th>
                            <th class="px-4 py-3 font-medium">Intent</th>
                            <th class="px-4 py-3 font-medium">State</th>
                            <th class="px-4 py-3 font-medium">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="attendee in attendees.data"
                            :key="attendee.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ attendee.name }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ attendee.email }}
                                </p>
                            </td>
                            <td class="px-4 py-3 capitalize">
                                {{ attendee.intent }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        attendee.cancelled_at
                                            ? 'outline'
                                            : 'default'
                                    "
                                >
                                    {{
                                        attendee.cancelled_at
                                            ? 'cancelled'
                                            : 'active'
                                    }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{
                                    new Date(
                                        attendee.created_at,
                                    ).toLocaleString()
                                }}
                            </td>
                        </tr>
                        <tr v-if="attendees.data.length === 0">
                            <td
                                colspan="4"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                No attendees match these filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <IndexPagination
                :page="attendees.current_page"
                :last-page="attendees.last_page"
                :from="attendees.from"
                :to="attendees.to"
                :total="attendees.total"
                :prev-url="attendees.prev_page_url"
                :next-url="attendees.next_page_url"
            />
        </div>
    </div>
</template>
