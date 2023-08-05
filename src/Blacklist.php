<?php

class Blacklist {
    public int $id = 9182;
    public object $data;

    public function init() : void {
        $this->data = new \stdClass;
    }

    public function get() : object {
        return $this->data;
    }

    public function add(string $element) : void {
        if (@$this->data->{$element} !== null) {
            $this->data->{$element}++;
        } else {
            $this->data->{$element} = 1;
        }
    }

    public function remove(string $element) : void {
        if (@$this->data->{$element} !== null) {
            $this->data->{$element} = null;
            unset($this->data->{$element});
        }
    }

    public function reset() : void {
        $this->init();
    }

    public function present(string $element, string $count) : bool {
        if (@$this->data->{$element} !== null) {
            if ($this->data->{$element} >= $count) {
                return true;
            }
        }
        return false;
    }

    public static function update(string $file, string $record) : void {
        $data = @explode(PHP_EOL, file_get_contents($file)) ?? [];
        $data[] = $record;
        file_put_contents($file, implode(PHP_EOL, $data));
    }
}