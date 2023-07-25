<?php

/*
 * This file is a part of the Civ13 project.
 *
 * Copyright (c) 2022-present Valithor Obsidion <valithor@valzargaming.com>
 */ 

use Civ13\Civ13;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Embed\Embed;
use Discord\Parts\User\Activity;
use React\Promise\Promise;

$status_changer_random = function(Civ13 $civ13): bool
{ // on ready
    if (! $civ13->files['status_path']) {
        unset($civ13->timers['status_changer_timer']);
        $civ13->logger->warning('status_path is not defined');
        return false;
    }
    if (! $status_array = file($civ13->files['status_path'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) {
        unset($civ13->timers['status_changer_timer']);
        $civ13->logger->warning("unable to open file `{$civ13->files['status_path']}`");
        return false;
    }
    list($status, $type, $state) = explode('; ', $status_array[array_rand($status_array)]);
    if (! $status) return false;
    $activity = new Activity($civ13->discord, [ // Discord status            
        'name' => $status,
        'type' => (int) $type, // 0, 1, 2, 3, 4 | Game/Playing, Streaming, Listening, Watching, Custom Status
    ]);
    $civ13->statusChanger($activity, $state);
    return true;
};
$status_changer_timer = function(Civ13 $civ13) use ($status_changer_random): void
{ // on ready
    $civ13->timers['status_changer_timer'] = $civ13->discord->getLoop()->addPeriodicTimer(120, function() use ($civ13, $status_changer_random) { $status_changer_random($civ13); });
};

$log_handler = function(Civ13 $civ13, $message, string $message_content)
{
    $tokens = explode(';', $message_content);
    $keys = [];
    foreach (array_keys($civ13->server_settings) as $key) {
        $keys[] = $server = strtolower($key);
        if (! trim($tokens[0]) == $server) continue; // Check if server is valid
        
        if (! isset($civ13->files[$server.'_log_basedir']) || ! file_exists($civ13->files[$server.'_log_basedir'])) {
            $civ13->logger->warning("`{$server}_log_basedir` is not defined or does not exist");
            return $message->react("🔥");
        }
        
        unset($tokens[0]);
        $results = $civ13->FileNav($civ13->files[$server.'_log_basedir'], $tokens);
        if ($results[0]) return $message->reply(MessageBuilder::new()->addFile($results[1], 'log.txt'));
        if (count($results[1]) > 7) $results[1] = [array_pop($results[1]), array_pop($results[1]), array_pop($results[1]), array_pop($results[1]), array_pop($results[1]), array_pop($results[1]), array_pop($results[1])];
        if (! isset($results[2]) || ! $results[2]) return $message->reply('Available options: ' . PHP_EOL . '`' . implode('`' . PHP_EOL . '`', $results[1]) . '`');
        return $message->reply("{$results[2]} is not an available option! Available options: " . PHP_EOL . '`' . implode('`' . PHP_EOL . '`', $results[1]) . '`');
    }
    return $message->reply('Please use the format `logs {server}`. Valid servers: `' . implode(', ', $keys) . '`');
};

$ranking = function(Civ13 $civ13): false|string
{
    $line_array = array();
    if (! file_exists($civ13->files['ranking_path']) || ! $search = @fopen($civ13->files['ranking_path'], 'r')) return false;
    while (($fp = fgets($search, 4096)) !== false) $line_array[] = $fp;
    fclose($search);

    $topsum = 1;
    $msg = '';
    foreach ($line_array as $line) {
        $sline = explode(';', trim(str_replace(PHP_EOL, '', $line)));
        $msg .= "($topsum): **{$sline[1]}** with **{$sline[0]}** points." . PHP_EOL;
        if (($topsum += 1) > 10) break;
    }
    return $msg;
};
$rankme = function(Civ13 $civ13, string $ckey): false|string
{
    $line_array = array();
    if (! file_exists($civ13->files['ranking_path']) || ! $search = @fopen($civ13->files['ranking_path'], 'r')) return false;
    while (($fp = fgets($search, 4096)) !== false) $line_array[] = $fp;
    fclose($search);
    
    $found = false;
    $result = '';
    foreach ($line_array as $line) {
        $sline = explode(';', trim(str_replace(PHP_EOL, '', $line)));
        if ($sline[1] == $ckey) {
            $found = true;
            $result .= "**{$sline[1]}** has a total rank of **{$sline[0]}**";
        };
    }
    if (! $found) return "No medals found for ckey `$ckey`.";
    return $result;
};
$medals = function(Civ13 $civ13, string $ckey): false|string
{
    $result = '';
    if (! file_exists($civ13->files['tdm_awards_path']) || ! $search = @fopen($civ13->files['tdm_awards_path'], 'r')) return false;
    $found = false;
    while (! feof($search)) if (str_contains($line = trim(str_replace(PHP_EOL, '', fgets($search))), $ckey)) {  # remove '\n' at end of line
        $found = true;
        $duser = explode(';', $line);
        if ($duser[0] == $ckey) {
            switch ($duser[2]) {
                case 'long service medal': $medal_s = '<:long_service:705786458874707978>'; break;
                case 'combat medical badge': $medal_s = '<:combat_medical_badge:706583430141444126>'; break;
                case 'tank destroyer silver badge': $medal_s = '<:tank_silver:705786458882965504>'; break;
                case 'tank destroyer gold badge': $medal_s = '<:tank_gold:705787308926042112>'; break;
                case 'assault badge': $medal_s = '<:assault:705786458581106772>'; break;
                case 'wounded badge': $medal_s = '<:wounded:705786458677706904>'; break;
                case 'wounded silver badge': $medal_s = '<:wounded_silver:705786458916651068>'; break;
                case 'wounded gold badge': $medal_s = '<:wounded_gold:705786458845216848>'; break;
                case 'iron cross 1st class': $medal_s = '<:iron_cross1:705786458572587109>'; break;
                case 'iron cross 2nd class': $medal_s = '<:iron_cross2:705786458849673267>'; break;
                default:  $medal_s = '<:long_service:705786458874707978>';
            }
            $result .= "**{$duser[1]}:** {$medal_s} **{$duser[2]}**, *{$duser[4]}*, {$duser[5]}" . PHP_EOL;
        }
    }
    if ($result != '') return $result;
    if (! $found && ($result == '')) return 'No medals found for this ckey.';
};
$brmedals = function(Civ13 $civ13, string $ckey): string
{
    $result = '';
    if (! file_exists($civ13->files['tdm_awards_br_path']) || ! $search = @fopen($civ13->files['tdm_awards_br_path'], 'r')) return 'Error getting file.';
    $found = false;
    while (! feof($search)) if (str_contains($line = trim(str_replace(PHP_EOL, '', fgets($search))), $ckey)) {
        $found = true;
        $duser = explode(';', $line);
        if ($duser[0] == $ckey) $result .= "**{$duser[1]}:** placed *{$duser[2]} of {$duser[5]},* on {$duser[4]} ({$duser[3]})" . PHP_EOL;
    }
    if (! $found) return 'No medals found for this ckey.';
    return $result;
};

$tests = function(Civ13 $civ13, $message, string $message_content)
{
    $tokens = explode(' ', $message_content);
    if (! $tokens[0]) {
        if (empty($civ13->tests)) return $message->reply("No tests have been created yet! Try creating one with `tests test_key add {Your Test's Question}`");
        return $message->reply('Available tests: `' . implode('`, `', array_keys($civ13->tests)) . '`');
    }
    if (! isset($tokens[1]) || (! array_key_exists($test_key = $tokens[0], $civ13->tests) && $tokens[1] != 'add')) return $message->reply("Test `$test_key` hasn't been created yet! Please add a question first.");
    if ($tokens[1] == 'list') return $message->reply(MessageBuilder::new()->addFileFromContent("$test_key.txt", var_export($civ13->tests[$test_key], true)));
    if ($tokens[1] == 'add') {
        unset ($tokens[1], $tokens[0]);
        $civ13->tests[$test_key][] = $question = implode(' ', $tokens);
        $message->reply("Added question to test $test_key: $question");
        return $civ13->VarSave('tests.json', $civ13->tests);
    }
    if ($tokens[1] == 'remove') {
        if (! is_numeric($tokens[2])) return $message->replay("Invalid format! Please use the format `tests test_key remove #`");
        if (! isset($civ13->tests[$test_key][$tokens[2]])) return $message->reply("Question not found in test $test_key! Please use the format `tests test_key remove #`");
        $message->reply("Removed question {$tokens[2]}: {$civ13->tests[$test_key][$tokens[2]]}");
        unset($civ13->tests[$test_key][$tokens[2]]);
        return $civ13->VarSave('tests.json', $civ13->tests);
    }
    if ($tokens[1] == 'post') {
        if (! is_numeric($tokens[2])) return $message->replay("Invalid format! Please use the format `tests test_key post #`");
        if (count($civ13->tests[$test_key])<$tokens[2]) return $message->replay("Can't return more questions than exist in a test!");
        $questions = [];
        while (count($questions)<$tokens[2]) if (! in_array($civ13->tests[$test_key][($rand = array_rand($civ13->tests[$test_key]))], $questions)) $questions[] = $civ13->tests[$test_key][$rand];
        return $message->reply("$test_key test:" . PHP_EOL . implode(PHP_EOL, $questions));
    }
    if ($tokens[1] == 'delete') {
        $message->reply("Deleted test `$test_key`");
        unset($civ13->tests[$test_key]);
        return $civ13->VarSave('tests.json', $civ13->tests);
    }
};

$banlog_update = function(string $banlog, array $playerlogs, $ckey = null): string
{
    $temp = [];
    $oldlist = [];
    foreach (explode('|||', $banlog) as $bsplit) {
        $ban = explode(';', trim($bsplit));
        if (isset($ban[9]))
            if (!isset($ban[9]) || !isset($ban[10]) || $ban[9] == '0' || $ban[10] == '0') {
                if (! $ckey) $temp[$ban[8]][] = $bsplit;
                elseif ($ckey == $ban[8]) $temp[$ban[8]][] = $bsplit;
            } else $oldlist[] = $bsplit;
    }
    foreach ($playerlogs as $playerlog)
    foreach (explode('|', $playerlog) as $lsplit) {
        $log = explode(';', trim($lsplit));
        foreach (array_values($temp) as &$b2) foreach ($b2 as &$arr) {
            $a = explode(';', $arr);
            if ($a[8] == $log[0]) {
                $a[9] = $log[2];
                $a[10] = $log[1];
                $arr = implode(';', $a);
            }
        }
    }

    $updated = [];
    foreach (array_values($temp) as $ban)
        if (is_array($ban)) foreach (array_values($ban) as $b) $updated[] = $b;
        else $updated[] = $ban;
    
    if (empty($updated)) return preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", PHP_EOL, trim(implode('|||' . PHP_EOL, $oldlist))) . '|||' . PHP_EOL;
    return trim(preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", PHP_EOL, implode('|||' . PHP_EOL, array_merge($oldlist, $updated)))) . '|||' . PHP_EOL;
};

$rank_check = function(Civ13 $civ13, $message = null, array $allowed_ranks = [], $verbose = true): bool
{
    $resolved_ranks = [];
    foreach ($allowed_ranks as $rank) $resolved_ranks[] = $civ13->role_ids[$rank];
    foreach ($message->member->roles as $role) if (in_array($role->id, $resolved_ranks)) return true;
    // $message->reply('Rejected! You need to have at least the [' . ($message->guild->roles ? $message->guild->roles->get('id', $civ13->role_ids[array_pop($resolved_ranks)])->name : array_pop($allowed_ranks)) . '] rank.');
    if ($verbose && $message) $message->reply('Rejected! You need to have at least the <@&' . $civ13->role_ids[array_pop($allowed_ranks)] . '> rank.');
    return false;
};
$guild_message = function(Civ13 $civ13, $message, string $message_content, string $message_content_lower) use ($rank_check, $log_handler, $ranking, $rankme, $medals, $brmedals, $tests, $banlog_update): ?Promise
{
    if (! $message->member) return $message->reply('Error! Unable to get Discord Member class.');
    
    if (str_starts_with($message_content_lower, 'approveme')) {
        if ($message->member->roles->has($civ13->role_ids['infantry']) || $message->member->roles->has($civ13->role_ids['veteran'])) return $message->reply('You already have the verification role!');
        if ($item = $civ13->getVerifiedItem($message->author->id)) {
            $message->member->setRoles([$civ13->role_ids['infantry']], "approveme {$item['ss13']}");
            return $message->react("👍");
        }
        if (! $ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('approveme')))) return $message->reply('Invalid format! Please use the format `approveme ckey`');
        return $message->reply($civ13->verifyProcess($ckey, $message->author->id));
    }
    if (str_starts_with($message_content_lower, 'relay') || str_starts_with($message_content_lower, 'relay')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain'])) return $message->react("❌");
        $civ13->relay_method === 'file' ? $method = 'webhook' : $method = 'file';
        $civ13->relay_method = $method;
        return $message->reply("Relay method changed to `$method`.");
    }
    if (str_starts_with($message_content_lower, 'ckeyinfo')) {
        $high_staff = $rank_check($civ13, $message, ['admiral', 'captain'], false);
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if (! $id = $civ13->sanitizeInput(substr($message_content_lower, strlen('ckeyinfo')))) return $message->reply('Invalid format! Please use the format: ckeyinfo `ckey`');
        if (is_numeric($id)) {
            if (! $item = $civ13->getVerifiedItem($id)) return $message->reply("No data found for Discord ID `$id`.");
            $ckey = $item['ss13'];
        } else $ckey = $id;
        if (! $collectionsArray = $civ13->getCkeyLogCollections($ckey)) return $message->reply('No data found for that ckey.');
        // $civ13->logger->debug('Collections array:', $collectionsArray, PHP_EOL);

        $embed = new Embed($civ13->discord);
        $embed->setTitle($ckey);
        if ($item = $civ13->getVerifiedItem($ckey)) {
            $ckey = $item['ss13'];
            if ($member = $civ13->getVerifiedMember($item))
                $embed->setAuthor("{$member->user->displayname} ({$member->id})", $member->avatar);
        }
        $ckeys = [$ckey];
        $ips = [];
        $cids = [];
        $dates = [];
        foreach ($collectionsArray[0] as $log) { // Get the ckey's primary identifiers
            if (isset($log['ip']) && ! in_array($log['ip'], $ips)) $ips[] = $log['ip'];
            if (isset($log['cid']) && ! in_array($log['cid'], $cids)) $cids[] = $log['cid'];
            if (isset($log['date']) && ! in_array($log['date'], $dates)) $dates[] = $log['date'];
        }
        foreach ($collectionsArray[1] as $log) { // Get the ckey's primary identifiers
            if (isset($log['ip']) && ! in_array($log['ip'], $ips)) $ips[] = $log['ip'];
            if (isset($log['cid']) && ! in_array($log['cid'], $cids)) $cids[] = $log['cid'];
            if (isset($log['date']) && ! in_array($log['date'], $dates)) $dates[] = $log['date'];
        }
        // $civ13->logger->debug('Primary identifiers:', $ckeys, $ips, $cids, $dates, PHP_EOL);
        $ckey_age = [];
        if (!empty($ckeys)) {
            foreach ($ckeys as $c) ($age = $civ13->getByondAge($c)) ? $ckey_age[$c] = $age : $ckey_age[$c] = "N/A";
            $ckey_age_string = '';
            foreach ($ckey_age as $key => $value) $ckey_age_string .= " $key ($value) ";
            $embed->addFieldValues('Primary Ckeys', trim($ckey_age_string));
        }
        if ($high_staff) {
            if (!empty($ips)) $embed->addFieldValues('Primary IPs', implode(', ', $ips), true);
            if (!empty($cids)) $embed->addFieldValues('Primary CIDs', implode(', ', $cids), true);
        }
        if (!empty($dates)) $embed->addFieldValues('Primary Dates', implode(', ', $dates));

        // Iterate through the playerlogs ban logs to find all known ckeys, ips, and cids
        $playerlogs = $civ13->playerlogsToCollection(); // This is ALL players
        $i = 0;
        $break = false;
        do { // Iterate through playerlogs to find all known ckeys, ips, and cids
            $found = false;
            $found_ckeys = [];
            $found_ips = [];
            $found_cids = [];
            $found_dates = [];
            foreach ($playerlogs as $log) if (in_array($log['ckey'], $ckeys) || in_array($log['ip'], $ips) || in_array($log['cid'], $cids)) {
                // $civ13->logger->debug('Found new match:', $log, PHP_EOL);
                if (! in_array($log['ckey'], $ckeys)) { $found_ckeys[] = $log['ckey']; $found = true; }
                if (! in_array($log['ip'], $ips)) { $found_ips[] = $log['ip']; $found = true; }
                if (! in_array($log['cid'], $cids)) { $found_cids[] = $log['cid']; $found = true; }
                if (! in_array($log['date'], $dates)) { $found_dates[] = $log['date']; }
            }
            $ckeys = array_unique(array_merge($ckeys, $found_ckeys));
            $ips = array_unique(array_merge($ips, $found_ips));
            $cids = array_unique(array_merge($cids, $found_cids));
            $dates = array_unique(array_merge($dates, $found_dates));
            if ($i > 10) $break = true;
            $i++;
        } while ($found && ! $break); // Keep iterating until no new ckeys, ips, or cids are found

        $banlogs = $civ13->bansToCollection();
        $civ13->bancheck($ckey) ? $banned = 'Yes' : $banned = 'No';
        $found = true;
        $i = 0;
        $break = false;
        do { // Iterate through playerlogs to find all known ckeys, ips, and cids
            $found = false;
            $found_ckeys = [];
            $found_ips = [];
            $found_cids = [];
            $found_dates = [];
            foreach ($banlogs as $log) if (in_array($log['ckey'], $ckeys) || in_array($log['ip'], $ips) || in_array($log['cid'], $cids)) {
                // $civ13->logger->debug('Found new match: ', $log, PHP_EOL);
                if (! in_array($log['ckey'], $ips)) { $found_ckeys[] = $log['ckey']; $found = true; }
                if (! in_array($log['ip'], $ips)) { $found_ips[] = $log['ip']; $found = true; }
                if (! in_array($log['cid'], $cids)) { $found_cids[] = $log['cid']; $found = true; }
                if (! in_array($log['date'], $dates)) { $found_dates[] = $log['date']; }
            }
            $ckeys = array_unique(array_merge($ckeys, $found_ckeys));
            $ips = array_unique(array_merge($ips, $found_ips));
            $cids = array_unique(array_merge($cids, $found_cids));
            $dates = array_unique(array_merge($dates, $found_dates));
            if ($i > 10) $break = true;
            $i++;
        } while ($found && ! $break); // Keep iterating until no new ckeys, ips, or cids are found
        $altbanned = 'No';
        foreach ($ckeys as $key) if ($key != $ckey) if ($civ13->bancheck($key)) { $altbanned = 'Yes'; break; }

        $verified = 'No';
        if ($civ13->verified->get('ss13', $ckey)) $verified = 'Yes';
        if (!empty($ckeys)) {
            foreach ($ckeys as $c) if (! isset($ckey_age[$c])) ($age = $civ13->getByondAge($c)) ? $ckey_age[$c] = $age : $ckey_age[$c] = "N/A";
            $ckey_age_string = '';
            foreach ($ckey_age as $key => $value) $ckey_age_string .= "$key ($value) ";
            $embed->addFieldValues('Matched Ckeys', trim($ckey_age_string));
        }
        if ($high_staff) {
            if (!empty($ips)) $embed->addFieldValues('Matched IPs', implode(', ', $ips), true);
            if (!empty($cids)) $embed->addFieldValues('Matched CIDs', implode(', ', $cids), true);
        }
        if (!empty($ips)) {
            $regions = [];
            foreach ($ips as $ip) if (! in_array($region = $civ13->IP2Country($ip), $regions)) $regions[] = $region;
            $embed->addFieldValues('Regions', implode(', ', $regions));
        }
        if (!empty($dates) && strlen($dates_string = implode(', ', $dates)) <= 1024) $embed->addFieldValues('Dates', $dates_string);
        $embed->addfieldValues('Verified', $verified, true);
        $discords = [];
        foreach ($ckeys as $c) if ($item = $civ13->verified->get('ss13', $c)) $discords[] = $item['discord'];
        if ($discords) {
            foreach ($discords as &$id) $id = "<@{$id}>";
            $embed->addfieldValues('Discord', implode(', ', $discords));
        }
        $embed->addfieldValues('Currently Banned', $banned, true);
        $embed->addfieldValues('Alt Banned', $altbanned, true);
        $embed->addfieldValues('Ignoring banned alts or new account age', isset($civ13->permitted[$ckey]) ? 'Yes' : 'No', true);
        $builder = MessageBuilder::new();
        if (! $high_staff) $builder->setContent('IPs and CIDs have been hidden for privacy reasons.');
        $builder->addEmbed($embed);
        $message->reply($builder);
    }
    if (str_starts_with($message_content_lower, 'fullbancheck')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain'])) return $message->react("❌");
        foreach ($message->guild->members as $member)
            if ($item = $civ13->getVerifiedItem($member->id))
                $civ13->bancheck($item['ss13']);
        return $message->react("👍");
    }
    if (str_starts_with($message_content_lower, 'fullaltcheck')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain'])) return $message->react("❌");
        $ckeys = [];
        $members = $message->guild->members->filter(function ($member) use ($civ13) { return !$member->roles->has($civ13->role_ids['banished']); });
        foreach ($members as $member)
            if ($item = $civ13->getVerifiedItem($member->id)) {
                $ckeyinfo = $civ13->ckeyinfo($item['ss13']);
                if (count($ckeyinfo['ckeys']) > 1)
                    $ckeys = array_unique(array_merge($ckeys, $ckeyinfo['ckeys']));
            }
        return $message->reply("The following ckeys are alt accounts of verified players:" . PHP_EOL . '`' . implode('`' . PHP_EOL . '`', $ckeys) . '`');
    }
    if (str_starts_with($message_content_lower, 'register')) { // This function is only authorized to be used by the database administrator
        if ($message->author->id != $civ13->technician_id) return $message->react("❌");
        $split_message = explode(';', trim(substr($message_content_lower, strlen('register'))));
        if (! $ckey = $civ13->sanitizeInput($split_message[0])) return $message->reply('Byond username was not passed. Please use the format `register <byond username>; <discord id>`.');
        if (! is_numeric($discord_id = $civ13->sanitizeInput($split_message[1]))) return $message->reply("Discord id `$discord_id` must be numeric.");
        return $message->reply($civ13->registerCkey($ckey, $discord_id)['error']);
    }
    if (str_starts_with($message_content_lower, 'discard')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if (! $ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('discard')))) return $message->reply('Byond username was not passed. Please use the format `discard <byond username>`.');
        $string = "`$ckey` will no longer attempt to be automatically registered.";
        if (isset($civ13->provisional[$ckey])) {
            if ($member = $message->guild->members->get($civ13->provisional[$ckey])) {
                $member->removeRole($civ13->role_ids['infantry']);
                $string .= " The <@&{$civ13->role_ids['infantry']}> role has been removed from $member.";
            }
            unset($civ13->provisional[$ckey]);
            $civ13->VarSave('provisional.json', $civ13->provisional);
        }
        return $message->reply($string);
    }
    if ($message_content_lower == 'permitted') {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if (empty($civ13->permitted)) return $message->reply('No users have been permitted to bypass the Byond account restrictions.');
        return $message->reply('The following ckeys are now permitted to bypass the Byond account limit and restrictions: ' . PHP_EOL . '`' . implode('`' . PHP_EOL . '`', array_keys($civ13->permitted)) . '`');
    }
    if (str_starts_with($message_content_lower, 'permit')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        $civ13->permitCkey($ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('permit'))));
        return $message->reply("$ckey is now permitted to bypass the Byond account restrictions.");
    }
    if (str_starts_with($message_content_lower, 'unpermit')) { // Alias for revoke
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        $civ13->permitCkey($ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('unpermit'))), false);
        return $message->reply("$ckey is no longer permitted to bypass the Byond account restrictions.");
    }
    if (str_starts_with($message_content_lower, 'revoke')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        $civ13->permitCkey($ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('revoke'))), false);
        return $message->reply("$ckey is no longer permitted to bypass the Byond account restrictions.");
    }
    if (str_starts_with($message_content_lower, 'parole')) {
        if (! isset($civ13->role_ids['paroled'])) return $message->react("🔥");
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if (! $item = $civ13->getVerifiedItem($id = $civ13->sanitizeInput(substr($message_content_lower, strlen('parole'))))) return $message->reply("<@{$id}> is not currently verified with a byond username or it does not exist in the cache yet");
        $civ13->paroleCkey($ckey = $item['ss13'], $message->author->id, true);
        $admin = $civ13->getVerifiedItem($message->author->id)['ss13'];
        if ($member = $civ13->getVerifiedMember($item))
            if (! $member->roles->has($civ13->role_ids['paroled']))
                $member->addRole($civ13->role_ids['paroled'], "`$admin` ({$message->member->displayname}) paroled `$ckey`");
        if ($channel = $civ13->discord->getChannel($civ13->channel_ids['parole_logs'])) $channel->sendMessage("`$ckey` (<@{$item['discord']}>) has been placed on parole by `$admin` (<@{$message->author->id}>).");
        return $message->react("👍");
    }
    if (str_starts_with($message_content_lower, 'release')) {
        if (! isset($civ13->role_ids['paroled'])) return $message->react("🔥");
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if (! $item = $civ13->getVerifiedItem($id = $civ13->sanitizeInput(substr($message_content_lower, strlen('release'))))) return $message->reply("<@{$id}> is not currently verified with a byond username or it does not exist in the cache yet");
        $civ13->paroleCkey($ckey = $item['ss13'], $message->author->id, false);
        $admin = $civ13->getVerifiedItem($message->author->id)['ss13'];
        if ($member = $civ13->getVerifiedMember($item))
            if ($member->roles->has($civ13->role_ids['paroled']))
                $member->removeRole($civ13->role_ids['paroled'], "`$admin` ({$message->member->displayname}) released `$ckey`");
        if ($channel = $civ13->discord->getChannel($civ13->channel_ids['parole_logs'])) $channel->sendMessage("`$ckey` (<@{$item['discord']}>) has been released from parole by `$admin` (<@{$message->author->id}>).");
        return $message->react("👍");
    }

    if (str_starts_with($message_content_lower, 'tests')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain'])) return $message->react("❌"); 
        return $tests($civ13, $message, trim(substr($message_content, strlen('tests'))));
    }
    
    if (str_starts_with($message_content_lower, 'promotable')) {
        if (! $promotable_check = $civ13->functions['misc']['promotable_check']) return $message->react("🔥");
        if (! $rank_check($civ13, $message, ['admiral', 'captain'])) return $message->react("❌"); 
        if (! $promotable_check($civ13, $civ13->sanitizeInput(substr($message_content, 10)))) return $message->react("👎");
        return $message->react("👍");
    }
    
    if (str_starts_with($message_content_lower, 'mass_promotion_loop')) {
        if (! $mass_promotion_loop = $civ13->functions['misc']['mass_promotion_loop']) return $message->react("🔥");
        if (! $rank_check($civ13, $message, ['admiral', 'captain'])) return $message->react("❌"); 
        if (! $mass_promotion_loop($civ13)) return $message->react("👎");
        return $message->react("👍");
    }
    
    if (str_starts_with($message_content_lower, 'mass_promotion_check')) {
        if (! $mass_promotion_check = $civ13->functions['misc']['mass_promotion_check']) return $message->react("🔥");
        if (! $rank_check($civ13, $message, ['admiral', 'captain'])) return $message->react("❌"); 
        if ($promotables = $mass_promotion_check($civ13)) return $message->reply(MessageBuilder::new()->addFileFromContent('promotables.txt', json_encode($promotables)));
        return $message->react("👎");
    }
    
    if (str_starts_with($message_content_lower, 'refresh')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if ($civ13->getVerified()) return $message->react("👍");
        return $message->react("👎");
    }
    if (str_starts_with($message_content_lower, 'ban ')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        $message_content = substr($message_content, 4);
        $split_message = explode('; ', $message_content);
        if (! $split_message[0] = $civ13->sanitizeInput($split_message[0])) return $message->reply('Missing ban ckey! Please use the format `ban ckey; duration; reason`');
        if (! $split_message[1]) return $message->reply('Missing ban duration! Please use the format `ban ckey; duration; reason`');
        if (! $split_message[2]) return $message->reply('Missing ban reason! Please use the format `ban ckey; duration; reason`');
        $arr = ['ckey' => $split_message[0], 'duration' => $split_message[1], 'reason' => $split_message[2] . " Appeal at {$civ13->banappeal}"];

        foreach (array_keys($civ13->server_settings) as $key) {
            $server = strtolower($key);
            $civ13->timers['banlog_update_'.$server] = $civ13->discord->getLoop()->addTimer(30, function() use ($civ13, $server, $banlog_update, $arr) {
                $playerlogs = [];
                foreach (array_keys($civ13->server_settings) as $key) {
                    $server = strtolower($key);
                    if (! isset($civ13->files[$server.'_playerlogs']) || ! file_exists($civ13->files[$server.'_playerlogs'])) continue;
                    if ($playerlog = file_get_contents($civ13->files[$server.'_playerlogs'])) $playerlogs[] = $playerlog;
                }
                if ($playerlogs) foreach (array_keys($civ13->server_settings) as $key) {
                    $server = strtolower($key);
                    if (! isset($civ13->files[$server.'_bans']) || ! file_exists($civ13->files[$server.'_bans'])) continue;
                    file_put_contents($civ13->files[$server.'_bans'], $banlog_update(file_get_contents($civ13->files[$server.'_bans']), $playerlogs, $arr['ckey']));
                }
            });
        }
    
        return $message->reply($civ13->ban($arr, $civ13->getVerifiedItem($message->author->id)['ss13']));
    }
    if (str_starts_with($message_content_lower, 'unban ')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if (is_numeric($ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('unban')))))
            if (! $item = $civ13->getVerifiedItem($id)) return $message->reply("No data found for Discord ID `$ckey`.");
            else $ckey = $item['ckey'];
        $civ13->unban($ckey, $admin = $civ13->getVerifiedItem($message->author->id)['ss13']);
        return $message->reply("**$admin** unbanned **$ckey**");
    }
    if (str_starts_with($message_content_lower, 'maplist')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if (! file_exists($civ13->files['map_defines_path'])) return $message->react("🔥");
        return $message->reply(MessageBuilder::new()->addFile($civ13->files['map_defines_path'], 'maps.txt'));
    }
    if (str_starts_with($message_content_lower, 'adminlist')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        
        $builder = MessageBuilder::new();
        $found = false;
        foreach (array_keys($civ13->server_settings) as $key) {
            $server = strtolower($key);
            if (! file_exists($civ13->files[$server.'_admins'])) {
                $civ13->logger->debug("`{$server}_admins` is not a valid file path!");
                continue;
            }
            $found = true;
            $file_contents = file_get_contents($civ13->files[$server.'_admins']);
            $builder->addFileFromContent($server.'_admins.txt', $file_contents);
        }
        if (! $found) return $message->react("🔥");
        return $message->reply($builder);
    }
    if (str_starts_with($message_content_lower, 'factionlist')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        
        $builder = MessageBuilder::new()->setContent('Faction Lists');
        foreach (array_keys($civ13->server_settings) as $key) {
            $server = strtolower($key);
            if (file_exists($civ13->files[$server.'_factionlist'])) $builder->addfile($civ13->files[$server.'_factionlist'], $server.'_factionlist.txt');
            else $civ13->logger->warning("`{$server}_factionlist` is not a valid file path!");
        }
        return $message->reply($builder);
    }
    if (str_starts_with($message_content_lower, 'sportsteams')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if (! file_exists($civ13->files['tdm_sportsteams'])) return $message->react("🔥");
        return $message->reply(MessageBuilder::new()->addFile($civ13->files['tdm_sportsteams'], 'sports_teams.txt'));
    }
    if (str_starts_with($message_content_lower, 'logs')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if ($log_handler($civ13, $message, trim(substr($message_content, 4)))) return null;
    }
    if (str_starts_with($message_content_lower, 'playerlogs')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        $tokens = explode(';', trim(substr($message_content, 10)));
        $keys = [];
        foreach (array_keys($civ13->server_settings) as $key) {
            $keys[] = $server = strtolower($key);
            if (trim($tokens[0]) != $key) continue;
            if (! isset($civ13->files[$server.'_playerlogs']) || ! file_exists($civ13->files[$server.'_playerlogs']) || ! $file_contents = file_get_contents($civ13->files[$server.'_playerlogs'])) return $message->react("🔥");
            return $message->reply(MessageBuilder::new()->addFileFromContent('playerlogs.txt', $file_contents));
        }
        return $message->reply('Please use the format `logs {server}`. Valid servers: `' . implode(', ', $keys). '`' );
    }
    if (str_starts_with($message_content_lower, 'bans')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain', 'knight'])) return $message->react("❌");
        if ($civ13->banlogHandler($message, trim(substr($message_content_lower, strlen('bans'))))) return null;
    }

    if (str_starts_with($message_content_lower, 'stop')) {
        if ($rank_check($civ13, $message, ['admiral', 'captain'])) return $message->react("❌");
        return $message->react("🛑")->done(function () use ($civ13) { $civ13->stop(); });
    }

    if (str_starts_with($message_content_lower, 'ts')) {
        if (! $state = trim(substr($message_content_lower, strlen('ts')))) return $message->reply('Wrong format. Please try `ts on` or `ts off`.');
        if (! in_array($state, ['on', 'off'])) return $message->reply('Wrong format. Please try `ts on` or `ts off`.');
        if (! $rank_check($civ13, $message, ['admiral'])) return $message->react("❌");
        
        if ($state == 'on') {
            \execInBackground("cd {$civ13->folders['typespess_path']}");
            \execInBackground('git pull');
            \execInBackground("sh {$civ13->files['typespess_launch_server_path']}&");
            return $message->reply('Put **TypeSpess Civ13** test server on: http://civ13.com/ts');
        } else {
            \execInBackground('killall index.js');
            return $message->reply('**TypeSpess Civ13** test server down.');
        }
    }

    if (str_starts_with($message_content_lower, 'ranking')) {
        if (! $civ13->recalculateRanking()) return $message->reply('There was an error trying to recalculate ranking! The bot may be misconfigured.');
        if (! $msg = $ranking($civ13)) return $message->reply('There was an error trying to recalculate ranking!');
        if (strlen($msg)<=2000) return $message->reply($msg);
        if (strlen($msg)<=4096) {
            $embed = new Embed($civ13->discord);
            $embed->setDescription($msg);
            return $message->channel->sendEmbed($embed);
        }
        return $message->reply(MessageBuilder::new()->addFileFromContent('ranking.txt', $msg));
        // return $message->reply("The ranking is too long to display.");
    }
    if (str_starts_with($message_content_lower, 'rankme')) {
        if (! $ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('rankme')))) return $message->reply('Wrong format. Please try `rankme [ckey]`.');
        if (! $civ13->recalculateRanking()) return $message->reply('There was an error trying to recalculate ranking! The bot may be misconfigured.');
        if (! $msg = $rankme($civ13, $ckey)) return $message->reply('There was an error trying to get your ranking!');
        if (strlen($msg)<=2000) return $message->reply($msg);
        if (strlen($msg)<=4096) {
            $embed = new Embed($civ13->discord);
            $embed->setAuthor($ckey);
            $embed->setDescription($msg);
            return $message->channel->sendEmbed($embed);
        }
        return $message->reply(MessageBuilder::new()->addFileFromContent('rank.txt', $msg));
        // return $message->reply("Your ranking is too long to display.");
    }
    if (str_starts_with($message_content_lower, 'medals')) {
        if (! $ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('medals')))) return $message->reply('Wrong format. Please try `medals [ckey]`.');
        if (! $msg = $medals($civ13, $ckey)) return $message->reply('There was an error trying to get your medals!');
        if (strlen($msg)<=2000) return $message->reply($msg); // Try embed description? 4096 characters
        if (strlen($msg)<=4096) {
            $embed = new Embed($civ13->discord);
            $embed->setAuthor($ckey);
            $embed->setDescription($msg);
            return $message->channel->sendEmbed($embed);
        }
        return $message->reply(MessageBuilder::new()->addFileFromContent('medals.txt', $msg));
        // return $message->reply("Too many medals to display.");
    }
    if (str_starts_with($message_content_lower, 'brmedals')) {
        if (! $ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('brmedals')))) return $message->reply('Wrong format. Please try `brmedals [ckey]`.');
        if (! $msg = $brmedals($civ13, $ckey)) return $message->reply('There was an error trying to get your medals!');
        if (strlen($msg)<=2000) return $message->reply($msg);
        if (strlen($msg)<=4096) {
            $embed = new Embed($civ13->discord);
            $embed->setAuthor($ckey);
            $embed->setDescription($msg);
            return $message->channel->sendEmbed($embed);
        }
        return $message->reply(MessageBuilder::new()->addFileFromContent('brmedals.txt', $msg));
        // return $message->reply("Too many medals to display.");
    }

    if (str_starts_with($message_content_lower, 'update bans')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain'])) return $message->react("❌");
        
        $server_playerlogs = [];
        foreach (array_keys($civ13->server_settings) as $key) {
            $server = strtolower($key);
            if (! $playerlogs = file_get_contents($civ13->files[$server.'_playerlogs'])) {
                $civ13->logger->warning("`{$server}_playerlogs` is not a valid file path!");
                continue;
            }
            $server_playerlogs[] = $playerlogs;
        }
        if (! $server_playerlogs) return $message->react("🔥");
        
        $updated = false;
        foreach (array_keys($civ13->server_settings) as $key) {
            $server = strtolower($key);
            if (! $bans = file_get_contents($civ13->files[$server.'_bans'])) {
                $civ13->logger->warning("`{$server}_bans` is not a valid file path!");
                continue;
            }
            file_put_contents($civ13->files[$server.'_bans'], preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $banlog_update($bans, $server_playerlogs)));
            $updated = true;
        }
        if ($updated) return $message->react("👍");
        return $message->react("🔥");
    }

    if (str_starts_with($message_content_lower, 'panic')) {
        if (! $rank_check($civ13, $message, ['admiral', 'captain'])) return $message->react("❌");
        return $message->reply('Panic bunker is now ' . (($civ13->panic_bunker = ! $civ13->panic_bunker) ? 'enabled.' : 'disabled.'));
    }

    return null;
};

