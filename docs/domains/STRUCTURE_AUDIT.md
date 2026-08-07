# Implementation-plan structure audit

Generated from the repository filesystem on the architecture-alignment branch.

## Canonical target

The source of truth is `docs/IMPLEMENTATION_PLAN.md` section 11.

## Top-level structure

- `app/`: Application, Domain, Http, Infrastructure, Models, Providers
- `docs/` directories: architecture, runbooks
- `docs/` root files: BRANCH_PROTECTION.md, CONTENT_MANAGEMENT.md, DEFINITION_OF_DONE.md, EVENTS_AND_RALLIES.md, IMPLEMENTATION_PLAN.md, PHASES_1_4_ALIGNMENT_AUDIT.md, PHASE_0_EXIT_REPORT.md, PHASE_1_ACCESSIBILITY_REVIEW.md, PHASE_1_EXIT_REPORT.md, PHASE_1_MIGRATION_ROLLBACK.md, PHASE_1_THREAT_MODEL.md, PHASE_2_ACCESSIBILITY.md, PHASE_2_EXIT_REPORT.md, PHASE_2_MIGRATION_ROLLBACK.md, PHASE_2_OPERATIONS.md, PHASE_2_THREAT_MODEL.md, PHASE_3_ACCESSIBILITY.md, PHASE_3_EXIT_REPORT.md, PHASE_3_OPERATIONS.md, PHASE_3_SCOPE.md, PHASE_3_THREAT_MODEL.md, PHASE_4_ACCESSIBILITY.md, PHASE_4_EXIT_REPORT.md, PHASE_4_MIGRATION_ROLLBACK.md, PHASE_4_OPERATIONS.md, PHASE_4_THREAT_MODEL.md, RECRUITMENT.md, RELEASE_CHECKLIST.md, SECURITY_BASELINE.md
- `tests/`: Feature, Unit

## Domain directory drift

- Current: Content, Events, Identity, Recruitment, Shared
- Missing canonical domains: Alliances, Audit, Authorization, Contributions, Integrations, Kingdoms, Memberships, Notifications, Platform, Rallies
- Non-canonical domain directories: Shared

## Test directory drift

- Missing canonical test groups: Architecture, Integration, Performance, TenantIsolation

## Documentation directory drift

- Missing canonical documentation groups: adr, domains, operations, product, security
- Non-canonical documentation directories: architecture, runbooks

## Application-layer inventory

### Content

- `app/Application/Content/ArchiveContentItem.php`
- `app/Application/Content/ArchiveMediaAsset.php`
- `app/Application/Content/BasicMediaScanner.php`
- `app/Application/Content/ContentOutbox.php`
- `app/Application/Content/ContentPresenter.php`
- `app/Application/Content/ContentQuery.php`
- `app/Application/Content/ContentRevisionWriter.php`
- `app/Application/Content/ContentSanitizer.php`
- `app/Application/Content/DeleteContentCategory.php`
- `app/Application/Content/MediaScanResult.php`
- `app/Application/Content/MediaScanner.php`
- `app/Application/Content/PublishContentItem.php`
- `app/Application/Content/PublishScheduledContent.php`
- `app/Application/Content/RestoreContentRevision.php`
- `app/Application/Content/SaveContentCategory.php`
- `app/Application/Content/SaveContentItem.php`
- `app/Application/Content/UpdateAlliancePublicProfile.php`
- `app/Application/Content/UploadMediaAsset.php`

### Events

