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
 * Defines for SQL queries of keyuser_cohorts
 *
 * @package    local_keyuser
 * @copyright  2022 Fabian Bech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('SELECT_KEYUSER_COHORT', "SELECT c.id, c.contextid, SUBSTRING(c.idnumber, LENGTH(c.prefix)+1) as name, SUBSTRING(c.idnumber, LENGTH(c.prefix)+1) as idnumber, c.description, c.descriptionformat, c.visible, c.component, c.timecreated, c.timemodified, c.theme, c.name as realname, c.idnumber as realidnumber, INSTR(c.prefix, '_r_') > 0 as readonly");
define('FROM_KEYUSER_COHORT',	 " FROM (SELECT *, REGEXP_SUBSTR(idnumber, :prefix) as prefix
                                           FROM {cohort}
                                         HAVING prefix IS NOT NULL) c");
