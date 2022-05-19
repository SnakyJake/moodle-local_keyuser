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
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot."/local/keyuser/locallib.php");

$systemcontext = context_system::instance();

if ($hassiteconfig){
    require_once($CFG->dirroot."/".$CFG->admin."/tool/capability/locallib.php");

    global $KEYUSER_CFG;

    $pluginname = get_string("pluginname","local_keyuser");

    $ADMIN->add('localplugins',new admin_category('localkeyuser',$pluginname));

    // Get some basic data we are going to need.
    $systemroles = get_roles_for_contextlevels(CONTEXT_SYSTEM);
    $roles = role_fix_names(get_all_roles($systemcontext), $systemcontext, ROLENAME_ORIGINAL);
    $roleurl = $CFG->wwwroot . '/' . $CFG->admin . '/roles/define.php';

    $settings = new admin_settingpage('local_keyuser_settings', get_string('settings'));
    
    $settings->add(new admin_setting_heading('local_keyuser/settings',get_string('settings'),''));

    //enable empty cohort prefix
    $settings->add(new admin_setting_configcheckbox('local_keyuser/no_prefix_allowed',get_string('settings_keyuser_no_prefix_allowed','local_keyuser'),'',0));

    $options = $DB->get_records_menu('user_info_field',null,'',"id, CONCAT(shortname,' (',name,')')");

    $settings->add(new admin_setting_heading('local_keyuser/defaultfields','', get_string('default')));
    $settings->add(new admin_setting_configmultiselect('local_keyuser/linkedfieldsdefault', get_string('settings_keyuser_linkedfields','local_keyuser'),'',[],$options));
    $settings->add(new admin_setting_configmultiselect('local_keyuser/linkedfieldsmultidefault', get_string('settings_keyuser_linkedfieldsmulti','local_keyuser'),'',[],$options));
    $settings->add(new admin_setting_configmultiselect('local_keyuser/cohortprefixfieldsdefault', get_string('settings_keyuser_cohortprefixfields','local_keyuser'),'',[],$options));
    $settings->add(new admin_setting_configmultiselect('local_keyuser/cohortprefixfieldsmultidefault', get_string('settings_keyuser_cohortprefixfieldsmulti','local_keyuser'),'',[],$options));

    foreach($roles as $role){
        if(array_search($role->id,$systemroles) !== false){
            $settings->add(new admin_setting_heading('local_keyuser/role'.$role->id,'', '<a href="' . $roleurl . '?action=view&amp;roleid=' . $role->id . '">' . $role->localname . '</a>'));
            $settings->add(new admin_setting_configcheckbox('local_keyuser/roleenabled'.$role->id,get_string('enable'),'',0));
            $tmp = new admin_setting_configmultiselect('local_keyuser/linkedfields'.$role->id, get_string('settings_keyuser_linkedfields','local_keyuser'),'',$KEYUSER_CFG->linked_default,$options);
            $tmp->add_dependent_on('local_keyuser/roleenabled'.$role->id);
            $settings->add($tmp);
            $settings->hide_if('local_keyuser/linkedfields'.$role->id,'local_keyuser/roleenabled'.$role->id);
            $tmp = new admin_setting_configmultiselect('local_keyuser/linkedfieldsmulti'.$role->id, get_string('settings_keyuser_linkedfieldsmulti','local_keyuser'),'',$KEYUSER_CFG->linked_multi_default,$options);
            $tmp->add_dependent_on('local_keyuser/roleenabled'.$role->id);
            $settings->add($tmp);
            $settings->hide_if('local_keyuser/linkedfieldsmulti'.$role->id,'local_keyuser/roleenabled'.$role->id);
            $tmp = new admin_setting_configmultiselect('local_keyuser/cohortprefixfields'.$role->id, get_string('settings_keyuser_cohortprefixfields','local_keyuser'),'',$KEYUSER_CFG->cohort_prefix_default,$options);
            $tmp->add_dependent_on('local_keyuser/roleenabled'.$role->id);
            $settings->add($tmp);
            $settings->hide_if('local_keyuser/cohortprefixfields'.$role->id,'local_keyuser/roleenabled'.$role->id);
            $tmp = new admin_setting_configmultiselect('local_keyuser/cohortprefixfieldsmulti'.$role->id, get_string('settings_keyuser_cohortprefixfieldsmulti','local_keyuser'),'',$KEYUSER_CFG->cohort_prefix_multi_default,$options);
            $tmp->add_dependent_on('local_keyuser/roleenabled'.$role->id);
            $settings->add($tmp);
            $settings->hide_if('local_keyuser/cohortprefixfieldsmulti'.$role->id,'local_keyuser/roleenabled'.$role->id);
        }
    }
    
    $settings->add(new admin_setting_heading('local_keyuser/profilefields_link2','', '<a href="' . $CFG->wwwroot. '/user/profile/index.php">' . get_string("edit_profilefields","local_keyuser") . "</a>"));

    $ADMIN->add('localkeyuser', $settings);
    $ADMIN->add('localkeyuser',new admin_externalpage('local_keyuser',
    get_String('heading_checkmoodlechanges','local_keyuser'),
    new moodle_url('/local/keyuser/checkmoodlechanges.php')));
}

if (has_capability('local/keyuser:uploadusers', $systemcontext) 
 or has_capability('local/keyuser:userupdate', $systemcontext) 
 or has_capability('local/keyuser:userdelete', $systemcontext) 
 or has_capability('local/keyuser:usercreate', $systemcontext) 
 or has_capability('local/keyuser:userbulkactions', $systemcontext)
 or has_capability('local/keyuser:cohortmanage', $systemcontext)
 or has_capability('local/keyuser:cohortview', $systemcontext)
) {
    $ADMIN->add(
        'users',
        new admin_category(
            'keyusersettings',
            new lang_string(
                'settings_category',
                'local_keyuser'
            )
        )
    );

    $ADMIN->add(
        'keyusersettings',
        new admin_externalpage(
            'keyuser_editusers',
            new lang_string('userlist','admin'),
            new moodle_url('/local/keyuser/admin/user.php'),
            array('local/keyuser:userupdate','local/keyuser:userdelete'),
        )
    );
    if(has_capability('local/keyuser:userbulkactions', $systemcontext)){
        $ADMIN->add(
            'keyusersettings', 
            new admin_externalpage(
                'keyuser_userbulk', 
                new lang_string('userbulk','admin'),
                new moodle_url('/local/keyuser/admin/user/user_bulk.php'), 
                array('local/keyuser:userupdate','local/keyuser:userdelete','local/keyuser:userbulkactions'),
            )
        );
    }
    $ADMIN->add(
        'keyusersettings',
        new admin_externalpage(
            'keyuser_addnewuser',
            new lang_string('addnewuser'),
            new moodle_url('/local/keyuser/user/editadvanced.php?id=-1'),
            array('local/keyuser:usercreate'),
        )
    );
    $ADMIN->add(
        'keyusersettings', 
        new admin_externalpage(
            'keyuser_cohorts', 
            new lang_string('cohorts', 'cohort'),
            new moodle_url('/local/keyuser/cohort/index.php'), 
            array('local/keyuser:cohortmanage', 'local/keyuser:cohortview')#
        )
    );
    $ADMIN->add(
        'keyusersettings', 
        new admin_externalpage(
            'keyuser_uploadusers', 
            new lang_string('uploadusers', 'tool_uploaduser'),
            new moodle_url('/local/keyuser/admin/tool/uploaduser/index.php'), 
            array('local/keyuser:uploadusers')
        )
    );
}
