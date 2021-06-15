<?php

namespace RTippin\Messenger\Actions\Messages;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use RTippin\Messenger\Actions\BaseMessengerAction;
use RTippin\Messenger\Broadcasting\ReactionRemovedBroadcast;
use RTippin\Messenger\Contracts\BroadcastDriver;
use RTippin\Messenger\Events\ReactionRemovedEvent;
use RTippin\Messenger\Exceptions\FeatureDisabledException;
use RTippin\Messenger\Messenger;
use RTippin\Messenger\Models\Message;
use RTippin\Messenger\Models\MessageReaction;
use RTippin\Messenger\Models\Thread;
use Throwable;

class RemoveReaction extends BaseMessengerAction
{
    /**
     * @var BroadcastDriver
     */
    private BroadcastDriver $broadcaster;

    /**
     * @var DatabaseManager
     */
    private DatabaseManager $database;

    /**
     * @var Dispatcher
     */
    private Dispatcher $dispatcher;

    /**
     * @var Messenger
     */
    private Messenger $messenger;

    /**
     * @var MessageReaction
     */
    private MessageReaction $reaction;

    /**
     * @var int
     */
    private int $reactionsCount;

    /**
     * RemoveReaction constructor.
     *
     * @param BroadcastDriver $broadcaster
     * @param DatabaseManager $database
     * @param Dispatcher $dispatcher
     * @param Messenger $messenger
     */
    public function __construct(BroadcastDriver $broadcaster,
                                DatabaseManager $database,
                                Dispatcher $dispatcher,
                                Messenger $messenger)
    {
        $this->broadcaster = $broadcaster;
        $this->database = $database;
        $this->dispatcher = $dispatcher;
        $this->messenger = $messenger;
    }

    /**
     * Remove a reaction from the given message.
     *
     * @param mixed ...$parameters
     * @var Thread[0]
     * @var Message[1]
     * @var MessageReaction[2]
     * @return $this
     * @throws Throwable|FeatureDisabledException
     */
    public function execute(...$parameters): self
    {
        $this->isReactionsEnabled();

        $this->setThread($parameters[0])->setMessage($parameters[1]);
        $this->reaction = $parameters[2];
        $this->reactionsCount = $this->getMessage()->reactions()->count();

        $this->handleTransactions()
            ->fireBroadcast()
            ->fireEvents();

        return $this;
    }

    /**
     * @return void
     * @throws FeatureDisabledException
     */
    private function isReactionsEnabled(): void
    {
        if (! $this->messenger->isMessageReactionsEnabled()) {
            throw new FeatureDisabledException('Message reactions are currently disabled.');
        }
    }

    /**
     * @return $this
     * @throws Throwable
     */
    private function handleTransactions(): self
    {
        if ($this->isChained() || $this->reactionsCount > 1) {
            $this->removeReaction();
        } else {
            $this->database->transaction(fn () => $this->removeReaction(), 3);
        }

        return $this;
    }

    /**
     * @return array
     */
    private function generateBroadcastResource(): array
    {
        return [
            'id' => $this->reaction->id,
            'message_id' => $this->getMessage()->id,
            'reaction' => $this->reaction->reaction,
        ];
    }

    /**
     * @return $this
     */
    private function fireBroadcast(): self
    {
        if ($this->shouldFireBroadcast()) {
            $this->broadcaster
                ->toPresence($this->getThread())
                ->with($this->generateBroadcastResource())
                ->broadcast(ReactionRemovedBroadcast::class);

            $this->checkBroadcastToMessageOwner();
        }

        return $this;
    }

    /**
     * Only broadcast to message owner if the current provider is not
     * the message owner and the owner is still in the thread.
     *
     * @return void
     */
    private function checkBroadcastToMessageOwner(): void
    {
        if ((string) $this->messenger->getProvider()->getKey() === (string) $this->getMessage()->owner_id
            && $this->messenger->getProvider()->getMorphClass() === $this->getMessage()->owner_type) {
            // We are the owner, break;
            return;
        }

        // Only broadcast if participant still in thread.
        if ($this->getThread()->participants()->forProviderWithModel($this->getMessage())->exists()) {
            $this->broadcaster
                ->to($this->getMessage()->owner)
                ->with($this->generateBroadcastResource())
                ->broadcast(ReactionRemovedBroadcast::class);
        }
    }

    /**
     * @return void
     */
    private function fireEvents(): void
    {
        if ($this->shouldFireEvents()) {
            $this->dispatcher->dispatch(new ReactionRemovedEvent(
                $this->messenger->getProvider()->withoutRelations(),
                $this->reaction->toArray()
            ));
        }
    }

    /**
     * Remove reaction. Mark message as not reacted to when none left.
     *
     * @throws Exception
     */
    private function removeReaction(): void
    {
        $this->reaction->delete();

        if ($this->reactionsCount === 1) {
            $this->getMessage()->update([
                'reacted' => false,
            ]);
        }
    }
}
