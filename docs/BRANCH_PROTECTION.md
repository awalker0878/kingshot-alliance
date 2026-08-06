# Recommended Branch Protection

Apply these settings to `main` after the first CI run confirms the check names.

- Require a pull request before merging.
- Require at least one approval.
- Dismiss stale approvals when new commits are pushed.
- Require conversation resolution.
- Require branches to be up to date before merging.
- Require these checks:
  - PHP quality and tests
  - Frontend quality and build
  - Container build
  - Dependency Review
  - CodeQL
- Block force pushes and branch deletion.
- Require signed commits when all contributors can support them.
- Restrict direct pushes to emergency administrators.
- Require linear history or squash merges.
- Enable secret scanning and push protection.
