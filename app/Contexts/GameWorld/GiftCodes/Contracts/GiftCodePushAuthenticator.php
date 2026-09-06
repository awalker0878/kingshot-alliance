<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Contracts;

use Illuminate\Http\Request;

interface GiftCodePushAuthenticator
{
    /**
     * @return array{provider_event_id:?string,provider_item_id:?string,replay_key:string,payload_sha256:string,correlation_id:?string}
     */
    public function authenticate(Request $request): array;
}
