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
      code: 'Authentication code',
      recoveryCode: 'Recovery code',
      submit: 'Continue',
    },
    invitation: {
      title: 'Alliance invitation',
      accept: 'Accept invitation',
    },
  },
  authExperience: {
    shell: {
      headline: 'Built for alliance leaders.',
      intro:
        'Secure access to the tools your alliance uses to coordinate, recruit, and prepare for what comes next.',
    },
    login: {
      intro: 'Access every alliance linked to your global account.',
      invitationNotice:
        'Sign in with the invited account to continue accepting your alliance invitation.',
      needAccount: 'Need an account?',
      register: 'Register',
    },
    register: {
      intro: 'One global identity can belong to multiple alliances.',
      invitationNotice:
        'You were invited to {alliance} as {email}. Creating your account will also accept this invitation.',
      invitationOnly:
        'Registration is currently invitation-only. Open the invitation link sent by your alliance.',
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
      resetIntro: 'Resetting your password revokes personal access tokens.',
      newPassword: 'New password',
      confirmNewPassword: 'Confirm new password',
      confirmDescription:
        'This action changes alliance access or permissions, so your password must be reconfirmed.',
    },
    verification: {
      description:
        'We sent a verification link to {email}. Verify the address before performing protected account actions.',
      sent: 'A fresh verification link has been sent.',
    },
    twoFactor: {
      kicker: 'Security check',
      description: 'Enter the current six-digit code from your authenticator app.',
      verifyCode: 'Verify code',
      useRecoveryCode: 'Use recovery code',
    },
  },
} satisfies MessageCatalogue;

export default messages;
