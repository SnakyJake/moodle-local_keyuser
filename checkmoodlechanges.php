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
 * Keyuser check for changes in core moodle files used by this plugin (backup of those files in /local/keyuser/orgmoodlefiles) .
 *
 * @package    local_keyuser
 * @copyright  2021 Jakob Heinemann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_keyuser','',null);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading_checkmoodlechanges', 'local_keyuser'));

$directory = new RecursiveDirectoryIterator("orgmoodlefiles",RecursiveIteratorIterator::SELF_FIRST);
$filterchanged = new \RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) {
    global $CFG;
    // Skip hidden files and directories.
    if ($current->getFilename()[0] === '.') {
      return FALSE;
    }
    if ($current->isDir()) {
        return TRUE;
    }
    else {
        //compare file contents
        $orgfile = $current->getPathname();
        $path = explode(DIRECTORY_SEPARATOR, $current->getPathname());
        unset($path[0]);
        $moodlefile = $CFG->dirroot."/".implode(DIRECTORY_SEPARATOR, $path);
        if(sha1_file($orgfile) !== sha1_file($moodlefile)){
            return TRUE;
        }
    }
});
$filterunchanged = new \RecursiveCallbackFilterIterator($directory, function ($current, $key, $iterator) {
    global $CFG;
    // Skip hidden files and directories.
    if ($current->getFilename()[0] === '.') {
      return FALSE;
    }
    if ($current->isDir()) {
        return TRUE;
    }
    else {
        //compare file contents
        $orgfile = $current->getPathname();
        $path = explode(DIRECTORY_SEPARATOR, $current->getPathname());
        unset($path[0]);
        $moodlefile = $CFG->dirroot."/".implode(DIRECTORY_SEPARATOR, $path);
        if(sha1_file($orgfile) === sha1_file($moodlefile)){
            return TRUE;
        }
    }
});

$iterator = new \RecursiveIteratorIterator($filterchanged);
$fileschanged = array();
foreach ($iterator as $info) {
    $path = explode(DIRECTORY_SEPARATOR, $info->getPathname());
    unset($path[0]);
    $fileschanged[] = implode(DIRECTORY_SEPARATOR, $path);
}

$iterator = new \RecursiveIteratorIterator($filterunchanged);
$filesunchanged = array();
foreach ($iterator as $info) {
    $path = explode(DIRECTORY_SEPARATOR, $info->getPathname());
    unset($path[0]);
    $filesunchanged[] = implode(DIRECTORY_SEPARATOR, $path);
}

echo $OUTPUT->heading(get_string('heading_changedfilescount', 'local_keyuser', count($fileschanged)));
foreach ($fileschanged as $file){
    echo $file . "<br>";
}

echo $OUTPUT->heading(get_string('heading_unchangedfilescount', 'local_keyuser', count($filesunchanged)));
foreach ($filesunchanged as $file){
    echo $file . "<br>";
}
echo $OUTPUT->footer();
