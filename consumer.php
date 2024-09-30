
<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set up error logging
ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/error.log');
ini_set('display_errors', 1); // Display errors for debugging

try {
    // Create a connection to RabbitMQ
    $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
    $channel = $connection->channel();

    // Declare the queue
    $channel->queue_declare('Adewale', false, true, false, false);

    echo " [*] Waiting for messages. To exit press CTRL+C\n";

    // Set up the callback function to process messages
    $callback = function($msg) {
        echo " [x] Received message: " . $msg->body . "\n";
        $task = json_decode($msg->body, true);
        $taskSuccess = false;
        $retryCount = 0;
        $maxRetries = 3; // Set max retries

        // Check if application_headers and x-retry-count exist
        if ($msg->has('application_headers')) {
            $headers = $msg->get('application_headers')->getNativeData();
            if (isset($headers['x-retry-count'])) {
                $retryCount = $headers['x-retry-count'];
            }
        }

        try {
            // Process the task based on the type
            switch ($task['type']) {
                case 'email':
                    // Send email using PHPMailer
                    $mail = new PHPMailer(true);
                    $mail->SMTPDebug = 2;
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'adewale2024@gmail.com';
                    $mail->Password = 'hzkfzygrjhiaoxkf';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->setFrom('adewale2024@gmail.com', 'Mailer');
                    $mail->addAddress($task['recipient']);
                    $mail->addReplyTo('adewalea354@gmail.com', 'Information');
                    $mail->isHTML(true);
                    $mail->Subject = 'RabbitMQ Task Email';
                    $mail->Body = $task['message'];
                    $mail->AltBody = strip_tags($task['message']);

                    if ($mail->send()) {
                        echo " [x] Email sent successfully to " . $task['recipient'] . "\n";
                        $taskSuccess = true;
                    } else {
                        echo " [x] Email sending failed: " . $mail->ErrorInfo . "\n";
                    }
                    break;
                case 'sms':
                    // Placeholder for SMS logic
                    $taskSuccess = true;
                    break;
                case 'db':
                    // Placeholder for DB logic
                    $taskSuccess = true;
                    break;
                default:
                    echo " [x] Unknown task type: " . $task['type'] . "\n";
                    $taskSuccess = false;
                    break;
            }
        } catch (Exception $e) {
            echo " [x] Task failed: " . $e->getMessage() . "\n";
            $taskSuccess = false;
        }

        // Handle retries and acknowledgments
        if ($taskSuccess) {
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        } else {
            if ($retryCount < $maxRetries) {
                echo " [x] Task failed. Retrying...\n";
                $headers = new AMQPTable(['x-retry-count' => $retryCount + 1]);
                $newMessage = new AMQPMessage($msg->body, array(
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'application_headers' => $headers
                ));
                $msg->delivery_info['channel']->basic_reject($msg->delivery_info['delivery_tag'], true);
                $msg->delivery_info['channel']->basic_publish($newMessage, '', 'Adewale');
            } else {
                echo " [x] Max retries reached. Discarding the message.\n";
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            }
        }
    };

    // Set up the consumer
    $channel->basic_qos(null, 1, null);
    $channel->basic_consume('Adewale', '', false, false, false, false, $callback);

    // Start consuming messages 
    while ($channel->is_consuming()) {
        $channel->wait();
    }
} catch (Exception $e) {
    echo " [x] Exception: " . $e->getMessage() . "\n";
    error_log($e->getMessage());
}
