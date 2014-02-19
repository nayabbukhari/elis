<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    block_courserequest
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2014 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'block/courserequest:request' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/course_request:request',
        'legacy'       => array(
            'manager'       => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
            'teacher'       => CAP_ALLOW
        )
    ),

    'block/courserequest:config' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/course_request:config',
        'legacy'       => array(
            'manager'       => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
        )
    ),

    'block/courserequest:approve' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'block/course_request:approve',
        'legacy'       => array(
            'manager'       => CAP_ALLOW,
            'coursecreator' => CAP_ALLOW,
        )
    ),

    'block/courserequest:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'clonepermissionsfrom' => 'block/course_request:addinstance',
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
    ),
);