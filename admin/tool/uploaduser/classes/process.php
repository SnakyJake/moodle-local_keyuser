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
 * Class process
 *
 * @package     local_keyuser, keyusertool_uploaduser
 * @copyright   2020 Moodle, 2021 Jakob Heinemann, 2022 Fabian Bech
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_keyuser\tool_uploaduser;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/keyuser/recordlib.php');

/**
 * Process CSV file with users data, this will create/update users, enrol them into courses, add them to keyuser_cohorts etc
 *
 * @package     local_keyuser, tool_uploaduser
 * @copyright   2020 Moodle, 2021 Jakob Heinemann, 2022 Fabian Bech
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process extends \tool_uploaduser\process {

    /**
     * Returns the list of columns in the file
     *
     * @return array
     */
    public function get_file_columns(): array {
        if ($this->filecolumns === null) {
            $returnurl = new \moodle_url('/local/keyuser/admin/tool/uploaduser/index.php');
            $this->filecolumns = uu_validate_user_upload_columns($this->cir,
                $this->standardfields, $this->profilefields, $returnurl);
        }
        return $this->filecolumns;
    }

    /**
     * Process one line from CSV file
     *
     * @param array $line
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function process_line(array $line) {
        global $DB, $CFG, $SESSION;

        if (!$user = $this->prepare_user_record($line)) {
            return;
        }

        if ($existinguser = $DB->get_record('user', ['username' => $user->username, 'mnethostid' => $user->mnethostid])) {
            $this->upt->track('id', $existinguser->id, 'normal', false);
        }

        if ($user->mnethostid == $CFG->mnet_localhost_id) {
            $remoteuser = false;

            // Find out if username incrementing required.
            if ($existinguser and $this->get_operation_type() == UU_USER_ADDINC) {
                $user->username = uu_increment_username($user->username);
                $existinguser = false;
            }

        } else {
            if (!$existinguser or $this->get_operation_type() == UU_USER_ADDINC) {
                $this->upt->track('status', get_string('errormnetadd', 'tool_uploaduser'), 'error');
                $this->userserrors++;
                return;
            }

            $remoteuser = true;

            // Make sure there are no changes of existing fields except the suspended status.
            foreach ((array)$existinguser as $k => $v) {
                if ($k === 'suspended') {
                    continue;
                }
                if (property_exists($user, $k)) {
                    $user->$k = $v;
                }
                if (in_array($k, $this->upt->columns)) {
                    if ($k === 'password' or $k === 'oldusername' or $k === 'deleted') {
                        $this->upt->track($k, '', 'normal', false);
                    } else {
                        $this->upt->track($k, s($v), 'normal', false);
                    }
                }
            }
            unset($user->oldusername);
            unset($user->password);
            $user->auth = $existinguser->auth;
        }

        // Notify about nay username changes.
        if ($user->originalusername !== $user->username) {
            $this->upt->track('username', '', 'normal', false); // Clear previous.
            $this->upt->track('username', s($user->originalusername).'-->'.s($user->username), 'info');
        } else {
            $this->upt->track('username', s($user->username), 'normal', false);
        }
        unset($user->originalusername);

        // Verify if the theme is valid and allowed to be set.
        if (isset($user->theme)) {
            list($status, $message) = field_value_validators::validate_theme($user->theme);
            if ($status !== 'normal' && !empty($message)) {
                $this->upt->track('status', $message, $status);
                // Unset the theme when validation fails.
                unset($user->theme);
            }
        }

        // Add default values for remaining fields.
        $formdefaults = array();
        if (!$existinguser ||
                ($this->get_update_type() != UU_UPDATE_FILEOVERRIDE && $this->get_update_type() != UU_UPDATE_NOCHANGES)) {
            foreach ($this->standardfields as $field) {
                if (isset($user->$field)) {
                    continue;
                }
                // All validation moved to form2.
                if (isset($this->formdata->$field)) {
                    // Process templates.
                    $user->$field = uu_process_template($this->formdata->$field, $user);
                    $formdefaults[$field] = true;
                    if (in_array($field, $this->upt->columns)) {
                        $this->upt->track($field, s($user->$field), 'normal');
                    }
                }
            }
            foreach ($this->allprofilefields as $field => $profilefield) {
                if (isset($user->$field)) {
                    continue;
                }
                if (isset($this->formdata->$field)) {
                    // Process templates.
                    $user->$field = uu_process_template($this->formdata->$field, $user);

                    // Form contains key and later code expects value.
                    // Convert key to value for required profile fields.
                    if (method_exists($profilefield, 'convert_external_data')) {
                        $user->$field = $profilefield->edit_save_data_preprocess($user->$field, null);
                    }

                    $formdefaults[$field] = true;
                }
            }
        }

        // Delete user.
        if (!empty($user->deleted)) {
            if (!$this->get_allow_deletes() or $remoteuser) {
                $this->usersskipped++;
                $this->upt->track('status', get_string('usernotdeletedoff', 'error'), 'warning');
                return;
            }
            if ($existinguser) {
                if (is_siteadmin($existinguser->id)) {
                    $this->upt->track('status', get_string('usernotdeletedadmin', 'error'), 'error');
                    $this->deleteerrors++;
                    return;
                }
                if (delete_user($existinguser)) {
                    $this->upt->track('status', get_string('userdeleted', 'tool_uploaduser'));
                    $this->deletes++;
                } else {
                    $this->upt->track('status', get_string('usernotdeletederror', 'error'), 'error');
                    $this->deleteerrors++;
                }
            } else {
                $this->upt->track('status', get_string('usernotdeletedmissing', 'error'), 'error');
                $this->deleteerrors++;
            }
            return;
        }
        // We do not need the deleted flag anymore.
        unset($user->deleted);

        // Renaming requested?
        if (!empty($user->oldusername) ) {
            if (!$this->get_allow_renames()) {
                $this->usersskipped++;
                $this->upt->track('status', get_string('usernotrenamedoff', 'error'), 'warning');
                return;
            }

            if ($existinguser) {
                $this->upt->track('status', get_string('usernotrenamedexists', 'error'), 'error');
                $this->renameerrors++;
                return;
            }

            if ($user->username === 'guest') {
                $this->upt->track('status', get_string('guestnoeditprofileother', 'error'), 'error');
                $this->renameerrors++;
                return;
            }

            if ($this->get_normalise_user_names()) {
                $oldusername = \core_user::clean_field($user->oldusername, 'username');
            } else {
                $oldusername = $user->oldusername;
            }

            // No guessing when looking for old username, it must be exact match.
            if ($olduser = $DB->get_record('user',
                    ['username' => $oldusername, 'mnethostid' => $CFG->mnet_localhost_id])) {
                $this->upt->track('id', $olduser->id, 'normal', false);
                if (is_siteadmin($olduser->id)) {
                    $this->upt->track('status', get_string('usernotrenamedadmin', 'error'), 'error');
                    $this->renameerrors++;
                    return;
                }
                $DB->set_field('user', 'username', $user->username, ['id' => $olduser->id]);
                $this->upt->track('username', '', 'normal', false); // Clear previous.
                $this->upt->track('username', s($oldusername).'-->'.s($user->username), 'info');
                $this->upt->track('status', get_string('userrenamed', 'tool_uploaduser'));
                $this->renames++;
            } else {
                $this->upt->track('status', get_string('usernotrenamedmissing', 'error'), 'error');
                $this->renameerrors++;
                return;
            }
            $existinguser = $olduser;
            $existinguser->username = $user->username;
        }

        // Can we process with update or insert?
        $skip = false;
        switch ($this->get_operation_type()) {
            case UU_USER_ADDNEW:
                if ($existinguser) {
                    $this->usersskipped++;
                    $this->upt->track('status', get_string('usernotaddedregistered', 'error'), 'warning');
                    $skip = true;
                }
                break;

            case UU_USER_ADDINC:
                if ($existinguser) {
                    // This should not happen!
                    $this->upt->track('status', get_string('usernotaddederror', 'error'), 'error');
                    $this->userserrors++;
                    $skip = true;
                }
                break;

            case UU_USER_ADD_UPDATE:
                break;

            case UU_USER_UPDATE:
                if (!$existinguser) {
                    $this->usersskipped++;
                    $this->upt->track('status', get_string('usernotupdatednotexists', 'error'), 'warning');
                    $skip = true;
                }
                break;

            default:
                // Unknown type.
                $skip = true;
        }

        if ($skip) {
            return;
        }

        if ($existinguser) {
            $user->id = $existinguser->id;

            $this->upt->track('username', \html_writer::link(
                new \moodle_url('/user/profile.php', ['id' => $existinguser->id]), s($existinguser->username)), 'normal', false);
            $this->upt->track('suspended', $this->get_string_yes_no($existinguser->suspended) , 'normal', false);
            $this->upt->track('auth', $existinguser->auth, 'normal', false);

            if (is_siteadmin($user->id)) {
                $this->upt->track('status', get_string('usernotupdatedadmin', 'error'), 'error');
                $this->userserrors++;
                return;
            }

            $existinguser->timemodified = time();
            // Do NOT mess with timecreated or firstaccess here!

            // Load existing profile data.
            profile_load_data($existinguser);

            $doupdate = false;
            $dologout = false;

            if ($this->get_update_type() != UU_UPDATE_NOCHANGES and !$remoteuser) {
                if (!empty($user->auth) and $user->auth !== $existinguser->auth) {
                    $this->upt->track('auth', s($existinguser->auth).'-->'.s($user->auth), 'info', false);
                    $existinguser->auth = $user->auth;
                    if (!isset($this->supportedauths[$user->auth])) {
                        $this->upt->track('auth', get_string('userauthunsupported', 'error'), 'warning');
                    }
                    $doupdate = true;
                    if ($existinguser->auth === 'nologin') {
                        $dologout = true;
                    }
                }
                $allcolumns = array_merge($this->standardfields, $this->profilefields);
                foreach ($allcolumns as $column) {
                    if ($column === 'username' or $column === 'password' or $column === 'auth' or $column === 'suspended') {
                        // These can not be changed here.
                        continue;
                    }
                    if (!property_exists($user, $column) or !property_exists($existinguser, $column)) {
                        continue;
                    }
                    if ($this->get_update_type() == UU_UPDATE_MISSING) {
                        if (!is_null($existinguser->$column) and $existinguser->$column !== '') {
                            continue;
                        }
                    } else if ($this->get_update_type() == UU_UPDATE_ALLOVERRIDE) {
                        // We override everything.
                        null;
                    } else if ($this->get_update_type() == UU_UPDATE_FILEOVERRIDE) {
                        if (!empty($formdefaults[$column])) {
                            // Do not override with form defaults.
                            continue;
                        }
                    }
                    if ($existinguser->$column !== $user->$column) {
                        if ($column === 'email') {
                            $select = $DB->sql_like('email', ':email', false, true, false, '|');
                            $params = array('email' => $DB->sql_like_escape($user->email, '|'));
                            if ($DB->record_exists_select('user', $select , $params)) {

                                $changeincase = \core_text::strtolower($existinguser->$column) === \core_text::strtolower(
                                        $user->$column);

                                if ($changeincase) {
                                    // If only case is different then switch to lower case and carry on.
                                    $user->$column = \core_text::strtolower($user->$column);
                                    continue;
                                } else if (!$this->get_allow_email_duplicates()) {
                                    $this->upt->track('email', get_string('useremailduplicate', 'error'), 'error');
                                    $this->upt->track('status', get_string('usernotupdatederror', 'error'), 'error');
                                    $this->userserrors++;
                                    return;
                                } else {
                                    $this->upt->track('email', get_string('useremailduplicate', 'error'), 'warning');
                                }
                            }
                            if (!validate_email($user->email)) {
                                $this->upt->track('email', get_string('invalidemail'), 'warning');
                            }
                        }

                        if ($column === 'lang') {
                            if (empty($user->lang)) {
                                // Do not change to not-set value.
                                continue;
                            } else if (\core_user::clean_field($user->lang, 'lang') === '') {
                                $this->upt->track('status', get_string('cannotfindlang', 'error', $user->lang), 'warning');
                                continue;
                            }
                        }

                        if (in_array($column, $this->upt->columns)) {
                            $this->upt->track($column, s($existinguser->$column).'-->'.s($user->$column), 'info', false);
                        }
                        $existinguser->$column = $user->$column;
                        $doupdate = true;
                    }
                }
            }

            try {
                $auth = get_auth_plugin($existinguser->auth);
            } catch (\Exception $e) {
                $this->upt->track('auth', get_string('userautherror', 'error', s($existinguser->auth)), 'error');
                $this->upt->track('status', get_string('usernotupdatederror', 'error'), 'error');
                $this->userserrors++;
                return;
            }
            $isinternalauth = $auth->is_internal();

            // Deal with suspending and activating of accounts.
            if ($this->get_allow_suspends() and isset($user->suspended) and $user->suspended !== '') {
                $user->suspended = $user->suspended ? 1 : 0;
                if ($existinguser->suspended != $user->suspended) {
                    $this->upt->track('suspended', '', 'normal', false);
                    $this->upt->track('suspended',
                        $this->get_string_yes_no($existinguser->suspended).'-->'.$this->get_string_yes_no($user->suspended),
                        'info', false);
                    $existinguser->suspended = $user->suspended;
                    $doupdate = true;
                    if ($existinguser->suspended) {
                        $dologout = true;
                    }
                }
            }

            // Changing of passwords is a special case
            // do not force password changes for external auth plugins!
            $oldpw = $existinguser->password;

            if ($remoteuser) {
                // Do not mess with passwords of remote users.
                null;
            } else if (!$isinternalauth) {
                $existinguser->password = AUTH_PASSWORD_NOT_CACHED;
                $this->upt->track('password', '-', 'normal', false);
                // Clean up prefs.
                unset_user_preference('create_password', $existinguser);
                unset_user_preference('auth_forcepasswordchange', $existinguser);

            } else if (!empty($user->password)) {
                if ($this->get_update_passwords()) {
                    // Check for passwords that we want to force users to reset next
                    // time they log in.
                    $errmsg = null;
                    $weak = !check_password_policy($user->password, $errmsg, $user);
                    if ($this->get_reset_passwords() == UU_PWRESET_ALL or
                            ($this->get_reset_passwords() == UU_PWRESET_WEAK and $weak)) {
                        if ($weak) {
                            $this->weakpasswords++;
                            $this->upt->track('password', get_string('invalidpasswordpolicy', 'error'), 'warning');
                        }
                        set_user_preference('auth_forcepasswordchange', 1, $existinguser);
                    } else {
                        unset_user_preference('auth_forcepasswordchange', $existinguser);
                    }
                    unset_user_preference('create_password', $existinguser); // No need to create password any more.

                    // Use a low cost factor when generating bcrypt hash otherwise
                    // hashing would be slow when uploading lots of users. Hashes
                    // will be automatically updated to a higher cost factor the first
                    // time the user logs in.
                    $existinguser->password = hash_internal_user_password($user->password, true);
                    $this->upt->track('password', $user->password, 'normal', false);
                } else {
                    // Do not print password when not changed.
                    $this->upt->track('password', '', 'normal', false);
                }
            }

            if ($doupdate or $existinguser->password !== $oldpw) {
                // We want only users that were really updated.
                user_update_user($existinguser, false, false);

                $this->upt->track('status', get_string('useraccountupdated', 'tool_uploaduser'));
                $this->usersupdated++;

                if (!$remoteuser) {
                    // Pre-process custom profile menu fields data from csv file.
                    $existinguser = uu_pre_process_custom_profile_data($existinguser);
                    // Save custom profile fields data from csv file.
                    profile_save_data($existinguser);
                }

                if ($this->get_bulk() == UU_BULK_UPDATED or $this->get_bulk() == UU_BULK_ALL) {
                    if (!in_array($user->id, $SESSION->bulk_users)) {
                        $SESSION->bulk_users[] = $user->id;
                    }
                }

                // Trigger event.
                \core\event\user_updated::create_from_userid($existinguser->id)->trigger();

            } else {
                // No user information changed.
                $this->upt->track('status', get_string('useraccountuptodate', 'tool_uploaduser'));
                $this->usersuptodate++;

                if ($this->get_bulk() == UU_BULK_ALL) {
                    if (!in_array($user->id, $SESSION->bulk_users)) {
                        $SESSION->bulk_users[] = $user->id;
                    }
                }
            }

            if ($dologout) {
                \core\session\manager::kill_user_sessions($existinguser->id);
            }

        } else {
            // Save the new user to the database.
            $user->confirmed    = 1;
            $user->timemodified = time();
            $user->timecreated  = time();
            $user->mnethostid   = $CFG->mnet_localhost_id; // We support ONLY local accounts here, sorry.

            if (!isset($user->suspended) or $user->suspended === '') {
                $user->suspended = 0;
            } else {
                $user->suspended = $user->suspended ? 1 : 0;
            }
            $this->upt->track('suspended', $this->get_string_yes_no($user->suspended), 'normal', false);

            if (empty($user->auth)) {
                $user->auth = 'manual';
            }
            $this->upt->track('auth', $user->auth, 'normal', false);

            // Do not insert record if new auth plugin does not exist!
            try {
                $auth = get_auth_plugin($user->auth);
            } catch (\Exception $e) {
                $this->upt->track('auth', get_string('userautherror', 'error', s($user->auth)), 'error');
                $this->upt->track('status', get_string('usernotaddederror', 'error'), 'error');
                $this->userserrors++;
                return;
            }
            if (!isset($this->supportedauths[$user->auth])) {
                $this->upt->track('auth', get_string('userauthunsupported', 'error'), 'warning');
            }

            $isinternalauth = $auth->is_internal();

            if (empty($user->email)) {
                $this->upt->track('email', get_string('invalidemail'), 'error');
                $this->upt->track('status', get_string('usernotaddederror', 'error'), 'error');
                $this->userserrors++;
                return;

            } else if ($DB->record_exists('user', ['email' => $user->email])) {
                if (!$this->get_allow_email_duplicates()) {
                    $this->upt->track('email', get_string('useremailduplicate', 'error'), 'error');
                    $this->upt->track('status', get_string('usernotaddederror', 'error'), 'error');
                    $this->userserrors++;
                    return;
                } else {
                    $this->upt->track('email', get_string('useremailduplicate', 'error'), 'warning');
                }
            }
            if (!validate_email($user->email)) {
                $this->upt->track('email', get_string('invalidemail'), 'warning');
            }

            if (empty($user->lang)) {
                $user->lang = '';
            } else if (\core_user::clean_field($user->lang, 'lang') === '') {
                $this->upt->track('status', get_string('cannotfindlang', 'error', $user->lang), 'warning');
                $user->lang = '';
            }

            $forcechangepassword = false;

            if ($isinternalauth) {
                if (empty($user->password)) {
                    if ($this->get_create_paswords()) {
                        $user->password = 'to be generated';
                        $this->upt->track('password', '', 'normal', false);
                        $this->upt->track('password', get_string('uupasswordcron', 'tool_uploaduser'), 'warning', false);
                    } else {
                        $this->upt->track('password', '', 'normal', false);
                        $this->upt->track('password', get_string('missingfield', 'error', 'password'), 'error');
                        $this->upt->track('status', get_string('usernotaddederror', 'error'), 'error');
                        $this->userserrors++;
                        return;
                    }
                } else {
                    $errmsg = null;
                    $weak = !check_password_policy($user->password, $errmsg, $user);
                    if ($this->get_reset_passwords() == UU_PWRESET_ALL or
                            ($this->get_reset_passwords() == UU_PWRESET_WEAK and $weak)) {
                        if ($weak) {
                            $this->weakpasswords++;
                            $this->upt->track('password', get_string('invalidpasswordpolicy', 'error'), 'warning');
                        }
                        $forcechangepassword = true;
                    }
                    // Use a low cost factor when generating bcrypt hash otherwise
                    // hashing would be slow when uploading lots of users. Hashes
                    // will be automatically updated to a higher cost factor the first
                    // time the user logs in.
                    $user->password = hash_internal_user_password($user->password, true);
                }
            } else {
                $user->password = AUTH_PASSWORD_NOT_CACHED;
                $this->upt->track('password', '-', 'normal', false);
            }

            $user->id = user_create_user($user, false, false);
            $this->upt->track('username', \html_writer::link(
                new \moodle_url('/user/profile.php', ['id' => $user->id]), s($user->username)), 'normal', false);

            // Pre-process custom profile menu fields data from csv file.
            $user = uu_pre_process_custom_profile_data($user);
            // Save custom profile fields data.
            profile_save_data($user);

            if ($forcechangepassword) {
                set_user_preference('auth_forcepasswordchange', 1, $user);
            }
            if ($user->password === 'to be generated') {
                set_user_preference('create_password', 1, $user);
            }

            // Trigger event.
            \core\event\user_created::create_from_userid($user->id)->trigger();

            $this->upt->track('status', get_string('newuser'));
            $this->upt->track('id', $user->id, 'normal', false);
            $this->usersnew++;

            // Make sure user context exists.
            \context_user::instance($user->id);

            if ($this->get_bulk() == UU_BULK_NEW or $this->get_bulk() == UU_BULK_ALL) {
                if (!in_array($user->id, $SESSION->bulk_users)) {
                    $SESSION->bulk_users[] = $user->id;
                }
            }
        }

        // Update user interests.
        if (isset($user->interests) && strval($user->interests) !== '') {
            useredit_update_interests($user, preg_split('/\s*,\s*/', $user->interests, -1, PREG_SPLIT_NO_EMPTY));
        }

        // Add to cohort first, it might trigger enrolments indirectly - do NOT create cohorts here!
        foreach ($this->get_file_columns() as $column) {
            if (!preg_match('/^cohort\d+$/', $column)) {
                continue;
            }

            if (!empty($user->$column)) {
                $addcohort = $user->$column;
                if (!isset($this->cohorts[$addcohort])) {
                    if (is_number($addcohort)) {
                        // Only non-numeric idnumbers!
                        $cohort = keyuser_cohort_get_record($addcohort);
                    } else {
                        $cohort = keyuser_cohort_get_record_by_idnumber($addcohort);
                        if (empty($cohort) && (has_capability('moodle/cohort:manage', \context_system::instance()) || has_capability('local/keyuser:cohortmanage', \context_system::instance()))) {
                            // Cohort was not found. Create a new one.
                            $cohortid = keyuser_cohort_add_cohort((object)array(
                                'idnumber' => $addcohort,
                                'name' => $addcohort,
                                'contextid' => \context_system::instance()->id
                            ));
                            $cohort = keyuser_cohort_get_record($cohortid);
                        }
                    }

                    if (empty($cohort)) {
                        $this->cohorts[$addcohort] = get_string('unknowncohort', 'core_cohort', s($addcohort));
                    } else if (!empty($cohort->component)) {
                        // Cohorts synchronised with external sources must not be modified!
                        $this->cohorts[$addcohort] = get_string('external', 'core_cohort');
                    } else {
                        $this->cohorts[$addcohort] = $cohort;
                    }
                }

                if (is_object($this->cohorts[$addcohort])) {
                    $cohort = $this->cohorts[$addcohort];
                    if (!$DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $user->id])) {
                        cohort_add_member($cohort->id, $user->id);
                        // We might add special column later, for now let's abuse enrolments.
                        $this->upt->track('enrolments', get_string('useradded', 'core_cohort', s($cohort->name)), 'info');
                    }
                } else {
                    // Error message.
                    $this->upt->track('enrolments', $this->cohorts[$addcohort], 'error');
                }
            }
        }

        // Find course enrolments, groups, roles/types and enrol periods
        // this is again a special case, we always do this for any updated or created users.
        foreach ($this->get_file_columns() as $column) {
            if (preg_match('/^sysrole\d+$/', $column)) {

                if (!empty($user->$column)) {
                    $sysrolename = $user->$column;
                    if ($sysrolename[0] == '-') {
                        $removing = true;
                        $sysrolename = substr($sysrolename, 1);
                    } else {
                        $removing = false;
                    }

                    if (array_key_exists($sysrolename, $this->sysrolecache)) {
                        $sysroleid = $this->sysrolecache[$sysrolename]->id;
                    } else {
                        $this->upt->track('enrolments', get_string('unknownrole', 'error', s($sysrolename)), 'error');
                        continue;
                    }

                    if ($removing) {
                        if (user_has_role_assignment($user->id, $sysroleid, SYSCONTEXTID)) {
                            role_unassign($sysroleid, $user->id, SYSCONTEXTID);
                            $this->upt->track('enrolments', get_string('unassignedsysrole',
                                'tool_uploaduser', $this->sysrolecache[$sysroleid]->name), 'info');
                        }
                    } else {
                        if (!user_has_role_assignment($user->id, $sysroleid, SYSCONTEXTID)) {
                            role_assign($sysroleid, $user->id, SYSCONTEXTID);
                            $this->upt->track('enrolments', get_string('assignedsysrole',
                                'tool_uploaduser', $this->sysrolecache[$sysroleid]->name), 'info');
                        }
                    }
                }

                continue;
            }
            if (!preg_match('/^course\d+$/', $column)) {
                continue;
            }
            $i = substr($column, 6);

            if (empty($user->{'course'.$i})) {
                continue;
            }
            $shortname = $user->{'course'.$i};
            if (!array_key_exists($shortname, $this->ccache)) {
                if (!$course = $DB->get_record('course', ['shortname' => $shortname], 'id, shortname')) {
                    $this->upt->track('enrolments', get_string('unknowncourse', 'error', s($shortname)), 'error');
                    continue;
                }
                $this->ccache[$shortname] = $course;
                $this->ccache[$shortname]->groups = null;
            }
            $courseid      = $this->ccache[$shortname]->id;
            $coursecontext = \context_course::instance($courseid);
            if (!isset($this->manualcache[$courseid])) {
                $this->manualcache[$courseid] = false;
                if ($this->manualenrol) {
                    if ($instances = enrol_get_instances($courseid, false)) {
                        foreach ($instances as $instance) {
                            if ($instance->enrol === 'manual') {
                                $this->manualcache[$courseid] = $instance;
                                break;
                            }
                        }
                    }
                }
            }

            if ($courseid == SITEID) {
                // Technically frontpage does not have enrolments, but only role assignments,
                // let's not invent new lang strings here for this rarely used feature.

                if (!empty($user->{'role'.$i})) {
                    $rolename = $user->{'role'.$i};
                    if (array_key_exists($rolename, $this->rolecache)) {
                        $roleid = $this->rolecache[$rolename]->id;
                    } else {
                        $this->upt->track('enrolments', get_string('unknownrole', 'error', s($rolename)), 'error');
                        continue;
                    }

                    role_assign($roleid, $user->id, \context_course::instance($courseid));

                    $a = new \stdClass();
                    $a->course = $shortname;
                    $a->role   = $this->rolecache[$roleid]->name;
                    $this->upt->track('enrolments', get_string('enrolledincourserole', 'enrol_manual', $a), 'info');
                }

            } else if ($this->manualenrol and $this->manualcache[$courseid]) {

                // Find role.
                $roleid = false;
                if (!empty($user->{'role'.$i})) {
                    $rolename = $user->{'role'.$i};
                    if (array_key_exists($rolename, $this->rolecache)) {
                        $roleid = $this->rolecache[$rolename]->id;
                    } else {
                        $this->upt->track('enrolments', get_string('unknownrole', 'error', s($rolename)), 'error');
                        continue;
                    }

                } else if (!empty($user->{'type'.$i})) {
                    // If no role, then find "old" enrolment type.
                    $addtype = $user->{'type'.$i};
                    if ($addtype < 1 or $addtype > 3) {
                        $this->upt->track('enrolments', get_string('error').': typeN = 1|2|3', 'error');
                        continue;
                    } else if (empty($this->formdata->{'uulegacy'.$addtype})) {
                        continue;
                    } else {
                        $roleid = $this->formdata->{'uulegacy'.$addtype};
                    }
                } else {
                    // No role specified, use the default from manual enrol plugin.
                    $roleid = $this->manualcache[$courseid]->roleid;
                }

                if ($roleid) {
                    // Find duration and/or enrol status.
                    $timeend = 0;
                    $timestart = $this->today;
                    $status = null;

                    if (isset($user->{'enrolstatus'.$i})) {
                        $enrolstatus = $user->{'enrolstatus'.$i};
                        if ($enrolstatus == '') {
                            $status = null;
                        } else if ($enrolstatus === (string)ENROL_USER_ACTIVE) {
                            $status = ENROL_USER_ACTIVE;
                        } else if ($enrolstatus === (string)ENROL_USER_SUSPENDED) {
                            $status = ENROL_USER_SUSPENDED;
                        } else {
                            debugging('Unknown enrolment status.');
                        }
                    }

                    if (!empty($user->{'enroltimestart'.$i})) {
                        $parsedtimestart = strtotime($user->{'enroltimestart'.$i});
                        if ($parsedtimestart !== false) {
                            $timestart = $parsedtimestart;
                        }
                    }

                    if (!empty($user->{'enrolperiod'.$i})) {
                        $duration = (int)$user->{'enrolperiod'.$i} * 60 * 60 * 24; // Convert days to seconds.
                        if ($duration > 0) { // Sanity check.
                            $timeend = $timestart + $duration;
                        }
                    } else if ($this->manualcache[$courseid]->enrolperiod > 0) {
                        $timeend = $timestart + $this->manualcache[$courseid]->enrolperiod;
                    }

                    $this->manualenrol->enrol_user($this->manualcache[$courseid], $user->id, $roleid,
                        $timestart, $timeend, $status);

                    $a = new \stdClass();
                    $a->course = $shortname;
                    $a->role   = $this->rolecache[$roleid]->name;
                    $this->upt->track('enrolments', get_string('enrolledincourserole', 'enrol_manual', $a), 'info');
                }
            }

            // Find group to add to.
            if (!empty($user->{'group'.$i})) {
                // Make sure user is enrolled into course before adding into groups.
                if (!is_enrolled($coursecontext, $user->id)) {
                    $this->upt->track('enrolments', get_string('addedtogroupnotenrolled', '', $user->{'group'.$i}), 'error');
                    continue;
                }
                // Build group cache.
                if (is_null($this->ccache[$shortname]->groups)) {
                    $this->ccache[$shortname]->groups = array();
                    if ($groups = groups_get_all_groups($courseid)) {
                        foreach ($groups as $gid => $group) {
                            $this->ccache[$shortname]->groups[$gid] = new \stdClass();
                            $this->ccache[$shortname]->groups[$gid]->id   = $gid;
                            $this->ccache[$shortname]->groups[$gid]->name = $group->name;
                            if (!is_numeric($group->name)) { // Only non-numeric names are supported!!!
                                $this->ccache[$shortname]->groups[$group->name] = new \stdClass();
                                $this->ccache[$shortname]->groups[$group->name]->id   = $gid;
                                $this->ccache[$shortname]->groups[$group->name]->name = $group->name;
                            }
                        }
                    }
                }
                // Group exists?
                $addgroup = $user->{'group'.$i};
                if (!array_key_exists($addgroup, $this->ccache[$shortname]->groups)) {
                    // If group doesn't exist,  create it.
                    $newgroupdata = new \stdClass();
                    $newgroupdata->name = $addgroup;
                    $newgroupdata->courseid = $this->ccache[$shortname]->id;
                    $newgroupdata->description = '';
                    $gid = groups_create_group($newgroupdata);
                    if ($gid) {
                        $this->ccache[$shortname]->groups[$addgroup] = new \stdClass();
                        $this->ccache[$shortname]->groups[$addgroup]->id   = $gid;
                        $this->ccache[$shortname]->groups[$addgroup]->name = $newgroupdata->name;
                    } else {
                        $this->upt->track('enrolments', get_string('unknowngroup', 'error', s($addgroup)), 'error');
                        continue;
                    }
                }
                $gid   = $this->ccache[$shortname]->groups[$addgroup]->id;
                $gname = $this->ccache[$shortname]->groups[$addgroup]->name;

                try {
                    if (groups_add_member($gid, $user->id)) {
                        $this->upt->track('enrolments', get_string('addedtogroup', '', s($gname)), 'info');
                    } else {
                        $this->upt->track('enrolments', get_string('addedtogroupnot', '', s($gname)), 'error');
                    }
                } catch (\moodle_exception $e) {
                    $this->upt->track('enrolments', get_string('addedtogroupnot', '', s($gname)), 'error');
                    continue;
                }
            }
        }
        if (($invalid = \core_user::validate($user)) !== true) {
            $this->upt->track('status', get_string('invaliduserdata', 'tool_uploaduser', s($user->username)), 'warning');
        }
    }
}
