<?php

/*
 * This file is a part of the Civ13 project.
 *
 * Copyright (c) 2022-present Valithor Obsidion <valithor@valzargaming.com>
 */

use Discord\Parts\Embed\Embed;
use React\Socket\SocketServer;
use React\Http\HttpServer;
use React\Http\Message\Response;
use \Psr\Http\Message\ServerRequestInterface;

@include getcwd() . '/webapi_token_env.php'; // putenv("WEBAPI_TOKEN='YOUR_TOKEN_HERE'");
$webhook_key = getenv('WEBAPI_TOKEN') ?? 'CHANGEME'; // The token is used to verify that the sender is legitimate and not a malicious actor

$webapiFail = function (string $part, string $id) {
    // logInfo('[webapi] Failed', ['part' => $part, 'id' => $id]);
    return new Response(($id ? 404 : 400), ['Content-Type' => 'text/plain'], ($id ? 'Invalid' : 'Missing').' '.$part);
};

$webapiSnow = function (string $string) {
    return preg_match('/^[0-9]{16,20}$/', $string);
};

// $external_ip = file_get_contents('http://ipecho.net/plain');
// $civ13_ip = gethostbyname('www.civ13.com');
// $vzg_ip = gethostbyname('www.valzargaming.com');
$port = '55555';

$portknock = false;
$max_attempts = 3;
$portknock_ips = []; // ['ip' => ['step' => 0, 'authed' = false]]
$portknock_servers = [];
@include getcwd() . '/webapi_portknocks.php'; // putenv("DOORS=['port1', 'port2', 'port1', 'port3', 'port2' 'port1']"); (not a real example)
if ($portknock_ports = getenv('DOORS') ? unserialize(getenv('DOORS')) : []) { // The port knocks are used to prevent malicious port scanners from spamming the webapi
    $validatePort = function (int|string $value) use ($port) {
        return (
            $value > 0 // Port numbers are positive
            && $value < 65536 // Port numbers are between 0 and 65535
            && $value != $port // If the webapi port is in the port knocks list it is misconfigured and should be disabled
        );
    };
    $valid_config = true;
    foreach ($portknock_ports as $p) {
        if (! $validatePort($p)) {
            $valid_config = false;
            break;
        }
    }
    if ($valid_config) {
        $portknock = true;
        $initialized_ports = [];
        foreach ($portknock_ports as $p) {
            if (! in_array($p, $initialized_ports)) { // Don't listen on the same port as the webapi or any other port
                $s = new SocketServer(sprintf('%s:%s', '0.0.0.0', $p), [], $civ13->loop);
                $w = new HttpServer($loop, function (ServerRequestInterface $request) use ($civ13, $p, $portknock_ips, $portknock_ports, $max_attempts) {
                    // Initialize variables
                    $ip = $request->getServerParams()['REMOTE_ADDR'];
                    $step = 0;
                    if (! isset($portknock_ips[$ip])) $portknock_ips[$ip] = ['step' => 0, 'authed' => false, 'failed' => 0, 'knocks' => 1]; // First time knocking
                    elseif (isset($portknock_ips[$ip]['step'])) { // Already knocked
                        $step = $portknock_ips[$ip]['step'];
                        $portknock_ips[$ip]['knocks']++; // Useful for detecting spam, but not functionally used (yet)
                    }

                    // Too many failed attempts
                    if ($portknock_ips[$ip]['failed'] > $max_attempts) {
                        $civ13->logger->warning('[webapi] Blocked Port Scanner', [
                            'ip' => $ip,
                            'step' => $portknock_ips[$ip]['step'],
                            'authed' => $portknock_ips[$ip]['authed'],
                            'failed' => $portknock_ips[$ip]['failed'],
                            'knocks' => $portknock_ips[$ip]['knocks'],
                        ]);
                        return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
                    }

                    // Already authed, so deauth
                    if ($portknock_ips[$ip]['authed']) {
                        $portknock_ips[$ip]['authed'] = false;
                        return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
                    }

                    // Check if knock is valid
                    // Authenticate if all steps are completed
                    // Reset knocks and log failed attempt if the step is invalid
                    $valid_steps = [];
                    foreach ($portknock_ports as $value) if ($value == $p) $valid_steps[] = $p;
                    if (in_array($step, $valid_steps)) {
                        $portknock_ips[$ip]['step']++;
                        if ($portknock_ips[$ip]['step'] > count($valid_steps)) $portknock_ips[$ip]['authed'] = true;
                    } else {
                        $portknock_ips[$ip]['step'] = 0;
                        $portknock_ips[$ip]['failed']++;
                    }
                    
                    // Log the knock
                    $civ13->logger->debug('[webapi] Knock', [
                        'ip' => $ip,
                        'step' => $portknock_ips[$ip]['step'],
                        'authed' => $portknock_ips[$ip]['authed'],
                        'failed' => $portknock_ips[$ip]['failed'],
                        'knocks' => $portknock_ips[$ip]['knocks'],
                    ]);

                    return new Response(200, ['Content-Type' => 'text/plain'], 'OK');
                });
                $w->listen($s);
                $w->on('error', function (Exception $e) use ($civ13) {
                    $civ13->logger->error('KNOCK ' . $e->getMessage() . ' [' . $e->getFile() . ':' . $e->getLine() . '] ' . str_replace('\n', PHP_EOL, $e->getTraceAsString()));
                });
                $portknock_servers[] = $w;
            }
            $initialized_ports[] = $p;
        }
    }
}

