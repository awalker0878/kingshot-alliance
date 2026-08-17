# Accounts — Authentication

Status: Current — Architecture V3

Implementation target: `app/Contexts/Accounts/Authentication`

Authentication owns sign-in, sign-out, session establishment and account-confirmation behavior.

Authentication proves which User is operating the application. It does not grant Player-scoped Alliance, Kingdom, Operations or Intelligence authority.

HTTP adapters remain thin and delegate state-changing authentication behavior to capability-owned Actions/services.