# Local environment recovery

Status: Current

For normal setup see [Codebase local development](../../codebase/local-development.md).

If the local environment becomes inconsistent:

1. preserve any local data you actually need;
2. verify `.env` against `.env.example` without overwriting secrets blindly;
3. rebuild/restart the repository's local container/service topology;
4. rerun migrations/setup as appropriate;
5. run `make check` and a representative test;
6. verify the app at `http://localhost:8080`.

Do not use destructive production restore procedures as a shortcut for local setup problems.