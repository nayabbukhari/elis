<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2010 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis
 * @subpackage programmanagement
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2010 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once elispm::lib('lib.php');
require_once elispm::lib('deprecatedlib.php'); // cm_get_param()
require_once elispm::lib('page.class.php');
require_once elispm::lib('associationpage.class.php');
//require_once elispm::lib('table.class.php');
require_once elispm::lib('data/pmclass.class.php');
require_once elispm::lib('data/student.class.php');
require_once elispm::lib('contexts.php'); // TBD

require_once elispm::file('pmclasspage.class.php');
require_once elispm::file('form/waitlistform.class.php');

class studentpage extends associationpage {
    const LANG_FILE = 'elis_program';

    var $data_class = 'student';
    var $pagename = 'stu';
    var $tab_page = 'pmclasspage'; // TBD: was cmclasspage

    //var $form_class = 'studentform';

    var $section = 'curr';

    var $parent_data_class = 'pmclass'; // TBD: was cmclass

    //var $tabs; // TBD: moved to associationpage

    function __construct(array $params = null) {
        $this->tabs = array( // TBD: 'currcourse_edit' -> 'edit'
            array('tab_id' => 'view', 'page' => get_class($this), 'params' => array('action' => 'view'), 'name' => get_string('detail', self::LANG_FILE), 'showtab' => true),
            array('tab_id' => 'currcourse_edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit', self::LANG_FILE), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'),
            array('tab_id' => 'edit', 'page' => get_class($this), 'params' => array('action' => 'edit'), 'name' => get_string('edit', self::LANG_FILE), 'showtab' => true, 'showbutton' => true, 'image' => 'edit'), // TBD: tab_id was 'edit' or 'bulkedit'???
           array('tab_id' => 'delete', 'page' => get_class($this), 'params' => array('action' => 'delete'), 'name' => get_string('delete', self::LANG_FILE), 'showbutton' => true, 'image' => 'delete'),
        );

        parent::__construct($params);
    }

    function _get_page_context() { // TBD
        return parent::_get_page_context();
    }

    function _get_page_params() { // TBD
        return parent::_get_page_params();
    }

    function can_do_default() { // cm => pm
        $id = $this->required_param('id', PARAM_INT);
        $pmclasspage = new pmclasspage(array('id' => $id));
        return $pmclasspage->can_do();
    }

    function can_do_add() {
        $id = $this->required_param('id');
        $users = optional_param('users', array(), PARAM_CLEAN);

        foreach($users as $uid => $user) {
            if(!student::can_manage_assoc($uid, $id)) {
                return false;
            }
        }

        return pmclasspage::can_enrol_into_class($id);
    }

    function can_do_savenew() {
        return $this->can_do_add();
    }

    function can_do_delete() {
        return $this->can_do_edit();
    }

    function can_do_confirm() {
        return $this->can_do_edit();
    }

    function can_do_edit() {
        $association_id = $this->optional_param('association_id', '', PARAM_INT);
        if (empty($association_id)) { // TBD
            error_log('studentpage.class.php::can_do_edit() - empty association_id!');
            return false;
        }
        $student = new student($association_id);
        return student::can_manage_assoc($student->userid, $student->classid);
    }

    function can_do_update() {
        return $this->can_do_edit();
    }

    function can_do_view() {
        return $this->can_do_edit();
    }

    function can_do_bulkedit() {
        //todo: allow bulk editing for non-admins
        $id = $this->required_param('id');
        return pmclasspage::_has_capability('block/curr_admin:track:enrol', $id);
    }

    function can_do_updatemultiple() {
        //todo: allow multi-update for non-admins
        $id = $this->required_param('id');
        return pmclasspage::_has_capability('block/curr_admin:track:enrol', $id);
    }

    function do_add() { // TBD: must overload the parents since no studentform
        //error_log('studentpage.class.php::do_add()');
        $this->do_savenew();
        //$this->display('add');
    }

    function do_delete() { // action_confirm
        $stuid = $this->required_param('association_id', PARAM_INT);
        $confirm = $this->required_param('confirm', PARAM_TEXT);

        $stu = new student($stuid);
        $sparam = new stdClass;
        $sparam->name = fullname($stu->user); // TBD ???
        if (md5($stuid) != $confirm) { // TBD
            echo cm_error(get_string('invalidconfirm', self::LANG_FILE));
        } else {
            $status = $stu->delete(); // TBD: no return code from delete()
          /* ****
            if (!$status) {
                echo cm_error(get_string('studentnotunenrolled', self::LANG_FILE, $sparam));
            } else
          **** */
            {
                echo cm_error(get_string('studentunenrolled', self::LANG_FILE, $sparam));
            }
        }
        $this->display('default'); // do_default()
    }

