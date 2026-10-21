# Laravel 설정

## routes/api.php

```php
use App\Http\Controllers\AppleIapController;

Route::post('/iap/apple/verify', [AppleIapController::class, 'verify'])->middleware('auth:sanctum');
```

## config/services.php

```php
'apple_iap' => [
    'bundle_id' => env('APPLE_BUNDLE_ID'),
    'environment' => env('APPLE_IAP_ENVIRONMENT', 'Sandbox'), // 운영 서버는 Production 사용
    'root_cert' => storage_path('app/apple/AppleRootCA-G3.cer'),
],
```

## .env

```
APPLE_BUNDLE_ID=com.example.app
APPLE_IAP_ENVIRONMENT=Sandbox
```

## Apple 루트 인증서

https://www.apple.com/certificateauthority/ 의 Apple Root CA - G3 인증서를 받아
`storage/app/apple/AppleRootCA-G3.cer` 에 저장

## 마이그레이션

```bash
php artisan migrate
```