- `app/Application/Events/AllianceEventQuery.php`
- `app/Application/Events/AssignRallyMember.php`
- `app/Application/Events/CancelEventRegistration.php`
- `app/Application/Events/CreateEvent.php`
- `app/Application/Events/CreateEventFromTemplate.php`
- `app/Application/Events/CreateEventRecommendedFormation.php`
- `app/Application/Events/CreateEventReminderRule.php`
- `app/Application/Events/CreateEventTemplate.php`
- `app/Application/Events/CreateRallyGroup.php`
- `app/Application/Events/CreateRallyGuidanceRule.php`
- `app/Application/Events/EventOutbox.php`
- `app/Application/Events/MarkEventReminderPublished.php`
- `app/Application/Events/QueueDueEventReminders.php`
- `app/Application/Events/RecordEventAttendance.php`
- `app/Application/Events/RecordRallyParticipation.php`
- `app/Application/Events/RecurrenceCalculator.php`
- `app/Application/Events/RegisterForEvent.php`
- `app/Application/Events/SaveMemberFormation.php`
- `app/Application/Events/SyncEventReminderDeliveries.php`
- `app/Application/Events/SyncUpcomingEventReminders.php`

### Identity

- `app/Application/Identity/AcceptInvitation.php`
- `app/Application/Identity/AllianceAuthorization.php`
- `app/Application/Identity/AllianceContext.php`
- `app/Application/Identity/AllianceRoleProvisioner.php`
- `app/Application/Identity/AssignMembershipRole.php`
- `app/Application/Identity/AuditRecorder.php`
- `app/Application/Identity/CreateAlliance.php`
- `app/Application/Identity/CreateInvitation.php`
- `app/Application/Identity/FindPendingInvitation.php`
- `app/Application/Identity/InvitationTokenService.php`
- `app/Application/Identity/IssuedInvitation.php`
- `app/Application/Identity/LeaveAlliance.php`
- `app/Application/Identity/MembershipAdministrationGuard.php`
- `app/Application/Identity/RegisterUser.php`
- `app/Application/Identity/RemoveMembershipRole.php`
- `app/Application/Identity/ResendInvitation.php`
- `app/Application/Identity/RevokeInvitation.php`
- `app/Application/Identity/TotpService.php`
- `app/Application/Identity/TwoFactorManager.php`
- `app/Application/Identity/UpdateMembershipStatus.php`

### Operations

- `app/Application/Operations/RuntimeConfigurationValidator.php`

### Recruitment

- `app/Application/Recruitment/AddRecruitmentNote.php`
- `app/Application/Recruitment/AssignRecruitmentReviewer.php`
- `app/Application/Recruitment/ChangeRecruitmentStage.php`
- `app/Application/Recruitment/ConfigureRecruitmentSettings.php`
- `app/Application/Recruitment/ConvertAcceptedRecruitmentCandidate.php`
- `app/Application/Recruitment/ConvertedRecruitmentCandidate.php`
- `app/Application/Recruitment/CreateRecruitmentDecisionTemplate.php`
- `app/Application/Recruitment/CreateRecruitmentOnboardingItem.php`
- `app/Application/Recruitment/CreateRecruitmentQuestion.php`
- `app/Application/Recruitment/IssueRecruitmentApplicationInvite.php`
- `app/Application/Recruitment/IssuedRecruitmentApplicationInvite.php`
- `app/Application/Recruitment/MarkRecruitmentCandidateJoined.php`
- `app/Application/Recruitment/MarkRecruitmentCommunicationSent.php`
- `app/Application/Recruitment/MergeRecruitmentCandidates.php`
- `app/Application/Recruitment/PrepareRecruitmentDecisionCommunication.php`
- `app/Application/Recruitment/PublicRecruitmentQuery.php`
- `app/Application/Recruitment/PurgeExpiredRecruitmentCandidates.php`
- `app/Application/Recruitment/RecruitmentApplicationTokenService.php`
- `app/Application/Recruitment/RecruitmentDuplicateFinder.php`
- `app/Application/Recruitment/RecruitmentMetricsQuery.php`
- `app/Application/Recruitment/RecruitmentOutbox.php`
- `app/Application/Recruitment/SubmitRecruitmentApplication.php`
- `app/Application/Recruitment/TagRecruitmentCandidate.php`
- `app/Application/Recruitment/UpdateRecruitmentOnboardingStatus.php`
- `app/Application/Recruitment/UpdateRecruitmentQuestion.php`

