# Ios App Purchase Sample

React와 Capacitor 기반 앱의 외부 인앱결제 라이브러리 없는 iOS 인앱결제(StoreKit 2) 구현 및 테스트 샘플

JS에서 호출할 수 없는 StoreKit(iOS 네이티브 API)은 직접 작성한 Swift Capacitor 플러그인으로 호출, 거래 검증은 Laravel 서버에서 처리

## 단계별 애플 인앱 결제 테스트


| 단계                    | 결제 환경      | 서버 검증         | 확인 범위                         | 이 프로젝트                      |
| --------------------- | ---------- | ------------- | ----------------------------- | --------------------------- |
| 1단계 서버 단독 (curl)      | 없음         | 인증과 입력값 검증만   | API 응답                        | 지원 (다른 단계와 병행)              |
| 2단계 Xcode StoreKit 로컬 | Xcode      | 항상 실패 (로컬 서명) | 앱 결제 흐름, 취소와 미완료 거래 등 예외 상황   | **현재 설정**                   |
| 3단계 실기기와 Sandbox      | Sandbox    | 실제 검증         | 결제, 서명 검증, 지급, finish까지 전체 흐름 | 지원 (유료 앱 계약 활성화 후 스킴 변경 필요) |
| 4단계 TestFlight        | Sandbox    | 실제 검증         | 배포 빌드에서의 동작                   | 범위 외                        |
| 5단계 운영                | Production | 실제 검증         | 실제 결제                         | 범위 외                        |


- 현재 Xcode 스킴 `App`에 `IAPTest.storekit` 연결 상태 (2단계), 3단계 변경 방법은 "3단계 Sandbox 테스트로 변경" 참고
- TestFlight와 앱 심사의 결제도 Sandbox 환경 (운영 서버 설정 주의, "테스트 시 주의" 참고)

## 구조

```
iap-sample/
├── react/                      화면(UI) 전체. Vite, React, Capacitor CLI
│   ├── src/IapTest.tsx         테스트 화면 (상품 조회, 구매, 서버 검증, 미완료 거래 처리)
│   ├── src/IapTest.css         테스트 화면 스타일 (Apple HIG 기준 iOS 목록 형태, 다크 모드 대응)
│   ├── src/iap.ts              네이티브 IAP 플러그인의 JS 인터페이스
│   └── capacitor.config.ts     Capacitor 설정 (iOS 프로젝트 위치는 ../ios)
├── ios/                        Capacitor가 생성한 Xcode 프로젝트 (빌드 결과물 아님, 커밋 대상)
│   └── App/
│       ├── App/
│       │   ├── IAPPlugin.swift         StoreKit 2 호출 플러그인 (직접 구현)
│       │   ├── MyViewController.swift  IAPPlugin 등록
│       │   ├── SceneDelegate.swift     루트 뷰 컨트롤러를 MyViewController로 지정
│       │   ├── IAPTest.storekit        2단계용 로컬 상품 정의
│       │   └── public/                 React 빌드 결과물 복사 위치 (git 제외)
│       └── App.xcodeproj/xcshareddata/xcschemes/App.xcscheme  StoreKit 설정 파일이 연결된 공유 스킴
└── laravel/                    거래(JWS) 서명 검증 및 지급 API
    ├── app/Support/AppleJwsVerifier.php
    ├── app/Http/Controllers/AppleIapController.php
    └── routes/api.php          POST /api/iap/apple/verify
```

- React 코드는 `npx cap sync ios` 실행 시 `ios/App/App/public`으로 복사, WKWebView에서 실행
- `ios/`는 직접 작성한 Swift 코드와 프로젝트 설정 포함 (`npx cap add ios`로 재생성 금지)

## 결제 흐름

1. `IAP.purchase()`로 StoreKit 결제 후 거래 JWS 반환
2. 앱에서 `POST /api/iap/apple/verify`로 JWS 전송
3. 서버의 Apple 루트 인증서 체인 및 서명 검증 후 `apple_purchases`에 저장 (같은 transaction_id는 재지급 없음)
4. 서버 응답 성공 시에만 앱에서 `IAP.finish()` 호출 (실패 거래는 "미완료 거래 처리"로 재시도)

## 환경 설정 파일


