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
 * @copyright  2021 Jakob Heinemann, 2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Add new keyuser_cohort.
 *
 * @param  stdClass $cohort
 * @return int new cohort id
 */
function keyuser_cohort_add_cohort($cohort) {
    if (keyuser_cohort_add_prefix($cohort->name)) {
        $cohort->idnumber = $cohort->name;
        return cohort_add_cohort($cohort);
    }
    return -1;
}

/**
 * Update existing keyuser_cohort.
 *
 * @param  stdClass $cohort
 * @return void
 */
function keyuser_cohort_update_cohort($cohort) {
    if (keyuser_cohort_add_prefix($cohort->name)) {
        $cohort->idnumber = $cohort->name;
        return cohort_update_cohort($cohort);
    }
}

/**
 * Return a single keyuser_cohort as an object where the $id and keyuser conditions are met.
 *
 * @param  int $id
 * @return stdClass keyuser_cohort
 */
function keyuser_cohort_get_record($id) {
    global $DB;

    $fields = "SELECT id, contextid, SUBSTRING(idnumber, LENGTH(prefix)+1) as name, SUBSTRING(idnumber, LENGTH(prefix)+1) as idnumber, description, descriptionformat, visible, component, timecreated, timemodified, theme, name as realname, idnumber as realidnumber, INSTR(prefix, '_r_') > 0 as readonly";
    $sql = " FROM (SELECT *, REGEXP_SUBSTR(idnumber, :prefix) as prefix
                     FROM {cohort}
                    WHERE id = :id
                   HAVING prefix) c";
    $params = array('id' => $id, 'prefix' => keyuser_cohort_get_prefix_regexp());

    return $DB->get_record_sql($fields . $sql, $params, MUST_EXIST);
}

/**
 * Get all keyuser_cohorts as an array.
 *
 * @return array of keyuser_cohorts
 */
function keyuser_cohort_get_records() {
    global $DB;

    $fields = "SELECT id, contextid, SUBSTRING(idnumber, LENGTH(prefix)+1) as name, SUBSTRING(idnumber, LENGTH(prefix)+1) as idnumber, description, descriptionformat, visible, component, timecreated, timemodified, theme, name as realname, idnumber as realidnumber, INSTR(prefix, '_r_') > 0 as readonly";
    $sql = " FROM (SELECT *, REGEXP_SUBSTR(idnumber, :prefix) as prefix
                     FROM {cohort}
                   HAVING prefix) c";
    $params = array('prefix' => keyuser_cohort_get_prefix_regexp());
    $order = " ORDER BY name ASC";

    return $DB->get_records_sql($fields . $sql . $order, $params);
}

/**
 * Test whether a keyuser_cohort exists with given idnumber.
 *
 * @param  int $idnumber
 * @return bool true if a keyuser_cohort with given idnumber exists, else false
 */
function keyuser_cohort_record_exists($idnumber) {
    global $DB;

    keyuser_cohort_add_prefix($idnumber);
    return $DB->record_exists('cohort', array('idnumber' => $idnumber));
}

/**
 * Get all the keyuser_cohorts defined in given context.
 *
 * The function does not check user capability to view/manage cohorts in the given context
 * assuming that it has been already verified.
 *
 * @param int $contextid
 * @param int $page number of the current page
 * @param int $perpage items per page
 * @param string $search search string
 * @return array    Array(totalcohorts => int, keyuser_cohorts => array, allcohorts => int)
 */
function keyuser_cohort_get_cohorts($contextid, $page = 0, $perpage = 25, $search = '') {
    global $DB;
/*
    $columns = $DB->get_columns('cohort');
    unset($columns['name']);
    unset($columns['idnumber']);
    $str = implode(", ", array_keys($columns));
    print($str);
*/
    $fields = "SELECT id, contextid, SUBSTRING(idnumber, LENGTH(prefix)+1) as name, SUBSTRING(idnumber, LENGTH(prefix)+1) as idnumber, description, descriptionformat, visible, component, timecreated, timemodified, theme, name as realname, idnumber as realidnumber, INSTR(prefix, '_r_') > 0 as readonly";
    $countfields = "SELECT COUNT(1)";
    $sql = " FROM (SELECT *, REGEXP_SUBSTR(idnumber, :prefix) as prefix
                     FROM {cohort}
                    WHERE contextid = :contextid";
    $having = " HAVING prefix) c";
    $params = array('prefix' => keyuser_cohort_get_prefix_regexp(), 'contextid' => $contextid);
    $order = " ORDER BY name ASC, idnumber ASC";

    $totalcohorts = $allcohorts = $DB->count_records_sql($countfields . $sql . $having, $params);

    if (!empty($search)) {
        list($searchcondition, $searchparams) = cohort_get_search_query($search);
        $sql .= ' AND ' . $searchcondition;
        $params = array_merge($params, $searchparams);
    }

    if (!empty($search)) {
        $totalcohorts = $DB->count_records_sql($countfields . $sql . $having, $params);
    }
    $cohorts = $DB->get_records_sql($fields . $sql . $having . $order, $params, $page*$perpage, $perpage);

    return array('totalcohorts' => $totalcohorts, 'cohorts' => $cohorts, 'allcohorts' => $allcohorts);
}

