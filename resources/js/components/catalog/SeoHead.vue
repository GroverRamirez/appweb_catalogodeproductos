<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { StoreSettings } from '@/lib/catalog';

const props = withDefaults(
    defineProps<{
        /** Passed to Inertia title template: "{title} - {AppName}" */
        title?: string;
        description?: string;
        canonical?: string;
        ogImage?: string | null;
        ogType?: 'website' | 'product';
        noindex?: boolean;
        /** Plain JS object serialized as JSON-LD script tag */
        jsonLd?: Record<string, unknown> | null;
    }>(),
    {
        ogType: 'website',
        noindex: false,
    },
);

const page = usePage<{ store: StoreSettings }>();
const store = computed(() => page.props.store);

/**
 * og:title uses full "Product — Store" format without the
 * " - AppName" suffix that the Inertia title template appends.
 */
const ogTitle = computed(() => {
    const parts = [props.title, store.value?.name].filter(Boolean);
    return parts.join(' — ') || store.value?.name || '';
});

const twitterCard = computed(() =>
    props.ogImage ? 'summary_large_image' : 'summary',
);

const jsonLdString = computed(() =>
    props.jsonLd ? JSON.stringify(props.jsonLd) : null,
);
</script>

<template>
    <!-- title="" → Inertia title callback returns just appName (home page) -->
    <Head :title="title ?? ''">
        <!-- Description -->
        <meta
            v-if="description"
            name="description"
            :content="description"
            head-key="description"
        />

        <!-- Robots -->
        <meta
            name="robots"
            :content="noindex ? 'noindex, nofollow' : 'index, follow'"
            head-key="robots"
        />

        <!-- Canonical -->
        <link
            v-if="canonical"
            rel="canonical"
            :href="canonical"
            head-key="canonical"
        />

        <!-- Open Graph -->
        <meta property="og:title" :content="ogTitle" head-key="og:title" />
        <meta
            v-if="description"
            property="og:description"
            :content="description"
            head-key="og:description"
        />
        <meta property="og:type" :content="ogType" head-key="og:type" />
        <meta
            v-if="canonical"
            property="og:url"
            :content="canonical"
            head-key="og:url"
        />
        <meta
            v-if="ogImage"
            property="og:image"
            :content="ogImage"
            head-key="og:image"
        />
        <meta
            v-if="ogImage"
            property="og:image:width"
            content="1200"
            head-key="og:image:width"
        />
        <meta
            v-if="ogImage"
            property="og:image:height"
            content="630"
            head-key="og:image:height"
        />
        <meta
            v-if="store?.name"
            property="og:site_name"
            :content="store.name"
            head-key="og:site_name"
        />

        <!-- Twitter Card -->
        <meta
            name="twitter:card"
            :content="twitterCard"
            head-key="twitter:card"
        />
        <meta
            name="twitter:title"
            :content="ogTitle"
            head-key="twitter:title"
        />
        <meta
            v-if="description"
            name="twitter:description"
            :content="description"
            head-key="twitter:description"
        />
        <meta
            v-if="ogImage"
            name="twitter:image"
            :content="ogImage"
            head-key="twitter:image"
        />

        <!-- Structured Data (JSON-LD) -->
        <!-- eslint-disable-next-line vue/no-v-text-v-html-on-component -->
        <component
            v-if="jsonLdString"
            is="script"
            type="application/ld+json"
            :innerHTML="jsonLdString"
            head-key="json-ld"
        />
    </Head>
</template>
