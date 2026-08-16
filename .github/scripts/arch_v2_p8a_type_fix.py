from pathlib import Path

path = Path('app/Workflows/KingdomTransfer/Actions/SaveTransferParticipant.php')
if not path.is_file():
    raise RuntimeError('Missing generated SaveTransferParticipant.php')

source = path.read_text(encoding='utf-8')
old = '''/**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */'''
new = '''/**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *   roster_entry_id: string|null,
     *   player_id: string,
     *   observed_name: string,
     *   game_player_id: string|null,
     *   source_kingdom_id: string,
     *   destination_kingdom_id: string|null
     * }
     */'''

hits = source.count(old)
if hits != 2:
    raise RuntimeError(f'Expected two generated participant value docblocks, found {hits}.')

path.write_text(source.replace(old, new), encoding='utf-8')
print('Tightened P8A Transfer participant value return shapes.')
