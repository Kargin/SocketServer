<?php

namespace HW3\Interfaces;

interface SocketServerInterface
{
    public function createSocket($host);

    public function closeSocket($socket);

    public function createConnectionHandler($connection);

    public function listen();

    public function acceptConnections();

    public function getSocket();

    public function dropConnections();

    public function showActiveConnections();

    public function refreshConnections();

    public function getParamsFromConfig($configPath);
}