/**
 * Get all the keyuser_cohorts defined anywhere in system.
 *
 * The function assumes that user capability to view/manage cohorts on system level
 * has already been verified. This function only checks if such capabilities have been
 * revoked in child (categories) contexts.
 *
 * @param int $page number of the current page
 * @param int $perpage items per page
 * @param string $search search string
 * @return array    Array(totalcohorts => int, keyuser_cohorts => array, allcohorts => int)
 */
function keyuser_cohort_get_all_cohorts($page = 0, $perpage = 25, $search = '') {
    global $DB;

    $fields = "SELECT id, contextid, SUBSTRING(idnumber, LENGTH(prefix)+1) as name, SUBSTRING(idnumber, LENGTH(prefix)+1) as idnumber, description, descriptionformat, visible, component, timecreated, timemodified, theme, name as realname, idnumber as realidnumber, INSTR(prefix, '_r_') > 0 as readonly, ".context_helper::get_preload_record_columns_sql('ctx');
    $countfields = "SELECT COUNT(*)";
    $sql = " FROM (SELECT *, REGEXP_SUBSTR(idnumber, :prefix) as prefix
                     FROM {cohort}";
    $having = " HAVING prefix) c";
    $join = " JOIN {context} ctx ON ctx.id = c.contextid ";
    $params = array('prefix' => keyuser_cohort_get_prefix_regexp());
    $wheresql = '';

    if ($excludedcontexts = cohort_get_invisible_contexts()) {
        list($excludedsql, $excludedparams) = $DB->get_in_or_equal($excludedcontexts, SQL_PARAMS_NAMED, 'excl', false);
        $wheresql = ' WHERE c.contextid '.$excludedsql;
        $params = array_merge($params, $excludedparams);
    }

    $totalcohorts = $allcohorts = $DB->count_records_sql($countfields . $sql . $wheresql . $having . $join, $params);

    if (!empty($search)) {
        list($searchcondition, $searchparams) = cohort_get_search_query($search, 'c');
        $wheresql .= ($wheresql ? ' AND ' : ' WHERE ') . $searchcondition;
        $params = array_merge($params, $searchparams);
        $totalcohorts = $DB->count_records_sql($countfields . $sql . $wheresql . $having . $join, $params);
    }

    $order = " ORDER BY suffix ASC";
    $cohorts = $DB->get_records_sql($fields . $sql . $wheresql . $having . $join . $order, $params, $page*$perpage, $perpage);

    // Preload used contexts, they will be used to check view/manage/assign capabilities and display categories names.
    foreach (array_keys($cohorts) as $key) {
        context_helper::preload_from_record($cohorts[$key]);
    }

    return array('totalcohorts' => $totalcohorts, 'cohorts' => $cohorts, 'allcohorts' => $allcohorts);
}

/**
 * Returns the list of keyuser_cohorts visible to the current user in the given course.
 *
 * The following fields are returned in each record: id, name, contextid, idnumber, visible
 * Fields memberscnt and enrolledcnt will be also returned if requested
 *
 * @param context $currentcontext
 * @param int $withmembers one of the COHORT_XXX constants that allows to return non empty cohorts only
 *      or cohorts with enroled/not enroled users, or just return members count
 * @param int $offset
 * @param int $limit
 * @param string $search
 * @return array
 */
