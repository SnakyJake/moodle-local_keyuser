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
     * Check for corrent linked profile fields
     * Prepend prefix to cohorts if needed
     *
     * @param array $line
     * @return \stdClass|null
     */
    protected function prepare_user_record(array $line): ?\stdClass {
        global $USER, $KEYUSER_CFG;
        $user = parent::prepare_user_record($line);

        foreach($KEYUSER_CFG->linked_fields as $linked_field) {
            $shortname = $linked_field->shortname;
            $name = 'profile_field_' . $shortname;

            if (isset($user->$name)) {
                if ($user->$name != $USER->profile[$shortname]) {
                    $this->upt->track('status', 'Wrong linked field', 'warning');
                    return null;
                }
            } else {
                $user->$name = $USER->profile[$shortname];
            }
        }

        array_walk($user, 'keyuser_cohort_add_prefix_by_cohort_key');
        //$this->upt->track('enrolments', "Prefix added: $cohortname", 'info');

        return $user;
    }
}
