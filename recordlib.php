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
 * Cohort related management functions, this file needs to be included manually.
 *
 * @package    local_keyuser
 * @copyright  2022 Fabian Bech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/defines.php');
require_once(__DIR__.'/locallib.php');

/**
 * Return a single keyuser_cohort as an object where the $id and keyuser conditions are met.
 *
 * @param int $id
 * @return mixed keyuser_cohort
 */
function keyuser_cohort_get_record($id, $strictness=IGNORE_MISSING) {
    global $DB;

    $sql = " WHERE id = :id";
    $params = array('prefix' => keyuser_cohort_get_prefix(true), 'id' => $id);

    return $DB->get_record_sql(SELECT_KEYUSER_COHORT . FROM_KEYUSER_COHORT . $sql, $params, $strictness);
}

/**
 * Get all keyuser_cohorts sorted by name and idnumber as an array of objects of objects.
 *
 * @return array array of keyuser_cohorts
 */
function keyuser_cohort_get_records() {
    global $DB;

    $params = array('prefix' => keyuser_cohort_get_prefix(true));
    $order = " ORDER BY name ASC, idnumber ASC";

    return $DB->get_records_sql(SELECT_KEYUSER_COHORT . FROM_KEYUSER_COHORT . $order, $params);
}

/**
 * Get keyuser_cohorts as an array of objects with the given conditions.
 *
 * @param string $select
 * @param array $params
 * @return array array of keyuser_cohorts
 */
function keyuser_cohort_get_records_select($select, $params) {
    global $DB;

    $params += array('prefix' => keyuser_cohort_get_prefix(true));

    return $DB->get_records_sql(SELECT_KEYUSER_COHORT . FROM_KEYUSER_COHORT . " WHERE " . $select, $params);
}

/**
 * Test whether a keyuser_cohort exists with given idnumber.
 *
 * @param string $idnumber
 * @return bool true if a keyuser_cohort with given idnumber exists, else false
 */
function keyuser_cohort_record_exists($idnumber) {
    global $DB;

    $idnumber = keyuser_cohort_get_prefix(true).preg_quote($idnumber).'$';
    return $DB->record_exists_select('cohort', 'idnumber REGEXP ?', [$idnumber]);
}
