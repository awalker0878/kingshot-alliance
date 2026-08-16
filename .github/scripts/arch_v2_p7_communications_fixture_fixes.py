from pathlib import Path

path = Path('tests/Feature/Communications/EventReminders/EventReminderDeliveryTest.php')
if not path.is_file():
    raise RuntimeError('Promoted EventReminderDeliveryTest was not created before fixture correction.')

source = path.read_text(encoding='utf-8')
old = """        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Reminder One', 'game_player_id' => '8306-first']);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Reminder Two', 'game_player_id' => '8306-second']);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();"""
new = """        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Reminder One', 'game_player_id' => '8306-first']);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Reminder Two', 'game_player_id' => '8306-second']);
        $issued = $this->app->make(CreateInvitation::class)->handle($alliance, $firstPlayer, $secondPlayer, (string) $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();"""

if source.count(old) != 1:
    raise RuntimeError('Roster reminder fixture shape changed unexpectedly.')

path.write_text(source.replace(old, new, 1), encoding='utf-8')
print('Promoted roster reminder fixture now gives the assignee real Alliance membership authority.')
