from pathlib import Path


def replace(path: str, old: str, new: str, count: int | None = None) -> None:
    target = Path(path)
    text = target.read_text()
    found = text.count(old)
    if found == 0:
        raise SystemExit(f"Expected pattern not found in {path}: {old[:100]!r}")
    if count is not None and found != count:
        raise SystemExit(f"Expected {count} occurrences in {path}, found {found}: {old[:100]!r}")
    target.write_text(text.replace(old, new))


replace(
    'app/Contexts/Intelligence/Contributions/Http/Controllers/ContributionController.php',
    "['name' => (string) $user->name, 'email' => (string) $user->email]",
    "['name' => $user->accountName(), 'email' => $user->accountEmail()]",
    2,
)

path = 'app/Contexts/Intelligence/Contributions/Queries/ContributionReportingQuery.php'
replace(
    path,
    "$recordPlayerIds = $pending->concat($recent)->pluck('player_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all();",
    "$recordPlayerIds = array_values($pending->concat($recent)->pluck('player_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all());",
)
replace(
    path,
    "$players = $this->players->byIds($records->pluck('player_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all());",
    "$players = $this->players->byIds(array_values($records->pluck('player_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all()));",
    2,
)
replace(path, "'entries' => array_values($totals),", "'entries' => $totals,")

replace('app/Contexts/Intelligence/Diplomacy/Actions/SaveKingdomAllianceDiplomacyContact.php', '$currentAlliance->id', '$allianceId')
replace('app/Contexts/Intelligence/Diplomacy/Actions/TransitionKingdomAllianceDiplomacy.php', '$currentAlliance->id', '$allianceId', 2)

replace(
    'app/Contexts/Intelligence/Diplomacy/Http/Controllers/KingdomAllianceDiplomacyContactController.php',
    '/** @param array<string,PlayerReference> $players @return array<string,mixed> */\n    private function contactRow',
    '/**\n     * @param  array<string, PlayerReference>  $players\n     * @return array{id:string,displayName:string,gameRole:string|null,channelType:string,handle:string,state:string,lastVerifiedAt:string|null,managerNotes:string|null,createdByName:string|null,updatedByName:string|null,deactivatedByName:string|null,createdAt:string,updatedAt:string,deactivatedAt:string|null}\n     */\n    private function contactRow',
)

path = 'app/Contexts/Intelligence/Diplomacy/Http/Controllers/KingdomAllianceDiplomacyController.php'
replace(
    path,
    '/** @param array<string,PlayerReference> $players @return array<string,mixed> */\n    private function relationshipSummary',
    '/**\n     * @param  array<string, PlayerReference>  $players\n     * @return array{exists:bool,state:string,effectiveAt:string|null,reviewAt:string|null,expiresAt:string|null,needsReview:bool,terms:string|null,rationale:string|null,lastActorName:string|null}\n     */\n    private function relationshipSummary',
)
replace(
    path,
    '/** @param array<string,PlayerReference> $players @return array<string,mixed> */\n    private function transitionRow',
    '/**\n     * @param  array<string, PlayerReference>  $players\n     * @return array{id:string,fromState:string,toState:string,effectiveAt:string,reviewAt:string|null,expiresAt:string|null,terms:string|null,rationale:string|null,actorName:string|null,recordedAt:string}\n     */\n    private function transitionRow',
)

for path in [
    'app/Contexts/Intelligence/Ingestion/Actions/ReplayKingdomIngestionCandidate.php',
    'app/Contexts/Intelligence/Ingestion/Actions/TransitionKingdomIngestionSubscription.php',
]:
    replace(
        path,
        '$scope->kingdomId === null\n                || (string) $scope->kingdomId !== (string) $subscription->kingdom_id',
        '$scope->kingdomId !== (string) $subscription->kingdom_id',
    )

for path in [
    'app/Contexts/Intelligence/Ingestion/Actions/RunKingdomIngestionSubscription.php',
    'app/Contexts/Intelligence/Ingestion/Actions/StartKingdomIngestionBatch.php',
]:
    replace(
        path,
        '$context->alliance->kingdomId === null\n                || (string) $context->alliance->kingdomId !== (string) $subscription->kingdom_id',
        '$context->alliance->kingdomId !== (string) $subscription->kingdom_id',
    )

