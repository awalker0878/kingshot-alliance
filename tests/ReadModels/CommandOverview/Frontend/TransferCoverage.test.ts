import assert from 'node:assert/strict';
import test from 'node:test';

const locales = [
  'ar',
  'de',
  'en',
  'es',
  'fr',
  'id',
  'it',
  'ja',
  'ko',
  'pl',
  'pt-BR',
  'ru',
  'th',
  'tr',
  'vi',
  'zh-CN',
  'zh-TW',
];

test('every supported locale distinguishes known attention from unassessed Transfer participants', async () => {
  for (const locale of locales) {
    const core = (await import(`../../../../resources/js/localization/messages/core/${locale}.ts`))
      .default;
    const assistant = (
      await import(`../../../../resources/js/localization/messages/assistant/${locale}.ts`)
    ).default;
    const reason = core.application.dashboard.commandReasons.transferAssessmentIncomplete;
    const state = core.application.dashboard.commandStates.assessment_incomplete;
    const answer = assistant.assistant.answers.transferVerificationIncomplete;
    assert.equal(typeof reason, 'string', locale);
    assert.equal(typeof state, 'string', locale);
    assert.equal(typeof answer, 'string', locale);
    for (const parameter of ['count', 'unassessed'])
      assert.ok(reason.includes(`{${parameter}}`), `${locale} ${parameter}`);
    for (const parameter of ['count', 'assessed', 'total', 'unassessed'])
      assert.ok(answer.includes(`{${parameter}}`), `${locale} ${parameter}`);
  }
});
