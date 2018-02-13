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
$table->construct_header($lang->database_helper_file);
$table->construct_header($lang->database_helper_this_script);
$table->construct_row();
foreach($error_array as $entry)
{
    $exstring = explode("\n", $entry);
    $exstring[0] = preg_replace("/\A(.*?)<dateline>([0-9]+)<\/dateline>(.*?)\Z/is", "$2", $exstring[1]);
    $exstring[1] = preg_replace("/\A(.*?)<query>(.*)<\/query>(.*?)\Z/is", "$2", $exstring[2]);
    $exstring[2] = preg_replace("/\A(.*?)<execution_time>(.*)<\/execution_time>(.*?)\Z/is", "$2", $exstring[3]);
    $exstring[3] = preg_replace("/\A(.*?)<file>(.*?)<\/file>(.*?)\Z/is", "$2", $exstring[4]);
    $exstring[4] = preg_replace("/\A(.*?)<this_script>(.*?)<\/this_script>(.*?)\Z/is", "$2", $exstring[5]);
    if(count($exstring < 5))
    {
        $exstring[3] = $exstring[4] = $lang->database_helper_unknown;
    }
    $date = my_date($mybb->settings['dateformat'], (int) $exstring[0]);
    $time = my_date($mybb->settings['timeformat'], (int) $exstring[0]);
    $table->construct_cell($date);
    $table->construct_cell($time);
    $table->construct_cell($exstring[1]);
    $table->construct_cell($exstring[2]);
    $table->construct_cell($exstring[3]);
    $table->construct_cell($exstring[4]);
    $table->construct_row();
}
$table->output($lang->database_helper_slow_query_log);
echo $pagination;
$page->output_footer();
