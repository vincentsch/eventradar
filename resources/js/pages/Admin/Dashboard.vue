<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, CircleCheckBig, CircleX, Database } from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    summary: {
        total: number;
        statuses: Record<string, number>;
        types: Record<string, number>;
    };
}>();

const cards = [
    {
        label: 'Total events',
        value: props.summary.total,
        icon: Database,
    },
    {
        label: 'Published',
        value: props.summary.statuses.published ?? 0,
        icon: CircleCheckBig,
    },
    {
        label: 'Drafts',
        value: props.summary.statuses.draft ?? 0,
        icon: CalendarDays,
    },
    {
        label: 'Sold out',
        value: props.summary.statuses.sold_out ?? 0,
        icon: CalendarDays,
    },
    {
        label: 'Cancelled',
        value: props.summary.statuses.cancelled ?? 0,
        icon: CircleX,
    },
];
</script>

<template>
    <Head title="Admin dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <header class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-muted-foreground">Admin</p>
                <h1 class="text-2xl font-semibold tracking-tight">Event catalogue</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Database-backed operational overview of the seeded catalogue.
                </p>
            </div>
            <Link href="/admin/events" class="text-sm font-medium text-primary hover:underline">
                Browse all events →
            </Link>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <Card v-for="card in cards" :key="card.label" class="gap-3">
                <CardHeader class="flex-row items-center justify-between space-y-0 pb-0">
                    <CardTitle class="text-sm font-medium text-muted-foreground">
                        {{ card.label }}
                    </CardTitle>
                    <component :is="card.icon" class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-semibold tabular-nums">
                        {{ card.value.toLocaleString() }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <Card>
            <CardHeader>
                <CardTitle>Events by type</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="(count, type) in summary.types"
                    :key="type"
                    class="flex items-center justify-between gap-4 border-b pb-3"
                >
                    <span class="text-sm capitalize">{{ type }}</span>
                    <span class="font-medium tabular-nums">{{ count.toLocaleString() }}</span>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
