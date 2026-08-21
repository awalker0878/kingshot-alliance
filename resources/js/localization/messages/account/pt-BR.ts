import type { MessageCatalogue } from '../../types';

const messages = {
  notifications: {
    types: {
      kingPerkReminder: 'Lembretes de Vantagens de Posição',
    },
  },
  accountExperience: {
    account: {
      eyebrow: 'Conta',
      title: 'Conta e segurança',
      intro:
        'Gerencie sua identidade, verificação, senha, autenticação em dois fatores e sessões ativas.',
      passwordUpdated: 'Sua senha foi atualizada e as outras sessões autenticadas foram encerradas.',
      sessionsRevoked: 'As outras sessões autenticadas foram encerradas.',
      twoFactorDisabled: 'A autenticação em dois fatores foi desativada.',
      profileTitle: 'Perfil',
      profileIntro: 'Alterar seu e-mail exige uma nova verificação.',
      timezone: 'Fuso horário',
      saveProfile: 'Salvar perfil',
      emailVerification: 'Verificação de e-mail',
      verified: 'Verificado',
      pending: 'Pendente',
      twoFactorState: 'Autenticação em dois fatores',
      enabled: 'Ativada',
      setupPending: 'Configuração pendente',
      notEnabled: 'Não ativada',
      twoFactorTitle: 'Autenticação em dois fatores',
      twoFactorIntro:
        'Proteja o login com um aplicativo autenticador. Os códigos de recuperação só aparecem quando são criados ou regenerados.',
      startSetup: 'Iniciar configuração',
      authenticatorSecret: 'Segredo do autenticador',
      provisioningUri: 'URI de provisionamento',
      authenticationCode: 'Código de autenticação',
      confirm: 'Confirmar',
      saveRecoveryCodes: 'Salve estes códigos de recuperação agora',
      recoveryIntro: 'Cada código funciona uma vez. Guarde-os em um local separado desta conta.',
      regenerateRecoveryCodes: 'Regenerar códigos de recuperação',
      disableTwoFactor: 'Desativar autenticação em dois fatores',
      passwordTitle: 'Alterar senha',
      passwordIntro:
        'Alterar sua senha encerra a sessão em outros dispositivos e fecha outros acessos ativos.',
      currentPassword: 'Senha atual',
      newPassword: 'Nova senha',
      confirmNewPassword: 'Confirmar nova senha',
      updatePassword: 'Atualizar senha',
      sessionsTitle: 'Outras sessões',
      sessionsIntro: 'Encerre todas as sessões autenticadas, exceto este dispositivo.',
      signOutOthers: 'Sair dos outros dispositivos',
      dangerTitle: 'Zona de risco',
      deleteAccount: 'Exclusão da conta',
    },
    deletion: {
      eyebrow: 'Ciclo de vida da conta',
      title: 'Exclusão da conta',
      intro:
        'A exclusão tem um período de espera de sete dias. Propriedade ativa de aliança, acesso de administrador da plataforma e retenções legais podem bloquear o processamento. Contas processadas são anonimizadas em vez de remover o histórico de auditoria.',
      currentRequest: 'Solicitação atual',
      eligibleAt: 'Elegível em',
      requestedAt: 'Solicitado em',
      processedAt: 'Processado em',
      notYet: 'Ainda não',
      requestTitle: 'Solicitar exclusão',
      requestIntro:
        'Transfira primeiro a propriedade de qualquer aliança. Registros sujeitos a retenção legal ou necessários para segurança e auditoria permanecem pseudonimizados.',
      requestButton: 'Solicitar exclusão da conta',
      confirm:
        'Solicitar exclusão da conta? Há um período de espera de sete dias e verificações de propriedade e retenção legal.',
      requested: 'Sua solicitação de exclusão da conta foi registrada.',
      backToAccount: 'Voltar para conta e segurança',
    },
  },
} satisfies MessageCatalogue;

export default messages;
