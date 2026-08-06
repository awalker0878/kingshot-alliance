# Recommended Branch Protection

Apply these settings to `main` after the first successful Phase 0 workflow run confirms the check contexts.

- Require a pull request before merging.
- Require at least one approval.
- Dismiss stale approvals when new commits are pushed.
- Require conversation resolution.
- Require branches to be up to date before merging.
- Require these checks:
  - `PHP quality and tests`
  - `Frontend quality and build`
  - `Container, staging, and recovery`
  - `Dependency review`
  - `CodeQL (javascript-typescript)`
  - `CodeQL (php)`
- Do not require the temporary `Generate lock files` workflow; delete that workflow after both lockfiles are committed.
- Block force pushes and branch deletion.
- Require signed commits when all contributors can support them.
- Restrict direct pushes to emergency administrators.
- Require linear history or squash merges.
- Enable secret scanning and push protection.

Record the applied repository settings and the successful check run in the Phase 0 exit report before acceptance.