function keyuser_cohort_get_available_cohorts($currentcontext, $withmembers = 0, $offset = 0, $limit = 25, $search = '') {
    global $DB;

    $params = array();

    // Build context subquery. Find the list of parent context where user is able to see any or visible-only cohorts.
    // Since this method is normally called for the current course all parent contexts are already preloaded.
    $contextsany = array_filter($currentcontext->get_parent_context_ids(),
        function($a) {
            return has_capability("local/keyuser:cohortview", context::instance_by_id($a));
        });
    $contextsvisible = array_diff($currentcontext->get_parent_context_ids(), $contextsany);
    if (empty($contextsany) && empty($contextsvisible)) {
        // User does not have any permissions to view cohorts.
        return array();
    }
    $subqueries = array();
    if (!empty($contextsany)) {
        list($parentsql, $params1) = $DB->get_in_or_equal($contextsany, SQL_PARAMS_NAMED, 'ctxa');
        $subqueries[] = 'c.contextid ' . $parentsql;
        $params = array_merge($params, $params1);
    }
    if (!empty($contextsvisible)) {
        list($parentsql, $params1) = $DB->get_in_or_equal($contextsvisible, SQL_PARAMS_NAMED, 'ctxv');
        $subqueries[] = '(c.visible = 1 AND c.contextid ' . $parentsql. ')';
        $params = array_merge($params, $params1);
    }
    $wheresql = '(' . implode(' OR ', $subqueries) . ')';

    keyuser_cohort_append_where($wheresql,$params);

    // Build the rest of the query.
    $fromsql = "";
    $fieldssql = 'c.id, c.name, c.contextid, c.idnumber, c.visible';
    $groupbysql = '';
    $havingsql = '';
    if ($withmembers) {
        $fieldssql .= ', s.memberscnt';
        $subfields = "c.id, COUNT(DISTINCT cm.userid) AS memberscnt";
        $groupbysql = " GROUP BY c.id";
        $fromsql = " LEFT JOIN {cohort_members} cm ON cm.cohortid = c.id ";
        if (in_array($withmembers,
                array(COHORT_COUNT_ENROLLED_MEMBERS, COHORT_WITH_ENROLLED_MEMBERS_ONLY, COHORT_WITH_NOTENROLLED_MEMBERS_ONLY))) {
            list($esql, $params2) = get_enrolled_sql($currentcontext);
            $fromsql .= " LEFT JOIN ($esql) u ON u.id = cm.userid ";
            $params = array_merge($params2, $params);
            $fieldssql .= ', s.enrolledcnt';
            $subfields .= ', COUNT(DISTINCT u.id) AS enrolledcnt';
        }
        if ($withmembers == COHORT_WITH_MEMBERS_ONLY) {
            $havingsql = " HAVING COUNT(DISTINCT cm.userid) > 0";
        } else if ($withmembers == COHORT_WITH_ENROLLED_MEMBERS_ONLY) {
            $havingsql = " HAVING COUNT(DISTINCT u.id) > 0";
        } else if ($withmembers == COHORT_WITH_NOTENROLLED_MEMBERS_ONLY) {
            $havingsql = " HAVING COUNT(DISTINCT cm.userid) > COUNT(DISTINCT u.id)";
        }
    }
    if ($search) {
        list($searchsql, $searchparams) = cohort_get_search_query($search);
        $wheresql .= ' AND ' . $searchsql;
        $params = array_merge($params, $searchparams);
    }

    $prefixlen = strlen(keyuser_cohort_get_prefix()) + 1;
    if ($withmembers) {
        $sql = "SELECT " . str_replace('c.', 'cohort.', $fieldssql) . "
                  FROM {cohort} cohort
                  JOIN (SELECT $subfields
                          FROM {cohort} c $fromsql
                         WHERE $wheresql $groupbysql $havingsql
                        ) s ON cohort.id = s.id";
        $sql .= " ORDER BY SUBSTRING(cohort.name,".$prefixlen."+(INSTR(SUBSTRING(cohort.name,".$prefixlen.",2),'r_')*2))";
    } else {
        $sql = "SELECT $fieldssql
                  FROM {cohort} c $fromsql
                 WHERE $wheresql";
        $sql .= " ORDER BY SUBSTRING(c.name,".$prefixlen."+(INSTR(SUBSTRING(c.name,".$prefixlen.",2),'r_')*2))";
    }


    return $DB->get_records_sql($sql, $params, $offset, $limit);
}

// cohort_get_cohort() and cohort_get_user_cohorts() need no implementation
// They are not called anywhere

/**
 * Returns navigation controls (tabtree) to be displayed on cohort management pages
 *
 * @param context $context system or category context where cohorts controls are about to be displayed
 * @param moodle_url $currenturl
 * @return null|renderable
 */
function keyuser_cohort_edit_controls(context $context, moodle_url $currenturl) {
    $tabs = array();
    $currenttab = 'view';
    $viewurl = new moodle_url('/local/keyuser/cohort/index.php', array('contextid' => $context->id));
    if (($searchquery = $currenturl->get_param('search'))) {
        $viewurl->param('search', $searchquery);
    }
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        $tabs[] = new tabobject('view', new moodle_url($viewurl, array('showall' => 0)), get_string('systemcohorts', 'cohort'));
        /* limit keyuser to system cohorts for now
         *
         * $tabs[] = new tabobject('viewall', new moodle_url($viewurl, array('showall' => 1)), get_string('allcohorts', 'cohort'));
         * if ($currenturl->get_param('showall')) {
         *     $currenttab = 'viewall';
         * }
         */
    } else {
        $tabs[] = new tabobject('view', $viewurl, get_string('cohorts', 'cohort'));
    }
    if (has_capability('local/keyuser:cohortmanage', $context)) {
        $addurl = new moodle_url('/local/keyuser/cohort/edit.php', array('contextid' => $context->id));
        $tabs[] = new tabobject('addcohort', $addurl, get_string('addcohort', 'cohort'));
        if ($currenturl->get_path() === $addurl->get_path() && !$currenturl->param('id')) {
            $currenttab = 'addcohort';
        }
/*
        $uploadurl = new moodle_url('/local/keyuser/cohort/upload.php', array('contextid' => $context->id));
        $tabs[] = new tabobject('uploadcohorts', $uploadurl, get_string('uploadcohorts', 'cohort'));
        if ($currenturl->get_path() === $uploadurl->get_path()) {
            $currenttab = 'uploadcohorts';
        }
*/
    }
    if (count($tabs) > 1) {
        return new tabtree($tabs, $currenttab);
    }
    return null;
}