$socket = new SocketServer(sprintf('%s:%s', '0.0.0.0', $port), [], $civ13->loop);

$webapi = new HttpServer($loop, function (ServerRequestInterface $request) use ($civ13, $port, $socket, $vzg_ip, $civ13_ip, $external_ip, $webhook_key, $portknock, $portknock_ips, $max_attempts, $webapiFail, $webapiSnow)
{
    // Port knocking security check
    $authed_ips = [];
    if ($portknock && isset($portknock_ips[$request->getServerParams()['REMOTE_ADDR']])) {
        if ($portknock_ips[$request->getServerParams()['REMOTE_ADDR']]['failed'] > $max_attempts) {// Malicious port scanner
            $civ13->logger->warning('[webapi] Blocked Port Scanner', [
                'ip' => $request->getServerParams()['REMOTE_ADDR'],
                'step' => $portknock_ips[$request->getServerParams()['REMOTE_ADDR']]['step'],
                'authed' => $portknock_ips[$request->getServerParams()['REMOTE_ADDR']]['authed'],
                'failed' => $portknock_ips[$request->getServerParams()['REMOTE_ADDR']]['failed'],
                'knocks' => $portknock_ips[$request->getServerParams()['REMOTE_ADDR']]['knocks'],
            ]);
            return new Response(401, ['Content-Type' => 'text/plain'], 'Unauthorized');
        }
        /* // Port knocking to obtain a valid session is not implemented for security reasons
        if ($portknock_ips[$request->getServerParams()['REMOTE_ADDR']]['authed']) 
            $authed_ips[] = $request->getServerParams()['REMOTE_ADDR'];
        */
    }
    /*
    $path = explode('/', $request->getUri()->getPath());
    $sub = (isset($path[1]) ? (string) $path[1] : false);
    $id = (isset($path[2]) ? (string) $path[2] : false);
    $id2 = (isset($path[3]) ? (string) $path[3] : false);
    $ip = (isset($path[4]) ? (string) $path[4] : false);
    $idarray = array(); // get from post data (NYI)
    */
    
    $echo = 'API ';
    $sub = 'index.';
    $path = explode('/', $request->getUri()->getPath());
    $repository = $sub = (isset($path[1]) ? (string) strtolower($path[1]) : false); if ($repository) $echo .= "$repository";
    $method = $id = (isset($path[2]) ? (string) strtolower($path[2]) : false); if ($method) $echo .= "/$method";
    $id2 = $repository2 = (isset($path[3]) ? (string) strtolower($path[3]) : false); if ($id2) $echo .= "/$id2";
    $ip = $partial = $method2 = (isset($path[4]) ? (string) strtolower($path[4]) : false); if ($partial) $echo .= "/$partial";
    $id3 = (isset($path[5]) ? (string) strtolower($path[5]) : false); if ($id3) $echo .= "/$id3";
    $id4 = (isset($path[6]) ? (string) strtolower($path[6]) : false); if ($id4) $echo .= "/$id4";
    $idarray = array(); // get from post data (NYI)
    // $civ13->logger->info($echo);
    
    if ($ip) $civ13->logger->info('API IP ' . $ip);
    $whitelist = [
        '127.0.0.1',
        $external_ip,
        $civ13_ip,
        $vzg_ip,
        '51.254.161.128',
        '69.244.83.231',
    ];
    $whitelist = array_merge($whitelist, $authed_ips);
    $substr_whitelist = ['10.0.0.', '192.168.']; 
    $whitelisted = false;
    foreach ($substr_whitelist as $substr) if (substr($request->getServerParams()['REMOTE_ADDR'], 0, strlen($substr)) == $substr) $whitelisted = true;
    if (in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist)) $whitelisted = true;
    
    if (! $whitelisted) $civ13->logger->info('API REMOTE_ADDR ' . $request->getServerParams()['REMOTE_ADDR']);

    $webpage_content = function (string $return) use ($civ13, $port, $sub) {
        return '<meta name="color-scheme" content="light dark"> 
                <div class="button-container">
                    <button style="width:8%" onclick="sendGetRequest(\'pull\')">Pull</button>
                    <button style="width:8%" onclick="sendGetRequest(\'reset\')">Reset</button>
                    <button style="width:8%" onclick="sendGetRequest(\'update\')">Update</button>
                    <button style="width:8%" onclick="sendGetRequest(\'restart\')">Restart</button>
                    <button style="background-color: black; color:white; display:flex; justify-content:center; align-items:center; height:100%; width:68%; flex-grow: 1;" onclick="window.open(\''. $civ13->github . '\')">' . $civ13->discord->user->displayname . '</button>
                </div>
                <div class="alert-container"></div>
                <div class="checkpoint">' . 
                    str_replace('[' . date("Y"), '</div><div> [' . date("Y"), 
                        str_replace([PHP_EOL, '[] []', ' [] '], '</div><div>', $return)
                    ) . 
                "</div>
                <div class='reload-container'>
                    <button onclick='location.reload()'>Reload</button>
                </div>
                <div class='loading-container'>
                    <div class='loading-bar'></div>
                </div>
                <script>
                    var mainScrollArea=document.getElementsByClassName('checkpoint')[0];
                    var scrollTimeout;
                    window.onload=function(){
                        if(window.location.href==localStorage.getItem('lastUrl')){
                            mainScrollArea.scrollTop=localStorage.getItem('scrollTop');
                        }else{
                            localStorage.setItem('lastUrl',window.location.href);
                            localStorage.setItem('scrollTop',0);
                        }
                    };
                    mainScrollArea.addEventListener('scroll',function(){
                        clearTimeout(scrollTimeout);
                        scrollTimeout=setTimeout(function(){
                            localStorage.setItem('scrollTop',mainScrollArea.scrollTop);
                        },100);
                    });
                    function sendGetRequest(endpoint) {
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', window.location.protocol + '//' + window.location.hostname + ':" . $port . "/' + endpoint, true);
                        xhr.onload = function () {
                            var response = xhr.responseText.replace(/(<([^>]+)>)/gi, '');
                            var alertContainer = document.querySelector('.alert-container');
                            var alert = document.createElement('div');
                            alert.innerHTML = response;
                            alertContainer.appendChild(alert);
                            setTimeout(function() {
                                alert.remove();
                            }, 15000);
                            if (endpoint === 'restart') {
                                var loadingBar = document.querySelector('.loading-bar');
                                var loadingContainer = document.querySelector('.loading-container');
                                loadingContainer.style.display = 'block';
                                var width = 0;
                                var interval = setInterval(function() {
                                    if (width >= 100) {
                                        clearInterval(interval);
                                        location.reload();
                                    } else {
                                        width += 2;
                                        loadingBar.style.width = width + '%';
                                    }
                                }, 300);
                                loadingBar.style.backgroundColor = 'white';
                                loadingBar.style.height = '20px';
                                loadingBar.style.position = 'fixed';
                                loadingBar.style.top = '50%';
                                loadingBar.style.left = '50%';
                                loadingBar.style.transform = 'translate(-50%, -50%)';
                                loadingBar.style.zIndex = '9999';
                                loadingBar.style.borderRadius = '5px';
                                loadingBar.style.boxShadow = '0 0 10px rgba(0, 0, 0, 0.5)';
                                var backdrop = document.createElement('div');
                                backdrop.style.position = 'fixed';
                                backdrop.style.top = '0';
                                backdrop.style.left = '0';
                                backdrop.style.width = '100%';
                                backdrop.style.height = '100%';
                                backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
                                backdrop.style.zIndex = '9998';
                                document.body.appendChild(backdrop);
                                setTimeout(function() {
                                    clearInterval(interval);
                                    if (!document.readyState || document.readyState === 'complete') {
                                        location.reload();
                                    } else {
                                        setTimeout(function() {
                                            location.reload();
                                        }, 5000);
                                    }
                                }, 5000);
                            }
                        };
                        xhr.send();
                    }
                    </script>
                    <style>
                        .button-container {
                            position: fixed;
                            top: 0;
                            left: 0;
                            right: 0;
                            background-color: #f1f1f1;
                            overflow: hidden;
                        }
                        .button-container button {
                            float: left;
                            display: block;
                            color: black;
                            text-align: center;
                            padding: 14px 16px;
                            text-decoration: none;
                            font-size: 17px;
                            border: none;
                            cursor: pointer;
                            color: white;
                            background-color: black;
                        }
                        .button-container button:hover {
                            background-color: #ddd;
                        }
                        .checkpoint {
                            margin-top: 100px;
                        }
                        .alert-container {
                            position: fixed;
                            top: 0;
                            right: 0;
                            width: 300px;
                            height: 100%;
                            overflow-y: scroll;
                            padding: 20px;
                            color: black;
                            background-color: black;
                        }
                        .alert-container div {
                            margin-bottom: 10px;
                            padding: 10px;
                            background-color: #fff;
                            border: 1px solid #ddd;
                        }
                        .reload-container {
                            position: fixed;
                            bottom: 0;
                            left: 50%;
                            transform: translateX(-50%);
                            margin-bottom: 20px;
                        }
                        .reload-container button {
                            display: block;
                            color: black;
                            text-align: center;
                            padding: 14px 16px;
                            text-decoration: none;
                            font-size: 17px;
                            border: none;
                            cursor: pointer;
                        }
                        .reload-container button:hover {
                            background-color: #ddd;
                        }
                        .loading-container {
                            position: fixed;
                            top: 0;
                            left: 0;
                            right: 0;
                            bottom: 0;
                            background-color: rgba(0, 0, 0, 0.5);
                            display: none;
                        }
                        .loading-bar {
                            position: absolute;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%);
                            width: 0%;
                            height: 20px;
                            background-color: white;
                        }
                        .nav-container {
                            position: fixed;
                            bottom: 0;
                            right: 0;
                            margin-bottom: 20px;
                        }
                        .nav-container button {
                            display: block;
                            color: black;
                            text-align: center;
                            padding: 14px 16px;
                            text-decoration: none;
                            font-size: 17px;
                            border: none;
                            cursor: pointer;
                            color: white;
                            background-color: black;
                            margin-right: 10px;
                        }
                        .nav-container button:hover {
                            background-color: #ddd;
                        }
                        .checkbox-container {
                            display: inline-block;
                            margin-right: 10px;
                        }
                        .checkbox-container input[type=checkbox] {
                            display: none;
                        }
                        .checkbox-container label {
                            display: inline-block;
                            background-color: #ddd;
                            padding: 5px 10px;
                            cursor: pointer;
                        }
                        .checkbox-container input[type=checkbox]:checked + label {
                            background-color: #bbb;
                        }
                    </style>
                    <div class='nav-container'>"
                        . ($sub == 'botlog' ? "<button onclick=\"location.href='/botlog2'\">Botlog 2</button>" : "<button onclick=\"location.href='/botlog'\">Botlog 1</button>")
                    . "</div>
                    <div class='reload-container'>
                        <div class='checkbox-container'>
                            <input type='checkbox' id='auto-reload-checkbox' " . (isset($_COOKIE['auto-reload']) && $_COOKIE['auto-reload'] == 'true' ? 'checked' : '') . ">
                            <label for='auto-reload-checkbox'>Auto Reload</label>
                        </div>
                        <button id='reload-button'>Reload</button>
                    </div>
                    <script>
                        var reloadButton = document.getElementById('reload-button');
                        var autoReloadCheckbox = document.getElementById('auto-reload-checkbox');
                        var interval;

                        reloadButton.addEventListener('click', function () {
                            clearInterval(interval);
                            location.reload();
                        });

                        autoReloadCheckbox.addEventListener('change', function () {
                            if (this.checked) {
                                interval = setInterval(function() {
                                    location.reload();
                                }, 15000);
                                localStorage.setItem('auto-reload', 'true');
                            } else {
                                clearInterval(interval);
                                localStorage.setItem('auto-reload', 'false');
                            }
                        });

                        if (localStorage.getItem('auto-reload') == 'true') {
                            autoReloadCheckbox.checked = true;
                            interval = setInterval(function() {
                                location.reload();
                            }, 15000);
                        }
                    </script>";
    };

    switch ($sub) {
        case (str_starts_with($sub, 'index.')):
            $return = '<meta http-equiv="refresh" content="0 url=\'https://www.valzargaming.com/?login\'" />'; // Redirect to the website to log in
            return new Response(200, ['Content-Type' => 'text/html'], $return);
            break;
        case 'github':
            $return = '<meta http-equiv = \"refresh\" content = \"0; url = https://github.com/VZGCoders/Civilizationbot\" />'; // Redirect to the website to log in
            return new Response(200, ['Content-Type' => 'text/html'], $return);
            break;
        case 'favicon.ico':
            if (! $whitelisted) {
                $civ13->logger->info('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if ($favicon = @file_get_contents('favicon.ico')) return new Response(200, ['Content-Type' => 'image/x-icon'], $favicon);
            else return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `favicon.ico`");
        
        case 'nohup.out':
            if (! $whitelisted) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if ($return = @file_get_contents('nohup.out')) return new Response(200, ['Content-Type' => 'text/plain'], $return);
            else return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `nohup.out`");
            break;
        
        case 'botlog':
            if (! $whitelisted) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if ($return = @file_get_contents('botlog.txt')) return new Response(200, ['Content-Type' => 'text/html'], $webpage_content($return));
            else return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `botlog.txt`");
            break;
            
        case 'botlog2':
            if (! $whitelisted) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if ($return = @file_get_contents('botlog2.txt')) return new Response(200, ['Content-Type' => 'text/html'], $webpage_content($return));
            else return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `botlog2.txt`");
            break;
        
        case 'channel':
            if (! $id || !$webapiSnow($id) || ! $return = $civ13->discord->getChannel($id)) return $webapiFail('channel_id', $id);
            break;

        case 'guild':
            if (! $id || !$webapiSnow($id) || ! $return = $civ13->discord->guilds->get('id', $id)) return $webapiFail('guild_id', $id);
            break;

        case 'bans':
            if (! $id || !$webapiSnow($id) || ! $return = $civ13->discord->guilds->get('id', $id)->bans) return $webapiFail('guild_id', $id);
            break;

        case 'channels':
            if (! $id || !$webapiSnow($id) || ! $return = $civ13->discord->guilds->get('id', $id)->channels) return $webapiFail('guild_id', $id);
            break;

        case 'members':
            if (! $id || !$webapiSnow($id) || ! $return = $civ13->discord->guilds->get('id', $id)->members) return $webapiFail('guild_id', $id);
            break;

        case 'emojis':
            if (! $id || !$webapiSnow($id) || ! $return = $civ13->discord->guilds->get('id', $id)->emojis) return $webapiFail('guild_id', $id);
            break;

        case 'invites':
            if (! $id || !$webapiSnow($id) || ! $return = $civ13->discord->guilds->get('id', $id)->invites) return $webapiFail('guild_id', $id);
            break;

        case 'roles':
            if (! $id || !$webapiSnow($id) || ! $return = $civ13->discord->guilds->get('id', $id)->roles) return $webapiFail('guild_id', $id);
            break;

        case 'guildMember':
            if (! $id || !$webapiSnow($id) || ! $guild = $civ13->discord->guilds->get('id', $id)) return $webapiFail('guild_id', $id);
            if (! $id2 || !$webapiSnow($id2) || ! $return = $guild->members->get('id', $id2)) return $webapiFail('user_id', $id2);
            break;

        case 'user':
            if (! $id || !$webapiSnow($id) || ! $return = $civ13->discord->users->get('id', $id)) return $webapiFail('user_id', $id);
            break;

        case 'userName':
            if (! $id || ! $return = $civ13->discord->users->get('name', $id)) return $webapiFail('user_name', $id);
            break;
        
        case 'reset':
            if (! $whitelisted) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            execInBackground('git reset --hard origin/main');
            if (isset($civ13->channel_ids['staff_bot']) && $channel = $civ13->discord->getChannel($civ13->channel_ids['staff_bot'])) $channel->sendMessage('Forcefully moving the HEAD back to origin/main...');
            $return = 'fixing git';
            break;
        
        case 'pull':
            if (! $whitelisted) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            execInBackground('git pull');
            $civ13->logger->info('[GIT PULL]');
            if (isset($civ13->channel_ids['staff_bot']) && $channel = $civ13->discord->getChannel($civ13->channel_ids['staff_bot'])) $channel->sendMessage('Updating code from GitHub...');
            $return = 'updating code';
            break;
        
        case 'update':
            if (! $whitelisted) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            execInBackground('composer update');
            $civ13->logger->info('[COMPOSER UPDATE]');
            if (isset($civ13->channel_ids['staff_bot']) && $channel = $civ13->discord->getChannel($civ13->channel_ids['staff_bot'])) $channel->sendMessage('Updating dependencies...');
            $return = 'updating dependencies';
            break;
        
        case 'restart':
            if (! $whitelisted) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            $civ13->logger->info('[RESTART]');
            if (isset($civ13->channel_ids['staff_bot']) && $channel = $civ13->discord->getChannel($civ13->channel_ids['staff_bot'])) $channel->sendMessage('Restarting...');
            $return = 'restarting';
            $socket->close();
            if (! isset($civ13->timers['restart'])) $civ13->timers['restart'] = $civ13->discord->getLoop()->addTimer(5, function () use ($civ13) {
                \restart();
                $civ13->discord->close();
                die();
            });
            break;

        case 'lookup':
            if (! $whitelisted) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if (! $id || !$webapiSnow($id) || ! $return = $civ13->discord->users->get('id', $id)) return $webapiFail('user_id', $id);
            break;

        case 'owner':
            if (! $whitelisted) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if (! $id || !$webapiSnow($id)) return $webapiFail('user_id', $id); $return = false;
            if ($user = $civ13->discord->users->get('id', $id)) { // Search all guilds the bot is in and check if the user id exists as a guild owner
                foreach ($civ13->discord->guilds as $guild) {
                    if ($id == $guild->owner_id) {
                        $return = true;
                        break 1;
                    }
                }
            }
            break;

        case 'avatar':
            if (! $id || !$webapiSnow($id)) return $webapiFail('user_id', $id);
            if (! $user = $civ13->discord->users->get('id', $id)) $return = 'https://cdn.discordapp.com/embed/avatars/'.rand(0,4).'.png';
            else $return = $user->avatar;
            // if (! $return) return new Response(($id ? 404 : 400), ['Content-Type' => 'text/plain'], (''));
            break;

        case 'avatars': // This needs to be optimized to not use async code
            /*
            $idarray = $data ?? array(); // $data contains POST data
            $results = [];
            $promise = $civ13->discord->users->fetch($idarray[0])->then(function (User $user) use (&$results) {
              $results[$user->id] = $user->avatar;
            });
            
            for ($i = 1; $i < count($idarray); $i++) {
                $discord = $civ13->discord;
                $promise->then(function () use (&$results, $idarray, $i, $discord) {
                return $civ13->discord->users->fetch($idarray[$i])->then(function (User $user) use (&$results) {
                    $results[$user->id] = $user->avatar;
                });
              });
            }

            $promise->done(function () use ($results) {
              return new Response (200, ['Content-Type' => 'application/json'], json_encode($results));
            }, function () use ($results) {
              // return with error ?
              return new Response(200, ['Content-Type' => 'application/json'], json_encode($results));
            });
            */
            $return = '';
            break;

        case 'webhook':
            $server =& $method; // alias for readability
            if (! isset($civ13->channel_ids[$server.'_debug_channel']) || ! $channel_id = $civ13->channel_ids[$server.'_debug_channel']) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
            $params = $request->getQueryParams();
            // var_dump($params);
            if (! $whitelisted && (! isset($params['key']) || $params['key'] != $webhook_key)) return new Response(401, ['Content-Type' => 'text/plain'], 'Unauthorized');
            if (! isset($params['method']) || ! isset($params['data'])) return new Response(400, ['Content-Type' => 'text/plain'], 'Missing Parameters');
            $data = json_decode($params['data'], true);
            $time = '['.date('H:i:s', time()).']';
            $message = '';
            $ckey = '';
            if (isset($data['ckey'])) $ckey = $civ13->sanitizeInput($data['ckey']);
            switch ($params['method']) {
                case 'ahelpmessage':
                    $message .= "**__{$time} AHELP__ $ckey**: " . html_entity_decode(urldecode($data['message']));
                    break;
                case 'asaymessage':
                    if (! isset($civ13->channel_ids[$server.'_asay_channel']) || ! $channel_id = $civ13->channel_ids[$server.'_asay_channel']) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
                    if (isset($data['message'])) $message .= "**__{$time} ASAY__ $ckey**: " . html_entity_decode(urldecode($data['message']));
                    if ($civ13->relay_method === 'webhook' && $ckey && $message && $civ13->gameChatWebhookRelay($ckey, $message, $channel_id)) 
                         return new Response(200, ['Content-Type' => 'text/html'], 'Done'); // Relay handled by civ13->gameChatWebhookRelay
                    break;
                case 'lobbymessage': // Might overlap with deadchat
                    if (! isset($civ13->channel_ids[$server.'_lobby_channel']) || ! $channel_id = $civ13->channel_ids[$server.'_lobby_channel']) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
                    $message .= "**__{$time} LOBBY__ $ckey**: " . html_entity_decode(urldecode($data['message']));
                    break;
                case 'oocmessage':
                    if (! isset($civ13->channel_ids[$server.'_ooc_channel']) || ! $channel_id = $civ13->channel_ids[$server.'_ooc_channel']) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
                    $message .= html_entity_decode(strip_tags(urldecode($data['message'])));
                    if ($civ13->relay_method === 'webhook' && $ckey && $message && $civ13->gameChatWebhookRelay($ckey, $message, $channel_id))
                        return new Response(200, ['Content-Type' => 'text/html'], 'Done'); // Relay handled by civ13->gameChatWebhookRelay
                    if ($civ13->relay_method === 'file' && ! $ckey && str_ends_with($message, 'starting!') && $strpos = strpos($message, 'New round ')) {
                        $new_message = '';
                        if (isset($civ13->role_ids['round_start'])) $new_message .= "<@&{$civ13->role_ids['round_start']}>, ";
                        $new_message .= substr($message, $strpos);
                        $message = $new_message;
                        if (isset($civ13->channel_ids[$server . '-playercount']) && $playercount_channel = $civ13->discord->getChannel($civ13->channel_ids[$server . '-playercount']))
                            if ($existingCount = explode('-', $playercount_channel->name)[1]) {
                                $existingCount = intval($existingCount);
                                if ($existingCount === 0) $message .= " There are currently no players on the $server server.";
                                elseif ($existingCount === 1) $message .= " There is currently 1 player on the $server server.";
                                elseif ($existingCount > 1) {
                                    if (isset($civ13->role_ids['30+']) && $civ13->role_ids['30+'] && ($existingCount >= 30)) $message .= " <@&{$civ13->role_ids['30+']}>,";
                                    elseif (isset($civ13->role_ids['15+']) && $civ13->role_ids['15+'] && ($existingCount >= 15)) $message .= " <@&{$civ13->role_ids['15+']}>,";
                                    elseif (isset($civ13->role_ids['2+']) && $civ13->role_ids['2+'] && ($existingCount >= 2)) $message .= " <@&{$civ13->role_ids['2+']}>,";
                                    $message .= " There are currently $existingCount players on the $server server.";
                                }
                            }
                    }
                    break;
                case 'icmessage':
                    if (! isset($civ13->channel_ids[$server.'_ic_channel']) || ! $channel_id = $civ13->channel_ids[$server.'_ic_channel']) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
                    $message .= html_entity_decode(strip_tags(urldecode($data['message'])));
                    if ($civ13->relay_method === 'webhook' && $ckey && $message && $civ13->gameChatWebhookRelay($ckey, $message, $channel_id, false))
                        return new Response(200, ['Content-Type' => 'text/html'], 'Done'); // Relay handled by civ13->gameChatWebhookRelay
                    break;
                case 'memessage':
                    if (isset($data['message'])) $message .= "**__{$time} EMOTE__ $ckey** " . html_entity_decode(urldecode($data['message']));
                    break;
                case 'garbage':
                    if (isset($data['message'])) $message .= "**__{$time} GARBAGE__ $ckey**: " . html_entity_decode(strip_tags($data['message']));
                    break;
                case 'round_start':
                    if (! isset($civ13->channel_ids[$server]) || ! $channel_id = $civ13->channel_ids[$server]) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
                    if ($civ13->relay_method !== 'webhook') return new Response(200, ['Content-Type' => 'text/html'], 'Done'); // Only relay if using webhook
                    if (isset($civ13->role_ids['round_start'])) $message .= "<@&{$civ13->role_ids['round_start']}>, ";
                    $message .= 'New round ';
                    if (isset($data['round']) && $game_id = $data['round']) {
                        $civ13->logNewRound($server, $game_id, $time);
                        $message .= "`$game_id` ";
                    }
                    $message .= 'has started!';
                    if ($playercount_channel = $civ13->discord->getChannel($civ13->channel_ids[$server . '-playercount']));
                        if ($existingCount = explode('-', $playercount_channel->name)[1]) {
                            $existingCount = intval($existingCount);
                            if ($existingCount === 0) $message .= " There are currently no players on the $server server.";
                            elseif ($existingCount === 1) $message .= " There is currently 1 player on the $server server.";
                            elseif ($existingCount > 1) {
                                if (isset($civ13->role_ids['30+']) && $civ13->role_ids['30+'] && ($existingCount >= 30)) $message .= "<@&{$civ13->role_ids['30+']}>, ";
                                elseif (isset($civ13->role_ids['15+']) && $civ13->role_ids['15+'] && ($existingCount >= 15)) $message .= "<@&{$civ13->role_ids['15+']}>, ";
                                elseif (isset($civ13->role_ids['2+']) && $civ13->role_ids['2+'] && ($existingCount >= 2)) $message .= "<@&{$civ13->role_ids['2+']}>, ";
                                $message .= " There are currently $existingCount players on the $server server.";
                            }
                        }
                    // A future update should include a way to call a $civ13 function using the server and round id
                    break;
                case 'respawn_notice':
                    // if (isset($civ13->role_ids['respawn_notice'])) $message .= "<@&{$civ13->role_ids['respawn_notice']}>, ";
                    if (isset($data['message'])) $message .= html_entity_decode(urldecode($data['message']));
                    break;
                case 'login':
                    // Move this to a function in civ13.php
                    if (isset($civ13->paroled[$ckey])
                        && isset($civ13->channel_ids['parole_notif'])
                        && $parole_log_channel = $civ13->discord->getChannel($civ13->channel_ids['parole_notif'])
                    ) {
                        $message2 = '';
                        if (isset($civ13->role_ids['parolemin'])) $message2 .= "<@&{$civ13->role_ids['parolemin']}>, ";
                        $message2 .= "`$ckey` has logged into `$server`";
                        $parole_log_channel->sendMessage($message2);
                    }

                    if (isset($civ13->current_rounds[$server]) && $civ13->current_rounds[$server]) 
                        $civ13->logPlayerLogin($server, $ckey, $time, $data['ip'] ?? '', $data['cid'] ?? '');

                    if (! isset($civ13->channel_ids[$server.'_transit_channel']) || ! $channel_id = $civ13->channel_ids[$server.'_transit_channel']) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
                    $message .= "$ckey connected to the server";
                    if (isset($data['ip'])) {
                        $address = $data['ip'];
                        $message .=  " with IP of $address";
                    }
                    if (isset($data['cid'])) {
                        $computer_id = $data['cid'];
                        $message .= " and CID of $computer_id";
                    }
                    $message .= '.';
                    break;
                case 'logout':
                    // Move this to a function in civ13.php    
                    if (isset($civ13->paroled[$ckey])
                        && isset($civ13->channel_ids['parole_notif'])
                        && $parole_log_channel = $civ13->discord->getChannel($civ13->channel_ids['parole_notif'])
                    ) {
                        $message2 = '';
                        if (isset($civ13->role_ids['parolemin'])) $message2 .= "<@&{$civ13->role_ids['parolemin']}>, ";
                        $message2 .= "`$ckey` has log out of `$server`";
                        $parole_log_channel->sendMessage($message2);
                    }

                    if (isset($civ13->current_rounds[$server]) && $civ13->current_rounds[$server])
                        $civ13->logPlayerLogout($server, $ckey, $time);

                    if (! isset($civ13->channel_ids[$server.'_transit_channel']) || ! $channel_id = $civ13->channel_ids[$server.'_transit_channel']) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
                    $message .= "$ckey disconnected from the server.";
                    break;
                case 'token':
                case 'roundstatus':
                case 'status_update':
                    echo "[DATA FOR {$params['method']}]: "; var_dump($params['data']); echo PHP_EOL;
                    break;
                case 'runtimemessage':
                    if (! isset($civ13->channel_ids[$server.'_runtime_channel'])) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
                    $channel_id = $civ13->channel_ids[$server.'_runtime_channel'];
                    $message .= "**__{$time} RUNTIME__**: " . strip_tags($data['message']);
                    break;
                case 'alogmessage':
                    if (! isset($civ13->channel_ids[$server.'_adminlog_channel'])) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
                    $channel_id = $civ13->channel_ids[$server.'_adminlog_channel'];
                    $message .= "**__{$time} ADMIN LOG__**: " . strip_tags($data['message']);
                    break;
                case 'attacklogmessage':
                    if ($server == 'tdm' && ! (! isset($data['ckey2']) || ! $data['ckey2'] || ($data['ckey'] !== $data['ckey2']))) return new Response(200, ['Content-Type' => 'text/html'], 'Done'); // Disabled on TDM, use manual checking of log files instead
                    if (! isset($civ13->channel_ids[$server.'_attack_channel'])) return new Response(400, ['Content-Type' => 'text/plain'], 'Webhook Channel Not Defined');
                    $channel_id = $civ13->channel_ids[$server.'_attack_channel'];
                    $message .= "**__{$time} ATTACK LOG__**: " . strip_tags($data['message']);
                    if (isset($data['ckey']) && isset($data['ckey2'])) if ($data['ckey'] && $data['ckey2']) if ($data['ckey'] === $data['ckey2']) $message .= " (Self-Attack)";
                    break;
                default:
                    $civ13->logger->alert("API UNKNOWN METHOD `{$params['method']}` FROM " . $request->getServerParams()['REMOTE_ADDR']);
                    return new Response(400, ['Content-Type' => 'text/plain'], 'Invalid Parameter');
            }
            if ($message && $channel = $civ13->discord->getChannel($channel_id)) {
                if (! $ckey || ! $item = $civ13->verified->get('ss13', $civ13->sanitizeInput(explode('/', $ckey)[0]))) $channel->sendMessage($message);
                elseif ($user = $civ13->discord->users->get('id', $item['discord'])) {
                    $embed = new Embed($civ13->discord);
                    $embed->setAuthor("{$user->displayname} ({$user->id})", $user->avatar);
                    $embed->setDescription($message);
                    $channel->sendEmbed($embed);
                } elseif ($item) {
                    $civ13->discord->users->fetch($item['discord']);
                    $channel->sendMessage($message);
                } else $channel->sendMessage($message);
            }
            return new Response(200, ['Content-Type' => 'text/html'], 'Done');

        case 'discord2ckey':
            if (! $id || !$webapiSnow($id) || !is_numeric($id)) return $webapiFail('user_id', $id);
            if ($discord2ckey = array_shift($civ13->messageHandler->offsetGet('discord2ckey'))) return new Response(200, ['Content-Type' => 'text/plain'], $discord2ckey($civ13, $id));
            break;
            
        case 'verified':
            return new Response(200, ['Content-Type' => 'text/plain'], json_encode($civ13->verified->toArray()));
            break;
            
        default:
            return new Response(501, ['Content-Type' => 'text/plain'], 'Not implemented');
    }
    // Server-specific
    foreach ($civ13->server_settings as $key => $settings) {
        if (!isset($settings['enabled']) || !$settings['enabled']) continue;
        $server = strtolower($key);
        if ($sub == $key)
            switch ($id) {
                case 'bans':
                    if (! $whitelisted) {
                        $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                        return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
                    }
                    if (! isset($civ13->files[$key.'bans']) || ! $bans = $civ13->files[$key.'bans']) return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `$bans`");
                    if (! $return = @file_get_contents($bans)) return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `$bans`");
                    return new Response(200, ['Content-Type' => 'text/plain'], $return);
                    break;
                case 'playerlogs':
                    if (! $whitelisted) {
                        $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                        return new Response(501, ['Content-Type' => 'text/plain'], 'Reject');
                    }
                    if (! isset($civ13->files[$key.'_playerlogs']) || ! $playerlogs = $civ13->files[$key.'_playerlogs']) return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `$playerlogs`");
                    if (! $return = @file_get_contents($playerlogs)) return new Response(501, ['Content-Type' => 'text/plain'], "Unable to access `$playerlogs`");
                    return new Response(200, ['Content-Type' => 'text/plain'], $return);
                    
                default:
                    return new Response(501, ['Content-Type' => 'text/plain'], 'Not implemented');
            }
            break;
    }
    return new Response(200, ['Content-Type' => 'text/json'], json_encode($return ?? ''));
});
//$webapi->listen($socket); // Moved to civ13.php
$webapi->on('error', function (Exception $e) use ($civ13, $socket) {
    $error = 'API ' . $e->getMessage() . ' [' . $e->getFile() . ':' . $e->getLine() . '] ' . str_replace('\n', PHP_EOL, $e->getTraceAsString());
    $civ13->logger->error($error);
    if (str_starts_with($e->getMessage(), 'The response callback')) {
        $civ13->logger->info('[RESTART] WEBAPI ERROR');
        if (isset($civ13->channel_ids['staff_bot']) && $channel = $civ13->discord->getChannel($civ13->channel_ids['staff_bot'])) {
            $builder = \Discord\Builders\MessageBuilder::new()
                ->setContent('Restarting due to error in HttpServer API...')
                ->addFileFromContent("httpserver_error.txt", $error);
            $channel->sendMessage($builder);
        }
        $socket->close();
        if (! isset($civ13->timers['restart'])) $civ13->timers['restart'] = $civ13->discord->getLoop()->addTimer(5, function () use ($civ13) {
            \restart();
            $civ13->discord->close();
            die();
        });
    }
});