<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

// Declare the 'Adewale' queue
$channel->queue_declare('Adewale', false, true, false, false);

$task = [
    'type' => 'email',
    'recipient' => 'adewalea354@gmail.com',
    'message' => 'Hello, this is a test message.'
];

$messageBody = json_encode($task);
$message = new AMQPMessage(
    $messageBody,
    array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT)
);

// Publish to the 'Adewale' queue
$channel->basic_publish($message, '', 'Adewale');
echo " [x] Sent task to 'Adewale' queue\n";

$channel->close();
$connection->close();
