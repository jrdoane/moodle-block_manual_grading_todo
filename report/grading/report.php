<?php  // $Id: report.php,v 1.1 2008/05/29 14:34:26 moquist Exp $
/**
 * Quiz report to help teachers manually grade quiz questions that need it.
 *
 * @package quiz
 * @subpackage reports
 */

define('MGTL_QUIZ', 'QUIZ');
define('MGTL_ASSIGNMENT', 'ASSIGNMENT');
define('MGTL_SORT_ITEMNAME', 'itemname');
define('MGTL_SORT_TIMEFINISHED', 'timefinished');

// Flow of the file:
//     Get variables, run essential queries
//     Check for post data submitted.  If exists, then process data (the data is the grades and comments for essay questions)
//     Check for userid, attemptid, or gradeall and for questionid.  If found, print out the appropriate essay question attempts
//     Switch:
//         first case: print out all essay questions in quiz and the number of ungraded attempts
//         second case: print out all users and their attempts for a specific essay question

require_once($CFG->dirroot . "/mod/quiz/editlib.php");
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/weblib.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/lib.php');

/**
 * Quiz report to help teachers manually grade quiz questions that need it.
 *
 * @package quiz
 * @subpackage reports
 */
class quiz_report extends quiz_default_report {
    /**
     * Displays the report.
     */
    function display($quiz, $cm, $course) {
        global $CFG, $SESSION, $USER, $db, $QTYPES;

        $action = optional_param('action', 'viewquestions', PARAM_ALPHA);
        $questionid = optional_param('questionid', 0, PARAM_INT);
        $sort = optional_param('sort', MGTL_SORT_TIMEFINISHED, PARAM_ALPHA);

        add_to_log($course->id, '', 'block_manual_grading_todo', qualified_me(), '', '', $USER->id);

        $nav = array(
            array(
                'name' => mgtl_get_string('manual_grading_todo')
            )
        );
        print_header($course->fullname, mgtl_get_string('manual_grading_todo'), build_navigation($nav));

        if (!empty($questionid)) {
            if (! $question = get_record('question', 'id', $questionid)) {
                error("Question with id $questionid not found");
            }
            $question->maxgrade = get_field('quiz_question_instances', 'grade', 'quiz', $quiz->id, 'question', $question->id);

            // Some of the questions code is optimised to work with several questions
            // at once so it wants the question to be in an array. The array key
            // must be the question id.
            $key = $question->id;
            $questions[$key] = &$question;

            // We need to add additional questiontype specific information to
            // the question objects.
            if (!get_question_options($questions)) {
                error("Unable to load questiontype specific question information");
            }
            // This will have extended the question object so that it now holds
            // all the information about the questions that may be needed later.
        }

        if (is_object($quiz) and is_object($cm)) {
            add_to_log($course->id, "quiz", "manualgrading", "report.php?mode=grading&amp;q=$quiz->id", "$quiz->id", "$cm->id");
        }

        echo '<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>'; // for overlib

        if ($data = data_submitted()) {  // post data submitted, process it
            confirm_sesskey();

            // now go through all of the responses and save them.
            foreach($data->manualgrades as $uniqueid => $response) {
                // get our attempt
                if (! $attempt = get_record('quiz_attempts', 'uniqueid', $uniqueid)) {
                    error('No such attempt ID exists');
                }

                // Load the state for this attempt (The questions array was created earlier)
                $states = get_question_states($questions, $quiz, $attempt);
                // The $states array is indexed by question id but because we are dealing
                // with only one question there is only one entry in this array
                $state = &$states[$question->id];

                // the following will update the state and attempt
                question_process_comment($question, $state, $attempt, $response['comment'], $response['grade']);

                // If the state has changed save it and update the quiz grade
                if ($state->changed) {
                    save_question_session($question, $state);
                    quiz_save_best_grade($quiz, $attempt->userid);
                }
            }
            notify(get_string('changessaved', 'quiz'), 'notifysuccess');
        }

        // our 4 different views
        // the first one displays all of the assessments in the course with 
        // outstanding manually graded questions, with the number of ungraded 
        // attempts for each quiz

        // the second one displays all of the manually graded questions in the quiz
        // with the number of ungraded attempts for each question

        // the third view displays the users who have answered the essay question
        // and all of their attempts at answering the question

        // the fourth prints the question with a comment
        // and grade form underneath it

        switch($action) {
            case 'viewquizzes':
                $this->view_quizzes($course, $sort);
                break;
            default:
                print "error: unknown action $action";
                exit;
        }
        return true;
    }

