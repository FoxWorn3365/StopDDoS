<?php
ini_set('memory_limit', '512M');
error_reporting(E_ALL);

use Symfony\Component\Yaml\Yaml;
use WSSC\WebSocketClient as SocketClient;
use WSSC\Components\ClientConfig;
use Spatie\Async\Pool;

require 'src/Blacklist.php';
require 'src/Logger.php';
require 'src/Text.php';
require 'src/Cache.php';
require 'src/WebsocketClient.php';
require 'vendor/autoload.php';

$config = json_decode(json_encode(Yaml::parse(file_get_contents('config.yml'), Yaml::PARSE_OBJECT_FOR_MAP)));

$configC = new ClientConfig();
$configC->setFragmentSize(8096);
$configC->setTimeout(15);

$client = new SocketClient('ws://s1.fcosma.it:8872', $configC);
$socket = new WebsocketClient($client);

$logger = new Logger();

$logger->reset();

$logger->add(Text::BOLD . "NoMoreDDOS v1.0 by ".Text::YELLOW . "FoxWorn3365");
$logger->add("");
$logger->add(Text::BOLD . "Connected to WS Server: " .Text::RESET .Text::RED . "false");
$logger->add("");
$logger->add(Text::BOLD . "Your WS-ID: " .Text::RESET . $config->ipdb->key);
$logger->add("");
$logger->add(Text::BOLD . "WebSocket Status: " . Text::DIM . "Awaiting connection to ws://s1.fcosma.it:8872");
$logger->add("");
$logger->add(Text::BOLD . "RAM Used: " . Text::DIM . "0MB/{$config->ram}B");
$logger->add("");
$logger->add(Text::BOLD . "Performing the current action: " . Text::DIM . "connecting to th ws:// server...");
$logger->add("");
$logger->add(Text::BOLD . "Errors: " . Text::GREEN . "nessun errore");

$logger->deploy();
$logger->cpd();

$socket->listen('connectedClient', function($data) {
    echo "Glad to welcome the WS Client of server {$data->name} with tmpConnId {$data->id}!\n\ruwu";
});

if ($config->ipdb->sync_ban) {
    $socket->listen('newIpBanned', function($data, $config) {
        if ($data->sensibility >= $config->ipdb->sync_ban_warn) {
            if ($config->autoblock) {
                shell_exec("ufw insert 1 deny from {$data->ip}");
            }

            if ($config->baselog->enabled) {
                Blacklist::update($config->baselog->file, $data->ip);
            }
            if ($config->reportlog->enabled) {
                Blacklist::update($config->reportlog->file, "[" . date("dm/Y - H:i:s") . "] {$data->ip} from country {$data->ipdata->country->iso_code} has reached {$config->autoblock} warnings - Banned from the IPDB system.");
            }
        }
        // Confirm the pair
        $socket->request('/data/last/update', []);
    });
}

$pool = Pool::create();

$pool->add(function() use ($client, &$socket) {
    $msg = $client->receive();

    var_dump($msg);
    $data = json_decode($msg);
    if ($data !== false && $data !== null) {
        $socket->manage($data);
    } else {
        echo "WebSocket Server says: {$msg}\n";
    }
});

