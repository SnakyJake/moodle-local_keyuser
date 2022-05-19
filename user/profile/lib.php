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

require_once($CFG->dirroot.'/user/profile/lib.php');

class keyuser_profile_field {
    public static function edit_field(&$mform,$field){
        $field->edit_field_add($mform);
        $field->edit_field_set_default($mform);
        $field->edit_field_set_required($mform);
    }
    public static function is_editable($field){
        global $USER;

        if (!keyuser_profile_field::is_visible($field)) {
            return false;
        }

        if ($field->is_signup_field() && (empty($field->userid) || isguestuser($field->userid))) {
            // Allow editing the field on the signup page.
            return true;
        }

        $systemcontext = context_system::instance();

        if ($field->userid == $USER->id && has_capability('moodle/user:editownprofile', $systemcontext)) {
            return true;
        }

        if (has_capability('local/keyuser:userupdate', $systemcontext)) {
            return true;
        }

        return false;
    }
    public static function is_visible($field){
        global $USER;

        $context = ($field->userid > 0) ? context_user::instance($field->userid) : context_system::instance();

        switch ($field->field->visible) {
            case PROFILE_VISIBLE_ALL:
                return true;
            case PROFILE_VISIBLE_PRIVATE:
                if ($field->is_signup_field() && (empty($field->userid) || isguestuser($field->userid))) {
                    return true;
                } else if ($field->userid == $USER->id) {
                    return true;
                } else {
                    return has_capability('local/keyuser:userviewalldetails', $context);
                }
            default:
                return has_capability('local/keyuser:userviewalldetails', $context);
        }
   }
}