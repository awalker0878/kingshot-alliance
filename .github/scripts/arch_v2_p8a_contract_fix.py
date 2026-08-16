from pathlib import Path

path = Path('tests/Architecture/ArchitectureV2WorkflowTest.php')
source = path.read_text(encoding='utf-8')
old = '        self::assertStringNotContainsString("$lockedPlayer->forceFill([\'user_id\'", $source);'
new = "        self::assertStringNotContainsString('forceFill([\\'user_id\\'', $source);"
if source.count(old) != 1:
    raise RuntimeError('P8A workflow contract source changed unexpectedly.')
path.write_text(source.replace(old, new, 1), encoding='utf-8')

# The legacy readiness password-confirmation fixture first creates its participant
# with a recently confirmed session, then attempts to exercise the password.confirm
# middleware using activeSession(). Laravel's withSession() merges keys, so the
# previous auth.password_confirmed_at value survives unless we explicitly replace it.
# Make the migrated contract deterministic by marking that confirmation stale.
readiness = Path('tests/Feature/Workflows/KingdomTransfer/TransferReadinessTest.php')
source = readiness.read_text(encoding='utf-8')
old = "            ->withSession($this->activeSession($ownerPlayer->id))\n            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'preparing'])\n            ->assertRedirect(route('password.confirm'));"
new = "            ->withSession([...$this->activeSession($ownerPlayer->id), 'auth.password_confirmed_at' => 0])\n            ->patch($this->readinessUrl($plan, $participant), ['readiness' => 'preparing'])\n            ->assertRedirect(route('password.confirm'));"
if source.count(old) != 1:
    raise RuntimeError('P8A readiness password-confirmation fixture changed unexpectedly.')
readiness.write_text(source.replace(old, new, 1), encoding='utf-8')
