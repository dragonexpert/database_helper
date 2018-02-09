<?php
if(!defined("IN_MYBB"))
{
    die("Direct access not allowed.");
}
$page->add_breadcrumb_item($lang->database_helper_slow_query_log, "index.php?module=tools-slow_query_log");
$page->output_header($lang->database_helper_slow_query_log);
$baseurl = "index.php?module=tools-slow_query_log";
$table = new TABLE;
if(file_exists(MYBB_ROOT . "/slowquery.log"))
{
    $filecontents = file_get_contents(MYBB_ROOT . "/slowquery.log");
}
else
{
    $page->output_error($lang->database_helper_slow_query_file_not_found);
    $page->output_footer();
}
$entries = explode("\n\n", $filecontents);
$itemcount = count($entries);
$last = $itemcount - 1;
unset($entries[$last]);
if($mybb->input['page'])
{
    $current_page = $mybb->get_input("page", MyBB::INPUT_INT);
}
else
{
    $current_page = 1;
}
if(!$current_page)
{
    $current_page = 1;
}
$pages = ceil($last / 50);
if($current_page > $pages)
{
    $current_page = $pages;
}
$start = $current_page * 50 - 50;
$pagination = draw_admin_pagination($current_page, 50, $last, "index.php?module=tools-slow_query_log");
/* Flip the array so the newest errors are shown first since that is usually what admins want. */
$entries = array_reverse($entries);
$error_array = array_slice($entries, $start, 50);
echo $pagination;
$table->construct_header($lang->database_helper_date);
$table->construct_header($lang->database_helper_time);
$table->construct_header($lang->database_helper_query);
$table->construct_header($lang->database_helper_execution_time);
$table->construct_row();
foreach($error_array as $entry)
{
    $string = preg_replace("/\A<slowquery>\n\t<dateline>([0-9]+)<\/dateline>\n\t<query>(.*)<\/query>"
    . "\n\t<execution_time>(.*)<\/execution_time>\n<\/slowquery>(.*?)\Z/is", "$1--$2--$3", $entry);
    $exstring = explode("--", $string);
    $date = my_date($mybb->settings['dateformat'], $exstring[0]);
    $time = my_date($mybb->settings['timeformat'], $exstring[0]);
    $table->construct_cell($date);
    $table->construct_cell($time);
    $table->construct_cell($exstring[1]);
    $table->construct_cell($exstring[2]);
    $table->construct_row();
}
$table->output($lang->database_helper_slow_query_log);
echo $pagination;
$page->output_footer();
