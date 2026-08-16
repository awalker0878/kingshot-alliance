# Recruitment

Status: Current  
Context: Alliance  
Implementation: `app/Contexts/Alliance/Recruitment`

Recruitment owns Alliance candidate/application behavior up to the controlled handoff into Membership.

## Responsibilities

- recruitment/application intake represented by the current capability;
- review/decision behavior;
- Alliance recruiter/reviewer authority;
- handoff to membership when an accepted candidate is eligible to become a member;
- retention/anonymization behavior where recruitment records contain applicant data.

## Authority

Alliance recruiters/reviewers act through the active Player and concrete Alliance scope. Public/account-facing intake may know the User account where necessary, but that does not make recruiter authority User-scoped.

## Boundary

Recruitment does not create a parallel membership model. Once membership is created, Membership is the authoritative Alliance relationship.