import type { MessageCatalogue } from '../../types';

const messages = {
  debrief: {
    title: 'Ayı Avı Değerlendirmesi',
    eyebrow: 'Ayı Avı · Etkinlik sonrası',
    subtitle:
      'Kaydedilen hasarı, katılımı, Rally performansını, eşleşmemiş Valileri ve son avlarla karşılaştırmayı inceleyin.',
    totalDamage: 'Toplam hasar',
    governors: 'Valiler',
    governor: 'Vali',
    governorCount: '{count} Vali',
    attendance: 'Katılım',
    recordedRallies: 'Kaydedilen Rally',
    notRecorded: 'Kaydedilmedi',
    notComparable: 'Karşılaştırma yok',
    noChange: 'Önceki ava göre değişiklik yok',
    increased: 'arttı',
    decreased: 'azaldı',
    changeWithPercent: 'önceki ava göre {amount} ({percent}%) {direction}',
    change: 'önceki ava göre {amount} {direction}',
    rankUp: 'Önceki ava göre {count} sıra yükseldi',
    rankDown: 'Önceki ava göre {count} sıra geriledi',
    yourHunt: 'Senin Avın',
    damage: 'Hasar',
    rank: 'Sıra',
    alliancePerformance: 'İttifak performansı',
    leaderboard: 'Vali sıralaması',
    reportCount: '{count} kayıtlı savaş raporu',
    unknownGovernor: 'Bilinmeyen Vali',
    noResults: 'Bu av için henüz Vali hasarı kaydedilmedi.',
    needsReview: 'İnceleme gerekli',
    unmatchedGovernors: '{count} Vali eşleştirilmeli',
    reviewHelp:
      'Vali eşleştirmesini Screenshot Intake içinde tamamlayın. Değerlendirme ikinci bir kimlik akışı oluşturmaz.',
    reviewImport: 'İçe aktarılan raporu incele',
    trends: 'Eğilimler',
    runTrends: 'Son Ayı Avı eğilimleri',
    yourDamageTrend: 'Av başına hasarın',
    allianceDamageTrend: 'Av başına İttifak hasarı',
    previousHunt: 'Önceki av',
    noPrevious: 'Önceki av yok',
    noPreviousHelp:
      'Bu İttifak için tamamlanmış daha eski bir Ayı Avı olduğunda karşılaştırma görünür.',
    history: 'Geçmiş',
    runHistory: 'Ayı Avı geçmişi',
  },
} satisfies MessageCatalogue;

export default messages;
