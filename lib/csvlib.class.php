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
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    core, local_keyuser
 * @subpackage lib
 * @copyright  2021 Jakob Heinemann, Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Utitily class for importing of CSV files.
 * @copyright Jakob Heinemann, Petr Skoda
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package   moodlecore
 */
class keyuser_csv_import_reader extends csv_import_reader {

    public $cols_to_remove = array();
    private $_cols_to_remove_keys = array();
    private $_cohort_col_keys = array();


    /**
     * @var int import identifier
     */
    private $_iid;

    /**
     * @var string which script imports?
     */
    private $_type;

    /**
     * Contructor
     *
     * @param int $iid import identifier
     * @param string $type which script imports?
     */
    public function __construct($iid, $type) {
        parent::__construct($iid, $type);
        $this->_iid = $iid;
        $this->_type = $type;
    }
    /**
     * Parse this content
     *
     * @param string $content the content to parse.
     * @param string $encoding content encoding
     * @param string $delimiter_name separator (comma, semicolon, colon, cfg)
     * @param string $column_validation name of function for columns validation, must have one param $columns
     * @param string $enclosure field wrapper. One character only.
     * @return bool false if error, count of data lines if ok; use get_error() to get error string
     */
    public function load_csv_content($content, $encoding, $delimiter_name, $column_validation=null, $enclosure='"') {
        global $USER, $CFG;

        $this->close();
        $this->_error = null;

        $content = core_text::convert($content, $encoding, 'utf-8');
        // remove Unicode BOM from first line
        $content = core_text::trim_utf8_bom($content);
        // Fix mac/dos newlines
        $content = preg_replace('!\r\n?!', "\n", $content);
        // Remove any spaces or new lines at the end of the file.
        if ($delimiter_name == 'tab') {
            // trim() by default removes tabs from the end of content which is undesirable in a tab separated file.
            $content = trim($content, chr(0x20) . chr(0x0A) . chr(0x0D) . chr(0x00) . chr(0x0B));
        } else {
            $content = trim($content);
        }

        $csv_delimiter = csv_import_reader::get_delimiter($delimiter_name);
        // $csv_encode    = csv_import_reader::get_encoded_delimiter($delimiter_name);

        // Create a temporary file and store the csv file there,
        // do not try using fgetcsv() because there is nothing
        // to split rows properly - fgetcsv() itself can not do it.
        $tempfile = tempnam(make_temp_directory('/csvimport'), 'tmp');
        if (!$fp = fopen($tempfile, 'w+b')) {
            $this->_error = get_string('cannotsavedata', 'error');
            @unlink($tempfile);
            return false;
        }
        fwrite($fp, $content);
        fseek($fp, 0);
        // Create an array to store the imported data for error checking.
        $columns = array();
        // str_getcsv doesn't iterate through the csv data properly. It has
        // problems with line returns.

        $num = 0;

        while ($fgetdata = fgetcsv($fp, 0, $csv_delimiter, $enclosure)) {
            // Check to see if we have an empty line.
            if (count($fgetdata) == 1) {
                if ($fgetdata[0] !== null) {
                    // The element has data. Add it to the array.
                    if(count($this->cols_to_remove)){
                        //header
                        if($num == 0){
                            foreach($fgetdata as $key=>$columnname){
                                $fgetdata[$key] = core_text::strtolower($columnname);
                                if(core_text::substr($columnname,0,6) == "cohort"){
                                    $this->_cohort_col_keys[] = $key;
                                }
                            }
                            foreach($this->cols_to_remove as $col_to_remove){
                                $key = array_search($col_to_remove, $fgetdata);
                                if($key !== false){
                                    $this->cols_to_remove[] = $key;
                                    unset($fgetdata[$key]);
                                }
                            }
                        } else {
                            foreach($this->_cohort_col_keys as $key){
                                if($fgetdata[$key]) {
                                    //true as second param handles r_ readonly cohorts
                                    keyuser_cohort_add_prefix($fgetdata[$key],true);
                                }
                            }
                            foreach($this->_cols_to_remove_keys as $key){
                                unset($fgetdata[$key]);
                            }
                        }
                    }
                    //check for empty line
                    $keep = false;
                    foreach($fgetdata as $value){
                        if($value){
                            $keep = true;
                            break;
                        }
                    }
                    if($keep){
                        $columns[] = $fgetdata;
                    }
                }
            } else {
                if(count($this->cols_to_remove)){
                    //header
                    if($num == 0){
                        foreach($fgetdata as $key=>$columnname){
                            $fgetdata[$key] = core_text::strtolower($columnname);
                            if(core_text::substr($columnname,0,6) == "cohort"){
                                $this->_cohort_col_keys[] = $key;
                            }
                        }
                        foreach($this->cols_to_remove as $col_to_remove){
                            $key = array_search($col_to_remove, $fgetdata);
                            if($key !== false){
                                $this->_cols_to_remove_keys[] = $key;
                                unset($fgetdata[$key]);
                            }
                        }
                    } else {
                        foreach($this->_cohort_col_keys as $key){
                            if($fgetdata[$key]) {
                                //true as second param handles r_ readonly cohorts
                                keyuser_cohort_add_prefix($fgetdata[$key],true);
                            }
                        }
                        foreach($this->_cols_to_remove_keys as $key){
                            unset($fgetdata[$key]);
                        }
                    }
                }
                //check for empty line
                $keep = false;
                foreach($fgetdata as $value){
                    if($value){
                        $keep = true;
                        break;
                    }
                }
                if($keep){
                    $columns[] = $fgetdata;
                }
            }
            $num++;
        }
        $col_count = 0;

        // process header - list of columns
        if (!isset($columns[0])) {
            $this->_error = get_string('csvemptyfile', 'error');
            fclose($fp);
            unlink($tempfile);
            return false;
        } else {
            $col_count = count($columns[0]);
        }

        // Column validation.
        if ($column_validation) {
            $result = $column_validation($columns[0]);
            if ($result !== true) {
                $this->_error = $result;
                fclose($fp);
                unlink($tempfile);
                return false;
            }
        }

        $this->_columns = $columns[0]; // cached columns
        // check to make sure that the data columns match up with the headers.
        foreach ($columns as $rowdata) {
            if (count($rowdata) !== $col_count) {
                $this->_error = get_string('csvweirdcolumns', 'error');
                fclose($fp);
                unlink($tempfile);
                $this->cleanup();
                return false;
            }
        }

        $filename = $CFG->tempdir.'/csvimport/'.$this->_type.'/'.$USER->id.'/'.$this->_iid;
        $filepointer = fopen($filename, "w");
        // The information has been stored in csv format, as serialized data has issues
        // with special characters and line returns.
        $storedata = csv_export_writer::print_array($columns, ',', '"', true);
        fwrite($filepointer, $storedata);

        fclose($fp);
        unlink($tempfile);
        fclose($filepointer);

        $datacount = count($columns);
        return $datacount;
    }


}


