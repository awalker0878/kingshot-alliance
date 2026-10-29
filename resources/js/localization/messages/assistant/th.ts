import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'ผู้ช่วย',
    title: 'ถาม Alliance ของคุณ',
    eyebrow: 'ผู้ช่วย Alliance · คำตอบตามสิทธิ์',
    subtitle:
      'ถามเกี่ยวกับอีเวนต์ โรสเตอร์และ RSVP ของคุณ งานที่ได้รับมอบหมาย คู่มือและข้อสังเกตของพันธมิตร แผนอาณาเขตที่เผยแพร่ ข้อมูลเกมที่มีแหล่งอ้างอิง และความพร้อมในการย้ายเมื่ออยู่ในขอบเขต',
    authorizationHint: 'คำตอบใช้เฉพาะข้อมูลที่คุณได้รับอนุญาตให้ดู',
    tryAsking: 'ลองถาม',
    conversation: 'การสนทนากับผู้ช่วย Alliance',
    youAsked: 'คุณถาม',
    possibleEvents: 'Event ที่เป็นไปได้',
    openEvent: 'เปิด Event',
    sourcesHeading: 'แหล่งข้อมูลที่ใช้',
    sourceTime: 'เวลาของแหล่งข้อมูล: {time}',
    questionLabel: 'ถาม Alliance ของคุณ',
    questionPlaceholder: 'Swordland เริ่มกี่โมงและฉันอยู่ใน roster ไหม?',
    inputHint: '{count}/{max} ตัวอักษร · Enter เพื่อถาม · Shift+Enter เพื่อขึ้นบรรทัดใหม่',
    asking: 'กำลังตรวจสอบแหล่งข้อมูล…',
    ask: 'ถาม',
    notRecorded: 'ยังไม่บันทึก',
    classifications: {
      operational_fact: 'ข้อเท็จจริงด้านปฏิบัติการ',
      game_fact: 'ข้อมูลเกม',
      alliance_strategy: 'กลยุทธ์ Alliance',
      observation: 'ข้อมูลสังเกตการณ์',
      alliance_command: 'รายละเอียดของเจ้าหน้าที่',
      event_readiness: 'เตรียมพร้อม',
      bear_hunt_run: 'หมีล่าวิ่ง',
      roster_freshness: 'ความสดชื่นของการสังเกตการณ์ของผู้ว่าการ',
      transfer_verification: 'ตรวจสอบการส่งต่อ',
      territory_comparison: 'พื้นที่เปรียบเทียบ',
    },
    sources: {
      event: 'เหตุการณ์',
      roster: 'รายชื่อสมาชิก',
      alliance_content: 'คู่มือ Alliance',
      observation: 'ข้อมูลสังเกตการณ์',
      game_fact: 'ข้อมูลเกม',
      participation: 'การเข้าร่วม',
      battle_plan_assignment: 'งานแผนการรบ',
      transfer_assessment: 'การประเมินการย้าย',
      territory_plan_revision: 'แผนอาณาเขตที่เผยแพร่',
      alliance_command: 'รายละเอียดของเจ้าหน้าที่',
      event_readiness: 'เตรียมพร้อม',
      bear_hunt_run: 'หมีล่าวิ่ง',
      roster_freshness: 'ความสดชื่นของการสังเกตการณ์ของผู้ว่าการ',
      transfer_verification: 'ตรวจสอบการส่งต่อ',
      territory_comparison: 'พื้นที่เปรียบเทียบ',
      alliance_settings: 'ตั้งค่าพันธมิตร',
      alliance_governance_history: 'ประวัติการปกครองของพันธมิตร',
      alliance_roster_reconciliation: 'ปรับปรุงรายชื่อพันธมิตร',
    },
    prompts: {
      swordland: 'Swordland เริ่มกี่โมงและฉันอยู่ใน roster ไหม?',
      nextEvent: 'Event ถัดไปของฉันคืออะไร?',
      bearGuide: 'คู่มือ Bear Hunt ของเราบอกว่าอย่างไร?',
      observation: 'เราสังเกตอะไรเกี่ยวกับคู่ต่อสู้บ้าง?',
      heroFact: 'Amadeus เป็นรุ่นที่เท่าไร?',
      rsvpWeek: 'สัปดาห์นี้ฉัน RSVP อีเวนต์ใดบ้าง?',
      battleAssignment: 'งาน Swordland ของฉันคืออะไร?',
      transferStatus: 'ฉันยังขาดอะไรสำหรับการย้าย?',
      territoryPlan: 'เราใช้ผังไฮฟ์ใดสำหรับ Bear Hunt?',
      allianceCommand: 'อะไรที่เจ้าหน้าที่ต้องให้ความสนใจ',
      eventReadiness: 'เหตุการณ์ที่เราจะตรวจสอบต่อไป เตรียมพร้อมหรือยัง',
      rallyGaps: 'ยังมีรอยแยกอะไรอีกบ้าง สำหรับการแข่งขันครั้งต่อไป',
      bearHuntHistory: 'แสดงประวัติการแสดงของพวกเราในงาน Hunting Bear',
      progressionFreshness: 'ข้อมูลของผู้ว่าฯ ไหนที่หายไปหรือเก่าไป?',
      transferVerification: 'ผู้เข้าร่วมการย้ายที่ต้องการการยืนยัน',
      intelligenceChanges: 'อะไรที่เปลี่ยนไปเมื่อเร็วๆนี้',
      territoryComparison:
        'พื้นที่ที่ได้จากการตรวจสอบ มันเทียบได้ยังไงกับแผนการที่ได้มีการเผยแพร่?',
    },
    answers: {
      transferVerificationIncomplete:
        'ภาพรวมนี้ประเมินผู้เข้าร่วมการย้ายแล้ว {assessed} จาก {total} คน อย่างน้อย {count} คนต้องได้รับการตรวจสอบ และ {unassessed} คนยังไม่ได้รับการประเมินที่นี่ เปิดหน้าความพร้อมในการย้ายเพื่อตรวจสอบข้อมูลปัจจุบัน',
      help: 'ฉันตอบได้จากอีเวนต์ที่ได้รับอนุญาต โรสเตอร์และ RSVP ของคุณ งานของคุณ คู่มือและข้อสังเกตของพันธมิตร แผนอาณาเขตที่เผยแพร่ ข้อมูลเกมที่มีแหล่งอ้างอิง และสถานะการย้ายในขอบเขต โดยไม่ใช้ความรู้ KingShot ที่ไม่มีแหล่งอ้างอิง',
      unsupported:
        'ฉันตอบได้เฉพาะจากแหล่งข้อมูลที่รองรับและได้รับอนุญาต ไม่ใช้ความรู้ KingShot ทั่วไปและไม่เปลี่ยนแปลงข้อมูลจาก Assistant',
      unavailable: 'ขณะนี้ผู้ช่วย Alliance ตรวจสอบแหล่งข้อมูลไม่ได้ โปรดลองอีกครั้ง',
      rateLimited: 'คุณถามเร็วเกินไป โปรดลองอีกครั้งในอีกสักครู่',
      validationError: 'กรอกคำถามระหว่าง 2 ถึง {max} ตัวอักษร',
      noUpcomingEvent: 'ไม่พบ Event ที่กำลังจะมาถึงซึ่งคุณมีสิทธิ์ดู',
      eventSubjectMissing: 'ระบุ Event ที่ต้องการให้ตรวจสอบ',
      eventNotFound: 'ไม่พบ Event ที่ได้รับอนุญาตและกำลังจะมาถึงซึ่งตรงกับ “{subject}”',
      eventAmbiguous:
        'พบมากกว่าหนึ่ง Event ที่ตรงกับ “{subject}” โปรดเปิด Event ที่ต้องการด้านล่าง',
      eventTime: '{event} เริ่ม {startsAt}',
      eventTimeNotRostered: '{event} เริ่ม {startsAt} ขณะนี้คุณไม่ได้อยู่ใน roster',
      notRostered: 'ขณะนี้คุณไม่ได้อยู่ใน roster ของ {event}',
      eventTimeRostered:
        '{event} เริ่ม {startsAt} คุณอยู่ใน {roster} บทบาท: {role}; ช่อง: {slot}; สถานะ: {status}',
      rostered:
        'คุณอยู่ใน roster ของ {event} ใน {roster} บทบาท: {role}; ช่อง: {slot}; สถานะ: {status}',
      contentSubjectMissing: 'ระบุ Event หรือหัวข้อของคู่มือ Alliance ที่ต้องการตรวจสอบ',
      contentNotFound: 'ไม่พบเนื้อหา Alliance ที่เผยแพร่ซึ่งตรงกับ “{subject}”',
      contentFound: 'กลยุทธ์ Alliance — {title}: {excerpt}',
      observationSubjectMissing: 'ระบุ Alliance หรือหัวข้อสังเกตการณ์ที่ต้องการตรวจสอบ',
      observationNotFound: 'ไม่พบข้อมูลสังเกตการณ์ที่ได้รับอนุญาตซึ่งตรงกับ “{subject}”',
      observationFound: 'ข้อมูลสังเกตการณ์ — {title}: {observation}',
      gameFactKnown:
        'ข้อมูลเกม — {title} ค่าที่มีแหล่งอ้างอิงด้านล่างมาจากชุดข้อมูล {datasetVersion}',
      gameFactUnknown: 'ข้อมูลเกม — ไม่มีค่าที่ทราบและรองรับสำหรับ {title} ฉันจะไม่เดา',
      gameFactConflicting:
        'ข้อมูลเกม — หลักฐานที่ดูแลไว้สำหรับ {title} ขัดแย้งกัน ฉันจะไม่เลือกค่าแทนคุณ',
      participationNone: 'ไม่มี RSVP หรือการลงทะเบียนของคุณสำหรับ {event}',
      participationFound: 'การเข้าร่วม {event} ของคุณแสดงด้านล่าง',
      participationWeekNone: 'ไม่พบ RSVP หรือการลงทะเบียนของคุณในสัปดาห์นี้',
      participationNotFound: 'ไม่พบสถานะ RSVP การลงทะเบียน หรือคิวที่ตรงกับคุณ',
      participationWeek: 'พบระเบียนการเข้าร่วมของคุณ {count} รายการในสัปดาห์นี้',
      participationList: 'พบระเบียนการเข้าร่วมที่ตรงกัน {count} รายการ',
      battlePlanNotFound: 'ไม่พบอีเวนต์ที่ได้รับอนุญาตซึ่งมีงานสำหรับคุณ',
      battlePlanNone: 'ขณะนี้คุณไม่มีงานแผนการรบสำหรับ {event}',
      battlePlanOne: 'คุณมีงานแผนการรบหนึ่งรายการสำหรับ {event}',
      battlePlanMany: 'คุณมีงานแผนการรบ {count} รายการสำหรับ {event}',
      transferNotInScope: 'ฉันไม่สามารถให้ความพร้อมการย้ายจากขอบเขตที่ได้รับอนุญาตปัจจุบันของคุณ',
      transferStatus: 'ผลประเมินการย้ายของคุณคือ {outcome} ข้อกำหนดที่เจ้าของคำนวณแสดงด้านล่าง',
      territoryPlanNotFound: 'ไม่มีฉบับแผนอาณาเขตที่เผยแพร่แนบกับ {event}',
      territoryPlanAmbiguous:
        'มีฉบับแผนอาณาเขตที่เผยแพร่หลายฉบับแนบกับ {event}; ฉันจะไม่เลือกโดยเงียบ ๆ',
      territoryPlanFound: '{event} ใช้ {planName} ฉบับ {revisionNumber} สำหรับ {purpose}',
      rosterWriteHandoff:
        'ฉันเปลี่ยนโรสเตอร์จากที่นี่ไม่ได้ โปรดเปิดขั้นตอนปกติของ {event} เพื่อทำการเปลี่ยนแปลง',
      ownerEventWriteHandoff:
        'ฉันเปลี่ยนจากผู้ช่วยไม่ได้ เปิดกระบวนการทำงานปกติของเจ้าของ {event} เพื่อทำต่อไป',
      ownerWriteHandoff:
        'ฉันเปลี่ยนสถานะ {owner} จากผู้ช่วยไม่ได้ ข้อมูลที่ได้รับอนุญาตในปัจจุบันจะแสดงไว้ด้านล่างนี้ เปิดกระบวนการทำงานของเจ้าของเพื่อทำต่อไป',
      allianceCommandAttention:
        'รายงานของเจ้าหน้าที่ มีข้อมูล {count} รายการที่ต้องให้ความสนใจ ข้อมูลที่ได้รับอนุญาต แสดงไว้ด้านล่างนี้',
      allianceCommandAttentionNotAvailable:
        'ไม่มีข้อมูลที่ได้รับอนุญาตจากเจ้าหน้าที่ สำหรับพันธมิตรนี้',
      eventReadiness:
        'เหตุการณ์ที่ตรวจสอบครั้งต่อไปมี {count} รายการบล็อกความพร้อม ภาพถ่ายของเจ้าของมันแสดงไว้ด้านล่างนี้',
      eventReadinessNotAvailable:
        'ฉันไม่สามารถหาการเตรียมพร้อมที่ตรงกันได้ ในรายงานของเจ้าหน้าที่ที่ได้รับอนุญาตของคุณ',
      rallyGaps:
        'เหตุการณ์ที่ตรวจสอบครั้งต่อไป มีช่องว่างความพร้อมในการแข่ง {count} ข้อมูลที่เกี่ยวข้องกับการชุมนุม แสดงไว้ด้านล่างนี้',
      rallyGapsNotAvailable:
        'ฉันไม่สามารถหาข้อมูลการเตรียมพร้อมของการชุมนุม สำหรับเหตุการณ์ที่ถูกตรวจสอบได้',
      bearHuntHistory:
        'ฉันพบ {count} รายงานการล่าหมีที่ได้รับอนุญาต สถานการณ์ที่บันทึกและยอมรับหลักฐาน ยังคงชัดเจนอยู่ข้างล่าง',
      bearHuntHistoryNotAvailable:
        'ฉันไม่สามารถหาการล่าหมีที่ได้รับอนุญาต ที่มีประวัติที่สนับสนุนได้',
      progressionFreshness:
        '{count} ข้อมูลการสังเกตของผู้ว่าการ เก่าหรือหายไป ในการคาดการณ์ความสดใหม่ของรายชื่อที่ได้รับอนุญาต',
      progressionFreshnessNotAvailable:
        'ความสดใหม่ของการสังเกตของผู้ว่าการ ไม่ได้มีอยู่ในรายงานของเจ้าหน้าที่ที่ได้รับอนุญาตของคุณ',
      transferVerification:
        '{count} ผู้เข้าร่วมการสื่อสารต้องการการยืนยันหรือถูกบล็อกในการประเมินเจ้าของ',
      transferVerificationNotAvailable: 'ไม่มีการตรวจสอบการโอนสิทธิ์ที่ได้รับอนุญาต',
      territoryComparison:
        'การเปรียบเทียบที่ได้รับอนุญาตของ Territory มีความแตกต่างที่พบ {count} จุด ข้อมูลที่หายไปและไม่ตรงกับการสังเกตยังคงแสดงไว้ด้านล่างนี้',
      territoryComparisonNotAvailable: 'ไม่มีข้อมูลที่ถูกต้อง เกี่ยวกับพื้นที่ที่ใช้ได้',
      allianceSettings:
        'ตั้งค่าพันธมิตรปัจจุบันคือ {name}, URL name {slug}, ภาษา {language}, และเขตเวลา {timezone}',
      allianceGovernanceHistory:
        'ฉันพบ {count} เหตุการณ์การปกครองของพันธมิตรที่ได้รับอนุญาต นี่คือข้อมูลการตรวจสอบของเจ้าของที่อ่านได้เท่านั้น',
      allianceRosterReconciliation:
        'การสังเกตรายชื่อผู้รับรองล่าสุด มี {needsReview} รายการที่ต้องตรวจสอบ และ {matched} รายการที่ตรงกับสมาชิกปัจจุบัน',
      allianceRosterReconciliationNotAvailable:
        'ยังไม่มีการรับรองการสังเกตรายชื่อพันธมิตรที่สามารถใช้ได้เพื่อปรับปรุง',
    },
    possibleMatches: 'รายการที่อาจตรงกัน',
    detailsHeading: 'รายละเอียดคำตอบ',
    requirementsHeading: 'ข้อกำหนดการย้าย',
    datasetVersion: 'เวอร์ชันชุดข้อมูล',
    evidenceStatus: 'สถานะหลักฐาน',
    checksum: 'เช็กซัม',
    revisionLabel: 'ฉบับแก้ไข {revision}',
    participationSummary: 'RSVP: {response} · ลงทะเบียน: {registration}',
    waitlistPosition: 'ลำดับคิว: {position}',
    teamLabel: 'ทีม: {team}',
    handoffs: {
      openRoster: 'เปิดโรสเตอร์อีเวนต์',
      openOwnerWorkflow: 'เปิดกระบวนการทำงานของเจ้าของ',
    },
    transferRequirements: {
      window_phase: 'ช่วงหน้าต่างการย้าย',
      transfer_group: 'กลุ่มการย้าย',
      power_cap: 'ขีดจำกัดพลัง',
      invitation: 'คำเชิญ',
      transfer_passes: 'บัตรย้าย',
      in_game_rules: 'กฎในเกม',
    },
    transferStates: {
      met: 'ผ่าน',
      unmet: 'ไม่ผ่าน',
      unknown: 'ไม่ทราบ',
      stale: 'เก่า',
      conflicting: 'ขัดแย้ง',
      not_applicable: 'ไม่เกี่ยวข้อง',
    },
    requirementValues: 'ปัจจุบัน: {actual} · ต้องการ: {required}',
  },
  intelligenceChange: {
    title: 'การเปลี่ยนแปลงข่าวกรองล่าสุด',
    subtitle:
      'การเปลี่ยนแปลงพร้อมแหล่งอ้างอิงจากประวัติที่ได้รับอนุญาต อธิบายสิ่งที่สังเกตได้ ไม่อนุมานเจตนาเชิงกลยุทธ์',
    empty: 'ไม่มีการเปลี่ยนแปลงข่าวกรองที่มีนัยสำคัญสำหรับขอบเขตนี้',
    source: 'แหล่งข้อมูล',
    observed: 'สังเกตเมื่อ',
    baseline: 'ค่าฐาน',
    current: 'ปัจจุบัน',
    previous: 'ก่อนหน้า',
    viewSource: 'ดูแหล่งข้อมูล',
    material: 'การเปลี่ยนแปลงสำคัญ',
    attention: 'ต้องตรวจสอบ',
  },
} satisfies MessageCatalogue;

export default messages;
