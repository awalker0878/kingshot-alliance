import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
    searchChoices: 'Seçenek ara',
    choicePageSummary:
      'Bu sayfada {count} seçenek; toplam {total} eşleşme. Sabit kayıt kimliğine göre sıralanır.',
    choiceUnavailable: 'Seçilen seçenek artık kullanılamıyor.',
    choicesFailed: 'Seçenekler yüklenemedi. Seçiminiz ve görüntülenen sayfa değişmedi.',
    retryChoices: 'Yeniden dene',

    participantPagination: 'Katılımcı sayfaları',
    participantPageSummary: 'Bu sayfada {count}, bu görünümde toplam {total} katılımcı var.',
    participantPageOrder:
      'Sabit kayıt kimliğine göre sıralanır. Toplamlar görünümün tamamını kapsar.',
    participantPageUnavailable: 'Katılımcı sayfası yüklenemedi. Görüntülenen sayfa değişmedi.',
    retryParticipantPage: 'Sayfayı yeniden dene',
    participantFilterPageOnly:
      'Bu uygunluk filtresi mevcut sayfaya uygulanır. Tüm katılımcıları incelemek için sayfalarda ilerleyin.',
    workflowHistoryUnavailable: 'Transfer süreci geçmişi yüklenemedi.',
    workflowHistoryTotal: 'Bu geçmişte {count} kayıt var.',
    observationHistoryUnavailable: 'Gözlem geçmişi yüklenemedi.',
    reloadHistory: 'Geçmişi yeniden yükle',
    eyebrow: 'Krallık Transferi',
    title: 'Transfer planlaması',
    readinessBoard: 'Hazırlık',
    completion: 'Sonuç',
    manageTransfers: 'Transferleri yönet',
    currentCycle: 'Geçerli döngü',
    participants: 'Katılımcılar',
    incoming: 'Gelen',
    outgoing: 'Giden',
    staying: 'Kalan',
    transferGroups: 'Transfer grupları',
    player: 'Vali',
    gamePlayerId: 'Vali oyun kimliği',
    readinessTitle: 'Transfer hazırlığı',
    completionTitle: 'Transfer sonucu',
    recordCompletion: 'Transfer sonucunu kaydet',
    rosterHandoffRecorded: 'İttifak kadrosu güncellendi',
    completedStatus: 'Tamamlandı',
    notCompletedStatus: 'Tamamlanmadı',
    readinessReady: 'Hazır',
    readinessBlocked: 'Engelli',
    readinessConfirmed: 'Onaylandı',
  },
} satisfies MessageCatalogue;

export default messages;
