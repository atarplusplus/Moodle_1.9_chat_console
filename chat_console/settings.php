<?php  //$Id: settings.php,v 1.1.2.2 2007/12/19 17:38:49 skodak Exp $

$settings->add(new admin_setting_configtext('block_chat_console_timetosee',
        get_string('timetosee', 'block_chat_console'),
        get_string('configtimetosee', 'block_chat_console'), 5, PARAM_INT));

$settings->add(new admin_setting_configtext('block_chat_console_highlightcolor',
        get_string('highlightcolor', 'block_chat_console'),
        get_string('confighighlightcolor', 'block_chat_console'), "yellow", PARAM_TEXT));

$history = array(
    0 => get_string('historysend_never', 'block_chat_console'),
    1 => get_string('historysend_everyday', 'block_chat_console'),
    2 => get_string('historysend_everydaybegin', 'block_chat_console'));

$settings->add(new admin_setting_configselect('block_chat_console_historysend',
        get_string('historysend', 'block_chat_console'),
        get_string('confighistorysend', 'block_chat_console'), 2, $history));

?>
