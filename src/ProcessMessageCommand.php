<?php

namespace Zimmo\RabbitMQCliBridge;

use Exception;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessMessageCommand extends Command
{
    CONST ACK            = 0;
    CONST REJECT         = 3;
    CONST REJECT_REQUEUE = 4;

    /** @var ConsumerInterface */
    private $consumer;

    public function __construct(string $name, ConsumerInterface $consumer)
    {
        parent::__construct($name);
        $this->consumer = $consumer;
    }

    protected function configure()
    {
        $this->addArgument('message', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $data = json_decode(base64_decode($input->getArgument('message')), true);
            $message = new AMQPMessage($data['body'], $data['properties']);
            $result = $this->consumer->execute($message);
        } catch (Exception $exception) {
            return self::REJECT;
        }

        if ($result === ConsumerInterface::MSG_ACK) {
            return self::ACK;
        }

        if ($result === ConsumerInterface::MSG_REJECT) {
            return self::REJECT;
        }

        if ($result === ConsumerInterface::MSG_REJECT_REQUEUE) {
            return self::REJECT_REQUEUE;
        }

        return self::REJECT;
    }
}