    function display_bulkedit() { // action_bulkedit
        $clsid        = cm_get_param('id', 0);
        $type         = cm_get_param('stype', '');
        $sort         = cm_get_param('sort', 'name');
        $dir          = cm_get_param('dir', 'ASC');
        $page         = cm_get_param('page', 0);
        $perpage      = cm_get_param('perpage', 30);        // how many per page
        $namesearch   = trim(cm_get_param('search', ''));
        $alpha        = cm_get_param('alpha', '');

        // TBD: 'edit' or 'bulkedit' or ???; and array(params ???)
        // print_tabs now in parent::print_header()
        // $this->get_tab_page()->print_tabs('edit', array('id' => $clsid));
        echo $this->get_view_form($clsid, $type, $sort, $dir, $page, $perpage, $namesearch, $alpha);
    }

    function do_savenew() { // action_savenew
        $clsid = $this->required_param('id', PARAM_INT);
        $users = $this->optional_param('users', array(), PARAM_CLEAN);

        if (!empty($users)) {
            $this->attempt_enrol($clsid, $users);
        } else {
            $this->display('add'); // do_default()
        }
    }

    private function build_student($uid, $clsid, $user) {
        $sturecord            = array();
        $sturecord['classid'] = $clsid;
        $sturecord['userid']  = $uid;

        $startyear  = $user['startyear'];
        $startmonth = $user['startmonth'];
        $startday   = $user['startday'];
        $sturecord['enrolmenttime'] = mktime(0, 0, 0, $startmonth, $startday, $startyear);

        $endyear  = $user['endyear'];
        $endmonth = $user['endmonth'];
        $endday   = $user['endday'];
        $sturecord['completetime'] = mktime(0, 0, 0, $endmonth, $endday, $endyear);

        $sturecord['completestatusid'] = $user['completestatusid'];
        $sturecord['grade']            = $user['grade'];
        $sturecord['credits']          = $user['credits'];
        $sturecord['locked']           = !empty($user['locked']) ? 1 : 0;

        return new student($sturecord);
    }

    private function attempt_enrol($classid, $users) {
        foreach ($users as $uid => $user) {
            if (!empty($user['enrol'])) {
                $newstu = $this->build_student($uid, $classid, $user);

                if($newstu->completestatusid != STUSTATUS_NOTCOMPLETE || empty($newstu->pmclass->maxstudents) || $newstu->pmclass->maxstudents > $newstu->count_enroled()) {
                    $status = $newstu->update(); // TBD: ->save()? WAS: $newstu->do_add();
                } else {
                    $waitlist[] = $newstu;
                    $status = true;
                }

              /* **** no return code from ->save()/update() ****
                if ($status !== true) {
                    if (!empty($status->message)) {
                        echo cm_error(get_string('record_not_created_reason',
                                         self::LANG_FILE, $status));
                    } else {
                        echo cm_error(get_string('record_not_created', self::LANG_FILE));
                    }
                }
              **** */
            }
        }

        if(!empty($waitlist)) {
            $this->get_waitlistform($waitlist);
        } else {
            $this->display('add'); // do_default() TBD???
        }
    }

