<?php

namespace NightOwl\Agent;

use Aws\Sqs\SqsClient;

/**
 * Polls an SQS queue as a durable, decoupled alternative front door to the
 * TCP/UDP listeners in AsyncServer — apps publish batched telemetry here
 * instead of (or alongside) a direct TCP connection, so agent downtime no
 * longer blocks or loses live request traffic.
 *
 * Message body format: the same JSON array of records the TCP path's
 * PayloadParser produces as `rawPayload` — no wire envelope
 * (`length:version:tokenHash:`) and no gzip. The TCP envelope's token hash
 * exists to authenticate an otherwise-unauthenticated raw socket; SQS already
 * authenticates the producer via IAM (only a role with `sqs:SendMessage` on
 * this queue can publish), so re-deriving it here is redundant. This means
 * the SQS and TCP paths share the same sink (SqliteBuffer::appendRaw) but not
 * the same framing — PayloadParser is not involved on this path at all.
 */
final class SqsPoller
{
    public function __construct(
        private SqsClient $client,
        private string $queueUrl,
        private int $maxMessages = 10, // SQS ReceiveMessage hard cap
        private int $waitTimeSeconds = 2,
    ) {
    }

    /**
     * One poll cycle: receive up to $maxMessages, appendRaw() each message
     * body verbatim into the buffer, then delete only the messages that were
     * successfully buffered. A message whose body fails to buffer is logged
     * and left undeleted — SQS's own redrive policy moves it to the
     * dead-letter queue after its configured max receive count, rather than
     * this poller silently dropping it.
     */
    public function poll(?RawPayloadAppender $buffer): void
    {
        if ($buffer === null) {
            return;
        }

        $result = $this->client->receiveMessage([
            'QueueUrl' => $this->queueUrl,
            'MaxNumberOfMessages' => $this->maxMessages,
            'WaitTimeSeconds' => $this->waitTimeSeconds,
        ]);

        $messages = $result->get('Messages') ?? [];
        if ($messages === []) {
            return;
        }

        $toDelete = [];

        foreach ($messages as $message) {
            try {
                $buffer->appendRaw($message['Body']);
                $toDelete[] = [
                    'Id' => $message['MessageId'],
                    'ReceiptHandle' => $message['ReceiptHandle'],
                ];
            } catch (\Throwable $e) {
                error_log("[NightOwl Agent] SQS message {$message['MessageId']} failed to buffer, leaving for redrive: {$e->getMessage()}");
            }
        }

        if ($toDelete === []) {
            return;
        }

        $this->client->deleteMessageBatch([
            'QueueUrl' => $this->queueUrl,
            'Entries' => $toDelete,
        ]);
    }
}
