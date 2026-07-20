<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

defineProps<{
    page: number;
    lastPage: number;
    from: number | null;
    to: number | null;
    total: number;
    prevUrl: string | null;
    nextUrl: string | null;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-3 border-t px-4 py-3 sm:grid sm:grid-cols-3 sm:items-center md:px-6"
    >
        <div class="text-center text-sm text-muted-foreground sm:text-left">
            Page {{ page.toLocaleString() }} of {{ lastPage.toLocaleString() }}
        </div>
        <div class="text-center text-sm text-muted-foreground">
            <template v-if="from !== null && to !== null">
                Showing {{ from.toLocaleString() }}–{{ to.toLocaleString() }} of
                {{ total.toLocaleString() }} results
            </template>
            <template v-else>No results</template>
        </div>
        <div class="flex items-center justify-between gap-2 sm:justify-end">
            <Link
                v-if="prevUrl"
                :href="prevUrl"
                preserve-scroll
                :class="
                    cn(
                        buttonVariants({ variant: 'outline', size: 'sm' }),
                        'flex-1 sm:flex-none',
                    )
                "
            >
                Previous
            </Link>
            <span
                v-else
                aria-disabled="true"
                :class="
                    cn(
                        buttonVariants({ variant: 'outline', size: 'sm' }),
                        'flex-1 opacity-50 sm:flex-none',
                    )
                "
            >
                Previous
            </span>
            <Link
                v-if="nextUrl"
                :href="nextUrl"
                preserve-scroll
                :class="
                    cn(
                        buttonVariants({ variant: 'outline', size: 'sm' }),
                        'flex-1 sm:flex-none',
                    )
                "
            >
                Next
            </Link>
            <span
                v-else
                aria-disabled="true"
                :class="
                    cn(
                        buttonVariants({ variant: 'outline', size: 'sm' }),
                        'flex-1 opacity-50 sm:flex-none',
                    )
                "
            >
                Next
            </span>
        </div>
    </div>
</template>