$on_message = function(Civ13 $civ13, $message) use ($guild_message)
{ // on message
    $message_array = $civ13->filterMessage($message);
    if (! $message_array['called']) return null; // Not a command
    if (! $message_content = $message_array['message_content']) { // No command
        $random_responses = ['You can see a full list of commands by using the `help` command.'];
        if (count($random_responses) > 0) return $message->channel->sendMessage(MessageBuilder::new()->setContent("<@{$message->author->id}>, " . $random_responses[rand(0, count($random_responses)-1)]));
    }
    $message_content_lower = $message_array['message_content_lower'];
    
    if (str_starts_with($message_content_lower, 'ping')) return $message->reply('Pong!');
    if (str_starts_with($message_content_lower, 'help')) return $message->reply(
        '**List of Commands**:' . PHP_EOL
        . '**General:** `approveme`, `ranking`, `rankme`, `medals`, `brmedals`' . PHP_EOL
        . '**Staff:** `ckeyinfo`, `permitted`, `permit`, `unpermit` or `revoke`, `parole`, `release`, `refresh`, `maplist`, `adminlist`, `factionlist`, `sportsteams`, `logs`, `playerlogs`, `bans`, `ban`, `unban`, `[SERVER]ban`, `[SERVER]unban`, `[SERVER]host`, `[SERVER]restart`, `[SERVER]kill`, `[SERVER]mapswap`' . PHP_EOL
        . '**High Staff:** `relay`, `fullbancheck`, `fullaltcheck`, `discard`, `tests`, `promotable`, `mass_promotion_loop`, `mass_promotion_check`, `stop`, `update bans`' . PHP_EOL
        . '**Bishop:** `register`' . PHP_EOL
        . '**Admiral:** `ts`'
    );
    if (str_starts_with($message_content_lower, 'cpu')) {
         if (PHP_OS_FAMILY == "Windows") {
            $p = shell_exec('powershell -command "gwmi Win32_PerfFormattedData_PerfOS_Processor | select PercentProcessorTime"');
            $p = preg_replace('/\s+/', ' ', $p); // reduce spaces
            $p = str_replace('PercentProcessorTime', '', $p);
            $p = str_replace('--------------------', '', $p);
            $p = preg_replace('/\s+/', ' ', $p); // reduce spaces
            $load_array = explode(' ', $p);

            $x=0;
            $load = '';
            foreach ($load_array as $line) if (trim($line) && $x == 0) { $load = "CPU Usage: $line%" . PHP_EOL; break; }
            return $message->reply($load);
        } else { // Linux
            $cpu_load = ($cpu_load_array = sys_getloadavg()) ? $cpu_load = array_sum($cpu_load_array) / count($cpu_load_array) : '-1';
            return $message->reply("CPU Usage: $cpu_load%");
        }
        return $message->reply('Unrecognized operating system!');
    }
    if (str_starts_with($message_content_lower, 'insult')) {
        $split_message = explode(' ', $message_content); // $split_target[1] is the target
        if ((count($split_message) <= 1 ) || ! strlen($split_message[1] === 0)) return null;
        if (! file_exists($civ13->files['insults_path']) || ! ($file = @fopen($civ13->files['insults_path'], 'r'))) return $message->react("🔥");
        $insults_array = array();
        while (($fp = fgets($file, 4096)) !== false) $insults_array[] = $fp;
        if (count($insults_array) > 0) return $message->channel->sendMessage(MessageBuilder::new()->setContent($split_message[1] . ', ' . $insults_array[rand(0, count($insults_array)-1)])->setAllowedMentions(['parse'=>[]]));
        return $message->reply('No insults found!');
    }
    if (str_starts_with($message_content_lower, 'ooc ')) {
        $message_content = substr($message_content, 4);
        foreach (array_keys($civ13->server_settings) as $key) {
            $server = strtolower($key);
            if (isset($civ13->server_funcs_uncalled[$server.'_discord2ooc'])) switch (strtolower($message->channel->name)) {
                case "ooc-{$server}":                    
                    if (! $civ13->server_funcs_uncalled[$server.'_discord2ooc']($message->author->displayname, $message_content)) return $message->react("🔥");
                    return $message->react("📧");
            }
        }
        return $message->reply('You need to be in any of the #ooc channels to use this command.');
    }
    if (str_starts_with($message_content_lower, 'asay ')) {
        $message_content = substr($message_content, 5);
        foreach (array_keys($civ13->server_settings) as $key) {
            $server = strtolower($key);
            if (isset($civ13->server_funcs_uncalled[$server.'_discord2admin'])) switch (strtolower($message->channel->name)) {
                case "asay-{$server}":                    
                    if (! $civ13->server_funcs_uncalled[$server.'_discord2admin']($message->author->displayname, $message_content)) return $message->react("🔥");
                    return $message->react("📧");
            }
        }
        return $message->reply('You need to be in any of the #asay channels to use this command.');
    }
    if (str_starts_with($message_content_lower, 'dm ') || str_starts_with($message_content_lower, 'pm ')) {
        $message_content = substr($message_content, 3);
        $explode = explode(';', $message_content);
        $recipient = array_shift($explode);
        $msg = implode(' ', $explode);
        foreach (array_keys($civ13->server_settings) as $key) {
            $server = strtolower($key);
            switch (strtolower($message->channel->name)) {
                // case 'ahelp-{$server}}': // Deprecated
                case "asay-{$server}":
                case "ooc-{$server}":
                    if (! $civ13->DirectMessage($recipient, $msg, $civ13->getVerifiedItem($message->author->id)['ss13'], $server)) return $message->react("🔥");
                    return $message->react("📧");
            }
        }
        return $message->reply('You need to be in any of the #ooc or #asay channels to use this command.');
    }

    if (str_starts_with($message_content_lower, 'bancheck')) {
        if (! $ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('bancheck')))) return $message->reply('Wrong format. Please try `bancheck [ckey]`.');
        if (is_numeric($ckey))
            if (! $item = $civ13->verified->get('discord', $ckey))
                return $message->reply("No ckey found for Discord ID `$ckey`.");
        $ckey = $item['ss13'];
        $reason = 'unknown';
        $found = false;
        $response = '';
        foreach (array_keys($civ13->server_settings) as $key) {
            $file_path = strtolower($key) . '_bans';
            if (isset($civ13->files[$file_path]) && file_exists($civ13->files[$file_path]) && ($file = @fopen($civ13->files[$file_path], 'r'))) {
                while (($fp = fgets($file, 4096)) !== false) {
                    $linesplit = explode(';', trim(str_replace('|||', '', $fp))); // $split_ckey[0] is the ckey
                    if ((count($linesplit)>=8) && ($linesplit[8] == strtolower($item['ss13']))) {
                        $found = true;
                        $type = $linesplit[0];
                        $reason = $linesplit[3];
                        $admin = $linesplit[4];
                        $date = $linesplit[5];
                        $response .= "**{$item['ss13']}** has been **$type** banned from **$key** on **$date** for **$reason** by $admin." . PHP_EOL;
                    }
                }
                fclose($file);
            }
        }
        if (! $found) $response .= "No bans were found for **{$item['ss13']}**." . PHP_EOL;
        if ($member = $civ13->getVerifiedMember($ckey))
            if (! $member->roles->has($civ13->role_ids['banished']))
                $member->addRole($civ13->role_ids['banished']);
        $embed = new Embed($civ13->discord);
        $embed->setDescription($response);
        return $message->reply(MessageBuilder::new()->addEmbed($embed));
    }
    if (str_starts_with($message_content_lower, 'serverstatus')) { // See GitHub Issue #1
        return null; // deprecated
        /*
        $embed = new Embed($civ13->discord);
        $_1714 = !\portIsAvailable(1714);
        $server_is_up = $_1714;
        if (! $server_is_up) {
            $embed->setColor(0x00ff00);
            $embed->addFieldValues('TDM Server Status', 'Offline');
        } else {
            if ($_1714) {
                if (! $data = file_get_contents($civ13->files['tdm_serverdata'])) {
                    $embed->setColor(0x00ff00);
                    $embed->addFieldValues('TDM Server Status', 'Starting');
                } else {
                    $data = explode(';', str_replace(['<b>Address</b>: ', '<b>Map</b>: ', '<b>Gamemode</b>: ', '<b>Players</b>: ', '</b>', '<b>'], '', $data));
                    $embed->setColor(0x00ff00);
                    $embed->addFieldValues('TDM Server Status', 'Online');
                    if (isset($data[1])) $embed->addFieldValues('Address', '<'.$data[1].'>');
                    if (isset($data[2])) $embed->addFieldValues('Map', $data[2]);
                    if (isset($data[3])) $embed->addFieldValues('Gamemode', $data[3]);
                    if (isset($data[4])) $embed->addFieldValues('Players', $data[4]);
                }
            } else {
                $embed->setColor(0x00ff00);
                $embed->addFieldValues('TDM Server Status', 'Offline');
            }
        }
        $_1715 = !\portIsAvailable(1715);
        $server_is_up = ($_1715);
        if (! $server_is_up) {
            $embed->setColor(0x00ff00);
            $embed->addFieldValues('Nomads Server Status', 'Offline');
        } else {
            if ($_1714) {
                if (! $data = file_get_contents($civ13->files['nomads_serverdata'])) {
                    $embed->setColor(0x00ff00);
                    $embed->addFieldValues('Nomads Server Status', 'Starting');
                } else {
                    $data = explode(';', str_replace(['<b>Address</b>: ', '<b>Map</b>: ', '<b>Gamemode</b>: ', '<b>Players</b>: ', '</b>', '<b>'], '', $data));
                    $embed->setColor(0x00ff00);
                    $embed->addFieldValues('Nomads Server Status', 'Online');
                    if (isset($data[1])) $embed->addFieldValues('Address', '<'.$data[1].'>');
                    if (isset($data[2])) $embed->addFieldValues('Map', $data[2]);
                    if (isset($data[3])) $embed->addFieldValues('Gamemode', $data[3]);
                    if (isset($data[4])) $embed->addFieldValues('Players', $data[4]);
                }
            } else {
                $embed->setColor(0x00ff00);
                $embed->addFieldValues('Nomads Server Status', 'Offline');
            }
        }
        return $message->channel->sendEmbed($embed);
        */
    }
    if (str_starts_with($message_content_lower, 'discord2ckey')) {
        if (! $item = $civ13->verified->get('discord', $id = $civ13->sanitizeInput(substr($message_content_lower, strlen('discord2ckey'))))) return $message->reply("`$id` is not registered to any byond username");
        return $message->reply("`$id` is registered to `{$item['ss13']}`");
    }
    if (str_starts_with($message_content_lower, 'ckey2discord')) {
        if (! $item = $civ13->verified->get('ss13', $ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('ckey2discord'))))) return $message->reply("`$ckey` is not registered to any discord id");
        return $message->reply("`$ckey` is registered to <@{$item['discord']}>");
    }
    if (! str_starts_with($message_content_lower, 'ckeyinfo') && str_starts_with($message_content_lower, 'ckey')) {
        if (! $ckey = $civ13->sanitizeInput(substr($message_content_lower, strlen('ckey')))) {
            if (! $item = $civ13->getVerifiedItem($id = $message->author->id)) return $message->reply("You are not registered to any byond username");
            return $message->reply("You are registered to `{$item['ss13']}`");
        }
        if (is_numeric($ckey)) {
            if (! $item = $civ13->getVerifiedItem($ckey)) return $message->reply("`$ckey` is not registered to any ckey");
            if (! $age = $civ13->getByondAge($item['ss13'])) return $message->reply("`{$item['ss13']}` does not exist");
            return $message->reply("`{$item['ss13']}` is registered to <@{$item['discord']}> ($age)");
        }
        if (! $age = $civ13->getByondAge($ckey)) return $message->reply("`$ckey` does not exist");
        if ($item = $civ13->getVerifiedItem($ckey)) return $message->reply("`{$item['ss13']}` is registered to <@{$item['discord']}> ($age)");
        return $message->reply("`$ckey` is not registered to any discord id ($age)");
    }
    
    if ($message->member && $guild_message($civ13, $message, $message_content, $message_content_lower)) return null;
};

