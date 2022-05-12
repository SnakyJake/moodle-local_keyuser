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
 * Contains class core_cohort\output\cohortname
 *
 * @package   core_cohort, local_keyuser
 * @copyright 2021 Jakob Heinemann, 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_keyuser\output;

require_once($CFG->dirroot.'/local/keyuser/locallib.php');

use lang_string;

/**
 * Class to prepare a cohort name for display.
 *
 * @package   core_cohort, local_keyuser
 * @copyright 2021 Jakob Heinemann, 2016 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohortname extends \core\output\inplace_editable {
    /**
     * Constructor.
     *
     * @param stdClass $cohort
     */
    public function __construct($cohort) {
        $cohortcontext = \context::instance_by_id($cohort->contextid);
        $editable = \has_capability('local/keyuser:cohortmanage', $cohortcontext) && !$cohort->readonly;
        $name = $cohort->suffix;
        $displayvalue = format_string($name, true, array('context' => $cohortcontext));
        parent::__construct('local_keyuser', 'cohortname', $cohort->id, $editable,
            $displayvalue,
            $name,
            new lang_string('editcohortname', 'cohort'),
            new lang_string('newnamefor', 'cohort', $displayvalue));
    }

    /**
     * Updates cohort name and returns instance of this object
     *
     * @param int $cohortid
     * @param string $newvalue
     * @return static
     */
    public static function update($cohortid, $newvalue) {
        global $DB;

        $sql = "SELECT * FROM {cohort} ";
        $wheresql = "WHERE id = :id";
        $params["id"]=$cohortid;
    
        \keyuser_cohort_append_where($wheresql,$params);

        $cohort = $DB->get_record_sql($sql . $wheresql, $params);
        $cohortcontext = \context::instance_by_id($cohort->contextid);
        \external_api::validate_context($cohortcontext);
        \require_capability('local/keyuser:cohortmanage', $cohortcontext);
        if(!clean_param($newvalue, PARAM_TEXT)){
            $newvalue = $cohort->name;
        }
        \keyuser_cohort_add_prefix($newvalue);
        $newvalue = clean_param($newvalue, PARAM_TEXT);
        if (strval($newvalue) !== '') {
            $record = (object)array('id' => $cohort->id, 'name' => $newvalue, 'idnumber' => $newvalue, 'contextid' => $cohort->contextid);
            \cohort_update_cohort($record);
            $cohort->name = $newvalue;
            $cohort->idnumber = $newvalue;
        }
        return new static($cohort);
    }

/**
     * Export this data so it can be used as the context for a mustache template (core/inplace_editable).
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return array data context for a mustache template
     */
    public function export_for_template(\renderer_base $output) {
        if (!$this->editable) {
            return array(
                'displayvalue' => "<span class='keyuser_readonly'>".(string)$this->displayvalue."<span>",
            );
        }

        return array(
            'component' => $this->component,
            'itemtype' => $this->itemtype,
            'itemid' => $this->itemid,
            'displayvalue' => (string)$this->displayvalue,
            'value' => (string)$this->value,
            'edithint' => (string)$this->edithint,
            'editlabel' => (string)$this->editlabel,
            'type' => $this->type,
            'options' => $this->options,
            'linkeverything' => $this->get_linkeverything() ? 1 : 0,
        );
    }
}