    /*
     * foreach student to enrol
     *      set up the student object
     *      enrol the student
     */
    function do_update() { //action_update
        global $DB;
        $stuid = $this->required_param('association_id', PARAM_INT);
        $clsid = $this->required_param('id', PARAM_INT);
        $users = $this->required_param('users');

        $uid   = key($users);
        $user  = current($users);

        $sturecord                     = array();
        $sturecord['id']               = $stuid;
        $sturecord['classid']          = $clsid;
        $sturecord['userid']           = $uid;

        $startyear  = $user['startyear'];
        $startmonth = $user['startmonth'];
        $startday   = $user['startday'];
        $sturecord['enrolmenttime'] = mktime(0, 0, 0, $startmonth, $startday, $startyear);

        $endyear  = $user['endyear'];
        $endmonth = $user['endmonth'];
        $endday   = $user['endday'];
        $sturecord['completetime'] = mktime(0, 0, 0, $endmonth, $endday, $endyear);

        $sturecord['completestatusid'] = $user['completestatusid'];
        $sturecord['grade']            = $user['grade'];
        $sturecord['credits']          = $user['credits'];
        $sturecord['locked']           = !empty($user['locked']) ? 1 : 0;
        $stu                           = new student($sturecord);

        if ($stu->completestatusid == STUSTATUS_PASSED &&
            $DB->get_field(student::TABLE, 'completestatusid', array('id' => $stuid)) != STUSTATUS_PASSED) {

            $stu->complete();
        } else {
            $status = $stu->update();
          /* *** no return code
            if ($status !== true) {
                echo cm_error(get_string('record_not_updated', self::LANG_FILE, $status));
            }
          **** */
        }

        /// Check for grade records...
        $element = cm_get_param('element', array());
        $newelement = cm_get_param('newelement', array());
        $timegraded = cm_get_param('timegraded', array());
        $newtimegraded = cm_get_param('newtimegraded', array());
        $completionid = cm_get_param('completionid', array());
        $newcompletionid = cm_get_param('newcompletionid', array());
        $grade = cm_get_param('grade', array());
        $newgrade = cm_get_param('newgrade', array());
        $locked = cm_get_param('locked', array());
        $newlocked = cm_get_param('newlocked', array());

        foreach ($element as $gradeid => $element) {
            $graderec = array();
            $graderec['id'] = $gradeid;
            $graderec['userid'] = $uid;
            $graderec['classid'] = $clsid;
            $graderec['completionid'] = $element;
            $graderec['timegraded'] = mktime(0, 0, 0, $timegraded[$gradeid]['startmonth'],
                                             $timegraded[$gradeid]['startday'], $timegraded[$gradeid]['startyear']);
            $graderec['grade'] = $grade[$gradeid];
            $graderec['locked'] = isset($locked[$gradeid]) ? $locked[$gradeid] : '0';

            $sgrade = new student_grade($graderec);
            $sgrade->update();
        }

        foreach ($newelement as $elementid => $element) {
            $graderec = array();
            $graderec['userid'] = $uid;
            $graderec['classid'] = $clsid;
            $graderec['completionid'] = $element;
            $graderec['timegraded'] = mktime(0, 0, 0, $newtimegraded[$elementid]['startmonth'],
                                             $newtimegraded[$elementid]['startday'], $newtimegraded[$elementid]['startyear']);
            $graderec['grade'] = $newgrade[$elementid];
            $graderec['locked'] = isset($newlocked[$elementid]) ? $newlocked[$elementid] : '0';

            $sgrade = new student_grade($graderec);
            $sgrade->do_add();
        }

        $this->display('default'); // do_default()
    }

    /**
     *
     */
    function do_updatemultiple() { // action_updatemultiple
        global $DB;
        $clsid = $this->required_param('id', PARAM_INT);
        $users = $this->optional_param('users', array(), PARAM_CLEAN);

        foreach($users as $uid => $user) {
            $sturecord            = array();
            $sturecord['id']      = $user['association_id'];
            $sturecord['classid'] = $clsid;
            $sturecord['userid']  = $uid;

            $startyear  = $user['startyear'];
            $startmonth = $user['startmonth'];
            $startday   = $user['startday'];
            $sturecord['enrolmenttime'] = mktime(0, 0, 0, $startmonth, $startday, $startyear);

            $endyear  = $user['endyear'];
            $endmonth = $user['endmonth'];
            $endday   = $user['endday'];
            $sturecord['completetime'] = mktime(0, 0, 0, $endmonth, $endday, $endyear);

            $sturecord['completestatusid'] = $user['completestatusid'];
            $sturecord['grade']            = $user['grade'];
            $sturecord['credits']          = $user['credits'];
            $sturecord['locked']           = !empty($user['locked']) ? 1 : 0;
            $stu                           = new student($sturecord);

            if ($stu->completestatusid == STUSTATUS_PASSED &&
                $DB->get_field(student::TABLE, 'completestatusid', array('id' => $stu->id)) != STUSTATUS_PASSED) {
                $stu->complete();
            } else {
                $status = $stu->update(); // ->update() or ->save()
              /* **** no return code from save()
                if ($status !== true) {
                    echo cm_error(get_string('record_not_updated', self::LANG_FILE, $status));
                }
              **** */
            }

            // Now once we've done all this, delete the student if we've been asked to
            if (isset($user['unenrol']) && pmclasspage::can_enrol_into_class($clsid)) {
                $stu_delete = new student($sturecord); // TBD: param was $user['association_id']
                $status = $stu_delete->delete();
              /* **** no return code from delete()
                if(!$status) {
                    $sparam = new stdClass;
                    $sparam->name = fullname($stu->user);
                    echo cm_error(get_string('studentnotunenrolled', self::LANG_FILE, $sparam));
                }
              **** */
            }
        }

        $this->display('default'); // do_default()
    }

