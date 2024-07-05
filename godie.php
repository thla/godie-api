<?php

defined('ABSPATH') or die('No direct access!');

class Godie
{
    // Tag	Datum	Zeit	Ort	Gottesdienstart	Prediger
    public $id;
    public $datetime;
    public $location;
    public $type;
    public $preacher;
    public ?int $calid;
    public $info;

    public function __construct()
    {
    }

    public function load_from_xml_item($xml_item)
    {
        $this->id = (int) $xml_item->ID;
        $this->datetime = DateTime::createFromFormat('d.m.Y H.i', (string) $xml_item->Datum . ' ' . (string) $xml_item->Zeit);
        $this->location = (string) $xml_item->Gottesdienstort;
        list($gdtype, $gdinfo) = array_map('trim', explode('-', (string) $xml_item->Gottesdienstinfos, 2));
        $this->type = $gdtype;
        $this->info = $gdinfo;
        $this->preacher = (string) $xml_item->Prediger_in;
        $this->calid = null;
    }

    public function make_calendar_entry($action)
    {
        if (!function_exists('my_calendar_save') || !function_exists('mc_update_event')) return; 

        // Insert an event.
        $submit = array(
            // Begin strings.
            'event_begin' => $this->datetime->format('Y-m-d'), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
            'event_end' => $this->datetime->format('Y-m-d'), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
            'event_title' => $this->type,
            'event_desc' => "<p>{$this->type}<br>{$this->info}<br>{$this->location}<br>{$this->preacher}</p>",
            'event_short' => "{$this->type} {$this->location}",
            'event_time' => $this->datetime->format('H:i:s'),
            'event_endtime' => '',
            'event_link' => 'https://godi.ekiba.org/Godiorg/kbz-bretten-bruchsal/index.php?thema=Login&username=linkheidelsheim',
            'event_label' => '',
            'event_street' => '',
            'event_street2' => '',
            'event_city' => '',
            'event_state' => '',
            'event_postcode' => '',
            'event_region' => '',
            'event_country' => '',
            'event_url' => '',
            'event_recur' => 'S1',
            'event_image' => '',
            'event_phone' => '',
            'event_phone2' => '',
            'event_access' => '',
            'event_tickets' => '',
            'event_registration' => '',
            'event_repeats' => '',
            // Begin integers.
            'event_author' => 1,
            'event_category' => 2,
            'event_link_expires' => 0,
            'event_zoom' => 16,
            'event_approved' => 1,
            'event_host' => 1,
            'event_flagged' => 0,
            'event_fifth_week' => 0,
            'event_holiday' => 0,
            'event_group_id' => 1,
            'event_span' => 0,
            'event_hide_end' => 1,
            // Begin floats.
            'event_longitude' => '',
            'event_latitude' => '',
            // Array: removed before DB insertion.
            'event_categories' => array(1),
        );

        $event = array(true, false, $submit, false);
        if ('add' === $action || 'copy' === $action) {
            $response = my_calendar_save('add', $event);
        } else {
            $response = my_calendar_save('edit', $event, $this->calid);
        }

        $event_id = $response['event_id'];
        $locid = 0;
        if (str_contains(strtolower($this->location), 'heidelsheim')) {
            $locid = 2;
        } else if (str_contains(strtolower($this->location), 'helmsheim')) {
            $locid = 3;
        }
        mc_update_event('event_location', $locid, $event_id);
        $this->calid = $event_id;
        return $event_id;
    }

    /**
     * Checks if the elapsed time between $startDate and now, is bigger
     * than a given period. This is useful to check an expiry-date.
     * @param DateTime $startDate The moment the time measurement begins.
     * @param DateInterval $validFor The period, the action/token may be used.
     * @return bool Returns true if the action/token expired, otherwise false.
     */
    public static function isExpired(DateTime $startDate, DateInterval $validFor)
    {
        $now = new DateTime();

        $expiryDate = clone $startDate;
        $expiryDate->add($validFor);

        return $now > $expiryDate;
    }

}
