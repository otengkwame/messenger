<?php

namespace RTippin\Messenger\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RTippin\Messenger\Contracts\MessengerProvider;
use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Traits\Uuids;

/**
 * App\Models\Messages\MessageReaction.
 *
 * @property string $id
 * @property string $owner_type
 * @property string|int $owner_id
 * @property string $message_id
 * @property string $reaction
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \RTippin\Messenger\Models\Message $message
 * @mixin Model|\Eloquent
 */
class MessageReaction extends Model
{
    use Uuids;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'message_reactions';

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var string
     */
    public $keyType = 'string';

    /**
     * The attributes that can be set with Mass Assignment.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at'];

    /**
     * @return BelongsTo|Message
     */
    public function message()
    {
        return $this->belongsTo(
            Message::class,
            'message_id',
            'id'
        );
    }

    /**
     * @return MorphTo|MessengerProvider
     */
    public function owner()
    {
        return $this->morphTo()->withDefault(function () {
            return Messenger::getGhostProvider();
        });
    }
}