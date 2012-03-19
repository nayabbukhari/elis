<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once($CFG->dirroot.'/blocks/rlip/rlip_importplugin.class.php');

/**
 * Original Moodle-only import
 */
class rlip_importplugin_version1 extends rlip_importplugin_base {
    //required field definition
    static $import_fields_user_create = array('username',
                                              'password',
                                              'firstname',
                                              'lastname',
                                              'email',
                                              'city',
                                              'country');
    static $import_fields_user_add = array('username',
                                           'password',
                                           'firstname',
                                           'lastname',
                                           'email',
                                           'city',
                                           'country');
    static $import_fields_user_update = array(array('username',
                                                    'email',
                                                    'idnumber'));
    static $import_fields_user_delete = array(array('username',
                                                    'email',
                                                    'idnumber'));
    static $import_fields_user_disable = array(array('username',
                                                     'email',
                                                     'idnumber'));

    static $import_fields_course_create = array('shortname',
                                                'fullname',
                                                'category');
    static $import_fields_course_update = array('shortname');
    static $import_fields_course_delete = array('shortname');

    static $import_fields_enrolment_create = array(array('username',
                                                         'email',
                                                         'idnumber'),
                                                   'context',
                                                   'instance',
                                                   'role');
    static $import_fields_enrolment_add = array(array('username',
                                                      'email',
                                                      'idnumber'),
                                                'context',
                                                'instance',
                                                'role');
    static $import_fields_enrolment_delete = array(array('username',
                                                        'email',
                                                        'idnumber'),
                                                  'context',
                                                  'instance',
                                                  'role');

    /**
     * Hook run after a file header is read
     *
     * @param string $entity The type of entity
     * @param array $header The header record
     */
    function header_read_hook($entity, $header) {
        global $DB;

        if ($entity !== 'user') {
            return;
        }

        $this->fields = array();

        foreach ($header as $column) {
            if (strpos($column, 'profile_field_') === 0) {
                $shortname = substr($column, strlen('profile_field_'));
                $this->fields[$shortname] = $DB->get_record('user_info_field', array('shortname' => $shortname));
            }
        }
    }

