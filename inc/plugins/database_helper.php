<?php
/**
 * Created by PhpStorm.
 * User: Latios
 * Date: 1/23/2018
 * Time: 9:51 PM
 */
if(!defined("IN_MYBB"))
{
    die("Direct access to this file is not allowed.");
}
/* We use 0 for priority because others will depend on it. */
$plugins->add_hook("global_start", "database_helper_start", 0);
if(defined("IN_ADMINCP"))
{
    $plugins->add_hook("admin_tools_menu_logs", "database_helper_admin_tools_menu_logs");
    $plugins->add_hook("admin_tools_action_handler", "database_helper_admin_tools_action_handler");
    $plugins->add_hook("admin_tools_permissions", "database_helper_admin_tools_permissions");
}

function database_helper_info()
{
    global $lang;
    $lang->load("database_helper");
    return array(
        "name" => $lang->database_helper,
        "description" => $lang->database_helper_description,
        "author" => "Mark Janssen",
        "version" => "2.0",
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

function database_helper_admin_tools_menu_logs(&$sub_menu)
{
    global $lang;
    $lang->load("database_helper");
    $sub_menu[78] = array(
        "id" => "slow_query_log",
        "title" => $lang->database_helper_slow_query_log,
        "link" => "index.php?module=tools-slow_query_log"
    );
}

function database_helper_admin_tools_action_handler(&$actions)
{
    $actions['slow_query_log'] = array(
        "active" => "slow_query_log",
        "file" => "slow_query_log.php"
    );
}

function database_helper_admin_tools_permissions(&$admin_permissions)
{
    global $lang;
    $lang->load("database_helper");
    $admin_permissions['slow_query_log'] = $lang->database_helper_can_view_slow_query_log;
}
