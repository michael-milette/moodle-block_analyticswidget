<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Code to be executed after the plugin's database scheme has been installed is defined here.
 *
 * @package     block_analyticswidget
 * @category    upgrade
 * @copyright   2022 Chandra K <developerck@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_analyticswidget;

use renderable;
use renderer_base;
use templatable;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class widget implements renderable, templatable {


    private $_userid;

    public function __construct($userid = 0) {
        global $USER;
        if (!$userid) {
            $userid = $USER->id;
        }

        $this->_userid = $userid;
    }



    private function get_teacher_widget() {

        global $DB, $USER, $CFG, $OUTPUT, $SESSION;
        $html = [];
        // Check if teacher at some course.
        $teacherrole = $DB->get_field("role", "id", array("shortname" => "editingteacher"));
        if (!$teacherrole) {
            return null;
        }
        $teachercourse = [];
        $ra = get_user_roles_sitewide_accessdata($this->_userid);
        foreach ($ra['ra'] as $c => $r) {
            if (in_array($teacherrole, $r)) {
                $c = explode("/", $c);
                $c = array_pop($c);
                $c = \context::instance_by_id($c, IGNORE_MISSING);
                if ($c instanceof \context_course) {

                    $teachercourse[] = $c->instanceid;
                }
            }
        }

        if (empty($teachercourse)) {
            return null;
        }
        $courseids = implode(",", $teachercourse);
        $sql = "select id,fullname, shortname from {course} where id in($courseids)";
        $courses = $DB->get_records_sql($sql);

        if (empty($courses)) {
            return null;
        }
        foreach (glob(__DIR__ . '/widgets/teacher/*.php') as $file) {
            require_once($file);
            $class = '\\block_analyticswidget\widgets\\teacher\\' . basename($file, '.php');
            if (in_array('block_analyticswidget\widgetfacade', class_implements($class))) {

                $obj = new $class($courses);
                if ($ht = $obj->export_html()) {
                    if (!array_key_exists($obj->order, $html)) {
                        $html[$obj->order] = $ht;
                    } else {
                        $html[] = $ht;
                    }
                }
            }
        }
        $links = [];
        ksort($html);
        return array("html" => implode("", $html), "links" => $links);
    }


    private function studying_in($activecourses) {
        global $DB;
        $studentrole = $DB->get_field("role", "id", array("shortname" => "student"));
        $studentcourses = [];
        foreach ($activecourses as $course) {
            $context = \context_course::instance($course->id);
            $records = $DB->get_records('role_assignments', array('contextid' => $context->id, "userid" => $this->_userid));
            foreach ($records as $r) {
                if ($r->roleid == $studentrole) {
                    $studentcourses[] = $course;
                }
            }
        }
        return $studentcourses;
    }
    private function get_my_widget() {

        global $DB, $USER, $CFG, $OUTPUT, $SESSION;

        $html = [];
        $courses = enrol_get_users_courses($this->_userid);
        $activecourses = enrol_get_users_courses($this->_userid, true);
        $studingin  = $this->studying_in($activecourses);
        foreach (glob(__DIR__ . '/widgets/my/*.php') as $file) {
            require_once($file);
            $class = '\\block_analyticswidget\widgets\\my\\' . basename($file, '.php');
            if (in_array('block_analyticswidget\widgetfacade', class_implements($class))) {

                $obj = new $class($this->_userid, $courses, $activecourses, $studingin);
                if ($ht = $obj->export_html()) {
                    if (!array_key_exists($obj->order, $html)) {
                        $html[$obj->order] = $ht;
                    } else {
                        $html[] = $ht;
                    }
                }
            }
        }

        ksort($html);
        $links = [];

        $links[] = \html_writer::link(new \moodle_url('/user/profile.php',
        array("id" => $this->_userid)), '<i class="fa fa-user"></i>',
        array("title" => get_string('profile'), "data-toggle" => "tooltip"));

        $links = implode(" ", $links);
        return array("html" => implode("", $html), "userid" => $this->_userid, "links" => $links);
        return array("html" => implode("", $html), "userid" => $this->_userid);
    }

    public function export_for_template($renderer) {
        $reutn = [];

        if (get_config('block_analyticswidget', 'aw_teacher_level') && $html = $this->get_teacher_widget()) {
            $return["teacher"] = $html;
        }
        $return["my"] = $this->get_my_widget();
        return  $return;
    }
}


interface widgetfacade {

    public function export_html();
}