<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import ClientProfileController from '@/actions/App/Http/Controllers/Catalog/ClientProfileController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';

defineOptions({
    layout: {
        title: 'Completa tus datos',
        description:
            'Necesitamos tu CI y tu teléfono para registrar tus pedidos y contactarte.',
    },
});

defineProps<{
    idCard?: string | null;
    phone?: string | null;
}>();
</script>

<template>
    <Head title="Completa tus datos" />

    <Form
        v-bind="ClientProfileController.update.form()"
        v-slot="{ errors, processing }"
        class="space-y-6"
    >
        <div class="grid gap-2">
            <Label for="id_card">Carnet de identidad (CI)</Label>
            <Input
                id="id_card"
                name="id_card"
                :default-value="idCard ?? ''"
                required
                autofocus
                maxlength="30"
                autocomplete="off"
                placeholder="Ej.: 1234567 LP"
            />
            <InputError :message="errors.id_card" />
        </div>

        <div class="grid gap-2">
            <Label for="phone">Teléfono / WhatsApp</Label>
            <Input
                id="phone"
                name="phone"
                type="tel"
                inputmode="tel"
                :default-value="phone ?? ''"
                required
                maxlength="30"
                autocomplete="tel"
                placeholder="Ej.: 70000000"
            />
            <InputError :message="errors.phone" />
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="complete-profile-button"
        >
            <Spinner v-if="processing" />
            Guardar y continuar
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Cerrar sesión
        </TextLink>
    </Form>
</template>
