import type { MessageCatalogue } from '../../types';

const messages = {
  auth: {
    login: {
      title: 'Entrar',
      email: 'E-mail',
      password: 'Senha',
      remember: 'Lembrar de mim',
      forgotPassword: 'Esqueceu a senha?',
      submit: 'Entrar',
      createAccount: 'Criar conta',
      invitation: 'Tem um convite?',
    },
    register: {
      title: 'Criar conta',
      name: 'Nome',
      email: 'E-mail',
      password: 'Senha',
      passwordConfirmation: 'Confirmar senha',
      submit: 'Criar conta',
      existingAccount: 'Já tem uma conta?',
    },
    password: {
      forgotTitle: 'Redefinir sua senha',
      forgotDescription: 'Digite seu e-mail e enviaremos um link para redefinir sua senha.',
      sendResetLink: 'Enviar link de redefinição',
      resetTitle: 'Escolha uma nova senha',
      resetSubmit: 'Redefinir senha',
      confirmTitle: 'Confirmar sua senha',
    },
    verification: {
      title: 'Verifique seu e-mail',
      resend: 'Reenviar e-mail de verificação',
    },
    twoFactor: {
      title: 'Autenticação de dois fatores',
      code: 'Código de autenticação',
      recoveryCode: 'Código de recuperação',
      submit: 'Continuar',
    },
    invitation: {
      title: 'Convite da aliança',
      accept: 'Aceitar convite',
    },
  },
  authExperience: {
    shell: {
      headline: 'Feito para líderes de aliança.',
      intro:
        'Acesso seguro às ferramentas que sua aliança usa para coordenar, recrutar e se preparar para o que vem a seguir.',
    },
    login: {
      intro: 'Acesse todas as alianças vinculadas à sua conta global.',
      invitationNotice:
        'Entre com a conta convidada para continuar aceitando o convite da aliança.',
      needAccount: 'Precisa de uma conta?',
      register: 'Criar conta',
    },
    register: {
      intro: 'Uma identidade global pode pertencer a várias alianças.',
      invitationNotice:
        'Você foi convidado para {alliance} como {email}. Criar sua conta também aceitará este convite.',
      invitationOnly:
        'O cadastro está disponível apenas por convite. Abra o link enviado pela sua aliança.',
      timezone: 'Fuso horário',
      passwordHint: 'Pelo menos 12 caracteres com maiúsculas, minúsculas e um número.',
      existingAccount: 'Já tem uma conta?',
    },
    invitation: {
      join: 'Entrar em {alliance}',
      forEmail: 'Este convite é para {email}.',
      expires: 'Expira em {date}',
      wrongAccount:
        'Você está conectado como {email}. Entre com o e-mail convidado para aceitar este convite.',
      createAndJoin: 'Criar conta e entrar',
      signInAccept: 'Entrar para aceitar',
    },
    password: {
      backToSignIn: 'Voltar para entrar',
      resetIntro: 'Redefinir sua senha revoga tokens de acesso pessoais.',
      newPassword: 'Nova senha',
      confirmNewPassword: 'Confirmar nova senha',
      confirmDescription:
        'Esta ação altera acesso ou permissões da aliança, então sua senha precisa ser confirmada novamente.',
      recoveryKicker: 'RECUPERAÇÃO DE CONTA',
      recoveryNeutral:
        'Se existir uma credencial de senha elegível para esse e-mail, enviaremos instruções seguras de redefinição de senha.',
      checkInboxKicker: 'VERIFIQUE A SUA CAIXA DE Entrada',
      checkInboxHeading: 'Instruções de recuperação solicitadas',
      checkInboxDescription:
        'Se existir uma credencial de senha elegível para o endereço que você inseriu, as instruções de redefinição de senha foram enviadas. Por segurança, não podemos confirmar se uma conta existe ou quais métodos ela usa.',
      resetKicker: 'SECURE Password RESET',
      accountEmail: 'Email da conta',
      requirements: 'Sua senha deve incluir:',
      requirementLength: 'Pelo menos 12 caracteres',
      requirementUpper: 'Uma letra maiúscula',
      requirementLower: 'Uma letra minúscula',
      requirementNumber: 'Um número',
      showPassword: 'Mostrar senha',
      hidePassword: 'Esconder a senha',
      validationSummary: 'Reveja os campos destacados antes de continuar.',
    },
    verification: {
      description:
        'Enviamos um link de verificação para o endereço de e-mail abaixo. Verifique o endereço antes de ações protegidas da conta.',
      sent: 'Um novo link de verificação foi enviado.',
      kicker: 'VERIFICAÇÃO DE EMAIL',
      instructions:
        'Abra a mensagem e siga o link para concluir a verificação do seu endereço de e-mail.',
      securityNote:
        'Kingshot Alliance nunca pedirá sua senha através de uma mensagem de verificação por e-mail.',
      resendIn: 'Você pode reenviar em {seconds}s',
      useAnotherAccount: 'Sair e usar outra conta',
    },
    twoFactor: {
      kicker: 'Verificação de segurança',
      description: 'Digite o código atual de seis dígitos do seu aplicativo autenticador.',
      verifyCode: 'Verificar código',
      useRecoveryCode: 'Usar código de recuperação',
    },
    social: {
      continueWithGoogle: 'Continue com Google',
      createWithGoogle: 'Criar conta Kingshot Alliance com Google',
      or: 'ou',
    },
    passkeys: {
      signIn: 'Entrar com uma chave de acesso',
      authenticating: 'Verificando a chave de acesso…',
    },
    confirm: {
      title: 'Confirmar sua senha',
      description:
        'Use um dos métodos de login anexados antes de continuar com essa alteração de conta confidencial.',
      usePassword: 'Confirmar com senha',
      useGoogle: 'Confirmar com Google',
      usePasskey: 'Confirmar com uma chave de acesso',
    },
  },
} satisfies MessageCatalogue;

export default messages;
