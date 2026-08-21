import type { MessageCatalogue } from '../../types';

const messages = {
  kingdomP7D: {
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
