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
 * Bulk export user into any dataformat
 *
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright  2021 Jakob Heinemann, 2007 Petr Skoda
 * @package    core
 */

define('NO_OUTPUT_BUFFERING', true);
require_once('../../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once($CFG->dirroot.'/local/keyuser/locallib.php');

$dataformat = optional_param('dataformat', '', PARAM_ALPHA);

admin_externalpage_setup('keyuser_userbulk');
require_capability('local/keyuser:userupdate', context_system::instance());

if (empty($SESSION->bulk_users)) {
    redirect(new moodle_url('/local/keyuser/admin/user/user_bulk.php'));
}

if ($dataformat) {

    global $KEYUSER_CFG;

    $fields = array('id'        => 'id',
                    'username'  => 'username',
                    'email'     => 'email',
                    'firstname' => 'firstname',
                    'lastname'  => 'lastname',
                    'idnumber'  => 'idnumber',
                    'institution' => 'institution',
                    'department' => 'department',
                    'phone1'    => 'phone1',
                    'phone2'    => 'phone2',
                    'city'      => 'city',
                    'country'   => 'country');

    if ($extrafields = $DB->get_records('user_info_field')) {
        foreach ($extrafields as $n => $field) {
            if(array_search($field->id, $KEYUSER_CFG->linked_field_ids) === false){
                $fields['profile_field_'.$field->shortname] = 'profile_field_'.$field->shortname;
                require_once($CFG->dirroot.'/user/profile/field/'.$field->datatype.'/field.class.php');
            }
        }
    }

    $filename = clean_filename(get_string('users'));

    $downloadusers = new ArrayObject($SESSION->bulk_users);
    $iterator = $downloadusers->getIterator();

    \core\dataformat::download_data($filename, $dataformat, $fields, $iterator, function($userid) use ($extrafields, $fields, $KEYUSER_CFG) {
        global $DB;

        $sql = 'SELECT * FROM {user} ';
        $wheresql = 'WHERE id=:id';
        $params = array('id' => $userid);
        keyuser_user_append_where($wheresql,$params);

        if (!$user = $DB->get_record_sql($sql . $wheresql, $params)) {
            return null;
        }
        foreach ($extrafields as $field) {
            if(array_search($field->id, $KEYUSER_CFG->linked_field_ids) === false){
                $newfield = 'profile_field_'.$field->datatype;
                $formfield = new $newfield($field->id, $user->id);
                $formfield->edit_load_user_data($user);
            }
        }
        $userprofiledata = array();
        foreach ($fields as $field => $unused) {
            // Custom user profile textarea fields come in an array
            // The first element is the text and the second is the format.
            // We only take the text.
            if (is_array($user->$field)) {
                $userprofiledata[$field] = reset($user->$field);
            } else {
                $userprofiledata[$field] = $user->$field;
            }
        }
        return $userprofiledata;
    });

    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('download', 'admin'));
echo $OUTPUT->download_dataformat_selector(get_string('userbulkdownload', 'admin'), 'user_bulk_download.php');
echo $OUTPUT->footer();

