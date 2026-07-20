<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Create your account',
        description: 'Save events and receive timely reminders',
    },
});

defineProps<{
    passwordRules: string;
}>();

const labelClasses =
    'text-xs font-black tracking-widest text-stone-500 uppercase';
const fieldClasses =
    'h-12 rounded-xl border-transparent bg-[#f4f0e8] text-sm font-medium text-stone-900 shadow-none placeholder:text-stone-500 focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-stone-900 dark:border-transparent dark:bg-[#f4f0e8] dark:text-stone-900 dark:placeholder:text-stone-500';
const linkClasses =
    'text-blue-700 decoration-blue-700/30 hover:text-blue-900 dark:text-blue-700 dark:decoration-blue-700/30';
</script>

<template>
    <Head title="Create account" />

    <Form
        action="/register"
        method="post"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="name" :class="labelClasses">Name</Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Your name"
                    :class="[fieldClasses, 'px-4']"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email" :class="labelClasses">Email address</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="you@example.com"
                    :class="[fieldClasses, 'px-4']"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password" :class="labelClasses">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Password"
                    :class="[fieldClasses, 'pl-4']"
                />
                <p class="text-xs leading-relaxed text-stone-500">
                    {{ passwordRules }}
                </p>
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation" :class="labelClasses">
                    Confirm password
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password"
                    :class="[fieldClasses, 'pl-4']"
                />
            </div>

            <Button
                type="submit"
                class="mt-2 h-12 w-full rounded-full bg-stone-900 text-sm font-bold text-white shadow-none hover:bg-stone-800 dark:bg-stone-900 dark:text-white dark:hover:bg-stone-800"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>
    </Form>

    <p class="text-center text-sm text-stone-600">
        Already have an account?
        <TextLink href="/login" class="font-bold" :class="linkClasses">
            Log in
        </TextLink>
    </p>
</template>
