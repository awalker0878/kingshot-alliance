<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $gift_code_source_id
 * @property string|null $subscription_after_id
 * @property string|null $subscription_through_id
 * @property int $recipient_after_id
 * @property int|null $recipient_through_id
 */
final class GiftCodeSourceAlertProgress extends Model
{
    protected $table = 'gift_code_source_alert_progress';

    protected $primaryKey = 'gift_code_source_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['recipient_after_id' => 'integer', 'recipient_through_id' => 'integer'];
    }
}
