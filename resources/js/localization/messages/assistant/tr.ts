import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Asistan',
    title: 'İttifakına Sor',
    eyebrow: 'İttifak Asistanı · Yetkili yanıtlar',
    subtitle:
      'Etkinlikler, kendi roster ve RSVP durumun, görevlerin, İttifak rehberleri ve gözlemleri, yayımlanmış bölge planları, kaynaklı oyun verileri ve kapsam içindeki transfer hazırlığın hakkında sor.',
    authorizationHint: 'Yanıtlar yalnızca görmeye yetkili olduğun verileri kullanır.',
    tryAsking: 'Bir soru dene',
    conversation: 'İttifak Asistanı konuşması',
    youAsked: 'Sen sordun',
    possibleEvents: 'Olası Etkinlikler',
    openEvent: 'Etkinliği Aç',
    sourcesHeading: 'Kullanılan kaynaklar',
    sourceTime: 'Kaynak zamanı: {time}',
    questionLabel: 'İttifakına sor',
    questionPlaceholder: 'Swordland saat kaçta ve rosterda mıyım?',
    inputHint: '{count}/{max} karakter · Sormak için Enter · Yeni satır için Shift+Enter',
    asking: 'Kaynaklar kontrol ediliyor…',
    ask: 'Sor',
    notRecorded: 'Kaydedilmemiş',
    classifications: {
      operational_fact: 'Operasyonel gerçek',
      game_fact: 'Oyun verisi',
      alliance_strategy: 'İttifak stratejisi',
      observation: 'Gözlem',
      alliance_command: 'Görevli genel bakış',
      event_readiness: 'hazırlık',
      bear_hunt_run: 'Ayı avcılığı koşuyor',
      roster_freshness: 'Vali gözlem tazeliği',
      transfer_verification: 'Transfer doğrulama',
      territory_comparison: 'Bölgesel karşılaştırma',
    },
    sources: {
      event: 'Etkinlik',
      roster: 'Üye listesi',
      alliance_content: 'İttifak rehberi',
      observation: 'Gözlem',
      game_fact: 'Oyun verisi',
      participation: 'Katılım',
      battle_plan_assignment: 'Savaş planı görevi',
      transfer_assessment: 'Transfer değerlendirmesi',
      territory_plan_revision: 'Yayımlanmış bölge planı',
      alliance_command: 'Görevli genel bakış',
      event_readiness: 'hazırlık',
      bear_hunt_run: 'Ayı avcılığı koşuyor',
      roster_freshness: 'Vali gözlem tazeliği',
      transfer_verification: 'Transfer doğrulama',
      territory_comparison: 'Bölgesel karşılaştırma',
      alliance_settings: 'ittifak ayarları',
      alliance_governance_history: 'İttifak yönetimi tarihi',
      alliance_roster_reconciliation: 'İttifak listesi uzlaşması',
    },
    prompts: {
      swordland: 'Swordland saat kaçta ve rosterda mıyım?',
      nextEvent: 'Sıradaki Etkinliğim ne?',
      bearGuide: 'Bear Hunt rehberimiz ne diyor?',
      observation: 'Rakibimiz hakkında ne gözlemledik?',
      heroFact: 'Amadeus kaçıncı nesil?',
      rsvpWeek: 'Bu hafta hangi etkinliklere RSVP verdim?',
      battleAssignment: 'Swordland görevim nedir?',
      transferStatus: 'Transfer için neyim eksik?',
      territoryPlan: 'Bear Hunt için hangi hive düzenini kullanıyoruz?',
      allianceCommand: 'Memur dikkatini neye çekti?',
      eventReadiness: 'Bir sonraki doğrulanmış olayımız hazır mı?',
      rallyGaps: 'Bir sonraki etkinlik için ne kadar Rally boşluğu var?',
      bearHuntHistory: 'Ayı Avcılığı performansımızı göster.',
      progressionFreshness: 'Hangi vali gözlemleri eskimiş veya eksik?',
      transferVerification: 'Hangi Transfer katılımcılarının doğrulanması gerekiyor?',
      intelligenceChanges: 'Son zamanlarda istihbarat ne değişti?',
      territoryComparison: 'Gözlemlenen bölge yayınlanan planla nasıl karşılaştırılır?',
    },
    answers: {
      transferVerificationIncomplete:
        'Bu özet, {total} transfer katılımcısının {assessed} tanesini değerlendirdi. En az {count} kişi dikkat gerektiriyor; {unassessed} kişi burada değerlendirilmedi. Güncel kontroller için transfer hazırlığını açın.',
      help: 'Yetkili etkinliklerden, kendi roster ve RSVP durumundan, görevlerinden, İttifak rehberleri ve gözlemlerinden, yayımlanmış bölge planlarından, kaynaklı oyun verilerinden ve kapsam içindeki transfer durumundan yanıt verebilirim. Kaynaksız KingShot bilgisi kullanmam.',
      unsupported:
        'Yalnızca desteklenen yetkili kaynaklardan yanıt verebilirim. Genel KingShot bilgisi kullanmam ve Assistant üzerinden değişiklik yapmam.',
      unavailable: 'İttifak Asistanı şu anda kaynaklarını kontrol edemiyor. Tekrar dene.',
      rateLimited: 'Çok hızlı soru soruyorsun. Kısa süre sonra tekrar dene.',
      validationError: '2 ile {max} karakter arasında bir soru gir.',
      noUpcomingEvent: 'Görmeye yetkili olduğun yaklaşan bir Etkinlik bulamadım.',
      eventSubjectMissing: 'Kontrol etmemi istediğin Etkinliği belirt.',
      eventNotFound: '“{subject}” ile eşleşen yetkili yaklaşan bir Etkinlik bulamadım.',
      eventAmbiguous:
        '“{subject}” ile eşleşen birden fazla Etkinlik buldum. Aşağıdan istediğini aç.',
      eventTime: '{event} {startsAt} tarihinde başlıyor.',
      eventTimeNotRostered: '{event} {startsAt} tarihinde başlıyor. Şu anda rosterda değilsin.',
      notRostered: 'Şu anda {event} rosterında değilsin.',
      eventTimeRostered:
        '{event} {startsAt} tarihinde başlıyor. {roster} içindesin. Rol: {role}; slot: {slot}; durum: {status}.',
      rostered: '{event} için {roster} içindesin. Rol: {role}; slot: {slot}; durum: {status}.',
      contentSubjectMissing:
        'Kontrol etmemi istediğin İttifak rehberinin Etkinliğini veya konusunu belirt.',
      contentNotFound: '“{subject}” ile eşleşen yayımlanmış İttifak içeriği bulamadım.',
      contentFound: 'İttifak stratejisi — {title}: {excerpt}',
      observationSubjectMissing: 'Kontrol etmemi istediğin İttifakı veya gözlem konusunu belirt.',
      observationNotFound: '“{subject}” ile eşleşen yetkili bir gözlem bulamadım.',
      observationFound: 'Gözlem — {title}: {observation}',
      gameFactKnown:
        'Oyun verisi — {title}. Aşağıdaki kaynaklı değerler {datasetVersion} veri kümesindendir.',
      gameFactUnknown:
        'Oyun verisi — {title} için desteklenen bilinen bir değer yok. Tahmin etmeyeceğim.',
      gameFactConflicting:
        'Oyun verisi — {title} için tutulan kanıtlar çelişiyor. Senin adına bir değer seçmeyeceğim.',
      participationNone: '{event} için kaydedilmiş RSVP veya kaydın yok.',
      participationFound: '{event} katılımın aşağıda gösteriliyor.',
      participationWeekNone: 'Bu hafta kaydedilmiş RSVP veya kaydın bulunamadı.',
      participationNotFound: 'Sana ait eşleşen RSVP, kayıt veya bekleme listesi durumu bulunamadı.',
      participationWeek: 'Bu hafta sana ait {count} katılım kaydı bulundu.',
      participationList: 'Sana ait {count} eşleşen katılım kaydı bulundu.',
      battlePlanNotFound: 'Senin için görev içeren yaklaşan yetkili bir etkinlik bulunamadı.',
      battlePlanNone: 'Şu anda {event} için savaş planı görevin yok.',
      battlePlanOne: '{event} için bir savaş planı görevin var.',
      battlePlanMany: '{event} için {count} savaş planı görevin var.',
      transferNotInScope:
        'Mevcut yetkili transfer kapsamından transfer hazırlığını sağlayamıyorum.',
      transferStatus:
        'Transfer değerlendirmen {outcome}. Sahip yeteneğin hesapladığı gereksinimler aşağıda.',
      territoryPlanNotFound: '{event} için yayımlanmış bir bölge planı revizyonu bağlı değil.',
      territoryPlanAmbiguous:
        '{event} için birden fazla yayımlanmış bölge planı revizyonu bağlı; sessizce birini seçmeyeceğim.',
      territoryPlanFound:
        '{event}, {purpose} için {planName} revizyon {revisionNumber} kullanıyor.',
      rosterWriteHandoff:
        'Rosterı buradan değiştiremem. Değişiklik yapmak için normal {event} akışını aç.',
      ownerEventWriteHandoff:
        'Bu değişikliği asistandan yapamam. Devam etmek için normal {event} sahibi çalışma akışını açın.',
      ownerWriteHandoff:
        'Yardımcıdan {owner} durumunu değiştiremem. Mevcut yetkilendirilmiş gerçekler aşağıda gösterilmiştir; devam etmek için sahibin çalışma akışını açın.',
      allianceCommandAttention:
        'Memur genel bakış dikkate ihtiyacı olan {count} gerçek madde(ler)i vardır. Yetkilendirilmiş kaynaklar aşağıda gösterilmiştir.',
      allianceCommandAttentionNotAvailable:
        'Bu ittifak için yetkilendirilmiş bir subay genel görünümü gerçekleri mevcut değildir.',
      eventReadiness:
        'Bir sonraki doğrulanmış olay {count} bloklayan hazırlık maddesine sahiptir. Aşağıda sahibinin projeksiyonu gösterilmiştir.',
      eventReadinessNotAvailable:
        'Yetkilendirilmiş memurların genelinde, olay hazırlığı ile uyumlu bir şey bulamadım.',
      rallyGaps:
        "Bir sonraki doğrulanmış olay {count} Rally hazırlık açı(ları)na sahiptir. Rally'e ait gerçekler aşağıda gösterilmiştir.",
      rallyGapsNotAvailable: 'Maç hazırlık bilgilerini doğrulanmış bir karşılaşma için bulamadım.',
      bearHuntHistory:
        '{count} yakın tarihli Ayı Avcılığı kayıtlarını buldum. Kayıtlı ve kabul edilen kanıt durumları aşağıda açıkça belirtilmiştir.',
      bearHuntHistoryNotAvailable:
        'Ayı avının onaylanmış ve tamamlanmış bir geçmişi olan bir sürümü bulamadım.',
      progressionFreshness:
        '{count} vali gözlemleri, yetkilendirilmiş roster-freshness projeksiyonunda eskimiş veya eksiktir.',
      progressionFreshnessNotAvailable:
        'Valinin gözlem tazeliği yetkilendirilmiş memurların genel bakışında mevcut değil.',
      transferVerification:
        '{count} Transfer katılımcıları doğrulamaya ihtiyaç duyar veya sahip değerlendirmesinde engellenir.',
      transferVerificationNotAvailable:
        'Şu anda yetkilendirilmiş bir Transfer doğrulama projesi yoktur.',
      territoryComparison:
        'Yetkilendirilmiş Territory karşılaştırması {count} gözlemlenen fark(lar) içerir. Eksik ve uyumsuz gözlemler aşağıda açıkça belirtilmiştir.',
      territoryComparisonNotAvailable:
        'Yayınlanmış resmi bir Territory karşılaştırması mevcut değildir.',
      allianceSettings:
        "Mevcut ittifak ayarları {name}, URL adı {slug}, dil {language} ve zaman dilimi {timezone}'tür.",
      allianceGovernanceHistory:
        '{count} yakın tarihli yetkilendirilmiş ittifak yönetimi olayı buldum. Bunlar sadece okunan sahip denetleme gerçekleri.',
      allianceRosterReconciliation:
        'En son kabul edilen roster gözlemlemesinde {needsReview} inceleme gerektiren madde(ler) ve {matched} mevcut üyeliğe uygundur.',
      allianceRosterReconciliationNotAvailable:
        'Henüz uzlaşmak için kabul edilmiş bir ittifak roster gözlemleme mevcut değil.',
    },
    possibleMatches: 'Olası eşleşmeler',
    detailsHeading: 'Yanıt ayrıntıları',
    requirementsHeading: 'Transfer gereksinimleri',
    datasetVersion: 'Veri kümesi sürümü',
    evidenceStatus: 'Kanıt durumu',
    checksum: 'Sağlama toplamı',
    revisionLabel: 'Revizyon {revision}',
    participationSummary: 'RSVP: {response} · Kayıt: {registration}',
    waitlistPosition: 'Bekleme listesi sırası: {position}',
    teamLabel: 'Takım: {team}',
    handoffs: {
      openRoster: 'Etkinlik rosterını aç',
      openOwnerWorkflow: 'Aç sahibi çalışma akışını',
    },
    transferRequirements: {
      window_phase: 'Transfer penceresi aşaması',
      transfer_group: 'Transfer grubu',
      power_cap: 'Güç sınırı',
      invitation: 'Davet',
      transfer_passes: 'Transfer geçişleri',
      in_game_rules: 'Oyun içi kurallar',
    },
    transferStates: {
      met: 'Karşılandı',
      unmet: 'Karşılanmadı',
      unknown: 'Bilinmiyor',
      stale: 'Eski',
      conflicting: 'Çelişkili',
      not_applicable: 'Uygulanamaz',
    },
    requirementValues: 'Mevcut: {actual} · Gerekli: {required}',
  },
  intelligenceChange: {
    title: 'Son istihbarat değişiklikleri',
    subtitle:
      'Yetkili geçmişten türetilen, kaynak gösterilen değişiklikler. Gözlemleri açıklar; stratejik niyet çıkarmaz.',
    empty: 'Bu kapsam için önemli bir istihbarat değişikliği yok.',
    source: 'Kaynak',
    observed: 'Gözlemlendi',
    baseline: 'Temel',
    current: 'Güncel',
    previous: 'Önceki',
    viewSource: 'Kaynağı görüntüle',
    material: 'Önemli değişiklik',
    attention: 'Doğrulama gerekli',
  },
} satisfies MessageCatalogue;

export default messages;
