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
 * @language   de
 */

require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/uploaduser/user_form.php');

class keyuser_admin_uploaduser_form2 extends admin_uploaduser_form2 {
    function definition () {
        global $CFG, $USER;

        $mform   = $this->_form;
        $columns = $this->_customdata['columns'];
        $data    = $this->_customdata['data'];

        // I am the template user, why should it be the administrator? we have roles now, other ppl may use this script ;-)
        $templateuser = $USER;

        // upload settings and file
        $mform->addElement('header', 'settingsheader', get_string('settings'));

        $choices = array(UU_USER_ADDNEW     => get_string('uuoptype_addnew', 'tool_uploaduser'),
                         UU_USER_ADDINC     => get_string('uuoptype_addinc', 'tool_uploaduser'),
                         UU_USER_ADD_UPDATE => get_string('uuoptype_addupdate', 'tool_uploaduser'),
                         UU_USER_UPDATE     => get_string('uuoptype_update', 'tool_uploaduser'));
        $mform->addElement('select', 'uutype', get_string('uuoptype', 'tool_uploaduser'), $choices);

        $choices = array(0 => get_string('infilefield', 'auth'), 1 => get_string('createpasswordifneeded', 'auth'));
        $mform->addElement('select', 'uupasswordnew', get_string('uupasswordnew', 'tool_uploaduser'), $choices);
        $mform->setDefault('uupasswordnew', 0);
        $mform->hideIf('uupasswordnew', 'uutype', 'eq', UU_USER_UPDATE);

        $choices = array(UU_UPDATE_NOCHANGES    => get_string('nochanges', 'tool_uploaduser'),
                         UU_UPDATE_FILEOVERRIDE => get_string('uuupdatefromfile', 'tool_uploaduser'),
                         UU_UPDATE_ALLOVERRIDE  => get_string('uuupdateall', 'tool_uploaduser'),
                         UU_UPDATE_MISSING      => get_string('uuupdatemissing', 'tool_uploaduser'));
        $mform->addElement('select', 'uuupdatetype', get_string('uuupdatetype', 'tool_uploaduser'), $choices);
        $mform->setDefault('uuupdatetype', UU_UPDATE_NOCHANGES);
        $mform->hideIf('uuupdatetype', 'uutype', 'eq', UU_USER_ADDNEW);
        $mform->hideIf('uuupdatetype', 'uutype', 'eq', UU_USER_ADDINC);

        $choices = array(0 => get_string('nochanges', 'tool_uploaduser'), 1 => get_string('update'));
        $mform->addElement('select', 'uupasswordold', get_string('uupasswordold', 'tool_uploaduser'), $choices);
        $mform->setDefault('uupasswordold', 0);
        $mform->hideIf('uupasswordold', 'uutype', 'eq', UU_USER_ADDNEW);
        $mform->hideIf('uupasswordold', 'uutype', 'eq', UU_USER_ADDINC);
        $mform->hideIf('uupasswordold', 'uuupdatetype', 'eq', 0);
        $mform->hideIf('uupasswordold', 'uuupdatetype', 'eq', 3);

        $choices = array(UU_PWRESET_WEAK => get_string('usersweakpassword', 'tool_uploaduser'),
                         UU_PWRESET_NONE => get_string('none'),
                         UU_PWRESET_ALL  => get_string('all'));
        if (empty($CFG->passwordpolicy)) {
            unset($choices[UU_PWRESET_WEAK]);
        }
        $mform->addElement('select', 'uuforcepasswordchange', get_string('forcepasswordchange', 'core'), $choices);
        $mform->setDefault('uuforcepasswordchange', 0);

        $mform->addElement('selectyesno', 'uuallowrenames', get_string('allowrenames', 'tool_uploaduser'));
        $mform->setDefault('uuallowrenames', 0);
        $mform->hideIf('uuallowrenames', 'uutype', 'eq', UU_USER_ADDNEW);
        $mform->hideIf('uuallowrenames', 'uutype', 'eq', UU_USER_ADDINC);

        $mform->addElement('selectyesno', 'uuallowdeletes', get_string('allowdeletes', 'tool_uploaduser'));
        $mform->setDefault('uuallowdeletes', 0);
        $mform->hideIf('uuallowdeletes', 'uutype', 'eq', UU_USER_ADDNEW);
        $mform->hideIf('uuallowdeletes', 'uutype', 'eq', UU_USER_ADDINC);

        $mform->addElement('selectyesno', 'uuallowsuspends', get_string('allowsuspends', 'tool_uploaduser'));
        $mform->setDefault('uuallowsuspends', 1);
        $mform->hideIf('uuallowsuspends', 'uutype', 'eq', UU_USER_ADDNEW);
        $mform->hideIf('uuallowsuspends', 'uutype', 'eq', UU_USER_ADDINC);

        if (!empty($CFG->allowaccountssameemail)) {
            $mform->addElement('selectyesno', 'uunoemailduplicates', get_string('uunoemailduplicates', 'tool_uploaduser'));
            $mform->setDefault('uunoemailduplicates', 1);
        } else {
            $mform->addElement('hidden', 'uunoemailduplicates', 1);
        }
        $mform->setType('uunoemailduplicates', PARAM_BOOL);

        $mform->addElement('selectyesno', 'uustandardusernames', get_string('uustandardusernames', 'tool_uploaduser'));
        $mform->setDefault('uustandardusernames', 0);

        $choices = array(UU_BULK_NONE    => get_string('no'),
                         UU_BULK_NEW     => get_string('uubulknew', 'tool_uploaduser'),
                         UU_BULK_UPDATED => get_string('uubulkupdated', 'tool_uploaduser'),
                         UU_BULK_ALL     => get_string('uubulkall', 'tool_uploaduser'));
        $mform->addElement('select', 'uubulk', get_string('uubulk', 'tool_uploaduser'), $choices);
        $mform->setDefault('uubulk', 0);

        // roles selection
        /* remove this, keyusers dont need it!
        $showroles = false;
        foreach ($columns as $column) {
            if (preg_match('/^type\d+$/', $column)) {
                $showroles = true;
                break;
            }
        }
        if ($showroles) {
            $mform->addElement('header', 'rolesheader', get_string('roles'));

            $choices = uu_allowed_roles(true);

            $mform->addElement('select', 'uulegacy1', get_string('uulegacy1role', 'tool_uploaduser'), $choices);
            if ($studentroles = get_archetype_roles('student')) {
                foreach ($studentroles as $role) {
                    if (isset($choices[$role->id])) {
                        $mform->setDefault('uulegacy1', $role->id);
                        break;
                    }
                }
                unset($studentroles);
            }

            $mform->addElement('select', 'uulegacy2', get_string('uulegacy2role', 'tool_uploaduser'), $choices);
            if ($editteacherroles = get_archetype_roles('editingteacher')) {
                foreach ($editteacherroles as $role) {
                    if (isset($choices[$role->id])) {
                        $mform->setDefault('uulegacy2', $role->id);
                        break;
                    }
                }
                unset($editteacherroles);
            }

            $mform->addElement('select', 'uulegacy3', get_string('uulegacy3role', 'tool_uploaduser'), $choices);
            if ($teacherroles = get_archetype_roles('teacher')) {
                foreach ($teacherroles as $role) {
                    if (isset($choices[$role->id])) {
                        $mform->setDefault('uulegacy3', $role->id);
                        break;
                    }
                }
                unset($teacherroles);
            }
        }
        */

        // default values
        $mform->addElement('header', 'defaultheader', get_string('defaultvalues', 'tool_uploaduser'));

        $mform->addElement('text', 'username', get_string('uuusernametemplate', 'tool_uploaduser'), 'size="20"');
        $mform->setType('username', PARAM_RAW); // No cleaning here. The process verifies it later.
        $mform->addRule('username', get_string('requiredtemplate', 'tool_uploaduser'), 'required', null, 'client');
        $mform->hideIf('username', 'uutype', 'eq', UU_USER_ADD_UPDATE);
        $mform->hideIf('username', 'uutype', 'eq', UU_USER_UPDATE);
        $mform->setForceLtr('username');

        $mform->addElement('text', 'email', get_string('email'), 'maxlength="100" size="30"');
        $mform->setType('email', PARAM_RAW); // No cleaning here. The process verifies it later.
        $mform->hideIf('email', 'uutype', 'eq', UU_USER_ADD_UPDATE);
        $mform->hideIf('email', 'uutype', 'eq', UU_USER_UPDATE);
        $mform->setForceLtr('email');

        // only enabled and known to work plugins
        // only allow manual for keyusers
        //$choices = uu_supported_auths();
        //$mform->addElement('select', 'auth', get_string('chooseauthmethod','auth'), $choices);
        //$mform->setDefault('auth', 'manual'); // manual is a sensible backwards compatible default
        //$mform->addHelpButton('auth', 'chooseauthmethod', 'auth');
        //$mform->setAdvanced('auth');
        $mform->addElement('hidden', 'auth', 'manual');
        $mform->setType('auth',PARAM_TEXT);

        // always hide mail
        /*
        $choices = array(0 => get_string('emaildisplayno'), 1 => get_string('emaildisplayyes'), 2 => get_string('emaildisplaycourse'));
        $mform->addElement('select', 'maildisplay', get_string('emaildisplay'), $choices);
        $mform->setDefault('maildisplay', core_user::get_property_default('maildisplay'));
        $mform->addHelpButton('maildisplay', 'emaildisplay');
        */
        $mform->addElement('hidden', 'maildisplay', 0);
        $mform->setType('maildisplay',PARAM_INT);

        // disable mail messages
        /*
        $choices = array(0 => get_string('emailenable'), 1 => get_string('emaildisable'));
        $mform->addElement('select', 'emailstop', get_string('emailstop'), $choices);
        $mform->setDefault('emailstop', core_user::get_property_default('emailstop'));
        $mform->setAdvanced('emailstop');
        */
        $mform->addElement('hidden', 'emailstop', 1);
        $mform->setType('emailstop',PARAM_INT);


        /* always htmlformat
        $choices = array(0 => get_string('textformat'), 1 => get_string('htmlformat'));
        $mform->addElement('select', 'mailformat', get_string('emailformat'), $choices);
        $mform->setDefault('mailformat', core_user::get_property_default('mailformat'));
        $mform->setAdvanced('mailformat');
        */
        $mform->addElement('hidden', 'mailformat', 1);
        $mform->setType('mailformat',PARAM_INT);

        /* einzelne Forenbeiträge als Mail
        $choices = array(0 => get_string('emaildigestoff'), 1 => get_string('emaildigestcomplete'), 2 => get_string('emaildigestsubjects'));
        $mform->addElement('select', 'maildigest', get_string('emaildigest'), $choices);
        $mform->setDefault('maildigest', core_user::get_property_default('maildigest'));
        $mform->setAdvanced('maildigest');
        */
        $mform->addElement('hidden', 'maildigest', 0);
        $mform->setType('maildigest',PARAM_INT);

        /* no!
        $choices = array(1 => get_string('autosubscribeyes'), 0 => get_string('autosubscribeno'));
        $mform->addElement('select', 'autosubscribe', get_string('autosubscribe'), $choices);
        $mform->setDefault('autosubscribe', core_user::get_property_default('autosubscribe'));
        */
        $mform->addElement('hidden', 'autosubscribe', 0);
        $mform->setType('autosubscribe',PARAM_INT);

        $mform->addElement('text', 'city', get_string('city'), 'maxlength="120" size="25"');
        $mform->setType('city', PARAM_TEXT);
        
        // always take city of keyuser as default
//        if (empty($CFG->defaultcity)) {
            $mform->setDefault('city', $templateuser->city);
//        } else {
//            $mform->setDefault('city', core_user::get_property_default('city'));
//        }

        $choices = get_string_manager()->get_list_of_countries();
        $choices = array(''=>get_string('selectacountry').'...') + $choices;
        $mform->addElement('select', 'country', get_string('selectacountry'), $choices);
        
        // always take country of keyuser as default
        //if (empty($CFG->country)) {
            $mform->setDefault('country', $templateuser->country);
        //} else {
        //    $mform->setDefault('country', core_user::get_property_default('country'));
        //}
        $mform->setAdvanced('country');

        $choices = core_date::get_list_of_timezones($templateuser->timezone, true);
        $mform->addElement('select', 'timezone', get_string('timezone'), $choices);
        $mform->setDefault('timezone', $templateuser->timezone);
        $mform->setAdvanced('timezone');

        $mform->addElement('select', 'lang', get_string('preferredlanguage'), get_string_manager()->get_list_of_translations());
        $mform->setDefault('lang', $templateuser->lang);
        $mform->setAdvanced('lang');

        $editoroptions = array('maxfiles'=>0, 'maxbytes'=>0, 'trusttext'=>false, 'forcehttps'=>false);
        $mform->addElement('editor', 'description', get_string('userdescription'), null, $editoroptions);
        $mform->setType('description', PARAM_CLEANHTML);
        $mform->addHelpButton('description', 'userdescription');
        //$mform->setAdvanced('description');

        // no need
        /*
        $mform->addElement('text', 'url', get_string('webpage'), 'maxlength="255" size="50"');
        $mform->setType('url', PARAM_URL);
        $mform->setAdvanced('url');

        $mform->addElement('text', 'idnumber', get_string('idnumber'), 'maxlength="255" size="25"');
        $mform->setType('idnumber', core_user::get_property_type('idnumber'));
        $mform->setForceLtr('idnumber');
        */

        $mform->addElement('text', 'institution', get_string('institution'), 'maxlength="255" size="25"');
        $mform->setType('institution', PARAM_TEXT);
        $mform->setDefault('institution', $templateuser->institution);

        $mform->addElement('text', 'department', get_string('department'), 'maxlength="255" size="25"');
        $mform->setType('department', PARAM_TEXT);
        $mform->setDefault('department', $templateuser->department);

        // no need
        /*
        $mform->addElement('text', 'phone1', get_string('phone1'), 'maxlength="20" size="25"');
        $mform->setType('phone1', PARAM_NOTAGS);
        $mform->setAdvanced('phone1');
        $mform->setForceLtr('phone1');

        $mform->addElement('text', 'phone2', get_string('phone2'), 'maxlength="20" size="25"');
        $mform->setType('phone2', PARAM_NOTAGS);
        $mform->setAdvanced('phone2');
        $mform->setForceLtr('phone2');

        $mform->addElement('text', 'address', get_string('address'), 'maxlength="255" size="25"');
        $mform->setType('address', PARAM_TEXT);
        $mform->setAdvanced('address');
        */

        // Next the profile defaults
        // dont need this!
        //profile_definition($mform);

        // hidden fields
        $mform->addElement('hidden', 'iid');
        $mform->setType('iid', PARAM_INT);

        $mform->addElement('hidden', 'previewrows');
        $mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(true, get_string('uploadusers', 'tool_uploaduser'));

        $this->set_data($data);
    }
}
