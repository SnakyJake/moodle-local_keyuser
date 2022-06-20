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
 * @copyright  2021 Jakob Heinemann, 2022 Fabian Bech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/keyuser/locallib.php');

function keyuser_cohort_prefix_options_for_select() {
    global $KEYUSER_CFG, $USER, $SESSION;

    $options = [];
    foreach($KEYUSER_CFG->cohort_prefix_fields as $field) {
        $inputname = 'keyuser_prefix_'.$field->id;
        $fieldvalue = $USER->profile[$field->shortname];
        $keyuser_prefix = optional_param($inputname, "", PARAM_TEXT);
        if(empty($keyuser_prefix) && array_key_exists($inputname, $SESSION)) {
            $keyuser_prefix = $SESSION->$inputname;
        } else {
            $SESSION->$inputname = $keyuser_prefix;
        }
        keyuser_is_multivalue($field, $fieldvalue, $KEYUSER_CFG->cohort_prefix_fieldsmulti);
        $options[$inputname] = is_array($fieldvalue)?array_combine($fieldvalue, $fieldvalue):$fieldvalue;
    }
    return $options;
}

function keyuser_cohort_prefix_select($url = 'index.php') {
    global $OUTPUT, $KEYUSER_CFG, $USER, $SESSION;

    $result = "";
    foreach($KEYUSER_CFG->cohort_prefix_fields as $field) {
        $fieldvalue = $USER->profile[$field->shortname];
        if(keyuser_is_multivalue($field, $fieldvalue, $KEYUSER_CFG->cohort_prefix_fieldsmulti) && count($fieldvalue) > 1) {
            $inputname = 'keyuser_prefix_'.$field->id;
            $keyuser_prefix = optional_param($inputname, "", PARAM_TEXT);
            if(empty($keyuser_prefix) && array_key_exists($inputname, $SESSION)) {
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
            foreach($fieldvalue as $value) {
                if(!$keyuser_prefix) {
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

function keyuser_linkedfield_select($url = 'user.php') {
    global $OUTPUT, $KEYUSER_CFG, $USER, $SESSION;

    $result = "";
    foreach($KEYUSER_CFG->linked_fields as $field) {
        $fieldvalue = $USER->profile[$field->shortname];
        if(keyuser_is_multivalue($field, $fieldvalue, $KEYUSER_CFG->linked_fieldsmulti) && count($fieldvalue)>1) {
            $inputname = 'keyuser_linkedfield_'.$field->id;
            $keyuser_linkedfield = optional_param($inputname, "", PARAM_TEXT);
            if(empty($keyuser_linkedfield) && !isset($_POST[$inputname]) && array_key_exists($inputname, $SESSION)) {
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
                'options' => [['value' => "", 'name'=>get_string("all"), 'selected'=>$keyuser_linkedfield===0]],
            ];
            foreach($fieldvalue as $value) {
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
