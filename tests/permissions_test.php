<?php

/**
 * Unit tests for scheduler permissions
 *
 * @package    mod_scheduler
 * @copyright  2019 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \mod_scheduler\model\scheduler;
use \mod_scheduler\model\slot;
use \mod_scheduler\permission\scheduler_permissions;

global $CFG;
require_once($CFG->dirroot . '/mod/scheduler/locallib.php');

/**
 * Unit tests for the scheduler_permissions class.
 *
 * @group      mod_scheduler
 * @copyright  2019 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_scheduler_permissions_testcase extends advanced_testcase {

    /**
     * @var int Course_modules id used for testing
     */
    protected $moduleid;

    /**
     * @var int Course id used for testing
     */
    protected $courseid;

    /**
     * @var scheduler Scheduler used for testing
     */
    protected $scheduler;

    /**
     * @var \context context of the scheduler instance
     */
    protected $context;

    /**
     * @var int User id of teacher used for testing
     */
    protected $edteacher;
    /**
     * @var int User id of nonediting teacher used for testing
     */
    protected $nonedteacher;

    /**
     * @var int User id of administrator used for testing
     */
    protected $administ;

    /**
     * @var slot[] slots used for testing
     */
    protected $slots;
    protected $appts;

    /**
     * @var int[] id of students used for testing
     */
    protected $students;

    /**
     * Sets up the test case. Common situation for all tests:
     *
     * - One scheduler in a course
     * - Three slots are created for different students
     * - There is one editing teacher, with default permissions, who is assigned to slot 1
     * - There is one nonediting teacher, with default permissions, who is assigned to slot 2
     * - There is one "administrator", a user with a custom role that allows only viewing, but not editing, the slots.
     */
    protected function setUp() {
        global $DB, $CFG;

        $dg = $this->getDataGenerator();

        $this->resetAfterTest(false);

        $course = $dg->create_course();

        $this->students = array();
        for ($i = 0; $i < 3; $i++) {
            $this->students[$i] = $dg->create_user()->id;
            $dg->enrol_user($this->students[$i], $course->id, 'student');
        }

        // An editing teacher.
        $this->edteacher = $dg->create_user()->id;
        $dg->enrol_user($this->edteacher, $course->id, 'editingteacher');

        // A nonediting teacher.
        $this->nonedteacher = $dg->create_user()->id;
        $dg->enrol_user($this->nonedteacher, $course->id, 'teacher');

        // An administrator.
        $adminrole = $dg->create_role();
        assign_capability('mod/scheduler:canseeotherteachersbooking', CAP_ALLOW, $adminrole, \context_system::instance()->id);
        $this->administ = $dg->create_user()->id;
        $dg->enrol_user($this->administ, $course->id, $adminrole);

        $options = array();
        $options['slottimes'] = [time() + DAYSECS, time() + 2*DAYSECS, time() + 3*DAYSECS];
        $options['slotstudents'] = array_values($this->students);
        $options['slotteachers'] = [$this->edteacher, $this->nonedteacher];

        $schedrec = $this->getDataGenerator()->create_module('scheduler', ['course' => $course->id], $options);
        $coursemodule = $DB->get_record('course_modules', array('id' => $schedrec->cmid));
        $this->scheduler = scheduler::load_by_coursemodule_id($coursemodule->id);

        $this->moduleid  = $coursemodule->id;
        $this->courseid  = $coursemodule->course;
        $this->context   = $this->scheduler->context;
        $slotids = array_keys($DB->get_records('scheduler_slots', array('schedulerid' => $this->scheduler->id), 'starttime ASC'));
        $this->slots = array();
        $this->appts = array();
        foreach($slotids as $key => $id) {
            $this->slots[$key] = $this->scheduler->get_slot($id);
            $this->appts[$key] = array_values($this->slots[$key]->get_appointments())[0];
        }
    }


    public function test_teacher_can_see_slot() {

        // Editing teacher sees all slots.
        $p = new scheduler_permissions($this->context, $this->edteacher);
        $this->assertTrue($p->teacher_can_see_slot($this->slots[0]));
        $this->assertTrue($p->teacher_can_see_slot($this->slots[1]));
        $this->assertTrue($p->teacher_can_see_slot($this->slots[2]));

        // Nonediting teacher sees only his own slot.
        $p = new scheduler_permissions($this->context, $this->nonedteacher);
        $this->assertFalse($p->teacher_can_see_slot($this->slots[0]));
        $this->assertTrue ($p->teacher_can_see_slot($this->slots[1]));
        $this->assertFalse($p->teacher_can_see_slot($this->slots[2]));

        // Adminstrator sees all slots.
        $p = new scheduler_permissions($this->context, $this->administ);
        $this->assertTrue($p->teacher_can_see_slot($this->slots[0]));
        $this->assertTrue($p->teacher_can_see_slot($this->slots[1]));
        $this->assertTrue($p->teacher_can_see_slot($this->slots[2]));

        // Student don't ever see the teacher side of things.
        $p = new scheduler_permissions($this->context, $this->students[1]);
        $this->assertFalse($p->teacher_can_see_slot($this->slots[0]));
        $this->assertFalse($p->teacher_can_see_slot($this->slots[1]));
        $this->assertFalse($p->teacher_can_see_slot($this->slots[2]));

    }

    public function test_can_edit_slot() {

        // Editing teacher can edit all slots.
        $p = new scheduler_permissions($this->context, $this->edteacher);
        $this->assertTrue($p->can_edit_slot($this->slots[0]));
        $this->assertTrue($p->can_edit_slot($this->slots[1]));
        $this->assertTrue($p->can_edit_slot($this->slots[2]));

        // Nonediting teacher can only edit his own slot.
        $p = new scheduler_permissions($this->context, $this->nonedteacher);
        $this->assertFalse($p->can_edit_slot($this->slots[0]));
        $this->assertTrue ($p->can_edit_slot($this->slots[1]));
        $this->assertFalse($p->can_edit_slot($this->slots[2]));

        // Adminstrator cannot edit any slots.
        $p = new scheduler_permissions($this->context, $this->administ);
        $this->assertFalse($p->can_edit_slot($this->slots[0]));
        $this->assertFalse($p->can_edit_slot($this->slots[1]));
        $this->assertFalse($p->can_edit_slot($this->slots[2]));

        // Student can't ever edit slots.
        $p = new scheduler_permissions($this->context, $this->students[1]);
        $this->assertFalse($p->can_edit_slot($this->slots[0]));
        $this->assertFalse($p->can_edit_slot($this->slots[1]));
        $this->assertFalse($p->can_edit_slot($this->slots[2]));

    }

    public function test_can_edit_own_slots() {

        // Both teachers can edit their own slots.
        $p = new scheduler_permissions($this->context, $this->edteacher);
        $this->assertTrue($p->can_edit_own_slots());
        $p = new scheduler_permissions($this->context, $this->nonedteacher);
        $this->assertTrue($p->can_edit_own_slots());

        // Adminstrator and student cannot edit any slots.
        $p = new scheduler_permissions($this->context, $this->administ);
        $this->assertFalse($p->can_edit_own_slots());
        $p = new scheduler_permissions($this->context, $this->students[1]);
        $this->assertFalse($p->can_edit_own_slots());

    }

    public function test_can_edit_all_slots() {

        // Editing teachers can edit all slots.
        $p = new scheduler_permissions($this->context, $this->edteacher);
        $this->assertTrue($p->can_edit_all_slots());

        // Nonediting teacher, adminstrator and student cannot edit all slots.
        $p = new scheduler_permissions($this->context, $this->nonedteacher);
        $this->assertFalse($p->can_edit_all_slots());
        $p = new scheduler_permissions($this->context, $this->administ);
        $this->assertFalse($p->can_edit_all_slots());
        $p = new scheduler_permissions($this->context, $this->students[1]);
        $this->assertFalse($p->can_edit_all_slots());

    }


    public function test_can_see_all_slots() {

        // Editing teachers can see all slots.
        $p = new scheduler_permissions($this->context, $this->edteacher);
        $this->assertTrue($p->can_see_all_slots());

        // Nonediting teacher cannot see all slots.
        $p = new scheduler_permissions($this->context, $this->nonedteacher);
        $this->assertFalse($p->can_see_all_slots());

        // Administrator can see (though not edit) all slots.
        $p = new scheduler_permissions($this->context, $this->administ);
        $this->assertTrue($p->can_see_all_slots());

        // Students cannot see all slots.
        $p = new scheduler_permissions($this->context, $this->students[1]);
        $this->assertFalse($p->can_see_all_slots());

    }


    public function test_can_see_appointment() {

        // Editing teacher can all appointments.
        $p = new scheduler_permissions($this->context, $this->edteacher);
        $this->assertTrue($p->can_see_appointment($this->appts[0]));
        $this->assertTrue($p->can_see_appointment($this->appts[1]));
        $this->assertTrue($p->can_see_appointment($this->appts[2]));

        // Nonediting teacher can only see his own appointment.
        $p = new scheduler_permissions($this->context, $this->nonedteacher);
        $this->assertFalse($p->can_see_appointment($this->appts[0]));
        $this->assertTrue ($p->can_see_appointment($this->appts[1]));
        $this->assertFalse($p->can_see_appointment($this->appts[2]));

        // Administrator can see all appointments.
        $p = new scheduler_permissions($this->context, $this->administ);
        $this->assertTrue($p->can_see_appointment($this->appts[0]));
        $this->assertTrue($p->can_see_appointment($this->appts[1]));
        $this->assertTrue($p->can_see_appointment($this->appts[2]));

        // Student can see only his own appointment.
        for ($i = 0; $i < 3; $i++) {
            $p = new scheduler_permissions($this->context, $this->students[$i]);
            for ($j = 0; $j < 3; $j++) {
                $actual = $p->can_see_appointment($this->appts[$j]);
                $expected = ($i == $j);
                $msg = "Student $i with id {$this->students[$i]} tested on appointment $j booked by {$this->appts[$j]->studentid}";
                $this->assertEquals($expected, $actual, $msg);
            }
        }

    }


}
