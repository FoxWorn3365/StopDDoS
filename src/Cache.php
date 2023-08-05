<?php

class Cache {
    public int $id = 9979;
    protected object $data;

    public function init() : void {
        $this->data = new \stdClass;
    }

    public function get() : object {
        return $this->data;
    }

    public function add(string $key, object|array $element) : void {
        $this->data->{$key} = $element;
    }

    public function retrive(string $key) : object|array|null {
        //var_dump($data);
        return @$this->data->{$key};
    }

    public function exists(string $key) : bool {
        if (@$this->data->{$key} !== null) {
            return true;
        }
        return false;
    }

    public function reset() : void {
        $this->data = new \stdClass;
    }
}