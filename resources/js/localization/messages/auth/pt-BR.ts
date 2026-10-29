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
      forgotTitle: 'Recuperar sua conta',
      forgotDescription: 'Digite o endereço de e-mail associado à sua conta Kingshot Alliance.',
      sendResetLink: 'Enviar instruções de recuperação',
      resetTitle: 'Escolha uma nova senha',
      resetSubmit: 'Redefinir senha',
      confirmTitle: 'Confirme sua identidade',
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
        'Recrute Governadores, prepare Eventos, gerencie sua Aliança e acompanhe o Reino em um só lugar.',
    },
    login: {
      intro: 'Entre na sua conta e escolha o Governador que deseja usar.',
      invitationNotice:
        'Entre com a conta convidada para continuar aceitando o convite da aliança.',
      needAccount: 'Precisa de uma conta?',
      register: 'Criar conta',
    },
    register: {
      intro:
        'Uma conta pode ter vários Governadores. O posto na Aliança e as funções no Reino sempre correspondem ao Governador que você escolher.',
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
      resetIntro: 'Escolha uma nova senha do Kingshot Alliance para a conta abaixo.',
      newPassword: 'Nova senha',
      confirmNewPassword: 'Confirmar nova senha',
      confirmDescription:
        'Esta ação altera o acesso protegido à Aliança. Confirme sua identidade antes de continuar.',
      recoveryKicker: 'RECUPERAÇÃO DE CONTA',
      recoveryNeutral:
        'Se a recuperação de senha estiver disponível para esse endereço de e-mail, enviaremos instruções seguras para redefini-la.',
      checkInboxKicker: 'VERIFIQUE SUA CAIXA DE ENTRADA',
      checkInboxHeading: 'Instruções de recuperação solicitadas',
      checkInboxDescription:
        'Se a recuperação de senha estiver disponível para o endereço informado, as instruções para redefini-la foram enviadas. Por segurança, não podemos confirmar se uma conta existe nem quais métodos de acesso ela usa.',
      resetKicker: 'REDEFINIÇÃO SEGURA DE SENHA',
      accountEmail: 'Email da conta',
      requirements: 'Sua senha deve incluir:',
      requirementLength: 'Pelo menos 12 caracteres',
      requirementUpper: 'Uma letra maiúscula',
      requirementLower: 'Uma letra minúscula',
      requirementNumber: 'Um dígito',
      showPassword: 'Mostrar senha',
      hidePassword: 'Esconder a senha',
      validationSummary: 'Reveja os campos destacados antes de continuar.',
    },
    verification: {
      description: 'Enviamos um link seguro de verificação da sua conta Kingshot Alliance para:',
      sent: 'Um novo link de verificação foi enviado.',
      kicker: 'VERIFICAÇÃO DE E-MAIL',
      instructions:
        'Abra a mensagem e siga o link para concluir a verificação do seu endereço de e-mail.',
      securityNote:
        'O Kingshot Alliance nunca pedirá sua senha em uma mensagem de verificação de e-mail.',
      resendIn: 'Você poderá reenviar em {seconds} segundos',
      useAnotherAccount: 'Sair e usar outra conta',
    },
    twoFactor: {
      kicker: 'Segurança da conta',
      description: 'Digite o código atual de seis dígitos do seu aplicativo autenticador.',
      verifyCode: 'Verificar código',
      useRecoveryCode: 'Usar código de recuperação',
    },
    social: {
      continueWithGoogle: 'Continuar com o Google',
      createWithGoogle: 'Criar conta Kingshot Alliance com o Google',
      or: 'ou',
    },
    passkeys: {
      signIn: 'Entrar com uma chave de acesso',
      authenticating: 'Verificando a chave de acesso…',
    },
    confirm: {
      title: 'Confirme sua identidade',
      description:
        'Use um dos métodos de acesso vinculados à sua conta antes de continuar com esta alteração sensível na conta.',
      usePassword: 'Confirmar com senha',
      useGoogle: 'Confirmar com Google',
      usePasskey: 'Confirmar com uma chave de acesso',
    },
  },
} satisfies MessageCatalogue;

export default messages;
