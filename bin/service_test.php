#!/usr/bin/env php
<?php
require_once(__DIR__ . '/../vendor/autoload.php');

$pidPath = '/tmp/my_php_daemon.pid';
$validator = new \HW3\Validator();
$daemonLogger = new \HW3\Logger($validator, '[Daemon]');
$socketServerLogger = new \HW3\Logger($validator, '[SocketServer]');
$connectionLogger = new \HW3\Logger($validator, '[BracketeerConnectionHandler]');
$BracketeerConnectionHandler = new \HW3\BracketeerHandler($connectionLogger);
$socketServer = new \HW3\SocketServer(1555, $BracketeerConnectionHandler, $validator, $socketServerLogger);
$socketServerAdapter = new \HW3\SocketServerAdapter($validator, $socketServerLogger, $socketServer);
$daemon = new \HW3\Daemon($validator, $daemonLogger, $socketServerAdapter);
$service = new \HW3\Service\Service($daemon);

$service->run();