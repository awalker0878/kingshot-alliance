import type { MessageCatalogue } from '../../types';
const messages = { evidence: { openIntake: '전투 보고서 가져오기', eyebrow: '곰 사냥 · 증거 검토', title: '스크린샷 가져오기', subtitle: '곰 사냥 전투 보고서를 업로드하고 이벤트 결과를 변경하기 전에 추출된 값을 모두 검토하세요.', back: '곰 사냥으로 돌아가기', uploadTitle: '전투 보고서 업로드', uploadHelp: 'JPEG, PNG 또는 WebP. 원본은 비공개로 보관되고 보안 검사와 변경 불가능한 체크섬을 받습니다.', chooseFile: '전투 보고서 스크린샷', upload: '스크린샷 업로드', uploading: '업로드 중…', existingTitle: '이 곰 사냥의 증거', empty: '이 곰 사냥에 업로드된 스크린샷이 없습니다.', originalName: '출처', status: '상태', received: '수신', security: '출처 정보' } } satisfies MessageCatalogue;
export default messages;
