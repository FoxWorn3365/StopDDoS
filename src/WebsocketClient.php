<?php
require 'vendor/autoload.php';

use Spatie\Async\Pool;

class WebsocketClient {
    protected $conn;
    public string $token;
    protected object $events;
    protected object $handlers;

    function __construct($conn) {
        $this->conn = $conn;
        $this->events = new \stdClass;
        $this->handlers = new \stdClass;
    }

    public function listen(string $event, callable $response) : void {
        $this->events->{$event} = $response;
    }

    public function request(string $path, object|array $data = [], int $requestId = null) : int {
        $data = (object)$data;
        $data->route = $path;
        $data->token = $this->token ?? "TEST";
        $data->requestId = $requestId ?? rand(10, 10000) . rand(1, 100);
        echo "SEND";
        $this->conn->send(json_encode($data));
        return $data->requestId;
    }

    public function requestWithResponse(string $path, object|array $data) : string|null {
        $id = rand(10, 10000) . rand(1, 100);
        $this->request($path, $data, $id);
        return $this->conn->receive();
    }

    public function asyncRequestWithResponse(string $path, object|array $data, callable $do) : void {
        $id = rand(10, 10000) . rand(1, 100);
        $this->request($path, $data, $id);
        $data = $this->conn->receive();
        $do(json_decode($data));
    }

    public function manage(object $data) : void {
        if (@$data->event) {
            // Is an event, let's see if any event is present here...
            if (@$this->events->{$data->eventName} !== null) {
                ($this->events->{$data->eventName})($data);
            }
        } else {
            if (@$this->handlers->{(int)$data->requestId} !== null) {
                $fn = $this->handlers->{(int)$data->requestId};
                $this->handlers->{(int)$data->requestId} = null;
                $fn($data);
            }
        }
    }
}