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
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->libdir . '/formslib.php');
require_once('locallib.php');

MoodleQuickForm::registerElementType('keyusercohort', "$CFG->dirroot/local/keyuser/classes/form/keyusercohort.php", 'MoodleQuickForm_keyusercohort');


/**
 * Overwrites global $PAGE object of type moodle_page
 *
 */
function local_keyuser_after_config(){
    if(array_key_exists('REQUEST_URI', $_SERVER)){
        $url = parse_url($_SERVER['REQUEST_URI']);
        if($url['path'] == "/user/editadvanced.php") {
            $systemcontext = context_system::instance();
            if(!has_capability("moodle/user:update",$systemcontext) && (has_capability("local/keyuser:userupdate",$systemcontext) || ((strpos($url->query,"id=-1")!==false) && has_capability("local/keyuser:usercreate",$systemcontext)))){
                redirect("/local/keyuser".$url['path']."?".$url['query']);
            }
        }
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function local_keyuser_inplace_editable($itemtype, $itemid, $newvalue) {
    if ($itemtype === 'cohortname') {
        return \local_keyuser\output\cohortname::update($itemid, $newvalue);
    }
}

function local_keyuser_myprofile_navigation($tree, $user, $iscurrentuser, $course){
    global $USER;
    $systemcontext = context_system::instance();
    $courseid = !empty($course) ? $course->id : SITEID;

    if (($iscurrentuser || is_siteadmin($USER) || !is_siteadmin($user)) && has_capability('local/keyuser:userupdate', $systemcontext)) {
        $url = new moodle_url('/user/editadvanced.php', array('id' => $user->id, 'course' => $courseid,
            'returnto' => 'profile'));
        $node = new core_user\output\myprofile\node('contact', 'editprofile', get_string('editmyprofile'), null, $url,
            null, null, 'editprofile');
        $tree->add_node($node);
    }
}

function local_keyuser_control_view_profile($user, $course = null, context_user $usercontext = null) {
    global $DB,$PAGE;
    $systemcontext = context_system::instance();
    if(has_capability("local/keyuser:userupdate",$systemcontext)){
        $sql = "SELECT count(*) as count FROM {user} ";
        $wheresql = "WHERE {user}.id = :id";
        $params['id'] = $user->id;
        keyuser_user_append_where($wheresql,$params);
        $r = $DB->get_record_sql($sql.$wheresql, $params);
        if($r->count > 0){
            //$PAGE->set_other_editing_capability("local/keyuser:userupdate");         
            return core_user::VIEWPROFILE_FORCE_ALLOW;
        }
    }
    return core_user::VIEWPROFILE_DO_NOT_PREVENT;
}