    function do_updateattendance() { // action_updateattendance
        $atnrecord                  = array();
        $atnrecord['id']            = cm_get_param('atnid', 0);
        $atnrecord['classid']       = $clsid;
        $atnrecord['userid']        = cm_get_param('userid', 0);

        $startyear  = cm_get_param('startyear');
        $startmonth = cm_get_param('startmonth');
        $startday   = cm_get_param('startday');
        $atnrecord['timestart'] = mktime(0, 0, 0, $startmonth, $startday, $startyear);

        $endyear  = cm_get_param('endyear');
        $endmonth = cm_get_param('endmonth');
        $endday   = cm_get_param('endday');
        $atnrecord['timeend'] = mktime(0, 0, 0, $endmonth, $endday, $endyear);

        $atnrecord['note'] = cm_get_param('note', '');
        $atn = new attendance($atnrecord);

        $status = $atn->save(); // ->update
      /* **** no return code
        if ($status !== true) {
            echo cm_error(get_string('record_not_updated', self::LANG_FILE, $status));
        }
      **** */
    }

    /**
     *
     */
    public function do_waitlistconfirm() { // action_waitlistconfirm
        $id = $this->required_param('userid', PARAM_INT);

        $form_url = new moodle_url(null, array('s'=>$this->pagename, 'section'=>$this->section, 'action'=>'waitlistconfirm'));

        $waitlistform = new waitlistaddform($form_url, array('student_ids'=>$id));

        if($data = $waitlistform->get_data()) {
            $now = time();

            foreach($data->userid as $uid) {
                if(isset($data->enrol[$uid]) &&
                    isset($data->classid[$uid]) &&
                    isset($data->enrolmenttime[$uid])) {

                    if($data->enrol[$uid] == 1) {
                        $wait_record = new object();
                        $wait_record->userid = $uid;
                        $wait_record->classid = $data->classid[$uid];
                        $wait_record->enrolmenttime = $data->enrolmenttime[$uid];
                        $wait_record->timecreated = $now;
                        $wait_record->position = 0;

                        $wait_list = new waitlist($wait_record);
                        $wait_list->save(); // TBD: was $wait_list->do_add()
                    } else if($data->enrol[$uid] == 2) {
                        $user = new user($uid);
                        $student_data= array();
                        $student_data['classid'] = $data->classid[$uid];
                        $student_data['userid'] = $uid;
                        $student_data['enrolmenttime'] = $data->enrolmenttime[$uid];
                        $student_data['timecreated'] = $now;
                        $student_data['completestatusid'] = STUSTATUS_NOTCOMPLETE;

                        $newstu = new student($student_data);
                        $status = $newstu->update(); // TBD: was $newstu->do_add()
                      /* **** returns objects from student::update() & save()
                        if ($status !== true) {
                            if (!empty($status->message)) {
                                echo cm_error(get_string('record_not_created_reason', self::LANG_FILE, $status));
                            } else {
                                echo cm_error(get_string('record_not_created',
                                                  self::LANG_FILE));
                            }
                        }
                      **** */
                    }
                }
            }
        }

        $this->display('default'); // do_default()
    }

    /**
     *
     * @global <type> $CFG
     * @uses $CFG
     * @uses $OUTPUT
     */
    function display_default() { // action_default (and above)
        global $CFG, $OUTPUT;

        $clsid        = $this->required_param('id', PARAM_INT);
        $sort         = $this->optional_param('sort', 'name', PARAM_ALPHANUM);
        $dir          = $this->optional_param('dir', 'ASC', PARAM_ALPHA);
        $page         = $this->optional_param('page', 0, PARAM_INT);
        $perpage      = $this->optional_param('perpage', 30, PARAM_INT); // how many per page
        $namesearch   = trim($this->optional_param('search', '', PARAM_TEXT));
        $alpha        = $this->optional_param('alpha', '', PARAM_ALPHA);

        $cls = new pmclass($clsid);

        // TBD: see student.class.php
        $columns = array(
            'idnumber'         => array('header' => get_string('student_idnumber', self::LANG_FILE)),
            'name'             => array('header' => get_string('student_name_1', self::LANG_FILE)),
            'enrolmenttime'    => array('header' => get_string('enrolment_time', self::LANG_FILE)),
            'completetime'     => array('header' => get_string('completion_time', self::LANG_FILE)),
            'completestatusid' => array('header' => get_string('student_status', self::LANG_FILE)),
            'grade'            => array('header' => get_string('student_grade', self::LANG_FILE)),
            'credits'          => array('header' => get_string('student_credits', self::LANG_FILE)),
            'locked'           => array('header' => get_string('student_locked', self::LANG_FILE)),
            'buttons'          => array('header' => '', 'sortable' => false ), // TBD , ?
            );

        // TBD
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }
        if (isset($columns[$sort])) {
            $columns[$sort]['sortable'] = $dir;
        } else {
            $sort = 'name';
            $columns[$sort]['sortable'] = $dir;
        }

