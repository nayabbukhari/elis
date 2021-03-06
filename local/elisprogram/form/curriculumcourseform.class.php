<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    local_elisprogram
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

defined('MOODLE_INTERNAL') || die();

require_once elispm::file('form/cmform.class.php');
require_once elispm::lib('data/curriculumcourse.class.php');

/**
 * class for the course curriculum and curriculum course form that needs to be sublcassed
 */
class coursecurriculumbaseform extends cmform {

    private $timeperiod_values = array(
        'year'  => 'Years',  // Default
        'month' => 'Months',
        'week'  => 'Weeks',
        'day'   => 'Days'
    );

    public function body_definition() {
        print_error('Abstract body_definition() method in class '.get_class($this).' must be overriden, please fix the code.');
    }

    public function definition() {
        parent::definition();

        $mform =& $this->_form;

        $this->body_definition();

        $mform->addElement('advcheckbox', 'required', get_string('required', 'local_elisprogram'), null, null, array('0', '1'));
        $mform->addHelpButton('required', 'curriculumcourseform:required', 'local_elisprogram');

        $mform->addElement('text', 'frequency', get_string('frequency', 'local_elisprogram') . ':');
        $mform->setType('frequency', PARAM_INT);
        $mform->addRule('frequency', null, 'maxlength', 64, 'client');
        $mform->addHelpButton('frequency', 'curriculumcourseform:frequency', 'local_elisprogram');

        foreach ($this->timeperiod_values as $key => $val) {
            if (get_string_manager()->string_exists("time_period_{$key}",
                                                    'local_elisprogram')) {
                $this->timeperiod_values[$key] = get_string("time_period_{$key}", 'local_elisprogram');
            }
        }
        $mform->addElement('select', 'timeperiod', get_string('time_period', 'local_elisprogram') . ':', $this->timeperiod_values);
        $mform->addHelpButton('timeperiod', 'curriculumcourseform:time_period', 'local_elisprogram');

        $mform->addElement('text', 'position', get_string('curriculumcourse_position', 'local_elisprogram') . ':');
        $mform->setType('position', PARAM_INT);
        $mform->addHelpButton('position', 'curriculumcourseform:position', 'local_elisprogram');

        $this->add_action_buttons();
    }
}

/**
 * defines the course curriculum form
 */
class coursecurriculumform extends coursecurriculumbaseform {
    /**
     * should display header of ccform then the extra fields followed by footer of ccform
     */
    public function body_definition() {
        $mform =& $this->_form;

        $parent_obj = $this->_customdata['parent_obj'];

        $coursecurriculum = new curriculumcourse();
        $coursecurriculum->courseid = $parent_obj->id;

        if(isset($this->_customdata['obj'])) {
            $coursecurriculum = new curriculumcourse($this->_customdata['obj']);
            $course = $coursecurriculum->course;
            $curriculum = $coursecurriculum->curriculum;
            $curriculas[$curriculum->id] = $curriculum->name;
        } else {
            $contexts = curriculumpage::get_contexts('local/elisprogram:associate');
            $curricula_avail = $coursecurriculum->get_curricula_avail(array('contexts' => $contexts));
            $curriculas = array();

            if(is_array($curricula_avail)) {
                foreach($curricula_avail as $crsid=>$c) {
                    $curriculas[$crsid] = $c->name . ' (' . $c->idnumber . ')';
                }

                natcasesort($curriculas);
            }
        }

        $mform->addElement('select', 'curriculumid', get_string('curriculum', 'local_elisprogram') . ':', $curriculas);
        $mform->addRule('curriculumid', null, 'required', null, 'client');
        $mform->addHelpButton('curriculumid', 'curriculumcourseform:curriculum', 'local_elisprogram');

        $mform->addElement('hidden', 'courseid', $parent_obj->id);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'coursename', get_string('course', 'local_elisprogram') . ':', 'readonly="readonly"');
        $mform->setType('coursename', PARAM_TEXT);
        $mform->addHelpButton('coursename', 'curriculumcourseform:course', 'local_elisprogram');

        $this->set_data(array('coursename' => $parent_obj->name));
    }
}

/**
 * defines the curriculum course form
 */
