import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Sign in',
      email: 'Email',
      password: 'Password',
      remember: 'Remember me',
      forgotPassword: 'Forgot password?',
      submit: 'Sign in',
      createAccount: 'Create account',
      invitation: 'Have an invitation?',
    },
    register: {
      title: 'Create account',
      name: 'Name',
      email: 'Email',
      password: 'Password',
      passwordConfirmation: 'Confirm password',
      submit: 'Create account',
      existingAccount: 'Already have an account?',
    },
    password: {
      forgotTitle: 'Recover your account',
      forgotDescription: 'Enter the email associated with your Kingshot Alliance account.',
      sendResetLink: 'Send recovery instructions',
      resetTitle: 'Set a new password',
      resetSubmit: 'Reset password',
      confirmTitle: "Confirm it's you",
    },
    verification: {
      title: 'Verify your email address',
      resend: 'Resend verification email',
    },
    twoFactor: {
      title: 'Two-factor authentication',
      code: 'Authenticator code',
      recoveryCode: 'Recovery code',
      submit: 'Continue',
    },
    invitation: {
      title: 'Alliance invitation',
      accept: 'Accept invitation',
    },
  },
  authExperience: {
    social: {
      continueWithGoogle: 'Continue with Google',
      createWithGoogle: 'Create Kingshot Alliance account with Google',
      or: 'or',
    },
    passkeys: {
      signIn: 'Sign in with a passkey',
      authenticating: 'Checking passkey…',
    },
    confirm: {
      title: "Confirm it's you",
      description:
        'Use one of your attached sign-in methods before continuing with this sensitive account change.',
      usePassword: 'Confirm with password',
      useGoogle: 'Confirm with Google',
      usePasskey: 'Confirm with a passkey',
    },
    shell: {
      headline: 'Built for Alliance leadership.',
      intro:
        'Recruit Governors, prepare Events, manage your Alliance, and keep track of the Kingdom in one place.',
    },
    login: {
      intro:
        'Sign in with any method attached to your Kingshot Alliance account, then choose the Governor you want to use.',
      invitationNotice:
        'Sign in with the invited account to continue accepting your Alliance invitation.',
      needAccount: 'Need an account?',
      register: 'Register',
    },
    register: {
      intro:
        'Create one Kingshot Alliance account. You can attach more sign-in methods later from Security settings.',
      invitationNotice:
        'You were invited to {alliance} as {email}. Creating your account will also accept this invitation.',
      invitationOnly:
        'Registration is currently invitation-only. Open the invitation link sent by your Alliance.',
      timezone: 'Time zone',
      passwordHint: 'At least 12 characters with mixed case and a number.',
      existingAccount: 'Already have an account?',
    },
    invitation: {
      join: 'Join {alliance}',
      forEmail: 'This invitation is for {email}.',
      expires: 'Expires {date}',
      wrongAccount:
        'You are signed in as {email}. Sign in with the invited email address to accept this invitation.',
      createAndJoin: 'Create account and join',
      signInAccept: 'Sign in to accept',
    },
    password: {
      backToSignIn: 'Back to sign in',
      recoveryKicker: 'ACCOUNT RECOVERY',
      recoveryNeutral:
        'If an eligible password credential exists for that email, we will send secure password-reset instructions.',
      checkInboxKicker: 'CHECK YOUR INBOX',
      checkInboxHeading: 'Recovery instructions requested',
      checkInboxDescription:
        'If an eligible password credential exists for the address you entered, password-reset instructions have been sent. For security, we cannot confirm whether an account exists or which methods it uses.',
      resetKicker: 'SECURE PASSWORD RESET',
      resetIntro: 'Choose a new Kingshot Alliance password for the account below.',
      accountEmail: 'Account email',
      requirements: 'Your password must include:',
      requirementLength: 'At least 12 characters',
      requirementUpper: 'An uppercase letter',
      requirementLower: 'A lowercase letter',
      requirementNumber: 'A number',
      showPassword: 'Show password',
      hidePassword: 'Hide password',
      validationSummary: 'Review the highlighted fields before continuing.',
      newPassword: 'New password',
      confirmNewPassword: 'Confirm new password',
      confirmDescription:
        'This changes protected Alliance access, so confirm your identity before continuing.',
    },
    verification: {
      kicker: 'EMAIL VERIFICATION',
      description: 'We sent a secure verification link for your Kingshot Alliance account to:',
      instructions: 'Open the message and follow the link to finish verifying your email address.',
      securityNote:
        'Kingshot Alliance will never ask for your password through an email verification message.',
      sent: 'A fresh verification link has been sent.',
      resendIn: 'You can resend in {seconds}s',
      useAnotherAccount: 'Sign out and use another account',
    },
    twoFactor: {
      kicker: 'Account security',
      description: 'Enter the current six-digit code from your authenticator app.',
      verifyCode: 'Verify code',
      useRecoveryCode: 'Use recovery code',
    },
  },
} satisfies MessageCatalogue;

export default messages;
