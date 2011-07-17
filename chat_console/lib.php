<?php
/// library functions for messaging


define ('MESSAGE_SHORTLENGTH', 300);
define ('MESSAGE_WINDOW', true);          // We are in a message window (so don't pop up a new one!)

if (!isset($CFG->message_contacts_refresh)) {  // Refresh the contacts list every 60 seconds
    $CFG->message_contacts_refresh = 60;
}
if (!isset($CFG->message_chat_refresh)) {      // Look for new comments every 5 seconds
    $CFG->message_chat_refresh = 5;
}
if (!isset($CFG->message_offline_time)) {
    $CFG->message_offline_time = 300;
}

/// Borrowed with changes from mod/forum/lib.php
function message_shorten_message($message, $minlength=0) {
// Given a post object that we already know has a long message
// this function truncates the message nicely to the first
// sane place between $CFG->forum_longpost and $CFG->forum_shortpost

    $i = 0;
    $tag = false;
    $length = strlen($message);
    $count = 0;
    $stopzone = false;
    $truncate = 0;
    if ($minlength == 0) $minlength = MESSAGE_SHORTLENGTH;


    for ($i=0; $i<$length; $i++) {
        $char = $message[$i];

        switch ($char) {
            case "<":
                $tag = true;
                break;
            case ">":
                $tag = false;
                break;
            default:
                if (!$tag) {
                    if ($stopzone) {
                        if ($char == '.' or $char == ' ') {
                            $truncate = $i+1;
                            break 2;
                        }
                    }
                    $count++;
                }
                break;
        }
        if (!$stopzone) {
            if ($count > $minlength) {
                $stopzone = true;
            }
        }
    }

    if (!$truncate) {
        $truncate = $i;
    }

    return substr($message, 0, $truncate);
}

/*
 * Inserts a message into the database, but also forwards it
 * via other means if appropriate.
 */
function message_post_message($userfrom, $userto, $message, $format, $messagetype) {

    global $CFG, $SITE, $USER;

/// Set up current language to suit the receiver of the message
    $savelang = $USER->lang;

    if (!empty($userto->lang)) {
        $USER->lang = $userto->lang;
    }

/// Save the new message in the database

    $savemessage = NULL;
    $savemessage->useridfrom    = $userfrom->id;
    $savemessage->useridto      = $userto->id;
    $savemessage->message       = $message;
    $savemessage->format        = $format;
    $savemessage->timecreated   = time();
    $savemessage->messagetype   = 'direct';

    if ($CFG->messaging) {
        if (!$savemessage->id = insert_record('message', $savemessage)) {
            return false;
        }
        $emailforced = false;
    } else { // $CFG->messaging is not on, we need to force sending of emails
        $emailforced = true;
        $savemessage->id = true;
    }

/// Check to see if anything else needs to be done with it

    $preference = (object)get_user_preferences(NULL, NULL, $userto->id);

    if ($emailforced || (!isset($preference->message_emailmessages) || $preference->message_emailmessages)) {  // Receiver wants mail forwarding
        if (!isset($preference->message_emailtimenosee)) {
            $preference->message_emailtimenosee = 10;
        }
        if (!isset($preference->message_emailformat)) {
            $preference->message_emailformat = FORMAT_HTML;
        }
        if ($emailforced || (time() - $userto->lastaccess) > ((int)$preference->message_emailtimenosee * 60)) { // Long enough

            $message = stripslashes_safe($message);
            $tagline = get_string('emailtagline', 'message', $SITE->shortname);

            $messagesubject = preg_replace('/\s+/', ' ', strip_tags($message)); // make sure it's all on one line
            //convert things like &quot; back to regular characters then strip out tags like <b> <p> etc
            $messagesubject = strip_tags(html_entity_decode($messagesubject));
            $messagesubject = message_shorten_message($messagesubject, 30).'...';

            $messagetext = format_text_email($message, $format).
                           "\n\n--\n".$tagline."\n"."$CFG->wwwroot/message/index.php?popup=1";

            if (isset($preference->message_emailformat) and $preference->message_emailformat == FORMAT_HTML) {
                $messagehtml  = format_text($message, $format);
                // MDL-10294, do not print link if messaging is disabled
                if ($CFG->messaging) {
                    $messagehtml .= '<hr /><p><a href="'.$CFG->wwwroot.'/message/index.php?popup=1">'.$tagline.'</a></p>';
                }
            } else {
                $messagehtml = NULL;
            }

            if (!empty($preference->message_emailaddress)) {
                $userto->email = $preference->message_emailaddress;   // Use custom messaging address
            }

            if (email_to_user($userto, $userfrom, $messagesubject, $messagetext, $messagehtml)) {
                $CFG->messagewasjustemailed = true;
            }
        }
    }

    $USER->lang = $savelang;  // restore original language

    return $savemessage->id;
}

?>