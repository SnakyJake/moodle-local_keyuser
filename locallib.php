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
 * Keyuser User/Cohort related functions and classes.
 *
 * @package    local_keyuser
 * @copyright  2021 Jakob Heinemann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');

/**
 * Cohort assignment candidates
 * @copyright 2012 Petr Skoda  {@link http://skodak.org}
 */
class keyuser_cohort_candidate_selector extends user_selector_base {
    protected $cohortid;

    public function __construct($name, $options) {
        $this->cohortid = $options['cohortid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params['cohortid'] = $this->cohortid;

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
            LEFT JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid) ";
        $wheresql = "WHERE cm.id IS NULL AND $wherecondition";

        keyuser_user_append_where($wheresql,$params, "u");

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql . $wheresql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $wheresql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }


        if ($search) {
            $groupname = get_string('potusersmatching', 'cohort', $search);
        } else {
            $groupname = get_string('potusers', 'cohort');
        }

        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['cohortid'] = $this->cohortid;
        $options['file'] = 'local/keyuser/locallib.php';
        return $options;
    }
}


/**
 * Cohort assignment candidates
 * @copyright 2012 Petr Skoda  {@link http://skodak.org}
 */
class keyuser_cohort_existing_selector extends user_selector_base {
    protected $cohortid;

    public function __construct($name, $options) {
        $this->cohortid = $options['cohortid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params['cohortid'] = $this->cohortid;

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
                 JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid) ";
        $wheresql = ($wherecondition ? " WHERE ".$wherecondition : "");

        keyuser_user_append_where($wheresql,$params, "u");

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql . $wheresql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $wheresql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }


        if ($search) {
            $groupname = get_string('currentusersmatching', 'cohort', $search);
        } else {
            $groupname = get_string('currentusers', 'cohort');
        }

        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['cohortid'] = $this->cohortid;
        $options['file'] = 'local/keyuser/locallib.php';
        return $options;
    }
}

function keyuser_user_append_where(&$wheresql,&$params, $usertable = "{user}"){
    $wheresql .= ($wheresql ? ' AND ' : ' WHERE ') . keyuser_user_where($params,$usertable);
}

function keyuser_cohort_append_where(&$wheresql, &$params){
    $wheresql .= ($wheresql ? ' AND ' : ' WHERE ') . keyuser_cohort_where($params);
}


/*
 * the following functions need to be edited for customizing the keyuser!
 * (in special cases! Actually no need for it anymore, I try to implement everything for this in the settings.)
 */

class keyuser_config {
     private $_cfg = null;
     public $linked_fields = [];
     public $linked_field_ids = [];
     public $cohort_prefix_fields = [];
     public $cohort_prefix_field_ids = [];
     public $linked_default = [];
     public $cohort_prefix_default = [];
     public $no_prefix_allowed = false;

     function __construct(){
        global $DB,$USER;

        $this->_cfg = get_config('local_keyuser');

        if(property_exists($this->_cfg,'no_prefix_allowed')){
            $this->no_prefix_allowed = $this->_cfg->no_prefix_allowed;
        }
        if(property_exists($this->_cfg,'linkedfieldsdefault') && $this->_cfg->linkedfieldsdefault){
            $this->linked_default = explode(",",$this->_cfg->linkedfieldsdefault);
        }
        if(property_exists($this->_cfg,'cohortprefixfieldsdefault') && $this->_cfg->cohortprefixfieldsdefault){
            $this->cohort_prefix_default = explode(",",$this->_cfg->cohortprefixfieldsdefault);
        }
        $roles = $DB->get_records('role_assignments', ['userid' => $USER->id]);
        //$roles = get_user_roles(context_system::instance(), $USER->id);
        foreach($roles as $role){
            $roleenabled = 'roleenabled'.$role->roleid;
            if(property_exists($this->_cfg,$roleenabled) && $this->_cfg->$roleenabled){
                $property = 'linkedfields'.$role->roleid;
                if(property_exists($this->_cfg,$property) && $this->_cfg->$property){
                    $this->linked_field_ids = array_unique(array_merge($this->linked_field_ids = explode(",",$this->_cfg->$property)));
                    foreach($this->linked_field_ids as $field_id){
                        $this->linked_fields[$field_id] = $DB->get_record('user_info_field', ['id' => $field_id], '*');
                    }
                }
                $property = 'cohortprefixfields'.$role->roleid;
                if(property_exists($this->_cfg,$property) && $this->_cfg->$property){
                    $this->cohort_prefix_field_ids = array_unique(array_merge($this->cohort_prefix_field_ids,explode(",",$this->_cfg->$property)));
                    foreach($this->cohort_prefix_field_ids as $field_id){
                        $this->cohort_prefix_fields[$field_id] = $DB->get_record('user_info_field', ['id' => $field_id], '*');
                    }
                }
            }
        }
        if(empty($this->linked_field_ids)){
            $this->linked_field_ids = $this->linked_default;
            foreach($this->linked_field_ids as $field_id){
                $this->linked_fields[$field_id] = $DB->get_record('user_info_field', ['id' => $field_id], '*');
            }
        }
        if(empty($this->cohort_prefix_field_ids)){ 
            $this->cohort_prefix_field_ids = $this->cohort_prefix_default;
            foreach($this->cohort_prefix_field_ids as $field_id){
                $this->cohort_prefix_fields[$field_id] = $DB->get_record('user_info_field', ['id' => $field_id], '*');
            }
        }
    }
}

