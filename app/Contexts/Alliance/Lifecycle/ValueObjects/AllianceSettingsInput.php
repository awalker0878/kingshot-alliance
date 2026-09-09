<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\ValueObjects;

use App\Contexts\Alliance\Lifecycle\Enums\SupportedAllianceLocale;
use DateTimeZone;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AllianceSettingsInput
{
    private const RESERVED_SLUGS = [
        'admin', 'api', 'alliance', 'alliances', 'assistant', 'dashboard', 'events',
        'gift-codes', 'kingdom', 'login', 'logout', 'platform', 'profile', 'public',
        'recruitment', 'register', 'settings',
    ];

    private function __construct(
        public string $name,
        public string $slug,
        public SupportedAllianceLocale $language,
        public string $timezone,
    ) {}

    public static function from(string $name, string $slug, string $language, string $timezone): self
    {
        $name = trim($name);
        $slug = Str::slug($slug);
        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Use an Alliance name between 1 and 120 characters.']);
        }
        if ($slug === '' || strlen($slug) > 120 || in_array($slug, self::RESERVED_SLUGS, true)) {
            throw ValidationException::withMessages(['slug' => 'Choose another Alliance URL name of at most 120 characters.']);
        }
        $locale = SupportedAllianceLocale::tryFrom($language);
        if ($locale === null) {
            throw ValidationException::withMessages(['language' => 'Choose a supported Alliance language.']);
        }
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages(['timezone' => 'Choose a valid IANA timezone.']);
        }

        return new self($name, $slug, $locale, $timezone);
    }

    /** @return array{name:string,slug:string,language:string,timezone:string} */
    public function attributes(): array
    {
        return ['name' => $this->name, 'slug' => $this->slug, 'language' => $this->language->value, 'timezone' => $this->timezone];
    }
}
