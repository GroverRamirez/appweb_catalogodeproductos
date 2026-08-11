<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Mail } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Iniciar sesión',
        description: 'Accede al panel de Mi Catálogo.',
        variant: 'split',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <div class="contents">
        <Head title="Iniciar sesión" />

        <div
            v-if="status"
            class="mb-6 rounded-md border border-border bg-accent px-4 py-3 text-center text-sm font-medium text-accent-foreground"
        >
            {{ status }}
        </div>

        <div class="mb-6 grid gap-2.5">
            <a
                href="/login/google"
                class="flex h-11 w-full items-center justify-center gap-2.5 rounded-md border border-border bg-card text-sm font-medium shadow-xs transition-colors hover:bg-accent"
            >
                <svg class="size-4.5" viewBox="0 0 48 48" aria-hidden="true">
                    <path
                        fill="#FFC107"
                        d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l5.7-5.7C34.6 6.1 29.6 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.5z"
                    />
                    <path
                        fill="#FF3D00"
                        d="m6.3 14.7 6.6 4.8C14.6 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l5.7-5.7C34.6 6.1 29.6 4 24 4c-7.5 0-14 4.2-17.3 10.4z"
                    />
                    <path
                        fill="#4CAF50"
                        d="M24 44c5.5 0 10.5-2.1 14.2-5.6l-6.6-5.6C29.6 34.7 27 35.7 24 35.7c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.9 39.8 16.4 44 24 44z"
                    />
                    <path
                        fill="#1976D2"
                        d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4-4 5.3l6.6 5.6C41.9 35.6 44 30.2 44 24c0-1.3-.1-2.7-.4-3.5z"
                    />
                </svg>
                Continuar con Google
            </a>
        </div>

        <PasskeyVerify
            label="Entrar con passkey"
            loading-label="Verificando passkey..."
            separator="O usá tu correo y contraseña"
        />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-5">
                <div class="grid gap-2.5">
                    <Label for="email">Correo electrónico</Label>
                    <div class="relative">
                        <Mail
                            class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="correo@ejemplo.com"
                            class="h-12 bg-card pl-10"
                        />
                    </div>
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2.5">
                    <div class="flex items-center justify-between">
                        <Label for="password">Contraseña</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            ¿Olvidaste tu contraseña?
                        </TextLink>
                    </div>
                    <div class="relative">
                        <Lock
                            class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="Contraseña"
                            class="h-12 bg-card pl-10"
                        />
                    </div>
                    <InputError :message="errors.password" />
                </div>

                <Label
                    for="remember"
                    class="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-muted-foreground"
                >
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    Recordarme en este dispositivo
                </Label>

                <Button
                    type="submit"
                    class="gradient-brand glow-brand mt-1 h-12 w-full font-semibold text-white hover:opacity-95"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Validando...' : 'Iniciar sesión' }}
                </Button>
            </div>
        </Form>

        <TextLink
            :href="home()"
            class="mt-8 inline-flex items-center justify-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
            :tabindex="6"
        >
            <ArrowLeft class="size-4" />
            Volver al catálogo
        </TextLink>
    </div>
</template>
