<?php

    require('../../config.php');

    $timetoshowusers = 300; //Seconds default
    if (isset($CFG->block_chat_console_timetosee)) {
        $timetoshowusers = $CFG->block_chat_console_timetosee * 60;
    }
    $timefrom = 100 * floor((time()-$timetoshowusers) / 100); // Round to nearest 100 seconds for better query cache

    // Get context so we can check capabilities.
    $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);

    //Calculate if we are in separate groups
    $isseparategroups = ($COURSE->groupmode == SEPARATEGROUPS
                         && $COURSE->groupmodeforce
                         && !has_capability('moodle/site:accessallgroups', $context));

    //Get the user current group
    $currentgroup = $isseparategroups ? groups_get_course_group($COURSE) : NULL;

    $groupmembers = "";
    $groupselect = "";
    $rafrom  = "";
    $rawhere = "";

    //Add this to the SQL to show only group users
    if ($currentgroup !== NULL) {
        $groupmembers = ",  {$CFG->prefix}groups_members gm ";
        $groupselect = " AND u.id = gm.userid AND gm.groupid = '$currentgroup'";
    }

    if ($COURSE->id == SITEID) {  // Site-level
        $select = "SELECT u.id, u.username, u.firstname, u.lastname, u.picture, max(u.lastaccess) as lastaccess ";
        $from = "FROM {$CFG->prefix}user u
                      $groupmembers ";
        $where = "WHERE u.lastaccess > $timefrom
                  $groupselect ";
        $order = "ORDER BY lastaccess DESC ";

    } else { // Course-level
        if (!has_capability('moodle/role:viewhiddenassigns', $context)) {
            $pcontext = get_related_contexts_string($context);
            $rafrom  = ", {$CFG->prefix}role_assignments ra";
            $rawhere = " AND ra.userid = u.id AND ra.contextid $pcontext AND ra.hidden = 0";
        }

        $courseselect = "AND ul.courseid = '".$COURSE->id."'";
        $select = "SELECT u.id, u.username, u.firstname, u.lastname, u.picture, max(ul.timeaccess) as lastaccess ";
        $from = "FROM {$CFG->prefix}user_lastaccess ul,
                      {$CFG->prefix}user u
                      $groupmembers $rafrom ";
        $where =  "WHERE ul.timeaccess > $timefrom
                   AND u.id = ul.userid
                   AND ul.courseid = $COURSE->id
                   $groupselect $rawhere ";
        $order = "ORDER BY lastaccess DESC ";
    }

    $groupby = "GROUP BY u.id, u.username, u.firstname, u.lastname, u.picture ";

    //Calculate minutes
    $minutes  = floor($timetoshowusers/60);

    $SQL = $select . $from . $where . $groupby . $order;

    if ($users = get_records_sql($SQL, 0, 50)) {   // We'll just take the most recent 50 maximum
        foreach ($users as $user) {
            $users[$user->id]->fullname = fullname($user);
        }
    } else {
        $users = array();
    }

    if (count($users) < 50) {
        $usercount = "";
    } else {
        $usercount = count_records_sql("SELECT COUNT(DISTINCT(u.id)) $from $where");
        $usercount = ": $usercount";
    }

    $blockdata->content->text = "<div class=\"info\">(".get_string("periodnminutes","block_chat_console",$minutes)."$usercount)</div>";

    //Now, we have in users, the list of users to show
    //Because they are online
    if (!empty($users)) {
        //Accessibility: Don't want 'Alt' text for the user picture; DO want it for the envelope/message link (existing lang string).
        //Accessibility: Converted <div> to <ul>, inherit existing classes & styles.
        $blockdata->content->text .= "<ul class='list'>\n";
        if (!empty($USER->id) && has_capability('moodle/site:sendmessage', $context)
                       && !empty($CFG->messaging) && !isguest()) {
            $canshowicon = true;
        } else {
            $canshowicon = false;
        }
        foreach ($users as $user)
        {
            $blockdata->content->text .= '<li class="listentry">';
            $timeago = format_time(time() - $user->lastaccess); //bruno to calculate correctly on frontpage

            $curr_user_pic = print_user_picture(
                        $user->id, $COURSE->id, $user->picture,
                        16, true, false, '', false);

            $curr_user_html = '';

            if ($user->username == 'guest')
            {
                $curr_user_html .= '<div class="user">';
                $curr_user_html .= $curr_user_pic;
                $curr_user_html .= get_string('guestuser');
                $curr_user_html .= '</div>';
            }
            else
            {
                if($USER->id == $user->id)
                    $curr_user_html .= '<div class="user" id="user'.$user->id.'" onmouseover="Highlight(1,'.$user->id.')" onmouseout="Highlight(0,'.$user->id.')">';
                else
                    $curr_user_html .= '<div class="user" id="user'.$user->id.'" onclick="Open_Div_Chat(\''.$user->fullname.'\',\''.$USER->firstname.' '.$USER->lastname.'\','.$USER->id.','.$user->id.')" onmouseover="Highlight(1,'.$user->id.')" onmouseout="Highlight(0,'.$user->id.')">';
                $curr_user_html .= $curr_user_pic;
                $curr_user_html .= $user->fullname;
                $curr_user_html .= '</div>';
            }

            $blockdata->content->text .= $curr_user_html;
            $blockdata->content->text .= "</li>\n";
        }
        $blockdata->content->text .= '</ul><div class="clearer"><!-- --></div>';
    } else {
        $blockdata->content->text .= "<div class=\"info\">".get_string("none")."</div>";
    }

    echo $blockdata->content->text;

?>
