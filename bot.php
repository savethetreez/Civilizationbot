<?php

/*
 * This file is a part of the Civ13 project.
 *
 * Copyright (c) 2022-present Valithor Obsidion <valithor@valzargaming.com>
 */

use \Civ13\Civ13;
use \Discord\Discord;
use \Discord\Helpers\CacheConfig;
use \React\EventLoop\Loop;
use \WyriHaximus\React\Cache\Redis as RedisCache;
use \Clue\React\Redis\Factory as Redis;
use \React\Filesystem\Factory as FilesystemFactory;
use \Monolog\Logger;
use \Monolog\Level;
use \Monolog\Formatter\LineFormatter;
use \Monolog\Handler\StreamHandler;
use \Discord\WebSockets\Intents;
use \React\Http\Browser;

ini_set('display_errors', 1);
error_reporting(E_ALL);

set_time_limit(0);
ignore_user_abort(1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1'); // Unlimited memory usage
define('MAIN_INCLUDED', 1); // Token and SQL credential files may be protected locally and require this to be defined to access
require getcwd() . '/token.php'; // $token
include getcwd() . '/vendor/autoload.php';

$loop = Loop::get();
$streamHandler = new StreamHandler('php://stdout', Level::Info);
$streamHandler->setFormatter(new LineFormatter(null, null, true, true));
$logger = new Logger('Civ13', [$streamHandler]);
$discord = new Discord([
    'loop' => $loop,
    'logger' => $logger,
    /* // Disabled for debugging
    'cache' => new CacheConfig(
        $interface = new RedisCache(
            (new Redis($loop))->createLazyClient('127.0.0.1:6379'),
            'dphp:cache:
        '),
        $compress = true, // Enable compression if desired
        $sweep = false // Disable automatic cache sweeping if desired
    ), 
    */
    /*'socket_options' => [
        'dns' => '8.8.8.8', // can change dns
    ],*/
    'token' => $token,
    'loadAllMembers' => true,
    'storeMessages' => true, // Because why not?
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::MESSAGE_CONTENT,
]);
include 'stats_object.php'; 
$stats = new Stats();
$stats->init($discord);
$browser = new Browser($loop);
$filesystem = FilesystemFactory::create($loop);
include 'functions.php'; // execIn ckground(), portIsAvailable()
include 'variable_functions.php';
include 'verifier_functions.php';
include 'civ13.php';
include 'Handler.php';
include 'MessageHandler.php';

$external_ip = file_get_contents('http://ipecho.net/plain');
$civ13_ip = gethostbyname('www.civ13.com');
$vzg_ip = gethostbyname('www.valzargaming.com');

$webapi = null;
$socket = null;
$options = array(
    'loop' => $loop,
    'discord' => $discord,
    'browser' => $browser,
    'filesystem' => $filesystem,
    'logger' => $logger,
    'stats' => $stats,

    'webapi' => &$webapi,
    'socket' => &$socket,
    
    // Configurations


    // The Verify URL is where verification requests are sent to and where the verification list is retrieved from
    // The website must return valid json when no parameters are passed to it and MUST allow POST requests including 'token', 'ckey', and 'discord'
    // Reach out to Valithor if you need help setting up your website
    'webserver_url' => 'www.valzargaming.com',
    'verify_url' => 'http://valzargaming.com:8080/verified/', // Leave this blank if you do not want to use the webserver, ckeys will be stored locally as provisional
    // 'serverinfo_url' => '', // URL of the serverinfo.json file

    'banappeal' => 'civ13.com slash discord',
    'rules' => 'civ13.com slash rules',
    'github' => 'https://github.com/VZGCoders/Civilizationbot',
    'command_symbol' => '@Civilizationbot',
    'owner_id' => '196253985072611328', // Taislin
    'technician_id' => '116927250145869826', // Valithor
    'civ13_guild_id' => '468979034571931648', // Civ13
    'verifier_feed_channel_id' => '1032411190695055440', // Channel VZG Verifier webhooks verification messages to
    'server_settings' => [ // Server specific settings, listed in the order in which they appear on the VZG server list.
        'TDM' => [
            'supported' => true,
            'enabled' => true,
            'name' => 'TDM',
            'ip' => $civ13_ip,
            'port' => '1714',
            'Host' => 'Taislin',
            'panic' => false,
            'legacy' => true,
            'moderate' => true,
            'relay_method' => 'webhook',
        ],
        'Nomads' => [
            'supported' => true, // Whether the server is supported by the remote webserver
            'enabled' => true, // Whether the server should have commands handled by the bot
            'name' => 'Nomads', // Name of the server and the prefix of the playercount channel (e.g. nomads-999)
            'ip' => $civ13_ip, // IP of the server
            'port' => '1715', // Port of the server
            'Host' => 'Taislin', // Host of the server
            'panic' => true, // Panic mode will ban all users who are not verified
            'legacy' => true, // Legacy mode will use the file system instead of an SQL database
            'moderate' => true, // Whether chat moderation is enabled
            'relay_method' => 'webhook', // How messages are relayed to the server
        ],
        'Pers' => [
            'supported' => true,
            'enabled' => false,
            'name' => 'Persistence',
            'ip' => $vzg_ip,
            'port' => '1717',
            'Host' => 'ValZarGaming',
            'panic' => true,
            'legacy' => true,
            'moderate' => true,
            'relay_method' => 'webhook',
        ],
    ],
    'legacy' => true,
    'relay_method' => 'webhook',
    'moderate' => true,
    'badwords' => [
        /* Format:
            'word' => 'bad word' // Bad word to look for
            'duration' => duration ['1 minute', '1 hour', '1 day', '1 week', '1 month', '999 years'] // Duration of the ban
            'reason' => 'reason' // Reason for the ban
            'category' => rule category ['racism/discrimination', 'toxic', 'advertisement'] // Used to group bad words together by category
            'method' => detection method ['exact', 'contains'] // Exact ignores partial matches, contains matches partial matchesq
            'warnings' => 1 // Number of warnings before a ban
        */
        ['word' => 'badwordtestmessage', 'duration' => '1 minute', 'reason' => 'Violated server rule.', 'category' => 'test', 'method' => 'contains', 'warnings' => 1], // Used to test the system
        
        ['word' => 'beaner', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'chink', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'coon', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'exact', 'warnings' => 1],
        ['word' => 'fag', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'gook', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'kike', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'nigg', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'nlgg', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'niqq', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        ['word' => 'tranny', 'duration' => '999 years', 'reason' => 'Racism and Discrimination.', 'category' => 'racism/discrimination', 'method' => 'contains', 'warnings' => 1],
        
        ['word' => 'cunt', 'duration' => '1 minute', 'reason' => 'You must not be toxic or too agitated in any OOC communication channels.', 'category' => 'toxic', 'method' => 'exact', 'warnings' => 5],
        ['word' => 'fuck you', 'duration' => '1 minute', 'reason' => 'You must not be toxic or too agitated in any OOC communication channels.', 'category' => 'toxic', 'method' => 'exact', 'warnings' => 5],
        ['word' => 'retard', 'duration' => '1 minute', 'reason' => 'You must not be toxic or too agitated in any OOC communication channels.', 'category' => 'toxic', 'method' => 'exact', 'warnings' => 5],
        ['word' => 'kys', 'duration' => '1 minute', 'reason' => 'You must not be toxic or too agitated in any OOC communication channels.', 'category' => 'toxic', 'method' => 'exact', 'warnings' => 1], // This is more severe than the others, so ban after only one warning
        
        ['word' => 'discord.gg', 'duration' => '999 years', 'reason' => 'You must not post unauthorized Discord invitation links in any OOC communication channels.', 'category' => 'advertisement', 'method' => 'contains', 'warnings' => 2],
        ['word' => 'discord.com', 'duration' => '999 years', 'reason' => 'You must not post unauthorized Discord invitation links in any OOC communication channels.', 'category' => 'advertisement', 'method' => 'contains', 'warnings' => 2],
    ],
    'folders' => array(
        // 'typespess_path' => '/home/civ13/civ13-typespess',
    ),
    'files' => array( // Server-specific file paths MUST start with the server name as defined in server_settings unless otherwise specified
        // Fun
        'insults_path' => 'insults.txt',
        'ranking_path' => 'ranking.txt',
        'status_path' => 'status.txt',
        
        // Defines
        'map_defines_path' => '/home/civ13/civ13-git/code/__defines/maps.dm',
        
        // Nomads
        'nomads_log_basedir' => '/home/civ13/civ13-rp/data/logs',
        'nomads_ooc_path' => '/home/civ13/civ13-rp/ooc.log',
        'nomads_admin_path' => '/home/civ13/civ13-rp/admin.log',
        'nomads_discord2ooc' => '/home/civ13/civ13-rp/SQL/discord2ooc.txt',
        'nomads_discord2admin' => '/home/civ13/civ13-rp/SQL/discord2admin.txt',
        'nomads_discord2dm' => '/home/civ13/civ13-rp/SQL/discord2dm.txt',
        'nomads_discord2ban' => '/home/civ13/civ13-rp/SQL/discord2ban.txt',
        'nomads_discord2unban' => '/home/civ13/civ13-rp/SQL/discord2unban.txt',
        'nomads_admins' => '/home/civ13/civ13-rp/SQL/admins.txt',
        'nomads_whitelist' => '/home/civ13/civ13-rp/SQL/whitelist.txt',
        'nomads_bans' => '/home/civ13/civ13-rp/SQL/bans.txt',
        'nomads_playerlogs' => '/home/civ13/civ13-rp/SQL/playerlogs.txt',
        // Campaign
        'nomads_factionlist' => '/home/civ13/civ13-rp/SQL/factionlist.txt',
        
        // TDM
        'tdm_log_basedir' => '/home/civ13/civ13-tdm/data/logs',
        'tdm_ooc_path' => '/home/civ13/civ13-tdm/ooc.log',
        'tdm_admin_path' => '/home/civ13/civ13-tdm/admin.log',
        'tdm_discord2ooc' => '/home/civ13/civ13-tdm/SQL/discord2ooc.txt',
        'tdm_discord2admin' => '/home/civ13/civ13-tdm/SQL/discord2admin.txt',
        'tdm_discord2dm' => '/home/civ13/civ13-tdm/SQL/discord2dm.txt',
        'tdm_discord2ban' => '/home/civ13/civ13-tdm/SQL/discord2ban.txt',
        'tdm_discord2unban' => '/home/civ13/civ13-tdm/SQL/discord2unban.txt',
        'tdm_admins' => '/home/civ13/civ13-tdm/SQL/admins.txt',
        'tdm_whitelist' => '/home/civ13/civ13-tdm/SQL/whitelist.txt',
        'tdm_bans' => '/home/civ13/civ13-tdm/SQL/bans.txt',
        'tdm_playerlogs' => '/home/civ13/civ13-tdm/SQL/playerlogs.txt',
        // Campaign
        'tdm_factionlist' => '/home/civ13/civ13-tdm/SQL/factionlist.txt',
        // Football
        'tdm_sportsteams' => '/home/civ13/civ13-tdm/SQL/sports_teams.txt',
        // Medals
        'tdm_awards_path' => '/home/civ13/civ13-tdm/SQL/awards.txt',
        'tdm_awards_br_path' => '/home/civ13/civ13-tdm/SQL/awards_br.txt',

        // Persistence
        'pers_log_basedir' => '/home/civ13/civ13-pers/data/logs',
        'pers_ooc_path' => '/home/civ13/civ13-pers/ooc.log',
        'pers_admin_path' => '/home/civ13/civ13-pers/admin.log',
        'pers_discord2ooc' => '/home/civ13/civ13-pers/SQL/discord2ooc.txt',
        'pers_discord2admin' => '/home/civ13/civ13-pers/SQL/discord2admin.txt',
        'pers_discord2dm' => '/home/civ13/civ13-pers/SQL/discord2dm.txt',
        'pers_discord2ban' => '/home/civ13/civ13-pers/SQL/discord2ban.txt',
        'pers_discord2unban' => '/home/civ13/civ13-pers/SQL/discord2unban.txt',
        'pers_admins' => '/home/civ13/civ13-pers/SQL/admins.txt',
        'pers_whitelist' => '/home/civ13/civ13-pers/SQL/whitelist.txt',
        'pers_bans' => '/home/civ13/civ13-pers/SQL/bans.txt',
        'pers_playerlogs' => '/home/civ13/civ13-pers/SQL/playerlogs.txt',
        'pers_awards_path' => '/home/civ13/civ13-pers/SQL/awards.txt',
        'pers_awards_br_path' => '/home/civ13/civ13-pers/SQL/awards_br.txt',
        // Campaign
        'pers_factionlist' => '/home/civ13/civ13-pers/SQL/factionlist.txt',
        // Football
        'tdm_sportsteams' => '/home/civ13/civ13-pers/SQL/sports_teams.txt',
        // Medals
        'tdm_awards_path' => '/home/civ13/civ13-pers/SQL/awards.txt',
        'tdm_awards_br_path' => '/home/civ13/civ13-pers/SQL/awards_br.txt',

        // Script paths
        'nomads_updateserverabspaths' => '/home/civ13/civ13-rp/scripts/updateserverabspaths.py',
        'nomads_serverdata' => '/home/civ13/civ13-rp/serverdata.txt',
        'nomads_dmb' => '/home/civ13/civ13-rp/civ13.dmb',
        'nomads_killsudos' => '/home/civ13/civ13-rp/scripts/killsudos.py',
        'nomads_killciv13' => '/home/civ13/civ13-rp/scripts/killciv13.py',
        'nomads_mapswap' => '/home/civ13/civ13-rp/scripts/mapswap.py',

        'tdm_updateserverabspaths' => '/home/civ13/civ13-tdm/scripts/updateserverabspaths.py',
        'tdm_serverdata' => '/home/civ13/civ13-tdm/serverdata.txt',
        'tdm_dmb' => '/home/civ13/civ13-tdm/civ13.dmb',
        'tdm_killsudos' => '/home/civ13/civ13-tdm/scripts/killsudos.py',
        'tdm_killciv13' => '/home/civ13/civ13-tdm/scripts/killciv13.py',
        'tdm_mapswap' => '/home/civ13/civ13-tdm/scripts/mapswap.py',

        'pers_updateserverabspaths' => '/home/civ13/civ13-pers/scripts/updateserverabspaths.py',
        'pers_serverdata' => '/home/civ13/civ13-pers/serverdata.txt',
        'pers_dmb' => '/home/civ13/civ13-pers/civ13.dmb',
        'pers_killsudos' => '/home/civ13/civ13-pers/scripts/killsudos.py',
        'pers_killciv13' => '/home/civ13/civ13-pers/scripts/killciv13.py',
        'pers_mapswap' => '/home/civ13/civ13-pers/scripts/mapswap.py',

        // 'typespess_launch_server_path' => '/home/civ13/civ13-typespess/scripts/launch_server.sh',
        
    ),
    'channel_ids' => array(
        /* Nomad */
        'nomads' => '799952084505067581', #nomads
        'nomads-playercount' => '1048777424894185484', // nomads-#

        'nomads_ooc_channel' => '1110001963405418616', // #ooc-nomads
        'nomads_lobby_channel' => '1110001986134347856', // #lobby-nomads
        'nomads_asay_channel' => '1110002005977604186', // #asay-nomads
        'nomads_ic_channel' => '1121531739114852432', // #ic-nomads

        'nomads_transit_channel' => '1110002027469221989', // #transit-nomads
        'nomads_adminlog_channel' => '1110002047123738624', // #adminlog-nomads
        'nomads_debug_channel' => '1106248132779593758', // #debug-nomads (debugging)
        'nomads_garbage_channel' => '1110002493259251752', // #garbage-nomads
        'nomads_runtime_channel' => '1110002671936602132', // #runtime-nomads
        'nomads_attack_channel' => '1110002697383448648', // #attack-nomads

        /* TDM */
        'tdm' => '799952134426591273', #tdm
        'tdm-playercount' => '1048777462898761789', // tdm-#

        'tdm_ooc_channel' => '1107016184328622233', // #ooc-tdm
        'tdm_lobby_channel' => '1107021760483831988', // #lobby-tdm
        'tdm_asay_channel' => '1107016769169801216', // #asay-tdm
        'tdm_ic_channel' => '1121531682198138920', // #ic-tdm

        'tdm_transit_channel' => '1107020747622326313', // #transit-tdm
        'tdm_adminlog_channel' => '1107024305927225455', // #adminlog-tdm
        'tdm_debug_channel' => '1106248157798600715', // #debug-tdm (debugging)
        'tdm_garbage_channel' => '1107018726307528735', // #garbage-tdm
        'tdm_runtime_channel' => '1107017103883632792', // #runtime-tdm
        'tdm_attack_channel' => '1107017830160936980', // #attack-tdm

        /* Persistence */ 
        'pers' => '799951945346711552', #pers
        'pers-playercount' => '1090788345082298369', // pers-#

        'pers_ooc_channel' => '1139614228408455388', // #ooc-pers
        'pers_lobby_channel' => '1139614248222343282', // #lobby-pers
        'pers_asay_channel' => '1139614266299785278', // #asay-pers
        'pers_ic_channel' => '1139614281512529941', // #ic-pers
        
        'pers_transit_channel' => '1139614542700216420', // #transit-pers
        'pers_adminlog_channel' => '1139614564577722448', // #adminlog-pers
        'pers_debug_channel' => '1139614582931984575', // #debug-pers (debugging)
        'pers_garbage_channel' => '1139614596789964820', // #garbage-pers
        'pers_runtime_channel' => '1139614629081915492', // #runtime-pers
        'pers_attack_channel' => '1139614643954921593', // #attack-pers

        // Misc
        'webserver-status' => '1106967195092783104', #webserver-status
        'staff_bot' => '712685552155230278', // #staff-bot
        // 'ban_appeals' => '1019718839749062687', // #ban-appeals (forum thread, unused by bot)
        'parole_logs' => '985606778916048966', // #parole-logs (for tracking)
        'parole_notif' => '977715818731294790', // #parole-notif (for login/logout notifications)
    ),
    'role_ids' => array(
        // Discord ranks
        'Owner' => '468980650914086913', // Civ13 Discord Server Owner
        'Chief Technical Officer' => '791450326455681034', // Civ13 Debug Host / Database admin
        'Host' => '677873806513274880', // Civ13 Server Host
        'Head Admin' => '487608503553490965',
        'Manager' => '496004389950193667',
        'High Staff' => '792826030796308503',
        'Supervisor' => '561770271300911105',
        'Event Admin' => '774435124611514368',
        'Admin' => '468982360659066912',
        'Moderator' => '823302316743589938',
        'Mentor' => '469297467918254085',
        'veteran' => '468983261708681216', // Promoted
        'infantry' => '468982790772228127', // Verified
        'banished' => '710328377210306641', // Banned in-game
        'paroled' => '745336314689355796', // On parole
        'parolemin' => '743971427929030748', // Parole Admin
        
        // Factions
        'red' => '1132678312301428886', // Redmenia
        'blue' => '1132678353070067802', // Blugoslavia
        'organizer' => '1089060051425165362', // Admin / Faction Organizer

        // Notification pings
        'round_start' => '1110597830403424328', // Round Start Ping
        '2+' => '981963719804346418', // LowPopStart
        '15+' => '981963721817620511', // 15+ Popping
        '30+' => '981963696895062106', // 30+ Popping

        // Server channels
        'tdm' => '753768519203684445',
        'nomads' => '753768513671397427',
        'pers' => '753768492834095235',
    ),
    'functions' => array(
        'ready' => [
            // 'on_ready' => $on_ready,
            'status_changer_timer' => $status_changer_timer,
            'status_changer_random' => $status_changer_random,
            'civ_listeners' => $civ_listeners, // TODO: Move into civ13.php
        ],
        'ready_slash' => [
            'slash_init' => $slash_init,
        ],
        'message' => [
            'on_message' => $on_message,
        ],
        'GUILD_MEMBER_ADD' => [
            // 
        ],
        'misc' => [ // Custom functions
            'promotable_check' => $promotable_check,
            'mass_promotion_loop' => $mass_promotion_loop,
            'mass_promotion_check' => $mass_promotion_check,
            
        ],
    ),
);
if (include 'civ_token.php') $options['civ_token'] = $civ_token;
$civ13 = new Civ13($options);
$global_error_handler = function (int $errno, string $errstr, ?string $errfile, ?int $errline) use ($civ13) {
    if (
        ($channel = $civ13->discord->getChannel($civ13->channel_ids['staff_bot']))
        && ! str_ends_with($errstr, 'Connection timed out')
        && ! str_ends_with($errstr, 'No route to host')
    )
    {
        $msg = "[$errno] Fatal error on `$errfile:$errline`: $errstr ";
        if (isset($civ13->technician_id) && $tech_id = $civ13->technician_id) $msg = "<@{$tech_id}>, $msg";
        $channel->sendMessage($msg);
    }
};
set_error_handler($global_error_handler);
include 'webapi.php'; // $socket, $webapi, webapiFail(), webapiSnow();
$civ13->run();