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
 * External cohort API
 *
 * @package    local_keyuser
 * @category   external
 * @copyright  2021 Jakob Heinemann, MediaTouch 2000 srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot . '/local/keyuser/locallib.php');
require_once($CFG->dirroot . '/local/keyuser/cohort/lib.php');

class local_keyuser_cohort_external extends external_api {

    /**
     * Returns the description of external function parameters.
     *
     * @return external_function_parameters
     */
    public static function search_cohorts_parameters() {
        $query = new external_value(
            PARAM_RAW,
            'Query string'
        );
        $includes = new external_value(
            PARAM_ALPHA,
            'What other contexts to fetch the frameworks from. (all, parents, self)',
            VALUE_DEFAULT,
            'parents'
        );
        $limitfrom = new external_value(
            PARAM_INT,
            'limitfrom we are fetching the records from',
            VALUE_DEFAULT,
            0
        );
        $limitnum = new external_value(
            PARAM_INT,
            'Number of records to fetch',
            VALUE_DEFAULT,
            25
        );
        return new external_function_parameters(array(
            'query' => $query,
            'context' => self::get_context_parameters(),
            'includes' => $includes,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum
        ));
    }

    /**
     * Search cohorts.
     *
     * @param string $query
     * @param array $context
     * @param string $includes
     * @param int $limitfrom
     * @param int $limitnum
     * @return array
     */
    public static function search_cohorts($query, $context, $includes = 'parents', $limitfrom = 0, $limitnum = 25) {
        global $CFG;

        $params = self::validate_parameters(self::search_cohorts_parameters(), array(
            'query' => $query,
            'context' => $context,
            'includes' => $includes,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ));
        $query = $params['query'];
        $includes = $params['includes'];
        $context = self::get_context_from_params($params['context']);
        $limitfrom = $params['limitfrom'];
        $limitnum = $params['limitnum'];

        self::validate_context($context);

        $manager = has_capability('local/keyuser:cohortmanage', $context);
        if (!$manager) {
            require_capability('local/keyuser:cohortview', $context);
        }

        // TODO Make this more efficient.
        if ($includes == 'self') {
            $results = keyuser_cohort_get_cohorts($context->id, $limitfrom, $limitnum, $query);
            $results = $results['cohorts'];
        } else if ($includes == 'parents') {
            $results = keyuser_cohort_get_cohorts($context->id, $limitfrom, $limitnum, $query);
            $results = $results['cohorts'];
            if (!$context instanceof context_system) {
                $results = array_merge($results, keyuser_cohort_get_available_cohorts($context, COHORT_ALL, $limitfrom, $limitnum, $query));
            }
        } else if ($includes == 'all') {
            $results = keyuser_cohort_get_all_cohorts($limitfrom, $limitnum, $query);
            $results = $results['cohorts'];
        } else {
            throw new coding_exception('Invalid parameter value for \'includes\'.');
        }

        $cohorts = array();
        foreach ($results as $key => $cohort) {
            $cohortcontext = context::instance_by_id($cohort->contextid);

            // Only return theme when $CFG->allowcohortthemes is enabled.
            if (!empty($cohort->theme) && empty($CFG->allowcohortthemes)) {
                $cohort->theme = null;
            }

            if (!isset($cohort->description)) {
                $cohort->description = '';
            }
            if (!isset($cohort->descriptionformat)) {
                $cohort->descriptionformat = FORMAT_PLAIN;
            }

            list($cohort->description, $cohort->descriptionformat) =
                external_format_text($cohort->description, $cohort->descriptionformat,
                        $cohortcontext->id, 'cohort', 'description', $cohort->id);

            $cohorts[$key] = $cohort;
        }

        return array('cohorts' => $cohorts);
    }

    /**
     * Returns description of external function result value.
     *
     * @return external_description
     */
    public static function search_cohorts_returns() {
        return new external_single_structure(array(
            'cohorts' => new external_multiple_structure(
                new external_single_structure(array(
                    'id' => new external_value(PARAM_INT, 'ID of the cohort'),
                    'name' => new external_value(PARAM_RAW, 'cohort name'),
                    'idnumber' => new external_value(PARAM_RAW, 'cohort idnumber'),
                    'description' => new external_value(PARAM_RAW, 'cohort description'),
                    'descriptionformat' => new external_format_value('description'),
                    'visible' => new external_value(PARAM_BOOL, 'cohort visible'),
                    'theme' => new external_value(PARAM_THEME, 'cohort theme', VALUE_OPTIONAL),
                ))
            )
        ));
    }
}