| 파일                         | 키                           | 설명                                                |
| -------------------------- | --------------------------- | ------------------------------------------------- |
| `react/.env.local`         | `CAPACITOR_APP_ID`          | Bundle ID                                         |
|                            | `VITE_API_URL`              | Laravel API 주소 (Mac의 LAN IP)                      |
|                            | `VITE_API_TOKEN`            | 테스트 사용자의 Sanctum 토큰                               |
|                            | `VITE_PRODUCT_IDS`          | 상품 ID (쉼표 구분)                                     |
| `ios/App/Signing.xcconfig` | `DEVELOPMENT_TEAM`          | Apple 개발자 팀 ID                                    |
|                            | `PRODUCT_BUNDLE_IDENTIFIER` | Bundle ID                                         |
| `laravel/.env`             | `APPLE_BUNDLE_ID`           | 거래의 bundleId 비교용 Bundle ID                        |
|                            | `APPLE_IAP_ENVIRONMENT`     | `Sandbox` 또는 `Production`                         |
|                            | `IAP_TEST_USER_EMAIL`       | 테스트 토큰 발급용 사용자 이메일 (`php artisan iap:test-token`) |


- 세 파일 모두 git 제외, 같은 위치의 `.env.example`과 `Signing.xcconfig.example` 복사로 생성
- Bundle ID는 세 파일 모두 같은 값 필요
- `VITE_` 값은 빌드 시 앱 번들에 그대로 포함 (테스트용 값만 사용)
- Team과 Bundle Identifier 변경은 `Signing.xcconfig`에서 (Xcode의 Signing &amp; Capabilities 화면에서 바꾸면 `project.pbxproj`에 값이 직접 기록됨)

## 처음 세팅

필요 도구: Node 22 이상, PHP 8.3 이상, Composer, Xcode (3단계는 iOS 15 이상 실기기)

### Laravel

```bash
cd laravel
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

mkdir -p storage/app/apple
curl -o storage/app/apple/AppleRootCA-G3.cer https://www.apple.com/certificateauthority/AppleRootCA-G3.cer

# .env의 IAP_TEST_USER_EMAIL 사용자 생성 및 토큰 발급 (출력된 토큰은 react/.env.local의 VITE_API_TOKEN에 입력)
php artisan iap:test-token
```

Apple 루트 인증서는 git 제외 대상(`storage/app/.gitignore`)으로 서버마다 별도 다운로드 필요

### React와 iOS

```bash
cd react
npm install
# .env.local 값 입력 (Mac IP는 ipconfig getifaddr en0으로 확인)
cp .env.example .env.local
cp ../ios/App/Signing.xcconfig.example ../ios/App/Signing.xcconfig
```

## 실행

### 1. API 서버 (실기기 접속용 0.0.0.0 바인딩)
```bash
cd laravel
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. 앱 빌드 및 iOS 프로젝트 반영 (다른 터미널)
```bash
cd react
npm run build && npx cap sync ios
npx cap open ios
```

3. Xcode 상단에서 실행 대상(시뮬레이터 또는 연결된 iPhone) 선택 후 Run (Cmd+R)
4. 실기기 첫 설치 시 iPhone 설정의 일반 항목 중 VPN 및 기기 관리에서 개발자 앱 신뢰

- 실기기 사용 시 Mac과 iPhone은 같은 Wi-Fi 연결 필요
- React 코드 수정 시 2번 재실행 후 Xcode에서 Run, Swift 코드만 수정한 경우 Xcode에서 Run만
- Mac IP 변경 시 `react/.env.local`의 `VITE_API_URL` 수정 후 2번부터 재실행

## 테스트

### 1단계 서버 단독 확인

```bash
TOKEN='<VITE_API_TOKEN 값>'
curl -X POST "http://$(ipconfig getifaddr en0):8000/api/iap/apple/verify" \
  -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -d '{"jws":"a.b.c"}'
