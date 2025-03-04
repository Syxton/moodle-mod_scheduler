<?php

/**
 * A class for representing a scheduler appointment.
 *
 * @package    mod_scheduler
 * @copyright  2011 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_scheduler\model;

defined('MOODLE_INTERNAL') || die();



/**
 * A class for representing a scheduler appointment.
 *
 * @copyright  2011 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appointment extends mvc_child_record_model {


    protected function get_table() {
        return 'scheduler_appointment';
    }

    public function __construct(slot $slot) {
        parent::__construct();
        $this->data = new \stdClass();
        $this->set_parent($slot);
        $this->data->slotid = $slot->get_id();
        $this->data->attended = 0;
        $this->data->appointmentnoteformat = FORMAT_HTML;
        $this->data->teachernoteformat = FORMAT_HTML;
    }

    public function save() {
        $this->data->slotid = $this->get_parent()->get_id();
        parent::save();
        $scheddata = $this->get_scheduler()->get_data();
        scheduler_update_grades($scheddata, $this->studentid);
    }

    public function delete() {
        $studid = $this->studentid;
        parent::delete();

        $scheddata = $this->get_scheduler()->get_data();
        scheduler_update_grades($scheddata, $studid);

        $fs = get_file_storage();
        $cid = $this->get_scheduler()->get_context()->id;
        $fs->delete_area_files($cid, 'mod_scheduler', 'appointmentnote', $this->get_id());
        $fs->delete_area_files($cid, 'mod_scheduler', 'teachernote', $this->get_id());
        $fs->delete_area_files($cid, 'mod_scheduler', 'studentnote', $this->get_id());

    }

    /**
     * Retrieve the slot associated with this appointment
     *
     * @return scheduler_slot;
     */
    public function get_slot() {
        return $this->get_parent();
    }

    /**
     * Retrieve the scheduler associated with this appointment
     *
     * @return scheduler
     */
    public function get_scheduler() {
        return $this->get_parent()->get_parent();
    }

    /**
     * Return the student object.
     * May be null if no student is assigned to this appointment (this _should_ never happen).
     */
    public function get_student() {
        global $DB;
        if ($this->data->studentid) {
            return $DB->get_record('user', array('id' => $this->data->studentid), '*', MUST_EXIST);
        } else {
            return null;
        }
    }

    /**
     * Has this student attended?
     */
    public function is_attended() {
        return (boolean) $this->data->attended;
    }

    /**
     * Are there any student notes associated with this appointment?
     * @return boolean
     */
    public function has_studentnotes() {
        return $this->get_scheduler()->uses_studentnotes() &&
                strlen(trim(strip_tags($this->studentnote))) > 0;
    }

    /**
     * How many files has the student uploaded for this appointment?
     *
     * @return int
     */
    public function count_studentfiles() {
        if (!$this->get_scheduler()->uses_studentnotes()) {
            return 0;
        }
        $ctx = $this->get_scheduler()->context->id;
        $fs = get_file_storage();
        $files = $fs->get_area_files($ctx, 'mod_scheduler', 'studentfiles', $this->id, "filename", false);
        return count($files);
    }

}