unset($KEYUSER_CFG);
global $KEYUSER_CFG;
$KEYUSER_CFG = new keyuser_config();

function keyuser_user_where(&$params,$usertable=null){
    global $DB,$USER,$KEYUSER_CFG;
    $sql = ($usertable?$usertable.".":"")."id IN (SELECT userid FROM (SELECT userid,count(userid) as cnt FROM {user_info_data} WHERE";
    $wheresql = '';
    $has_empty_field = false;
    foreach($KEYUSER_CFG->linked_fields as $field){
        $wheresql .= ($wheresql ? " OR " : "")." (fieldid=:fieldid".$field->id . " AND ".$DB->sql_like('data',':data'.$field->id).")";
        $params["fieldid".$field->id] = $field->id;
        $params["data".$field->id] = $DB->sql_like_escape($USER->profile[$field->shortname]);
        $has_empty_field = $has_empty_field || !$USER->profile[$field->shortname];
    }
    if($has_empty_field){
        $wheresql .= ($wheresql ? " AND ":"")."1=2";
    }
    if($wheresql == ''){
        return "1 = 2";
    }
    return $sql . $wheresql . " GROUP BY userid HAVING cnt=".count($KEYUSER_CFG->linked_fields).") userdata)";
}

function keyuser_cohort_remove_rights(&$cohortname){
    if(substr($cohortname,0,2) == "r_"){
        $cohortname = substr($cohortname,2,strlen($cohortname));
    }
}

function keyuser_cohort_is_readonly($cohortname){
    keyuser_cohort_remove_prefix($cohortname,false);
    if(substr($cohortname,0,2) != "r_"){
        return false;
    }
    return true;
}

function keyuser_cohort_get_prefix(){
    global $KEYUSER_CFG,$USER;
    $prefix = '';
    foreach($KEYUSER_CFG->cohort_prefix_fields as $field){
        if(!$USER->profile[$field->shortname]){
            //disable "no_prefix_allowed" if prefix fields are chosen!
            $KEYUSER_CFG->no_prefix_allowed = false;
            return false;
        }
        $prefix .= $USER->profile[$field->shortname]."_";
    }
    return $prefix;
}

function keyuser_cohort_where(&$params){
    global $KEYUSER_CFG,$DB;

    $prefix = keyuser_cohort_get_prefix();
    
    if(!$prefix && !$KEYUSER_CFG->no_prefix_allowed){
        return "1=2";
    }
    $params['prefix'] = $DB->sql_like_escape($prefix)."%";
    return $DB->sql_like('idnumber',':prefix');
}

function keyuser_cohort_add_prefix(&$cohortname){
    global $KEYUSER_CFG;

    $prefix = keyuser_cohort_get_prefix();
    if($prefix){
        if(substr($cohortname, 0, strlen($prefix)) != $prefix){
            $cohortname = $prefix . $cohortname;
        }
        return true;
    }
    return $KEYUSER_CFG->no_prefix_allowed?true:false;
}                                                                                                                                                                              

function keyuser_cohort_remove_prefix(&$cohortname,$removerights = true){
    global $KEYUSER_CFG;

    $prefix = keyuser_cohort_get_prefix();

    if($prefix){
        $len = strlen($prefix);
        if(substr($cohortname, 0, $len) == $prefix){
            $cohortname = substr($cohortname, $len, strlen($cohortname));
        }
        if($removerights){
            keyuser_cohort_remove_rights($cohortname);
        }
        return true;
    }
    return $KEYUSER_CFG->no_prefix_allowed?true:false;
}

