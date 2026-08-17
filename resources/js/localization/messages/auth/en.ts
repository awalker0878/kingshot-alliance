import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Enter the Realm',
      email: 'Email',
      password: 'Password',
      remember: 'Remember me',
      forgotPassword: 'Forgot password?',
      submit: 'Enter the Realm',
      createAccount: 'Begin as a Governor',
      invitation: 'Have an invitation?',
    },
    register: {
      title: 'Begin as a Governor',
      name: 'Name',
      email: 'Email',
      password: 'Password',
      passwordConfirmation: 'Confirm password',
      submit: 'Begin as a Governor',
      existingAccount: 'Already have an account?',
    },
    password: {
      forgotTitle: 'Reset your password',
      forgotDescription: 'Enter your email address and we will send you a password reset link.',
      sendResetLink: 'Send password reset link',
      resetTitle: 'Choose a new password',
      resetSubmit: 'Reset password',
      confirmTitle: 'Confirm your password',
    },
    verification: {
      title: 'Verify your email',
      resend: 'Resend verification email',
    },
    twoFactor: {
      title: 'Two-factor authentication',
      code: 'Guard code',
      recoveryCode: 'Recovery code',
      submit: 'Continue',
    },
    invitation: {
      title: 'Alliance Summons',
      accept: 'Accept invitation',
    },
  },
  authExperience: {
    shell: {
      headline: 'Built for Alliance leadership.',
      intro:
        'Enter the protected command rooms your Alliance uses to recruit Governors, prepare Events, and keep watch on the Kingdom.',
    },
    login: {
      intro: 'Enter with your account, then choose the Governor you want to command as.',
      invitationNotice:
        'Enter the Realm with the invited account to continue accepting your alliance invitation.',
      needAccount: 'Need an account?',
      register: 'Register',
    },
    register: {
      intro: 'One account may own several Governors. Alliance rank and Kingdom duty always follow the Governor you choose.',
      invitationNotice:
        'You were invited to {alliance} as {email}. Creating your account will also accept this invitation.',
      invitationOnly:
        'Registration is currently invitation-only. Open the invitation link sent by your alliance.',
      timezone: 'Time zone',
      passwordHint: 'At least 12 characters with mixed case and a number.',
      existingAccount: 'Already have an account?',
    },
    invitation: {
      join: 'Answer {alliance}’s summons',
      forEmail: 'This invitation is for {email}.',
      expires: 'Expires {date}',
      wrongAccount:
        'You are signed in as {email}. Enter the Realm with the invited email address to accept this invitation.',
      createAndJoin: 'Begin as a Governor and join',
      signInAccept: 'Enter the Realm to accept',
    },
    password: {
      backToSignIn: 'Back to sign in',
      resetIntro: 'Resetting your password revokes personal access tokens.',
      newPassword: 'New password',
      confirmNewPassword: 'Confirm new password',
      confirmDescription:
        'This order changes protected Alliance access, so confirm your realm key before continuing.',
    },
    verification: {
      description:
        'We sent a verification link to {email}. Verify the address before performing protected account actions.',
      sent: 'A fresh verification link has been sent.',
    },
    twoFactor: {
      kicker: 'Realm guard',
      description: 'Enter the current six-digit code from your authenticator app.',
      verifyCode: 'Verify code',
      useRecoveryCode: 'Use recovery code',
    },
  },
} satisfies MessageCatalogue;

export default messages;
