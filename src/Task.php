<?php
/* +===============+
 * | StopDDoS v0.1 |
 * +===============+
 * | Rileva, blocca e segnala attacchi DDoS
 * +================
 * | Autore: FoxWorn3365
 * | Licenza: GNU aGPL v3.0
 * | GitHub: https://github.com/FoxWorn3365/StopDDoS
 * +================
*/

class Task {
    protected const BASEURL = "https://api.fcosma.it/globalban/";

    public function run(string $key, int $count, int $port = 7777, int $warnings = 10) : void {
        $promise = \parallel\run(function() use ($key, $count, $port, $warnings) {
            $output = (string)shell_exec("tcpdump --interface 1 -c {$count} -nn dst port {$port}");
            //echo "\nOUTPUTED\n";
            //var_dump(gettype($output), gettype($key), gettype($count), gettype($port), gettype($warnings));
            // Done, close and call
            if (strpos(@$output, " ") === false) {
                die();
            }

            if (!class_exists("Shared")) {
                require 'src/Shared.php';
            }

            if (!class_exists("Blacklist")) {
                require 'src/Blacklist.php';
            }

            if (!class_exists("Cache")) {
                require 'src/Cache.php';
            }

            $cache = new Cache();
            var_dump($cache->get());

            foreach (explode(PHP_EOL, $output) as $row) {
                echo "PACKETED ( AS '{$row}' )\n";
                // Analyze content
                if (strpos($row, "IP") === false) {
                    continue;
                }
                $data = explode(" ", $row);
                if ($data[1] !== "IP") {
                    continue;
                }
                //echo "CONTROLLI OK\n";
                $ip = explode('.', $data[2]);
                $port = $ip[4];
                $ip = $ip[0] .'.'. $ip[1] .'.'. $ip[2] .'.'. $ip[3];
                $packet = $data[7];
                if ($packet >= 400) {
                    //echo "SUS LENGHT OF {$packet}\n";
                    // Something is SUS, let's analyze the IP
                    // Before, let's chech if we have the IP data cached:
                    if ($cache->exists($ip)) {
                        echo "\n\nCACHED\n\n";
                        $ipdata = $cache->retrive($ip);
                        //var_dump($ipdata);
                        die();
                    } else {
                        $ipdata = json_decode(file_get_contents("https://api.findip.net/{$ip}/?token=76653068a2ff42e8b4245667dfc6a4c4"));
                        //var_dump($ipdata);
                        // Add to cache
                        $cache->add($ip, $ipdata);
                    }

                    if (strpos(strtolower($ipdata->traits->isp), 'nvidia') !== false) {
                        // Nothing is sus, let's go
                        continue;
                    } elseif ($ipdata->country->iso_code !== "IT") {
                        echo "BIG BIG SUS\n";
                        // Something is SUSSY, let's put it inside the "you might are evil" list
                        $blacklist = new Blacklist();
                        $blacklist->add($ip);
                        if ($blacklist->present($ip, $warnings)) {
                            echo "KILLED\n";
                            shell_run("ufw deny from {$ip} to any");
                        }
                        $blacklist->remove($ip);
                    }
                }
            }
        });
        //echo "OUTPUT\n";
        return;
    }
}