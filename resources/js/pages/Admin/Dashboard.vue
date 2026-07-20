<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CircleCheckBig,
    CircleX,
    Database,
    FilePen,
    ListOrdered,
    MailWarning,
    Plus,
    TicketX,
    Users,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    summary: {
        total: number;
        statuses: Record<string, number>;
        types: Record<string, number>;
        active_attendances: number;
        failed_deliveries: number;
    };
}>();

// Every catalogue number links to the event list filtered to exactly the
// rows it counts.
const catalogueCards = [
    {
        label: 'Total events',
        value: props.summary.total,
        icon: Database,
        href: '/admin/events',
    },
    {
        label: 'Published',
        value: props.summary.statuses.published ?? 0,
        icon: CircleCheckBig,
        href: '/admin/events?status=published',
    },
    {
        label: 'Drafts',
        value: props.summary.statuses.draft ?? 0,
        icon: FilePen,
        href: '/admin/events?status=draft',
    },
    {
        label: 'Sold out',
        value: props.summary.statuses.sold_out ?? 0,
        icon: TicketX,
        href: '/admin/events?status=sold_out',
    },
    {
        label: 'Cancelled',
        value: props.summary.statuses.cancelled ?? 0,
        icon: CircleX,
        href: '/admin/events?status=cancelled',
    },
];

const typeShare = (count: number) =>
    props.summary.total === 0
        ? 0
        : Math.max((count / props.summary.total) * 100, 0.75);
</script>

<template>
    <Head title="Admin dashboard" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p class="text-sm font-medium text-muted-foreground">Admin</p>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Event catalogue
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Database-backed operational overview of the seeded
                    catalogue.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link href="/admin/events">
                        <ListOrdered class="size-4" /> Browse events
                    </Link>
                </Button>
                <Button as-child>
                    <Link href="/admin/events/create">
                        <Plus class="size-4" /> New event
                    </Link>
                </Button>
            </div>
        </header>

        <section class="space-y-3">
            <h2
                class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
            >
                Catalogue
            </h2>
            <div
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
            >
                <Link
                    v-for="card in catalogueCards"
                    :key="card.label"
                    :href="card.href"
                    class="group rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <Card
                        class="h-full gap-3 transition-colors group-hover:border-foreground/40"
                    >
                        <CardHeader
                            class="flex-row items-center justify-between space-y-0 pb-0"
                        >
                            <CardTitle
                                class="text-sm font-medium text-muted-foreground"
                            >
                                {{ card.label }}
                            </CardTitle>
                            <component
                                :is="card.icon"
                                class="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                        </CardHeader>
                        <CardContent
                            class="flex items-end justify-between gap-2"
                        >
                            <p class="text-3xl font-semibold tabular-nums">
                                {{ card.value.toLocaleString() }}
                            </p>
                            <ArrowUpRight
                                class="mb-1 size-4 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
                                aria-hidden="true"
                            />
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <Card class="xl:col-span-2">
                <CardHeader>
                    <CardTitle>Events by type</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-x-8 gap-y-1 sm:grid-cols-2">
                    <Link
                        v-for="(count, type) in summary.types"
                        :key="type"
                        :href="`/admin/events?type=${type}`"
                        class="group -mx-2 rounded-lg px-2 py-2.5 transition-colors hover:bg-muted/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span
                            class="flex items-center justify-between gap-4 text-sm"
                        >
                            <span class="font-medium capitalize">
                                {{ type }}
                            </span>
                            <span
                                class="flex items-center gap-1.5 text-muted-foreground tabular-nums"
                            >
                                {{ count.toLocaleString() }}
                                <ArrowUpRight
                                    class="size-3.5 opacity-0 transition-opacity group-hover:opacity-100"
                                    aria-hidden="true"
                                />
                            </span>
                        </span>
                        <svg
                            class="mt-2 h-1.5 w-full"
                            viewBox="0 0 100 4"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                        >
                            <rect
                                width="100"
                                height="4"
                                rx="2"
                                class="fill-muted"
                            />
                            <rect
                                :width="typeShare(count)"
                                height="4"
                                rx="2"
                                class="fill-foreground/80"
                            />
                        </svg>
                    </Link>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-6">
                <Card class="flex-1">
                    <CardHeader
                        class="flex-row items-center justify-between space-y-0 pb-0"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Active attendance
                        </CardTitle>
                        <Users
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-semibold tabular-nums">
                            {{ summary.active_attendances.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Going and interested registrations that are not
                            cancelled.
                        </p>
                    </CardContent>
                </Card>
                <Card class="flex-1">
                    <CardHeader
                        class="flex-row items-center justify-between space-y-0 pb-0"
                    >
                        <CardTitle
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Failed emails
                        </CardTitle>
                        <MailWarning
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-3xl font-semibold tabular-nums"
                            :class="
                                summary.failed_deliveries > 0
                                    ? 'text-destructive'
                                    : ''
                            "
                        >
                            {{ summary.failed_deliveries.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Confirmation or reminder deliveries marked failed.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </section>
    </div>
</template>
