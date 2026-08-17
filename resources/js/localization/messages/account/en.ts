import type { MessageCatalogue } from '../../types';

const messages = {
  accountExperience: {
    account: {
      eyebrow: 'Account command',
      title: 'Account & security',
      intro:
        'Manage your identity, verification, password, two-factor authentication, and active sessions.',
      passwordUpdated: 'Your password was updated and other authenticated sessions were revoked.',
      sessionsRevoked: 'Other authenticated sessions were signed out.',
      twoFactorDisabled: 'Two-factor authentication was disabled.',
      profileTitle: 'Profile',
      profileIntro: 'Changing your email address requires verification again.',
      timezone: 'Time zone',
      saveProfile: 'Save profile',
      emailVerification: 'Email verification',
      verified: 'Verified',
      pending: 'Pending',
      twoFactorState: 'Two-factor authentication',
      enabled: 'Enabled',
      setupPending: 'Setup pending',
      notEnabled: 'Not enabled',
      twoFactorTitle: 'Two-factor authentication',
      twoFactorIntro:
        'Protect sign-in with a TOTP authenticator. Recovery codes are shown only when created or regenerated.',
      startSetup: 'Start setup',
      authenticatorSecret: 'Authenticator secret',
      provisioningUri: 'Provisioning URI',
      authenticationCode: 'Authentication code',
      confirm: 'Confirm',
      saveRecoveryCodes: 'Save these recovery codes now',
      recoveryIntro: 'Each code works once. Store them somewhere separate from this account.',
      regenerateRecoveryCodes: 'Regenerate recovery codes',
      disableTwoFactor: 'Disable two-factor authentication',
      passwordTitle: 'Change password',
      passwordIntro:
        'Changing your password signs out other devices and closes other active access.',
      currentPassword: 'Current password',
      newPassword: 'New password',
      confirmNewPassword: 'Confirm new password',
      updatePassword: 'Update password',
      sessionsTitle: 'Other sessions',
      sessionsIntro: 'Revoke every authenticated session except this device.',
      signOutOthers: 'Sign out other devices',
      dangerTitle: 'Danger zone',
      deleteAccount: 'Account deletion',
    },
    deletion: {
      eyebrow: 'Governor record',
      title: 'Account deletion',
      intro:
        'A seven-day cooling-off period protects the realm record. Active Alliance leadership, Citadel Warden duty, or a legal hold can prevent the request from completing. Completed requests preserve the realm chronicle while removing personal account details.',
      currentRequest: 'Current request',
      status: 'Status',
      eligibleAt: 'Eligible at',
      requestedAt: 'Requested',
      processedAt: 'Processed',
      notYet: 'Not yet',
      requestTitle: 'Request deletion',
      requestIntro:
        'Transfer ownership of any alliance you own first. Records subject to legal hold or required for security and audit integrity are retained in pseudonymized form.',
      requestButton: 'Request account deletion',
      confirm:
        'Request account deletion? There is a seven-day cooling-off period and ownership/legal-hold checks apply.',
      requested: 'Your account deletion request was recorded.',
      backToAccount: 'Back to account & security',
    },
  },
} satisfies MessageCatalogue;

export default messages;
