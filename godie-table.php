<?php

defined('ABSPATH') or die('No direct access!');

class GodieTable
{
    private const TABLE_NAME = 'godie_table';
    private const TABLE_VERSION = '1.0.0';

    private $wpdb;
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . self::TABLE_NAME;
    }

    public function get_all()
    {
        $table_content = $this->wpdb->get_results("SELECT ID,eventdate,location,type,preacher,calid,info FROM `$this->table_name`");
        return $table_content;
    }

    public function insert_record($gd)
    {
        // prepare and bind
        $stmt = $this->wpdb->prepare("INSERT INTO `$this->table_name` (ID, eventdate, location, type, info, preacher, calid) values (%d,%s,%s,%s,%s,%s,%d)",
                                     $gd->id, $gd->eventdate->format(DateTime::ATOM), $gd->location, $gd->type, $gd->info, $gd->preacher, $gd->calid);
        // Executing the query   
        $this->wpdb->query($stmt);
        return $this->wpdb->insert_id;
    }
    
    function update_record($gd)
    {
        // prepare and bind
        $stmt = $this->wpdb->prepare("UPDATE `$this->table_name` SET eventdate=%s, location=%s type=%s, preacher=%s, calid=%d WHERE ID=%d",
                                $gd->eventdate->format(DateTime::ATOM), $gd->location, $gd->type, $gd->info ,$gd->preacher, $gd->calid, $gd->id);
        // Executing the query
        $this->wpdb->query($stmt);
    }

    public function get_cal_id($id) : ?int
    {
        return $this->wpdb->get_var($this->wpdb->prepare("SELECT calid FROM `$this->table_name` WHERE ID=%d", $id));
    }

    function get_ids()
    {
        $results = $this->wpdb->get_results("SELECT ID, calid FROM `$this->table_name`");
        $res = array();
        foreach( $results as $item ) {
            $res[$item->ID] = $item->calid;
        }
        return $res;
    }

    function delete($id)
    {
        $this->wpdb->query($wpdb->prepare("DELETE FROM `$this->table_name` WHERE ID = $id"));
    }

    public function create()
    {
        $charset_collate = $this->wpdb->get_charset_collate();

        //Check to see if the table exists already, if not, then create it
        if ($this->wpdb->get_var("show tables like '$this->table_name'") != $this->table_name) {
            $sql = "CREATE TABLE `$this->table_name` (
                    `ID` int(11) NOT NULL,
                    `eventdate` DATETIME  NULL,
                    `location` varchar(50)  NULL,
                    `type` varchar(50)  NULL,
                    `preacher` varchar(50) NULL,
                    `calid` int(11) NULL,
                    `info` varchar(255) NULL,
                    PRIMARY KEY (`ID`)
            ) $charset_collate;";

            require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            add_option('test_db_version', self::TABLE_VERSION);
        }
    }
}