    /**
     * Prints a table containing all quizzes with manually graded questions in the course
     *
     * @param object $course Course object of the current course
     * @param string $sort is a sort string on which field to sort.
     * @return boolean
     **/
    function view_quizzes($course, $sort='') {
        global $CFG, $QTYPE_MANUAL;

        $str_itemname = mgtl_get_string('itemname');
        $str_student = mgtl_get_string('student');
        $str_submissiontime = mgtl_get_string('submissiontime');
        $str_tableheading = mgtl_get_string('itemsrequiredgrading');
        $str_extrainfo = mgtl_get_string('extrasubmissioninfo');

        $users = get_course_students($course->id);

        if(empty($users)) {
            print_heading(get_string("noattempts", "quiz"));
            return true;
        }

        $table = new stdClass;
        $table->head = array($str_itemname, $str_student, $str_submissiontime, $str_extrainfo);
        $table->align = array("left", "left", "right");
        $table->wrap = array("wrap", "wrap", "wrap");
        $table->width = "20%";
        $table->size = array("*", "*", "*");
        $table->data = array();

        // Setup sort links.
        $sort_opts = array(
            MGTL_SORT_ITEMNAME => mgtl_get_string('assessmentname'),
            MGTL_SORT_TIMEFINISHED => mgtl_get_string('timefinished')
        );
        $sorturl = new moodle_url(qualified_me());
        $str_sorted = '<small><small>('.mgtl_get_string('sorted').')</small></small>';

        if($sort != MGTL_SORT_ITEMNAME) {
            $sorturl->param('sort', MGTL_SORT_ITEMNAME);
            $table->head[0] = mgtl_anchor($table->head[0], $sorturl->out());
        } else {
            $table->head[0] .= "<br />{$str_sorted}";
        }

        if($sort != MGTL_SORT_TIMEFINISHED) {
            $sorturl->param('sort', MGTL_SORT_TIMEFINISHED);
            $table->head[2] = mgtl_anchor($table->head[2], $sorturl->out());
        } else {
            $table->head[2] .= "<br />{$str_sorted}";
        }

        # setup the table

        # quizzes and attempts are merged in $work
        $work = array();

        $contextlevel = CONTEXT_COURSE;

        # get quiz attempts 
        $events_submitted = QUESTION_EVENTSUBMIT;
        $events_submitted .= ','.QUESTION_EVENTCLOSE;

        $events_graded = QUESTION_EVENTGRADE;
        $events_graded .= ','.QUESTION_EVENTCLOSEANDGRADE;
        $events_graded .= ','.QUESTION_EVENTMANUALGRADE;

        $min_time = get_field('manual_grading_todo_cache', 'timestamp', 'courseid', $course->id);
        if (!$min_time) { $min_time = 0; }

        $sql = "
            SELECT qs.id, pq.id AS quizid, pq.name AS quiz_name, qs.event, qa.userid, qa.uniqueid, q.id as questionid, qs.timestamp,
            u.firstname, u.lastname, qa.timefinish, qa.timestart, qa.id AS attemptid
            FROM {$CFG->prefix}quiz pq
                JOIN {$CFG->prefix}quiz_question_instances qqi ON qqi.quiz = pq.id
                JOIN {$CFG->prefix}question q ON qqi.question = q.id AND q.qtype = 'essay'
                JOIN {$CFG->prefix}question_states qs ON qs.question = qqi.question
                JOIN {$CFG->prefix}quiz_attempts qa ON qa.uniqueid = qs.attempt AND qa.quiz = pq.id
                JOIN {$CFG->prefix}user u ON qa.userid = u.id
                JOIN {$CFG->prefix}role_assignments ra ON u.id = ra.userid
                JOIN {$CFG->prefix}context c ON c.id = ra.contextid
                JOIN {$CFG->prefix}role r ON r.id = ra.roleid
            WHERE
                qs.timestamp >= $min_time AND
                qa.timefinish >= $min_time AND
                c.contextlevel = {$contextlevel} AND
                c.instanceid = {$course->id} AND
                r.shortname = 'student' AND
                pq.course = {$course->id} AND
                qa.timefinish != 0
        ";
        $qattempts = get_records_sql($sql);

        # group the attempts by quiz while adding to the work array
        # (attempts is actually a misnomer, we're really dealing with
        # question states, potentially for multiple questions per attempt)
        foreach ($qattempts as $qa) {
            $key = "q$qa->quizid";
            if (!isset($work[$key])) {
                $work[$key] = (object) array(
                    'id' => $qa->quizid,
                    'uniqueid' => $qa->uniqueid,
                    'name' => $qa->quiz_name,
                    'attempts' => array(),
                    'graded_attempts' => array(),
                    'type' => MGTL_QUIZ,
                    'sort' => $sort,
                );
            }
            switch (intval($qa->event)) {
                case QUESTION_EVENTSUBMIT:
                case QUESTION_EVENTCLOSE:
                    if (!isset($work[$key]->graded_attempts["$qa->uniqueid:$qa->questionid"])) {
                        $work[$key]->attempts["$qa->uniqueid:$qa->questionid"] = $qa;
                    }
                    break;
                case QUESTION_EVENTGRADE:
                case QUESTION_EVENTCLOSEANDGRADE:
                case QUESTION_EVENTMANUALGRADE:
                    $work[$key]->graded_attempts["$qa->uniqueid:$qa->questionid"] = $qa;
                    unset($work[$key]->attempts["$qa->uniqueid:$qa->questionid"]);
                    break;
            }
        }

        # remove quizzes with out ungraded submissions
        #   (we don't have to do this for assignments as in 
        #   assignments our query gets us only ungraded submissions)
        foreach ($work as $key => $w) {
            if (!count($w->attempts)) { unset($work[$key]); }
        }

        # get the assignments
        $sql = "
            SELECT s.id, s.assignment, a.name, s.timemodified AS timefinish, u.id AS userid, u.firstname,
                u.lastname, a.course, (s.timemodified > s.timemarked AND s.grade > -1) AS updatedsubmission
            FROM {$CFG->prefix}assignment_submissions s
                JOIN {$CFG->prefix}assignment a ON a.id = s.assignment
                JOIN {$CFG->prefix}user u ON u.id = s.userid
                JOIN {$CFG->prefix}role_assignments ra ON u.id = ra.userid
                JOIN {$CFG->prefix}context c ON c.id = ra.contextid
                JOIN {$CFG->prefix}role r ON r.id = ra.roleid
            WHERE
                c.contextlevel = {$contextlevel} AND
                c.instanceid = {$course->id} AND
                r.shortname = 'student' AND
                a.course = {$course->id} AND
                s.timemodified > 0 AND
                (
                    s.timemodified > s.timemarked OR
                    s.grade = -1
                ) AND (
                    a.assignmenttype != 'upload' OR
                    s.data2 = 'submitted'
                )
        ";
        $assignments = get_records_sql($sql);

        // Supress the invalid arg on the foreach when this returns 
        // a non-populated array.
        if(empty($assignments)) {
            $assignments = array();
        }

        # group the attempts by assignment while adding to the work array
        foreach ($assignments as $a) {
            $key = "a$a->assignment";
            if (!isset($work[$key])) {
                # we don't need graded_attempts for assignments
                $work[$key] = (object) array(
                    'id' => $a->assignment,
                    'name' => $a->name,
                    'attempts' => array(),
                    'type' => MGTL_ASSIGNMENT,
                    'sort' => $sort
                );
            }
            $cm = get_coursemodule_from_instance('assignment', $a->assignment, $a->course);
            $a->cmid = $cm->id;
            $work[$key]->attempts[] = $a;
        }

        # sort the quizzes/assignments by earliest ungraded submission
        foreach ($work as $w) {
            uasort($w->attempts, 'mgtl_attempts_cmp');
        }
        uasort($work, 'mgtl_cmp');

        unset($min_time);

        # build the table
        $quiz_url = new moodle_url("{$CFG->wwwroot}/mod/quiz/report.php");
        $quiz_url->param('mode', 'grading');
        $assignment_url = new moodle_url("{$CFG->wwwroot}/mod/assignment/submissions.php");
        $assignment_url->param('sort', 'status');
        $min_time = 0;

        foreach ($work as $w) {
            $url = '';
            $attempturl = '';
            $mtc = reset($w->attempts)->timefinish;
            if (empty($min_time)) { $min_time = $mtc; }
            else { $min_time = $min_time > $mtc ? $mtc : $min_time; }
            switch ($w->type) {
            case MGTL_QUIZ:
                $quiz_url->param('q', $w->id);
                $url = $quiz_url->out();
                break;
            case MGTL_ASSIGNMENT:
                $assignment_url->param('a', $w->id);
                $url = $assignment_url->out();
                break;
            }
            $options = 'menubar,location,scrollbars,resizable,width=780,height=500';

            $link = link_to_popup_window($url, $w->name, $w->name, 550, 750, 'Manual Grading: '.$w->name, $options, true);

            $displayed_attempts = array();
            $first = true;

            $quiz_attempt_url = new moodle_url("{$CFG->wwwroot}/mod/quiz/review.php");
            $quiz_attempt_url->param('q', $w->id);

            $assignment_attempt_url = new moodle_url("{$CFG->wwwroot}/mod/assignment/submissions.php");
            $assignment_attempt_url->param('mode', 'single');
            $assignment_attempt_url->param('offset', 1);

            foreach ($w->attempts as $a) {
                $extrainfo = '';
                $attempturl = '';
                $attemptdate = strftime('%F %r',$a->timefinish);

                if ($w->type == MGTL_QUIZ) {
                    if (isset($displayed_attempts[$a->uniqueid])) { continue; }
                    $quiz_attempt_url->param('attempt', $a->attemptid);
                    $attempturl = $quiz_attempt_url->out();
                    $displayed_attempts[$a->uniqueid] = true;
                    $extrainfo = mgtl_get_string('quizattempt');
                }

                if ($w->type == MGTL_ASSIGNMENT) {
                    $assignment_attempt_url->param('id', $a->cmid);
                    $assignment_attempt_url->param('userid', $a->userid);
                    $attempturl = $assignment_attempt_url->out();
                    if($a->updatedsubmission == 't') {
                        $extrainfo = mgtl_get_string('updatedassignmentsubmission');
                    } else {
                        $extrainfo = mgtl_get_string('newassignmentsubmission');
                    }
                }

                if(!empty($attempturl)) {
                    $attemptdate = link_to_popup_window($attempturl, $attemptdate, $attemptdate, 550, 750, 'Manual Grading: '.$w->name, $options, true);
                }

                # the first line for a quiz should have the quiz name/link, student name, and submission datetime
                if ($first) {
                    $table->data[] = array($link, "$a->lastname, $a->firstname", $attemptdate, $extrainfo);
                    $first = false;
                    continue;
                }
                # each successive line just needs the student name and submission datetime
                $table->data[] = array('', "$a->lastname, $a->firstname", $attemptdate, $extrainfo);
            }
        }

        # print the table
        print "<h3 style=\"text-align: center;\">$str_tableheading</h3>";
        if (count($table->data)) {
            print_table($table);
        } else {
            print "<center><big>".mgtl_get_string('none')."</big></center>";
        }

        # save the new min time
        if (!empty($min_time)) {
            if (record_exists('manual_grading_todo_cache', 'courseid', $course->id)) {
                set_field('manual_grading_todo_cache', 'timestamp', $min_time, 'courseid', $course->id);
            } else {
                $manual_grading = (object) array(
                    'courseid' => $course->id,
                    'timestamp' => $min_time,
                    'timecreated' => time(),
                );
                insert_record('manual_grading_todo_cache', $manual_grading);
            }
        }

        return true;
    }

    /**
     * Checks to see if a question in a particular attempt is graded
     *
     * @return boolean
     * @todo Finnish documenting this function
     **/
    function is_graded($question, $attempt) {
        global $CFG;

        if (!$state = get_record_sql("SELECT state.id, state.event FROM
        {$CFG->prefix}question_states state, {$CFG->prefix}question_sessions sess
        WHERE sess.newest = state.id AND
        sess.attemptid = $attempt->uniqueid AND
        sess.questionid = $question->id")) {
            error('Could not find question state');
        }

        return ($state->event == QUESTION_EVENTGRADE
            or $state->event == QUESTION_EVENTCLOSEANDGRADE
            or $state->event == QUESTION_EVENTMANUALGRADE);
        #return question_state_is_graded($state);
    }
}

?>