```

- 토큰 없는 요청은 401, 위와 같은 잘못된 JWS는 422 응답이 정상
- 토큰에 포함된 `|` 문자 처리를 위한 작은따옴표 사용
- `127.0.0.1:8000`을 다른 로컬 서버가 점유한 경우 `localhost:8000` 요청은 그 서버로 전달 (LAN IP로 확인하거나 다른 포트로 실행 후 `VITE_API_URL`도 변경)

### 2단계 Xcode StoreKit 로컬 테스트 (현재 설정)

- 스킴 `App`의 Run 설정에 `ios/App/App/IAPTest.storekit` 연결 (Product 메뉴의 Scheme 하위 Edit Scheme에서 Run 항목의 Options 탭, StoreKit Configuration)
- App Store Connect와 Sandbox 계정 없이 시뮬레이터와 실기기 모두에서 결제 가능
- 상품 3개(`assessment.ticket.30000`, `assessment.ticket.50000`, `standard`)는 로컬 테스트용 정의 (가격과 이름은 App Store Connect와 무관)
- 서버 검증은 항상 422 실패 (Apple이 아닌 Xcode 로컬 인증서 서명), finish되지 않은 거래는 미완료 상태로 유지
- 거래 내역 확인, 삭제, 환불 처리는 Xcode Debug 메뉴의 StoreKit 하위 Manage Transactions
- 승인 대기(Ask to Buy), 거래 실패 등 예외 상황은 `IAPTest.storekit`을 연 상태의 Editor 메뉴에서 설정

### 3단계 Sandbox 테스트로 변경

- 사전 조건은 App Store Connect 유료 앱 계약 활성화 (비활성 상태에서는 상품 조회 결과 0개)
- Edit Scheme의 Run 항목 Options 탭에서 StoreKit Configuration을 None으로 변경 (설정 파일이 연결된 상태에서는 실기기 결제도 로컬 처리)
- 스킴 변경은 공유 스킴 파일(`App.xcscheme`)에 기록되어 git 변경으로 표시
- 실기기 설정의 App Store 항목 중 Sandbox 계정에 테스터 계정 로그인
- `VITE_PRODUCT_IDS`의 상품 ID와 App Store Connect 등록 상품 일치 필요

### 앱 시나리오

앱 화면 아래쪽 로그 영역에 단계별 결과 표시


| 시나리오       | 방법                                                        | 2단계 기대 결과                            | 3단계 기대 결과                            |
| ---------- | --------------------------------------------------------- | ------------------------------------ | ------------------------------------ |
| 상품 조회      | "상품 조회" 버튼                                                | `상품 3개 조회`                           | `상품 N개 조회` (App Store Connect 등록 상품) |
| 구매         | 구매 버튼, 결제 시트에서 확인                                         | `결제 완료, 서버 검증 요청` 후 `서버 검증 실패 (422)` | `결제 완료, 서버 검증 요청` 후 `✅ 지급 완료`        |
| 구매 취소      | 결제 시트에서 취소                                                | `사용자가 구매 취소`                         | `사용자가 구매 취소`                         |
| 미완료 거래 재처리 | 2단계는 구매 직후, 3단계는 서버 종료 상태로 구매 후 서버 재시작, 이후 "미완료 거래 처리" 버튼 | `미완료 거래 N건`, 거래별 `서버 검증 실패 (422)`    | `미완료 거래 N건`, 거래별 `✅ 지급 완료`           |
| 중복 지급 방지   | 같은 거래 재검증 요청                                              | 해당 없음                                | `apple_purchases` 행 추가 없음            |


서버에 저장된 거래 확인

```bash
cd laravel
php artisan tinker --execute="App\Models\ApplePurchase::all()->each(fn (\$p) => print(\$p->toJson().PHP_EOL));"
```

상품 조회 결과 0개일 때 확인 항목

- 2단계는 스킴의 StoreKit Configuration 연결 여부, `VITE_PRODUCT_IDS`와 `IAPTest.storekit`의 상품 ID 일치
- 3단계는 유료 앱 계약 상태, `VITE_PRODUCT_IDS`, Bundle ID 일치, 기기의 Sandbox 계정 로그인

## Apple 측 준비 (3단계 이상)

- App Store Connect 유료 앱 계약 활성화 (세금 양식, 은행 계좌)
- 앱의 인앱 구입 상품 등록 (제출 준비 완료 상태 이상)
- 사용자 및 액세스의 샌드박스 탭에서 테스터 계정 생성, 실기기 설정의 App Store 항목 중 Sandbox 계정에 로그인

## 테스트 시 주의

- 서버의 서명 검증 확인은 3단계(실기기와 Sandbox 계정)에서만 가능
- 실기기의 첫 서버 요청 시 로컬 네트워크 접근 권한 허용 필요 (`Info.plist`의 `NSAllowsLocalNetworking`은 로컬 네트워크 한정 http 접속 허용 설정)
- TestFlight와 App Store 심사 결제는 운영 빌드에서도 Sandbox 환경 (운영 서버 `APPLE_IAP_ENVIRONMENT`의 `Production` 고정 시 심사 중 결제 실패, 운영 배포 전 처리 방식 결정 필요)
- 지급 로직 미구현 (`AppleIapController`의 `wasRecentlyCreated` 분기)