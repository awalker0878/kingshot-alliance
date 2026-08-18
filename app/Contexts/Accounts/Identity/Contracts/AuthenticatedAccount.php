<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Identity\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Accounts-owned HTTP authentication boundary.
 *
 * Business contexts may depend on this contract when adapting the authenticated
 * platform account at the request edge. They must not import the Accounts User
 * Eloquent model or carry it into domain/application actions.
 *
 * @property-read int $id
 * @property-read string $name
 * @property-read string $email
 * @property-read string $timezone
 */
interface AuthenticatedAccount extends Authenticatable, MustVerifyEmail
{
}