$slash_init = function(Civ13 $civ13, $commands) use ($ranking, $rankme, $medals, $brmedals): void
{ // ready_slash, requires other functions to work
    $civ13->discord->listenCommand('pull', function ($interaction) use ($civ13): void
    {
        $civ13->logger->info('[GIT PULL]');
        \execInBackground('git pull');
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Updating code from GitHub...'));
    });
    
    $civ13->discord->listenCommand('update', function ($interaction) use ($civ13): void
    {
        $civ13->logger->info('[COMPOSER UPDATE]');
        \execInBackground('composer update');
        $interaction->respondWithMessage(MessageBuilder::new()->setContent('Updating dependencies...'));
    });
    
    $civ13->discord->listenCommand('nomads_restart', function ($interaction) use ($civ13): void
    {
    $interaction->respondWithMessage(MessageBuilder::new()->setContent("Attempted to kill, update, and bring up Nomads <byond://{$civ13->ips['tdm']}:{$civ13->ports['tdm']}>"));
        if (isset($civ13->server_funcs_called['nomadsrestart'])) $civ13->server_funcs_called['nomadsrestart']();
    });
    $civ13->discord->listenCommand('tdm_restart', function ($interaction) use ($civ13): void
    {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent("Attempted to kill, update, and bring up TDM <byond://{$civ13->ips['tdm']}:{$civ13->ports['tdm']}>"));
        if (isset($civ13->server_funcs_called['tdmrestart'])) $civ13->server_funcs_called['tdmrestart']();
    });
    
    $civ13->discord->listenCommand('ranking', function ($interaction) use ($civ13, $ranking): void
    {
        $interaction->respondWithMessage(MessageBuilder::new()->setContent($ranking($civ13)), true);
    });
    $civ13->discord->listenCommand('rankme', function ($interaction) use ($civ13, $rankme): void
    {
        if (! $item = $civ13->verified->get('discord', $interaction->member->id)) $interaction->respondWithMessage(MessageBuilder::new()->setContent("<@{$interaction->data->target_id}> is not currently verified with a byond username or it does not exist in the cache yet"), true);
        else $interaction->respondWithMessage(MessageBuilder::new()->setContent($rankme($civ13, $item['ss13'])), true);
    });
    /* Deprecated
    $civ13->discord->listenCommand('rank', function ($interaction) use ($civ13, $rankme): void
    {
        if (! $item = $civ13->verified->get('discord', $interaction->data->target_id)) $interaction->respondWithMessage(MessageBuilder::new()->setContent("<@{$interaction->data->target_id}> is not currently verified with a byond username or it does not exist in the cache yet"), true);
        else $interaction->respondWithMessage(MessageBuilder::new()->setContent($rankme($civ13, $item['ss13'])), true);
    });*/
    /* Deprecated
    $civ13->discord->listenCommand('medals', function ($interaction) use ($civ13, $medals): void
    {
        if (! $item = $civ13->verified->get('discord', $interaction->data->target_id)) $interaction->respondWithMessage(MessageBuilder::new()->setContent("<@{$interaction->data->target_id}> is not currently verified with a byond username or it does not exist in the cache yet"), true);
        else $interaction->respondWithMessage(MessageBuilder::new()->setContent($medals($civ13, $item['ss13'])), true);
    });*/
    /* Deprecated
    $civ13->discord->listenCommand('brmedals', function ($interaction) use ($civ13, $brmedals): void
    {
        if (! $item = $civ13->verified->get('discord', $interaction->data->target_id)) $interaction->respondWithMessage(MessageBuilder::new()->setContent("<@{$interaction->data->target_id}> is not currently verified with a byond username or it does not exist in the cache yet"), true);
        else $interaction->respondWithMessage(MessageBuilder::new()->setContent($brmedals($civ13, $item['ss13'])), true);
    });*/

    /*For deferred interactions
    $civ13->discord->listenCommand('',  function (Interaction $interaction) use ($civ13) {
      // code is expected to be slow, defer the interaction
      $interaction->acknowledge()->done(function () use ($interaction, $civ13) { // wait until the bot says "Is thinking..."
        // do heavy code here (up to 15 minutes)
        // ...
        // send follow up (instead of respond)
        $interaction->sendFollowUpMessage(MessageBuilder...);
      });
    }
    */
};
/*$on_ready = function(Civ13 $civ13): void
{    
    // 
};*/