import type { MessageCatalogue } from '../../types';
const messages = {
  assistant: {
    navigation: 'Trợ lý',
    title: 'Hỏi Liên minh của bạn',
    eyebrow: 'Trợ lý Liên minh · Câu trả lời được cấp quyền',
    subtitle:
      'Hỏi về Sự kiện, roster của bạn, hướng dẫn Liên minh và quan sát. Câu trả lời chỉ dựa trên các nguồn bạn đã có quyền xem.',
    authorizationHint: 'Câu trả lời chỉ dùng dữ liệu Liên minh mà bạn được phép xem.',
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
    },
    sources: {
      event: 'Sự kiện',
      roster: 'Roster',
      alliance_content: 'Hướng dẫn Liên minh',
      observation: 'Quan sát',
      game_fact: 'Dữ liệu trò chơi',
    },
    prompts: {
      swordland: 'Swordland diễn ra lúc mấy giờ và tôi có trong roster không?',
      nextEvent: 'Sự kiện tiếp theo của tôi là gì?',
      bearGuide: 'Hướng dẫn Bear Hunt của chúng ta nói gì?',
      observation: 'Chúng ta đã quan sát gì về đối thủ?',
    },
    answers: {
      help: 'Tôi có thể trả lời từ Sự kiện, roster của bạn, hướng dẫn Liên minh và các quan sát được cấp quyền. Tôi không dùng kiến thức KingShot không có nguồn.',
      unsupported:
        'Tôi chỉ có thể trả lời từ Sự kiện được cấp quyền, roster của bạn, hướng dẫn Liên minh và quan sát. Tôi không thể thực hiện thay đổi từ đây.',
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
    },
  },
} satisfies MessageCatalogue;
export default messages;
