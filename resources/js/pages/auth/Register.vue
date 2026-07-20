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
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Your name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="you@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Password"
                />
                <p class="text-xs text-muted-foreground">
                    {{ passwordRules }}
                </p>
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password"
                />
            </div>

            <Button type="submit" class="mt-1 w-full" :disabled="processing">
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>
    </Form>

    <p class="text-center text-sm text-muted-foreground">
        Already have an account?
        <TextLink href="/login" class="font-medium">Log in</TextLink>
    </p>
</template>