class curriculumcourseform extends coursecurriculumbaseform {
    public function body_definition() {
        $mform =& $this->_form;

        $parent_obj = $this->_customdata['parent_obj'];

        $curriculumid = $parent_obj->id;

        $coursecurriculum = new curriculumcourse();
        $coursecurriculum->curriculumid = $curriculumid;

        $mform->addElement('hidden', 'curriculumid', $curriculumid);
        $mform->setType('curriculumid', PARAM_INT);

        $mform->addElement('text', 'curriculumname', get_string('curriculum', 'local_elisprogram') . ':', 'readonly="readonly"');
        $mform->setType('curriculumname', PARAM_TEXT);
        $mform->addHelpButton('curriculumname', 'curriculumcourseform:curriculum', 'local_elisprogram');

        $contexts = coursepage::get_contexts('local/elisprogram:associate');
        $courses_avail = $coursecurriculum->get_courses_avail(array('contexts' => $contexts));
        $courses = array();

        if(isset($this->_customdata['obj'])) {
            $coursecurriculum = new curriculumcourse($this->_customdata['obj']);
            $course = $coursecurriculum->course;
            $curriculum = $coursecurriculum->curriculum;
            $courses[$course->id] = $course->name;
        }
        else if(is_array($courses_avail)) {
            foreach($courses_avail as $crsid=>$c) {
                $courses[$crsid] = $c->name . ' (' . $c->idnumber . ')';
            }

            natcasesort($courses);
        }

        $mform->addElement('select', 'courseid', get_string('course', 'local_elisprogram') . ':', $courses);
        $mform->addRule('courseid', null, 'required', null, 'client');
        $mform->addHelpButton('courseid', 'curriculumcourseform:course', 'local_elisprogram');

        $this->set_data(array('curriculumname' => $parent_obj->name));
    }
}

class prerequisiteform extends cmform {
    public function definition() {
        parent::definition();

        $mform =& $this->_form;

        //config data setup
        //curriculumid, courseid, id, availablePrerequisites, existingPrerequisites
        $dataSet = array('curriculumid', 'courseid', 'id', 'availablePrerequisites', 'existingPrerequisites', 'association_id');
        foreach($dataSet as $d) {
            if(isset($this->_customdata[$d])) {
                $data->{$d} = $this->_customdata[$d];
            } else {
                $data->{$d} = null;
            }
        }

        //add elements
        $mform->addElement('hidden', 'curriculum', $data->curriculumid);
        $mform->setType('curriculum', PARAM_INT);

        $mform->addElement('hidden', 'course', $data->courseid);
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'association_id', $data->association_id);
        $mform->setType('association_id', PARAM_INT);

        $mform->addElement('header', 'curriculumcourseeditform', get_string('edit_course_prerequisites', 'local_elisprogram'));
        $mform->closeHeaderBefore('prerequisiteSave');

        $select =& $mform->addElement('select', 'prereqs', get_string('available_course_prerequisites', 'local_elisprogram'), $data->availablePrerequisites);
        $select->setMultiple(true);

        $select =& $mform->addElement('select', 'sprereqs', get_string('existing_course_prerequisites', 'local_elisprogram'), $data->existingPrerequisites);
        $select->setMultiple(true);

        $mform->addElement('checkbox', 'add_to_curriculum', get_string('add_prereq_to_curriculum', 'local_elisprogram'));

        $group = array();
        $group[] =& $mform->createElement('submit', 'add', get_string('add_prereq', 'local_elisprogram'));
        $group[] =& $mform->createElement('submit', 'remove', get_string('remove_prereq', 'local_elisprogram'));

        $mform->addGroup($group, 'submitbuttons', '', '', false);

        $mform->addElement('cancel', 'exit', get_string('exit', 'local_elisprogram'));
    }
}

class corequisiteform extends cmform {
    public function definition() {
        parent::definition();

        $mform =& $this->_form;

        //config data setup

        $dataSet = array('curriculumid', 'courseid', 'id', 'availableCorequisites', 'existingCorequisites', 'association_id');

        foreach($dataSet as $d) {
            if(isset($this->_customdata[$d])){
                $data->{$d} = $this->_customdata[$d];
            } else {
                $data->{$d} = null;
            }
        }

        //add elements
        $mform->addElement('hidden', 'curriculum');
        $mform->setType('curriculum', PARAM_INT);

        $mform->addElement('hidden', 'course');
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'association_id', $data->association_id);
        $mform->setType('association_id', PARAM_INT);

        $mform->addElement('header', 'curriculumcourseeditform', get_string('edit_course_corequisites', 'local_elisprogram'));

        $select =& $mform->addElement('select', 'coreqs', get_string('available_course_corequisites', 'local_elisprogram'), $data->availableCorequisites);
        $select->setMultiple(true);

        $select =& $mform->addElement('select', 'scoreqs', get_string('existing_course_corequisites', 'local_elisprogram'), $data->existingCorequisites);
        $select->setMultiple(true);

        $mform->addElement('checkbox', 'add_to_curriculum', get_string('add_coreq_to_curriculum', 'local_elisprogram'));

        $group = array();
        $group[] =& $mform->createElement('submit', 'add', get_string('add_coreq', 'local_elisprogram'));
        $group[] =& $mform->createElement('submit', 'remove', get_string('remove_coreq', 'local_elisprogram'));

        $mform->addGroup($group, 'submitbuttons', '', '', false);

        $mform->addElement('cancel', 'exit', get_string('exit', 'local_elisprogram'));
    }
}
