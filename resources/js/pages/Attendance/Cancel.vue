<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarX2 } from '@lucide/vue';

defineProps<{
    attendance: {
        active: boolean;
        event: {
            title: string;
            starts_at: string;
            timezone: string;
            location: string;
        };
    };
}>();

const page = usePage();
</script>

<template>
    <Head title="Manage attendance" />

    <section class="mx-auto max-w-xl px-4 py-16 sm:px-6">
        <div
            class="rounded-3xl border border-stone-900/10 bg-[#fffdf8] p-8 text-center shadow-sm sm:p-10"
        >
            <CalendarX2 class="mx-auto size-10 text-orange-600" />
            <p
                class="mt-5 text-xs font-black tracking-widest text-orange-700 uppercase"
            >
                Attendance
            </p>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight">
                {{
                    attendance.active
                        ? 'Leave this event list?'
                        : 'Attendance cancelled'
                }}
            </h1>
            <p class="mt-4 text-stone-600">
                {{ attendance.event.title }}<br />
                {{ attendance.event.location }}
            </p>

            <Form
                v-if="attendance.active"
                :action="page.url"
                method="delete"
                v-slot="{ processing }"
                class="mt-8"
            >
                <button
                    type="submit"
                    :disabled="processing"
                    class="rounded-full bg-red-700 px-6 py-3 text-sm font-bold text-white disabled:opacity-50"
                >
                    {{ processing ? 'Cancelling...' : 'Cancel my attendance' }}
                </button>
            </Form>

            <Link
                href="/"
                class="mt-6 inline-block text-sm font-bold text-blue-700 hover:underline"
            >
                Back to EventRadar
            </Link>
        </div>
    </section>
</template>
