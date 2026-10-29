import type { MessageCatalogue } from '../../types';

const messages = {
  assistant: {
    navigation: 'Trợ lý',
    title: 'Hỏi Liên minh của bạn',
    eyebrow: 'Trợ lý Liên minh · Câu trả lời được cấp quyền',
    subtitle:
      'Hỏi về Sự kiện, roster và RSVP của bạn, nhiệm vụ của bạn, hướng dẫn và quan sát của Liên minh, kế hoạch lãnh thổ đã xuất bản, dữ liệu game có nguồn và mức sẵn sàng chuyển vương quốc khi bạn thuộc phạm vi.',
    authorizationHint: 'Câu trả lời chỉ dùng dữ liệu bạn được phép xem.',
    tryAsking: 'Thử hỏi',
    conversation: 'Cuộc trò chuyện với Trợ lý Liên minh',
    youAsked: 'Bạn đã hỏi',
    possibleEvents: 'Sự kiện có thể',
    openEvent: 'Mở Sự kiện',
    sourcesHeading: 'Nguồn đã dùng',
    sourceTime: 'Thời gian nguồn: {time}',
    questionLabel: 'Hỏi Liên minh của bạn',
    questionPlaceholder: 'Swordland diễn ra lúc mấy giờ và tôi có trong roster không?',
    inputHint: '{count}/{max} ký tự · Enter để hỏi · Shift+Enter để xuống dòng',
    asking: 'Đang kiểm tra nguồn…',
    ask: 'Hỏi',
    notRecorded: 'Chưa ghi nhận',
    classifications: {
      operational_fact: 'Sự thật vận hành',
      game_fact: 'Dữ liệu trò chơi',
      alliance_strategy: 'Chiến lược Liên minh',
      observation: 'Quan sát',
      alliance_command: 'Khám phá',
      event_readiness: 'Đang sẵn sàng',
      bear_hunt_run: 'Đi đi!',
      roster_freshness: 'Thống đốc quan sát sự tươi mới',
      transfer_verification: 'Kiểm tra chuyển tiếp',
      territory_comparison: 'So sánh lãnh thổ',
    },
    sources: {
      event: 'Sự kiện',
      roster: 'Danh sách thành viên',
      alliance_content: 'Hướng dẫn Liên minh',
      observation: 'Quan sát',
      game_fact: 'Dữ liệu trò chơi',
      participation: 'Tham gia',
      battle_plan_assignment: 'Nhiệm vụ kế hoạch chiến đấu',
      transfer_assessment: 'Đánh giá chuyển vương quốc',
      territory_plan_revision: 'Kế hoạch lãnh thổ đã xuất bản',
      alliance_command: 'Khám phá',
      event_readiness: 'Đang sẵn sàng',
      bear_hunt_run: 'Đi đi!',
      roster_freshness: 'Thống đốc quan sát sự tươi mới',
      transfer_verification: 'Kiểm tra chuyển tiếp',
      territory_comparison: 'So sánh lãnh thổ',
      alliance_settings: 'thiết lập liên minh',
      alliance_governance_history: 'lịch sử quản trị liên minh',
      alliance_roster_reconciliation: 'Hợp nhất danh sách liên minh',
    },
    prompts: {
      swordland: 'Swordland diễn ra lúc mấy giờ và tôi có trong roster không?',
      nextEvent: 'Sự kiện tiếp theo của tôi là gì?',
      bearGuide: 'Hướng dẫn Bear Hunt của chúng ta nói gì?',
      observation: 'Chúng ta đã quan sát gì về đối thủ?',
      heroFact: 'Amadeus thuộc thế hệ nào?',
      rsvpWeek: 'Tuần này tôi đã RSVP sự kiện nào?',
      battleAssignment: 'Nhiệm vụ Swordland của tôi là gì?',
      transferStatus: 'Tôi còn thiếu gì để chuyển vương quốc?',
      territoryPlan: 'Chúng ta dùng bố cục hive nào cho Bear Hunt?',
      allianceCommand: 'Có gì cần sự chú ý của sĩ quan?',
      eventReadiness: 'Sự kiện xác nhận tiếp theo của chúng ta đã sẵn sàng chưa?',
      rallyGaps: 'Còn những khoảng trống nào trong Rally để tiếp tục?',
      bearHuntHistory: 'Cho xem lịch sử hoạt động gần đây của Bear Hunt.',
      progressionFreshness: 'Những quan sát của thống đốc nào đã lỗi thời hay bị mất?',
      transferVerification: 'Những người tham gia chuyển đổi nào cần xác nhận?',
      intelligenceChanges: 'Gần đây tình báo đã thay đổi gì?',
      territoryComparison: 'Lãnh thổ quan sát được so sánh như thế nào với kế hoạch được công bố?',
    },
    answers: {
      transferVerificationIncomplete:
        'Tổng quan này đã đánh giá {assessed} trong số {total} người tham gia chuyển. Ít nhất {count} người cần được chú ý; {unassessed} người chưa được đánh giá ở đây. Mở mục sẵn sàng chuyển để kiểm tra thông tin hiện tại.',
      help: 'Tôi có thể trả lời từ Sự kiện được phép, roster và RSVP của bạn, nhiệm vụ của bạn, hướng dẫn và quan sát Liên minh, kế hoạch lãnh thổ đã xuất bản, dữ liệu game có nguồn và trạng thái chuyển vương quốc trong phạm vi. Tôi không dùng kiến thức KingShot không có nguồn.',
      unsupported:
        'Tôi chỉ có thể trả lời từ các nguồn được phép và được hỗ trợ. Tôi không dùng kiến thức KingShot chung và không thay đổi dữ liệu từ Assistant.',
      unavailable: 'Trợ lý Liên minh hiện không thể kiểm tra nguồn. Hãy thử lại.',
      rateLimited: 'Bạn đang hỏi quá nhanh. Hãy thử lại sau ít phút.',
      validationError: 'Nhập câu hỏi từ 2 đến {max} ký tự.',
      noUpcomingEvent: 'Tôi không tìm thấy Sự kiện sắp tới mà bạn được phép xem.',
      eventSubjectMissing: 'Hãy nêu Sự kiện bạn muốn kiểm tra.',
      eventNotFound: 'Tôi không tìm thấy Sự kiện sắp tới được cấp quyền khớp với “{subject}”.',
      eventAmbiguous:
        'Tôi tìm thấy nhiều Sự kiện khớp với “{subject}”. Hãy mở Sự kiện bạn muốn bên dưới.',
      eventTime: '{event} bắt đầu lúc {startsAt}.',
      eventTimeNotRostered: '{event} bắt đầu lúc {startsAt}. Hiện bạn không có trong roster.',
      notRostered: 'Hiện bạn không có trong roster của {event}.',
      eventTimeRostered:
        '{event} bắt đầu lúc {startsAt}. Bạn ở trong {roster}. Vai trò: {role}; vị trí: {slot}; trạng thái: {status}.',
      rostered:
        'Bạn có trong roster của {event}, tại {roster}. Vai trò: {role}; vị trí: {slot}; trạng thái: {status}.',
      contentSubjectMissing: 'Hãy nêu Sự kiện hoặc chủ đề hướng dẫn Liên minh bạn muốn kiểm tra.',
      contentNotFound: 'Tôi không tìm thấy nội dung Liên minh đã xuất bản khớp với “{subject}”.',
      contentFound: 'Chiến lược Liên minh — {title}: {excerpt}',
      observationSubjectMissing: 'Hãy nêu Liên minh hoặc chủ đề quan sát bạn muốn kiểm tra.',
      observationNotFound: 'Tôi không tìm thấy quan sát được cấp quyền khớp với “{subject}”.',
      observationFound: 'Quan sát — {title}: {observation}',
      gameFactKnown:
        'Dữ liệu game — {title}. Các giá trị có nguồn bên dưới đến từ bộ dữ liệu {datasetVersion}.',
      gameFactUnknown:
        'Dữ liệu game — tôi không có giá trị đã biết được hỗ trợ cho {title}. Tôi sẽ không đoán.',
      gameFactConflicting:
        'Dữ liệu game — bằng chứng được duy trì cho {title} đang mâu thuẫn. Tôi sẽ không chọn giá trị thay bạn.',
      participationNone: 'Không có RSVP hoặc đăng ký của bạn cho {event}.',
      participationFound: 'Thông tin tham gia {event} của bạn ở bên dưới.',
      participationWeekNone: 'Không tìm thấy RSVP hoặc đăng ký của bạn trong tuần này.',
      participationNotFound:
        'Không tìm thấy trạng thái RSVP, đăng ký hoặc danh sách chờ phù hợp cho bạn.',
      participationWeek: 'Tìm thấy {count} bản ghi tham gia của bạn trong tuần này.',
      participationList: 'Tìm thấy {count} bản ghi tham gia phù hợp cho bạn.',
      battlePlanNotFound: 'Không tìm thấy Sự kiện sắp tới được phép có nhiệm vụ cho bạn.',
      battlePlanNone: 'Hiện bạn không có nhiệm vụ kế hoạch chiến đấu cho {event}.',
      battlePlanOne: 'Bạn có một nhiệm vụ kế hoạch chiến đấu cho {event}.',
      battlePlanMany: 'Bạn có {count} nhiệm vụ kế hoạch chiến đấu cho {event}.',
      transferNotInScope:
        'Tôi không thể cung cấp mức sẵn sàng chuyển vương quốc từ phạm vi được phép hiện tại của bạn.',
      transferStatus:
        'Đánh giá chuyển vương quốc của bạn là {outcome}. Các yêu cầu do năng lực sở hữu tính toán được hiển thị bên dưới.',
      territoryPlanNotFound: 'Không có bản sửa đổi kế hoạch lãnh thổ đã xuất bản gắn với {event}.',
      territoryPlanAmbiguous:
        'Có nhiều bản sửa đổi kế hoạch lãnh thổ đã xuất bản gắn với {event}; tôi sẽ không tự chọn một bản.',
      territoryPlanFound:
        '{event} đang dùng {planName}, bản sửa đổi {revisionNumber}, cho {purpose}.',
      rosterWriteHandoff:
        'Tôi không thể thay đổi roster từ đây. Hãy mở luồng {event} bình thường để thực hiện thay đổi.',
      ownerEventWriteHandoff:
        'Tôi không thể thay đổi từ trợ lý. Mở dòng lệnh {event} bình thường để tiếp tục.',
      ownerWriteHandoff:
        'Tôi không thể thay đổi trạng thái {owner} từ trợ lý. Các sự kiện được ủy quyền hiện tại được hiển thị dưới đây; mở dòng công việc của chủ sở hữu để tiếp tục.',
      allianceCommandAttention:
        'Kết quả sơ bộ của sĩ quan có {count} mục cần chú ý. Các nguồn được ủy quyền được liệt kê dưới đây.',
      allianceCommandAttentionNotAvailable:
        'Không có sự kiện thống kê sĩ quan được ủy quyền cho liên minh này.',
      eventReadiness:
        'Sự kiện xác nhận tiếp theo có {count} chặn mục sẵn sàng. Xác suất chủ sở hữu của nó được thể hiện dưới đây.',
      eventReadinessNotAvailable:
        'Tôi không tìm thấy sự sẵn sàng cho sự kiện được xác nhận trong bảng thống kê của các sĩ quan được ủy quyền.',
      rallyGaps:
        'Sự kiện xác nhận tiếp theo có {count} khoảng trống sẵn sàng Rally. Các sự kiện thuộc sở hữu của Rally được thể hiện dưới đây.',
      rallyGapsNotAvailable:
        'Tôi không thể tìm thấy sự kiện sẵn sàng Rally cho một sự kiện xác nhận tương ứng.',
      bearHuntHistory:
        'Tôi tìm thấy {count} ghi chép về việc săn gấu gần đây. Các trạng thái ghi lại và chứng cứ chấp nhận vẫn rõ ràng dưới đây.',
      bearHuntHistoryNotAvailable:
        'Tôi không thể tìm thấy một cuộc săn gấu được ủy quyền với lịch sử được hỗ trợ.',
      progressionFreshness:
        '{count} thống đốc quan sát (s) là cũ hoặc thiếu trong dự đoán mới của danh sách được ủy quyền.',
      progressionFreshnessNotAvailable:
        'Thống đốc, sự quan sát mới nhất không có sẵn trong bảng thống kê của ông.',
      transferVerification:
        '{count} Người tham gia chuyển đổi cần xác minh hoặc bị chặn trong đánh giá chủ sở hữu.',
      transferVerificationNotAvailable:
        'Không có dự đoán xác thực chuyển giao hiện tại được ủy quyền.',
      territoryComparison:
        'Sự so sánh Lãnh thổ được ủy quyền chứa {count} khác biệt được quan sát. Các quan sát thiếu và không tương thích vẫn rõ ràng dưới đây.',
      territoryComparisonNotAvailable: 'Không có sự so sánh Lãnh thổ được công bố có sẵn.',
      allianceSettings:
        'Các thiết lập liên minh hiện tại là {name}, tên URL {slug}, ngôn ngữ {language}, và múi giờ {timezone}.',
      allianceGovernanceHistory:
        'Tôi tìm thấy {count} sự kiện quản lý liên minh gần đây. Đây là những thông tin kiểm toán của chủ sở hữu chỉ đọc.',
      allianceRosterReconciliation:
        'Quan sát danh sách chấp nhận gần đây nhất có {needsReview} mục cần xem xét và {matched} phù hợp với thành viên hiện tại.',
      allianceRosterReconciliationNotAvailable:
        'Không có quan sát danh sách liên minh được chấp nhận để hòa giải.',
    },
    possibleMatches: 'Kết quả có thể khớp',
    detailsHeading: 'Chi tiết câu trả lời',
    requirementsHeading: 'Yêu cầu chuyển vương quốc',
    datasetVersion: 'Phiên bản bộ dữ liệu',
    evidenceStatus: 'Trạng thái bằng chứng',
    checksum: 'Tổng kiểm tra',
    revisionLabel: 'Bản sửa đổi {revision}',
    participationSummary: 'RSVP: {response} · Đăng ký: {registration}',
    waitlistPosition: 'Vị trí chờ: {position}',
    teamLabel: 'Đội: {team}',
    handoffs: {
      openRoster: 'Mở roster Sự kiện',
      openOwnerWorkflow: 'Mở dòng công việc của chủ sở hữu',
    },
    transferRequirements: {
      window_phase: 'Giai đoạn cửa sổ chuyển',
      transfer_group: 'Nhóm chuyển',
      power_cap: 'Giới hạn lực chiến',
      invitation: 'Lời mời',
      transfer_passes: 'Thẻ chuyển',
      in_game_rules: 'Quy tắc trong game',
    },
    transferStates: {
      met: 'Đạt',
      unmet: 'Chưa đạt',
      unknown: 'Chưa biết',
      stale: 'Đã cũ',
      conflicting: 'Mâu thuẫn',
      not_applicable: 'Không áp dụng',
    },
    requirementValues: 'Hiện tại: {actual} · Yêu cầu: {required}',
  },
  intelligenceChange: {
    title: 'Thay đổi tình báo gần đây',
    subtitle:
      'Các thay đổi có trích nguồn được suy ra từ lịch sử đã được cấp quyền. Chúng mô tả quan sát, không suy đoán ý định chiến lược.',
    empty: 'Không có thay đổi tình báo đáng kể cho phạm vi này.',
    source: 'Nguồn',
    observed: 'Quan sát',
    baseline: 'Mốc',
    current: 'Hiện tại',
    previous: 'Trước đó',
    viewSource: 'Xem nguồn',
    material: 'Thay đổi đáng kể',
    attention: 'Cần xác minh',
  },
} satisfies MessageCatalogue;

export default messages;
