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
require_once(dirname(__FILE__) . '/lib.php');

/*
 * Block object class for VLACS
 */
class block_manual_grading_todo extends block_base {

    function init() {
        global $CFG, $USER;
        $this->title = mgtl_get_string('manual_grading_todo');
        $this->version = 2012032100;
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

            $txt = mgtl_get_string('mycoursesmgtl');
            $this->content->text .= "<p>$txt</p>\n<ul>\n";
            foreach($courses as $c) {
                $gurl = new moodle_url("{$CFG->wwwroot}/blocks/manual_grading_todo/report.php"); // grading url

                $gurl->param('action', 'viewquizzes');
                $gurl->param('mode', 'grading');
                $gurl->param('c', $c->id);

                $this->content->text .= "<li>".mgtl_anchor($c->fullname, $gurl->out())."</li>\n";
            }
            $this->content->text .= "</ul><br />\n";
        }

        // Otherwise just show one MGTL. :)
        if ($COURSE->id != SITEID and has_capability('moodle/grade:edit', $context)) {
            # Manual grading to-do list
            $url = $CFG->wwwroot.'/blocks/manual_grading_todo/report.php';
            $url = $url."?c={$COURSE->id}";
            $url .= '&mode=grading&action=viewquizzes';
            $txt = mgtl_get_string('mgtl_list');
            $link = mgtl_anchor($txt, $url, array('target' => '_blank'));
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
} // End of the block_manual_grading class
?>
