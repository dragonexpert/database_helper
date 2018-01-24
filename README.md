# database_helper
A plugin for MyBB to grant some new database related functions.  It is currently written for PHP 5 series because PHP 7 is not yet required
for the core product and would force a huge rewrite.

## Supported Database Extensions
* MySQLi
* MySQL
* PostGreSQL ( Note: Untested. )
* SQLite ( Note: Untested. )

## Key Methods  
* add_foreign_key - adds a foreign key.  Not supported in SQLite.
* drop_foreign_key - removes a foreign key.  Not supported in SQLite.
* get_foreign_keys - gets all foreign keys. ( Note only works on foreign keys created with add_foreign_key ).  
* count_query - performs a select count() and returns the result.  
* fetch_clean_array - similar to fetch_array, but calls htmlspecialchars_uni on the result before returning it.  
* fetch_clean_field - same as above except a field.  
* truncate_table - Truncates a table.  

## Additional Benefits
* Calls to the query method are screened for DROP TABLE, TRUNCATE TABLE, and DELETE FROM.
* The only way to execute queries that do those things is to use the method designed for them. Ex. delete_query
* SELECT statements are scanned for a LIMIT clause.  If one is not present, it prepends the value of $config['database']['limit'].
* If not set, it will default to 50 so you don't overload memory.
* If a limit clause is set, it verifies that it is within the allowed limit.
* Specific pages can be set to ignore the LIMIT CLause by defining $config['database']['limit_skip'] as an array and adding the value of the constant THIS_SCRIPT for each page.  Default is search.php.

## Installation
1) Upload all files to their directories.
2) Install in the Admin CP.

## Usage
* If you are on a page that loads global.php, you can use $db->helper->method|property after you load global.php.
* The plugin has a priority rate for global_start designed to execute first.  Make sure any plugin hooks use a value higher than 0.

## Contributing
* Feature suggestions and bugs can be reported here by opening an issue.
* Creating a way to add a foreign key to an existing table in SQLite.
