<?php
namespace App;

// use one of these as dbtype: mysqli,oci8(for oracle),mssqlnative,postgres,sybase
define("PHPGRID_DBTYPE","mysqli");
define("PHPGRID_DBHOST",env("DB_HOST"));
define("PHPGRID_DBUSER",env("DB_USERNAME"));
define("PHPGRID_DBPASS",env("DB_PASSWORD"));
define("PHPGRID_DBNAME",env("DB_DATABASE"));

// Basepath for lib
define("PHPGRID_LIBPATH",dirname(__FILE__).DIRECTORY_SEPARATOR."Classes".DIRECTORY_SEPARATOR."Gridphp".DIRECTORY_SEPARATOR);

class Gridphp
{
	static function get()
	{
		include_once(PHPGRID_LIBPATH."jqgrid_dist.php");
		return new \jqgrid();
	}
}