import type { MessageCatalogue } from '../../types';

const messages = {
  common: {
    language: 'Язык',
    signIn: 'Войти',
    signOut: 'Выйти',
    createAccount: 'Создать аккаунт',
    continue: 'Продолжить',
    cancel: 'Отмена',
    save: 'Сохранить',
    close: 'Закрыть',
    loading: 'Загрузка',
    menu: 'Меню',
    openNavigation: 'Открыть навигацию',
    closeNavigation: 'Закрыть навигацию',
    playerAlliance: 'Альянс активного Губернатора',
    noPlayerAlliance: 'Активный Губернатор сейчас не состоит в Альянсе.',
    skipToContent: 'Перейти к содержимому',
  },
  navigation: {
    home: 'Главная',
    dashboard: 'Обзор Альянса',
    alliance: 'Альянс',
    events: 'События',
    roster: 'Участники Альянса',
    recruitment: 'Набор',
    content: 'Доска объявлений',
    contributions: 'Вклад в Альянс',
    kingdom: 'Альянсы Королевства',
    transfers: 'Перенос Королевства',
    integrations: 'Подключения',
    profile: 'Аккаунт Губернатора',
    settings: 'Настройки',
    allianceOperations: 'Альянс',
    kingdomOperations: 'Королевство',
    account: 'Аккаунт Губернатора',
  },
  application: {
    dashboard: {
      title: 'Обзор Альянса',
      eyebrow: 'Ваш Альянс',
      welcome: 'Добро пожаловать, Губернатор {name}',
      verificationPending: 'Ожидается подтверждение электронной почты',
      playerContextTitle: 'Активный Губернатор',
      playerContextIntro:
        'Смените Губернатора, чтобы изменить личность Kingshot, используемую для действий Альянса и Королевства.',
      playerKingdom: 'Королевство #{kingdom}',
      playerAuthorityIntro:
        'Ранг Альянса, роли, обязанности в Королевстве и доступ к Событиям следуют за активным Губернатором.',
      selectPlayer: 'Выбрать Губернатора',
      playerAllianceTitle: 'Альянс активного Губернатора',
      playerAllianceIntro: 'Доступ к Альянсу зависит от ранга и ролей активного Губернатора.',
      noPlayerAllianceTitle: 'Этот Губернатор не состоит в Альянсе',
      noPlayerAllianceIntro:
        'Смените Губернатора, вступите в Альянс или создайте Альянс, чтобы использовать функции Альянса.',
      openPlayerAlliance: 'Открыть Альянс',
      active: 'Активный',
      roles: 'Роли Альянса',
      roster: 'Участники Альянса',
      kingdomAlliances: 'Альянсы Королевства',
      transfers: 'Перенос Королевства',
      kingdomSettings: 'Настройки Королевства',
      createTitle: 'Создать Альянс',
      createIntro:
        'Создайте Альянс для активного Губернатора. Альянс использует Королевство этого Губернатора, а основавший его Губернатор становится R5.',
      allianceName: 'Название Альянса',
      timezone: 'Часовой пояс Альянса',
      create: 'Создать Альянс',
    },
  },
} satisfies MessageCatalogue;

export default messages;
