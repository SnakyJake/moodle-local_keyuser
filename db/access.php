<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version details.
 *
 * @package    local_keyuser
 * @author     Jakob Heinemann <jakob@jakobheinemann.de>
 * @copyright  Jakob Heinemann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @language   de
 */

 defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'local/keyuser:usercreate' => array(
        'riskbitmask' => RISK_SPAM | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'moodle/user:create'
    ),

    'local/keyuser:userdelete' => array(
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'moodle/user:delete'
    ),

    'local/keyuser:userupdate' => array(
        'riskbitmask' => RISK_SPAM | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'moodle/user:update'
    ),

    'local/keyuser:userbulkactions' => array(
        'riskbitmask' => RISK_SPAM | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'moodle/user:update'
    ),

    'local/keyuser:userviewdetails' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'clonepermissionsfrom' => 'moodle/user:viewdetails'
    ),

    'local/keyuser:userviewalldetails' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_USER,
        'clonepermissionsfrom' => 'moodle/user:viewalldetails'
    ),

    'local/keyuser:userviewlastip' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_USER,
        'clonepermissionsfrom' => 'moodle/user:viewlastip'
    ),

    'local/keyuser:userviewhiddendetails' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'clonepermissionsfrom' => 'moodle/user:viewhiddendetails'
    ),
    'local/keyuser:cohortmanage' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'clonepermissionsfrom' => 'moodle/cohort:manage'
    ),
    'local/keyuser:cohortassign' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'clonepermissionsfrom' => 'moodle/cohort:assign'
    ),
    'local/keyuser:cohortview' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'clonepermissionsfrom' => 'moodle/cohort:view'
    ),
    'local/keyuser:uploadusers' => array(
        'riskbitmask' => RISK_SPAM | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'clonepermissionsfrom' => 'moodle/site:uploadusers'
    ),
    'local/keyuser:roleassign' => array(

        'riskbitmask' => RISK_SPAM | RISK_PERSONAL | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'clonepermissionsfrom' => 'moodle/role:assign'
    ),
);