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
