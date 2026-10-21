<?php

namespace App\Support;

use InvalidArgumentException;
use JsonException;

final class AppleJwsVerifier
{
    private const LEAF_OID = '1.2.840.113635.100.6.11.1';
    private const INTERMEDIATE_OID = '1.2.840.113635.100.6.2.1';

    public function __construct(private readonly string $rootCertPath)
    {
    }

    /**
     * StoreKit 2 거래(JWS) 서명 검증 (Apple 공식 App Store Server Library의 SignedDataVerifier와 같은 순서)
     */
    public function verify(string $jws): array
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid JWS format');
        }
        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJson($encodedHeader);
        $payload = $this->decodeJson($encodedPayload);

        if (($header['alg'] ?? null) !== 'ES256') {
            throw new InvalidArgumentException('Unsupported algorithm');
        }

        $x5c = $header['x5c'] ?? [];
        if (count($x5c) !== 3) {
            throw new InvalidArgumentException('Invalid certificate chain');
        }

        $leaf = $this->toPem($x5c[0]);
        $intermediate = $this->toPem($x5c[1]);
        $root = $this->toPem(base64_encode($this->readRootCert()));

        $this->verifyChain($leaf, $intermediate, $root);
        $this->verifyOid($leaf, self::LEAF_OID);
        $this->verifyOid($intermediate, self::INTERMEDIATE_OID);

        $signedAt = intdiv((int) ($payload['signedDate'] ?? 0), 1000) ?: time();
        $this->verifyValidity($leaf, $signedAt);
        $this->verifyValidity($intermediate, $signedAt);

        $this->verifySignature("{$encodedHeader}.{$encodedPayload}", $encodedSignature, $leaf);

        return $payload;
    }

    private function verifyChain(string $leaf, string $intermediate, string $root): void
    {
        if (openssl_x509_verify($leaf, $intermediate) !== 1 || openssl_x509_verify($intermediate, $root) !== 1) {
            throw new InvalidArgumentException('Certificate chain verification failed');
        }
    }

    private function verifyOid(string $certificate, string $oid): void
    {
        $extensions = openssl_x509_parse($certificate)['extensions'] ?? [];

        if (! array_key_exists($oid, $extensions)) {
            throw new InvalidArgumentException("Missing certificate OID {$oid}");
        }
    }

    private function verifyValidity(string $certificate, int $timestamp): void
    {
        $parsed = openssl_x509_parse($certificate);

        if ($timestamp < $parsed['validFrom_time_t'] || $timestamp > $parsed['validTo_time_t']) {
            throw new InvalidArgumentException('Certificate expired or not yet valid');
        }
    }

    private function verifySignature(string $data, string $encodedSignature, string $leaf): void
    {
        $signature = $this->rawSignatureToDer($this->base64UrlDecode($encodedSignature));

        if (openssl_verify($data, $signature, openssl_pkey_get_public($leaf), OPENSSL_ALGO_SHA256) !== 1) {
            throw new InvalidArgumentException('Signature verification failed');
        }
    }

    /**
     * JWS ES256 서명(r||s 64바이트)을 openssl_verify가 요구하는 DER 형식으로 변환
     */
    private function rawSignatureToDer(string $raw): string
    {
        if (strlen($raw) !== 64) {
            throw new InvalidArgumentException('Invalid signature length');
        }

        $sequence = $this->derInteger(substr($raw, 0, 32)) . $this->derInteger(substr($raw, 32));

        return "\x30" . chr(strlen($sequence)) . $sequence;
    }

    private function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '' || ord($bytes[0]) > 0x7F) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . chr(strlen($bytes)) . $bytes;
    }

    private function decodeJson(string $encoded): array
    {
        try {
            return json_decode($this->base64UrlDecode($encoded), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Invalid JSON in JWS');
        }
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64url');
        }

        return $decoded;
    }

    private function toPem(string $base64Der): string
    {
        return "-----BEGIN CERTIFICATE-----\n" . chunk_split($base64Der, 64, "\n") . "-----END CERTIFICATE-----\n";
    }

    private function readRootCert(): string
    {
        $der = @file_get_contents($this->rootCertPath);

        if ($der === false) {
            throw new InvalidArgumentException('Apple root certificate not found');
        }

        return $der;
    }
}