### Shared

- `app/Application/Shared/PublishOutboxBatch.php`

## Domain-layer inventory

### Content

- `app/Domain/Content/Enums/ContentStatus.php`
- `app/Domain/Content/Enums/ContentType.php`
- `app/Domain/Content/Enums/ContentVisibility.php`
- `app/Domain/Content/Enums/MediaLifecycleStatus.php`
- `app/Domain/Content/Enums/MediaScanStatus.php`

### Events

- `app/Domain/Events/Enums/EventOccurrenceStatus.php`
- `app/Domain/Events/Enums/EventRegistrationStatus.php`
- `app/Domain/Events/Enums/EventReminderDeliveryStatus.php`
- `app/Domain/Events/Enums/EventStatus.php`
- `app/Domain/Events/Enums/RallyAssignmentRole.php`
- `app/Domain/Events/Enums/RallyAssignmentStatus.php`
- `app/Domain/Events/Enums/RecurrenceFrequency.php`
- `app/Domain/Events/FormationComposition.php`

### Identity

- `app/Domain/Identity/Authorization/DefaultAllianceRole.php`
- `app/Domain/Identity/Authorization/PermissionKey.php`
- `app/Domain/Identity/Enums/AllianceStatus.php`
- `app/Domain/Identity/Enums/InvitationStatus.php`
- `app/Domain/Identity/Enums/MembershipStatus.php`

### Recruitment

- `app/Domain/Recruitment/Enums/RecruitmentApplicationMode.php`
- `app/Domain/Recruitment/Enums/RecruitmentCommunicationStatus.php`
- `app/Domain/Recruitment/Enums/RecruitmentOnboardingStatus.php`
- `app/Domain/Recruitment/Enums/RecruitmentQuestionType.php`
- `app/Domain/Recruitment/Enums/RecruitmentStage.php`

### Shared

- `app/Domain/Shared/Events/OutboxPublished.php`
- `app/Domain/Shared/Tenancy/TenantContextSnapshot.php`

## Flat Eloquent model inventory

- `app/Models/Alliance.php`
- `app/Models/AllianceBrandingMedia.php`
- `app/Models/AllianceMembership.php`
- `app/Models/AllianceProfile.php`
- `app/Models/AuditEvent.php`
- `app/Models/ContentCategory.php`
- `app/Models/ContentItem.php`
- `app/Models/ContentRevision.php`
- `app/Models/Event.php`
- `app/Models/EventOccurrence.php`
- `app/Models/EventRecommendedFormation.php`
- `app/Models/EventRegistration.php`
- `app/Models/EventReminderDelivery.php`
- `app/Models/EventReminderRule.php`
- `app/Models/EventTemplate.php`
- `app/Models/Invitation.php`
- `app/Models/MediaAsset.php`
- `app/Models/MemberFormation.php`
- `app/Models/OutboxMessage.php`
- `app/Models/Permission.php`
- `app/Models/RallyAssignment.php`
- `app/Models/RallyGroup.php`
- `app/Models/RallyGuidanceRule.php`
- `app/Models/RecruitmentAnswer.php`
- `app/Models/RecruitmentApplicationInvite.php`
- `app/Models/RecruitmentCandidate.php`
- `app/Models/RecruitmentCandidateOnboarding.php`
- `app/Models/RecruitmentCommunication.php`
- `app/Models/RecruitmentDecisionTemplate.php`
- `app/Models/RecruitmentNote.php`
- `app/Models/RecruitmentOnboardingItem.php`
- `app/Models/RecruitmentQuestion.php`
- `app/Models/RecruitmentSetting.php`
- `app/Models/RecruitmentStageHistory.php`
- `app/Models/RecruitmentTag.php`
- `app/Models/Role.php`
- `app/Models/User.php`

## `Shared` inventory

