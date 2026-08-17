# Accounts — Profile

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Profile`

Profile owns mutable User profile information and profile-change invariants.

Profile HTTP adapters validate transport input and invoke capability Actions. They do not own `DB::transaction`, direct `save()` calls or other business persistence.