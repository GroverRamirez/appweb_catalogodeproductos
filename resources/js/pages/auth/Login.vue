<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Iniciar sesión',
        description: 'Accede al panel de Mi Catálogo.',
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
            class="mb-5 rounded-md border border-border bg-accent px-4 py-3 text-center text-sm font-medium text-accent-foreground"
        >
            {{ status }}
        </div>

        <PasskeyVerify
            label="Entrar con passkey"
            loading-label="Verificando passkey..."
            separator="O continúa con tu correo"
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
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="correo@ejemplo.com"
                        class="h-12 bg-background/80"
                    />
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
                    <PasswordInput
                        id="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Contraseña"
                        class="h-12 bg-background/80"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between rounded-[8px]">
                    <Label for="remember" class="flex items-center gap-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Recordarme</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="gradient-brand glow-brand mt-2 h-12 w-full font-semibold text-white hover:opacity-95"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    {{ processing ? 'Validando...' : 'Iniciar sesión' }}
                </Button>
            </div>
        </Form>
    </div>
</template>
