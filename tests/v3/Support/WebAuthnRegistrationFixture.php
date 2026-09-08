<?php

declare(strict_types=1);

namespace Tests\v3\Support;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\TextStringObject;
use Laravel\Passkeys\Support\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;

final class WebAuthnRegistrationFixture
{
    public static function credential(
        PublicKeyCredentialCreationOptions $options,
        string $credentialId = 'synthetic-registration-credential',
        string $origin = 'https://accounts.example.test',
        ?string $challenge = null,
        bool $userVerified = true,
        ?string $cosePublicKey = null,
    ): PublicKeyCredential {
        return WebAuthn::fromJson(json_encode(self::browserCredential(
            $options, $credentialId, $origin, $challenge, $userVerified, $cosePublicKey,
        ), JSON_THROW_ON_ERROR), PublicKeyCredential::class);
    }

    /** @return array<string,mixed> */
    public static function browserCredential(
        PublicKeyCredentialCreationOptions $options,
        string $credentialId = 'synthetic-registration-credential',
        string $origin = 'https://accounts.example.test',
        ?string $challenge = null,
        bool $userVerified = true,
        ?string $cosePublicKey = null,
    ): array {
        // Public P-256 generator point in a COSE ES256 key; no private key or
        // hardware attestation is involved in this synthetic browser response.
        $publicKey = $cosePublicKey ?? hex2bin('a5010203262001215820'
            .'6b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c296'
            .'225820'
            .'4fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5');
        $authenticatorData = hash('sha256', (string) $options->rp->id, true)
            .chr($userVerified ? 0x45 : 0x41).pack('N', 0)
            .str_repeat("\0", 16).pack('n', strlen($credentialId)).$credentialId.$publicKey;
        $attestation = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authenticatorData));
        $clientData = json_encode([
            'type' => 'webauthn.create',
            'challenge' => Base64UrlSafe::encodeUnpadded($challenge ?? $options->challenge),
            'origin' => $origin,
            'crossOrigin' => false,
        ], JSON_THROW_ON_ERROR);

        return [
            'id' => Base64UrlSafe::encodeUnpadded($credentialId),
            'rawId' => Base64UrlSafe::encodeUnpadded($credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientData),
                'attestationObject' => Base64UrlSafe::encodeUnpadded((string) $attestation),
                'transports' => ['internal'],
            ],
        ];
    }
}
