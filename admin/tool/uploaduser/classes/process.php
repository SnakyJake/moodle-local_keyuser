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
 * Class process
 *
 * @package     local_keyuser, keyusertool_uploaduser
 * @copyright   2020 Moodle, 2021 Jakob Heinemann, 2022 Fabian Bech
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_keyuser\tool_uploaduser;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/keyuser/locallib.php');

/**
 * Process CSV file with users data, this will create/update users, enrol them into courses, add them to keyuser_cohorts etc
 *
 * @package     local_keyuser, tool_uploaduser
 * @copyright   2020 Moodle, 2021 Jakob Heinemann, 2022 Fabian Bech
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process extends \tool_uploaduser\process {

    /**
     * Returns the list of columns in the file
     *
     * @return array
     */
    public function get_file_columns(): array {
        if ($this->filecolumns === null) {
            $returnurl = new \moodle_url('/local/keyuser/admin/tool/uploaduser/index.php');
            $this->filecolumns = uu_validate_user_upload_columns($this->cir,
                $this->standardfields, $this->profilefields, $returnurl);
        }
        return $this->filecolumns;
    }

    /**
     * Prepare one line from CSV file as a user record
     * Check and prepend prefix to keyuser cohorts
     * Check or set correct linked profile fields
     *
     * @param array $line
     * @return \stdClass|null
     */
    protected function prepare_user_record(array $line): ?\stdClass {
        global $CFG, $USER, $DB, $KEYUSER_CFG;

        $user = new \stdClass();
        $prefix = keyuser_cohort_get_prefix(true);

        // Add fields to user object.
        foreach ($line as $keynum => $value) {
            if (!isset($this->get_file_columns()[$keynum])) {
                // This should not happen.
                continue;
            }
            $key = $this->get_file_columns()[$keynum];
            if (strpos($key, 'profile_field_') === 0) {
                // NOTE: bloody mega hack alert!!
                if (isset($USER->$key) and is_array($USER->$key)) {
                    // This must be some hacky field that is abusing arrays to store content and format.
                    $user->$key = array();
                    $user->{$key['text']}   = $value;
                    $user->{$key['format']} = FORMAT_MOODLE;
                } else {
                    $user->$key = trim($value);
                }
            } elseif (preg_match('/^cohort\d+$/', $key)) {
                if (!$prefix)
                    continue;

                $value = trim($value);
                if (!empty($value)) {
                    if (preg_match("!$prefix!Ai", $value, $preg_matches)) {
                        // We have cohort with real idnumber here - cut prefix
                        $value = substr($value, strlen($preg_matches[0]));
                    }

                    // Add regex prefix to idnumber - find existing cohort
                    $value_regexp = $prefix.preg_quote($value).'$';
                    $cohort = $DB->get_record_select('cohort', 'idnumber REGEXP ?', [$value_regexp]);

                    if (empty($cohort)) {
                        // Its a new cohort
                        if ($preg_matches) {
                            if(in_array('r_', $preg_matches)) {
                                $this->upt->track('enrolments', "Can not create readonly cohort '{$preg_matches[0]}$value'", 'warning');
                                continue;
                            }
                        } else {
                            if (!keyuser_cohort_add_prefix($value) && $KEYUSER_CFG->no_prefix_allowed) {
                                $this->upt->track('enrolments', "Can not add prefix to '$value'", 'warning');
                                continue;
                            }
                        }
                    } else {
                        $value = $cohort->idnumber;
                    }
                }
                $user->$key = $value;
            } else {
                $user->$key = trim($value);
            }

            if (in_array($key, $this->upt->columns)) {
                // Default value in progress tracking table, can be changed later.
                $this->upt->track($key, s($value), 'normal');
            }
        }
        if (!isset($user->username)) {
            // Prevent warnings below.
            $user->username = '';
        }

        if ($this->get_operation_type() == UU_USER_ADDNEW or $this->get_operation_type() == UU_USER_ADDINC) {
            // User creation is a special case - the username may be constructed from templates using firstname and lastname
            // better never try this in mixed update types.
            $error = false;
            if (!isset($user->firstname) or $user->firstname === '') {
                $this->upt->track('status', get_string('missingfield', 'error', 'firstname'), 'error');
                $this->upt->track('firstname', get_string('error'), 'error');
                $error = true;
            }
            if (!isset($user->lastname) or $user->lastname === '') {
                $this->upt->track('status', get_string('missingfield', 'error', 'lastname'), 'error');
                $this->upt->track('lastname', get_string('error'), 'error');
                $error = true;
            }
            if ($error) {
                $this->userserrors++;
                return null;
            }
            // We require username too - we might use template for it though.
            if (empty($user->username) and !empty($this->formdata->username)) {
                $user->username = uu_process_template($this->formdata->username, $user);
                $this->upt->track('username', s($user->username));
            }
        }

        // Normalize username.
        $user->originalusername = $user->username;
        if ($this->get_normalise_user_names()) {
            $user->username = \core_user::clean_field($user->username, 'username');
        }

        // Make sure we really have username.
        if (empty($user->username)) {
            $this->upt->track('status', get_string('missingfield', 'error', 'username'), 'error');
            $this->upt->track('username', get_string('error'), 'error');
            $this->userserrors++;
            return null;
        } else if ($user->username === 'guest') {
            $this->upt->track('status', get_string('guestnoeditprofileother', 'error'), 'error');
            $this->userserrors++;
            return null;
        }
        // Make sure we have correct linked profile fields.
        foreach($KEYUSER_CFG->linked_fields as $linked_field) {
            $shortname = $linked_field->shortname;
            $name = 'profile_field_'.$shortname;

            if (isset($user->$name)) {
                if ($user->$name != $USER->profile[$shortname]) {
                    $this->upt->track('status', sprintf('%s "%s" is not allowed', $shortname, $user->$name), 'error');
                    $this->userserrors++;
                    return null;
                }
            } else {
                $user->$name = $USER->profile[$shortname];
            }
        }

        if ($user->username !== \core_user::clean_field($user->username, 'username')) {
            $this->upt->track('status', get_string('invalidusername', 'error', 'username'), 'error');
            $this->upt->track('username', get_string('error'), 'error');
            $this->userserrors++;
        }

        if (empty($user->mnethostid)) {
            $user->mnethostid = $CFG->mnet_localhost_id;
        }

        return $user;
    }
}
