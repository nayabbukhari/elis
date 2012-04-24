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
 * @subpackage elis_files (Alfresco)
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once($CFG->dirroot . '/repository/elis_files/ELIS_files_factory.class.php');
require_once($CFG->dirroot . '/repository/elis_files/lib/eventlib.php');
require_once($CFG->dirroot . '/repository/elis_files/lib/ELIS_files.php');

function xmldb_repository_elis_files_upgrade($oldversion = 0) {
    global $DB;
    $result = true;

    if ($oldversion < 2011110301) {
        $errors = false;
        $auths = elis_files_nopasswd_auths();
        $authlist = "'". implode("', '", $auths) ."'";
        $users = $DB->get_records_select('user', "auth IN ({$authlist})", array(), 'id, auth');
        if (!empty($users)) {
            foreach ($users as $user) {
                $user = get_complete_user_data('id', $user->id);
                $migrate_ok = elis_files_user_created($user);
                if (!$migrate_ok) {
                    $errors = true;
                    error_log("xmldb_block_elis_files_upgrade({$oldversion}) - failed migrating user ({$user->id}) to Alfresco.");
                }
            }
        }
        if (!$errors) {
            set_config('initialized', 1, ELIS_files::$plugin_name);
        }

        // elis_files savepoint reached
        upgrade_plugin_savepoint(true, 2011110301, 'repository', 'elis_files');
    }

    if ($oldversion < 2012042300) {
        //check that elis_files_organization_store exists and elis_files_userset_store does not exist
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('elis_files_organization_store') && !$dbman->table_exists('elis_files_userset_store')) {

            $original_table = new xmldb_table('elis_files_organization_store');

            //rename table
            $dbman->rename_table($original_table,'elis_files_userset_store');

            $new_table = new xmldb_table('elis_files_userset_store');

            //drop the keys
            $original_uuid_index = new xmldb_index('elisfileorgastor_orguu_uix', XMLDB_INDEX_UNIQUE, array('organizationid', 'uuid'));
            $original_index = new xmldb_index('elisfileorgastor_org_ix', XMLDB_INDEX_NOTUNIQUE, array('organizationid'));
            $dbman->drop_index($new_table, $original_uuid_index);
            $dbman->drop_index($new_table, $original_index);

            //rename field
            $organization = new xmldb_field('organizationid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
            $dbman->rename_field($new_table, $organization, 'usersetid');

            //add the keys
            $new_uuid_index = new xmldb_index('elisfileuserstor_useuu_uix', XMLDB_INDEX_UNIQUE, array('usersetid', 'uuid'));
            $new_index = new xmldb_index('elisfileuserstor_use_ix', XMLDB_INDEX_NOTUNIQUE, array('usersetid'));
            $dbman->add_index($new_table, $new_uuid_index);
            $dbman->add_index($new_table, $new_index);
        }

        // elis_files savepoint reached
        upgrade_plugin_savepoint(true, 2012042300, 'repository', 'elis_files');
    }

    return $result;
}

?>

