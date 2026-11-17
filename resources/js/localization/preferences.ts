import { defaultLocale, normalizeLocale, type LocaleCode } from './locales.ts';

export const preferenceCookie = 'kingshot_locale_state';
export const preferenceStorage = 'kingshot.locale.preference';
export const legacyCookie = 'kingshot_locale';
export const legacyStorage = 'kingshot.locale';

export interface LocalePreference {
  version: 1;
  epoch: string;
  revision: number;
  selectionId: string;
  locale: LocaleCode;
}

// One bounded, atomic cookie. PHP uses the same grammar and canonical codes.
export function parsePreference(value: unknown): LocalePreference | null {
  if (typeof value !== 'string' || value.length > 128) return null;
  const match = /^1\.([a-f0-9]{32})\.(0|[1-9][0-9]{0,15})\.([a-f0-9]{32})\.([A-Za-z-]+)$/.exec(
    value,
  );
  if (!match || match[0] !== value) return null;
  const revision = Number(match[2]);
  const locale = normalizeLocale(match[4]);
  if (!Number.isSafeInteger(revision) || !locale || locale !== match[4]) return null;
  return { version: 1, epoch: match[1]!, revision, selectionId: match[3]!, locale };
}

export function serializePreference(value: LocalePreference): string {
  return `1.${value.epoch}.${value.revision}.${value.selectionId}.${value.locale}`;
}

/** Only compare records in the cookie's current epoch; a reset has no numeric order. */
export function comparePreferences(a: LocalePreference, b: LocalePreference): number {
  return (
    a.revision - b.revision ||
    (a.selectionId < b.selectionId ? -1 : a.selectionId > b.selectionId ? 1 : 0) ||
    (a.locale < b.locale ? -1 : a.locale > b.locale ? 1 : 0)
  );
}

interface PreferenceEnvironment {
  readCookie(name: string): string | null;
  writeCookie(name: string, value: string): void;
  writeStorage(key: string, value: string): void;
  fallbackLocale(): string | null;
  newId(): string;
  load(locale: LocaleCode, authorize: () => boolean): Promise<void>;
  desired(locale: LocaleCode): void;
}

/** Cookie anchors epochs; storage events carry optional same-epoch notifications. */
export function createLocalePreferences(env: PreferenceEnvironment) {
  let shared: LocalePreference | null = null;
  let virtual = true;
  let invalidCookie: string | null = null;
  let sequence = 0;
  let reserved = 0;
  let pending: LocalePreference | null = null;
  let resetAnchor: string | undefined;
  let applied: string | null = null;
  let localOnly: { record: LocalePreference; anchor: string } | null = null;

  function read(name: string): string | null {
    try {
      return env.readCookie(name);
    } catch {
      return null;
    }
  }

  function publish(record: LocalePreference): boolean {
    const encoded = serializePreference(record);
    try {
      if (read(preferenceCookie) !== encoded) env.writeCookie(preferenceCookie, encoded);
      if (read(preferenceCookie) !== encoded) return false;
    } catch {
      return false;
    }
    if (shared?.epoch !== record.epoch) reserved = record.revision;
    shared = record;
    virtual = false;
    // A failed mirror must not turn a successful UI load into a failed selection.
    try {
      env.writeCookie(legacyCookie, record.locale);
    } catch {
      /* optional legacy mirror */
    }
    try {
      env.writeStorage(preferenceStorage, encoded);
      env.writeStorage(legacyStorage, record.locale);
    } catch {
      /* Cookie remains usable when storage is blocked. */
    }
    return true;
  }

  function readShared(eventValue?: string | null): LocalePreference {
    const raw = read(preferenceCookie);
    const cookie = parsePreference(raw);
    if (!cookie) {
      if (!shared || !virtual || invalidCookie !== raw) {
        shared = {
          version: 1,
          epoch: env.newId(),
          revision: 0,
          selectionId: env.newId(),
          locale:
            normalizeLocale(read(legacyCookie)) ??
            normalizeLocale(env.fallbackLocale()) ??
            defaultLocale,
        };
        reserved = 0;
        localOnly = null;
      }
      invalidCookie = raw;
      virtual = true;
      return shared;
    }

    if (!shared || virtual || shared.epoch !== cookie.epoch) {
      shared = cookie;
      reserved = cookie.revision;
      localOnly = null;
    } else if (comparePreferences(cookie, shared) > 0) shared = cookie;
    virtual = false;
    const event = parsePreference(eventValue);
    if (event?.epoch === cookie.epoch && comparePreferences(event, shared) > 0) shared = event;
    // Repair an interleaved older write. Replayed events do not create a revision.
    if (comparePreferences(shared, cookie) > 0) publish(shared);
    return shared;
  }

  async function attempt(record: LocalePreference, resetFrom?: string): Promise<void> {
    const request = ++sequence;
    pending = record;
    resetAnchor = resetFrom;
    env.desired(record.locale);
    let superseded = false;
    await env.load(record.locale, () => {
      if (request !== sequence) return false;
      const latest = readShared();
      if (
        resetFrom
          ? serializePreference(latest) !== resetFrom
          : record.epoch !== latest.epoch || comparePreferences(record, latest) < 0
      ) {
        superseded = true;
        return false;
      }
      const anchor = serializePreference(latest);
      const persisted = publish(record);
      // Another tab can write between our cookie write and confirmation.
      const after = readShared();
      if (
        (persisted || serializePreference(after) !== anchor) &&
        (record.epoch !== after.epoch || comparePreferences(record, after) < 0)
      ) {
        superseded = true;
        return false;
      }
      localOnly = persisted ? null : { record, anchor };
      applied = serializePreference(record);
      pending = null;
      resetAnchor = undefined;
      return true;
    });
    if (superseded && request === sequence) await synchronize();
  }

  async function synchronize(eventValue?: string | null, force = false): Promise<void> {
    const latest = readShared(eventValue);
    const encoded = serializePreference(latest);
    if (localOnly?.anchor === encoded && !force) return;
    if (!force && (applied === encoded || (pending && serializePreference(pending) === encoded)))
      return;
    // A pending local intent still wins older same-epoch notifications.
    if (!force && pending?.epoch === latest.epoch && comparePreferences(pending, latest) > 0)
      return;
    await attempt(latest);
  }

  async function select(locale: LocaleCode): Promise<void> {
    const latest = readShared();
    const revision = Math.max(latest.revision, reserved, localOnly?.record.revision ?? 0);
    // Reserve a reset, but publish it only after loading and rechecking its anchor.
    if (revision === Number.MAX_SAFE_INTEGER) {
      await attempt(
        { ...latest, epoch: env.newId(), revision: 1, selectionId: env.newId(), locale },
        serializePreference(latest),
      );
      return;
    }
    reserved = revision + 1;
    await attempt({ ...latest, revision: reserved, selectionId: env.newId(), locale });
  }

  return {
    initialize: () => synchronize(undefined, true),
    select,
    synchronize,
    async retry() {
      const latest = readShared();
      const record = pending ?? localOnly?.record;
      if (record && resetAnchor === serializePreference(latest)) await attempt(record, resetAnchor);
      else if (record?.epoch === latest.epoch && comparePreferences(record, latest) >= 0)
        await attempt(record);
      else await synchronize(undefined, true);
    },
  };
}