$socket->asyncRequestWithResponse('/auth', [
    'id' => $config->ipdb->key
], function($data) use (&$socket, $config, $logger) {
    $logger->add(Text::BOLD . "NoMoreDDOS v1.0 by ".Text::YELLOW . "FoxWorn3365");
    $logger->add("");
    $logger->add(Text::BOLD . "Connected to WS Server: " .Text::RESET .Text::GREEN . "true");
    $logger->add("");
    $logger->add(Text::BOLD . "Your WS-ID: " .Text::RESET . $config->ipdb->key);
    $logger->add("");
    $logger->add(Text::BOLD . "WebSocket Status: " . Text::DIM . Text::LIGHT_GREEN. "Authenticated with token from endpoint, ready for request(s)");
    $logger->add("");
    $logger->add(Text::BOLD . "RAM Used: " . Text::DIM . round(memory_get_usage()/1000000, 3) . "MB/{$config->ram}B");
    $logger->add("");
    $logger->add(Text::BOLD . "Performing the current action: " . Text::DIM . "syncronization of the local BlackDB from the IPDB");
    $logger->add("");
    $logger->add(Text::BOLD . "Errors: " . Text::GREEN . "nessun errore");
    $logger->add("");
    $logger->add(Text::BOLD . "Temp logging: none....");

    $logger->clear();
    $logger->deploy();
    $logger->cpd();

    if ($data->status !== 200) {
        die("ERROR on WebSocket.AUTH: {$data->message}");
    }
    $socket->token = $data->token;
    sleep(1);

    if ($config->ipdb->sync_ban) {
        // We need to pair the list
        $pool = Pool::create();
        $pool->add(function() use ($socket, $config) {
            $latest = json_decode($socket->requestWithResponse('/data/count', []));
            $ourLatest = json_decode($socket->requestWithResponse('/data/last/get', []));
            if ($ourLatest < $latest-1) {
                // Let's sync
                // Take the chunk
                $list = json_decode($socket->requestWithResponse('/data/get', [
                    'last' => $ourLatest
                ]));
                foreach ($list->data as $data) {
                    if ($data->sensibility >= $config->ipdb->sync_ban_warn) {
                        if ($config->autoblock) {
                            shell_exec("ufw insert 1 deny from {$data->ip}");
                        }
            
                        if ($config->baselog->enabled) {
                            Blacklist::update($config->baselog->file, $data->ip);
                        }
                        if ($config->reportlog->enabled) {
                            Blacklist::update($config->reportlog->file, "[" . date("dm/Y - H:i:s") . "] {$data->ip} from country {$data->ipdata->country->iso_code} has reached {$config->autoblock} warnings - Banned from the IPDB system.");
                        }
                    }
                }
                // Confirm the pair
                $socket->request('/data/last/update', []);
            }
        });
    }

    $blacklist = new Blacklist();
    $blacklist->init();

    $cache = new Cache();
    $cache->init();

    $ips = [];

    // Data
    $port = $config->port;
    $packetCount = $config->packetCount;
    $warnings = $config->warnings;
    $printFollow = 3;

    $initalFingerprint = "3030303030303030303000";
    $maliciousPackets = [];
    $mightMalicious = [];
    $fingerprints = [];
    $displayip = [];

    $globalData = new \stdClass;

    $ext = 1;
    while (true) {
        $count = 0;
        /*
        $logger->add(Text::BOLD . "Connected to WS Server: " .Text::RESET .Text::GREEN . "true");
        $logger->add("");
        $logger->add(Text::BOLD . "Your WS-ID: " .Text::RESET . $config->ipdb->key);
        $logger->add("");
        $logger->add(Text::BOLD . "WebSocket Status: " . Text::DIM . Text::LIGHT_GREEN. "Authenticated with token from endpoint, ready for request(s)");
        $logger->add("");
        $logger->add(Text::BOLD . "RAM Used: " . Text::DIM . round(memory_get_usage()/1000000, 3) . "MB/{$config->ram}B");
        $logger->add("");
        $logger->add(Text::BOLD . "Performing the current action: " . Text::DIM . "starting internal LinuxCommandExecution loops");
        $logger->add("");
        $logger->add(Text::BOLD . "Errors: " . Text::GREEN . "nessun errore");
        $logger->add("");
        $logger->add(Text::BOLD . "Temp logging: none....");

        $logger->clear();
        $logger->deploy();
        $logger->cpd();
        */

        while ($count < 5) {
            usleep(150000);
            //$output = (string)shell_exec("tcpdump --interface 1 -c {$count} -nn dst port {$port}");
            shell_exec("tcpdump -n -X udp dst port {$port} -c {$packetCount} > temp.txt 2> /dev/null");
            $output = file_get_contents('temp.txt');
            echo "OUTPUT";
            // Done, close and call

            foreach (explode(PHP_EOL, $output) as $row) {
                if (strpos($row, "	") === false) {
                    // Analyze content
                    if (strpos($row, "IP") === false) {
                        continue;
                    }
                    $data = explode(" ", $row);
                    if ($data[1] !== "IP") {
                        continue;
                    }

                    if ($data[5] !== "UDP,") {
                        continue;
                    }

                    //echo "CONTROLLI OK\n";
                    $ip = explode('.', $data[2]);
                    $port = $ip[4];
                    $ip = $ip[0] .'.'. $ip[1] .'.'. $ip[2] .'.'. $ip[3];
                    $packet = $data[7];
                    $globalData->ip = $ip;
                    $globalData->packet = $packet;
                    if ($cache->exists($ip)) {
                        $ipdata = $cache->retrive($ip);
                    } else {
                        $ipdata = json_decode(file_get_contents("https://api.findip.net/{$ip}/?token=76653068a2ff42e8b4245667dfc6a4c4"));
                        $cache->add($ip, $ipdata);
                    }

                    if ($ipdata === null) {
                        continue;
                    }

                    if ($packet >= 400) {
                        if (strpos(strtolower($ipdata->traits->isp), 'nvidia') !== false) {
                            // Nothing is sus, let's go
                            continue;
                        } elseif ($ipdata->country->iso_code !== "IT") {
                            // Something is SUSSY, let's put it inside the "you might are evil" list
                            $blacklist->add($ip);
                            if ($blacklist->present($ip, $warnings)) {
                                if (!in_array($ip, $ips)) {
                                    $ips[] = $ip;
                                    $logger->add("An IP adress ({$ip}) from the country {$ipdata->country->iso_code} has been banned by the Internal System (Protocol IP.UDP_PACKET_LENGHT). Epic");

                                    if ($config->autoblock) {
                                        shell_exec("ufw insert 1 deny from {$ip}");
                                    }
                                    
                                    if ($config->baselog->enabled) {
                                        Blacklist::update($config->baselog->file, $ip);
                                    }

                                    if ($config->reportlog->enabled) {
                                        Blacklist::update($config->reportlog->file, "[" . date("dm/Y - H:i:s") . "] {$ip} from country {$ipdata->country->iso_code} has reached " .$blacklist->get()->{$ip}. " warnings - Banned from the Internal AntiAbuse system.");
                                    }

                                    if ($config->ipdb->share_ban) {
                                        $socket->request('/data/put', [
                                            'ip' => $ip,
                                            'ipdata' => $ipdata,
                                            'warn' => $blacklist->get()->{$ip}
                                        ]);
                                        $socket->request('/data/last/update', []);
                                    }
                                }
                            }
                        }
                    } 
                } else {
                    // Packet analyzer
                    $packet = str_replace(" ", "", explode("  ", $row)[1]);
                    if (stripos($packet, $initalFingerprint) !== false) {
                        // Retrive and block the IPv4
                        if (!in_array($ip, $ips)) {
                            $logger->add("An IP adress ({$ip}) from the country {$ipdata->country->iso_code} has been banned by the Internal System (Protocol IP.UDP_PACKET_CNT). Epic");
                            $ips[] = $ip;

                            if ($config->autoblock) {
                                shell_exec("ufw insert 1 deny from {$ip}");
                            }
                            
                            if ($config->baselog->enabled) {
                                Blacklist::update($config->baselog->file, $ip);
                            }

                            if ($config->reportlog->enabled) {
                                Blacklist::update($config->reportlog->file, "[" . date("dm/Y - H:i:s") . "] {$ip} from country {$ipdata->country->iso_code} has reached " .$blacklist->get()->{$ip}. " warnings - Banned from the Internal AntiAbuse system.");
                            }

                            if ($config->ipdb->share_ban) {
                                $socket->request('/data/put', [
                                    'ip' => $ip,
                                    'ipdata' => $ipdata,
                                    'warn' => $blacklist->get()->{$ip}
                                ]);
                                $socket->request('/data/last/update', []);
                            }
                        }
                        // Add to the fingerprint detector
                        $maliciousPackets[] = explode(" ", explode("  ", $row)[1]);
                    } elseif (strpos(strtolower($ipdata->traits->isp), 'nvidia') === false && $ipdata->country->iso_code !== "IT") {
                        // Add to "potential malicious array"
                        if (!in_array($packet, $mightMalicious)) {
                            $mightMalicious[] = explode("  ", $row)[1];
                        }

                        // Analyze packet
                        foreach ($maliciousPackets as $mal) {
                            if (count(array_intersect($packet, $mal)) >= $printFollow) {
                                // Flag as malicious
                                $blacklist->add($ip);
                                if ($blacklist->present($ip, $warnings)) {
                                    if (!in_array(explode(" ", explode("  ", $row)[1]))) {
                                        $maliciousPackets[] = explode(" ", explode("  ", $row)[1]);
                                    }
                                    if (!in_array($ip, $ips)) {
                                        $logger->add("An IP adress ({$ip}) from the country {$ipdata->country->iso_code} has been banned by the Internal System (Protocol IP.UDP_PACKET_CNT.FPBASED). Epic");
                                        $ips[] = $ip;

                                        if ($config->autoblock) {
                                            shell_exec("ufw insert 1 deny from {$ip}");
                                        }
                                        
                                        if ($config->baselog->enabled) {
                                            Blacklist::update($config->baselog->file, $ip);
                                        }

                                        if ($config->reportlog->enabled) {
                                            Blacklist::update($config->reportlog->file, "[" . date("dm/Y - H:i:s") . "] {$ip} from country {$ipdata->country->iso_code} has reached " .$blacklist->get()->{$ip}. " warnings - Banned from the Internal AntiAbuse system.");
                                        }

                                        if ($config->ipdb->share_ban) {
                                            $socket->request('/data/put', [
                                                'ip' => $ip,
                                                'ipdata' => $ipdata,
                                                'warn' => $blacklist->get()->{$ip}
                                            ]);
                                            $socket->request('/data/last/update', []);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        foreach ($maliciousPackets as $mal) {
                            if (count(array_intersect($packet, $mal)) >= $printFollow) {
                                // Flag as malicious
                                $blacklist->add($ip);
                                if ($blacklist->present($ip, $warnings)) {
                                    if (!in_array(explode(" ", explode("  ", $row)[1]))) {
                                        $maliciousPackets[] = explode(" ", explode("  ", $row)[1]);
                                    }
                                    if (!in_array($ip, $ips)) {
                                        $ips[] = $ip;
                                        $logger->add("An IP adress ({$ip}) from the country {$ipdata->country->iso_code} has been banned by the Internal System (Protocol IP.UDP_PACKET_LENGHT.UNKNOWN). Epic");

                                        if ($config->autoblock) {
                                            shell_exec("ufw insert 1 deny from {$ip}");
                                        }
                                        
                                        if ($config->baselog->enabled) {
                                            Blacklist::update($config->baselog->file, $ip);
                                        }

                                        if ($config->reportlog->enabled) {
                                            Blacklist::update($config->reportlog->file, "[" . date("dm/Y - H:i:s") . "] {$ip} from country {$ipdata->country->iso_code} has reached " .$blacklist->get()->{$ip}. " warnings - Banned from the Internal AntiAbuse system.");
                                        }

                                        if ($config->ipdb->share_ban) {
                                            $socket->request('/data/put', [
                                                'ip' => $ip,
                                                'ipdata' => $ipdata,
                                                'warn' => $blacklist->get()->{$ip}
                                            ]);
                                            $socket->request('/data/last/update', []);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $logger->add(Text::BOLD . "NoMoreDDOS v1.0 by ".Text::YELLOW . "FoxWorn3365");
            $logger->add("");
            $logger->add(Text::BOLD . "Connected to WS Server: " .Text::RESET .Text::GREEN . "true");
            $logger->add("");
            $logger->add(Text::BOLD . "Your WS-ID: " .Text::RESET . $config->ipdb->key);
            $logger->add("");
            $logger->add(Text::BOLD . "WebSocket Status: " . Text::DIM . Text::LIGHT_GREEN. "Authenticated with token from endpoint, ready for request(s)");
            $logger->add("");
            $logger->add(Text::BOLD . "RAM Used: " . Text::DIM . round(memory_get_usage()/1000000, 3) . "MB/{$config->ram}B");
            $logger->add("");
            $logger->add(Text::BOLD . "Performing the current action: " . Text::DIM . "starting internal LinuxCommandExecution loops - Loop count {$count}[]{$ext}");
            $logger->add("");
            $logger->add(Text::BOLD . "The epic SUSlist has " . Text::DIM . count((array)$blacklist->get()) . Text::RESET . " entries");
            $logger->add("");
            $logger->add(Text::BOLD . "The epic blacklist has " . Text::DIM . count($ips) . Text::RESET . " entries");
            $logger->add("");
            $logger->add(Text::BOLD . "Errors: " . Text::GREEN . "nessun errore");
            $logger->add("");
            $logger->add(Text::BOLD . "Temp logging:");
    
            $logger->clear();
            $logger->deploy();
            $logger->cpd();

            $count++;
        }

        // Analyze the packets to see if mightMalicious are similar to malicious
        foreach ($mightMalicious as $packet) {
            foreach ($maliciousPackets as $mal) {
                if (count(array_intersect($packet, $mal)) >= $printFollow) {
                    $fingerprints[] = array_intersect($packet, $mal);
                    $logger->add("New bad fingerprint detected: '" . implode(" ", array_intersect($packet, $mal)) ."'. Epic.");
                    if ($config->fingerprintlog->enabled) {
                        file_put_contents($config->fingerprintlog->file, implode(PHP_EOL, $fingerprints));
                    }
                }
            }
        }
        $mightMalicious = [];

        shell_exec("ufw reload");
        shell_exec("service ufw restart");

        $ext++;

        $socket->request('/ping', []);
    }
});