        // print_tabs now in parent::print_header()
        //$this->get_tab_page()->print_tabs('view', array('id' => $clsid)); // TBD

        $stus    = student_get_listing($clsid, $sort, $dir, $page*$perpage, $perpage, $namesearch, $alpha);
        $numstus = student_count_records($clsid, $namesearch, $alpha);

        $this->print_num_items($clsid, $cls->maxstudents);

        $this->print_alpha();
        $this->print_search();

        $this->print_list_view($stus, $columns);

        $pagingbar = new paging_bar($numstus, $page, $perpage,
                         "index.php?s=stu&amp;section=curr&amp;id=$clsid&amp;sort=$sort&amp;" .
                         "dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;search="
                         . urlencode(stripslashes($namesearch))); // .'&amp;'
        echo $OUTPUT->render($pagingbar);

        echo "<form>";
        // TODO: pass in query parameters
        if ($this->can_do('bulkedit')) {
            echo "<input type=\"button\" onclick=\"document.location='index.php?s=stu&amp;section=curr&amp;" .
                "action=bulkedit&amp;id=$clsid&amp;sort=$sort&amp;dir=$dir&amp;perpage=$perpage&amp;alpha=$alpha&amp;search=" . urlencode(stripslashes($namesearch)) . "';\" value=\"Bulk Edit\" />";
        }
        if ($this->can_do('add')) {
            echo "<input type=\"button\" onclick=\"document.location='index.php?s=stu&amp;section=curr&amp;" .
                "action=add&amp;id=$clsid';\" value=\"" . get_string('enrolstudents', self::LANG_FILE) . "\" />";
        }
        echo "</form>";
    }

    public function create_table_object($items, $columns /*, $formatters */) {
        return new student_table($items, $columns, $this);
    }

    public function get_waitlistform($students) {
        $form_url = new moodle_url(null, array('s'=>$this->pagename, 'section'=>$this->section, 'action'=>'waitlistconfirm'));

        $student = current($students);
        $data = $student->pmclass;
        $waitlistform = new waitlistaddform($form_url, array('obj'=>$data, 'students'=>$students));

        $waitlistform->display();
    }

    function get_view_form($clsid, $type, $sort, $dir, $page, $perpage, $namesearch, $alpha) {
        $output = '';

        $newstu = new student();
        $newstu->classid = $clsid;

        $output .= $newstu->view_form_html($clsid, $type, $sort, $dir, $page, $perpage, $namesearch, $alpha);

        return $output;
    }

    function print_add_form($cmclass) {
        $type         = $this->optional_param('stype', '', PARAM_ALPHA);
        $sort         = $this->optional_param('sort', 'name', PARAM_ALPHANUM);
        $dir          = $this->optional_param('dir', 'ASC', PARAM_ALPHA);
        $page         = $this->optional_param('page', 0, PARAM_INT);
        $perpage      = $this->optional_param('perpage', 30, PARAM_INT); // how many per page
        $namesearch   = trim($this->optional_param('search', '', PARAM_TEXT));
        $alpha        = $this->optional_param('alpha', '', PARAM_ALPHA);

        $newstu = new student();
        $newstu->classid = $cmclass->id;

        echo $newstu->edit_form_html($cmclass->id, $type, $sort, $dir, $page, $perpage, $namesearch, $alpha);
    }

    function print_edit_form($stu, $cls) {
        echo $stu->edit_form_html($stu->id);
    }


    /**
     * Returns the delete student form.
     *
     * @param int $id The ID of the student.
     * @return string HTML for the form.
     *
     */
    function print_delete_form($stu) {
        $url     = 'index.php';
        $a = new stdClass;
        $a->name = fullname($stu->user);
        $message = get_string('student_deleteconfirm', self::LANG_FILE, $a);
        $optionsyes = array('s' => 'stu', 'section' => 'curr', 'id' => $stu->classid,
                            'action' => 'confirm', 'association_id' => $stu->id, 'confirm' => md5($stu->id));
        $optionsno = array('s' => 'stu', 'section' => 'curr', 'id' => $stu->classid);

        echo cm_delete_form($url, $message, $optionsyes, $optionsno);
    }


    /**
     * override print_num_items to display the max number of students allowed in this class
     *
     * @param int $numitems max number of students
     */
    function print_num_items($classid, $max) {
        $pmclass = new pmclass($classid);
        $students = $pmclass->count_students_by_section($classid);

        if(!empty($students[STUSTATUS_FAILED])) {
            echo '<div style="float:right;">' . get_string('num_students_failed', self::LANG_FILE) . ': ' . $students[STUSTATUS_FAILED]->c . '</div><br />';
        }

        if(!empty($students[STUSTATUS_PASSED])) {
            echo '<div style="float:right;">' . get_string('num_students_passed', self::LANG_FILE) . ': ' . $students[STUSTATUS_PASSED]->c . '</div><br />';
        }

        if(!empty($students[STUSTATUS_NOTCOMPLETE])) {
            echo '<div style="float:right;">' . get_string('num_students_not_complete', self::LANG_FILE) . ': ' . $students[STUSTATUS_NOTCOMPLETE]->c . '</div><br />';
        }

        if(!empty($max)) {
            echo '<div style="float:right;">' . get_string('num_max_students',
                 self::LANG_FILE) . ': ' . $max . '</div><br />';
        }
    }
}

