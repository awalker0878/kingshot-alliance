# Reminder policy

Status: Current  
Context: Operations  
Implementation: `app/Contexts/Operations/Participation/Reminders`

Operations owns **whether and when** an Event reminder is due: reminder rules, offsets/scheduling policy and the Event/occurrence relationship that caused the reminder.

Communications owns **delivery state**: attempts, retries/idempotency, recipient delivery preferences and channel behavior.

This split prevents a delivery subsystem from becoming the owner of Event timing while also preventing Operations from navigating into transport/delivery records. Cross-context reminder inbox composition belongs in a ReadModel when it needs both scheduling and delivery information.