<?php

if (!function_exists('pcntl_fork')) {
    echo "OrderReviews SMTP adaptor test skipped: pcntl is unavailable.\n";
    exit(0);
}

require_once dirname(__DIR__) . '/system/library/vendor/isenselabs/orderreviews/mail/smtp.php';
require_once dirname(__DIR__) . '/system/library/vendor/isenselabs/orderreviews/mail.php';

function startFakeSmtpServer($scenario) {
    $errno = 0;
    $error = '';
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

    if (!$server) {
        throw new RuntimeException('Could not start the fake SMTP server.');
    }

    $address = stream_socket_get_name($server, false);
    $port = (int)substr(strrchr($address, ':'), 1);
    $pid = pcntl_fork();

    if ($pid === -1) {
        fclose($server);
        throw new RuntimeException('Could not fork the fake SMTP server.');
    }

    if ($pid === 0) {
        $connection = @stream_socket_accept($server, 5);
        fclose($server);

        if (!$connection) {
            exit(10);
        }

        stream_set_timeout($connection, 5);
        fwrite($connection, "220 fake-smtp ESMTP ready\r\n");
        $dotStuffed = false;

        while (($line = fgets($connection, 2048)) !== false) {
            $command = rtrim($line, "\r\n");

            if (stripos($command, 'EHLO ') === 0) {
                fwrite($connection, "250-fake-smtp\r\n250 PIPELINING\r\n");
            } elseif (stripos($command, 'HELO ') === 0) {
                fwrite($connection, "250 fake-smtp\r\n");
            } elseif (stripos($command, 'MAIL FROM:') === 0) {
                fwrite($connection, "250 2.1.0 Sender accepted\r\n");
            } elseif (stripos($command, 'RCPT TO:') === 0) {
                if ($scenario === 'policy_rejection') {
                    fwrite($connection, "550 5.7.1 Rejected by policy\r\n");
                    break;
                }

                fwrite($connection, "250 2.1.5 Recipient accepted\r\n");
            } elseif (strcasecmp($command, 'DATA') === 0) {
                fwrite($connection, "354 End data with <CR><LF>.<CR><LF>\r\n");

                while (($dataLine = fgets($connection, 4096)) !== false) {
                    $dataLine = rtrim($dataLine, "\r\n");

                    if ($dataLine === '.') {
                        break;
                    }

                    if ($dataLine === '..leading-dot') {
                        $dotStuffed = true;
                    }
                }

                if ($scenario === 'connection_drop') {
                    fclose($connection);
                    exit(0);
                }

                if ($scenario === 'temporary_data_failure') {
                    fwrite($connection, "450 4.3.0 Temporary queue failure\r\n");
                    break;
                }

                fwrite($connection, "250 2.0.0 Queued\r\n");
            } elseif (strcasecmp($command, 'QUIT') === 0) {
                fwrite($connection, "221 2.0.0 Bye\r\n");
                break;
            }
        }

        fclose($connection);

        if ($scenario === 'success' && !$dotStuffed) {
            exit(11);
        }

        exit(0);
    }

    fclose($server);

    return array($port, $pid);
}

function createSmtpMessage($port, $beforeDataTerminator = null) {
    putenv('SERVER_NAME=glazbena-kutija.hr');
    $mail = new Vendor\iSenseLabs\OrderReviews\Mail('smtp');
    $mail->smtp_hostname = '127.0.0.1';
    $mail->smtp_username = '';
    $mail->smtp_password = '';
    $mail->smtp_port = $port;
    $mail->smtp_timeout = 2;
    $mail->setTo('customer@example.com');
    $mail->setFrom('noreply@example.com');
    $mail->setSender('OrderReviews test');
    $mail->setSubject('SMTP adaptor test');
    $mail->setHtml(".leading-dot\n<p>Test</p>");

    if (is_callable($beforeDataTerminator)) {
        $mail->setBeforeDataTerminator($beforeDataTerminator);
    }

    return $mail;
}

function waitForFakeSmtpServer($pid) {
    $status = 0;
    pcntl_waitpid($pid, $status);

    return pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 99;
}

$failures = array();

list($port, $pid) = startFakeSmtpServer('success');
$phaseHookCalled = 0;

try {
    if (createSmtpMessage($port, function() use (&$phaseHookCalled) {
        $phaseHookCalled++;
    })->send() !== true) {
        $failures[] = 'Successful SMTP delivery must return true.';
    }
} catch (Exception $exception) {
    $failures[] = 'Successful SMTP delivery threw: ' . $exception->getMessage();
}

if (waitForFakeSmtpServer($pid) !== 0) {
    $failures[] = 'SMTP DATA was not dot-stuffed correctly.';
}

if ($phaseHookCalled !== 1) {
    $failures[] = 'SMTP phase hook must run exactly once immediately before the DATA terminator.';
}

list($port, $pid) = startFakeSmtpServer('temporary_data_failure');

try {
    createSmtpMessage($port)->send();
    $failures[] = 'Explicit SMTP DATA 450 must throw.';
} catch (Exception $exception) {
    if ($exception->getCode() !== 450 || stripos($exception->getMessage(), 'outcome unknown') !== false) {
        $failures[] = 'Explicit SMTP DATA 450 must remain a definitive retryable failure.';
    }
}

waitForFakeSmtpServer($pid);

list($port, $pid) = startFakeSmtpServer('connection_drop');

try {
    createSmtpMessage($port)->send();
    $failures[] = 'A connection drop after DATA must throw.';
} catch (Exception $exception) {
    if (stripos($exception->getMessage(), 'outcome unknown') === false) {
        $failures[] = 'A connection drop after DATA must be classified as an unknown outcome.';
    }
}

waitForFakeSmtpServer($pid);

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    }

    exit(1);
}

echo "OrderReviews SMTP adaptor tests passed.\n";
