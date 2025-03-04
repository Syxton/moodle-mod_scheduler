<?php

/**
 * Defines the mod_scheduler booking form added event.
 *
 * @package    mod_scheduler
 * @copyright  2014 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_scheduler\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The mod_scheduler booking form added event.
 *
 * Indicates that a student has booked into a slot.
 *
 * @package    mod_scheduler
 * @copyright  2014 Henning Bostelmann and others (see README.txt)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class booking_added extends slot_base {

    /**
     * Create this event on a given scheduler.
     *
     * @param \mod_scheduler\model\scheduler $scheduler
     * @return \core\event\base
     */
    public static function create_from_slot(\mod_scheduler\model\slot $slot) {
        $event = self::create(self::base_data($slot));
        $event->set_slot($slot);
        return $event;
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_bookingadded', 'scheduler');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has booked into the slot with id  '{$this->objectid}'"
                ." in the scheduler with course module id '$this->contextinstanceid'.";
    }
}
