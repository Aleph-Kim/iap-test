<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('iap:test-token', function () {
    // 토큰 발급 전용 사용자 (로그인 미사용으로 임의 비밀번호)
    $user = User::firstOrCreate(
        ['email' => config('services.apple_iap.test_user_email')],
        ['name' => 'IAP Tester', 'password' => Str::random(32)],
    );

    $this->line($user->createToken('iap-test')->plainTextToken);
})->purpose('IAP 테스트 사용자 Sanctum 토큰 발급');