replace(
    'app/Contexts/Intelligence/Ingestion/Http/Controllers/KingdomIngestionController.php',
    "$kingdomRefs = $kingdoms->byIds($subscriptions->pluck('kingdom_id')->map(static fn ($id): string => (string) $id)->all());",
    "$kingdomRefs = $kingdoms->byIds(array_values($subscriptions->pluck('kingdom_id')->map(static fn ($id): string => (string) $id)->all()));",
)

replace(
    'app/Contexts/Intelligence/Ingestion/Services/KingdomIngestionMutationState.php',
    "            KingdomIngestionSubscription::query()->findOrFail($subscriptionId);\n        }\n\n        $alliance = $this->alliances->lockCurrent((string) $route->alliance_id);",
    "            $route = KingdomIngestionSubscription::query()\n                ->select(['id', 'alliance_id'])\n                ->findOrFail($subscriptionId);\n        }\n\n        $alliance = $this->alliances->lockCurrent((string) $route->alliance_id);",
)

path = 'app/Contexts/Intelligence/Observations/Http/Controllers/KingdomAllianceController.php'
replace(
    path,
    '/** @param iterable<int,TrackedKingdomAlliance> $tracking @return list<array<string,mixed>> */\n    private function trackingRows',
    '/**\n     * @param  iterable<int, TrackedKingdomAlliance>  $tracking\n     * @return list<array<string, mixed>>\n     */\n    private function trackingRows',
)
replace(
    path,
    '$kingdomRefs = $kingdoms->byIds(array_map(static fn (TrackedKingdomAlliance $row): string => (string) $row->kingdom_id, $items));',
    '$kingdomRefs = $kingdoms->byIds(array_values(array_map(static fn (TrackedKingdomAlliance $row): string => (string) $row->kingdom_id, $items)));',
)
replace(
    path,
    '$allianceRefs = $kingdomAlliances->byIds(array_map(static fn (TrackedKingdomAlliance $row): string => (string) $row->kingdom_alliance_id, $items));',
    '$allianceRefs = $kingdomAlliances->byIds(array_values(array_map(static fn (TrackedKingdomAlliance $row): string => (string) $row->kingdom_alliance_id, $items)));',
)

path = 'app/Contexts/Intelligence/Observations/Http/Controllers/KingdomAllianceObservationController.php'
replace(
    path,
    '/** @param array<string,PlayerReference> $actors @return array<string,mixed> */\n    private function observationRow',
    '/**\n     * @param  array<string, PlayerReference>  $actors\n     * @return array<string, mixed>\n     */\n    private function observationRow',
)
replace(path, "'actorName' => $actor?->currentName,", "'actorName' => $actor instanceof PlayerReference ? $actor->currentName : null,")
replace(path, "'invalidatedByName' => $invalidator?->currentName,", "'invalidatedByName' => $invalidator instanceof PlayerReference ? $invalidator->currentName : null,")

path = 'app/Contexts/Intelligence/Roster/Services/RosterCsvExporter.php'
replace(
    path,
    '/** @param list<string> $entryIds @return array<string,PlayerSnapshot> */\n    private function latestSnapshots',
    '/**\n     * @param  list<string>  $entryIds\n     * @return array<string, PlayerSnapshot>\n     */\n    private function latestSnapshots',
)
replace(path, "'game_player_id' => $player?->gamePlayerId ?? '',", "'game_player_id' => $player instanceof PlayerReference ? ($player->gamePlayerId ?? '') : '',")
replace(path, "'progression_level' => $snapshot?->progression_level ?? '',", "'progression_level' => $snapshot instanceof PlayerSnapshot ? ($snapshot->progression_level ?? '') : '',")
replace(path, "'alliance_tag' => $snapshot?->observed_alliance_tag ?? '',", "'alliance_tag' => $snapshot instanceof PlayerSnapshot ? ($snapshot->observed_alliance_tag ?? '') : '',")

replace(
    'app/ReadModels/KingdomIntelligence/KingdomAllianceIntelligenceQuery.php',
    '/** @param iterable<int, KingdomAllianceTrackingRow> $tracking @return list<string> */\n    private function trackingIds',
    '/**\n     * @param  iterable<int, KingdomAllianceTrackingRow>  $tracking\n     * @return list<string>\n     */\n    private function trackingIds',
)
