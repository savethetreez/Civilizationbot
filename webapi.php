<?php

/*
 * This file is a part of the Civ13 project.
 *
 * Copyright (c) 2022-present Valithor Obsidion <valithor@valzargaming.com>
 */

function webapiFail($part, $id) {
    //logInfo('[webapi] Failed', ['part' => $part, 'id' => $id]);
    return new \React\Http\Message\Response(($id ? 404 : 400), ['Content-Type' => 'text/plain'], ($id ? 'Invalid' : 'Missing').' '.$part);
}

function webapiSnow($string) {
    return preg_match('/^[0-9]{16,18}$/', $string);
}

$socket = new \React\Socket\Server(sprintf('%s:%s', '0.0.0.0', '55555'), $civ13->loop);
$webapi = new \React\Http\Server($loop, function (\Psr\Http\Message\ServerRequestInterface $request) use ($civ13, $socket)
{
    /*
    $path = explode('/', $request->getUri()->getPath());
    $sub = (isset($path[1]) ? (string) $path[1] : false);
    $id = (isset($path[2]) ? (string) $path[2] : false);
    $id2 = (isset($path[3]) ? (string) $path[3] : false);
    $ip = (isset($path[4]) ? (string) $path[4] : false);
    $idarray = array(); //get from post data (NYI)
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
    $idarray = array(); //get from post data (NYI)
    $civ13->logger->info($echo);
    
    if ($ip) $civ13->logger->info('API IP ' . $ip);
    $whitelist = [
        '127.0.0.1', //local host
        gethostbyname('www.civ13.com'),
        gethostbyname('www.valzargaming.com'),
        '51.254.161.128', //civ13.com
        '69.140.47.22', //valzargaming.com
    ];
    if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) {
        $civ13->logger->info('API REMOTE_ADDR ' . $request->getServerParams()['REMOTE_ADDR']);
        return;
    }

    switch ($sub) {
        case (str_starts_with($sub, 'index.')):
            $return = '<meta http-equiv = \"refresh\" content = \"0; url = https://www.valzargaming.com/?login\" />'; //Redirect to the website to log in
            return new \React\Http\Message\Response(200, ['Content-Type' => 'text/html'], $return);
            break;
        case 'github':
            $return = '<meta http-equiv = \"refresh\" content = \"0; url = https://github.com/VZGCoders/Civilizationbot\" />'; //Redirect to the website to log in
            return new \React\Http\Message\Response(200, ['Content-Type' => 'text/html'], $return);
            break;
        case 'favicon.ico':
            if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) { //Restricted for obvious reasons
                $civ13->logger->info('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            $favicon = file_get_contents('favicon.ico');
            return new \React\Http\Message\Response(200, ['Content-Type' => 'image/x-icon'], $favicon);
        
        case 'nohup.out':
            if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) { //Restricted for obvious reasons
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if ($return = file_get_contents('nohup.out')) return new \React\Http\Message\Response(200, ['Content-Type' => 'text/plain'], $return);
            else return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], "Unable to access `nohup.out`");
            break;
        
        case 'botlog':
            if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) { //Restricted for obvious reasons
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if ($return = file_get_contents('botlog.txt')) return new \React\Http\Message\Response(200, ['Content-Type' => 'text/plain'], $return);
            else return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], "Unable to access `botlog.txt`");
            break;
            
        case 'botlog2':
            if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) { //Restricted for obvious reasons
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if ($return = file_get_contents('botlog2.txt')) return new \React\Http\Message\Response(200, ['Content-Type' => 'text/plain'], $return);
            else return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], "Unable to access `botlog2.txt`");
            break;
        
        case 'channel':
            if (!$id || !webapiSnow($id) || !$return = $civ13->discord->getChannel($id))
                return webapiFail('channel_id', $id);
            break;

        case 'guild':
            if (!$id || !webapiSnow($id) || !$return = $civ13->discord->guilds->offsetGet($id))
                return webapiFail('guild_id', $id);
            break;

        case 'bans':
            if (!$id || !webapiSnow($id) || !$guild = $civ13->discord->guilds->offsetGet($id))
                return webapiFail('guild_id', $id);
            $return = $guild->bans;
            break;

        case 'channels':
            if (!$id || !webapiSnow($id) || !$guild = $civ13->discord->guilds->offsetGet($id))
                return webapiFail('guild_id', $id);
            $return = $guild->channels;
            break;

        case 'members':
            if (!$id || !webapiSnow($id) || !$guild = $civ13->discord->guilds->offsetGet($id))
                return webapiFail('guild_id', $id);
            $return = $guild->members;
            break;

        case 'emojis':
            if (!$id || !webapiSnow($id) || !$guild = $civ13->discord->guilds->offsetGet($id))
                return webapiFail('guild_id', $id);
            $return = $guild->emojis;
            break;

        case 'invites':
            if (!$id || !webapiSnow($id) || !$guild = $civ13->discord->guilds->offsetGet($id))
                return webapiFail('guild_id', $id);
            $return = $guild->invites;
            break;

        case 'roles':
            if (!$id || !webapiSnow($id) || !$guild = $civ13->discord->guilds->offsetGet($id))
                return webapiFail('guild_id', $id);
            $return = $guild->roles;
            break;

        case 'guildMember':
            if (!$id || !webapiSnow($id) || !$guild = $civ13->discord->guilds->offsetGet($id))
                return webapiFail('guild_id', $id);
            if (!$id2 || !webapiSnow($id2) || !$return = $guild->members->offsetGet($id2))
                return webapiFail('user_id', $id2);
            break;

        case 'user':
            if (!$id || !webapiSnow($id) || !$return = $civ13->discord->users->offsetGet($id)) {
                return webapiFail('user_id', $id);
            }
            break;

        case 'userName':
            if (!$id || !$return = $civ13->discord->users->get('name', $id))
                return webapiFail('user_name', $id);
            break;
        
        case 'reset':
            if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) { //Restricted for obvious reasons
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            execInBackground('git reset --hard origin/main');
            $return = 'fixing git';
            break;
        
        case 'pull':
            if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) { //Restricted for obvious reasons
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            execInBackground('git pull');
            $civ13->logger->info('[GIT PULL]');
            if ($channel = $civ13->discord->getChannel('712685552155230278'))
                $channel->sendMessage('Updating code from GitHub...');
            $return = 'updating code';
            break;
        
        case 'update':
            if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) { //Restricted for obvious reasons
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            execInBackground('composer update');
            $civ13->logger->info('[COMPOSER UPDATE]');
            if ($channel = $civ13->discord->getChannel('712685552155230278'))
                $channel->sendMessage('Updating dependencies...');
            $return = 'updating dependencies';
            break;
        
        case 'restart':
            if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) { //Restricted for obvious reasons
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            $civ13->logger->info('[RESTART]');
            if ($channel = $civ13->discord->getChannel('712685552155230278'))
                $channel->sendMessage('Restarting...');
            $return = 'restarting';
            $socket->close();
            $civ13->discord->getLoop()->addTimer(5, function () use ($civ13, $socket) {
                \restart();
                $civ13->discord->close();
                die();
            });
            break;

        case 'lookup':
            if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if (!$id || !webapiSnow($id) || !$return = $civ13->discord->users->offsetGet($id))
                return webapiFail('user_id', $id);
            break;

        case 'owner':
            if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) {
                $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
            }
            if (!$id || !webapiSnow($id))
                return webapiFail('user_id', $id);
            $return = false;
            if ($user = $civ13->discord->users->offsetGet($id)) { //Search all guilds the bot is in and check if the user id exists as a guild owner
                foreach ($discord->guilds as $guild) {
                    if ($id == $guild->owner_id) {
                        $return = true;
                        break 1;
                    }
                }
            }
            break;

        case 'avatar':
            if (!$id || !webapiSnow($id)) {
                return webapiFail('user_id', $id);
            }
            if (!$user = $civ13->discord->users->offsetGet($id)) {
                $civ13->discord->users->fetch($id)->done(
                    function ($user) {
                        $return = $user->avatar;
                        return new \React\Http\Message\Response(200, ['Content-Type' => 'text/plain'], $return);
                    }, function ($error) {
                        return webapiFail('user_id', $id);
                    }
                );
                $return = 'https://cdn.discordapp.com/embed/avatars/'.rand(0,4).'.png';
            }else{
                $return = $user->avatar;
            }
            //if (!$return) return new \React\Http\Message\Response(($id ? 404 : 400), ['Content-Type' => 'text/plain'], (''));
            break;

        case 'avatars':
            $idarray = $data ?? array(); // $data contains POST data
            $results = [];
            $promise = $civ13->discord->users->fetch($idarray[0])->then(function ($user) use (&$results) {
              $results[$user->id] = $user->avatar;
            });
            
            for ($i = 1; $i < count($idarray); $i++) {
                $discord = $civ13->discord;
                $promise->then(function () use (&$results, $idarray, $i, $discord) {
                return $civ13->discord->users->fetch($idarray[$i])->then(function ($user) use (&$results) {
                    $results[$user->id] = $user->avatar;
                });
              });
            }

            $promise->done(function () use ($results) {
              return new \React\Http\Message\Response (200, ['Content-Type' => 'application/json'], json_encode($results));
            }, function () use ($results) {
              // return with error ?
              return new \React\Http\Message\Response(200, ['Content-Type' => 'application/json'], json_encode($results));
            });
            break;
        
        case 'nomads':
            switch ($id) {
                case 'bans':
                    if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) { //Restricted for obvious reasons
                        $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                        return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
                    }
                    $nomads_bans = $civ13->files['nomads_bans'];
                    if ($return = file_get_contents($nomads_bans)) return new \React\Http\Message\Response(200, ['Content-Type' => 'text/plain'], $return);
                    else return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], "Unable to access `$nomads_bans`");
                    break;
                default:
                    return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Not implemented');
            }
            break;
        case 'nomads':
            switch ($id) {
                case 'bans':
                    if (substr($request->getServerParams()['REMOTE_ADDR'], 0, 6) != '10.0.0' && ! in_array($request->getServerParams()['REMOTE_ADDR'], $whitelist) ) { //Restricted for obvious reasons
                        $civ13->logger->alert('API REJECT ' . $request->getServerParams()['REMOTE_ADDR']);
                        return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Reject');
                    }
                    $tdm_bans = $civ13->files['tdm_bans'];
                    if ($return = file_get_contents($tdm_bans)) return new \React\Http\Message\Response(200, ['Content-Type' => 'text/plain'], $return);
                    else return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], "Unable to access `$tdm_bans`");
                    break;
                default:
                    return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Not implemented');
            }
            break;
        
        case 'discord2ckey':
            if (!$id || !webapiSnow($id) || !is_numeric($id))
                return webapiFail('user_id', $id);
            $discord2ckey = $civ13->functions['misc']['discord2ckey'];
            $return = $discord2ckey($civ13, $id);
            return new \React\Http\Message\Response(200, ['Content-Type' => 'text/plain'], $return);
            break;
            
        default:
            return new \React\Http\Message\Response(501, ['Content-Type' => 'text/plain'], 'Not implemented');
    }
    return new \React\Http\Message\Response(200, ['Content-Type' => 'text/json'], json_encode($return));
});
$webapi->listen($socket);
$webapi->on('error', function ($e) {
    $civ13->logger->error('API ' . $e->getMessage());
});