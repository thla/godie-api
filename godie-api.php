<?php
/*
 Plugin Name: Godie Api
 Description: checks https://godi.ekiba.org/ for new events
 Version: 1.0
 Author: Thomas Lamparter
 Author URI: https://eki-heidelsheim.de/
 */
defined('ABSPATH') or die('No direct access!');
require_once __DIR__ . '/godie.php';
require_once __DIR__ . '/godie-table.php';

$godie_api = new GodieApi();

class GodieApi
{
    private const GODIE_URL = 'https://godi.ekiba.org/Godiorg/Systemadministration/Exportdatei/Export_HeidelHelm.xml';
    private const USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.0.0 Safari/537.36";

    private $table;

    public function __construct()
    {
        $this->table = new GodieTable();
        register_activation_hook(__FILE__, array($this, 'godieApi_activate'));
        register_activation_hook(__FILE__, array($this->table, 'create'));
        register_activation_hook(__FILE__, array($this, 'update_events'));

        register_deactivation_hook(__FILE__, array($this, 'godieApi_deactivation'));

        add_action('godieApiEvent', array($this, 'update_events'));
    }



    public function update_events()
    {
        try {
            $xml = self::get_xml(self::GODIE_URL);
            $gdArray = self::parse_xml($xml);

            foreach ($gdArray as $gd) {
                $gd->calid = $this->table->get_cal_id($gd->id);
                if ($gd->calid === null) {
                    $gd->make_calendar_entry('add');
                    $gd->id = $this->table->insert_record($gd);
                } else {
                    $this->table->update_record($gd);
                    $gd->make_calendar_entry('edit');
                }
            }


            // delete expired events
            $items = $this->table->get_all();
            $validFor = new DateInterval('P10D'); // valid for 10 days
            foreach ($items as $item) {
                $startDate = new DateTime($item->datetime);
                $isExpired = Godie::isExpired($startDate, $validFor);
                if ($isExpired) {
                    if (function_exists('mc_delete_event')) {
                        echo mc_delete_event($item->calid);
                    }
                    $this->table->delete($item->ID);                    
                }
            }
        } catch (Exception $e) {
            error_log(
                sprintf(
                    'Script failed with error #%d: %s',
                    $e->getCode(),
                    $e->getMessage()
                )
            );
        }
    }

    public function godieApi_activate()
    {
        if (!wp_next_scheduled('godieApiEvent')) {
            wp_schedule_event(time(), 'daily', 'godieApiEvent');
        }
    }

    public function godieApi_deactivation()
    {
        if (wp_next_scheduled('godieApiEvent')) {
            wp_clear_scheduled_hook('godieApiEvent');
        }
    }

    private static function get_xml($url)
    {
        $xml = "";
        try {
            // initializing the cURL request
            $curl = curl_init();
            // setting the URL to reach with a GET HTTP request
            curl_setopt($curl, CURLOPT_URL, $url);
            // to make the cURL request follow eventual redirects
            // and reach the final page of interest
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            // to get the data returned by the cURL request as a string
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            // setting the User-Agent header
            curl_setopt($curl, CURLOPT_USERAGENT, self::USER_AGENT);
            // executing the cURL request and
            // get the HTML of the page as a string
            $xml = curl_exec($curl);
            // Check the return value of curl_exec(), too
            if ($xml === false) {
                throw new Exception(curl_error($curl), curl_errno($curl));
            }
        } catch (Exception $e) {
            trigger_error(
                sprintf(
                    'Curl failed with error #%d: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                E_USER_ERROR
            );
        } finally {
            // Close curl handle unless it failed to initialize
            if (is_resource($curl)) {
                curl_close($curl);
            }
        }
        return $xml;
    }

    private static function parse_xml($xml)
    {
        $gdArray = array();

        if (empty($xml)) {
            echo "No godie content found!";
        } else {
            //echo "godie content found!";
            libxml_use_internal_errors(true);
            $godies = new SimpleXMLElement($xml);
            if ($godies === false) {
                echo "Laden des XML fehlgeschlagen\n";
                foreach (libxml_get_errors() as $error) {
                    echo "\t", $error->message;
                }
            }

            foreach ($godies as $godie) {
                $gd = new Godie();
                $gd->load_from_xml_item($godie);
                $gdArray[] = $gd;

                //var_dump($godie);
                //echo 'Datumxxx  ' . $godie->ID  . PHP_EOL;
                //echo 'Datum  ' . $dt->format('d.m.Y H.i')  . PHP_EOL;
                //echo 'Gottesdienstort  ' .  . PHP_EOL;
                //echo 'Gottesdienstinfos  ' . (string) $godie->Gottesdienstinfos  . PHP_EOL;
                //echo 'Prediger_in  ' . (string) $godie->Prediger_in  . PHP_EOL;
                //echo '---------------------------------------------------------------------------'  . PHP_EOL;
            }
        }
        return $gdArray;
    }
}
