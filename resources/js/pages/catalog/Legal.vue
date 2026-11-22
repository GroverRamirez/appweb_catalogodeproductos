<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({ layout: PublicLayout });

const props = defineProps<{ page: 'privacidad' | 'terminos' }>();

const inertiaPage = usePage();
const store = computed(() => inertiaPage.props.store as StoreSettings);
const storeName = computed(() => store.value.name || 'la tienda');

const title = computed(() =>
    props.page === 'privacidad' ? 'Política de privacidad' : 'Términos de uso',
);
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto w-full max-w-3xl px-6 py-10 lg:px-8">
        <h1
            class="font-display text-2xl font-extrabold tracking-tight md:text-3xl"
        >
            {{ title }}
        </h1>
        <p class="mt-2 text-sm text-muted-foreground">
            Última actualización: 10 de noviembre de 2026
        </p>

        <div
            v-if="props.page === 'privacidad'"
            class="mt-8 space-y-6 leading-relaxed [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-bold [&_li]:ml-5 [&_li]:list-disc"
        >
            <p>
                En {{ storeName }} cuidamos los datos que nos confías. Esta
                página explica qué datos guardamos, para qué los usamos y cómo
                puedes pedir que los borremos.
            </p>

            <section class="space-y-2">
                <h2>Qué datos guardamos</h2>
                <ul class="space-y-1">
                    <li>
                        Si entras con Google: tu nombre, tu correo y el
                        identificador de tu cuenta de Google. No recibimos tu
                        contraseña ni tus contactos.
                    </li>
                    <li>
                        Los datos que completas en tu cuenta: carnet de
                        identidad (CI), teléfono y, si la escribes, tu
                        dirección.
                    </li>
                    <li>
                        Tus consultas y pedidos: productos, cantidades, montos y
                        la fecha.
                    </li>
                    <li>
                        Datos técnicos mínimos para que el sitio funcione: una
                        cookie de sesión, la dirección IP en los registros de
                        seguridad y los productos que visitas, para saber qué
                        productos interesan más.
                    </li>
                    <li>
                        Tus favoritos y tu carrito se guardan solo en tu
                        navegador.
                    </li>
                </ul>
            </section>

            <section class="space-y-2">
                <h2>Para qué los usamos</h2>
                <ul class="space-y-1">
                    <li>
                        Atender tus consultas y pedidos, y contactarte sobre
                        ellos.
                    </li>
                    <li>Emitir comprobantes de venta con tu nombre y CI.</li>
                    <li>
                        Proteger el sitio contra abusos y accesos indebidos.
                    </li>
                </ul>
                <p>
                    No vendemos ni compartimos tus datos con terceros para
                    publicidad. No enviamos correos promocionales.
                </p>
            </section>

            <section class="space-y-2">
                <h2>Dónde se guardan</h2>
                <p>
                    En un servidor privado contratado por la tienda, con
                    conexión cifrada (HTTPS). Solo el personal autorizado de la
                    tienda puede verlos, cada uno según su rol.
                </p>
            </section>

            <section class="space-y-2">
                <h2>Tus derechos</h2>
                <p>
                    Puedes ver y corregir tus datos desde tu cuenta, y borrar tu
                    cuenta cuando quieras desde
                    <Link
                        href="/settings/profile"
                        class="text-primary underline"
                    >
                        la configuración de tu perfil</Link
                    >. Si prefieres, escríbenos y lo hacemos por ti. Los
                    comprobantes de ventas ya realizadas se conservan por
                    obligación contable.
                </p>
            </section>

            <section class="space-y-2">
                <h2>Contacto</h2>
                <p>
                    Para cualquier consulta sobre tus datos, escríbenos
                    <template v-if="store.email">
                        a
                        <a
                            :href="`mailto:${store.email}`"
                            class="text-primary underline"
                            >{{ store.email }}</a
                        >
                    </template>
                    <template v-if="store.whatsapp">
                        {{ store.email ? 'o' : '' }} por WhatsApp al
                        {{ store.whatsapp }}</template
                    >.
                </p>
            </section>
        </div>

        <div
            v-else
            class="mt-8 space-y-6 leading-relaxed [&_h2]:font-display [&_h2]:text-lg [&_h2]:font-bold [&_li]:ml-5 [&_li]:list-disc"
        >
            <p>
                Al usar el sitio de {{ storeName }} aceptas estas condiciones.
            </p>

            <section class="space-y-2">
                <h2>Versión de prueba</h2>
                <p>
                    El sitio está en etapa de prueba. Puede tener errores o
                    cambios sin aviso. Tus sugerencias nos ayudan a mejorarlo.
                </p>
            </section>

            <section class="space-y-2">
                <h2>Precios y stock</h2>
                <ul class="space-y-1">
                    <li>
                        Los precios se muestran en bolivianos según el tipo de
                        cambio del día y pueden cambiar sin aviso.
                    </li>
                    <li>
                        Una consulta o pedido desde el sitio no es una compra
                        confirmada: la tienda te contacta para confirmar precio,
                        stock y forma de pago.
                    </li>
                    <li>El sitio no cobra pagos en línea.</li>
                </ul>
            </section>

            <section class="space-y-2">
                <h2>Tu cuenta</h2>
                <p>
                    Eres responsable de los datos que ingresas y de usar el
                    sitio de buena fe. Podemos desactivar cuentas usadas para
                    abusos o datos falsos.
                </p>
            </section>

            <section class="space-y-2">
                <h2>Privacidad</h2>
                <p>
                    El uso de tus datos se explica en la
                    <Link href="/privacidad" class="text-primary underline"
                        >política de privacidad</Link
                    >.
                </p>
            </section>
        </div>
    </div>
</template>
