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

require_once($CFG->dirroot.'/user/filters/lib.php');

class keyuser_user_filtering extends user_filtering {
    public function __construct($fieldnames = null, $baseurl = null, $extraparams = null) {
        global $SESSION, $DB, $USER;

        if (!isset($SESSION->user_filtering)) {
            $SESSION->user_filtering = array();
        }

        if (empty($fieldnames)) {
            // As a start, add all fields as advanced fields (which are only available after clicking on "Show more").
            $fieldnames = array('realname' => 1, 'lastname' => 1, 'firstname' => 1, 'username' => 1, 'email' => 1, 'city' => 1,
                'country' => 1, 'confirmed' => 1, 'suspended' => 1, 'courserole' => 1,
                'anycourses' => 1, 'systemrole' => 1, 'cohort' => 1, 'firstaccess' => 1, 'lastaccess' => 1,
                'neveraccessed' => 1, 'timemodified' => 1, 'nevermodified' => 1, 'department' => 1);

            // Get the config which filters the admin wanted to show by default.
            $userfiltersdefault = get_config('core', 'userfiltersdefault');

            // If the admin did not enable any filter, the form will not make much sense if all fields are hidden behind
            // "Show more". Thus, we enable the 'realname' filter automatically.
            if ($userfiltersdefault == '') {
                $userfiltersdefault = array('realname');

                // Otherwise, we split the enabled filters into an array.
            } else {
                $userfiltersdefault = explode(',', $userfiltersdefault);
            }

            // Show these fields by default which the admin has enabled in the config.
            foreach ($userfiltersdefault as $key) {
                $fieldnames[$key] = 0;
            }
        }

        $this->_fields  = array();

        foreach ($fieldnames as $fieldname => $advanced) {
            if ($field = $this->get_field($fieldname, $advanced)) {
                $this->_fields[$fieldname] = $field;
            }
        }

        // Fist the new filter form.
        $this->_addform = new user_add_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        if ($adddata = $this->_addform->get_data()) {
            foreach ($this->_fields as $fname => $field) {
                $data = $field->check_data($adddata);
                if ($data === false) {
                    continue; // Nothing new.
                }
                if (!array_key_exists($fname, $SESSION->user_filtering)) {
                    $SESSION->user_filtering[$fname] = array();
                }
                $SESSION->user_filtering[$fname][] = $data;
            }
            // Clear the form.
            $_POST = array();
            $this->_addform = new user_add_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        }

        // Now the active filters.
        $this->_activeform = new user_active_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        if ($adddata = $this->_activeform->get_data()) {
            if (!empty($adddata->removeall)) {
                $SESSION->user_filtering = array();

            } else if (!empty($adddata->removeselected) and !empty($adddata->filter)) {
                foreach ($adddata->filter as $fname => $instances) {
                    foreach ($instances as $i => $val) {
                        if (empty($val)) {
                            continue;
                        }
                        unset($SESSION->user_filtering[$fname][$i]);
                    }
                    if (empty($SESSION->user_filtering[$fname])) {
                        unset($SESSION->user_filtering[$fname]);
                    }
                }
            }
            // Clear+reload the form.
            $_POST = array();
            $this->_activeform = new user_active_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        }
    }

    public function get_sql_filter($extra='', array $params=null) {
        $sql = parent::get_sql_filter($extra, $params);
        $sql[0] .= $sql[0]?" AND ":"";
        $sql[0] .= keyuser_user_where($sql[1]);
        return $sql;
    }
    public function get_keyuser_sql_filter() {
        $sql = ["",[]];
        $sql[0] = keyuser_user_where($sql[1]);
        return $sql;
    }

}
