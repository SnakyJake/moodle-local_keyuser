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
 * @language   en
 */

$string['user'] = 'User';
$string['email'] = 'E-Mail';
$string['pluginname'] = 'Keyuser';
$string['heading_index'] = 'Keyuser';

$string['confirmdelete'] = "Really delete?";

$string['settings_category'] = "Keyuser Settings";

$string['keyuser:usercreate'] = get_string('user:create','role');
$string['keyuser:userdelete'] = get_string('user:delete','role');
$string['keyuser:userupdate'] = get_string('user:update','role');
$string['keyuser:userviewdetails'] = get_string('user:viewdetails','role');
$string['keyuser:userviewalldetails'] = get_string('user:viewalldetails','role');
$string['keyuser:userviewlastip'] = get_string('user:viewlastip','role');
$string['keyuser:userviewhiddendetails'] =  get_string('user:viewhiddendetails','role');
$string['keyuser:uploadusers'] = 'Upload users';

$string['keyuser:userbulkactions'] = get_string('userbulk','admin');

$string['keyuser:cohortmanage'] = get_string('cohort:manage','role');
$string['keyuser:cohortview'] = get_string('cohort:view','role');
$string['keyuser:cohortassign'] = get_string('cohort:assign','role');

$string['keyuser:roleassign'] = get_string('role:assign','role');

$string['heading_checkmoodlechanges'] = "Checking moodle files for changes";
$string['link_checkmoodlechanges'] = "Check moodle files for changes";
$string['heading_changedfilescount'] = '{$a} files have changed:';
$string['heading_unchangedfilescount'] = '{$a} files are the same:';

$string['settings_keyuser_no_prefix_allowed'] = "Allow no linked cohort prefix field";
$string['settings_keyuser_linkedfields'] = "Linked profile fields";
$string['settings_keyuser_linkedfieldshelp'] = "Which custom profile fields are linked between keyuser and the users he manages.";
$string['settings_keyuser_linkedfieldsmulti'] = "Linked profile fields multiple values";
$string['settings_keyuser_linkedfieldsmultihelp'] = "All values of this multi value field are accepted";
$string['settings_keyuser_cohortprefixfields'] = "cohort prefix fields";
$string['settings_keyuser_cohortprefixfieldshelp'] = "cohort prefix fields";
$string['settings_keyuser_cohortprefixfieldsmulti'] = "cohort prefix fields multiple values";
$string['settings_keyuser_cohortprefixfieldsmultihelp'] = "show all cohorts with any prefix of this multi value fields";

$string['label_cohort_prefix_select'] = "Cohort prefix: ";
$string['label_linkedfield_select'] = "Link: ";

$string['edit_profilefields'] = "Edit custom profile fields";

$string['error_missing_fields'] = "This action cannot be executed. No profile fields are linked. Please contact the administrator!";

$string['emptyname'] =  "--- no name --- please give me a name!";


