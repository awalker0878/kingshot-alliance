# Primary user journeys

Status: Current

## Account to game context

```text
Register/sign in
 -> satisfy account security requirements
 -> claim/select Player
 -> active Player resolved
 -> Alliance/Kingdom/game capabilities become available according to that Player
```

## Daily Governor briefing

```text
Select active Player
 -> compose unread notifications, actionable Gift Codes, Event actions and upcoming Events
 -> include recruitment follow-up only when the Player has recruitment authority
 -> link to owner-context rooms for every write
```

The command overview is a read-model composition surface. It does not own or copy the underlying business state.

## Alliance member

```text
Active Player
 -> current Alliance membership
 -> rank/specialist authority
 -> Alliance content / recruitment / membership / event capabilities as permitted
```

## Event coordinator

```text
Select scoped Event
 -> plan occurrence/capabilities
 -> manage participation/roster/polls/battle plan/rallies
 -> record results
 -> analytical/history projections become available through Intelligence/ReadModels
```

## King Perks coordinator

```text
Kingdom/Event preparation context
 -> publish planning window
 -> assign appointment/skill schedule
 -> enforce occupancy and cooldown
 -> communicate due reminders through delivery pipeline
 -> close/record operational outcomes
```

## Platform administrator

```text
Authenticate User + required account assurance
 -> validate Platform Administrator grant
 -> perform cross-tenant platform operation
```

Platform administration never substitutes for selecting a Player when performing game-domain behavior.