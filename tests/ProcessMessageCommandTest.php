<?php

namespace Zimmo\RabbitMQCliBridge\Tests;

use Exception;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Zimmo\RabbitMQCliBridge\ProcessMessageCommand;

class ProcessMessageCommandTest extends TestCase
{
    const TEST_COMMAND_NAME = 'test_command_name';

    /**
     * base64 encoded value of
     *
     * {
     *   "properties": {
     *     "application_headers": {
     *       "name": "value"
     *     },
     *     "content_type": "",
     *     "content_encoding": "",
     *     "delivery_mode": 1,
     *     "priority": 0,
     *     "correlation_id": "",
     *     "reply_to": "",
     *     "expiration": "",
     *     "message_id": "",
     *     "timestamp": "0001-01-01T00:00:00Z",
     *     "type": "",
     *     "user_id": "",
     *     "app_id": ""
     *   },
     *   "delivery_info": {
     *     "message_count": 0,
     *     "consumer_tag": "ctag-./rabbitmq-cli-consumer-1",
     *     "delivery_tag": 2,
     *     "redelivered": true,
     *     "exchange": "example",
     *     "routing_key": ""
     *   },
     *   "body": "foo"
     * }
     */
    const TEST_MESSAGE = 'ewogICJwcm9wZXJ0aWVzIjogewogICAgImFwcGxpY2F0aW9uX2hlYWRlcnMiOiB7CiAgICAgICJuYW1lIjogInZhbHVlIgogICAgfSwKICAgICJjb250ZW50X3R5cGUiOiAiIiwKICAgICJjb250ZW50X2VuY29kaW5nIjogIiIsCiAgICAiZGVsaXZlcnlfbW9kZSI6IDEsCiAgICAicHJpb3JpdHkiOiAwLAogICAgImNvcnJlbGF0aW9uX2lkIjogIiIsCiAgICAicmVwbHlfdG8iOiAiIiwKICAgICJleHBpcmF0aW9uIjogIiIsCiAgICAibWVzc2FnZV9pZCI6ICIiLAogICAgInRpbWVzdGFtcCI6ICIwMDAxLTAxLTAxVDAwOjAwOjAwWiIsCiAgICAidHlwZSI6ICIiLAogICAgInVzZXJfaWQiOiAiIiwKICAgICJhcHBfaWQiOiAiIgogIH0sCiAgImRlbGl2ZXJ5X2luZm8iOiB7CiAgICAibWVzc2FnZV9jb3VudCI6IDAsCiAgICAiY29uc3VtZXJfdGFnIjogImN0YWctLi9yYWJiaXRtcS1jbGktY29uc3VtZXItMSIsCiAgICAiZGVsaXZlcnlfdGFnIjogMiwKICAgICJyZWRlbGl2ZXJlZCI6IHRydWUsCiAgICAiZXhjaGFuZ2UiOiAiZXhhbXBsZSIsCiAgICAicm91dGluZ19rZXkiOiAiIgogIH0sCiAgImJvZHkiOiAiZm9vIgp9%';

    /** @var ConsumerInterface | MockObject */
    private $consumer;

    /** @var ProcessMessageCommand */
    private $command;

    /** @var CommandTester */
    private $commandTester;

    protected function setUp()
    {
        $this->consumer = $this->createMock(ConsumerInterface::class);
        $this->command = new ProcessMessageCommand(self::TEST_COMMAND_NAME, $this->consumer);

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($application->find(self::TEST_COMMAND_NAME));
    }

    public function testCommandReturnsAckWhenConsumerReturnsAck()
    {
        $this->consumer->method('execute')->willReturn(ConsumerInterface::MSG_ACK);

        $this->commandTester->execute(
            [
                'command' => self::TEST_COMMAND_NAME,
                'message' => self::TEST_MESSAGE,
            ]
        );

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertEquals(ProcessMessageCommand::ACK, $statusCode);
    }

    public function testCommandReturnsRejectWhenConsumerThrowsException()
    {
        $this->consumer->method('execute')->willThrowException(new Exception());

        $this->commandTester->execute(
            [
                'command' => self::TEST_COMMAND_NAME,
                'message' => self::TEST_MESSAGE,
            ]
        );

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertEquals(ProcessMessageCommand::REJECT, $statusCode);
    }

    public function testCommandReturnsRejectWhenConsumerReturnsReject()
    {
        $this->consumer->method('execute')->willReturn(ConsumerInterface::MSG_REJECT);

        $this->commandTester->execute(
            [
                'command' => self::TEST_COMMAND_NAME,
                'message' => self::TEST_MESSAGE,
            ]
        );

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertEquals(ProcessMessageCommand::REJECT, $statusCode);
    }

    public function testCommandReturnsRejectAndRequeueWhenConsumerReturnsRejectAndRequeue()
    {
        $this->consumer->method('execute')->willReturn(ConsumerInterface::MSG_REJECT_REQUEUE);

        $this->commandTester->execute(
            [
                'command' => self::TEST_COMMAND_NAME,
                'message' => self::TEST_MESSAGE,
            ]
        );

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertEquals(ProcessMessageCommand::REJECT_REQUEUE, $statusCode);
    }

    public function testCommandReturnsRejectWhenConsumerReturnsUnknownValue()
    {
        $this->consumer->method('execute')->willReturn('bar');

        $this->commandTester->execute(
            [
                'command' => self::TEST_COMMAND_NAME,
                'message' => self::TEST_MESSAGE,
            ]
        );

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertEquals(ProcessMessageCommand::REJECT, $statusCode);
    }

    public function testCommandCreatesAndDispatchesAMQPMessage()
    {
        $this->consumer->expects($this->once())
                       ->method('execute')
                       ->with(
                           $this->callback(
                               function ($message) {
                                   return $message instanceof AMQPMessage
                                          && $message->getBody() === 'foo';
                               }
                           )
                       );

        $this->commandTester->execute(
            [
                'command' => self::TEST_COMMAND_NAME,
                'message' => self::TEST_MESSAGE,
            ]
        );

        $statusCode = $this->commandTester->getStatusCode();
        $this->assertEquals(ProcessMessageCommand::REJECT, $statusCode);
    }
}
