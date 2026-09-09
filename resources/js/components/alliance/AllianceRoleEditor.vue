<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

import AppButton from '@/components/ui/AppButton.vue';
import FormError from '@/components/ui/FormError.vue';
import { useLocale } from '@/localization';

const props = defineProps<{
  role: { id: string; name: string; permissions: string[] };
  permissions: string[];
}>();
const emit = defineEmits<{ archive: [] }>();
const { t } = useLocale();
const form = useForm({ name: props.role.name, permissions: [...props.role.permissions] });
const roleError = ref<string | null>(null);

function resetToServer(): void {
  form.defaults({ name: props.role.name, permissions: [...props.role.permissions] });
  form.reset();
}

watch(
  () => props.role,
  () => {
    if (!form.isDirty && !form.processing) resetToServer();
  },
);

function save(): void {
  roleError.value = null;
  form.patch('/alliance/roles/' + props.role.id, {
    preserveScroll: true,
    onSuccess: resetToServer,
    onError: (errors) => {
      roleError.value = errors.role ?? null;
    },
  });
}
</script>

<template>
  <form class="mt-5 grid gap-4" @submit.prevent="save">
    <div>
      <label class="text-sm font-semibold" :for="'role-name-' + role.id">
        {{ t('allianceExpansion.roleName') }}
      </label>
      <input
        :id="'role-name-' + role.id"
        v-model="form.name"
        class="ks-input mt-2"
        maxlength="100"
        :aria-invalid="Boolean(form.errors.name)"
        :aria-describedby="form.errors.name ? 'role-name-error-' + role.id : undefined"
      />
      <FormError :id="'role-name-error-' + role.id" :message="form.errors.name" />
    </div>
    <fieldset>
      <legend class="text-sm font-semibold">{{ t('allianceExpansion.permissions') }}</legend>
      <div class="mt-2 grid gap-2 sm:grid-cols-2">
        <label
          v-for="permission in permissions"
          :key="permission"
          class="flex items-center gap-2 rounded-[var(--ks-radius-sm)] border border-[var(--ks-border)] px-3 py-2 text-sm"
        >
          <input v-model="form.permissions" type="checkbox" :value="permission" />
          <span>{{ t('allianceExpansion.permissionLabels.' + permission) }}</span>
        </label>
      </div>
      <FormError :message="form.errors.permissions" />
    </fieldset>
    <FormError :message="roleError" />
    <div class="flex flex-wrap gap-2">
      <AppButton type="submit" variant="secondary" :disabled="form.processing">
        {{ t('allianceExpansion.updateRole') }}
      </AppButton>
      <AppButton type="button" variant="ghost" :disabled="form.processing" @click="emit('archive')">
        {{ t('allianceExpansion.archiveRole') }}
      </AppButton>
    </div>
  </form>
</template>
