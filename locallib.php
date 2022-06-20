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

/*
 * the following functions need to be edited for customizing the keyuser!
 * (in special cases! Actually no need for it anymore, I try to implement everything for this in the settings.)
 */

class keyuser_config {
     private $_cfg = null;
     public $linked_fields = [];
     public $linked_fieldsmulti = [];
     public $linked_field_ids = [];
     public $cohort_prefix_fields = [];
     public $cohort_prefix_fieldsmulti = [];
     public $cohort_prefix_field_ids = [];
     public $linked_default = [];
     public $linked_multi_default = [];
     public $cohort_prefix_default = [];
     public $cohort_prefix_multi_default = [];
     public $no_prefix_allowed = false;
     public $roles_enabled = [];

     function __construct(){
        global $DB,$USER;

        $this->_cfg = get_config('local_keyuser');

        if(property_exists($this->_cfg,'no_prefix_allowed')){
            $this->no_prefix_allowed = $this->_cfg->no_prefix_allowed;
        }
        if(property_exists($this->_cfg,'linkedfieldsdefault') && $this->_cfg->linkedfieldsdefault){
            $this->linked_default = explode(",",$this->_cfg->linkedfieldsdefault);
        }
        if(property_exists($this->_cfg,'linkedfieldsmultidefault') && $this->_cfg->linkedfieldsmultidefault){
            $this->linked_multi_default = explode(",",$this->_cfg->linkedfieldsmultidefault);
        }
        if(property_exists($this->_cfg,'cohortprefixfieldsdefault') && $this->_cfg->cohortprefixfieldsdefault){
            $this->cohort_prefix_default = explode(",",$this->_cfg->cohortprefixfieldsdefault);
        }
        if(property_exists($this->_cfg,'cohortprefixfieldsmultidefault') && $this->_cfg->cohortprefixfieldsmultidefault){
            $this->cohort_prefix_multi_default = explode(",",$this->_cfg->cohortprefixfieldsmultidefault);
        }

        //actually we think, any keyuser should have the permission to update users
        array_filter(get_roles_with_capability("local/keyuser:userupdate", CAP_ALLOW, context_system::instance()),
            function($var){
                $this->roles_enabled[$var->id] = $var->id;
            });
        //or at least view the cohorts
        array_filter(get_roles_with_capability("local/keyuser:cohortview", CAP_ALLOW, context_system::instance()),
            function($var){
                $this->roles_enabled[$var->id] = $var->id;
            });

        //$roles = $DB->get_records('role_assignments', ['userid' => $USER->id]);
        $roles = get_user_roles(context_system::instance(), $USER->id);
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
                $property = 'linkedfieldsmulti'.$role->roleid;
                if(property_exists($this->_cfg,$property) && $this->_cfg->$property){
                    $this->linked_fieldsmulti = array_unique(array_merge($this->linked_fieldsmulti = explode(",",$this->_cfg->$property)));
                }
                $property = 'cohortprefixfieldsmulti'.$role->roleid;
                if(property_exists($this->_cfg,$property) && $this->_cfg->$property){
                    $this->cohort_prefix_fieldsmulti = array_unique(array_merge($this->cohort_prefix_fieldsmulti,explode(",",$this->_cfg->$property)));
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
        if(empty($this->linked_fieldsmulti)){
            $this->linked_fieldsmulti = $this->linked_multi_default;
        }
        if(empty($this->cohort_prefix_fieldsmulti)){
            $this->cohort_prefix_fieldsmulti = $this->cohort_prefix_multi_default;
        }
    }
}

unset($KEYUSER_CFG);
global $KEYUSER_CFG;
$KEYUSER_CFG = new keyuser_config();

function get_keyusers($ignore_self = false){
    global $KEYUSER_CFG,$DB,$USER;
    list($insql, $params) = $DB->get_in_or_equal($KEYUSER_CFG->roles_enabled, SQL_PARAMS_NAMED, 'roleid');
    $params['contextid'] = context_system::instance()->id;
    $wheresql = " WHERE ra.contextid = :contextid AND ra.roleid " . $insql;
    if($ignore_self){
        $wheresql .= " AND u.id != :self_userid ";
        $params["self_userid"] = $USER->id;
    }
    $wheresql .= " AND " . keyuser_user_where($params,'u');
    $sql = 'SELECT u.* FROM {user} u JOIN {role_assignments} ra on u.id = ra.userid'.$wheresql;

    $keyusers = $DB->get_records_sql($sql, $params);
    return $keyusers;
}

function keyuser_user_append_where(&$wheresql,&$params, $usertable = "{user}"){
    $wheresql .= ($wheresql ? ' AND ' : ' WHERE ') . keyuser_user_where($params,$usertable);
}

function keyuser_user_where(&$params,$usertable=null){
    global $DB,$USER,$KEYUSER_CFG,$SESSION;
    $sql = ($usertable?$usertable.".":"")."id IN (SELECT userid FROM (SELECT userid,count(userid) as cnt FROM {user_info_data} WHERE";
    $wheresql = '';
    $has_empty_field = false;
    foreach($KEYUSER_CFG->linked_fields as $field){
        if(!array_key_exists($field->shortname,$USER->profile)){
            break;
        }
        $wheresql .= ($wheresql ? " OR " : "")." (fieldid=:fieldid".$field->id;
        $params["fieldid".$field->id] = $field->id;
        $fieldvalue = $USER->profile[$field->shortname];
        if(keyuser_is_multivalue($field,$fieldvalue,$KEYUSER_CFG->linked_fieldsmulti)){
            $inputname = 'keyuser_linkedfield_'.$field->id;
            $keyuser_linkedfield = optional_param($inputname, "", PARAM_TEXT);
            if(empty($keyuser_linkedfield) && array_key_exists($keyuser_linkedfield,$SESSION)){
                $keyuser_linkedfield = $SESSION->$inputname;
            } else {
                $SESSION->$inputname = $keyuser_linkedfield;
            }
            $wheresql .= " AND json_valid({user_info_data}.data) AND (";
            if(empty($keyuser_linkedfield)){
                $keyuser_linkedfield = $USER->profile[$field->shortname];
            } else {
                $keyuser_linkedfield = "[\"".$keyuser_linkedfield."\"]";
            }
            $wheresql .= "JSON_OVERLAPS({user_info_data}.data,:data".$field->id.")))";
            $params["data".$field->id] = $keyuser_linkedfield;
        } else {
            $wheresql .= " AND data = :data".$field->id.")";
            $params["data".$field->id] = $DB->sql_like_escape(is_array($fieldvalue)?json_encode($fieldvalue):$fieldvalue);
        }
        $has_empty_field = $has_empty_field || empty($fieldvalue);
    }
    if($has_empty_field){
        $wheresql .= ($wheresql ? " AND ":"")."1=2";
    }
    if($wheresql == ''){
        return "1 = 2";
    }
    return $sql . $wheresql . " GROUP BY userid HAVING cnt=".count($KEYUSER_CFG->linked_fields).") userdata)";
}

/**
 * Return prefix of current $USER.
 *
 * @param  bool $regexp
 * @return string|bool prefix of $USER
 */
function keyuser_cohort_get_prefix($regexp = false){
    global $KEYUSER_CFG,$USER,$SESSION;

    $prefix = $regexp?'^':'';
    $divider = $regexp?"_(r_)?":"_";

    foreach($KEYUSER_CFG->cohort_prefix_fields as $field){
        if(empty($USER->profile[$field->shortname])){
            //disable "no_prefix_allowed" if prefix fields are chosen!
            $KEYUSER_CFG->no_prefix_allowed = false;
            return false;
        }
        $fieldvalue = $USER->profile[$field->shortname];
        if(keyuser_is_multivalue($field,$fieldvalue,$KEYUSER_CFG->cohort_prefix_fieldsmulti)){
            $inputname = 'keyuser_prefix_'.$field->id;
            $keyuser_prefix = optional_param($inputname, "", PARAM_TEXT);
            if(empty($keyuser_prefix) && array_key_exists($inputname,$SESSION)){
                $keyuser_prefix = $SESSION->$inputname;
            } else {
                $SESSION->$inputname = $keyuser_prefix;
            }
            if(empty($keyuser_prefix)){
                $keyuser_prefix = $fieldvalue[0];
            }
            $prefix .= $keyuser_prefix.$divider;
        } else {
            $prefix .= (is_array($fieldvalue)?implode($divider,$fieldvalue):$fieldvalue).$divider;
        }
    }
    return $prefix;
}

/**
 * Prepend prefix to $cohortname
 *
 * @param  string $cohortname
 * @return bool
 */
function keyuser_cohort_add_prefix(&$cohortname){
    $prefix = keyuser_cohort_get_prefix();
    if($prefix){
        if(substr($cohortname, 0, strlen($prefix)) != $prefix){
            $cohortname = $prefix . $cohortname;
        }
        return true;
    }
    return false;
}

/**
 * Remove prefix of $cohortname
 *
 * @param  string $cohortname
 * @return void
 */
function keyuser_cohort_remove_prefix(&$cohortname){
    $prefix_regexp = keyuser_cohort_get_prefix(true);
    if($prefix_regexp){
        $cohortname = preg_replace('!'.preg_quote($prefix_regexp).'!Ai', '', $cohortname, 1);
    }
}

function keyuser_cohort_prefix_options_for_select(){
    global $KEYUSER_CFG,$USER,$SESSION;
    $options = [];
    foreach($KEYUSER_CFG->cohort_prefix_fields as $field){
        $inputname = 'keyuser_prefix_'.$field->id;
        $fieldvalue = $USER->profile[$field->shortname];
        $keyuser_prefix = optional_param($inputname, "", PARAM_TEXT);
        if(empty($keyuser_prefix) && array_key_exists($inputname,$SESSION)){
            $keyuser_prefix = $SESSION->$inputname;
        } else {
            $SESSION->$inputname = $keyuser_prefix;
        }
        keyuser_is_multivalue($field,$fieldvalue,$KEYUSER_CFG->cohort_prefix_fieldsmulti);
        $options[$inputname] = is_array($fieldvalue)?array_combine($fieldvalue,$fieldvalue):$fieldvalue;
    }
    return $options;
}

function keyuser_cohort_prefix_select($url='index.php'){
    global $OUTPUT,$KEYUSER_CFG,$USER,$SESSION;
    $result = "";
    foreach($KEYUSER_CFG->cohort_prefix_fields as $field){
        $fieldvalue = $USER->profile[$field->shortname];
        if(keyuser_is_multivalue($field,$fieldvalue,$KEYUSER_CFG->cohort_prefix_fieldsmulti) && count($fieldvalue) > 1){
            $inputname = 'keyuser_prefix_'.$field->id;
            $keyuser_prefix = optional_param($inputname, "", PARAM_TEXT);
            if(empty($keyuser_prefix) && array_key_exists($inputname,$SESSION)){
                $keyuser_prefix = $SESSION->$inputname;
            } else {
                $SESSION->$inputname = $keyuser_prefix;
            }
            $data = [
                'name' => 'keyuser_prefix_'.$field->id,
                'method' => 'post',
                'action' => new moodle_url('/local/keyuser/cohort/'.$url),
                'inputname' => $inputname,
                'label' => get_string("label_cohort_prefix_select", "local_keyuser"),
                'id' => 'keyuser_form_cohort_prefix_'.$field->id,
                'formid' => 'keyuser_form_cohort_prefix_'.$field->id,
                'options' => [],
            ];
            foreach($fieldvalue as $value){
                if(!$keyuser_prefix){
                    $keyuser_prefix = $value;
                    $SESSION->$inputname = $keyuser_prefix;
                }
                $data['options'][] = [
                    'value' => $value,
                    'name' => $value,
                    'selected' => $value == $keyuser_prefix,
                ];
            }

            $result .= $OUTPUT->render_from_template('core/single_select', $data);
        }
    }
    return $result;
}

function keyuser_linkedfield_select($url='user.php'){
    global $OUTPUT,$KEYUSER_CFG,$USER,$SESSION;
    $result = "";
    foreach($KEYUSER_CFG->linked_fields as $field){
        $fieldvalue = $USER->profile[$field->shortname];
        if(keyuser_is_multivalue($field,$fieldvalue,$KEYUSER_CFG->linked_fieldsmulti) && count($fieldvalue)>1){
            $inputname = 'keyuser_linkedfield_'.$field->id;
            $keyuser_linkedfield = optional_param($inputname, "", PARAM_TEXT);
            if(empty($keyuser_linkedfield) && !isset($_POST[$inputname]) && array_key_exists($inputname,$SESSION)){
                $keyuser_linkedfield = $SESSION->$inputname;
            } else {
                $SESSION->$inputname = $keyuser_linkedfield;
            }
            $data = [
                'name' => 'keyuser_linkedfield_'.$field->id,
                'method' => 'post',
                'action' => new moodle_url('/local/keyuser/admin/'.$url),
                'inputname' => $inputname,
                'label' => get_string("label_linkedfield_select", "local_keyuser"),
                'id' => 'keyuser_form_linkedfield_'.$field->id,
                'formid' => 'keyuser_form_linkedfield_'.$field->id,
                'options' => [['value' => "",'name'=>get_string("all"),'selected'=>$keyuser_linkedfield===0]],
            ];
            foreach($fieldvalue as $value){
                $data['options'][] = [
                    'value' => $value,
                    'name' => $value,
                    'selected' => $value == $keyuser_linkedfield,
                ];
            }

            $result .= $OUTPUT->render_from_template('core/single_select', $data);
        }
    }
    return $result;
}

//returns array or string
function keyuser_is_multivalue($field,&$value,$multiconfig){
    global $USER;
    $tmp = json_decode($USER->profile[$field->shortname]);
    $err = json_last_error();
    if($err === JSON_ERROR_NONE){
        $value = $tmp;
    } elseif ($err === JSON_ERROR_SYNTAX) {
        $value = $USER->profile[$field->shortname];
    }
    return is_array($value) && in_array($field->id,$multiconfig);
}

