<?php
/********************************************************************************
 * block_manual_grading.php
 *
 * @copyright 2005/2006, Matt Oquist ({@link http://majen.net})
 * @copyright 2005/2006, Seacoast Professional Development Center, Exeter, NH ({@link http://spdc.org/})
 * @copyright 2008, Virtual Learning Academy Charter School ({@link http://vlacs.org})
 * @author Matt Oquist
 * @version  $Id: block_manual_grading.php,v 1.5 2008/07/09 14:44:04 moquist Exp $
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 *
 * vim:shiftwidth=4
 ********************************************************************************/
defined('MOODLE_INTERNAL') or die("Direct access to this location is not allowed.");

global $CFG;
require_once($CFG->dirroot.'/vlacs/lib.php');

/*
 * Block object class for VLACS
 */
class block_manual_grading extends block_base {

    function init() {
        global $CFG, $USER;
        # TODO: I18N all strings...
        $this->title = 'Manual Grading To Do';
        $this->version = 2010021200;
        $this->cron = VLA_BLOCK_CRON_INTERVAL;
    }

    function anchor($text, $url, $attr=array()) {
        $str = "<a href=\"$url\"";
        foreach($attr as $key => $a) {
            $str .= " $key=\"" . htmlspecialchars($a) . "\"";
        }
        $str .= ">$text</a>";
        return $str;
    }

    function get_content() {


        // We don't care about the rest of this!!!
        global $CFG, $USER, $COURSE, $VSA_STATUSIDS, $VLA;
        #$this->content = new stdClass; $this->content->items = array(); $this->content->icons = array(); $this->content->footer = '';
        #return $this->content;

        if (!$site = get_site()) { error(__FUNCTION__, "Site isn't defined!"); }

        $this->content = new object;
        $this->content->text = '';
        $this->content->footer = '';

        require_login($COURSE->id, false);
        $context = get_context_instance(CONTEXT_COURSE, $COURSE->id);

        // Show all courses and their MGTLs.
        if($COURSE->id == SITEID) {
            $sql = "
                SELECT c.*
                FROM {$CFG->prefix}user AS u
                JOIN {$CFG->prefix}classroom AS cr ON cr.sis_user_idstr = u.idnumber
                JOIN {$CFG->prefix}course AS c ON c.idnumber = cr.classroom_idstr
                WHERE u.id = {$USER->id}
            ";
            $courses = get_records_sql($sql);
            if(empty($courses)) {
                return $this->content;
            }

            $this->content->text .= "<p>My courses' manual grading</p>\n<ul>\n";
            foreach($courses as $c) {
                $gurl = new moodle_url("{$CFG->wwwroot}/blocks/manual_grading/report.php"); // grading url

                $gurl->param('action', 'viewquizzes');
                $gurl->param('mode', 'grading');
                $gurl->param('c', $c->id);

                $this->content->text .= "<li>".$this->anchor($c->fullname, $gurl->out())."</li>\n";
            }
            $this->content->text .= "</ul><br />\n";
        }

        // Otherwise just show one MGTL. :)
        if ($COURSE->id != SITEID and has_capability('moodle/grade:edit', $context)) {
            # Manual grading to-do list
            $url = $CFG->wwwroot.'/blocks/manual_grading/report.php';
            $url = $url."?c={$COURSE->id}";
            $url .= '&mode=grading&action=viewquizzes';
            $txt = 'Manual Grading List';
            $link = "<a target=\"_blank\" href=\"$url\">$txt</a>";
            $this->content->text .= "$link<br />";
        }


        #$teachers_only .= vlacs_courseresources('', $COURSE->id);
        #$teachers_only .= vla_exam_passwords($COURSE);

        # Administrative section
        # School admins only
        if (has_capability(VLA_CAP_OFFICE, $context)) {
            $this->content->text .= '<a href="https://courses.vlacs.org/admin/user.php">User Search</a><br />';
        }

        return $this->content;
    }

    function cron() {
        global $CFG, $USER, $COURSE, $db;
        vlacs_debug(__FUNCTION__, VLA_DBG_ENTRY);

        vlacs_rename_courses(VLA_CMD_COURSES_RENAME_NOW);

        vlacs_debug(__FUNCTION__, VLA_DBG_EXIT);
        return true;
    }

} // End of the block_manual_grading class
?>
