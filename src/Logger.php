<?php

class Logger {
    protected array $logger = [];
    protected int $height;

    public function __construct() {
        $this->height = shell_exec("tput lines");
    }

    public function add(string $data) : void {
        $this->logger[] = Text::RESET . "\r{$data}";
    }

    public function deploy() : void {
        if (count($this->logger) < $this->height) {
            for ($a = count($this->logger); $a < $this->height-1; $a++) {
                $this->logger[] = "";
            }
        }
        echo implode(PHP_EOL, $this->logger);
    }

    public function reset() : void {
        
        $prs = "";
        for ($a = 1; $a < $this->height; $a++) {
            $prs .= "\033[A";
        }

        echo $prs;

        for ($a = 1; $a < $this->height; $a++) {
            $this->logger[] = "                                                                      ";
        }

        $this->deploy();

        for ($a = 1; $a < $this->height; $a++) {
            $prs .= "\033[A";
        }

        echo $prs;

        $this->logger = [];
    }

    public function clear() : void {
        /*
        $prs = "";
        for ($a = 1; $a < $this->height; $a++) {
            $prs .= "\033[A";
        }

        echo $prs;

        $mmt = [];

        for ($a = 1; $a < $this->height; $a++) {
            $mmt[] = "                                                                      ";
        }

        echo implode(PHP_EOL, $mmt);

        for ($a = 1; $a < $this->height; $a++) {
            $prs .= "\033[A";
        }

        echo $prs;
        */


        for ($a = 1; $a < $this->height+5; $a++) {
            $mmt[] = "                                                                      ";
        }
        echo implode(PHP_EOL, $mmt);
    }

    public function cpd() : void {
        $this->logger = [];
    }
}