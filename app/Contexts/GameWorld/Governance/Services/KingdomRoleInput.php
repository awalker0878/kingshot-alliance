<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class KingdomRoleInput
{
    /** @param array<mixed> $permissionKeys
     * @return array{name:string,key:string,description:?string,permissions:list<string>}
     */
    public function definition(string $name, ?string $description, array $permissionKeys): array
    {
        $name = trim($name);
        $description = $description === null ? null : trim($description);
        $key = Str::slug($name);
        if ($name === '' || mb_strlen($name) > 100 || $key === '') {
            throw ValidationException::withMessages(['name' => 'A meaningful Kingdom role name of at most 100 characters is required.']);
        }
        if ($description !== null && mb_strlen($description) > 255) {
            throw ValidationException::withMessages(['description' => 'The Kingdom role description must be at most 255 characters.']);
        }
        if (! array_is_list($permissionKeys) || count($permissionKeys) > 50) {
            throw ValidationException::withMessages(['permissions' => 'Choose a list of at most 50 permissions.']);
        }
        $keys = [];
        foreach ($permissionKeys as $permission) {
            if (! is_string($permission) || trim($permission) === '' || mb_strlen($permission) > 100) {
                throw ValidationException::withMessages(['permissions' => 'Every permission must be a recognized key of at most 100 characters.']);
            }
            $keys[] = trim($permission);
        }
        if (strlen($key) > 64) {
            $key = rtrim(substr($key, 0, 48), '-').'-'.substr(hash('sha256', $name), 0, 12);
        }

        return ['name' => $name, 'key' => $key, 'description' => $description, 'permissions' => array_values(array_unique($keys))];
    }

    public function date(?string $value, string $field): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        Validator::make([$field => $value], [$field => ['required', 'string', 'max:100', 'date']])->validate();
        $date = Carbon::parse($value)->utc();
        if ($date->year < 1 || $date->year > 9999) {
            throw ValidationException::withMessages([$field => 'Choose a date in the supported calendar range.']);
        }

        return $date;
    }

    public function reason(?string $reason): ?string
    {
        $reason = $reason === null ? null : trim($reason);
        if ($reason !== null && mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'A Kingdom role reason must be at most 500 characters.']);
        }

        return $reason;
    }
}
