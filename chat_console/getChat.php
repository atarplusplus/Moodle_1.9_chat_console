<?php
/*
	This is the PHP backend file for the JSON AJAX Driven Chat plugin
        for Moodle 1.9.

        Based on original code by:

            This is the PHP backend file for the JSON AJAX Driven Chat application.

            You may use this code in your own projects as long as this copyright
            is left in place.  All code is provided AS-IS.
            This code is distributed in the hope that it will be useful,
            but WITHOUT ANY WARRANTY; without even the implied warranty of
            MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

            For the rest of the code visit http://www.DynamicAJAX.com

            Copyright 2005 Ryan Smith / 345 Technical / 345 Group.
*/


require('../../config.php');
require('lib.php');

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
header("Content-Type: text/plain; charset=utf-8");

$now = time();
$a_day = 24 * 60 * 60;

$userfrom = get_record('user','id',$_GET['sender']);
$userto = get_record('user','id',$_GET['receiver']);

function CheckRefresh($timestamp)
{
    global $now, $a_day, $CFG;
    switch($CFG->block_chat_console_historysend)
    {
    case 1:
        if($now - $timestamp > $a_day)
            return false;
        break;
    case 2:
        if($now - ($now % $a_day) > $timestamp)
            return false;
        break;
    default:
    }
    return true;
}

function RefreshHistory()
{
    global $now, $a_day, $CFG;

    $select = '';
    $select.= "((useridto = '{$_GET['sender']}'";
    $select.= " AND useridfrom = '{$_GET['receiver']}') OR ";
    $select.= "(useridto = '{$_GET['receiver']}'";
    $select.= " AND useridfrom = '{$_GET['sender']}'))";

    switch($CFG->block_chat_console_historysend)
    {
    case 1:
        $select.= " AND timecreated < ".($now - $timestamp);
        break;
    case 2:
        $select.= " AND timecreated < ".($now - ($now % $a_day));
        break;
    default:
        return;
    }

    $outdated_messages = get_records_select('message', $select, 'timecreated');

    foreach($outdated_messages as $message)
    {
        $messageid = $message->id;
        if (insert_record('message_read', $message) !== NULL)
        {
            delete_records('message', 'id', $messageid);
        }
    }
}

//Check to see if a message was sent.
if(isset($_POST['message']) && strip_tags($_POST['message']) != '')
{
    $usehtmleditor = (can_use_html_editor() && get_user_preferences('message_usehtmleditor', 0));
    if ($usehtmleditor)
        $format = FORMAT_HTML;
    else
        $format = FORMAT_MOODLE;

    $send_message = $_POST['message'];
    $send_message = preg_replace("/(#!amp!#)/", "&", $send_message);
    $send_message = preg_replace("/(#!pls!#)/", "+", $send_message);
    message_post_message($userfrom, $userto,
            addslashes($send_message),
            $format, 'direct');

//
//  Update Last Access
//

/// Store site lastaccess time for the current user
    if ($now - $userfrom->lastaccess > 0) {
    /// Update $USER->lastaccess for next checks
        $userfrom->lastaccess = $now;
        if (defined('MDL_PERFDB')) { $PERF->dbqueries++;};

        $remoteaddr = getremoteaddr();
        if (empty($remoteaddr)) {
            $remoteaddr = '0.0.0.0';
        }
        if ($db->Execute("UPDATE {$CFG->prefix}user
                             SET lastip = '$remoteaddr', lastaccess = $now
                           WHERE id = $userfrom->id")) {
        } else {
            debugging('Error: Could not update global user lastaccess information');  // Don't throw an error
        }
    /// Remove this record from record cache since it will change
        if (!empty($CFG->rcache)) {
            rcache_unset('user', $userfrom->id);
        }
    }
}

$json = '{"messages": {';

if(!isset($_GET['chat']))
{
	$json .= '"message":[ {';
	$json .= '"id":  "0",
            "user": "Admin",
            "text": "You are not currently in a chat session.  &lt;a href=""&gt;Enter a chat session here&lt;/a&gt;",
            "time": "' . date('h:i') . '"
            }]';
}
else
{
	$last = (isset($_GET['last']) && $_GET['last'] != '') ? $_GET['last'] : 0;
//	$messages = get_records_select('message',
//                "useridto = '{$_GET['sender']}'".
//                " AND useridfrom = '{$_GET['receiver']}'".
//                " AND id > {$last}",
//                'timecreated');
	$messages = get_records_select('message',
                "((useridto = '{$_GET['sender']}'".
                " AND useridfrom = '{$_GET['receiver']}') OR ".
                "(useridto = '{$_GET['receiver']}'".
                " AND useridfrom = '{$_GET['sender']}'))".
                " AND id > {$last}",
                'timecreated');

        $first = array_shift($messages);
        array_unshift($messages, $first);

        if(!CheckRefresh($first->timecreated))
        {
            RefreshHistory();
            $messages = get_records_select('message',
                    "((useridto = '{$_GET['sender']}'".
                    " AND useridfrom = '{$_GET['receiver']}') OR ".
                    "(useridto = '{$_GET['receiver']}'".
                    " AND useridfrom = '{$_GET['sender']}'))".
                    " AND id > {$last}",
                    'timecreated');
        }

        if($messages)
        {
            $json .= '"message":[ ';
            foreach($messages as $message)
            {
                $senderid = $message->useridfrom;
                $rec_message = $message->message;
                $rec_message = preg_replace("/[&]/", "#!amp!#", $rec_message);
                $rec_message = preg_replace("/[+]/", "#!pls!#", $rec_message);
                $username = get_field('user','firstname','id',$message->useridfrom);
                $json .= '{';
                $json .= '"id":  "' . $message->id . '",';
                $json .= '"user": "' . htmlspecialchars($username) . '",';
                $json .= '"userid": "' . $senderid . '",';
                $json .= '"text": "' . htmlspecialchars($rec_message) . '",';
                $json .= '"time": "' . $message->timecreated . '"';
                $json .= '},';
            }
            $json = substr($json, 0, strlen($json)-1);
            $json .= ']';
            $json = str_replace("\n", "", $json);
//              $json = json_encode($messages);
        }
        else
        {
		//Send an empty message to avoid a Javascript error when we check for message lenght in the loop.
		$json .= '"message":[]';
	}
}
//Close our response
$json .= '}}';
echo $json;
?>
