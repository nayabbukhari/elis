<?php

require_once(dirname(__FILE__) .'/../../../../config.php');
global $CFG, $OUTPUT, $PAGE, $USER;

require_once("{$CFG->dirroot}/repository/elis_files/lib.php");
require_once($CFG->dirroot .'/lib/formslib.php');
require_once('./alfresco_filemanager.php');

class alfreso_test_form extends moodleform {
    var $afm_elem = null;

    function definition() {
        global $DB, $USER;
        $mform = & $this->_form;
        $ret = array('locations' => array('course' => 'course'));
        $sql = 'SELECT i.name, i.typeid, r.type FROM {repository} r, {repository_instances} i WHERE r.type=? AND i.typeid=r.id';
        $repository = $DB->get_record_sql($sql, array('elis_files'));
        if ($repository) {
            try {
                $repo = new repository_elis_files('elis_files', get_context_instance(CONTEXT_USER, $USER->id), array(
                    'ajax' => false,
                    'name' => $repository->name,
                    'type' => 'elis_files')
                );
                if (!empty($repo)) {
                    $ret = $repo->get_listing();
                }
            } catch (Exception $e) {
                $repo = null;
           }
        }
        ob_start();
        var_dump($ret);
        $tmp = ob_get_contents();
        ob_end_clean();
        error_log("alfresco_filemanager::test_form:: ret = {$tmp}");

        $fm_options = array('maxfiles'   => -1,
                            'maxbytes'   => 1000000000,
                            'sesskey'    => sesskey(),
                            'locations'  => $ret['locations'] // TBD
                      );
        $attrs = null; // TBD
        $this->afm_elem = $mform->createElement('alfresco_filemanager',
                             'files_filemanager',
                          // NOTE: ^^^ element name MUST be 'files_filemanager'
                             '<b>Alfresco File Manager Form Element</b>',
                             $attrs, array_merge($fm_options, $this->_customdata['options']));
        $mform->addElement($this->afm_elem);

        $mform->addElement('hidden', 'returnurl', $this->_customdata['data']->returnurl);

        $this->add_action_buttons(true, get_string('savechanges'));

        $this->set_data($this->_customdata['data']);
    }

}

$context = get_context_instance(CONTEXT_USER, $USER->id);
$PAGE->set_context($context);
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_pagetype('user-files');
$PAGE->set_heading('<b>Alfresco File Manager</b>');
$PAGE->set_url('/repository/elis_files/lib/form/testform.php');

$data = new stdClass;
$data->returnurl = new moodle_url('/repository/elis_files/lib/form/testform.php');
$options = array('subdirs'=>1, 'maxbytes'=>$CFG->userquota, 'maxfiles'=>-1, 'accepted_types'=>'*');
$data = file_prepare_standard_filemanager($data, 'files', $options, $context, 'user', 'private', 0);

$form = new alfreso_test_form($data->returnurl,
                              array('data' => $data, 'options' => $options));

if ($form->is_cancelled()) {
    redirect($data->returnurl);
} else if ($formdata = $form->get_data()) {
    $formdata = file_postupdate_standard_filemanager($formdata, 'files', $options, $context, 'user', 'private', 0);
    redirect($data->returnurl);
}
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();