    /**
     * Checks a field's data is one of the specified values
     * @todo: consider moving this because it's fairly generalized
     *
     * @param object $record The record containing the data to validate,
                             and possibly modify if $stringvalues used.
     * @param string $property The field / property to check
     * @param array $list The valid possible values
     * @param array $stringvalues associative array of strings to map back to
     *                            $list value. Eg. array('no' => 0, 'yes' => 1)
     */
    function validate_fixed_list(&$record, $property, $list, $stringvalues = null) {
        //note: do not worry about missing fields here
        if (isset($record->$property)) {
            if (is_array($stringvalues) && isset($stringvalues[$record->$property])) {
                $record->$property = (string)$stringvalues[$record->$property];
            }
            // CANNOT use in_array() 'cause types don't match ...
            // AND PHP::in_array('yes', array(0, 1)) == true ???
            foreach ($list as $entry) {
                if ((string)$record->$property == (string)$entry) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    /**
     * Converts a date in MMM/DD/YYYY format
     * to a unix timestamp
     * @todo: consider further generalizing / moving to base class
     *
     * @param string $date Date in MMM/DD/YYYY format
     * @return mixed The unix timestamp, or false if date is
     *               not in the right format
     */
    function parse_date($date) {
        //make sure there are three parts
        $parts = explode('/', $date);
        if (count($parts) != 3) {
            return false;
        }

        //make sure the month is valid
        $month = $parts[0];
        $day = $parts[1];
        $year = $parts[2];
        $months = array('jan', 'feb', 'mar', 'apr',
                        'may', 'jun', 'jul', 'aug',
                        'sep', 'oct', 'nov', 'dec');
        $pos = array_search(strtolower($month), $months);
        if ($pos === false) {
            //invalid month
            return false;
        }

        //make sure the combination of date components is valid
        $month = $pos + 1;
        $day = (int)$day;
        $year = (int)$year;
        if (!checkdate($month, $day, $year)) {
            //invalid combination of month, day and year
            return false;
        }

        //return unix timestamp
        return mktime(0, 0, 0, $month, $day, $year);
    }

    /**
     * Remove invalid fields from a user record
     * @todo: consider generalizing this
     *
     * @param object $record The user record
     * @return object The user record with the invalid fields removed
     */
    function remove_invalid_user_fields($record) {
        $allowed_fields = array('entity', 'action', 'username', 'auth',
                                'password', 'firstname', 'lastname', 'email',
                                'maildigest', 'autosubscribe', 'trackforums',
                                'screenreader', 'city', 'country', 'timezone',
                                'theme', 'lang', 'description', 'idnumber',
                                'institution', 'department');
        foreach ($record as $key => $value) {
            if (!in_array($key, $allowed_fields) && strpos($key, 'profile_field_') !== 0) {
                unset($record->$key);
            }
        }

        return $record;
    }

    /**
     * Check the lengths of fields from a user record
     * @todo: consider generalizing
     *
     * @param object $record The user record
     * @return boolean True if field lengths are ok, otherwise false
     */
    function check_user_field_lengths($record) {
        $lengths = array('firstname' => 100,
                         'lastname' => 100,
                         'email' => 100,
                         'city' => 120,
                         'idnumber' => 255,
                         'institution' => 40,
                         'department' => 30);

        foreach ($lengths as $field => $length) {
            //note: do not worry about missing fields here
            if (isset($record->$field)) {
                if (strlen($record->$field) > $length) {
                    return false;
                }
            }
        }

        //no problems found
        return true;
    }

    /**
     * Performs any necessary conversion of the action value based on the
     * "createorupdate" setting
     *
     * @param object $record One record of import data
     * @param string $action The supplied action
     * @return string The action to use in the import
     */
    function handle_user_createorupdate($record, $action) {
        global $CFG, $DB;

        //check config setting
        $createorupdate = get_config('rlipimport_version1', 'createorupdate');

        if (!empty($createorupdate)) {
            if (isset($record->username) || isset($record->record->email) || isset($record->idnumber)) {
                //identify the user
                $params = array();
                if (isset($record->username)) {
                    $params['username'] = $record->username;
                    $params['mnethostid'] = $CFG->mnet_localhost_id;
                }
                if (isset($record->email)) {
                    $params['email'] = $record->email;
                }
                if (isset($record->idnumber)) {
                    $params['idnumber'] = $record->idnumber;
                }

                if ($DB->record_exists('user', $params)) {
                    //user exists, so the action is an update
                    $action = 'update';
                } else {
                    //user does not exist, so the action is a create
                    $action = 'create';
                }
            } else {
                $action = 'create';
            }
        }

        return $action;
    }

    /**
     * Removes fields equal to the empty string from the provided record
     *
     * @param object $record The import record
     * @return object A version of the import record, with all empty fields removed
     */
    function remove_empty_fields($record) {
        foreach ($record as $key => $value) {
            if ($value === '') {
                unset($record->$key);
            }
        }

        return $record;
    }

    /**
     * Calculates a string that specifies which fields can be used to identify
     * a user record based on the import record provided
     *
     * @param object $record
     * @param boolean $value_syntax true if we want to use "field" value of
     *                              "value" syntax, otherwise use field "value"
     *                              syntax
     * @param boolean $quotes use quotes in message if true
     * @return string The description of identifying fields, as a
     *                comma-separated string
     * [field1] "value1", ...
     */
    function get_user_descriptor($record, $value_syntax = false, $quotes = true) {
        $fragments = array();
        //quote character
        $quote = $quotes ? '"' : '';

        //the fields we care to check
        $possible_fields = array('username',
                                 'email',
                                 'idnumber');

        foreach ($possible_fields as $field) {
            if (isset($record->$field)) {
                //data for that field
                $value = $record->$field;

                //calculate syntax fragment
                if ($value_syntax) {
                    $fragments[] = "{$quote}{$field}{$quote} value of {$quote}{$value}{$quote}";
                } else {
                    $fragments[] = "{$field} {$quote}{$value}{$quote}";
                }
            }
        }

        //combine into string
        return implode(', ', $fragments);
    }

    /**
     * Calculates a string that specifies a descriptor for a context instance
     *
     * @param object $record The object specifying the context and instance
     * @param boolean $quotes use quotes in message if true
     * @return string The descriptive string
     */
    function get_context_descriptor($record, $quotes = true) {
        //quote character
        $quote = $quotes ? '"' : '';

        if ($record->context == 'system') {
            //no instance for the system context
            $context_descriptor = 'the system context';
        } else if ($record->context == 'coursecat') {
            //convert "coursecat" to "course category" due to legacy 1.9 weirdness
            $context_descriptor = "course category {$quote}{$record->instance}{$quote}";
        } else {
            //standard case
            $context_descriptor = "{$record->context} {$quote}{$record->instance}{$quote}";
        }

        return $context_descriptor;
    }

    /**
     * Delegate processing of an import line for entity type "user"
     *
     * @param object $record One record of import data
     * @param string $action The action to perform, or use data's action if
     *                       not supplied
     * @param string $filename The import file name, used for logging
     *
     * @return boolean true on success, otherwise false
     */
    function user_action($record, $action = '', $filename = '') {
        if ($action === '') {
            //set from param
            $action = $record->action;
        }

        if (!$this->check_action_field($record, $filename)) {
            //missing an action value
            return false;
        }

        //apply "createorupdate" flag, if necessary
        $action = $this->handle_user_createorupdate($record, $action);
        $record->action = $action;

        if (!$this->check_required_fields('user', $record, $filename)) {
            //missing a required field
            return false;
        }

        //remove empty fields
        $record = $this->remove_empty_fields($record);

        //perform action
        $method = "user_{$action}";
        return $this->$method($record, $filename);
    }

    /**
     * Validates that core user fields are set to valid values, if they are set
     * on the import record
     *
     * @param string $action One of 'create' or 'update'
     * @param object $record The import record
     *
     * @return boolean true if the record validates correctly, otherwise false
     */
    function validate_core_user_data($action, $record) {
        global $CFG;

        //make sure auth plugin refers to a valid plugin
        $auths = get_plugin_list('auth');
        if (!$this->validate_fixed_list($record, 'auth', array_keys($auths))) {
            $this->fslogger->log("\"auth\" values of {$record->auth} is not a valid auth plugin.");
            return false;
        }

        //make sure password satisfies the site password policy
        if (isset($record->password)) {
            $errmsg = '';
            if (!check_password_policy($record->password, $errmsg)) {
                $this->fslogger->log("\"password\" value of {$record->password} does not conform to your site's password policy.");
                return false;
            }
        }

        //make sure email is in user@domain.ext format
        if ($action == 'create') {
            if (!validate_email($record->email)) {
                $this->fslogger->log("\"email\" value of {$record->email} is not a valid email address.");
                return false;
            }
        }

        //make sure maildigest is one of the available values
        if (!$this->validate_fixed_list($record, 'maildigest', array(0, 1, 2))) {
            $this->fslogger->log("\"maildigest\" value of {$record->maildigest} is not one of the available options (0, 1, 2).");
            return false;
        }

        //make sure autosubscribe is one of the available values
        if (!$this->validate_fixed_list($record, 'autosubscribe', array(0, 1),
                                        array('no' => 0, 'yes' => 1))) {
            $this->fslogger->log("\"autosubscribe\" value of {$record->autosubscribe} is not one of the available options (0, 1).");
            return false;
        }

        //make sure trackforums can only be set if feature is enabled
        if (isset($record->trackforums)) {
            if (empty($CFG->forum_trackreadposts)) {
                $this->fslogger->log("Tracking unread posts is currently disabled on this site.");
                return false;
            }
        }

        //make sure trackforums is one of the available values
        if (!$this->validate_fixed_list($record, 'trackforums', array(0, 1),
                                        array('no' => 0, 'yes' => 1))) {
            $this->fslogger->log("\"trackforums\" value of {$record->trackforums} is not one of the available options (0, 1).");
            return false;
        }

        //make sure screenreader is one of the available values
        if (!$this->validate_fixed_list($record, 'screenreader', array(0, 1),
                                        array('no' => 0, 'yes' => 1))) {
            $this->fslogger->log("\"screenreader\" value of {$record->screenreader} is not one of the available options (0, 1).");
            return false;
        }

        //make sure country refers to a valid country code
        $countries = get_string_manager()->get_list_of_countries();
        if (!$this->validate_fixed_list($record, 'country', array_keys($countries))) {
            $this->fslogger->log("\"country\" value of {$record->country} is not a valid country code.");
            return false;
        }

        //make sure timezone can only be set if feature is enabled
        if (isset($record->timezone)) {
            if ($CFG->forcetimezone != 99 && $record->timezone != $CFG->forcetimezone) {
                $this->fslogger->log("\"timezone\" value of {$record->timezone} is not consistent with forced timezone value of {$CFG->forcetimezone} on your site.");
                return false;
            }
        }

        //make sure timezone refers to a valid timezone offset
        $timezones = get_list_of_timezones();
        if (!$this->validate_fixed_list($record, 'timezone', array_keys($timezones))) {
            $this->fslogger->log("\"timezone\" value of {$record->timezone} is not a valid timezone.");
            return false;
        }

        //make sure theme can only be set if feature is enabled
        if (isset($record->theme)) {
            if (empty($CFG->allowuserthemes)) {
                $this->fslogger->log("User themes are currently disabled on this site.");
                return false;
            }
        }

        //make sure theme refers to a valid theme
        $themes = get_list_of_themes();
        if (!$this->validate_fixed_list($record, 'theme', array_keys($themes))) {
            $this->fslogger->log("\"theme\" value of {$record->theme} is invalid.");
            return false;
        }

        //make sure lang refers to a valid language
        $languages = get_string_manager()->get_list_of_translations();
        if (!$this->validate_fixed_list($record, 'lang', array_keys($languages))) {
            $this->fslogger->log("\"lang\" value of {$record->lang} is not a valid language code.");
            return false;
        }

        return true;
    }

    /**
     * Validates user profile field data and performs any required
     * data transformation in-place
     *
     * @param object $record The import record
     *
     * @return boolean true if the record validates, otherwise false
     */
    function validate_user_profile_data($record) {
        //go through each profile field in the header
        foreach ($this->fields as $shortname => $field) {
            $key = 'profile_field_'.$shortname;
            $data = $record->$key;

            //perform type-specific validation and transformation
            if ($field->datatype == 'checkbox') {
                if ($data != 0 && $data != 1) {
                    $this->fslogger->log("\"{$key}\" is not one of the available options for a checkbox profile field {$shortname} (0, 1).");
                    return false;
                }
            } else if ($field->datatype == 'menu') {
                $options = explode("\n", $field->param1);
                if (!in_array($data, $options)) {
                    $this->fslogger->log("\"{$key}\" is not one of the available options for a menu of choices profile field {$shortname}.");
                    return false;
                }
            } else if ($field->datatype == 'datetime') {
                $value = $this->parse_date($data);
                if ($value === false) {
                    return false;
                }

                $record->$key = $value;
            }
        }

        return true;
    }

    /**
     * Create a user
     * @todo: consider factoring this some more once other actions exist
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function user_create($record, $filename) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/user/lib.php');
        require_once($CFG->dirroot.'/user/profile/lib.php');

        //remove invalid fields
        $record = $this->remove_invalid_user_fields($record);

        //field length checking
        $lengthcheck = $this->check_user_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        //data checking
        if (!$this->validate_core_user_data('create', $record)) {
            return false;
        }

        //profile field validation
        if (!$this->validate_user_profile_data($record)) {
            return false;
        }

        //uniqueness checks
        if ($DB->record_exists('user', array('username' => $record->username,
                                             'mnethostid'=> $CFG->mnet_localhost_id))) {
            return false;
        }

        if ($DB->record_exists('user', array('email' => $record->email))) {
            return false;
        }

        if (isset($record->idnumber)) {
            if ($DB->record_exists('user', array('idnumber' => $record->idnumber))) {
                return false;
            }
        }

        //final data sanitization
        if (!isset($record->description)) {
            $record->description = '';
        }

        if (!isset($record->lang)) {
            $record->lang = $CFG->lang;
        }

        //write to the database
        $record->descriptionformat = FORMAT_HTML;
        $record->mnethostid = $CFG->mnet_localhost_id;
        $record->password = hash_internal_user_password($record->password);
        $record->timecreated = time();
        $record->timemodified = $record->timecreated;
        //make sure the user is confirmed!
        $record->confirmed = 1;

        $record->id = $DB->insert_record('user', $record);

        get_context_instance(CONTEXT_USER, $record->id);

        profile_save_data($record);

        //sync to PM is necessary
        $user = $DB->get_record('user', array('id' => $record->id));
        events_trigger('user_created', $user);

        //string to describe the user
        $user_descriptor = $this->get_user_descriptor($record);

        //log success
        $this->fslogger->log("[{$filename} line {$this->linenumber}] User with {$user_descriptor} successfully created.");

        return true;
    }

    /**
     * Add a user
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function user_add($record, $filename) {
        //note: this is only here due to legacy 1.9 weirdness
        return $this->user_create($record, $filename);
    }

    /**
     * Update a user
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function user_update($record, $filename) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/user/lib.php');
        require_once($CFG->dirroot.'/user/profile/lib.php');

        //remove invalid fields
        $record = $this->remove_invalid_user_fields($record);

        //field length checking
        $lengthcheck = $this->check_user_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        //data checking
        if (!$this->validate_core_user_data('update', $record)) {
            return false;
        }

        //profile field validation
        if (!$this->validate_user_profile_data($record)) {
            return false;
        }

        //find existing user record
        $params = array();
        if (isset($record->username)) {
            $params['username'] = $record->username;
            $updateusername = $DB->get_record('user', array('username' => $params['username']));
            if(!$updateusername) {
                $this->fslogger->log("\"username\" value of {$params['username']} does not refer to a valid user.");
                return false;
            }
        }

        if (isset($record->email)) {
            $params['email'] = $record->email;
            $updateemail = $DB->get_record('user', array('email' => $params['email']));
            if(!$updateemail) {
                $this->fslogger->log("\"email\" value of {$params['email']} does not refer to a valid user.");
                return false;
            }
        }

        if (isset($record->idnumber)) {
            $params['idnumber'] = $record->idnumber;
            $updateidnumber = $DB->get_record('user', array('idnumber' => $params['idnumber']));
            if(!$updateidnumber) {
                $this->fslogger->log("\"idnumber\" value of {$params['idnumber']} does not refer to a valid user.");
                return false;
            }
        }

        $record->id = $DB->get_field('user', 'id', $params);
        if (empty($record->id)) {
            return false;
        }

        //write to the database

        //taken from user_update_user
        // hash the password
        if (isset($record->password)) {
            $record->password = hash_internal_user_password($record->password);
        }

        $record->timemodified = time();
        $DB->update_record('user', $record);

        profile_save_data($record);

        // trigger user_updated event on the full database user row
        $updateduser = $DB->get_record('user', array('id' => $record->id));
        events_trigger('user_updated', $updateduser);

        //string to describe the user
        $user_descriptor = $this->get_user_descriptor($record);

        //log success
        $this->fslogger->log("[{$filename} line {$this->linenumber}] User with {$user_descriptor} successfully updated.");

        return true;
    }

    /**
     * Delete a user
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function user_delete($record, $filename) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/user/lib.php');

        //find existing user record
        $params = array();
        if (isset($record->username)) {
            $params['username'] = $record->username;
            $updateusername = $DB->get_record('user', array('username' => $params['username']));
            if(!$updateusername) {
                $this->fslogger->log("\"username\" value of {$params['username']} does not refer to a valid user.");
                return false;
            }
        }

        if (isset($record->email)) {
            $params['email'] = $record->email;
            $updateusername = $DB->get_record('user', array('email' => $params['email']));
            if(!$updateusername) {
                $this->fslogger->log("\"email\" value of {$params['email']} does not refer to a valid user.");
                return false;
            }
        }

        if (isset($record->idnumber)) {
            $params['idnumber'] = $record->idnumber;
            $updateusername = $DB->get_record('user', array('idnumber' => $params['idnumber']));
            if(!$updateusername) {
                $this->fslogger->log("\"idnumber\" value of {$params['idnumber']} does not refer to a valid user.");
                return false;
            }
        }

        //make the appropriate changes
        if ($user = $DB->get_record('user', $params)) {
            user_delete_user($user);

            //string to describe the user
            $user_descriptor = $this->get_user_descriptor($record);

            //log success
            $this->fslogger->log("[{$filename} line {$this->linenumber}] User with {$user_descriptor} successfully deleted.");

            return true;
        }

        return false;
    }

    /**
     * Create a user
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function user_disable($record, $filename) {
        //note: this is only here due to legacy 1.9 weirdness
        return $this->user_delete($record);
    }

    /**
     * Performs any necessary conversion of the action value based on the
     * "createorupdate" setting
     *
     * @param object $record One record of import data
     * @param string $action The supplied action
     * @return string The action to use in the import
     */
    function handle_course_createorupdate($record, $action) {
        global $DB;

        //check config setting
        $createorupdate = get_config('rlipimport_version1', 'createorupdate');

        if (!empty($createorupdate)) {
            if (isset($record->shortname)) {
                //identify the course
                if ($DB->record_exists('course', array('shortname' => $record->shortname))) {
                    //course exists, so the action is an update
                    $action = 'update';
                } else {
                    //course does not exist, so the action is a create
                    $action = 'create';
                }
            } else {
                $action = 'create';
            }
        }

        return $action;
    }

    /**
     * Delegate processing of an import line for entity type "course"
     *
     * @param object $record One record of import data
     * @param string $action The action to perform, or use data's action if
     *                       not supplied
     * @param string $filename The import file name, used for logging
     *
     * @return boolean true on success, otherwise false
     */
    function course_action($record, $action = '', $filename = '') {
        if ($action === '') {
            //set from param
            $action = $record->action;
        }

        if (!$this->check_action_field($record, $filename)) {
            //missing an action value
            return false;
        }

        //apply "createorupdate" flag, if necessary
        $action = $this->handle_course_createorupdate($record, $action);

        $record->action = $action;
        if (!$this->check_required_fields('course', $record, $filename)) {
            //missing a required field
            return false;
        }

        //remove empty fields
        $record = $this->remove_empty_fields($record);

        //perform action
        $method = "course_{$action}";
        return $this->$method($record, $filename);
    }

    /**
     * Remove invalid fields from a course record
     * @todo: consider generalizing this
     *
     * @param object $record The course record
     * @return object The course record with the invalid fields removed
     */
    function remove_invalid_course_fields($record) {
        $allowed_fields = array('entity', 'action','shortname', 'fullname',
                                'idnumber', 'summary', 'format', 'numsections',
                                'startdate', 'newsitems', 'showgrades', 'showreports',
                                'maxbytes', 'guest', 'password', 'visible',
                                'lang', 'category', 'link');
        foreach ($record as $key => $value) {
            if (!in_array($key, $allowed_fields)) {
                unset($record->$key);
            }
        }

        return $record;
    }

    /**
     * Check the lengths of fields from a course record
     * @todo: consider generalizing
     *
     * @param object $record The course record
     * @return boolean True if field lengths are ok, otherwise false
     */
    function check_course_field_lengths($record) {
        $lengths = array('fullname' => 254,
                         'shortname' => 100,
                         'idnumber' => 100);

        foreach ($lengths as $field => $length) {
            //note: do not worry about missing fields here
            if (isset($record->$field)) {
                if (strlen($record->$field) > $length) {
                    return false;
                }
            }
        }

        //no problems found
        return true;
    }

    /**
     * Intelligently splits a category specification into a list of categories
     *
     * @param string $category_string  The category specification string, using
     *                                 \\\\ to represent \, \\/ to represent /,
     *                                 and / as a category separator
     * @return array An array with one entry per category, containing the
     *               unescaped category names
     */
    function get_category_path($category_string) {
        //in-progress method result
        $result = array();

        //used to build up the current token before splitting
        $current_token = '';

        //tracks which token we are currently looking at
        $current_token_num = 0;

        for ($i = 0; $i < strlen($category_string); $i++) {
            //initialize the entry if necessary
            if (!isset($result[$current_token_num])) {
                $result[$current_token_num] = '';
            }

            //get the ith character from the category string
            $current_token .= substr($category_string, $i, 1);

            if(strpos($current_token, '\\\\') === strlen($current_token) - strlen('\\\\')) {
                //backslash character

                //append the result
                $result[$current_token_num] .= substr($current_token, 0, strlen($current_token) - strlen('\\\\')) . '\\';
                //reset the token
                $current_token = '';
            } else if(strpos($current_token, '\\/') === strlen($current_token) - strlen('\\/')) {
                //forward slash character

                //append the result
                $result[$current_token_num] .= substr($current_token, 0, strlen($current_token) - strlen('\\/')) . '/';
                //reset the token so that the / is not accidentally counted as a category separator
                $current_token = '';
            } else if(strpos($current_token, '/') === strlen($current_token) - strlen('/')) {
                //category separator

                //append the result
                $result[$current_token_num] .= substr($current_token, 0, strlen($current_token) - strlen('/'));
                //reset the token
                $current_token = '';
                //move on to the next token
                $current_token_num++;
            }
        }

        //append leftovers after the last slash

        //initialize the entry if necessary
        if (!isset($result[$current_token_num])) {
            $result[$current_token_num] = '';
        }

        $result[$current_token_num] .= $current_token;

        return $result;
    }

    /**
     * Map the specified category to a record id
     *
     * @param string $category_string The category specification string, using
     *                                \\\\ to represent \, \\/ to represent /,
     *                                and / as a category separator
     * @return mixed Returns false on error, or the integer category id otherwise
     */
    function get_category_id($category_string) {
        global $DB;

        $parentids = array();

        //check for a leading / for the case where an absolute path is specified
        if (strpos($category_string, '/') === 0) {
            $category_string = substr($category_string, 1);
            $parentids[] = 0;
        }

        //split the category string into a list of categories
        $path = $this->get_category_path($category_string);

        foreach ($path as $categoryname) {
            //look for categories with the correct name
            $select = "name = ?";
            $params = array($categoryname);

            if (!empty($parentids)) {
                //only allow categories that also are children of categories
                //found in the last iteration of the specified path
                list($parentselect, $parentparams) = $DB->get_in_or_equal($parentids);
                $select = "{$select} AND parent {$parentselect}";
                $params = array_merge($params, $parentparams);
            }

            //find matching records
            if ($records = $DB->get_recordset_select('course_categories', $select, $params)) {
                if (!$records->valid()) {
                    //none found, so try see if the id was specified
                    if (is_numeric($category_string)) {
                        if ($DB->record_exists('course_categories', array('id' => $category_string))) {
                            return $category_string;
                        }
                    }

                    $parent = 0;
                    if (count($parentids) == 1) {
                        //we have a specific parent to create a child for
                        $parent = $parentids[0];
                    } else if (count($parentids) > 0) {
                        //ambiguous parent, so we can't continue
                        return false;
                    }

                    //create a new category
                    $newcategory = new stdClass;
                    $newcategory->name = $categoryname;
                    $newcategory->parent = $parent;
                    $newcategory->id = $DB->insert_record('course_categories', $newcategory);

                    //set "parent ids" to the new category id
                    $parentids = array($newcategory->id);
                } else {
                    //set "parent ids" to the current result set for our next iteration
                    $parentids = array();

                    foreach ($records as $record) {
                        $parentids[] = $record->id;
                    }
                }
            }
        }

        if (count($parentids) == 1) {
            //found our category
            return $parentids[0];
        } else {
            //path refers to multiple potential categories
            $this->fslogger->log("\"category\" value of {$categorystring} refers to multiple categories.");
            return false;
        }
    }

    /**
     * Validates that core course fields are set to valid values, if they are set
     * on the import record
     *
     * @param string $action One of 'create' or 'update'
     * @param object $record The import record
     *
     * @return boolean true if the record validates correctly, otherwise false
     */
    function validate_core_course_data($action, $record) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/enrollib.php');

        //make sure format refers to a valid course format
        if (isset($record->format)) {
            $courseformats = get_plugin_list('format');

            if (!$this->validate_fixed_list($record, 'format', array_keys($courseformats))) {
                $this->fslogger->log("\"format\" value does not refer to a valid course format.");
                return false;
            }
        }

        //make sure numsections is an integer between 0 and the configured max
        if (isset($record->numsections)) {
            $maxsections = (int)get_config('moodlecourse', 'maxsections');

            if ((int)$record->numsections != $record->numsections) {
                //not an integer
                return false;
            }

            $record->numsections = (int)$record->numsections;
            if ($record->numsections < 0 || $record->numsections > $maxsections) {
                $this->fslogger->log("\"numsections\" value of {$record->numsections} is not one of the available options (0 .. {$maxsections}).");
                //not between 0 and max
                return false;
            }
        }

        //make sure startdate is a valid date
        if (isset($record->startdate)) {
            $value = $this->parse_date($record->startdate);
            if ($value === false) {
                $this->fslogger->log("\"startdate\" value of {$record->startdate} is not a valid date in MMM/DD/YYYY format.");
                return false;
            }

            //use the unix timestamp
            $record->startdate = $value;
        }

        //make sure newsitems is an integer between 0 and 10
        $options = range(0, 10);
        if (!$this->validate_fixed_list($record, 'newsitems', $options)) {
            $this->fslogger->log("\"newsitems\" value of {$record->newsitems} is not one of the available options (0 .. 10).");
            return false;
        }

        //make sure showgrades is one of the available values
        if (!$this->validate_fixed_list($record, 'showgrades', array(0, 1),
                                        array('no' => 0, 'yes' => 1))) {
            $this->fslogger->log("\"showgrades\" value of {$record->showgrades} is not one of the available options (0, 1).");
            return false;
        }

        //make sure showreports is one of the available values
        if (!$this->validate_fixed_list($record, 'showreports', array(0, 1),
                                        array('no' => 0, 'yes' => 1))) {
            $this->fslogger->log("\"showreports\" value of {$record->showreports} is not one of the available options (0, 1).");
            return false;
        }

        //make sure maxbytes is one of the available values
        if (isset($record->maxbytes)) {
            $choices = get_max_upload_sizes($CFG->maxbytes);
            if (!$this->validate_fixed_list($record, 'maxbytes', array_keys($choices))) {
                $this->fslogger->log("\"maxbytes\" value of {$record->maxbytes} is not one of the available options.");
                return false;
            }
        }

        //make sure guest is one of the available values
        if (!$this->validate_fixed_list($record, 'guest', array(0, 1),
                                        array('no' => 0, 'yes' => 1))) {
            $this->fslogger->log("\"guest\" value of {$record->guest} is not one of the available options (0, 1).");
            return false;
        }

        //make sure visible is one of the available values
        if (!$this->validate_fixed_list($record, 'visible', array(0, 1),
                                        array('no' => 0, 'yes' => 1))) {
            $this->fslogger->log("\"visible\" value of {$record->visible} is not one of the available options (0, 1).");
            return false;
        }

        //make sure lang refers to a valid language or the default value
        $languages = get_string_manager()->get_list_of_translations();
        $language_codes = array_merge(array(''), array_keys($languages));
        if (!$this->validate_fixed_list($record, 'lang', $language_codes)) {
            $this->fslogger->log("\"lang\" value of {$record->lang} is not a valid language code.");
            return false;
        }

        //determine if this plugin is even enabled
        $enabled = explode(',', $CFG->enrol_plugins_enabled);
        if (!in_array('guest', $enabled) && !empty($record->guest)) {
            $this->fslogger->log("\"guest\" enrolments cannot be enabled because the guest enrolment plugin is globally disabled.");
            return false;
        }

        if ($action == 'create') {
            //make sure "guest" settings are consistent for new course
            if (isset($record->guest) && empty($record->guest) && !empty($record->password)) {
                //password set but guest is not enabled
                return false;
            }

            $defaultenrol = get_config('enrol_guest', 'defaultenrol');
            if (empty($defaultenrol) && (!empty($record->guest) || !empty($record->password))) {
                //enabling guest access without the guest plugin being added by default
                return false;
            }

            //make sure we don't have a course "link" (template) that refers to
            //an invalid course shortname
            if (isset($record->link)) {
                if (!$DB->record_exists('course', array('shortname' => $record->link))) {
                    return false;
                }
            }
        }

        if ($action == 'update') {
            //make sure "guest" settings are consistent for new course

            if (isset($record->guest) || isset($record->password)) {
                //a "guest" setting is used, validate that the guest enrolment
                //plugin is added to the current course
                if ($courseid = $DB->get_field('course', 'id', array('shortname' => $record->shortname))) {
                    if (!$DB->record_exists('enrol', array('courseid' => $courseid,
                                                            'enrol' => 'guest'))) {
                       return false;
                    }
                }
            }

            if (!empty($record->password)) {
                //make sure a password can only be set if guest access is enabled
                if ($courseid = $DB->get_field('course', 'id', array('shortname' => $record->shortname))) {

                    if (isset($record->guest) && empty($record->guest)) {
                        //guest access specifically disabled, which isn't
                        //consistent with providing a password
                        $this->fslogger->log("guest enrolment plugin cannot be assigned a password because the guest enrolment plugin is globally disabled.");
                        return false;
                    } else if (!isset($record->guest)) {
                        $params = array('courseid' => $courseid,
                                        'enrol' => 'guest',
                                        'status' => ENROL_INSTANCE_ENABLED);
                        if (!$DB->record_exists('enrol', $params)) {
                            //guest access disabled in the database
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * Create a course
     * @todo: consider factoring this some more once other actions exist
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function course_create($record, $filename) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/course/lib.php');

        //remove invalid fields
        $record = $this->remove_invalid_course_fields($record);

        //field length checking
        $lengthcheck = $this->check_course_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        //data checking
        if (!$this->validate_core_course_data('create', $record)) {
            return false;
        }

        //validate and set up the category
        $categoryid = $this->get_category_id($record->category);
        if ($categoryid === false) {
            return false;
        }

        $record->category = $categoryid;

        //uniqueness check
        if ($DB->record_exists('course', array('shortname' => $record->shortname))) {
            return false;
        }

        //final data sanitization
        if (isset($record->guest)) {
            if ($record->guest == 0) {
                $record->enrol_guest_status_0 = ENROL_INSTANCE_DISABLED;
            } else {
                $record->enrol_guest_status_0 = ENROL_INSTANCE_ENABLED;
                if (isset($record->password)) {
                    $record->enrol_guest_password_0 = $record->password;
                } else {
                    $record->enrol_guest_password_0 = NULL;
                }
            }
        }

        //write to the database
        if (isset($record->link)) {
            //creating from template
            require_once($CFG->dirroot.'/elis/core/lib/setup.php');
            require_once(elis::lib('rollover/lib.php'));
            $courseid = $DB->get_field('course', 'id', array('shortname' => $record->link));

            //perform the content rollover
            $record->id = course_rollover($courseid);
            //update appropriate fields, such as shortname
            //todo: validate if this fully works with guest enrolments?
            update_course($record);

            //log success
            $this->fslogger->log("[{$filename} line {$this->linenumber}] Course with shortname \"{$record->shortname}\" successfully created from template course with shortname \"{$record->link}\".");
        } else {
            //creating directly (not from template)
            create_course($record);

            //log success
            $this->fslogger->log("[{$filename} line {$this->linenumber}] Course with shortname \"{$record->shortname}\" successfully created.");
        }

        return true;
    }

    /**
     * Update a course
     * @todo: consider factoring this some more once other actions exist
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function course_update($record, $filename) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/lib/enrollib.php');

        //remove invalid fields
        $record = $this->remove_invalid_course_fields($record);

        //field length checking
        $lengthcheck = $this->check_course_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        //data checking
        if (!$this->validate_core_course_data('update', $record)) {
            return false;
        }

        //validate and set up the category
        if (isset($record->category)) {
            $categoryid = $this->get_category_id($record->category);
            if ($categoryid === false) {
                return false;
            }

            $record->category = $categoryid;
        }

        $record->id = $DB->get_field('course', 'id', array('shortname' => $record->shortname));
        if (empty($record->id)) {
            $this->fslogger->log("\"shortname\" value of {$record->shortname} does not refer to a valid course.");
            return false;
        }

        update_course($record);

        //special work for "guest" settings

        if (isset($record->guest) && empty($record->guest)) {
            //todo: add more error checking
            if ($enrol = $DB->get_record('enrol', array('courseid' => $record->id,
                                                        'enrol' => 'guest'))) {
                //disable the plugin for the current course
                $enrol->status = ENROL_INSTANCE_DISABLED;
                $DB->update_record('enrol', $enrol);
            } else {
                $this->fslogger->log("\"guest\" enrolments cannot be enabled because the guest enrolment plugin has been removed from course {$record->shortname}.");
                return false;
            }
        }

        if (!empty($record->guest)) {
            //todo: add more error checking
            if ($enrol = $DB->get_record('enrol', array('courseid' => $record->id,
                                                        'enrol' => 'guest'))) {
                //enable the plugin for the current course
                $enrol->status = ENROL_INSTANCE_ENABLED;
                if (isset($record->password)) {
                    //password specified, so set it
                    $enrol->password = $record->password;
                }
                $DB->update_record('enrol', $enrol);
            } else {
                $this->fslogger->log("guest enrolment plugin cannot be assigned a password because the guest enrolment plugin has been removed from course {$record->shortname}.");
                return false;
            }
        }

        //log success
        $this->fslogger->log("[{$filename} line {$this->linenumber}] Course with shortname \"{$record->shortname}\" successfully updated.");

        return true;
    }

    /**
     * Delete a course
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function course_delete($record, $filename) {
        global $DB;

        if ($courseid = $DB->get_field('course', 'id', array('shortname' => $record->shortname))) {
            delete_course($courseid, false);
            fix_course_sortorder();

            //log success
            $this->fslogger->log("[{$filename} line {$this->linenumber}] Course with shortname \"{$record->shortname}\" successfully deleted.");

            return true;
        }

        return false;
    }

    /**
     * Delegate processing of an import line for entity type "enrolment"
     *
     * @param object $record One record of import data
     * @param string $action The action to perform, or use data's action if
     *                       not supplied
     * @param string $filename The import file name, used for logging
     *
     * @return boolean true on success, otherwise false
     */
    function enrolment_action($record, $action = '', $filename = '') {
        if ($action === '') {
            //set from param
            $action = $record->action;
        }

        if (!$this->check_action_field($record, $filename)) {
            //missing an action value
            return false;
        }

        $record->action = $action;
        $exceptions = array('instance' => array('context' => 'system'));
        if (!$this->check_required_fields('enrolment', $record, $filename, $exceptions)) {
            //missing a required field
            return false;
        }

        //remove empty fields
        $record = $this->remove_empty_fields($record);

        //perform action
        $method = "enrolment_{$action}";
        if (method_exists($this, $method)) {
            return $this->$method($record, $filename);
        } else {
            //todo: add logging
            return false;
        }
    }

    /**
     * Obtains a userid from a data record, logging an error message to the
     * file system log on failure
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return mixed The user id, or false if not found
     */
    function get_userid_from_record($record, $filename) {
        global $CFG, $DB;

        //find existing user record
        $params = array();
        //track how many fields identify the user
        $num_identifiers = 0;

        if (isset($record->username)) {
            $num_identifiers++;
            $params['username'] = $record->username;
            $params['mnethostid'] = $CFG->mnet_localhost_id;
        }
        if (isset($record->email)) {
            $num_identifiers++;
            $params['email'] = $record->email;
        }
        if (isset($record->idnumber)) {
            $num_identifiers++;
            $params['idnumber'] = $record->idnumber;
        }

        if (!$userid = $DB->get_field('user', 'id', $params)) {
            //failure

            //get description of identifying fields
            $user_descriptor = $this->get_user_descriptor((object)$params, true);

            if ($num_identifiers > 1) {
                $does_token = 'do';
            } else {
                $does_token = 'does';
            }

            //log message
            $this->process_error("[{$filename} line {$this->linenumber}] {$user_descriptor} ".
                                 "{$does_token} not refer to a valid user.");

            return false;
        }

        //success
        return $userid;
    }

    /**
     * Obtains a context level and context record based on a role assignment
     * data record, logging an error message to the file system on failure
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return mixed The user id, or 
     */
    function get_contextinfo_from_record($record, $filename) {
        global $CFG, $DB;

        if ($record->context == 'course') {
            //find existing course
            if (!$courseid = $DB->get_field('course', 'id', array('shortname' => $record->instance))) {
                //invalid shortname
                $this->process_error("[{$filename} line {$this->linenumber}] \"instance\" value ".
                                     "of {$record->instance} does not refer to a valid instance ".
                                     "of a course context.");
                return false;
            }

            //obtain the course context instance
            $contextlevel = CONTEXT_COURSE;
            $context = get_context_instance($contextlevel, $courseid);
            return array($contextlevel, $context); 
        } else if ($record->context == 'system') {
            //obtain the system context instance
            $contextlevel = CONTEXT_SYSTEM;
            $context = get_context_instance($contextlevel);
            return array($contextlevel, $context, false);
        } else if ($record->context == 'coursecat') {
            //make sure category name is not ambiguous
            $count = $DB->count_records('course_categories', array('name' => $record->instance));
            if ($count > 1) {
                //ambiguous category name
                $this->process_error("[{$filename} line {$this->linenumber}] \"instance\" value ".
                                     "of {$record->instance} refers to multiple course category contexts.");
                return false;
            }

            //find existing course category
            if (!$categoryid = $DB->get_field('course_categories', 'id', array('name' => $record->instance))) {
                //invalid name
                $this->process_error("[{$filename} line {$this->linenumber}] \"instance\" value ".
                                     "of {$record->instance} does not refer to a valid instance ".
                                     "of a course category context.");
                return false;
            }

            //obtain the course category context instance
            $contextlevel = CONTEXT_COURSECAT;
            $context = get_context_instance($contextlevel, $categoryid);
            return array($contextlevel, $context, false);
        } else if ($record->context == 'user') {
            //find existing user
            if (!$targetuserid = $DB->get_field('user', 'id', array('username' => $record->instance,
                                                                    'mnethostid' => $CFG->mnet_localhost_id))) {
                //invalid username
                $this->process_error("[{$filename} line {$this->linenumber}] \"instance\" value ".
                                     "of {$record->instance} does not refer to a valid instance of a user context.");
                return false;
            }

            //obtain the user context instance
            $contextlevel = CONTEXT_USER;
            $context = get_context_instance($contextlevel, $targetuserid);
            return array($contextlevel, $context, false);
        } else {
            //currently only supporting course, system, user and category
            //context levels
            $this->process_error("[{$filename} line {$this->linenumber}] \"context\" value of ".
                                 "{$record->context} is not one of the available options ".
                                 "(system, user, coursecat, course).");
            return false;
        }
    }

    /**
     * Create an enrolment
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function enrolment_create($record, $filename) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/enrollib.php');

        //data checking
        if (!$roleid = $DB->get_field('role', 'id', array('shortname' => $record->role))) {
            return false;
        }

        //find existing user record
        if (!$userid = $this->get_userid_from_record($record, $filename)) {
            return false;
        }

        //track context info
        $contextinfo = $this->get_contextinfo_from_record($record, $filename);
        if ($contextinfo == false) {
            return false;
        }
        list($contextlevel, $context) = $contextinfo;

        //make sure the role is assignable at the course context level
        if (!$DB->record_exists('role_context_levels', array('roleid' => $roleid,
                                                             'contextlevel' => $contextlevel))) {
            $this->process_error("[{$filename} line {$this->linenumber}] The role with shortname ".
                                 "{$record->role} is not assignable on the {$record->context} ".
                                 "context level.");
            return false;
        }

        //note: this seems redundant but will be useful for error messages later
        $params = array('roleid' => $roleid,
                        'contextid' => $context->id,
                        'userid' => $userid,
                        'component' => '',
                        'itemid' => 0);
        $role_assignment_exists = $DB->record_exists('role_assignments', $params);

        //track the group and grouping specified
        $groupid = 0;
        $groupingid = 0;

        //duplicate group / grouping name checks and name validity checking
        if ($record->context == 'course' && isset($record->group)) {
            $count = $DB->count_records('groups', array('name' => $record->group,
                                                        'courseid' => $context->instanceid));

            $creategroups = get_config('rlipimport_version1', 'creategroupsandgroupings');
            if ($count > 1) {
                //ambiguous
                $this->process_error("[{$filename} line {$this->linenumber}] \"group\" value of ".
                                     "{$record->group} refers to multiple groups in course with shortname {$record->instance}.");
                return false;
            } else if ($count == 0 && empty($creategroups)) {
                //does not exist and not creating
                $this->process_error("[{$filename} line {$this->linenumber}] \"group\" value of ".
                                     "{$record->group} does not refer to a valid group in course with shortname {$record->instance}.");
                return false;
            } else {
                //exact group exists
                $groupid = groups_get_group_by_name($context->instanceid, $record->group);
            }

            if (isset($record->grouping)) {
                $count = $DB->count_records('groupings', array('name' => $record->grouping,
                                                               'courseid' => $context->instanceid));
                if ($count > 1) {
                    //ambiguous
                    $this->process_error("[{$filename} line {$this->linenumber}] \"grouping\" value of ".
                                         "{$record->grouping} refers to multiple groupings in course with shortname {$record->instance}.");
                    return false;
                } else if ($count == 0 && empty($creategroups)) {
                    //does not exist and not creating
                    $this->process_error("[{$filename} line {$this->linenumber}] \"grouping\" value of ".
                                         "{$record->grouping} does not refer to a valid grouping in ".
                                         "course with shortname {$record->instance}.");
                    return false;
                } else {
                    //exact grouping exists
                    $groupingid = groups_get_grouping_by_name($context->instanceid, $record->grouping);
                }
            }
        }

        //string to describe the user
        $user_descriptor = $this->get_user_descriptor($record);
        //string to describe the context instance
        $context_descriptor = $this->get_context_descriptor($record);

        //going to collect all messages for this action
        $logmessages = array();

        $studentroleids = array();
        if (isset($CFG->gradebookroles)) {
            $studentroleids = explode(',', $CFG->gradebookroles);
        }

        if ($record->context == 'course' && in_array($roleid, $studentroleids)) {

            //set enrolment start time to the course start date
            $timestart = $DB->get_field('course', 'startdate', array('id' => $context->instanceid));

            $is_enrolled = is_enrolled($context, $userid);
            if ($role_assignment_exists && !$is_enrolled) {

                //role assignment already exists, so just enrol the user
                enrol_try_internal_enrol($context->instanceid, $userid, null, $timestart);
            } else if (!$is_enrolled) {
                //role assignment does not exist, so enrol and assign role
                enrol_try_internal_enrol($context->instanceid, $userid, $roleid, $timestart);

                //collect success message for logging at end of action
                $logmessages[] = "User with {$user_descriptor} successfully assigned role with shortname \"{$record->role}\" on {$context_descriptor}.";
            } else {

                //duplicate enrolment attempt
                $this->process_error("[{$filename} line {$this->linenumber}] User with {$user_descriptor} is already assigned role with shortname \"{$record->role}\" on {$context_descriptor}. User with {$user_descriptor} is already enroled in course with shortname \"{$record->instance}\".");
                return false;
            }

            //collect success message for logging at end of action
            $logmessages[] = "User with {$user_descriptor} enrolled in course with shortname \"{$record->instance}\".";
        } else {

            if ($role_assignment_exists) {
                //role assignment already exists, so this action serves no purpose
                $this->process_error("[{$filename} line {$this->linenumber}] User with {$user_descriptor} is already assigned role with shortname \"{$record->role}\" on {$context_descriptor}.");
                return false;
            }

            role_assign($roleid, $userid, $context->id);

            //collect success message for logging at end of action
            $logmessages[] = "User with {$user_descriptor} successfully assigned role with shortname \"{$record->role}\" on {$context_descriptor}.";
        }

        if ($record->context == 'course' && isset($record->group)) {
            //process specified group
            require_once($CFG->dirroot.'/lib/grouplib.php');
            require_once($CFG->dirroot.'/group/lib.php');

            if ($groupid == 0) {
                //need to create the group
                $data = new stdClass;
                $data->courseid = $context->instanceid;
                $data->name = $record->group;

                $groupid = groups_create_group($data);

                //collect success message for logging at end of action
                $logmessages[] = "Group created with name \"{$record->group}\".";
            }

            if (groups_is_member($groupid, $userid)) {
                //error handling
            } else {
                //try to assign the user to the group
                if (!groups_add_member($groupid, $userid)) {
                    //error handling - already a member
                }

                //collect success message for logging at end of action
                $logmessages[] = "Assigned user with {$user_descriptor} to group with name \"{$record->group}\".";
            }

            if (isset($record->grouping)) {
                //process the specified grouping

                if ($groupingid == 0) {
                    //need to create the grouping
                    $data = new stdClass;
                    $data->courseid = $context->instanceid;
                    $data->name = $record->grouping;

                    $groupingid = groups_create_grouping($data);

                    //collect success message for logging at end of action
                    $logmessages[] = "Created grouping with name \"{$record->grouping}\".";
                }

                //assign the group to the grouping
                if ($DB->record_exists('groupings_groups', array('groupingid' => $groupingid,
                                                                 'groupid' => $groupid))) {
                    //error handling
                } else {
                    groups_assign_grouping($groupingid, $groupid);

                    //collect success message for logging at end of action
                    $logmessages[] = "Assigned group with name \"{$record->group}\" to grouping with name \"{$record->grouping}\".";
                }
            }
        }

        //log success
        $this->fslogger->log("[{$filename} line {$this->linenumber}] ".implode(' ', $logmessages));

        return true;
    }

    /**
     * Add an enrolment
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function enrolment_add($record, $filename) {
        //note: this is only here due to legacy 1.9 weirdness
        return $this->enrolment_create($record);
    }

    /**
     * Delete an enrolment
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @return boolean true on success, otherwise false
     */
    function enrolment_delete($record, $filename) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/enrollib.php');

        //data checking
        if (!$roleid = $DB->get_field('role', 'id', array('shortname' => $record->role))) {
            $this->fslogger->log("[{$filename} line {$this->linenumber}] \"role\" value of ".
                                 "{$record->role} does not refer to a valid role.");
            return false;
        }

        //find existing user record
        if (!$userid = $this->get_userid_from_record($record, $filename)) {
            return false;
        }

        //track the context info
        $contextinfo = $this->get_contextinfo_from_record($record, $filename);
        if ($contextinfo == false) {
            return false;
        }
        list($contextlevel, $context) = $contextinfo;

        //track whether an enrolment exists
        $enrolment_exists = false;

        if ($contextlevel == CONTEXT_COURSE) {
            $enrolment_exists = is_enrolled($context, $userid);
        }

        //determine whether the role assignment and enrolment records exist
        $role_assignment_exists = $DB->record_exists('role_assignments', array('roleid' => $roleid,
                                                                               'contextid' => $context->id,
                                                                               'userid' => $userid));

        $studentroleids = explode(',', $CFG->gradebookroles);
        if (!$role_assignment_exists) {
            $user_descriptor = $this->get_user_descriptor($record, false, false);
            $context_descriptor = $this->get_context_descriptor($record, false);
            $message = "[{$filename} line {$this->linenumber}] User with {$user_descriptor} ".
                       "is not assigned role with shortname {$record->role} on ".
                       "{$context_descriptor}.";

            if (!in_array($roleid, $studentroleids)) {
                //nothing to delete
                $this->fslogger->log($message);
                return false;
            } else if (!$enrolment_exists) {
                $message .= " User with {$user_descriptor} is not enroled in ".
                            "course with shortname {$record->instance}.";
                $this->fslogger->log($message);
                return false;
            }
        }

        //string to describe the user
        $user_descriptor = $this->get_user_descriptor($record);
        //string to describe the context instance
        $context_descriptor = $this->get_context_descriptor($record);

        //going to collect all messages for this action
        $logmessages = array();

        if ($role_assignment_exists) {
            //unassign role
            role_unassign($roleid, $userid, $context->id);

            //collect success message for logging at end of action
            $logmessages[] = "User with {$user_descriptor} successfully unassigned role with shortname \"{$record->role}\" on {$context_descriptor}.";
        }

        if ($enrolment_exists && in_array($roleid, $studentroleids)) {
            //remove enrolment
            if ($instance = $DB->get_record('enrol', array('enrol' => 'manual',
                                                           'courseid' => $context->instanceid))) {
                $plugin = enrol_get_plugin('manual');
                $plugin->unenrol_user($instance, $userid);

                //collect success message for logging at end of action
                $logmessages[] = "User with {$user_descriptor} unenrolled from course with shortname \"{$record->instance}\".";
            }
        }

        //log success
        $this->fslogger->log("[{$filename} line {$this->linenumber}] ".implode(' ', $logmessages));

        return true;
    }

    /**
     * Apply the configured field mapping to a single record
     *
     * @param string $entity The type of entity
     * @param object $record One record of import data
     *
     * @return object The record, with the field mapping applied
     */
    function apply_mapping($entity, $record) {
        global $DB;

        //fetch all records for the current entity type (not using recordset
        //since there are a fixed number of fields)
        $params = array('entitytype' => $entity);
        if ($mapping = $DB->get_records('block_rlip_version1_fieldmap', $params)) {
            foreach ($mapping as $entry) {
                //get the custom and standard field names from the mapping
                //record
                $customfieldname = $entry->customfieldname;
                $standardfieldname = $entry->standardfieldname;

                if (isset($record->$customfieldname)) {
                    //do the conversion
                    $record->$standardfieldname = $record->$customfieldname;
                    unset($record->$customfieldname);
                } else if (isset($record->$standardfieldname)) {
                    //remove the standard field because it should have been
                    //provided as a mapped value
                    unset($record->$standardfieldname);
                }
            }
        }

        return $record;
    }

    /**
     * Entry point for processing a single record
     *
     * @param string $entity The type of entity
     * @param object $record One record of import data
     * @param string $filename Import file name to user for logging
     *
     * @return boolean true on success, otherwise false
     */
    function process_record($entity, $record, $filename) {
        //apply the field mapping
        $record = $this->apply_mapping($entity, $record);

        return parent::process_record($entity, $record, $filename);
    }

    /**
     * Specifies the UI labels for the various import files supported by this
     * plugin
     *
     * @return array The string labels, in the order in which the
     *               associated [entity]_action methods are defined
     */
    function get_file_labels() {
        return array(get_string('userfile', 'rlipimport_version1'),
                     get_string('coursefile', 'rlipimport_version1'),
                     get_string('enrolmentfile', 'rlipimport_version1'));
    }
}
