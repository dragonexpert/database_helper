<?php
/**
 * Created by PhpStorm.
 * User: Latios
 * Date: 1/23/2018
 * Time: 9:51 PM
 */

/* We use 0 for priority because others will depend on it. */
$plugins->add_hook("global_start", "database_helper_start", 0);

function database_helper_info()
{
    global $lang;
    $lang->load("database_helper");
    return array(
        "name" => $lang->database_helper,
        "description" => $lang->database_helper_description,
        "author" => "Mark Janssen",
        "version" => "1.0",
        "codename" => "database_helper",
        "compatibility" => "18*"
    );
}

function database_helper_start()
{
    global $db;
    require_once "database_helper/classes/" . $db->type . "_extender.php";
    $classname = $db->type . "_extender";
    $db->helper = new $classname();
}

function database_helper_install()
{
    global $db;
    /* Create a table for holding foreign keys since not all users have access to information_schema. */
    $db->write_query("CREATE TABLE " . TABLE_PREFIX . "foreign_keys (
    id INT NOT NULL AUTO_INCREMENT,
    constraint_name VARCHAR(50),
    parent_table VARCHAR(50),
    parent_column VARCHAR(50),
    child_table VARCHAR(50),
    child_column VARCHAR(50),
    on_update VARCHAR(20) DEFAULT 'CASCADE',
    on_delete VARCHAR(20) DEFAULT 'CASCADE',
    PRIMARY KEY (id)
    ) ENGINE = Innodb " . $db->build_create_table_collation());
}

function database_helper_is_installed()
{
    global $db;
    return $db->table_exists("foreign_keys");
}

function database_helper_activate()
{

}

function database_helper_deactivate()
{

}

function database_helper_uninstall()
{
    global $db, $cache;
    if($db->table_exists("foreign_keys"))
    {
        $db->drop_table("foreign_keys");
    }
}
