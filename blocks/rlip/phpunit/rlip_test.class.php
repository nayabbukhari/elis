<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2012 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    elis
 * @subpackage core
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2008-2012 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__) .'/../../../elis/core/lib/setup.php');
require_once(elis::lib('testlib.php'));

/*
 * This class is handling log maintenance
 */
abstract class rlip_test extends elis_database_test {
    static $existing_csvfiles = array();
    static $existing_logfiles = array();
    static $existing_zipfiles = array();

    public static function setUpBeforeClass() {
        static::get_csv_files();
        static::get_logfilelocation_files();
        static::get_zip_files();
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass() {
        static::cleanup_csv_files();
        static::cleanup_log_files();
        static::cleanup_zip_files();
        parent::tearDownAfterClass();
    }

    /**
     * Cleans up files created by PHPunit tests
     *
     * @param string $fext  The file extension to clean-up
     */
    protected static function cleanup_test_files($fext) {
        global $CFG;
        //set the log file location
        $filepath = $CFG->dataroot;
        $farray = "existing_{$fext}files";
        foreach(glob_recursive("{$filepath}/*.{$fext}") as $file) {
            //echo "Checking whether to clean-up {$file}\n";
            if (!in_array($file, self::$$farray)) {
                //echo "deleting {$file}\n";
                unlink($file);
            }
        }
    }

    /**
     * Gets a list of files to not delete
     *
     * @param string $fext  The file extension to check for existing files
     */
    protected static function get_existing_files($fext) {
        global $CFG;
        //set the log file location
        $filepath = $CFG->dataroot;
        $farray = "existing_{$fext}files";
        self::$$farray = array();
        foreach(glob_recursive("{$filepath}/*.{$fext}") as $file) {
            self::${$farray}[] = $file;
        }
    }

    /**
     * Cleans up log files created by PHPunit tests
     */
    public static function cleanup_log_files() {
        self::cleanup_test_files('log');
    }

    /**
     * Gets a list of log files to not delete
     */
    public static function get_logfilelocation_files() {
        self::get_existing_files('log');
    }

    /**
     * Cleans up csv files created by PHPunit tests
     */
    public static function cleanup_csv_files() {
        self::cleanup_test_files('csv');
    }

    /**
     * Gets a list of zip files to not delete
     */
    public static function get_csv_files() {
        self::get_existing_files('csv');
    }

    /**
     * Cleans up zip files created buy PHPunit tests
     */
    public static function cleanup_zip_files() {
        self::cleanup_test_files('zip');
    }

    /**
     * Gets a list of zip files to not delete
     */
    public static function get_zip_files() {
        self::get_existing_files('zip');
    }

    /**
     * Find the most recent log file
     * @param string filename The base filename to find the most recent file
     * @return string newest_file The most current filename
     */
    public static function get_current_logfile($filename) {
        $filename_prefix = explode('.',$filename);
        //get newest file
        $newest_file = $filename;
        $versions = array();
        foreach (glob("$filename_prefix[0]*.log") as $fn) {
            if (($fn != $filename) &&
                ((is_array(self::$existing_logfiles) && !in_array($fn, self::$existing_logfiles)) ||
                 !is_array(self::$existing_logfiles))) {
                //extract count
                $fn_prefix = explode('.',$fn);
                $fn_part = explode('_',$fn_prefix[0]);
                $count = end($fn_part);
                //store version number in an array
                $versions[] = $count;
            }
        }

        //get latest version of the log file if there is more than one version
        if (!empty($versions)) {
            sort($versions,SORT_NUMERIC);
            $newest_file = $filename_prefix[0].'_'.end($versions).'.log';
        }
        return $newest_file;
    }

    /**
     * Find the next log file
     * @param string filename The base filename to find the most recent file
     * @return string next_file The most current filename + 1
     */
    public static function get_next_logfile($filename) {
        $newest_file = self::get_current_logfile($filename);
        $filename_prefix = explode('.',$filename);

        // generate the 'next' filename
        if (!file_exists($filename)) {
            $next_file = $filename;
        } else if ($newest_file == $filename) {
            $next_file = $filename_prefix[0].'_0.log';
        } else {
            $filename_part = explode('_',$filename_prefix[0]);
            $count = end($filename_part);
            $next_file = $filename_prefix[0].'_'.$count++.'.log';
        }
        return $next_file;
    }

}

if (!function_exists('glob_recursive'))
{
    // Does not support flag GLOB_BRACE
    function glob_recursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
        {
            $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
        }
        return $files;
    }
}