class student_table extends association_page_table {
    const LANG_FILE = 'elis_program';

    function __construct(&$items, $columns, $page) {
        $display_functions =
             array('enrolmenttime'    => 'get_item_display_enrolmenttime',
                   'completetime'     => 'get_item_display_completetime',
                   'completestatusid' => 'get_item_display_completestatusid',
                   'locked'           => 'get_item_display_locked',
                   'idnumber'         => 'get_item_display_idnumber',
                   'name'             => 'get_item_display_name',
                   'buttons'          => 'get_items_display_buttons');

        foreach ($display_functions as $key => $val) {
            if (isset($columns[$key]) && is_array($columns[$key])) {
                $columns[$key]['display_function'] = array(&$this, $val);
            }
        }
        parent::__construct($items, $columns, $page);
    }

    function get_item_display_enrolmenttime($column, $item) {
        return get_date_item_display($column, $item);
    }

    function get_item_display_completetime($column, $item) {
        if ($item->completestatusid == STUSTATUS_NOTCOMPLETE) {
            return '-';
        } else {
            return get_date_item_display($column, $item);
        }
    }

    function get_item_display_completestatusid($column, $id) {
        $status = student::$completestatusid_values[$id->$column];
        return get_string($status, self::LANG_FILE);
    }

    function get_item_display_locked($column, $id) {
        return get_yesno_item_display($column, $id);
    }

    function is_column_wrapped_idnumber() {
        return true;
    }

    function is_column_wrapped_name() {
        return true;
    }

    function get_item_display_idnumber($column, $item) {
        global $CFG, $USER;

        $usermanagementpage = new studentpage(); // TBD, WAS: usermanagementpage();
        if ($usermanagementpage->can_do_view()) {
            $target = $usermanagementpage->get_new_page(array('action' => 'view', 'id' => $item->userid));
            $link = $target->url;
            $elis_link_begin = '<a href="'.$link.'" alt="ELIS profile" title="ELIS profile">';
            $elis_link_end = '</a>';
        } else {
            $elis_link_begin = '';
            $elis_link_end = '';
        }

        return $elis_link_begin.$item->idnumber.$elis_link_end;
    }

    function get_item_display_name($column, $item) {
        global $CFG, $USER;

        if (has_capability('moodle/user:viewdetails', get_context_instance(CONTEXT_USER, $USER->id))) {
            $moodle_link_begin = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.cm_get_moodleuserid($item->userid).'" alt="Moodle profile" title="Moodle profile">';
            $moodle_link_end = ' <img src="'.$CFG->wwwroot.'/curriculum/pix/moodle.gif" alt="Moodle profile" title="Moodle profile" /></a>';
        } else {
            $moodle_link_begin = '';
            $moodle_link_end = '';
        }

        return $moodle_link_begin.$item->name.$moodle_link_end;
    }

    function get_item_display_buttons($column, $item) {
        return htmltab_display_function($column, $item);
    }

}

