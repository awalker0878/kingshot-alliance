<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps<{
  user: {
    name: string;
    email: string;
    emailVerified: boolean;
    timezone: string;
    twoFactorEnabled: boolean;
    twoFactorPending: boolean;
  };
  twoFactorSetup: { secret: string; provisioning_uri: string } | null;
  twoFactorRecoveryCodes: string[] | null;
  status: string | null;
}>();

const profileForm = useForm({
  name: props.user.name,
  email: props.user.email,
  timezone: props.user.timezone,
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const sessionsForm = useForm({
  password: '',
});

const twoFactorForm = useForm({
  code: '',
});

function updateProfile(): void {
  profileForm.patch('/profile');
}

function updatePassword(): void {
  passwordForm.put('/profile/password', {
    onFinish: () => passwordForm.reset(),
  });
}

function revokeOtherSessions(): void {
  sessionsForm.delete('/profile/sessions/other', {
    onFinish: () => sessionsForm.reset(),
  });
}

function beginTwoFactor(): void {
  router.post('/profile/two-factor');
}

function confirmTwoFactor(): void {
  twoFactorForm.post('/profile/two-factor/confirm', {
    onFinish: () => twoFactorForm.reset(),
  });
}

function regenerateRecoveryCodes(): void {
  router.post('/profile/two-factor/recovery-codes');
}

function disableTwoFactor(): void {
  router.delete('/profile/two-factor');
}

function logout(): void {
  router.delete('/logout');
}
</script>

<template>
  <Head title="Profile" />

  <main class="mx-auto min-h-screen max-w-4xl px-6 py-12 lg:px-8">
    <header class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <Link class="text-sm font-semibold text-cyan-300 hover:text-cyan-200" href="/dashboard">← Dashboard</Link>
        <h1 class="mt-3 text-3xl font-bold">Account & security</h1>
      </div>
      <button class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold" type="button" @click="logout">
        Sign out
      </button>
    </header>

    <p v-if="props.status" class="mt-6 rounded-lg border border-emerald-800 bg-emerald-950/30 p-4 text-sm text-emerald-100">
      {{ props.status }}
    </p>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <h2 class="text-xl font-semibold">Profile</h2>
      <p class="mt-2 text-sm text-slate-400">Changing your email requires verification again.</p>

      <form class="mt-6 grid gap-5 md:grid-cols-2" @submit.prevent="updateProfile">
        <div>
          <label class="block text-sm font-medium" for="profile-name">Name</label>
          <input id="profile-name" v-model="profileForm.name" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" required type="text" />
          <p v-if="profileForm.errors.name" class="mt-1 text-sm text-rose-300">{{ profileForm.errors.name }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium" for="profile-email">Email</label>
          <input id="profile-email" v-model="profileForm.email" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" required type="email" />
          <p v-if="profileForm.errors.email" class="mt-1 text-sm text-rose-300">{{ profileForm.errors.email }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium" for="profile-timezone">Time zone</label>
          <input id="profile-timezone" v-model="profileForm.timezone" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" required type="text" />
          <p v-if="profileForm.errors.timezone" class="mt-1 text-sm text-rose-300">{{ profileForm.errors.timezone }}</p>
        </div>
        <div class="flex items-end">
          <button class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60" :disabled="profileForm.processing" type="submit">Save profile</button>
        </div>
      </form>

      <p class="mt-4 text-xs text-slate-500">
        Email verification: {{ props.user.emailVerified ? 'verified' : 'pending' }} · Two-factor authentication: {{ props.user.twoFactorEnabled ? 'enabled' : props.user.twoFactorPending ? 'setup pending' : 'not enabled' }}
      </p>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <h2 class="text-xl font-semibold">Two-factor authentication</h2>
      <p class="mt-2 text-sm text-slate-400">Protect sign-in with a TOTP authenticator. Recovery codes are shown only when created or regenerated.</p>

      <button v-if="!props.user.twoFactorEnabled && !props.twoFactorSetup" class="mt-6 rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950" type="button" @click="beginTwoFactor">
        Start setup
      </button>

      <div v-if="props.twoFactorSetup" class="mt-6 rounded-xl border border-slate-700 p-5">
        <p class="text-sm font-semibold">Authenticator secret</p>
        <p class="mt-2 break-all font-mono text-sm text-cyan-200">{{ props.twoFactorSetup.secret }}</p>
        <p class="mt-4 text-xs text-slate-500">Provisioning URI</p>
        <p class="mt-1 break-all font-mono text-xs text-slate-400">{{ props.twoFactorSetup.provisioning_uri }}</p>

        <form class="mt-5 flex flex-col gap-3 sm:flex-row" @submit.prevent="confirmTwoFactor">
          <input v-model="twoFactorForm.code" autocomplete="one-time-code" class="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 font-mono" inputmode="numeric" maxlength="6" placeholder="123456" required type="text" />
          <button class="rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950" type="submit">Confirm</button>
        </form>
        <p v-if="twoFactorForm.errors.code" class="mt-2 text-sm text-rose-300">{{ twoFactorForm.errors.code }}</p>
      </div>

      <div v-if="props.twoFactorRecoveryCodes" class="mt-6 rounded-xl border border-amber-800 bg-amber-950/20 p-5">
        <p class="font-semibold text-amber-100">Save these recovery codes now</p>
        <p class="mt-1 text-sm text-amber-200/80">Each code works once. Store them somewhere separate from this account.</p>
        <ul class="mt-4 grid gap-2 font-mono text-sm sm:grid-cols-2">
          <li v-for="code in props.twoFactorRecoveryCodes" :key="code">{{ code }}</li>
        </ul>
      </div>

      <div v-if="props.user.twoFactorEnabled" class="mt-6 flex flex-wrap gap-3">
        <button class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold" type="button" @click="regenerateRecoveryCodes">Regenerate recovery codes</button>
        <button class="rounded-lg border border-rose-800 px-4 py-2 text-sm font-semibold text-rose-200" type="button" @click="disableTwoFactor">Disable two-factor authentication</button>
      </div>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <h2 class="text-xl font-semibold">Change password</h2>
      <p class="mt-2 text-sm text-slate-400">Changing your password revokes personal access tokens and invalidates other authenticated sessions.</p>

      <form class="mt-6 grid gap-5" @submit.prevent="updatePassword">
        <div>
          <label class="block text-sm font-medium" for="current-password">Current password</label>
          <input id="current-password" v-model="passwordForm.current_password" autocomplete="current-password" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" required type="password" />
          <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-rose-300">{{ passwordForm.errors.current_password }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium" for="new-password">New password</label>
          <input id="new-password" v-model="passwordForm.password" autocomplete="new-password" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" required type="password" />
          <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-rose-300">{{ passwordForm.errors.password }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium" for="new-password-confirmation">Confirm new password</label>
          <input id="new-password-confirmation" v-model="passwordForm.password_confirmation" autocomplete="new-password" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" required type="password" />
        </div>
        <button class="w-fit rounded-lg bg-cyan-300 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60" :disabled="passwordForm.processing" type="submit">Update password</button>
      </form>
    </section>

    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-8">
      <h2 class="text-xl font-semibold">Other sessions</h2>
      <p class="mt-2 text-sm text-slate-400">Revoke every authenticated session except this device.</p>

      <form class="mt-6 flex flex-col gap-3 sm:flex-row" @submit.prevent="revokeOtherSessions">
        <input v-model="sessionsForm.password" autocomplete="current-password" class="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2" placeholder="Current password" required type="password" />
        <button class="rounded-lg border border-rose-800 px-4 py-2 font-semibold text-rose-200 disabled:opacity-60" :disabled="sessionsForm.processing" type="submit">Sign out other devices</button>
      </form>
      <p v-if="sessionsForm.errors.password" class="mt-2 text-sm text-rose-300">{{ sessionsForm.errors.password }}</p>
    </section>
  </main>
</template>
