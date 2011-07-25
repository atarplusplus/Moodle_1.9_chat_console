<?php //$Id: block_chat_console.php,v 1.54.2.7 2009/11/20 03:08:59 andyjdavis Exp $

/**
 * This block needs to be reworked.
 * The new roles system does away with the concepts of rigid student and
 * teacher roles.
 */
class block_chat_console extends block_base {
    function init() {
        $this->title = get_string('blockname','block_chat_console');
        $this->version = 2011052403;
    }

    function has_config() {return true;}

    function get_content() {
        global $USER, $CFG, $COURSE;

        if ($this->content !== NULL) {
            return $this->content;
        }

        require_js($CFG->wwwroot.'/blocks/chat_console/Console.js.php');

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->instance) || !isloggedin()) {
            return $this->content;
        }

        $timetoshowusers = 300; //Seconds default
        if (isset($CFG->block_chat_console_timetosee)) {
            $timetoshowusers = $CFG->block_chat_console_timetosee * 60;
        }

        if (empty($this->instance->pinned)) {
            $blockcontext = get_context_instance(CONTEXT_BLOCK, $this->instance->id);
        } else {
            $blockcontext = get_context_instance(CONTEXT_SYSTEM); // pinned blocks do not have own context
        }

        //Calculate minutes
        $minutes  = floor($timetoshowusers/60);

        // Verify if we can see the list of users, if not just print number of users
        if (!has_capability('block/online_users:viewlist', $blockcontext))
        {
            $usercount = get_string("none");
            $this->content->text = "<div class=\"info\">".get_string("periodnminutes","block_chat_console",$minutes).": $usercount</div>";
            return $this->content;
        }

        $this->content->text = 
            '<div id="chat_console_block" style="height:150px;overflow:auto;"></div>';

        echo '<script type="text/javascript">var myId='.$USER->id.';</script>';

        require_js($CFG->wwwroot.'/blocks/chat_console/content.js.php');

        return $this->content;
    }
}

?>
