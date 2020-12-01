<?php

namespace RTippin\Messenger\Http\Controllers\Actions;

use RTippin\Messenger\Actions\Calls\LeaveCall as LeaveCallAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use RTippin\Messenger\Models\Call;
use RTippin\Messenger\Models\Thread;

class LeaveCall
{
    use AuthorizesRequests;

    /**
     * Is the thread unread for current participant?
     *
     * @param LeaveCallAction $leaveCall
     * @param Thread $thread
     * @param Call $call
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function __invoke(LeaveCallAction $leaveCall,
                             Thread $thread,
                             Call $call)
    {
        $this->authorize('leave', [
            $call,
            $thread
        ]);

        return $leaveCall->execute(
            $call,
            $call->currentCallParticipant()
        )->getMessageResponse();
    }
}