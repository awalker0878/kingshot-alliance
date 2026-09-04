<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import RoomBanner from '@/components/game/RoomBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  user: { name: string; email: string };
  alliance: { id: string; name: string; slug: string; language: string; timezone: string };
  locales: string[];
}>();

const { t } = useLocale();
const form = useForm({
  name: props.alliance.name,
  slug: props.alliance.slug,
  language: props.alliance.language,
  timezone: props.alliance.timezone,
});

function submit(): void {
  form.patch('/alliance/settings', { preserveScroll: true });
}
</script>

<template>
  <Head :title="`${t('allianceExpansion.settingsTitle')} · ${alliance.name}`" />

  <AppLayout :user="user" :player-alliance-name="alliance.name" :has-player-alliance="true">
    <RoomBanner
      :eyebrow="t('allianceExpansion.settingsEyebrow')"
      :title="t('allianceExpansion.settingsTitle')"
      :subtitle="t('allianceExpansion.settingsSubtitle')"
      image="/images/kingshot/v4/alliance-hall.svg"
      compact
    >
      <template #actions>
        <Link href="/alliance/roles" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navRoles') }}
        </Link>
        <Link href="/alliance/history" class="ks-command-link" data-variant="secondary">
          {{ t('allianceExpansion.navHistory') }}
        </Link>
      </template>
    </RoomBanner>

    <form class="ks-surface mx-auto mt-6 max-w-3xl p-5 sm:p-6" @submit.prevent="submit">
      <div class="grid gap-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="text-sm font-semibold" for="alliance-name">
            {{ t('allianceExpansion.allianceName') }}
          </label>
          <input id="alliance-name" v-model="form.name" class="ks-input mt-2" maxlength="120" />
          <FormError :message="form.errors.name" />
        </div>

        <div class="sm:col-span-2">
          <label class="text-sm font-semibold" for="alliance-slug">
            {{ t('allianceExpansion.allianceUrlName') }}
          </label>
          <input id="alliance-slug" v-model="form.slug" class="ks-input mt-2" maxlength="120" />
          <FormError :message="form.errors.slug" />
        </div>

        <div>
          <label class="text-sm font-semibold" for="alliance-language">
            {{ t('allianceExpansion.language') }}
          </label>
          <select id="alliance-language" v-model="form.language" class="ks-input mt-2">
            <option v-for="locale in locales" :key="locale" :value="locale">{{ locale }}</option>
          </select>
          <FormError :message="form.errors.language" />
        </div>

        <div>
          <label class="text-sm font-semibold" for="alliance-timezone">
            {{ t('allianceExpansion.timezone') }}
          </label>
          <input id="alliance-timezone" v-model="form.timezone" class="ks-input mt-2" />
          <FormError :message="form.errors.timezone" />
        </div>
      </div>

      <div class="mt-6 flex justify-end">
        <AppButton type="submit" :disabled="form.processing">
          {{ t('allianceExpansion.saveSettings') }}
        </AppButton>
      </div>
    </form>
  </AppLayout>
</template>
