<?php

declare(strict_types=1);

namespace Tests\Support;

use Laravel\Passkeys\Support\WebAuthn;
use OpenSSLAsymmetricKey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;
use SensitiveParameter;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

final readonly class WebAuthnAssertionFixture
{
    public function __construct(
        #[SensitiveParameter] private OpenSSLAsymmetricKey $key,
        public string $credentialId,
    ) {}

    public static function create(string $credentialId = 'synthetic-assertion-credential'): self
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($key === false) {
            throw new RuntimeException('Unable to generate the ephemeral test authenticator.');
        }

        return new self($key, $credentialId);
    }

    public function registration(PublicKeyCredentialCreationOptions $options): PublicKeyCredential
    {
        $details = openssl_pkey_get_details($this->key);
        if ($details === false) {
            throw new RuntimeException('Unable to read the test authenticator public key.');
        }
        $publicKey = hex2bin('a5010203262001215820').str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)
            .hex2bin('225820').str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        return WebAuthnRegistrationFixture::credential($options, $this->credentialId, cosePublicKey: $publicKey);
    }

    public function assertion(
        PublicKeyCredentialRequestOptions $options,
        string $userHandle,
        string $origin = 'https://accounts.example.test',
        ?string $challenge = null,
        bool $userVerified = true,
        int $counter = 1,
        bool $validSignature = true,
    ): PublicKeyCredential {
        return WebAuthn::fromJson(json_encode($this->browserAssertion(
            $options, $userHandle, $origin, $challenge, $userVerified, $counter, $validSignature,
        ), JSON_THROW_ON_ERROR), PublicKeyCredential::class);
    }

    /** @return array<string,mixed> */
    public function browserAssertion(
        PublicKeyCredentialRequestOptions $options,
        string $userHandle,
        string $origin = 'https://accounts.example.test',
        ?string $challenge = null,
        bool $userVerified = true,
        int $counter = 1,
        bool $validSignature = true,
    ): array {
        $clientData = json_encode([
            'type' => 'webauthn.get',
            'challenge' => Base64UrlSafe::encodeUnpadded($challenge ?? $options->challenge),
            'origin' => $origin,
            'crossOrigin' => false,
        ], JSON_THROW_ON_ERROR);
        $authenticatorData = hash('sha256', (string) $options->rpId, true)
            .chr($userVerified ? 0x05 : 0x01).pack('N', $counter);
        $signed = openssl_sign($authenticatorData.hash('sha256', $clientData, true), $signature,
            $validSignature ? $this->key : self::create()->key, OPENSSL_ALGO_SHA256);
        if (! $signed) {
            throw new RuntimeException('Unable to sign the test assertion.');
        }

        return [
            'id' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'rawId' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientData),
                'authenticatorData' => Base64UrlSafe::encodeUnpadded($authenticatorData),
                'signature' => Base64UrlSafe::encodeUnpadded($signature),
                'userHandle' => Base64UrlSafe::encodeUnpadded($userHandle),
            ],
        ];
    }
}