- `app/Domain/Shared/Events/OutboxPublished.php`
- `app/Domain/Shared/Tenancy/TenantContextSnapshot.php`

## Rally-named code currently owned by Events

- `app/Application/Events/AssignRallyMember.php`
- `app/Application/Events/CreateRallyGroup.php`
- `app/Application/Events/CreateRallyGuidanceRule.php`
- `app/Application/Events/RecordRallyParticipation.php`
- `app/Domain/Events/Enums/RallyAssignmentRole.php`
- `app/Domain/Events/Enums/RallyAssignmentStatus.php`

## Cross-domain application/domain imports

- `app/Application/Content/ArchiveContentItem.php` (Content) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Content/ArchiveContentItem.php` (Content) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Content/ArchiveContentItem.php` (Content) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Content/ArchiveMediaAsset.php` (Content) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Content/ArchiveMediaAsset.php` (Content) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Content/ArchiveMediaAsset.php` (Content) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Content/DeleteContentCategory.php` (Content) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Content/DeleteContentCategory.php` (Content) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Content/DeleteContentCategory.php` (Content) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Content/PublishContentItem.php` (Content) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Content/PublishContentItem.php` (Content) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Content/PublishContentItem.php` (Content) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Content/PublishScheduledContent.php` (Content) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Content/RestoreContentRevision.php` (Content) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Content/RestoreContentRevision.php` (Content) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Content/RestoreContentRevision.php` (Content) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Content/SaveContentCategory.php` (Content) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Content/SaveContentCategory.php` (Content) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Content/SaveContentCategory.php` (Content) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Content/SaveContentItem.php` (Content) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Content/SaveContentItem.php` (Content) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Content/SaveContentItem.php` (Content) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Content/UpdateAlliancePublicProfile.php` (Content) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Content/UpdateAlliancePublicProfile.php` (Content) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Content/UpdateAlliancePublicProfile.php` (Content) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Content/UploadMediaAsset.php` (Content) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Content/UploadMediaAsset.php` (Content) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Content/UploadMediaAsset.php` (Content) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Events/AssignRallyMember.php` (Events) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Events/AssignRallyMember.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/AssignRallyMember.php` (Events) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Events/AssignRallyMember.php` (Events) -> `App\Domain\Identity\Enums\MembershipStatus`
- `app/Application/Events/CancelEventRegistration.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/CancelEventRegistration.php` (Events) -> `App\Domain\Identity\Enums\MembershipStatus`
- `app/Application/Events/CreateEvent.php` (Events) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Events/CreateEvent.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/CreateEvent.php` (Events) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Events/CreateEventRecommendedFormation.php` (Events) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Events/CreateEventRecommendedFormation.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/CreateEventRecommendedFormation.php` (Events) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Events/CreateEventReminderRule.php` (Events) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Events/CreateEventReminderRule.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/CreateEventReminderRule.php` (Events) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Events/CreateEventTemplate.php` (Events) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Events/CreateEventTemplate.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/CreateEventTemplate.php` (Events) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Events/CreateRallyGroup.php` (Events) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Events/CreateRallyGroup.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/CreateRallyGroup.php` (Events) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Events/CreateRallyGuidanceRule.php` (Events) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Events/CreateRallyGuidanceRule.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/CreateRallyGuidanceRule.php` (Events) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Events/RecordEventAttendance.php` (Events) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Events/RecordEventAttendance.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/RecordEventAttendance.php` (Events) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Events/RecordRallyParticipation.php` (Events) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Events/RecordRallyParticipation.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/RecordRallyParticipation.php` (Events) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Events/RegisterForEvent.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/RegisterForEvent.php` (Events) -> `App\Domain\Identity\Enums\MembershipStatus`
- `app/Application/Events/SaveMemberFormation.php` (Events) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Events/SaveMemberFormation.php` (Events) -> `App\Domain\Identity\Enums\MembershipStatus`
- `app/Application/Recruitment/AddRecruitmentNote.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/AddRecruitmentNote.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/AddRecruitmentNote.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/AddRecruitmentNote.php` (Recruitment) -> `App\Domain\Identity\Enums\MembershipStatus`
- `app/Application/Recruitment/AssignRecruitmentReviewer.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/AssignRecruitmentReviewer.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/AssignRecruitmentReviewer.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/AssignRecruitmentReviewer.php` (Recruitment) -> `App\Domain\Identity\Enums\MembershipStatus`
- `app/Application/Recruitment/ChangeRecruitmentStage.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/ChangeRecruitmentStage.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/ChangeRecruitmentStage.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/ConfigureRecruitmentSettings.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/ConfigureRecruitmentSettings.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/ConfigureRecruitmentSettings.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/ConvertAcceptedRecruitmentCandidate.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/ConvertAcceptedRecruitmentCandidate.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/ConvertAcceptedRecruitmentCandidate.php` (Recruitment) -> `App\Application\Identity\CreateInvitation`
- `app/Application/Recruitment/ConvertAcceptedRecruitmentCandidate.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/CreateRecruitmentDecisionTemplate.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/CreateRecruitmentDecisionTemplate.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/CreateRecruitmentDecisionTemplate.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/CreateRecruitmentOnboardingItem.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/CreateRecruitmentOnboardingItem.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/CreateRecruitmentOnboardingItem.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/CreateRecruitmentQuestion.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/CreateRecruitmentQuestion.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/CreateRecruitmentQuestion.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/IssueRecruitmentApplicationInvite.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/IssueRecruitmentApplicationInvite.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/IssueRecruitmentApplicationInvite.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/MarkRecruitmentCandidateJoined.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/MarkRecruitmentCommunicationSent.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/MarkRecruitmentCommunicationSent.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/MarkRecruitmentCommunicationSent.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/MergeRecruitmentCandidates.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/MergeRecruitmentCandidates.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/MergeRecruitmentCandidates.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/PrepareRecruitmentDecisionCommunication.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/PrepareRecruitmentDecisionCommunication.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/PrepareRecruitmentDecisionCommunication.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/PurgeExpiredRecruitmentCandidates.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/SubmitRecruitmentApplication.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/TagRecruitmentCandidate.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/TagRecruitmentCandidate.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/TagRecruitmentCandidate.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/UpdateRecruitmentOnboardingStatus.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/UpdateRecruitmentOnboardingStatus.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/UpdateRecruitmentOnboardingStatus.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`
- `app/Application/Recruitment/UpdateRecruitmentQuestion.php` (Recruitment) -> `App\Application\Identity\AllianceAuthorization`
- `app/Application/Recruitment/UpdateRecruitmentQuestion.php` (Recruitment) -> `App\Application\Identity\AuditRecorder`
- `app/Application/Recruitment/UpdateRecruitmentQuestion.php` (Recruitment) -> `App\Domain\Identity\Authorization\PermissionKey`

## Namespace counts

- `App\Application\Content`: 18
- `App\Application\Events`: 20
- `App\Application\Identity`: 20
- `App\Application\Operations`: 1
- `App\Application\Recruitment`: 25
- `App\Application\Shared`: 1
- `App\Domain\Content\Enums`: 5
- `App\Domain\Events`: 1
- `App\Domain\Events\Enums`: 7
- `App\Domain\Identity\Authorization`: 2
- `App\Domain\Identity\Enums`: 3
- `App\Domain\Recruitment\Enums`: 5
- `App\Domain\Shared\Events`: 1
- `App\Domain\Shared\Tenancy`: 1
- `App\Http\Controllers`: 7
- `App\Http\Controllers\Alliance`: 11
- `App\Http\Controllers\Auth`: 11
- `App\Http\Controllers\Health`: 1
- `App\Http\Middleware`: 5
- `App\Models`: 37
- `App\Providers`: 1

