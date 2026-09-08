# Accounts — Authentication

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Authentication`

Authentication owns sign-in, sign-out, session establishment and account-confirmation behavior.

Authentication proves which User is operating the application. It does not grant Player-scoped Alliance, Kingdom, Operations or Intelligence authority.

HTTP adapters remain thin and delegate state-changing authentication behavior to capability-owned Actions/services.

RecordAccountSession registers and updates durable browser state within a short current-active account transaction, taking the account lock before session state. This serializes registration with finalization and revocation. Missing/finalized accounts return false, and the conditional update still refuses revoked sessions. Request middleware checks remain useful early rejection, while the owner recheck protects requests already in flight. Primary credential proof freshness during concurrent login/revocation remains tracked separately as HARD-037.

Password confirmation delegates to ConfirmAccountPassword, which locks the current active account and validates its current password before recording confirmation audit. RecentAuthentication session proof is published only after the outermost commit; rollback leaves prior proof unchanged. The callback rechecks account binding, lifecycle and the password fingerprint, suppressing proof if a later operation in that transaction changed the credential or account.

Google reauthentication delegates to ConfirmGoogleAccount after Socialite verifies the response and the Workflow consumes its bound operation. The owner locks the current active account before its Google identity, checks the exact provider subject, and commits identity-use metadata with authentication audit. Subject mismatch records rejection and grants no proof. The outer-commit callback rechecks request binding, active state and that exact identity before publishing Google recent proof.
