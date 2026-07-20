<?php

namespace NightOwl\Tests\Unit;

use Aws\ResultInterface;
use Aws\Sqs\SqsClient;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use NightOwl\Agent\RawPayloadAppender;
use NightOwl\Agent\SqsPoller;
use PHPUnit\Framework\TestCase;

class SqsPollerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_poll_is_a_noop_when_buffer_is_null(): void
    {
        $client = Mockery::mock(SqsClient::class);
        $client->shouldNotReceive('receiveMessage');

        $poller = new SqsPoller($client, 'https://sqs.example/queue');

        $poller->poll(null);
    }

    public function test_poll_is_a_noop_on_empty_receive(): void
    {
        $client = Mockery::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->andReturn(
            $this->resultReturning([])
        );
        $client->shouldNotReceive('deleteMessageBatch');

        $buffer = Mockery::mock(RawPayloadAppender::class);
        $buffer->shouldNotReceive('appendRaw');

        $poller = new SqsPoller($client, 'https://sqs.example/queue');

        $poller->poll($buffer);
    }

    public function test_poll_appends_each_message_body_verbatim_and_deletes_the_batch(): void
    {
        $messages = [
            ['MessageId' => 'm1', 'ReceiptHandle' => 'rh1', 'Body' => '[{"t":"request"}]'],
            ['MessageId' => 'm2', 'ReceiptHandle' => 'rh2', 'Body' => '[{"t":"query"}]'],
        ];

        $client = Mockery::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->andReturn(
            $this->resultReturning($messages)
        );

        $buffer = Mockery::mock(RawPayloadAppender::class);
        $buffer->shouldReceive('appendRaw')->once()->with('[{"t":"request"}]');
        $buffer->shouldReceive('appendRaw')->once()->with('[{"t":"query"}]');

        $client->shouldReceive('deleteMessageBatch')->once()->with(Mockery::on(
            function (array $args) {
                return $args['QueueUrl'] === 'https://sqs.example/queue'
                    && $args['Entries'] === [
                        ['Id' => 'm1', 'ReceiptHandle' => 'rh1'],
                        ['Id' => 'm2', 'ReceiptHandle' => 'rh2'],
                    ];
            }
        ));

        $poller = new SqsPoller($client, 'https://sqs.example/queue');

        $poller->poll($buffer);
    }

    public function test_poll_leaves_a_malformed_message_undeleted_for_redrive(): void
    {
        $messages = [
            ['MessageId' => 'good', 'ReceiptHandle' => 'rh-good', 'Body' => '[{"t":"request"}]'],
            ['MessageId' => 'bad', 'ReceiptHandle' => 'rh-bad', 'Body' => 'not valid'],
        ];

        $client = Mockery::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->andReturn(
            $this->resultReturning($messages)
        );

        $buffer = Mockery::mock(RawPayloadAppender::class);
        $buffer->shouldReceive('appendRaw')->once()->with('[{"t":"request"}]');
        $buffer->shouldReceive('appendRaw')->once()->with('not valid')->andThrow(
            new \RuntimeException('failed to buffer')
        );

        $client->shouldReceive('deleteMessageBatch')->once()->with(Mockery::on(
            function (array $args) {
                return $args['Entries'] === [
                    ['Id' => 'good', 'ReceiptHandle' => 'rh-good'],
                ];
            }
        ));

        $poller = new SqsPoller($client, 'https://sqs.example/queue');

        $poller->poll($buffer);
    }

    public function test_poll_deletes_nothing_when_every_message_fails_to_buffer(): void
    {
        $messages = [
            ['MessageId' => 'bad', 'ReceiptHandle' => 'rh-bad', 'Body' => 'not valid'],
        ];

        $client = Mockery::mock(SqsClient::class);
        $client->shouldReceive('receiveMessage')->once()->andReturn(
            $this->resultReturning($messages)
        );
        $client->shouldNotReceive('deleteMessageBatch');

        $buffer = Mockery::mock(RawPayloadAppender::class);
        $buffer->shouldReceive('appendRaw')->once()->andThrow(
            new \RuntimeException('failed to buffer')
        );

        $poller = new SqsPoller($client, 'https://sqs.example/queue');

        $poller->poll($buffer);
    }

    private function resultReturning(array $messages): ResultInterface
    {
        $result = Mockery::mock(ResultInterface::class);
        $result->shouldReceive('get')->with('Messages')->andReturn($messages);

        return $result;
    }
}
