<?php
/**
 * Grid 4 PHP Component
 *
 * @author Abu Ghufran <gridphp@gmail.com> - https://www.gridphp.com
 * @version 2.9 build 20240320-1630
 * @license: see license.txt included in package
 */

$old_error_reporting = error_reporting();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set("display_errors","off");

class jqgrid
{
	// id of grid
	var $id;

	// grid version
	var $version = "2.9.20240320.1630";

	// grid parameters
	var $options = array();

	// nav grid parameters
	var $navgrid = array();

	// internal use params
	var $internal = array();

	// select query to show data
	var $select_command;

	// db table name used in add,update,delete
	var $table;

	// allowed operation on grid
	var $actions;

	### P ###
	// var for conditional css data
	var $conditional_css;

	### P ###
	// var for conditional css data
	var $group_header;

	### P ###
	// var for pivot grid options
	var $pivot_options;

	// show server error
	var $debug;

	// show debug search
	var $debug_search;

	// db connection identifier - not used now, @todo: need to integrate adodb lib
	var $con;

	// db char set
	var $charset;

	// db driver name
	var $db_driver;

	// callback events
	var $events;

	// buy note
	var $buy_license = " is not supported in free version. <a href='https://www.gridphp.com/compare?track=fx-code' target='_blank'>Buy Licensed Version</a>.";
	/**
	 * Contructor to set default params
	 */
	function __construct($db_conf = null)
	{
		// defined check for backward compatibility
		if ($db_conf == null)
		{
			if (version_compare(PHP_VERSION, '5.5.0', '<=') && @mysql_ping())
			{
				// if old php and using mysql_x functions
				// do nothing, use existing connection
			}
			elseif (defined("PHPGRID_DBTYPE"))
			{
				// make new connection from config.php constants
				$db_conf = array();
				$db_conf["type"] = PHPGRID_DBTYPE;
				$db_conf["server"] = PHPGRID_DBHOST;
				$db_conf["user"] = PHPGRID_DBUSER;
				$db_conf["password"] = PHPGRID_DBPASS;
				$db_conf["database"] = PHPGRID_DBNAME;

				// failover for php <= 5.3 and mysql
				if ( version_compare( phpversion(), '5.3', '<=' ) && PHPGRID_DBTYPE == 'mysqli' )
					$db_conf["type"] = 'mysql';
			}
		}

		// resume older session or create new
		if(session_id() == '') session_start();

		$this->db_driver = "mysql";
		$this->debug = 1;
		$this->debug_search = 0;

		// shown in case of debug = 0
		$this->error_msg = "Some issues occured in this operation, Contact technical support for help";
		$this->having_clause = array();

		// set default charset to utf8
		if (defined("PHPGRID_DBCHARSET"))
			$this->charset = PHPGRID_DBCHARSET;
		else
			$this->charset = "utf8";

		// use adodb layer to support non-mysql dbs
		if ($db_conf)
		{
			// make lower case for adodb file inclusion (in case of typo)
			$db_conf["type"] = strtolower($db_conf["type"]);

			// set up DB
			include_once(dirname(__FILE__)."/adodb/adodb.inc.php");
			$driver = $db_conf["type"];
			$this->con = ADONewConnection($driver); # eg. 'mysql,oci8(for oracle),mssql,postgres,sybase'
			$this->con->SetFetchMode(ADODB_FETCH_ASSOC);
			$this->con->debug = 0;
			$this->con->charSet = $this->charset;

			// set port if exist
			if (isset($db_conf["port"]))
				$this->con->port = $db_conf["port"];

			$r = $this->con->Connect($db_conf["server"], $db_conf["user"], $db_conf["password"], $db_conf["database"]);

			// missing extension check
			if ($r===0) phpgrid_error("You need to enable php extension '".$this->con->dataProvider."' first.");

			// if connection failed
			if (!$r)  phpgrid_error("Please check your database connection configuration. ".$this->con->ErrorMsg());

			// set your db encoding -- for ascent chars (if required)
			if ($db_conf["type"] == "mysql" || $db_conf["type"] == "mysqli" || ($db_conf["type"]=="pdo" && strstr($db_conf["server"],"mysql")!==false) )
				$this->con->Execute("SET NAMES '".$this->charset."'");

			$this->db_driver = $db_conf["type"];

			// set server for strstr match in case of pdo
			if ($this->db_driver == "pdo")
				$this->db_driver = $db_conf["server"];

			$this->db_conf = $db_conf;
		}

		// set utf8 encoding for old style mysql connection (fix for php7 & load-array)
		if ($this->db_driver == "mysql" && function_exists("mysql_query"))
			@mysql_query("SET NAMES '".$this->charset."'");

		$grid["datatype"] = "json";
		$grid["rowNum"] = 20;
		$grid["rowList"] = array(10,20,30,'All');
		$grid["width"] = 900;
		$grid["height"] = 350;
		$grid["forceFit"] = true;
		$grid["viewrecords"] = true;
		$grid["multiSort"] = false;
		$grid["scrollrows"] = true;
		$grid["gridview"] = true;
		$grid["sanitize"] = true;
		// $grid["loadui"] = "block"; // show overlay while loading
		$grid["toppager"] = false;
		// renamed qstr variable due to wordpress conflict
		$grid["prmNames"] = array("page"=>"jqgrid_page");
		$grid["altRows"] = true;
		$grid["altclass"] = "ui-alt-rows";
		$grid["autoformat"] = true; // auto suggest formatter and edittype (if no columns defined)
		$grid["hidegrid"] = false; // show top right hide-button

		// default sort options (first field and asc)
		$grid["sortname"] = "1";
		$grid["sortorder"] = "asc";
		$grid["form"]["nav"] = false;
		$grid["forcessl"] = false;
		$grid["onPaging"] = "function (btn) { btn = btn.replace('_pager',''); btn = btn.substr(btn.indexOf('_')+1); jQuery('#'+btn).closest('.ui-jqgrid-bdiv').scrollTop(0); }";

		$protocol = "http";

		### P ### - allow only http for free
		if ($this->is_secure()) $protocol = "https";

		$grid["url"] = $protocol."://".$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];

		### P ###
		// pass subgrid params if exist
		$s = (strstr($grid["url"], "?")) ? "&":"?";
		if (isset($_REQUEST["rowid"]) && isset($_REQUEST["subgrid"]))
			$grid["url"] .= $s."rowid=".$_REQUEST["rowid"]."&subgrid=".$_REQUEST["subgrid"];
		### P-END ###

		// xss fix for querystring
		$grid["url"] = $this->sanitize_xss_url($grid["url"]);

		$grid["editurl"] = $grid["url"];
		$grid["cellurl"] = $grid["url"];

		// virtual scrolling, for big datasets
		$grid["scroll"] = 0;

		// drag drop columns to sort
		$grid["sortable"] = true;
		$grid["headertitles"] = true;

		// excel like editing
		$grid["cellEdit"] = false;

		### P ###
		// if specific export is requested
		if (isset($_GET["export_type"]) && ($_GET["export_type"] == "xls" || $_GET["export_type"] == "xlsx" || $_GET["export_type"] == "excel"))
			$grid["export"]["format"] = "excel";
		else if (isset($_GET["export_type"]) && $_GET["export_type"] == "pdf")
			$grid["export"]["format"] = "pdf";
		else if (isset($_GET["export_type"]) && $_GET["export_type"] == "csv")
			$grid["export"]["format"] = "csv";
		else if (isset($_GET["export_type"]) && $_GET["export_type"] == "html")
			$grid["export"]["format"] = "html";
		else if (isset($_GET["export_type"]) && $_GET["export_type"] == "word")
			$grid["export"]["format"] = "word";

		// default pdf export options
		$grid["export"]["paper"] = "a4";
		$grid["export"]["orientation"] = "landscape";
		$grid["export"]["range"] = "filtered";
		### P-END ###

		$grid["add_options"] = array("recreateForm" => true, "closeAfterAdd"=>true, "closeOnEscape"=>true,
										"errorTextFormat"=> "function(r){ return r.responseText;}", "jqModal" => false, "modal" => true,
										"width" => 500, 
										"afterShowForm" => "function(form){ 					
											// inside dialog scroll
											var h = jQuery(window).height() * 0.8;
											form.css('maxHeight', h); }"
										);
		$grid["edit_options"] = array("recreateForm" => true, "closeAfterEdit"=>true, "closeOnEscape"=>true,
										"errorTextFormat" => "function(r){ return r.responseText;}", "jqModal" => false, "modal" => true,
										"width" => 500, 
										"afterShowForm" => "function(form){ 					
											// inside dialog scroll
											var h = jQuery(window).height() * 0.8;
											form.css('maxHeight', h); }"
										);
		$grid["delete_options"] = array("closeOnEscape"=>true, "errorTextFormat"=> "function(r){ return r.responseText;}", "jqModal" => true,
										"width" => 400, "modal" => true
										);

		$grid["view_options"] = array("jqModal" => false, "modal" => true, "closeOnEscape"=>true, "recreateForm"=>true, 
										"width" => 500,
										"rowButton" => true, "labelswidth" => "25%", 
										"afterShowForm" => "function(form){ 					
											// inside dialog scroll
											var h = jQuery(window).height() * 0.8;
											form.css('maxHeight', h); }"
										);

		$grid["search_options"] = array("jqModal" => true, "modal" => true, "searchOnEnter" => true, "beforeShowSearch" => "", "closeOnEscape"=>true,
										"width" => 500);

		$grid["clone_options"] = array();
		$grid["bulkedit_options"] = array();

		$grid["autofilter_options"] = array("searchOnEnter" => false, "autosearchDelay" => 700, "defaultSearch" => 'cn');

		### F - for free version only ###
		// $grid["loadtext"] = "Loading ...";
		// $x = "st"."r"."_"."r"."ot".strval((15%8)+6);
		// $y = $x("obggbzvasb");
		// // $grid["add_options"][$y] = str_rot13('<center>You are using Free version. For commercial use <a href="http://www.gridphp.com/compare/?track=free-dialog" target="_blank" style="color:blue">Buy Licensed Version</a></center>');
		// $grid["add_options"][$y] = $x('<pragre>Lbh ner hfvat Serr irefvba.<oe />Sbe pbzzrepvny hfr <n uers="uggc://jjj.cuctevq.bet/pbzcner/?genpx=serr-qvnybt" gnetrg="_oynax" fglyr="pbybe:oyhr">Ohl Yvprafrq Irefvba</n></pragre>');
		// $grid["edit_options"][$y] = $grid["add_options"][$y];
		### F-END ###

		$grid["form"]["position"] = "center";
		$grid["actionicon"] = true;
		$grid["columnicon"] = false;
		$grid["multiselect"] = false;
		$grid["persistsearch"] = false;
		$grid["treeGrid"] = false;
		$grid["reloadedit"] = false;
		$grid["responsive"] = true;
		$grid["autoheight"] = false;
		$grid["resizable"] = false;
		$grid["tooltip"] = true;
		$grid["hotkeys"] = false;
		$grid["globalsearch"] = true;
		$grid["colNames"] = array();
		$grid["toolbar"] = "both";

		// new icons for subgrid
		$grid["subGridOptions"]["plusicon"] = "ui-icon-carat-1-e";
		$grid["subGridOptions"]["minusicon"] = "ui-icon-carat-1-s";
		$grid["subGridOptions"]["openicon"] = "";

		// for comfort layout
		$grid["multiselectWidth"] = 50;
		// $grid["subGridWidth"] = "30";
		$grid["rowheight"] = 22;
		$grid["roweffect"] = true;

		$this->options = $grid;

		// set default action settings
		$this->actions["search"] = "";
		$this->actions["add"] = true;
		$this->actions["edit"] = true;
		$this->actions["delete"] = true;
		$this->actions["view"] = true;
		$this->actions["refresh"] = true;
		$this->actions["autofilter"] = true;
		$this->actions["rowactions"] = true;
		$this->actions["clone"] = false;
		$this->actions["bulkedit"] = false;
		$this->actions["export"] = false;
		$this->actions["export_csv"] = false;
		$this->actions["export_pdf"] = false;
		$this->actions["export_excel"] = false;
		$this->actions["showhidecolumns"] = false;
		$this->actions["inlineadd"] = $this->actions["inline"]= false;
	}


	public function array_is_associative($array)
	{
		return (bool)count(array_filter(array_keys($array), 'is_string'));
	}

	/**
	 * Helping function to parse array
	 */
	public function strip($value)
	{
		// gpc line removed for wp plugin search fix
		// if(get_magic_quotes_gpc() != 0)
		{
			if(is_array($value))
				if ( $this->array_is_associative($value) )
				{
					foreach( $value as $k=>$v)
						$tmp_val[$k] = stripslashes($v);
					$value = $tmp_val;
				}
				else
					for($j = 0; $j < sizeof($value); $j++)
						$value[$j] = stripslashes($value[$j]);
			else
				$value = stripslashes($value);
		}
		return $value;
	}

	/**
	 * Advance search where clause maker
	 */
	private function construct_where($s)
	{
		$qwery = "";

		// if called from wordpress, remove auto magic quotes of WP
		if (function_exists("wp_magic_quotes"))
			$s = stripslashes($s);

		// fix for daterange input, removing quotes from date pattern
		// "data":"{\"start\":\"2021-08-03\",\"end\":\"2021-08-09\"}"
		$re = '/\"{\\\\\"([a-z]+)\\\\\":\\\\\"(.+)\\\\\",\\\\\"([a-z]+)\\\\\":\\\\\"(.+)\\\\\"}\"/m';
		$subst = '{"$1":"$2","$3":"$4"}';
		$s = preg_replace($re, $subst, $s);

		if ($s) {
			$jsona = (array)json_decode($s,true);
			if(is_array($jsona))
			{
				$qwery = $this->make_where($jsona);
			}
		}

		if (!empty($qwery))
			$qwery = " AND ( $qwery )";

		if ($this->debug_search && $_GET["_search"] == "true")
			die($qwery);

		return $qwery;
	}

	private function make_where($jsona)
	{
		$qwery = "";

		//['eq','ne','lt','le','gt','ge','bw','bn','in','ni','ew','en','cn','nc']
		$qopers = array(
					  'eq'=>" = ",
					  'ne'=>" <> ",
					  'lt'=>" < ",
					  'le'=>" <= ",
					  'gt'=>" > ",
					  'ge'=>" >= ",
					  'bw'=>" LIKE ",
					  'bn'=>" NOT LIKE ",
					  'in'=>" IN ",
					  'ni'=>" NOT IN ",
					  'ew'=>" LIKE ",
					  'en'=>" NOT LIKE ",
					  'se'=>" = ",
					  'cn'=>" LIKE " ,
					  'nu'=>" IS NULL " ,
					  'nn'=>" IS NOT NULL " ,
					  'nc'=>" NOT LIKE " );

		$gopr = $jsona['groupOp'];
		$rules = $jsona['rules'];
		$groups = $jsona['groups'];

		$i=0;

		// if global search (split csv field names to make array)
		$search_type = $this->strip($_REQUEST['searchtype']);

		// skip global search for state persist reload
		$field_count = count(explode(",",$rules[0]["field"]));
		if (empty($search_type) && $field_count > 1) unset($rules[0]);

		$qwery = "";

		if (is_array($rules))
		foreach($rules as $key=>$val)
		{
			$val = (array)$val;
			$op = $val['op'];

			# fix for conflicting table name fields (used alias from page, in property dbname)
			foreach($this->options["colModel"] as $link_c)
			{
				// only used exact date match, when operator is not 'cn' (contains) - default is cn
				if ($val['field'] == $link_c["name"] && !empty($link_c["formatoptions"]) && in_array($op, array("ne","eq","gt","ge","lt","le")))
				{
					// fix for d/m/Y or d/m/y date format. strtotime expects m/d/Y
					if (stristr($link_c["formatoptions"]["newformat"],"d/m/Y"))
					{
						$val['data'] = preg_replace('/(\d+)\/(\d+)\/(\d+)/i','$2/$1/$3',$val['data']);
					}
					// fix for d-m-y (2 digit year) for strtotime
					else if (strstr($link_c["formatoptions"]["newformat"],"d-m-y"))
					{
						$val['data'] = preg_replace('/(\d+)-(\d+)-(\d+)/i','$3-$2-$1',$val['data']);
					}
					else if (strstr($link_c["formatoptions"]["newformat"],"d/M/Y") || strstr($link_c["formatoptions"]["newformat"],"d-M-Y"))
					{
						$val['data'] = preg_replace('/\/\-/i',' ',$val['data']);
					}
	
					if ($link_c["formatter"] == "date")
						$val['data'] = $this->custom_date_format($link_c["formatoptions"]["srcformat"],$val['data'],$link_c["formatoptions"]["newformat"]);
					else if ($link_c["formatter"] == "datetime")
						$val['data'] = $this->custom_date_format($link_c["formatoptions"]["srcformat"],$val['data'],$link_c["formatoptions"]["newformat"]);
				}

				if ($val['field'] == $link_c["name"] && !empty($link_c["dbname"]))
				{
					$val['field'] = $link_c["dbname"];
				}
			}

			$field = $val['field'];

			// skip if some mysql function is used, e.g. concat
			$is_fx = false;
			$is_fx = (strstr($field,"(") !== false && strstr($field,")") !== false);

			// add tilde sign for mysql
			if (!$is_fx)
			{
				$field = $this->wrap_field($field);
			}

			// for daterange fix for warning
			if (!is_array($val["data"]))
				$v = trim($val['data']);
			else
				$v = $val['data'];

			// escape %,_ sign for mysql (wildcard)
			$db_conf = $this->db_conf;
			if ($db_conf["type"] == "mysql" || $db_conf["type"] == "mysqli" || ($db_conf["type"]=="pdo" && strstr($db_conf["server"],"mysql")!==false) )
			{
				// and like search
				if ($op == "cn" || $op == "bw" || $op == "ew" )
				{
					$v = str_replace("%","\\%",$v);
					$v = str_replace("_","\\_",$v);
				}
			}

			// enable >,>=,<,<= in search textbox
			// search behavior of AND words. ">10 <20" ===> id > 10 AND id < 20
			if (!is_array($v))
			{
				if (strpos($v,"!=") === 0 || strpos($v,">=") === 0 || strpos($v,"<=") === 0 || strpos($v,"=") === 0 || strpos($v,">") === 0 || strpos($v,"<") === 0)
				{
					$new_arr = array();
					$v_arr = explode(" ",$v);
					foreach($v_arr as $v_item)
					{
						$v_item = trim($v_item);
						$d_len = 0;
						if (strpos($v_item,"!=") === 0 || strpos($v_item,">=") === 0 || strpos($v_item,"<=") === 0)
							$d_len = 2;
						else if (strpos($v_item,"=") === 0 || strpos($v_item,">") === 0 || strpos($v_item,"<") === 0)
							$d_len = 1;

						if ($d_len > 0)
						{
							$d_op = substr($v_item,0,$d_len);
							$d_val = substr($v_item,$d_len);

							if (is_numeric($d_val))
							{
								$new_arr[] = "{field} $d_op $d_val";
							}
						}
					}

					if (count($new_arr))
					{
						$v = implode(" AND ",$new_arr);
						$op = 'inline';
						$qopers['inline']='';
					}
				}
			}
			// if array (usually IN operator multiselect)
			else if ($op == "in" && is_array($v))
				$v = implode(",",$v);

			// if aggregate fx, skip default where clause
			$val['field'] = trim($val['field']);
			$is_agg_fx = (strpos(strtolower($val['field']),"count(") === 0 || strpos(strtolower($val['field']),"group_concat(") === 0 || strpos(strtolower($val['field']),"sum(") === 0
							|| strpos(strtolower($val['field']),"max(") === 0 || strpos(strtolower($val['field']),"min(") === 0);
			if ($is_agg_fx)
			{
				$v = $this->to_sql($val['field'],$op,$v);
				$this->having_clause[] = $val['field'].$qopers[$op]." $v";
				continue;
			}

			if(isset($v) && isset($op))
			{
				$i++;

				$v_raw = $v;
				// ToSql in this case is absolutley needed
				$v = $this->to_sql($val['field'],$op,$v);

				if ($i > 1)
					$qwery .= " " .$gopr." ";

				// fix for informix
				if(substr($this->db_driver, 0, strlen("informix:")) === "informix:")
				{
					if($op <> 'le' && $op <> 'lt' && $op <> 'ge' && $op <> 'gt' )
						$field = "to_char($field)";
				}

				switch ($op) {
					// in need other thing
					case 'in' :
					case 'ni' :
						$qwery .= $field.$qopers[$op]." (".$v.")";
						break;
					case 'cn' :
						if (strpos($this->db_driver,"postgres") !== false || strpos($this->db_driver,"pgsql") !== false )
							$qwery .= "LOWER(CAST($field as text))".$qopers[$op]." LOWER(".$v.")";
						// make case insensitive for oracle and db2
						else if (strpos($this->db_driver,"oci8") !== false || strpos($this->db_driver,"db2") !== false || strpos($this->db_driver,"firebird") !== false || strpos($this->db_driver,"informix") !== false)
							$qwery .= "LOWER($field)".$qopers[$op]." LOWER(".$v.")";
						else
						{
							// update for nvarchar datatypes (support unicode)
							// todo: add this in all like searches
							if (strpos($this->db_driver, "mssql") !== false || strpos($this->db_driver, "dblib") !== false)
								$v = "N$v";

							$qwery .= $field.$qopers[$op].$v;
						}
						break;
					case 'bw' :
						$qwery .= "LOWER($field)".$qopers[$op]." LOWER(".$v.")";
						break;
					case 'nn' :
					case 'nu' :
						$qwery .= $field.$qopers[$op];
						break;
					case 'bt' :
						if (empty($v)) phpgrid_error("Please enter daterange to filter");
						$qwery .= "($field >= '{$v['start']} 00:00:00' AND $field <= '{$v['end']} 23:59:59')";
						// for oracle 19c
						// $qwery .= "($field >= to_date('{$v['start']} 00:00:00','yyyy-mm-dd hh24:mi:ss') AND $field <= to_date('{$v['end']} 23:59:59','yyyy-mm-dd hh24:mi:ss'))";
						break;
					case 'se' :
						$qwery .= "(soundex($field) $qopers[$op] soundex($v) OR $field like '%$v_raw%')";
						break;
					case 'fs' :
						$qwery .= "(FIND_IN_SET('$v_raw',$field))";
						break;
					case 'inline' :
						// replaced field here as we don't need to escape field dbname value (sql)
						$v = str_replace("{field}",$field,$v);
						$qwery .= "($v)";
						break;
					default:
						$qwery .= $field.$qopers[$op].$v;
				}
			}
		}

		if (!empty($groups))
		{
			if (!empty($rules))
				$qwery .= " $gopr ";

			foreach($groups as &$g)
			{
				$tmp = $this->make_where($g);
				if (!empty($tmp))
					$group_qwery[] = "($tmp)";
			}

			if (!empty($group_qwery))
				$qwery .= implode(" $gopr ",$group_qwery);
		}
		return $qwery;
	}

	/**
	 * Advance search, make search operator sql compatible
	 */
	private function to_sql($field, $oper, $val)
	{
		//mysql_real_escape_string is better
		if($oper=='bw' || $oper=='bn') return "'" . $this->escape_string($val) . "%'";
		else if ($oper=='ew' || $oper=='en') return "'%" . $this->escape_string($val) . "'";
		else if ($oper=='cn' || $oper=='nc') return "'%" . $this->escape_string($val) . "%'";
		else if ($oper=='inline') return $this->escape_string($val);
		else if ($oper=='in' || $oper=='ni')
		{
			// only enquote '' if isnum != true (means string)
			foreach($this->options["colModel"] as $c)
			{
				if ($field == $c["name"] || $field == $c["dbname"])
				{
					if ($c["isnum"] == true)
						return $val;
					else
					{
						// escape in case of string in multi-select
						
						// if values conatins comma
						preg_match("/\'.*,.*\'/m",$val,$result);
						if (count($result))
						{
							$varr = explode("','",$val);
							foreach($varr as $ik => &$iv)
							{
								$iv = trim($iv,"'");
								$iv = $this->escape_string($iv);
							}
							$val = implode("','",$varr);
							return "'".$val."'";
						}
						// otherwise just escape normally
						else
						{
							$varr = explode(",",$val);
							foreach($varr as $ik => &$iv)
							{
								$iv = trim($iv,"'");
								$iv = $this->escape_string($iv);
							}
							$val = implode(",",$varr);
							return "'".implode("','",str_getcsv($val, ",", "'"))."'";
						}

					}
				}
			}
		}
		else if ($oper=='bt') return $val;
		else
		{
			// only enquote '' if isnum != true (means string)
			foreach($this->options["colModel"] as $c)
				if ($field == $c["name"] || $field == $c["dbname"])
				{
					if ($c["isnum"] == true)
						return $val;
					else
						return "'" . $this->escape_string($val) . "'";
				}
		}
	}

	### P ###
	/**
	 * Setter for event handler
	 */
	function set_events($arr)
	{
		### F ###
		// phpgrid_error("Event handler".$this->buy_license);
		$this->events = $arr;
	}

	### P ###
	/**
	 * Get dropdown values using ajax, onchange of dropdowns
	 */
	function get_dependent_dropdown($sql,$return_format)
	{
		$select = array();
		$result = $this->execute_query($sql);

		if ($this->con)
		{
			$arr = $result->GetRows();

			foreach($arr as $rs)
			{
				$rs["k"] = (!empty($rs["K"])) ? $rs["K"] : $rs["k"];
				$rs["v"] = (!empty($rs["V"])) ? $rs["V"] : $rs["v"];

				$select[$rs["k"]] = $rs["v"];
			}
		}
		else
		{
			$arr = array();
			while($rs = mysql_fetch_array($result,MYSQL_ASSOC))
			{
				$arr[] = $rs;
				$select[$rs["k"]] = $rs["v"];
			}
		}

		$str = "";
		if ($return_format == "option")
		{
			// return html for dependent dropdown ajax
			foreach($select as $k => $v)
			{
				$str .= "<option value='$k'>$v</option>";
			}
		}
		elseif ($return_format == "select")
		{
			$str .= "<select>";
			// return html for dependent dropdown ajax
			foreach($select as $k => $v)
			{
				$str .= "<option value='$k'>$v</option>";
			}
			$str .= "</select>";
		}
		elseif ($return_format == "json")
		{
			$str = json_encode($arr);
		}

		echo $str;
		die;
	}

	### P ###
	/**
	 * Get dropdown values for select dropdowns
	 */
	function get_dropdown_values($sql,$sep=":",$delim=";")
	{
		### F ###
		// phpgrid_error("Lookup Dropdown".$this->buy_license);

		$str = array();
		$result = $this->execute_query($sql);

		if ($this->con)
		{
			$arr = $result->GetRows();

			foreach($arr as $rs)
			{
				$rs["k"] = (isset($rs["K"])) ? $rs["K"] : $rs["k"];
				$rs["v"] = (isset($rs["V"])) ? $rs["V"] : $rs["v"];

				$str[] = $rs["k"].$sep.$rs["v"];
			}
		}
		else
		{
			while($rs = mysql_fetch_array($result,MYSQL_ASSOC))
			{
				$str[] = $rs["k"].$sep.$rs["v"];
			}
		}

		$str = implode($delim,$str);
		return $str;
	}

	/**
	 * Setter for allowed actions (add/edit/del/autofilter etc)
	 */
	function set_actions($arr)
	{
		if (empty($arr))
			$arr = array();

		if (empty($this->actions))
			$this->actions = array();

		// for add_option array
		foreach($arr as $k=>$v)
			if (is_array($v))
			{
				if (!isset($this->actions[$k]))
					$this->actions[$k] = array();

				$arr[$k] = array_merge($arr[$k],$this->actions[$k]);
			}

		$this->actions = array_merge($this->actions,$arr);
	}

	/**
	 * Setter for grid customization options
	 */
	function set_options($options)
	{
		if (empty($arr))
			$arr = array();

		if (empty($this->options))
			$this->options = array();

		// reset responsive if width is set
		if (isset($options["width"]))
		{
			$options["responsive"] = false;
			$this->options["responsive"] = false;
		}

		if (isset($options["rowList"]))
			unset($this->options["rowList"]);

		// for export like array merge
		foreach($options as $k=>$v)
			if (is_array($v))
			{
				if (!isset($this->options[$k]))
					$this->options[$k] = array();

				$options[$k] = array_merge($this->options[$k],$options[$k]);
			}

		$this->options = array_merge($this->options,$options);

		// xss fix for querystring
		$this->options["url"] = $this->sanitize_xss_url($this->options["url"]);

		### P ###
		if ($this->options["forcessl"] === true) 
			$this->options["url"] = str_ireplace("http://","https://",$this->options["url"]); 

		$this->options["editurl"] = $this->options["url"];
		$this->options["cellurl"] = $this->options["url"];

		// our logo
		// $this->options["icon"] = "<img src='http://www.gridphp.com/wp-content/uploads/logo-small-live.png'>";

		// pencil vector
		// $this->options["icon"] = '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAAVXQAAFV0Bemv8ZgAAAAd0SU1FB+ACGhU5HJ+c5dcAAAi6SURBVGje1Zh7cJTlFYef91sC5MLNKsF26FBUnBqjSEBFVGyh6hRLKTpVIggkEBDFDk0MgiCxaolcBCUFjcbLjCWJ1oLWC0itiq2g1Mu0Vk2Clw4CuW5CAkl293vP6R/fbgiQYEhz853Z+WZ3s5nz5Dm/852NoYceu27dQhFZ64p87FqbbV13x8Bly+rKsrKIz8o64eednlS8rF8fgVgvIo9Ya6OstWPE2hes6ofl9957cXxWFvuXLz/hs6YHmlgkIg+51qoVMdZaXBFcaxHvumDoAw9s2puZiYgwYs2anmOkmYmHReQhay1WxLhhCCuCiKi1Vq21G/dmZm46e9UqXGt7npFWTYggESDXxYqo673/2Hnr1s3/8PbbGZWTg687i086P4GD5RWUv/duTvQXXy93VT0TYQs2DGHDj/DrxrVWxdrRaWPGVIzKydnz/vz53W/khim/vCv7wZUr+1VUasw/dhlx3dZMHJ8Vz4zrXnppbu573ZKR9/6+E4BPP/4wp7K6euWcufOIS0w0jVdegdvYiLXWg7AW2wzMHoVoAhTVZ3empPTr1R0gl1x+JSuW3Z2RmDTmtr9seUFjBww0n5Xs5ccjR9LQy4f78quegXDh9lgTEVsm/PrZInJ1lxrZ92UJAEPi4ze+tXPn6vVrV1PXEDAJCQnUVB9i+2uvEZuQgO/aa3Dr673CWzHhepMt8vpdXQoydPg5VHzxTPo/d79765EjR3Rk0hguGzuW13fs4EfDfkh5eTl5eU/hnH0WvadOwao2FeyGW02aPW822S7sEhAtig5fnccG2UVrorSIN//2llFVSkpKuOiCC3h2cz6Xj7uM2tpa1qxeS/+EBPpMuhbb2IAbMRDJTcSEiIZb7nddAmLObUCLfEtA0nyO1cFn1NI7KsTopFEcLC2luKSEqydOYNWqtVw1/goOHDjAgoW/wQwbRuzUqVho2YS1RkTyJxUU3O90lQmwv8fpg8Y/Y3BOIyr0Jhqq5ZqfTeCTT/7D2++8wy3Tp/HbjMXMnjmDurpaZs1K4bQLExkw5ReEGhuPBv6oiexJBQXJnb6ihE0sBUnDN1AZUohxosGtxDSUElP3NFGmjgXz5/P1V1+za/f7ZCy6gxuTpzNrxnTKKyqZPPUGooYP54ybpyGqzU1snlRQsOSVm27qvBVFi2Iw59aHTUgaTl8YUgBODLiV0FCNhvwYtwqRAIdibqOuoS/zbr2NmJhoJl83iVmpc9n6fCHrN+RgRXhpy58o/eAj/fLxPENUVPakgoIldE3AfUu1CNW9A0Xrtqoe2a56aLNq6R9U992n+tUi1ZKZqp9dp7prvFbufVfL9u/TxIQEnTE9WfNyH1VAtz5fqInnJ+gV4y7TL0o+189e35YPEDHRKUujFkWH28nJBZnbkglCfnDDD/GjVX5MWRUiLhXn5VJjT2fy1Ov5wffPZEZyMilp89nyfKHmbNxk4mJj17748isZLbZxJ5i4G+z9+AYp8U8bnL7gVp0IYf3g90OpH0JVEKwGhLKk7WjcOVw1cSIXjx7NT34ynjnzFrBn1zv5SZeMS74o8Xw++vcnnQPSsolCCAe7JRNU+aHcDwEPQiWEUZC6/lRc9WcOmXgmX3+9DomPNxMn/DR7+Yp7T5oJ04EmloG979RN1IAEQQA/4AI+H2XXbEcGnMWYsePy9+8/kPz2G68zfsLVnQPSzMTjIHPaYwIJgQJVHoQaMIpK30GmauyGlYMvnr60TaO+Q00MecZg+rTfhMGDMoAxz5k79cZ9XxYzdPiIzgHR4hjMiHq0yHkCJBUnGs4sBNO33SbClXgYPrPGpOudp1JTu+7sZkQ9WuxbDpKKb5B6EH1ahrBhiNIIRI0HIcdBaPgP65hCk6536iOn9lXJtMtEsfMEKqk4MXBmPpjobjPRLiNNJjRioqD1duoiE6dkRIvjMCMOo0VOHkiKZ6KgIzOx2qRr5v8zdJy2mTiMFvvuAUnBd1rYRIdmIrO9JtpkpMlEsZOHRkycJNjih0o/VHSdiTYZaTKhEROFYHq3bqLSD2VtNlFg0jVTN3TMP3JMyyb6YUbUocXOk6jMbrOJcj8E22LCWWXSZXFHLqtOyybq0OJeK1CZfUomgm01IYt1Q1THfhs9xkRNPmbgNPRznsQwGyeWNgW7G0202lpa/XgKgQ/ykMNK/1sMThy4pVB/AELVRyFcP/irPBMh/1GI1nYnxxSYDJ2mG3pjFgY7D0TdI5hesaht/BcqiV5RDaBBsLVQ/Ry4dd5CaKs9E5HpFPCDuN4v6mITJ2TE9IpFQ/WxSDARW69IPWgAJAAq3sYaMVFVBWVVEKiEQLUHod+Wid50CYh3gilo0LOgAW/N1iBoKGyiylvFK8KreCDcThzXTqAYwOc8aDJ0GtAp7dQ6iIRu8sIaNOFrGAYPpMlEFQRqvs1EvkmXuzrbROQ03Y20sfR7aGgYElQ0ZJogJAiOD8r2QeURLw/BI16ofS2aMPicbJMuS7rCxIlhb/jmArAfIUHnqIkITABMLy/07mFw60EOw9ub4GDR8dNps8nQmztrOn17a2no5y1DBL0cuIfABryKHR9E9YOo6KZPhzORbTL05q400QTy15dyvUokNLtliGbPj7kGwOdrnonNJl2WdFUmWmwtVY0nUFSqwXqMhk4CEfAmmIS8YOwpUEp2G6KclSZdltKNxwEwxsx5cesODC56UhMhvGEQVCQIPmMw/NGky1Ld0IduBwGm9DvjPGrqFOOzqARaMBFU724fNEjAII2fMmjwPLOY6V4mAt0OEj9z5i1D4wcPZuGSR8l7ckvYTPiuLgGQkCChg2hwF+ouAneAGX5Pghn/Yq7uvoEecZIuGjlq27ZtmpaWpuEhqnnrblWtyFfZ/1SxfpO7VPdtvFD/+/DpxyyXXz1Ajzppc+esSE1NVUCNMaWOY/YAy1XfGMJ36fxqyuRXY2P6ZsXFRY/q3y86vvl7i+749XeG43/lLmY/yYZKhgAAAABJRU5ErkJggg==" />'.$this->options["caption"];

		// our logo small
		// $this->options["icon"] = '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADIAAAAyCAYAAAAeP4ixAAAAAXNSR0IArs4c6QAAAAZiS0dEAP8A/wD/oL2nkwAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB+ACGhYkBQbdn1IAAAAZdEVYdENvbW1lbnQAQ3JlYXRlZCB3aXRoIEdJTVBXgQ4XAAAG10lEQVRo3u2Zf3BU1RXHP/fe9/ZtSDY/hjAJyEQyjSmkCTGCDMiQ9Aco0kEKWui0THGoluqA2kJrpTi0xbFlhCkQacmoQwdTqDiltSApCFaCNAipiWiFBotpVBIJgWTzw2x237v9Y5OYH5tNkHRDWu7M/pi7e967n3vP+Z5z7xOJeQVcPLKMxLxts0AsBO5jGDYBMDJ32wYhxMr2Pt3RP6xAEvMKvgwcRqMRww+go0lgffvaDFsIAAOYjNYaITpBbH8AMQAsaRg9ejS2X6F1eGMhNMq0u3mw1uCzjX7vqYSDqexe3m+0X7kbRNrsGWjbCXtB2+/n38WlSCU7+xxHkHPnO8SOagxr6631UH4wA/mpKfHuFpbf+lcM2fd9lXQ4WpXG/rNZmMoJAdJ1Tm2blOlZOIHwM+Nv8VH52gnoAoIWZMw4y5jP14S1Pf/PZMoPfKFdV4It1mpl+a2vYhj+MC4QHNTeimxM+gEB0E7wFa5pJ/QfHEfi2CqsrePI0JNjq7ArAmD3YSv5H2nXQa6DXAf5PwExQic7jROww0toH7/bfonfp/pJpr3nz9GC1oAZ1s5UkkAf8isS8wp0t/ygNe54T9dcFTqPaI3P29S1KEBriI5vaS8/woEomutHdCuDlLBJjvEiRfgbe31u6ltH9CqhjN51kMDX0DSw0rnH1YSAloYRA7TtAacVHzUmfCbb68E+bILdH3Cuyc2JBqQUKCn6B2lqaaNkxyLa/PY1B6KUZPfBCra9+DYuU4UHCdiam8ePitws6+ARgRAd34MionXooC55qxrH0QNzrUhCdChfRWUdhfvepbHZx4KZ6cyYlNJz43rlMRIpACEE1bVefrypmD2vVmIYBlLCc3+uoPCJmdz1pfQBwwyJagkh+PiilzPnLpAQG8XEmxLRGpQUCAQuQ/HIU8UEbHvAKyIjGwsdn5rVm4vJumcXa58+ysp7p5L/aC5aOwgRBK255CO/8OTVye9/y41qLzWxcNXLjE+N49mfz2FSZjkrNx7D0Q5PrZrJX15/jwNvfIxpKKJcisKi9/j+t6eAkEO3Il0VqOStD9i+p5w4j5tHl07mT69VkXvvC3z37mweuCeD/N+/y6GSczzz09kYMpgxhBB4m9oI2HroXCsonYKmllaWrN7LV5fvY9WmEhb9cB+zpqXy0q/mcOpsHWvyj7JuxQzGjY3hiWdK8cREMTd3HKDxBxymZydhGCryIB2r4GibrTtPkrOwkD8e+QClJEJIDp84z/fWHWTKxLGsf/g2Nu86RdX5yyy9awKnztZRXdvI1OzRfNLqcPvUMfxmzazI1Vpadw1kKP1HDRPm7eAnvy6lvsnBMo32hCewXIrdr5yjqLiCpQuySU9JYM3TJTz4jRx8vgBlp2uI91isXZbDixvn4XabkQv2oDoKqqobeGzLMQ6UVKEdMJQMCW0aig07yrgzN507bhvLHw6/T5RlkpmWwCWvj2/OyUAp1emeEVuRxmYfW3f9nZyFOyk6VgVaIKUMOYiOruNvX6DyozpmT0/F29yGt7mN1Utv5uu3j0cp1eeeY9BBOlwJYNOON/jR5hKQMpjQhKDv7WWwphJSUbjvDOk3xvP4/ZPxRFvMm5mJ2zK7Xfuqy/iBZOaGxlaKS6t4/IFckkZ6eP7lM5yubAi5c+w5CVJoauqaGZscz0Pfmtxt8EKIyIEALFlTRPGbNWTfVMZLW+Yx4XMjWfCD/b18IljbBt8dR+NozS8emsZ9Cyb2KhwHfWPVX5be/PxJDp2oJspSlFXUceBv7zM3L404j4vL3jakFGgtEEKDFthao7XD176Yys8enEbK6LhOtRsMiCuOESEEFZUXefK5N3FbQWVRSrF263HclskL6+8IDr5zfwFaO6QkWRwumM/2dbNJGR3XGcyDBXFFIFprLtU3M/+RvfhtB9EujwL4sPYTVjx5iEkZY1ixKAONptVnExct+eXDUzjxu8XckpH8mQN5UEGEEDy25XVqLvuQnY+aggNzmQaF+yv48IKX2no/phIsnpPKO3uWcP/dt2CaxlUF8iDFSNBVNvz2ODuL/oXlCuq8A2hHY9uaQMDBbQlio11MzUriO/MzyZmQPKjBPAgggrLT1azfXk5stIllKUa4DRJiTG5IimHcaA/pN8aRlTaKKMvF4rlZvcr3ITsO6nXEaTvs3zqXxIRoYqMtLJfCcilMQ9Lz6WqkAQYMorVmcuYNV6Rs1+RJ41ANrK8W9IIBrEi8x8KclH/NHo1aLkWUZfRSo5CuFe+xhtOxr9BaPzvcD7F1+/5+9/AE+bREEFqLjRePLHtFtue24dWEEGhKga/UFS9blZhbwH8AbVKiFSqcjvIAAAAASUVORK5CYII=" />';

		// append icon if set
		if (isset($this->options["icon"]) && !empty($this->options["icon"]))
			$this->options["caption"] = $this->options["icon"] . $this->options["caption"];

		// enable form prev/next buttons. disabled by default now
		$show_form_nav = '';
		if ($this->options["form"]["nav"] === true && $this->options["multiselect"] !== true)
		{
			$show_form_nav = 'setTimeout(function(){jQuery("#pData").show();jQuery("#nData").show();},100);';
		}
		else
		{
			$show_form_nav = 'setTimeout(function(){jQuery("#pData").hide();jQuery("#nData").hide();},100);';
		}

		$this->internal["add_options"]["beforeShowForm"] = $show_form_nav;
		$this->internal["edit_options"]["beforeShowForm"] = $show_form_nav;
		$this->internal["delete_options"]["beforeShowForm"] = $show_form_nav;
		// left out on view, for ease of navigation on view
		$this->internal["view_options"]["beforeShowForm"] = $show_form_nav;

		// toolbar position
		if (isset($this->options["toolbar"]) && $this->options["toolbar"] != "bottom")
		{
			$this->options["toppager"] = true;
		}

		// align dialog to center
		if ($this->options["form"]["position"] == "center")
		{
			$fx_center = ($this->options["add_options"]["jqModal"] == false) ? "fixed" : "abs";
			$this->internal["add_options"]["beforeShowForm"] .= '
																	var gid = formid.attr("id").replace("FrmGrid_","");
																	jQuery("#editmod" + gid).'.$fx_center.'center();
																';

			$fx_center = ($this->options["edit_options"]["jqModal"] == false) ? "fixed" : "abs";
			$this->internal["edit_options"]["beforeShowForm"] .= '
																	var gid = formid.attr("id").replace("FrmGrid_","");
																	jQuery("#editmod" + gid).'.$fx_center.'center();
																';

			$fx_center = ($this->options["delete_options"]["jqModal"] == false) ? "fixed" : "abs";
			$this->internal["delete_options"]["beforeShowForm"] .= '
																	var gid = formid.attr("id").replace("DelTbl_","");
																	jQuery("#delmod" + gid).'.$fx_center.'center();
																';

			$fx_center = ($this->options["view_options"]["jqModal"] == false) ? "fixed" : "abs";
			$this->internal["view_options"]["beforeShowForm"] .= '
																	var gid = formid.attr("id").replace("ViewGrid_","");
																	// inside dialog scroll
																	var h = jQuery(window).height() * 0.75;
																	jQuery("form.FormGrid").css("maxHeight", h);

																	jQuery("#viewmod" + gid).'.$fx_center.'center();
																';

			$fx_center = ($this->options["search_options"]["jqModal"] == false) ? "fixed" : "abs";
			$this->options["search_options"]["beforeShowSearch"] .= 'function(formid) {
																		if (!formid.attr("id")) return true;

																		var gid = formid.attr("id").replace("fbox_","");
																		jQuery("#searchmodfbox_" + gid).'.$fx_center.'center();
																		return true;
																	}
																';


			unset($this->options["form"]["position"]);
		}

		// show action icons by default, unless disabled
		if ($this->options["actionicon"] !== false)
		{
			$this->internal["actionicon"] = true;
			unset($this->options["actionicon"]);
		}

		// shift based selection for multiselect
		if ($this->options["multiselect"] == true)
		{
			// chain 'beforeSelectRow' function with base working
			$beforeSelectRow = '';
			if (!empty($this->options["beforeSelectRow"]))
			{
				$beforeSelectRow = "var fx = ".$this->options["beforeSelectRow"]."; return fx(rowid,e);";
				unset($this->options["beforeSelectRow"]);
			}

			$this->options["beforeSelectRow"] = "function(rowid, e)
			{
				var grid = jQuery(this), rows = this.rows,

				// get id of the previous selected row
				startId = grid.jqGrid('getGridParam', 'selrow'),
				startRow, endRow, iStart, iEnd, i, rowidIndex;

				if (!e.ctrlKey && !e.shiftKey)
				{
					//intentionally left here to show differences with
					//Oleg's solution. Just have normal behavior instead.
					//grid.jqGrid('resetSelection');
				}
				else if (startId && e.shiftKey)
				{
					//Do not clear existing selections
					//grid.jqGrid('resetSelection');

					// get DOM elements of the previous selected and
					// the currect selected rows
					startRow = rows.namedItem(startId);
					endRow = rows.namedItem(rowid);

					if (startRow && endRow)
					{
						// get min and max from the indexes of the previous selected
						// and the currect selected rows
						iStart = Math.min(startRow.rowIndex, endRow.rowIndex);
						rowidIndex = endRow.rowIndex;
						iEnd = Math.max(startRow.rowIndex, rowidIndex);

						// get the rowids of selected rows
						var selected = grid.jqGrid('getGridParam','selarrrow');

						for (i = iStart; i <= iEnd; i++)
						{
							// if this row isn't selected, then toggle it.
							// jqgrid will select the clicked on row, so just ingore it.
							// note that we still go <= iEnd because we don't know which is start or end.
							if(selected.indexOf(rows[i].id) < 0 && i != rowidIndex)
							{
								// true is to trigger onSelectRow event, which you may not need
								grid.jqGrid('setSelection', rows[i].id, true);
							}
						}
					}

					// clear text selection (needed in IE)
					if(document.selection && document.selection.empty)
					{
						document.selection.empty();
					}
					else if(window.getSelection)
					{
						window.getSelection().removeAllRanges();
					}
				}
				// commented as unabled to copy text after selection
				// grid.disableSelection();

				// chain beforeSelectRow
				$beforeSelectRow

				return true;
			}";
		}

		if (isset($this->options["readonly"]) && $this->options["readonly"] === true)
		{
			$this->actions["search"] = "";
			$this->actions["add"] = false;
			$this->actions["edit"] = false;
			$this->actions["delete"] = false;
			$this->actions["view"] = true;
			$this->actions["refresh"] = true;
			$this->actions["autofilter"] = true;
			$this->actions["rowactions"] = false;
			$this->actions["clone"] = false;
			$this->actions["bulkedit"] = false;
			$this->actions["export"] = true;
			$this->actions["export_csv"] = false;
			$this->actions["export_pdf"] = false;
			$this->actions["export_excel"] = false;
			$this->actions["showhidecolumns"] = false;
			$this->actions["inlineadd"] = $this->actions["inline"] = false;
			$this->options["toolbar"] = "both";
		}

		// disable gridview (fast rendering) if subgrid/treegrid (limitation)
		if ($this->options["subGrid"] || $this->options["treeGrid"])
			$this->options["gridview"] = false;

		// sync top toolbar of show grid (if hidden by default)
		if ($this->options["hiddengrid"] == true && $this->options["hidegrid"] == true) 
			$this->options["onHeaderClick"] = "function(state){ if (state=='visible') fx_toolbar_init(); }";
	}

	### P ###
	function set_conditional_css($params)
	{
		### F ###
		// phpgrid_error("Conditional formatting".$this->buy_license);

		$this->conditional_css = $params;
	}

	### P ###
	function set_group_header($params)
	{
		### F ###
		// phpgrid_error("Conditional formatting".$this->buy_license);

		// false  due to column resizing issue in group header
		$this->options["forceFit"] = false; 
		$this->group_header[] = $params;
	}

	### P ###
	function set_pivot_options($params)
	{
		### F ###
		// phpgrid_error("Pivot Grid".$this->buy_license);

		$this->pivot_options = $params;
	}

	/**
	 * Get column and it's properties
	 */
	function get_column($name,$prop=false)
	{
		foreach($this->options["colModel"] as $c)
		{
			if ($c["name"] == $name)
			{
				if ($prop != false)
					return (isset($c[$prop])?$c[$prop]:"");
				else
				{
					return $c;
				}
			}
		}

		return "";
	}

	/**
	 * Set column and it's properties
	 */
	function set_prop($name,$prop,$val)
	{
		foreach($this->options["colModel"] as &$c)
		{
			if ($c["name"] == $name || $name == "*")
			{
					$c[$prop] = $val;
			}
		}

		// update colNames array for titless
		if ($prop == "title")
		{
			$this->options["colNames"] = array();
			foreach($this->options["colModel"] as $t)
				$this->options["colNames"][] = $t["title"];
		}
	}

	/**
	 * Auto generate columns for grid based on SQL / table
	 */
	function set_titles($data)
	{
		$this->internal["column_titles"] = $data;
	}

	/**
	 * Auto generate columns for grid based on SQL / table
	 */
	function set_columns($cols = null, $change_field = false)
	{
		// if set something else beside array, set to blank to avoid type countable error 
		if (!is_array($cols)) $cols = array();

		// if columns not defined
		if (!$cols && !is_array($this->table) && !$this->table && !$this->select_command) die("Please specify datasource (table, select_command) OR define columns manually.");

		// if loading from array
		if (is_array($this->table))
		{
			### P ###
			// phpgrid_error("Load from array".$this->buy_license);

			$arr = $this->table;
			$f = array_keys((array)$arr[0]);
		}
		// prepare select query form table, if not for on_select where table is unset e.g. rest-api loading
		else if (!empty($this->table) || !empty($this->select_command))
		{
			// if only table is defined, make select sql for it
			if (!$this->select_command && $this->table)
				$this->select_command = "SELECT * FROM ".$this->table;

			// make sql on single line, with no extra spaces
			$this->select_command = trim($this->select_command);
			$this->select_command = preg_replace("/(\r|\n)/"," ",$this->select_command);

			// $this->select_command = preg_replace("/[ ]+/"," ",$this->select_command); // causing multi space data value to single space
			$this->select_command = trim($this->select_command);

			if ($this->select_command[0] == "(" && $this->select_command[count($this->select_command)-1] == ")")
				$this->select_command = trim($this->select_command,"()");

			// preserve subqueries
			$matches_subsql = $this->remove_subsql();

			// add where clause if not present -- fix for search feature
			if (stristr($this->select_command,"WHERE") === false)
			{
				// place group by at proper position in sql
				if (($p = stripos($this->select_command,"GROUP BY")) !== false)
				{
					$start = substr($this->select_command,0,$p);
					$end = substr($this->select_command,$p);
					$this->select_command = $start." WHERE 1=1 ".$end;
				}
				else
					$this->select_command .= " WHERE 1=1";
			}

			// re-adjust subqueries in sql
			$this->select_command = $this->add_subsql($this->select_command,$matches_subsql);

			// get sql column names by running nulled sql
			if (!empty($this->internal["sql"]))
				$this->select_command = $this->internal["sql"];

			// if not cols are defined OR partially defined, create columns meta from query
			if(!$cols || $change_field)
			{
				$sql = $this->select_command . " LIMIT 1 OFFSET 0";

				$sql = $this->prepare_sql($sql,$this->db_driver);

				$result = $this->execute_query($sql);

				if ($this->con)
				{
					// fetch fields - method 1
					if ($result->FetchField(0)->name == "bad getColumnMeta()")
					{
						foreach($result->fields as $k=>$v)
						{
							$f[] = $k;

							foreach($result->_fieldobjects as $fobj)
								if ($fobj->name == $k)
								{
									$meta[$k] = $fobj;
									break;
								}
						}
					}
					// fetch fields - method 2
					else
					{
						$cnt = $result->FieldCount();
						for ($x=0; $x<$cnt; $x++)
						{
							$fld = $result->FetchField($x);
							$fld->type = $result->MetaType($fld->type);

							$f[] = $fld->name;
							$meta[$fld->name] = $fld;
						}
					}
				}
				else
				{
					$meta = array();
					$numfields = mysql_num_fields($result);
					for ($i=0; $i < $numfields; $i++) // Header
					{
						$f[] = $n = mysql_field_name($result, $i);
						$meta[$n] = mysql_fetch_field($result, $i);
					}
				}
			}
		}

		if ($change_field)
		{
			$tmp = $cols;
			unset($cols);
		}

		// if grid columns not defined, make from sql
		$g_cols = array();
		if (!$cols)
		{
			// initialize position sequence
			$pos = array();
			for($p=0; $p<1000; $p++)
				$pos[$p] = $p;

			// dont assign custom set position, unset
			if ($change_field)
			{
				foreach($tmp as $t)
				{
					if (in_array($t["position"], $pos))
						unset($pos[$t["position"]]);
				}
			}

			$c_num = 0;
			foreach($f as $c)
			{
				// skip rnum for oci drivers
				if (strtolower($c) == 'rnum') continue;

				$col = array();
				$col["title"] = ucwords(str_replace("_"," ",$c));
				$col["name"] = $c;
				$col["index"] = $c;
				$col["position"] = array_shift($pos);
				$col["editable"] = true;

				// disable editable if done in cmTemplate
				if (isset($this->options["cmTemplate"]["editable"]) && $this->options["cmTemplate"]["editable"] === false)
					$col["editable"] = false;

				if ($c_num++ == 0 && (isset($this->options["hidefirst"]) && $this->options["hidefirst"] == true))
					$col["hidden"] = true;

				// auto suggest edit control on field type
				if (!empty($meta) && $this->options["autoformat"] !== false)
				{
					// small size for id fields
					if (substr_compare($c, "id", -strlen("id")) === 0)
					{
						$col["width"] = 50;
						$col["fixed"] = true;
					}
					else if (strpos(strtolower($meta[$c]->type),"time") !== false || $meta[$c]->type == 'T')
					{
						$col["formatter"] = "datetime";
						$col["formatoptions"] = array("srcformat"=>'Y-m-d H:i:s',"newformat"=>'m/d/Y H:i:s');
						if ($this->options["autowidth"]) $col["width"] = "40";
					}
					else if (strpos(strtolower($meta[$c]->type),"date") !== false || $meta[$c]->type == 'D')
					{
						$col["formatter"] = "date";
						$col["formatoptions"] = array("srcformat"=>'Y-m-d',"newformat"=>'m/d/Y');
						if ($this->options["autowidth"]) $col["width"] = "40";
					}
					else if (strpos(strtolower($meta[$c]->type),"blob") !== false || $meta[$c]->type == 'X' || $meta[$c]->type == 'B')
					{
						$col["edittype"] = "textarea";
					}
					else if ($meta[$c]->type == 'I' || $meta[$c]->type == 'N')
					{
						if ($this->options["autowidth"]) 
							$col["width"] = 30;
						
						$col["formatter"] = "integer";
					}
					else if ($meta[$c]->type == 'C')
					{
						if ($this->options["autowidth"]) $col["width"] = "55";
					}
				}

				$col["editoptions"] = array("size"=>20);
				$col["searchoptions"]["clearSearch"] = false; # to disable clear search (x)
				$g_cols[] = $col;
			}
		}

		$act_col = array();

		// if $change_field is passed, only update the defined column and rest from table
		if ($change_field)
		{
			$all_col_names = array();

			foreach ($g_cols as &$gc)
				$all_col_names[] = strtolower($gc["name"]);

			foreach ($g_cols as &$gc)
			{
				foreach ($tmp as $tc)
				{
					if (strtolower($gc["name"]) == strtolower($tc["name"]))
					{
						$gc = array_merge($gc,$tc);
					}
					else if (!in_array(strtolower($tc["name"]),$all_col_names))
					{
						$new_col[$tc["name"]] = $tc;
					}
				}
			}

			if (!empty($new_col))
				foreach($new_col as $n)
					array_push($g_cols,$n);
		}

		if (!$cols)
			$cols = $g_cols;

		// set count visible columns
		$count_visible = 0;
		$index_visible = 0;
		for($i=0;$i<count($cols);$i++)
			if ($cols[$i]["hidden"] !== true)
				$count_visible++;

		// to find parent dropdown and invoke .change()
		$col_dependent = array();
		for($i=0;$i<count($cols);$i++)
		{
			if (isset($cols[$i]["editoptions"]["onchange"]["update_field"]))
			{
				$col_dependent[] = $cols[$i]["editoptions"]["onchange"]["update_field"];
			}
			elseif (isset($cols[$i]["editoptions"]["onchange"][0]["update_field"]))
			{
				foreach($cols[$i]["editoptions"]["onchange"] as $a)
					$col_dependent[] = $a["update_field"];
			}
		}

		// if XS screen customized listing is set
		$xs_list = false;
		for($i=0,$j=0;$i<count($cols);$i++)
		{
			if ($cols[$i]["name"] == "xs_list")
				$xs_list = true;
		}

		// auto distribute fields on columns - init
		if (is_numeric($this->options["autocolumn"]))
		{
			$rowpos = 1;
			$colpos = 0;

			// displacement of hidden columns so they do not disturb row setting
			$rowpos_hidden = 1000;

			$col_count = intval($this->options["autocolumn"]);

			// if not overrided externally and still default 400, adjust w.r.t layout columns
			
			if ($this->options["edit_options"]["width"] == 400)
				$this->options["edit_options"]["width"] = 350*$col_count;
	
			if ($this->options["view_options"]["width"] == 400)
				$this->options["view_options"]["width"] = 350*$col_count;
	
			if ($this->options["add_options"]["width"] == 400)
				$this->options["add_options"]["width"] = 350*$col_count;
		}
				
		$cols_editable_count = 0;
		for($i=0;$i<count($cols);$i++)
			if ($cols[$i]["editable"] !== false && $cols[$i]["hidden"] !== true)
				$cols_editable_count++;

		// index attr is must for jqgrid, so add it in array
		for($i=0,$j=0;$i<count($cols);$i++)
		{
			// auto distribute fields on columns
			if (is_numeric($this->options["autocolumn"]))
			{
				// if distribute editable columns
				if ($cols[$i]["editable"] !== false)
				{
					if ($cols[$i]["hidden"] == true)
					{
						if (!isset($cols[$i]["formoptions"]["rowpos"]))
							$cols[$i]["formoptions"]["rowpos"] = $rowpos_hidden++;

						if (!isset($cols[$i]["formoptions"]["colpos"]))
							$cols[$i]["formoptions"]["colpos"] = 1;

						continue;
					}

					// reset row for next column and when first iteration j=0 (and colpos=0)
					if ($j % ceil($cols_editable_count / $this->options["autocolumn"]) == 0)
					{
						$rowpos = 1;
						$colpos++;
					}

					if (!isset($cols[$i]["formoptions"]["rowpos"]))
						$cols[$i]["formoptions"]["rowpos"] = $rowpos++;

					if (!isset($cols[$i]["formoptions"]["colpos"]))
						$cols[$i]["formoptions"]["colpos"] = $colpos;
	
					$cols[$i]["editoptions"]["tabindex"] = $j+1;
					$j++;
				}
			}

			// if no title set, auto make it from field
			if (!isset($cols[$i]["title"]))
				$cols[$i]["title"] = ucwords(str_replace("_"," ",$cols[$i]["name"]));

			$cols[$i]["index"] = $cols[$i]["name"];
			$cols[$i]["firstsortorder"] = "desc";

			// set default position for defined columns as well
			if (!isset($cols[$i]["position"]))
				$cols[$i]["position"] = $i;

			// if titles set via set_titles()
			if (isset($this->internal["column_titles"]) && isset($this->internal["column_titles"][$cols[$i]["name"]]))
				$cols[$i]["title"] = $this->internal["column_titles"][$cols[$i]["name"]];

			$cols[$i]["searchoptions"]["clearSearch"] = false; # to disable clear search (x)

			// field is editable by default, on custom column definition
			#if (!isset($cols[$i]["editable"]))
			#	$cols[$i]["editable"] = true;

			if (isset($cols[$i]["editrules"]["required"]) && $cols[$i]["editrules"]["required"] == true)
				$cols[$i]["formoptions"]["elmsuffix"] = '<font color=red> *</font>';

			if (isset($cols[$i]["formatter"]) && $cols[$i]["formatter"] == "date" && empty($cols[$i]["formatoptions"]))
				$cols[$i]["formatoptions"] = array("srcformat"=>'Y-m-d',"newformat"=>'m/d/Y');

			if (isset($cols[$i]["formatter"]) && $cols[$i]["formatter"] == "datetime" && empty($cols[$i]["formatoptions"]))
				$cols[$i]["formatoptions"] = array("srcformat"=>'Y-m-d H:i:s',"newformat"=>'m/d/Y H:i:s');

			if (isset($cols[$i]["formatter"]) && $cols[$i]["formatter"] == "integer" && empty($cols[$i]["align"]))
				$cols[$i]["align"] = "right";

			$js_dt_fmt = '';
			if (isset($cols[$i]["formatoptions"]["newformat"]))
				$js_dt_fmt = $cols[$i]["formatoptions"]["newformat"];

			if (isset($cols[$i]["formatter"]) && $cols[$i]["formatter"] == "currency" && empty($cols[$i]["formatoptions"]))
			{
				$cols[$i]["align"] = "right";
				$cols[$i]["formatoptions"] = array("prefix" => "$",
													"suffix" => '',
													"thousandsSeparator" => ",",
													"decimalSeparator" => ".",
													"decimalPlaces" => 2);
			}


			if (isset($cols[$i]["formatter"]) && $cols[$i]["formatter"] == "badge")
			{
				$cols[$i]["formatter"] = "function(cellvalue, options, rowObject){ 
    
					var colors = new Array('green','yellow','blue','red','gray','orange','pink');
					
					var tag = cellvalue.toLowerCase();
					var mapping = new Array();
					function getHash(r){for(var t=0,e=r.length,n=0;n<e;n++)t=(t<<5)-t+r.charCodeAt(n),t|=0;return t}
				
					if (options.colModel.formatoptions)
						mapping = options.colModel.formatoptions;
					else
					{
						// get random colors
						mapping[tag] = colors[Math.abs(getHash(tag)) % colors.length];
					}
				
					return '<span class=\"badge badge-'+mapping[cellvalue.toLowerCase()]+'\">'+cellvalue+'</span>'; }";

				$col["unformat"] = "function(cellvalue, options, cell){ return jQuery(cell).text(); }";
			}

			### P ###
			// reponsive settings of columns

			// globally set columns visibility e.g. xs+
			if (!empty($this->options["cmTemplate"]["visible"]) && !isset($cols[$i]["visible"]))
				$cols[$i]["visible"] = $this->options["cmTemplate"]["visible"];

			// auto hide column from last
			if ($this->options["responsive"] == true && !isset($cols[$i]["visible"]))
			{
				$this->options["autowidth"] = true;
				if ($count_visible > 2)
				{
					if ($cols[$i]["hidden"] !== true)
					{
						switch ($index_visible)
						{
							case 0:
							case 1:
								$cols[$i]["visible"] = "xs+";
							break;

							case 2:
							case 3:
								$cols[$i]["visible"] = "sm+";
							break;

							case 4:
							case 5:
								$cols[$i]["visible"] = "md+";
							break;

							case 6:
							case 7:
								$cols[$i]["visible"] = "lg+";
							break;

							default:
								$cols[$i]["visible"] = "xl";
							break;
						}
						$index_visible++;
					}
				}
			}

			if (isset($cols[$i]["visible"]))
			{
				// array("xs","sm","md","lg","xl","xs+","sm+","md+","lg+","xl+") - some of these
				if ( !is_array($cols[$i]["visible"]) )
					$cols[$i]["visible"] = array($cols[$i]["visible"]);

				// replicate other sizes for + use
				foreach($cols[$i]["visible"] as $size)
				{
					if ($size == "xs+")
					{
						$cols[$i]["visible"] = array("sm","md","lg","xl");
						
						// if no XS customized listing, show tabular columns
						if (!$xs_list)
							$cols[$i]["visible"][] = "xs";

						break;
					}
					else if ($size == "sm+")
					{
						$cols[$i]["visible"] = array("sm","md","lg","xl");
						break;
					}
					else if ($size == "md+")
					{
						$cols[$i]["visible"] = array("md","lg","xl");
						break;
					}
					else if ($size == "lg+")
					{
						$cols[$i]["visible"] = array("lg","xl");
						break;
					}
					else if ($size == "xl+")
					{
						$cols[$i]["visible"] = array("xl");
						break;
					}
				}

				// show responsively hidden columns in search/edit
				$cols[$i]["searchoptions"]["searchhidden"] = true;
				$cols[$i]["editrules"]["edithidden"] = true;
			}

			// override cmTemplate settings
			if (!empty($this->options["cmTemplate"]["edittype"]))
				$cols[$i]["edittype"] = $this->options["cmTemplate"]["edittype"];

			### P ###
			if (isset($cols[$i]["formatter"]) && $cols[$i]["formatter"] == "date")
			{
				$js_dt_fmt = str_replace("Y", "yy", $js_dt_fmt);
				$js_dt_fmt = str_replace("m", "mm", $js_dt_fmt);
				$js_dt_fmt = str_replace("d", "dd", $js_dt_fmt);
				$js_dt_fmt = str_replace("h", "", $js_dt_fmt);
				$js_dt_fmt = str_replace("H", "", $js_dt_fmt);
				$js_dt_fmt = str_replace("i", "", $js_dt_fmt);
				$js_dt_fmt = str_replace("s", "", $js_dt_fmt);
				$js_dt_fmt = str_replace(":", "", $js_dt_fmt);
				$js_dt_fmt = str_replace("A", "", $js_dt_fmt);
				$js_dt_fmt = str_replace("a", "", $js_dt_fmt);
				$js_dt_fmt = trim($js_dt_fmt);

				// fix is only for inline edit, not celledit mode
				if ($this->options["cellEdit"] !== true)
					$cols[$i]["formatoptions"]["reformatAfterEdit"]=true;

				$opts = $cols[$i]["formatoptions"]["opts"];
				if (empty($opts)) $opts = array();
				$opts = json_encode_jsfunc($opts);

				unset($cols[$i]["formatoptions"]["opts"]);

				if (!isset($cols[$i]["editoptions"]["readonly"]) && !isset($cols[$i]["editoptions"]["dataInit"]))
					$cols[$i]["editoptions"]["dataInit"] = "function(o){link_date_picker(o,'{$js_dt_fmt}',0,$opts);}";

				// only used exact date match, when operator is not 'cn' (contains)
				if ( empty($cols[$i]["searchoptions"]["sopt"]) )
				{
					$cols[$i]["searchoptions"]["sopt"] = array("eq","ne","gt","ge","lt","le","nu","nn");
				}

				$cols[$i]["searchoptions"]["dataInit"] = "function(o){link_date_picker(o,'{$js_dt_fmt}',1,$opts);}";
				
				if (!isset($cols[$i]["isnull"]))
					$cols[$i]["isnull"] = true;
			}

			// prepend empty option if not there
			if (isset($cols[$i]["stype"]) && $cols[$i]["stype"] == "select" && $cols[$i]["searchoptions"]["skipempty"]!==true && substr($cols[$i]["searchoptions"]["value"],0,1) !== ":")
			{
				// override if sep,delim set
				$sep = (!isset($cols[$i]["searchoptions"]["separator"]) ? ":" : $cols[$i]["searchoptions"]["separator"]); // :
				$delim = (!isset($cols[$i]["searchoptions"]["delimeter"]) ? ";" : $cols[$i]["searchoptions"]["delimeter"]); // ;

				if (!empty($cols[$i]["searchoptions"]["value"]))
					$cols[$i]["searchoptions"]["value"] = "$delim".$cols[$i]["searchoptions"]["value"];

				$cols[$i]["searchoptions"]["value"] = "$sep-".$cols[$i]["searchoptions"]["value"];
			}

			### P ###
			if (isset($cols[$i]["formatter"]) && $cols[$i]["formatter"] == "datetime")
			{
				// http://docs.jquery.com/UI/Datepicker/formatDate
				$dt_fmt = $js_dt_fmt;
				$dt_fmt = str_replace("Y", "yy", $dt_fmt);
				$dt_fmt = str_replace("m", "mm", $dt_fmt);
				$dt_fmt = str_replace("d", "dd", $dt_fmt);
				$dt_fmt = str_replace("h", "", $dt_fmt);
				$dt_fmt = str_replace("H", "", $dt_fmt);
				$dt_fmt = str_replace("i", "", $dt_fmt);
				$dt_fmt = str_replace("s", "", $dt_fmt);
				$dt_fmt = str_replace(":", "", $dt_fmt);
				$dt_fmt = str_replace("A", "", $dt_fmt);
				$dt_fmt = str_replace("a", "", $dt_fmt);
				$dt_fmt = str_replace("00", "", $dt_fmt);
				$dt_fmt = trim($dt_fmt);

				// http://trentrichardson.com/examples/timepicker/
				$tm_fmt = $js_dt_fmt;
				$tm_fmt = str_replace("Y", "", $tm_fmt);
				$tm_fmt = str_replace("y", "", $tm_fmt);
				$tm_fmt = str_replace("M", "", $tm_fmt);
				$tm_fmt = str_replace("m", "", $tm_fmt);
				$tm_fmt = str_replace("d", "", $tm_fmt);
				$tm_fmt = str_replace("/", "", $tm_fmt);
				$tm_fmt = str_replace("-", "", $tm_fmt);
				$tm_fmt = str_replace("H", "HH", $tm_fmt);
				$tm_fmt = str_replace("h", "hh", $tm_fmt);
				$tm_fmt = str_replace("i", "mm", $tm_fmt);
				$tm_fmt = str_replace("s", "ss", $tm_fmt);
				$tm_fmt = str_replace("A", "TT", $tm_fmt);
				$tm_fmt = str_replace("a", "tt", $tm_fmt);
				$tm_fmt = trim($tm_fmt);

				$cols[$i]["formatoptions"]["reformatAfterEdit"]=true;

				$opts = $cols[$i]["formatoptions"]["opts"];
				$opts["timeFormat"] = $tm_fmt;
				if (empty($opts)) $opts = array();
				$opts = json_encode_jsfunc($opts);

				unset($cols[$i]["formatoptions"]["opts"]);

				if (!isset($cols[$i]["editoptions"]["readonly"]))
					$cols[$i]["editoptions"]["dataInit"] = "function(o){link_datetime_picker(o,'{$dt_fmt}',0,$opts);}";

				// only used exact date match, when operator is not 'cn' (contains)
				if ( empty($cols[$i]["searchoptions"]["sopt"]) )
				{
					$cols[$i]["searchoptions"]["sopt"] = array("eq","ne","gt","ge","lt","le","nu","nn");
				}

				$cols[$i]["searchoptions"]["dataInit"] = "function(o){link_datetime_picker(o,'{$dt_fmt}',1,$opts);}";

				if (!isset($cols[$i]["isnull"]))
					$cols[$i]["isnull"] = true;
			}

			### P ###
			if (isset($cols[$i]["stype"]) && $cols[$i]["stype"] == "select-multiple")
			{
				// multi-select in search filter
				$cols[$i]["stype"] = "select";
				$cols[$i]["searchoptions"]["dataInit"] = "function(el){ setTimeout(function(){ link_multiselect(el); },200); }";
				$cols[$i]["searchoptions"]["sopt"] = array("in");
				$cols[$i]["searchoptions"]["attr"] = array("multiple"=>"multiple","size"=>4);
				$cols[$i]["searchoptions"]["multiple"] = true;
			}

			### P ###
			if (isset($cols[$i]["stype"]) && $cols[$i]["stype"] == "daterange")
			{
				// multi-select in search filter

				$opts = $cols[$i]["searchoptions"]["opts"];
				unset($cols[$i]["searchoptions"]["opts"]);
				if (empty($opts)) $opts = array();

				// use function callback in options (presetRange)
				$opts = json_encode_jsfunc($opts);

				$cols[$i]["stype"] = "text";
				$cols[$i]["searchoptions"]["dataInit"] = "function(el){ setTimeout(function(){ link_daterange_picker(el,$opts); },200); }";
				$cols[$i]["searchoptions"]["sopt"] = array("bt","nn","nu");
			}

			### P ###
			if (isset($cols[$i]["formatter"]) && $cols[$i]["formatter"] == "wysiwyg")
			{
				$cols[$i]["formatter"] = "function(cellval,options,rowdata){ jQuery(document).data('wysiwyg_{$cols[$i]["name"]}_'+options.rowId,jQuery.jgrid.htmlEncode(cellval)); return '<div style=\'white-space:inherit;\'>'+jQuery.jgrid.htmlDecode(cellval)+'</div>'; }";
				$cols[$i]["unformat"] = "function(cellval,options,rowdata){ return jQuery.jgrid.htmlDecode(jQuery(document).data('wysiwyg_{$cols[$i]["name"]}_'+options.rowId)); }";
				$cols[$i]["editoptions"]["dataInit"] = "function(el){ setTimeout(function(){ link_editor(el); },50); }";
			}

			### P ###
			if (isset($cols[$i]["formatter"]) && $cols[$i]["formatter"] == "rating")
			{
				$max = (isset($cols[$i]["formatoptions"]["count"])) ? $cols[$i]["formatoptions"]["count"] : 5;

				$cols[$i]["editoptions"]["dataInit"] = "function(o){
																// make dropdown for rating
																var s = jQuery('<select />');
																jQuery('<option />', {value: '', text: ''}).appendTo(s);
																for(var i=1;i <= $max;i++) { jQuery('<option />', {value: i, text: i}).appendTo(s); }
																jQuery(s).find('option[value='+parseInt(jQuery(o).val())+']').attr('selected',true);
																s.appendTo(jQuery(o).parent());
																s.change(function(){ jQuery(o).val(jQuery(this).val()); });
																jQuery(o).hide();
																
																// connect rating plugin
																jQuery(s).barrating({theme: 'fontawesome-stars', initialRating:jQuery(o).val()});

																// remove nbsp; from start of td
																if (jQuery(o).closest('.DataTD')[0])
																	jQuery(o).closest('.DataTD')[0].firstChild.remove()
															}";

				$cols[$i]["formatter"] = "function(cellval,options,rowdata){ 

												var s = jQuery('<select />'); 
												jQuery('<option />', {value: '', text: ''}).appendTo(s);
												for(var i=1;i<=$max;i++) 
												{ 
													jQuery('<option />', {value: i, text: i}).appendTo(s);
												} 
												jQuery(s).find('option[value='+parseInt(cellval)+']').attr('selected',true); 
												s.addClass('rating-stars').hide();

												// show as rating stars after formatter setting
												setTimeout(()=>{ jQuery('.rating-stars').closest('td').attr('title',''); jQuery('.rating-stars').barrating({theme: 'fontawesome-stars', readonly:true}); },50);
												
												return s[0].outerHTML; 
											}";

				$cols[$i]["unformat"] = "function(cellval,options,cell){ return jQuery(cell).find('select').val(); }";
			}

			### P ###
			
			// only for backward compatibility
			if (isset($cols[$i]["formatter"]) && $cols[$i]["formatter"] == "autocomplete")
			{
				if (empty($cols[$i]["editoptions"]))
					$cols[$i]["editoptions"] = array();

				if ($cols[$i]["formatoptions"] == null)
				$cols[$i]["formatoptions"] = array(); 
					
				$cols[$i]["edittype"] = "autocomplete";
				$cols[$i]["editoptions"] = array_merge($cols[$i]["editoptions"],$cols[$i]["formatoptions"]);
				unset($cols[$i]["formatter"]);
				unset($cols[$i]["formatoptions"]);
			}

			if (isset($cols[$i]["edittype"]) && $cols[$i]["edittype"] == "autocomplete")
			{
				$cols[$i]["edittype"] = "text";
				$this->internal["cols"][$i] = $cols[$i];

				if ($cols[$i]["editoptions"]["callback"])
					$param = "function(d){ d=eval(d); {$cols[$i]["editoptions"]["callback"]}(d); }";
				else
					$param = "'".$cols[$i]["editoptions"]["update_field"]."'";

				$force = 0;
				$minlen = isset($cols[$i]["editoptions"]["min_length"]) ? $cols[$i]["editoptions"]["min_length"] : 1;
				
				if ($cols[$i]["editoptions"]["force_select"] == true)
				{
					$cols[$i]["editrules"]["custom"] = true;
					$cols[$i]["editrules"]["custom_func"] = "function(val,label){ return validate_autocomplete('{$cols[$i]["name"]}'); }";
					$force = 1;
				}

				$cols[$i]["editoptions"]["dataInit"] = "function(o){ link_autocomplete(o,$param,$force,$minlen); }";

				unset($cols[$i]["editoptions"]["sql"]);
				unset($cols[$i]["editoptions"]["search_on"]);
				unset($cols[$i]["editoptions"]["force_select"]);
				unset($cols[$i]["editoptions"]["callback"]);				
			}

			### P ###
			if (isset($cols[$i]["edittype"]) && $cols[$i]["edittype"] == "color")
			{
				$cols[$i]["editoptions"]["dataInit"] = "function(o){setTimeout(function(){ 
				
														jQuery(o).parent().append('<input type=color />');
														jQuery(o).parent().find('input[type=color]').change(function(){ o.value=this.value; });
														jQuery(o).hide();
				
													},200);}";
				$cols[$i]["edittype"] = "text";
			}

			### P ###
			if (isset($cols[$i]["edittype"]) && $cols[$i]["edittype"] == "file")
			{
				// used to skip onClose delete when add form is actually submitted
				$this->options["add_options"]["onclickSubmit"] = "function(){ jQuery('.reset_upload').unbind('click'); }";
				// delete uploaded file onClose
				$this->options["add_options"]["onClose"] = "function(){ jQuery('.reset_upload').click(); }";
				$cols[$i]["editoptions"]["dataInit"] = "function(o){link_upload(o,'".$cols[$i]["name"]."');}";
				$cols[$i]["edittype"] = "text";
				$cols[$i]["show"]["list"] = false;
			}

			### P ###
			if (isset($cols[$i]["edittype"]) && $cols[$i]["edittype"] == "lookup")
			{
				$cols[$i]["editoptions"]["dataInit"] = "function(){ setTimeout(function(){ if (link_select2) link_select2('{$cols[$i]["name"]}'); },200); }";
				$cols[$i]["searchoptions"]["dataInit"] = "function(){ setTimeout(function(){ if (link_select2) link_select2('gs_{$cols[$i]["name"]}'); },200); }";

				$cols[$i]["edittype"] = "select";
				$cols[$i]["stype"] = "select";
				$cols[$i]["formatter"] = "select";

				if (!empty($cols[$i]["editoptions"]["sql"]))
				{
					$cols[$i]["editoptions"]["value"] = $this->get_dropdown_values($cols[$i]["editoptions"]["sql"]);
					$cols[$i]["searchoptions"]["value"] = ":-;".$this->get_dropdown_values($cols[$i]["editoptions"]["sql"]);
				}
				else if (!empty($cols[$i]["editoptions"]["table"]))
				{
					$opts = $cols[$i]["editoptions"];
					$sql = sprintf("SELECT %s as k, %s as v FROM %s",$opts["id"],$opts["label"],$opts["table"]);
					$cols[$i]["editoptions"]["value"] = $this->get_dropdown_values($sql);
					$cols[$i]["searchoptions"]["value"] = ":-;".$this->get_dropdown_values($sql);
				}

				unset($cols[$i]["editoptions"]["id"]);
				unset($cols[$i]["editoptions"]["label"]);
				unset($cols[$i]["editoptions"]["table"]);
				unset($cols[$i]["editoptions"]["sql"]);
			}

			### P ###
			if ((is_array($cols[$i]["editoptions"]["onchange"]) || is_array($cols[$i]["editoptions"]["onload"])) )
			{
				$col_tmp = $cols[$i];

				$old_init = "";
				if (!empty($cols[$i]["editoptions"]["dataInit"]))
					$old_init = "(".$cols[$i]["editoptions"]["dataInit"].")();";

				if (is_array($cols[$i]["editoptions"]["onchange"]))
				{

					if (!isset($cols[$i]["editoptions"]["onchange"][0]))
					{
						$col_tmp["editoptions"]["onchange"] = array($col_tmp["editoptions"]["onchange"]);
					}

					$cols[$i]["editoptions"]["onchange"] = "";

					foreach($col_tmp["editoptions"]["onchange"] as $col_change)
					{
						$field = $col_change["update_field"];
						$callback = $col_change["callback"];

						if (!empty($field))
							$cols[$i]["editoptions"]["onchange"] .= "this.event='onchange'; fx_get_dropdown(this,'$field');";
						else if (!empty($callback))
						{
							$cols[$i]["editoptions"]["onchange"] .= "this.event='onchange'; fx_get_dropdown(this,function(d){ d=eval(d); $callback(d); })";
						}

						// todo: add config to remove dependent dropdown work from autofilter search
						// to enable dependent dropdown on search toolbar
						if (!empty($field))
							$this->internal["js_dependent_dropdown"] .= "jQuery('.ui-search-toolbar select[name={$cols[$i]['name']}]').change(function(){ fx_get_dropdown(this,'$field',1); });";

						if (!empty($callback))
							$this->internal["js_dependent_dropdown"] .= "jQuery('.ui-search-toolbar select[name={$cols[$i]['name']}]').change(function(){ $callback(this); });";
					}

					// reload dependents if parent dropdown
					if (!in_array($cols[$i]["name"],$col_dependent) && empty($callback))
						$cols[$i]["editoptions"]["dataInit"] = "function(o) { setTimeout( function(){ jQuery(o).change(); },200); $old_init }";
				}

				if (is_array($cols[$i]["editoptions"]["onload"]))
				{
					// blank value to make dropdown
					if (empty($cols[$i]["editoptions"]["value"]))
						$cols[$i]["editoptions"]["value"] = ":";

					$cols[$i]["editoptions"]["dataInit"] = "function(o) { o.event = 'onload'; setTimeout(function(){ fx_get_dropdown(o,'".$cols[$i]["name"]."'); },200); $old_init }";
				}

				// on postback check
				if ($_POST["src"] == $cols[$i]["name"] && isset($_POST["return"]))
				{
					$row = $_POST;

					$target = $_POST["target"];

					// execute correct sql based on load event
					if ($row['event'] == 'onload')
					{
						$sql = $col_tmp["editoptions"]["onload"]["sql"];
					}
					else
					{
						foreach($col_tmp["editoptions"]["onchange"] as $c)
						{
							if ($c["update_field"] == $target)
							{
								$sql = $c["sql"];
								break;
							}
						}
					}

					$row[$row["src"]] = $row["value"];

					// remove non-db posted data, $row has all current selections
					unset($row["return"]);
					unset($row["src"]);
					unset($row["value"]);

					foreach($row as $k=>$v)
					{
						// for multi-select IN clause search
						if (is_array($v))
						{
							$v_quotes = "'".implode("','",$v)."'";
							$v = implode(",",$v);
						}
						else
							$v_quotes = "'".$v."'";

						if (strstr($sql,"'{".$k."}'") !== false)
							$sql = str_replace("'{".$k."}'", $v_quotes, $sql);
						else
						{
							$v = $this->escape_string($v);
							if (empty($v)) $v = 0;
							$sql = str_replace("{".$k."}", $v, $sql);
						}
					}

					// replace $GET param as well
					foreach($_GET as $k=>$v)
					{
						$v_quotes = "'".$v."'";

						if (strstr($sql,"'{".$k."}'") !== false)
							$sql = str_replace("'{".$k."}'", $v_quotes, $sql);
						else
						{
							$v = $this->escape_string($v);
							if (empty($v)) $v = 0;
							$sql = str_replace("{".$k."}", $v, $sql);
						}
					}	
										
					$this->get_dependent_dropdown($sql,$_POST["return"]);
				}

				unset($cols[$i]["editoptions"]["onload"]);
			}

			// renamed 'default' to 'template' of column
			if ($cols[$i]["template"])
				$cols[$i]["default"] = $cols[$i]["template"];

			if (!empty($this->options["readonly"]) && $this->options["readonly"] == true)
			{
				$cols[$i]["editable"] = false;
			}

			// placed icons based on formatters
			if (($this->options["columnicon"] !== false) && !empty($cols[$i]["title"]))
			{
				switch ($cols[$i]["formatter"])
				{
					case "date":
					case "datetime":
					case "daterange":
						$icon = "calendar";
						break;

					case "currency":
						$icon = "bank";
						break;

					case "select":
						$icon = "chevron-circle-down";
						break;

					case "email":
						$icon = "envelope-o";
						break;

					case "integer":
					case "number":
						$icon = "hashtag";
						break;

					default:
						$icon = "edit";
				}

				switch ($cols[$i]["edittype"])
				{
					case "upload":
						$icon = "cloud-upload";
						break;

					case "select":
						$icon = "chevron-circle-down";
						break;
				}

				if ($icon == "rename" && $cols[$i]["editable"] === false)
					$icon = "lock";

				// if false, set to remove icon
				if ($cols[$i]["icon"] === false)
					$icon = false;

				// if custom icon name, set that
				if (!empty($cols[$i]["icon"]))
					$icon = $cols[$i]["icon"];

				if ($icon == false)
					$icon_text = '';
				else
					$icon_text = '<span style="margin: 0 0.1em 0 0.3em; opacity:0.33;filter: Alpha(Opacity=33); display:inline;" class="col-icon ui-icon fa fa-'.$icon.'"></span> ';

				$cols[$i]["title"] = $icon_text . $cols[$i]["title"];
			}

		}

		// drag-drop row sorting
		if (is_array($this->options["sortrows"]))
		{
			$this->options["cmTemplate"] = array("sortable"=>false);
			$this->options["sortname"] = "COALESCE(".$this->options["sortrows"]["sortfield"].",999999999),".$this->options["sortrows"]["sortfield"];
		
			$col = array();
			$col["title"] = '';
			$col["name"] = 'position_icon';
			$col["width"] = 35;
			$col["fixed"] = true;

			if (!isset($this->options["sortrows"]["position"]) || $this->options["sortrows"]["position"] == "first")
			{
				$col["position"] = 0;
				$col["index"] = $cols[0]["index"];
			}
			else
				$col["position"] = 99999;
			
			$col["sortable"] = false;
			$col["search"] = false;
			$col["viewable"] = false;
			$col["editable"] = false;
			$col["search"] = false;
			$col["export"] = false;
			$col["show"] = array("edit"=>false, "view"=>false, "add"=>false);
			$col["hidedlg"] = true;
			
			$col["formatter"] = "function(cellvalue, options, rowObject){ return '<span style=\"float: left;cursor:move; margin-right: 0.1em;margin-left: 0.5em;\" class=\"ui-sorticon cbox ui-icon ui-icon-grip-dotted-vertical\">Drag</span>';}";
			$cols[] = $col; 		
		}

		// make first column as key for postbacks
		$cols[0]["key"] = true;

		// sort column w.r.t position
		usort($cols, function($a, $b) {
		    return ceil($a['position']) - ceil($b['position']);
		});

		$this->options["colModel"] = $cols;
		foreach($cols as $c)
		{
			// if there is any frozen column
			if ($c["frozen"] == true)
				$this->internal["frozen"] = true;

			$this->options["colNames"][] = $c["title"];
		}
	}

	/**
	 * Common functions for db operations
	 */
	function execute_query($sql,$data = false,$return="")
	{
		if ($this->con)
		{
			$ret = $this->con->Execute($sql,$data);
			if (!$ret)
			{
				if ($this->debug)
					phpgrid_error("Couldn't execute query. ".$this->con->ErrorMsg()." - $sql");
				else
					phpgrid_error($this->error_msg);
			}

			if ($return == "insert_id")
			{
				// for informix
				if(substr($this->db_driver, 0, strlen("informix:")) === "informix:")
					return $this->get_one("SELECT first 1 greatest(DBINFO( 'sqlca.sqlerrd1' ), DBINFO( 'bigserial' )) as id FROM systables;")["id"];
				else
					return $this->con->Insert_ID();
			}
		}
		else
		{
			$ret = mysql_query($sql);
			if (!$ret)
			{
				if ($this->debug)
					phpgrid_error("Couldn't execute query. ".mysql_error()." - $sql");
				else
					phpgrid_error($this->error_msg);
			}

			if ($return == "insert_id")
				return mysql_insert_id();
		}

		return $ret;
	}
	function get_one($sql,$data = false)
	{
		$res = $this->execute_query($sql,$data);
		$rs = $res->getrows();
		return $rs[0];
	}
	function get_all($sql,$data = false)
	{
		$res = $this->execute_query($sql,$data);
		$rs = $res->getrows();
		return $rs;
	}

	/**
	 * Generate JSON array for grid rendering
	 * @param $grid_id Unique ID for grid
	 */
	function render_add($grid_id)
	{
		$this->options["caption"] = "-";
		$this->options["hiddengrid"] = true;
		return $this->render($grid_id,"add");
	}

	function render($grid_id,$dialog="")
	{		
		// evaluation time period
		// if (strtotime("n"."o"."w") >= strtotime("2"."0"."2"."4"."-"."0"."3"."-"."1"."5"))
		// 	return("F"."u"."l"."l "."e"."v"."a"."l"."u"."a"."t"."i"."o"."n "."v"."e"."r"."s"."i"."o"."n "."e"."x"."p"."i"."r"."e"."d".". "."P"."l"."e"."a"."s"."e "."v"."i"."s"."i"."t "."h"."t"."t"."p"."s".":"."/"."/"."w"."w"."w"."."."g"."r"."i"."d"."p"."h"."p"."."."c"."o"."m"."/"."c"."o"."n"."t"."a"."c"."t "."f"."o"."r "."s"."u"."p"."p"."o"."r"."t.");

		// clean grid id
		$grid_id = $this->sanitize_xss($grid_id);

		// render grid for first time (non ajax), but specific grid on ajax calls
		$is_ajax = isset($_REQUEST["nd"]) || isset($_REQUEST["oper"]) || isset($_REQUEST["export"]);
		if ($is_ajax && $_GET["grid_id"] != $grid_id)
			return;

		### F ###
		// if ($is_ajax && $this->is_secure())
		// 	phpgrid_error("<br>HTTPs is supported in paid version. <br><br><a target='_blank' href='https://www.gridphp.com/compare'>Buy licensed version</a>.<br>&nbsp;");
		### F-end ###

		$append_by = (strpos($this->options["url"],"?") === false) ? "?" : "&";

		$this->options["url"] .= $append_by."grid_id=$grid_id";
		$this->options["editurl"] .= $append_by."grid_id=$grid_id";
		$this->options["cellurl"] .= $append_by."grid_id=$grid_id";

		if (isset($_REQUEST["subgrid"]))
		{
			// remove non-js variable standards as grid_id makes object var name
			$_REQUEST["subgrid"] = preg_replace("/[^A-Za-z0-9_]+/","_",$_REQUEST["subgrid"]);
			$grid_id = $_REQUEST["subgrid"]."_".$grid_id;
		}

		$this->id = $grid_id;

		// generate column names, if not defined
		if (!$this->options["colNames"])
			$this->set_columns();

		### P ###
		if (!function_exists("merge_filter_rules"))
		{
			function merge_filter_rules($one, $two, $override_when_exist = false)
			{
				// return blank
				if (empty($one->rules) && empty($two->rules))
					return new stdClass();
	
				$rules = Array();
				$out = Array();
				$result = Array();
				
				foreach($one->rules as $o)
				{
					$rules[$o->field."-".$o->op] = $o;
				}
				
				foreach($two->rules as $o)
				{
					if ($override_when_exist)
					{
						if (array_key_exists($o->field."-".$o->op,$rules)) 
						{
							$rules[$o->field."-".$o->op] = $o;
						}
					}
					else
					{
						$rules[$o->field."-".$o->op] = $o;
					}
				}

				foreach($rules as $r)
				{
					$out[] = $r;
				}
	
				if (!empty($one->rules))
				{
					$result = clone($one);
					$result->rules = $out;

					if (!empty($two->groups) && !$override_when_exist)
						$result->groups = $two->groups;
					
					return $result;
				}
				else
				{
					$result = clone($two);
					$result->rules = $out;
					return $result;
				}
			}
		}

		// if persist search
		if (!empty($this->options["persistsearch"]))
		{
			if (!empty($this->options["postData"]["filters"]))
			{
				// if no search performed manually - opened first time, retain old filters
				if (!isset($_REQUEST['filters']))
				{
					// in case first time opened
					if (empty($_SESSION["jqgrid_{$this->id}_searchstr"]))
						$_SESSION["jqgrid_{$this->id}_searchstr"] = '{}';

					$json_old = json_decode($_SESSION["jqgrid_{$this->id}_searchstr"]);
					
				}
				// else remove old filters
				else
				{
					$json_old = json_decode('{}');
				}

				// in case of no search performed
				if (empty($_REQUEST['filters']))
					$_REQUEST['filters'] = '{}';
					
				// load for hardcoded first time, then from session
				if (empty($_SESSION["jqgrid_{$this->id}_fixedfilter"]))
					$json_fix = json_decode($this->options["postData"]["filters"]);
				else
					$json_fix = json_decode($_SESSION["jqgrid_{$this->id}_fixedfilter"]);

				$json_new = json_decode($_REQUEST['filters']);

				// if fixed field is changes in new search, override
				$json_fix = merge_filter_rules($json_fix,$json_new,true);
				
				// error_log("Fix:".json_encode($json_fix));
				// error_log("New:".json_encode($json_new));
				// error_log("Old:".json_encode($json_old));
				
				$current = merge_filter_rules($json_fix,$json_old);
				$current = merge_filter_rules($current,$json_new);
			
				// error_log("MERGED:".json_encode($current));

				$_SESSION["jqgrid_{$this->id}_fixedfilter"] = json_encode($json_fix);
				$_SESSION["jqgrid_{$this->id}_searchstr"] = json_encode($current);
				
				// error_log("FIX SESSION:".json_encode($json_fix));
			}

			$this->options["postData"]["filters"] = $_SESSION["jqgrid_{$this->id}_searchstr"];
		}	
		
		// if search on load set (e.g. search-onload)
		if (!empty($this->options["postData"]))
		{
			// error_log("PostData:".$this->options["postData"]["filters"]);

			$session_search_str = $this->options["postData"]["filters"];

			// fix for daterange picker - removing outer quote: "{"start":"2016-08-09","end":"2016-08-26"}"
			$pattern = "/\"({\"start\":([^}]*),\"end\":([^}]*)})\"/";
			$session_search_str = preg_replace($pattern, "$1", $session_search_str);

			// if not already set
			$this->options["search"] = true;

			$array_of_search_values = json_decode($session_search_str, true);

			// if wrong json
			if (!is_array($array_of_search_values)) $array_of_search_values = array();

			foreach($this->options["colModel"] as &$col)
			{
				// remove all except what in session
				unset($col["searchoptions"]["defaultValue"]);

				if ($col["search"] === false)
					continue;

				foreach ($array_of_search_values["rules"] as &$rules)
				{
					if( $rules['field'] == $col["name"] )
					{
						$search_word=$rules['data'];
						if ( strstr($col["searchoptions"]["dataInit"],"daterange") !== false )
						{
							if ($rules["op"] == "ge")
								$search_daterange["start"] = $rules["data"];
							else if ($rules["op"] == "le")
								$search_daterange["end"] = $rules["data"];

							if (!empty($search_daterange["start"]) && !empty($search_daterange["end"]))
								$col["searchoptions"]["defaultValue"] = '{"start":"'.$search_daterange["start"].'","end":"'.$search_daterange["end"].'"}';
						}
						else if ( $col["searchoptions"]["multiple"] == true )
						{
							$col["searchoptions"]["defaultValue"] = explode(",",$search_word);
						}
						else
						{
							$col["searchoptions"]["defaultValue"] = $search_word;
						}
					}
				}
			}
		}

		### P ###
		// Columns hide/show with URL code, ?list1_showcols=id,invdate,note&list1_hidecols=total
		// only for first call
		if (!isset($_GET["_search"]))
		{
			$url_filter = array();
			foreach($_GET as $k=>$v)
			{
				if (substr($k,0,strlen($this->id)) == $this->id)
				{
					$action = str_replace($this->id."_","",$k);
					if (!($action == "showcols" || $action == "hidecols"))
						break;

					$cols = explode(",",$v);
					foreach($this->options["colModel"] as &$col)
					{
						if (in_array($col["name"],$cols))
						{
							if ($action == "showcols")
								$col["hidden"]=false;
							else
								$col["hidden"]=true;
						}
					}
				}
			}
		}

		### P ###
		// Filter with URL code, ?list1_closed=1 - only for first call and if colname match in grid
		if (!isset($_GET["_search"]))
		{
			$url_filter = array();
			foreach($_GET as $k=>$v)
			{
				// e.g. $k => list1_unit_price=10
				list($url_grid_id, $field) = explode("_",$k,2);
				
				if (strstr($k,$this->id))
				{
					$url_grid_id = $this->id;
					$field = str_replace($this->id."_","",$k);
				}
				else
				{
					continue;
				}
				
				if ($url_grid_id == $this->id)
				{
					$c = $field;
					foreach($this->options["colModel"] as &$col)
					{
						if ($col["name"]==$c)
						{
							// use contains by default
							$op = "cn";

							// if csv then use IN operator
							if (strstr($v,","))
								$op = "in";

							$url_filter[] = "{\"field\":\"$c\",\"op\":\"$op\",\"data\":\"$v\"}";
							$col["searchoptions"]["defaultValue"]=$v;
							$col["searchoptions"]["attr"]["defaultValue"]=$v;
							break;
						}
					}
				}
			}

			$url_filter_str = implode(",",$url_filter);
			$sarr = "{ \"groupOp\":\"AND\", \"rules\":[$url_filter_str]}";
			if (!empty($url_filter))
			{
				$this->options["search"] = true;
				$this->options["postData"] = array("filters" => $sarr);
			}
		}

		### P ###
		// if import option is requested (from grid or subgrid)

		$is_same_grid = false;
		if (isset($_REQUEST["subgrid"]))
			$is_same_grid = ($_GET["subgrid"]."_".$_GET["grid_id"] == $grid_id);
		else
			$is_same_grid = ($_GET["grid_id"] == $grid_id);

		if (isset($_REQUEST["import"]) && $is_same_grid)
		{
			$step = intval($_REQUEST["step"]);
			if ($step == 1)
			{
				include_once(dirname(__FILE__)."/import/step1.php");
				die;
			}
			else if ($step == 2)
			{
				include_once(dirname(__FILE__)."/import/step2.php");
				die;
			}
			else if ($step == 3)
			{
				include_once(dirname(__FILE__)."/import/step3.php");
				die;
			}
		}

		### P ###
		// manage uploaded files (grid_id check for master-detail fix || subgrid check)
		if (count($_FILES) && ($_REQUEST["grid_id"] == $grid_id || $_REQUEST["subgrid"]."_".$_REQUEST["grid_id"] == $grid_id))
		{
			$files = array_keys($_FILES);
			$fileElementName = $files[0];
			$msg = array();

			// support for multiple file upload
			for($f=0; $f<count($_FILES[$fileElementName]['name']); $f++)
			{
				if(!empty($_FILES[$fileElementName]['error'][$f]))
				{
					switch($_FILES[$fileElementName]['error'][$f])
					{
						case '1':
							$error = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
							break;
						case '2':
							$error = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
							break;
						case '3':
							$error = 'The uploaded file was only partially uploaded';
							break;
						case '4':
							$error = 'No file was uploaded';
							break;
						case '6':
							$error = 'Missing a temporary folder';
							break;
						case '7':
							$error = 'Failed to write file to disk';
							break;
						case '8':
							$error = 'File upload stopped by extension';
							break;
						case '999':
						default:
							$error = 'No error code avaiable';
					}
				}
				elseif(empty($_FILES[$fileElementName]['tmp_name'][$f]) || $_FILES[$fileElementName]['tmp_name'][$f] == 'none')
				{
					$error = 'No file was uploaded';
				}
				else
				{
					foreach($this->options["colModel"] as $c)
					{
						if ($c["upload_dir"] != "" && $c["name"]."_file" == $fileElementName)
						{
							$tmp_name = $_FILES[$fileElementName]["tmp_name"][$f];
							$name = $_FILES[$fileElementName]["name"][$f];

							//Get the file size in bytes.
							$filesize = filesize($tmp_name);
							// Check file has valid extension
							if (!empty($c["editrules"]["allowedsize"]))
							{
								$maxsize = intval($c["editrules"]["allowedsize"]);
								if ($filesize > $maxsize)
								{
									$error = "Uploaded file size is greater than allowed size of " . round($c["editrules"]["allowedsize"]/(1024*1024),2) . " MB.";
									break;
								}
							}

							// Check file has valid extension
							if (!empty($c["editrules"]["allowedext"]))
							{
								$extensions = explode(',', $c["editrules"]["allowedext"]);
								$extensionAuthorised = 0;
								// Extract extension from file to be uploaded
								$fileext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

								foreach ($extensions as $extension)
								{
									// Check if extension authorised
									if (strtoupper($fileext) == strtoupper(trim($extension)))
									{
										$extensionAuthorised = 1;
										break;
									}
								}

								if ($extensionAuthorised == 0)
								{
									$error = "Uploading of \'$fileext\' files are not allowed.<br>Only the following file types are allowed: " . implode(", ", $extensions) . ".";
									break;
								}
							}

							$uploads_dir = $c["upload_dir"];

							if(!file_exists($uploads_dir))
								@mkdir($uploads_dir,0755,true);

							// set to rename file by default
							if (empty($c["editrules"]["ifexist"]))
								$c["editrules"]["ifexist"] = "rename";

							// check if required
							if ($c["editrules"]["ifexist"] == "error")
							{
								if (file_exists("$uploads_dir/$name"))
								{
									$error = "File already exist: $uploads_dir/$name";
									break;
								}
							}
							else if ($c["editrules"]["ifexist"] == "rename")
							{
								// rename file if exist
								$ext = strrchr($name, '.');
								$prefix = substr($name, 0, -strlen($ext));
								$i = 0;
								while(file_exists("$uploads_dir/$name")) // If file exists, add a number to it.
								{
									$name = $prefix . "_" . ++$i . $ext;
								}
							}

							if ( @move_uploaded_file($tmp_name, "$uploads_dir/$name") )
							{
								$msg[] = "$uploads_dir/$name";
							}
							else
								$error = "Unable to move to desired folder $uploads_dir/$name";

							break;
						}
					}
				}
			}

 			echo "{";
			echo	"error: '" . $error . "',";
			echo	"msg: '" . implode(",",$msg) . "'";
			echo "}//"; # fix for upload lib, it get response from doc body that include <canvas>
			die;
		}
		### P-END ###

		if (isset($_POST["oper"]))
		{
			// removed in case of treeGrid
			if ($this->options["treeGrid"] == true)
			{
				unset($_POST["expanded"]);
				unset($_POST["icon"]);
				unset($_POST["isLeaf"]);
				unset($_POST["level"]);
				unset($_POST["loaded"]);
			}

			$op = $_POST["oper"];
			$data = $_POST;
			$pk_field = $this->options["colModel"][0]["index"];

			// fix for dialog edit v/s inline edit
			$id = (!empty($data[$pk_field])?$data[$pk_field]:$data["id"]);

			// formatters array for k->v
			$is_numeric = array();

			// reformat date w.r.t mysql
			foreach( $this->options["colModel"] as $c )
			{
				// don't fix vars that are not posted (celledit mode)
				if (!isset($data[$c["index"]]))
					continue;

				// fix for short weekday name
				if (strstr($c["formatoptions"]["newformat"],"D"))
				{
					$data[$c["index"]] = str_ireplace(array("sun","mon","tue","wed","thu","fri","sat"), "", $data[$c["index"]]);
					$data[$c["index"]] = trim($data[$c["index"]]);
				}

				// put zeros for blank date field
				if (($c["formatter"] == "date" || $c["formatter"] == "datetime") && (empty($data[$c["index"]]) || $data[$c["index"]] == "//"))
				{
					$data[$c["index"]] = "";
				}
				else if ($c["formatter"] == "date")
				{
					$data[$c["index"]] = $this->custom_date_format($c["formatoptions"]["srcformat"],$data[$c["index"]],$c["formatoptions"]["newformat"]);
				}
				else if ($c["formatter"] == "datetime")
				{
					$data[$c["index"]] = $this->custom_date_format($c["formatoptions"]["srcformat"],$data[$c["index"]],$c["formatoptions"]["newformat"]);
				}
				// remove for lookup FK data, and dont when searching in same field (autocomplete case)
				else if ($c["edittype"] == "text" && (isset($c["editoptions"]["update_field"]) && $c["index"] != $c["editoptions"]["update_field"]) )
				{
					unset($data[$c["index"]]);
				}
				else if ($c["formatter"] == "password" && $data[$c["index"]] == "*****")
				{
					unset($data[$c["index"]]);
				}

				// if db field allows null, then set NULL
				if ($c["isnull"] && $data[$c["index"]] == '') 
					$data[$c["index"]] = "NULL";

				// isnumeric check for sql '' issue
				if ($c["isnum"] === true)
					$is_numeric[$c["index"]] = true;
			}

			// handle grid operations of CRUD
			switch($op)
			{
				### P ###
				case "sortrows":
					// phpgrid_error($data);
					$field = $this->options["sortrows"]["sortfield"];
					$start = ($data["page"] - 1) * $this->options["rowNum"];
					$row_order = $start + 1;
					$seq = explode(",",$data["sequence"]);
					foreach($seq as $id)
					{
						$sql = "UPDATE {$this->table} SET $field = ".intval($row_order++)." WHERE $pk_field = {$id}";
						$this->execute_query($sql);	
					}

					$res = array("id" => 0, "success" => true);
					echo json_encode($res);
					break;

				### P ###
				case "unlink":
					$f = $data["file"];
					if (strstr($f,"..") !== false)
						break;

					// remove $_POST file path and use upload_dir for security purpose
					$p = explode("/",$f);
					$f = $p[count($p)-1];
					$col = array_pop(array_filter($this->options["colModel"], function($item){ return $item['name'] == $_POST["field"];}));
					$uploads_dir = $col["upload_dir"];

					@unlink("$uploads_dir/$f");
					$res = array("id" => 0, "success" => true);
					echo json_encode($res);
					break;

				### P ###
				case "autocomplete":
					$field = $data['element'];
					$term = $this->escape_string($data['term']);
					foreach( $this->options["colModel"] as $k => $c )
					{
						if ($c["index"] == $field)
						{
							$c["editoptions"] = $this->internal["cols"][$k]["editoptions"];

							// remove extra white spaces 
							$c["editoptions"]["sql"] = preg_replace("/(\r|\n|\t)/"," ",$c["editoptions"]["sql"]);

							// if subquery
							if (preg_match('/SELECT (.*) \\([ ]*SELECT (.*)\) (.*)/i', $c["editoptions"]["sql"], $match))
							{
								if (preg_match('/SELECT .* \\([ ]*SELECT (.*)\) (.*) WHERE (.*)/i', $c["editoptions"]["sql"], $match))
									$cond = "AND";
								else
									$cond = "WHERE";
							}
							// if normal query
							else if (stristr($c["editoptions"]["sql"], " WHERE "))
								$cond = "AND";
							else
								$cond = "WHERE";

							$search_on = (!empty($c["dbname"])) ? $c["dbname"] : $c["index"];
							if (!empty($c["editoptions"]["search_on"]))
								$search_on = $c["editoptions"]["search_on"];

							$sql = $c["editoptions"]["sql"];

							// default contains, if set then bw (begins with)
							if ($c["editoptions"]["op"] == "bw")
								$where_part = " $cond {$search_on} like '$term%'";
							else
							{
								// case in-sensitive search for oracle etc
								if (strpos($this->db_driver,"oci8") !== false || strpos($this->db_driver,"db2") !== false || strpos($this->db_driver,"postgres") !== false)
									$where_part .= " $cond LOWER({$search_on}) like LOWER('%$term%')";
								else
									$where_part = " $cond {$search_on} like '%$term%'";
							}
							// insert where condition before orderby
							if (($p = stripos($sql,"ORDER BY")) !== false)
							{
								$start = substr($sql,0,$p);
								$end = substr($sql,$p);
								$sql = $start." $where_part ".$end;
							}
							else
							{
								$sql .= $where_part;
							}

							// replace any param in link e.g. http://domain.com?id={id} given that, there is a $col["name"] = "id" exist
							$sql = $this->replace_row_data($data,$sql);

							function change_utf8_encoding($m)
							{
								return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
							}

							$result = $this->execute_query($sql);
							if ($this->con)
							{
								$rows = $result->GetArray();
								foreach ($rows as $key => $row)
								{
									$arr = array();
									$arr['id'] = (isset($row["K"]) ? $row["K"] : $row["k"]);
								    $arr['label'] = (isset($row["V"]) ? $row["V"] : $row["v"]);
								    $arr['value'] = (isset($row["V"]) ? $row["V"] : $row["v"]);
									if ($c["editoptions"]["callback"]) $arr['data'] = $row;

									// html entity code
									$arr['label'] = preg_replace_callback("/(&#[0-9]+;)/", 'change_utf8_encoding', $arr['label']);
									$arr['value'] = preg_replace_callback("/(&#[0-9]+;)/", 'change_utf8_encoding', $arr['value']);
									$arr['id'] = preg_replace_callback("/(&#[0-9]+;)/", 'change_utf8_encoding', $arr['id']);

								    $data_arr[] = $arr;
								}
							}
							else
							{
								while($row = mysql_fetch_assoc($result))
								{
									$arr = array();
								    $arr['id'] = $row['k'];
								    $arr['label'] = $row['v'];
								    $arr['value'] = $row['v'];
									if ($c["editoptions"]["callback"]) $arr['data'] = $row;

									// html entity code
									$arr['label'] = preg_replace_callback("/(&#[0-9]+;)/", 'change_utf8_encoding', $arr['label']);
									$arr['value'] = preg_replace_callback("/(&#[0-9]+;)/", 'change_utf8_encoding', $arr['value']);
									$arr['id'] = preg_replace_callback("/(&#[0-9]+;)/", 'change_utf8_encoding', $arr['id']);

									$data_arr[] = $arr;
								}
							}

							header('Content-type: application/json');
							echo json_encode($data_arr);
							die;
						}
					}
					break;

				### P ###
				case "clone":
						// only clone if grid id is matched (fix for master-detail)
						if ($data["grid_id"] != $grid_id)
							break;

						// check for crafted ajax call
						if (!$this->actions["clone"])
							phpgrid_error("Operation not allowed");

						$src_id = $data['id'];

						// get columns to build INSERT - SELECT query
						$sql = "SELECT * FROM ".$this->table . " LIMIT 1 OFFSET 0";
						$sql = $this->prepare_sql($sql,$this->db_driver);

						$result = $this->execute_query($sql);

						// and exclude PK
						if ($this->con)
						{
							$arr = $result->FetchRow();
							foreach($arr as $k=>$rs)
							{
								// skip rnum for oci drivers
								if (strtolower($k) == 'rnum') continue;

								if ($k != $pk_field)
									$f[] = $k;
							}
						}
						else
						{
							$numfields = mysql_num_fields($result);
							for ($i=0; $i < $numfields; $i++) // Header
							{
								$k = mysql_field_name($result, $i);

								// skip rnum for oci drivers
								if (strtolower($k) == 'rnum') continue;

								if ($k != $pk_field)
									$f[] = $k;
							}
						}

						// custom onclone event execution
						if (!empty($this->events["on_clone"]))
						{
							$func = $this->events["on_clone"][0];
							$obj = $this->events["on_clone"][1];
							$continue = $this->events["on_clone"][2];

							if ($obj)
								call_user_func(array($obj,$func),array($pk_field => $src_id, "params" => &$f));
							else
								call_user_func($func,array($pk_field => $src_id, "params" => &$f));

							if (!$continue)
								break;
						}

						// wrap all fields in clone query
						$pk_field = $this->wrap_field($pk_field);
						for($i=0;$i<count($f);$i++)
							$f[$i] = $this->wrap_field($f[$i]);

						$fields_str = implode(",",$f);
						$sql = "INSERT INTO {$this->table} ($fields_str) SELECT $fields_str FROM {$this->table} WHERE $pk_field IN ($src_id)";
						$insert_id = $this->execute_query($sql,false,"insert_id");

						### P ###
						// custom on_after_clone event execution
						if (!empty($this->events["on_after_clone"]))
						{
							$func = $this->events["on_after_clone"][0];
							$obj = $this->events["on_after_clone"][1];
							$continue = $this->events["on_after_clone"][2];

							if ($obj)
								call_user_func(array($obj,$func),array($pk_field => $insert_id, "params" => &$data));
							else
								call_user_func($func,array($pk_field => $insert_id, "params" => &$data));

							if (!$continue)
								break;
						}

						if (intval($insert_id)>0)
							$res = array("id" => $insert_id, "success" => true);
						else
							$res = array("id" => 0, "success" => false);

						echo json_encode($res);
					break;

				case "add":
					if ($pk_field != "id")
						unset($data['id']);

					// check for crafted ajax call
					if (!$this->actions["add"] && !$this->actions["inline"] && !$this->actions["inlineadd"])
						phpgrid_error("Operation not allowed");

					unset($data["oper"]);

					$update_str = array();

					### P ###
					// custom oninsert event execution
					if (!empty($this->events["on_insert"]))
					{
						$func = $this->events["on_insert"][0];
						$obj = $this->events["on_insert"][1];
						$continue = $this->events["on_insert"][2];

						if ($obj)
							call_user_func(array($obj,$func),array($pk_field => $id, "params" => &$data));
						else
							call_user_func($func,array($pk_field => $id, "params" => &$data));

						if (!$continue)
							break;
					}

					foreach($data as $k=>$v)
					{
						// skip first column while insert, unless autoid = false
						if ($k == $pk_field && ($this->options["colModel"][0]["autoid"] !== false && $this->options["autoid"] !== false) )
							continue;

						if ($this->options["sanitize"] !== false && $this->get_column($k,"sanitize") !== false)
						{
							// xss fix for non-html columns
							if (strstr($this->get_column($k,"formatter"),'wysiwyg') === false
								&& strstr($this->get_column($k,"formatter"),'html') === false) // custom html editor
							{
								$v = strip_tags($v);
							}
							else
							{
								$v = $this->sanitize_xss($v);
							}
						}

						$k_raw = $k;
						$k = addslashes($k);
						$v = $this->escape_string($v);

						// update for nvarchar datatypes (support unicode)
						$unicode_char = "";
						if (strpos($this->db_driver, "mssql") !== false || strpos($this->db_driver, "dblib") !== false)
							$unicode_char = "N";

						$v = ($v == "NULL" || $is_numeric[$k_raw] === true) ? $v : "$unicode_char'$v'";
						$values_str[] = "$v";

						// e.g. wrap tilde sign for mysql
						$k = $this->wrap_field($k);

						$fields_str[] = "$k";
					}

					$insert_str = "(".implode(",",$fields_str).") VALUES (".implode(",",$values_str).")";

					$sql = "INSERT INTO {$this->table} $insert_str";

					// enable SQL query debugging flag
					if (isset($this->debug_sql) && $this->debug_sql) phpgrid_error($sql);

					$insert_id = $this->execute_query($sql,false,"insert_id");

					### P ###
					// custom on_after_insert event execution
					if (!empty($this->events["on_after_insert"]))
					{
						$func = $this->events["on_after_insert"][0];
						$obj = $this->events["on_after_insert"][1];
						$continue = $this->events["on_after_insert"][2];

						if ($obj)
							call_user_func(array($obj,$func),array($pk_field => $insert_id, "params" => &$data));
						else
							call_user_func($func,array($pk_field => $insert_id, "params" => &$data));

						if (!$continue)
							break;
					}

					// for inline row addition, return insert id to update PK of grid (e.g. order_id#33)
					if ($id == "new_row")
						die($pk_field."#".$insert_id);

					// return JSON response for insert id
					if (intval($insert_id)>0)
						$res = array("id" => $insert_id, "success" => true);
					else
						$res = array("id" => 0, "success" => false);

					echo json_encode($res);

					break;

				case "edit":
					if ($pk_field != "id")
						unset($data['id']);

					unset($data["oper"]);

					$update_str = array();

					### P ###
					// custom onupdate event execution
					if (!empty($this->events["on_update"]))
					{
						$func = $this->events["on_update"][0];
						$obj = $this->events["on_update"][1];
						$continue = $this->events["on_update"][2];

						if ($obj)
							call_user_func(array($obj,$func),array($pk_field => $id, "params" => &$data));
						else
							call_user_func($func,array($pk_field => $id, "params" => &$data));

						if (!$continue)
							break;
					}

					// check for crafted ajax call - moved below on_update because it is used in bulk update as well
					if (!$this->actions["edit"] && !$this->options["cellEdit"] && !$this->actions["bulkedit"])
						phpgrid_error("Operation not allowed");

					foreach($data as $k=>$v)
					{
						// skip PK in update sql
						if ($k == $pk_field)
							continue;

						if ($this->options["sanitize"] !== false && $this->get_column($k,"sanitize") !== false)
						{
							// xss fix for non-html columns
							if (strstr($this->get_column($k,"formatter"),'wysiwyg') === false
								&& strstr($this->get_column($k,"formatter"),'html') === false) // custom html editor
								$v = strip_tags($v);
							else
							{
								$v = $this->sanitize_xss($v);
							}
						}

						$k_raw = $k;

						$k = addslashes($k);
						// e.g. wrap tilde sign for mysql
						$k = $this->wrap_field($k);

						$v = $this->escape_string($v);
						// dont update blank fields in case of bulk edit
						if (strstr($id,",") !== false && ($v === "" || $v == "NULL"))
							continue;

						// if blank option is select in bulk edit
						if ($v == "-")
							$v = ($this->get_column($k_raw,"isnull") == true) ? "NULL" : "";

						// update for nvarchar datatypes (support unicode)
						$unicode_char = "";
						if (strpos($this->db_driver, "mssql") !== false || strpos($this->db_driver, "dblib") !== false)
							$unicode_char = "N";

						$v = ($v == "NULL" || $is_numeric[$k_raw] === true) ? $v : "$unicode_char'$v'";

						$update_str[] = "$k=$v";
					}

					// don't run update if no field is changed (in bulk edit)
					if (count($update_str)==0)
						break;

					$update_str = "SET ".implode(",",$update_str);

					// no quote if isnum
					if ($this->get_column($pk_field,"isnum") == true)
						$id_sql = implode(",",explode(",", $id));
					else
						$id_sql = "'".implode("','",explode(",", $id))."'";

					$pk_field_sql = $this->wrap_field($pk_field);

					$sql = "UPDATE {$this->table} $update_str WHERE $pk_field_sql IN ($id_sql)";

					// enable SQL query debugging flag
					if (isset($this->debug_sql) && $this->debug_sql) phpgrid_error($sql);

					$ret = $this->execute_query($sql);

					### P ###
					// custom on after update event execution
					if (!empty($this->events["on_after_update"]))
					{
						$func = $this->events["on_after_update"][0];
						$obj = $this->events["on_after_update"][1];
						$continue = $this->events["on_after_update"][2];

						if ($obj)
							call_user_func(array($obj,$func),array($pk_field => $id, "params" => &$data));
						else
							call_user_func($func,array($pk_field => $id, "params" => &$data));

						if (!$continue)
							break;
					}

					// return JSON response for update (passing id that was updated)
					if ($ret)
						$res = array("id" => $id, "success" => true);
					else
						$res = array("id" => 0, "success" => false);

					echo json_encode($res);

				break;

				case "del":
					// row to delete is passed as id
					$id = $data["id"];

					// check for crafted ajax call
					if (!$this->actions["delete"])
						phpgrid_error("Operation not allowed");

					### P ###
					// custom on delete event execution
					if (!empty($this->events["on_delete"]))
					{
						$func = $this->events["on_delete"][0];
						$obj = $this->events["on_delete"][1];
						$continue = $this->events["on_delete"][2];
						if ($obj)
							call_user_func(array($obj,$func),array($pk_field => $id));
						else
							call_user_func($func,array($pk_field => $id));

						if (!$continue)
							break;
					}

					$pk_field_sql = $this->wrap_field($pk_field);
					$id_sql = "'".implode("','",explode(",",$id))."'";

					$sql = "DELETE FROM {$this->table} WHERE $pk_field_sql IN ($id_sql)";

					// enable SQL query debugging flag
					if (isset($this->debug_sql) && $this->debug_sql) phpgrid_error($sql);

					$this->execute_query($sql);

					### P ###
					// custom on after delete event execution
					if (!empty($this->events["on_after_delete"]))
					{
						$func = $this->events["on_after_delete"][0];
						$obj = $this->events["on_after_delete"][1];
						$continue = $this->events["on_after_delete"][2];
						if ($obj)
							call_user_func(array($obj,$func),array($pk_field => $id));
						else
							call_user_func($func,array($pk_field => $id));

						if (!$continue)
							break;
					}

				break;
			}

			die;
		}

		// apply search conditions (where clause)
		$wh = "";

		if (!isset($_REQUEST['_search']))
			$_REQUEST['_search'] = "";

		$searchOn = $this->strip($_REQUEST['_search']);

		// always search when persist is on
		if ($this->options["persistsearch"] == true)
			$searchOn = true;

		if($searchOn=='true')
		{
			$fld = $this->escape_string($_REQUEST['searchField']);

			$cols_index = array();
			foreach($this->options["colModel"] as $col_idx)
				$cols_index[] = $col_idx["index"];

			// quick search bar
			if (!$fld)
			{
				// if persisting search
				if (!empty($this->options["persistsearch"]))
				{
					$searchstr = $_SESSION["jqgrid_{$this->id}_searchstr"];						
				}
				else
				{
					// used to search in autofilter 'content "test" content'
					// $searchstr = $this->strip($_REQUEST['filters']);
					$searchstr = ($_REQUEST['filters']);
				}
				// error_log("FILTER:".$searchstr);

				$wh = $this->construct_where($searchstr);
			}
			// search popup form, simple one
			else
			{
				if(in_array($fld,$cols_index))
				{
					$fldata = $this->escape_string($_REQUEST['searchString']);
					$foper = $this->strip($_REQUEST['searchOper']);

					# fix for conflicting table name fields (used alias from page, in property dbname)
					foreach($this->options["colModel"] as $link_c)
					{
						// only used exact date match, when operator is not 'cn' (contains) - default is cn
						if ($fld == $link_c["name"] && !empty($link_c["formatoptions"]) && in_array($foper, array("ne","eq","gt","ge","lt","le")))
						{
							// fix for d/m/Y or d/m/y date format. strtotime expects m/d/Y
							if (stristr($link_c["formatoptions"]["newformat"],"d/m/Y"))
							{
								$fldata = preg_replace('/(\d+)\/(\d+)\/(\d+)/i','$2/$1/$3',$fldata);
							}
							// fix for d-m-y (2 digit year) for strtotime
							else if (strstr($link_c["formatoptions"]["newformat"],"d-m-y"))
							{
								$fldata = preg_replace('/(\d+)-(\d+)-(\d+)/i','$3-$2-$1',$fldata);
							}
							else if (strstr($link_c["formatoptions"]["newformat"],"d/M/Y") || strstr($link_c["formatoptions"]["newformat"],"d-M-Y"))
							{
								$fldata = preg_replace('/\/\-/i',' ',$fldata);
							}

							if ($link_c["formatter"] == "date")
								$fldata = $this->custom_date_format("Y-m-d",$fldata);
							else if ($link_c["formatter"] == "datetime")
								$fldata = $this->custom_date_format("Y-m-d H:i:s",$fldata);
						}

						if ($fld == $link_c["name"] && !empty($link_c["dbname"]))
						{
							$fld = $link_c["dbname"];
						}
					}

					$fld = $this->wrap_field($fld);

					// make case insensitive for oracle
					if ($foper == "cn")
						if (strpos($this->db_driver,"oci8") !== false || strpos($this->db_driver,"db2") !== false)
							$fld = "LOWER($fld)";

					// costruct where
					$wh .= " AND ".$fld;
					switch ($foper) {
						case "eq":
							if(is_numeric($fldata)) {
								$wh .= " = ".$fldata;
							} else {
								$wh .= " = '".$fldata."'";
							}
							break;
						case "ne":
							if(is_numeric($fldata)) {
								$wh .= " <> ".$fldata;
							} else {
								$wh .= " <> '".$fldata."'";
							}
							break;
						case "lt":
							if(is_numeric($fldata)) {
								$wh .= " < ".$fldata;
							} else {
								$wh .= " < '".$fldata."'";
							}
							break;
						case "le":
							if(is_numeric($fldata)) {
								$wh .= " <= ".$fldata;
							} else {
								$wh .= " <= '".$fldata."'";
							}
							break;
						case "gt":
							if(is_numeric($fldata)) {
								$wh .= " > ".$fldata;
							} else {
								$wh .= " > '".$fldata."'";
							}
							break;
						case "ge":
							if(is_numeric($fldata)) {
								$wh .= " >= ".$fldata;
							} else {
								$wh .= " >= '".$fldata."'";
							}
							break;
						case "ew":
							$wh .= " LIKE '%".$fldata."'";
							break;
						case "en":
							$wh .= " NOT LIKE '%".$fldata."'";
							break;
						case "cn":

							// make case insensitive for oracle
							if (strpos($this->db_driver,"oci8") !== false || strpos($this->db_driver,"db2") !== false)
								$wh .= " LIKE LOWER('%".$fldata."%')";
							else
								$wh .= " LIKE '%".$fldata."%'";

							break;
						case "nc":
							$wh .= " NOT LIKE '%".$fldata."%'";
							break;
						case "in":
							$wh .= " IN (".$fldata.")";
							break;
						case "ni":
							$wh .= " NOT IN (".$fldata.")";
							break;
						case "nu":
							$wh .= " IS NULL";
							break;
						case "nn":
							$wh .= " IS NOT NULL";
							break;
						case "bw":
						default:
							$fldata .= "%";
							$wh .= " LIKE '".$fldata."'";
							break;
					}
				}
			}
			// setting to persist where clause in export option
			$_SESSION["jqgrid_{$grid_id}_filter"] = $wh;
			$_SESSION["jqgrid_{$grid_id}_filter_request"] = $_REQUEST["filters"];
		}
		elseif($searchOn=='false')
		{
			unset($_SESSION["jqgrid_{$this->id}_searchstr"]);
			unset($_SESSION["jqgrid_{$this->id}_filter"]);
			unset($_SESSION["jqgrid_{$this->id}_filter_request"]);
		}

		### P ###
		if ($this->options["treeGrid"]==true)
		{

			foreach ($this->options["colModel"] as &$c)
			{
				if ( in_array($c["name"],array($this->options["treeConfig"]["id"],$this->options["treeConfig"]["parent"])) )
					$c["hidden"]=true;
			}

			// hide actions column
			$this->actions["rowactions"] = false;

			$this->options["ExpandColClick"]=true;
			$this->options["ExpandColumn"]=$this->options["treeConfig"]["column"];
			$this->options["treedatatype"]="json";
			$this->options["treeGridModel"]="adjacency";
			// $this->options["loadonce"]=true;
			$this->options["treeReader"]=array(
									"parent_id_field"=>$this->options["treeConfig"]["parent"],
									"level_field"=>"level",
									"leaf_field"=>"isLeaf",
									"expanded_field"=>"expanded",
									"loaded"=>"loaded",
									"icon_field"=>"icon"
									);

			if ($this->options["treeConfig"]["loaded"] === false)
			{
				$this->options["treeConfig"]["expanded"] = false;

				// Get parameters from the grid
				$node = (integer)$_REQUEST["nodeid"];
				$n_lvl = (integer)$_REQUEST["n_level"];

				$parent_col = $this->options["treeConfig"]["parent"];
				$parent_col_dbname = $this->get_column($this->options["treeConfig"]["parent"],"dbname");
				if (!empty($parent_col_dbname))
					$parent_col = $parent_col_dbname;

				// check to see which node to load
				if($node > 0)
				{
					$where_tree = $parent_col.'='.$node; // parents
					$n_lvl = $n_lvl+1; // we should ouput next level
				}
				else
				{
					// to do NVL for db2, NVL(field,0)=0
					$where_tree = $parent_col.' IS NULL OR '.$parent_col.' = 0'; // roots
				}

				// bypass tree when searching in treeview
				if ($_REQUEST["_search"] != "true")
					$wh .= " AND ($where_tree)";
			}

		}

		if ($this->actions["rowactions"] !==  false)
		{
			// CRUD operation column
			$f = false;
			$defined = false;
			foreach($this->options["colModel"] as &$c)
			{
				if ($c["name"] == "act")
				{
					$defined = &$c;
				}

				if (!empty($c["width"]))
				{
					$f = true;
				}
			}

			// // // increase action column width for safari
			// // $w_inc = 0;
			// // $user_agent = $_SERVER['HTTP_USER_AGENT'];
			// // if (stripos( $user_agent, 'msie') !== false || stripos( $user_agent, 'Trident/7.0') !== false) // for IE ver < 11 || 11
			// 	// $w_inc = 5;
			// // else if (stripos( $user_agent, 'chrome') !== false) // contains both safari & chrome
			// 	// $w_inc = 0;
			// // else if (stripos( $user_agent, 'Safari') !== false) // only 4 safari as chrome covered above
			// 	// $w_inc = 35;
			//
			// // // icon col fix, text links as old behavior (fixed:true, mean exact px)
			// // if ($this->internal["actionicon"] === true)
			// 	// $w = ($this->actions["clone"] === true)? 96 : 60 + $w_inc;
			// // else
			// 	// $w = ($this->actions["clone"] === true)? 120 : 100 + $w_inc;
			//
			// if (!is_array($this->actions["custom"]) && !empty($this->actions["custom"]))
			// 		$this->actions["custom"] = array($this->actions["custom"]);
			//
			// $btn_count = 0;
			// if ($this->actions["edit"] === true)
			// 	$btn_count++;
			// if ($this->actions["delete"] === true)
			// 	$btn_count++;
			// if ($this->actions["clone"] === true)
			// 	$btn_count++;
			//
			// // 2 places minimum
			// if ($btn_count==1)
			// 	$btn_count++;
			//
			// // default FF
			// $w = (count($this->actions["custom"]) + $btn_count) * 24 + 11; // for chrome,ff, 59-2, 83-3, 107-4
			//
			// if (stripos( $user_agent, 'msie') !== false || stripos( $user_agent, 'Trident/7.0') !== false) // for IE ver < 11 || 11
			// 	$w = (count($this->actions["custom"]) + $btn_count) * 27.5 + 8; // for ie 63-2, 90-3, 118-4, 228-8
			// else if (stripos( $user_agent, 'chrome') !== false) // contains both safari & chrome
			// 	$w = (count($this->actions["custom"]) + $btn_count) * 24 + 11; // for chrome,ff, 59-2, 83-3, 107-4
			// else if (stripos( $user_agent, 'Safari') !== false) // only 4 safari as chrome covered above
			// 	$w = (count($this->actions["custom"]) + $btn_count) * 29 + 15; // for safari 73-2, 102-3, 130-4

			// increase action column width for safari
			$w_inc = 15;
			$user_agent = $_SERVER['HTTP_USER_AGENT'];
			if (stripos( $user_agent, 'msie') !== false || stripos( $user_agent, 'Trident/7.0') !== false) // for IE ver < 11 || 11
				$w_inc = 15;
			else if (stripos( $user_agent, 'opr') !== false) // only 4 opera
				$w_inc = 15;
			else if (stripos( $user_agent, 'chrome') !== false) // contains both safari & chrome
				$w_inc = 15;
			else if (stripos( $user_agent, 'Safari') !== false) // only 4 safari as chrome covered above
				$w_inc = 15;

			// icon col fix, text links as old behavior (fixed:true, mean exact px)
			if ($this->internal["actionicon"] === true)
				$w = ($this->actions["clone"] === true)? 102 : 60 + $w_inc;
			else
				$w = ($this->actions["clone"] === true)? 120 : 100 + $w_inc;

			// width adjustment for row actions column
			$action_column = array(
				"name" => "act", "fixed" => true, "align" => "center",
				"index" => "act", "width" => "$w", "sortable" => false,
				"search" => false, "viewable" => false, "visible" => array("sm", "md", "lg", "xl")
			);

			// if ($this->internal["actionicon"] === true) // icons as action links
			// {
				// if ($this->actions["edit"] !== false)
					// $act_links[] = "<a class=\"ui-custom-icon ui-icon ui-icon-pencil\" title=\"Edit this row\" href=\"javascript:void(0);\" onclick=\"jQuery(this).dblclick();\"></a>";

				// ### P ###
				// if ($this->actions["clone"] === true)
					// $act_links[] = "<a class=\"ui-custom-icon ui-icon ui-icon-copy\" title=\"Clone this row\" href=\"javascript:void(0);\" onclick=\"fx_clone_row(\'$grid_id\',\''+cl+'\'); \"></a>";
				// ### P-END ###

				// if ($this->actions["delete"] !== false)
					// $act_links[] = "<a class=\"ui-custom-icon ui-icon ui-icon-trash\" title=\"Delete this row\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#$grid_id\').resetSelection(); jQuery(\'#$grid_id\').setSelection(\''+cl+'\'); jQuery(\'#del_$grid_id\').click(); \"></a>";

				// $act_links = implode("", $act_links);


				// // il_save, ilcancel, iledit are clicked for inlineNav button reset
				// $save = "<a class=\"ui-custom-icon ui-icon ui-icon-disk\" title=\"Save this row\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#{$grid_id}_ilsave\').click(); if (jQuery(\'#$grid_id\').saveRow(\''+cl+'\',null,null,{$extraparam}) || jQuery(\'.editable\').length==0) { jQuery(this).parent().hide(); jQuery(this).parent().prev().show(); ". addslashes($reload_after_edit)." }\"></a>';
				// $restore = '<a class=\"ui-custom-icon ui-icon ui-icon-cancel\" title=\"'+restore_text+'\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#{$grid_id}_ilcancel\').click(); jQuery(\'#$grid_id\').restoreRow(\''+cl+'\'); jQuery(this).parent().hide(); jQuery(this).parent().prev().show();\"></a>';

				// $action_column["default"] = "$act_links";
			// }

			if (!$defined)
			{
				$act_title = "Actions";

				if (isset($this->internal["column_titles"]) && isset($this->internal["column_titles"]["act"]))
					$act_title = $this->internal["column_titles"]["act"];

				$this->options["colNames"][] = $act_title;
				$this->options["colModel"][] = $action_column;
			}
			else
				$defined = array_merge($action_column,$defined);
		}

		// small screen xs - show columns details "..."
		if ($this->options["view_options"]["rowButton"] == true)
		{
			$more = array();
			$more["name"] = "xs_view_dots";
			$more["index"] = "xs_view_dots";
			$more["default"] = "<span title=\"\" style=\"cursor:pointer;font-weight:bold;\" onclick=\"fx_show_view_dialog(this)\">&nbsp;...&nbsp;</span>";
			$more["fixed"] = true; // exact pixel width
			$more["width"] = 25;
			$more["hidden"] = true;
			$more["viewable"] = false;
			$more["align"] = "center";
			$more["editable"] = false;
			$more["search"] = false;
			$more["export"] = false;
			$more["show"] = array("edit"=>false, "view"=>false, "add"=>false);
			$more["hidedlg"] = true;
			$this->options["colNames"][] = "";
			$this->options["colModel"][] = $more;
		}

		// generate main json	
		if (isset($_GET['jqgrid_page']))
		{
			$page = intval($_GET['jqgrid_page']); // get the requested page
			$limit = intval($_GET['rows']); // get how many rows we want to have into the grid

			// if not passed from client, set default sortname,sortorder as set in grid options
			if (!empty($_GET['sidx']))
				$sidx = trim($_GET['sidx']); // get index row - i.e. user click to sort
			else
				$idx = $this->options["sortname"];

			if (!empty($_GET['sord']))
				$sord = (strtolower($_GET['sord']) == "asc") ? "ASC" : "DESC"; // get the direction
			else
				$sord = $this->options["sortorder"];

			// grouping expect ordered data, allow postback multi sort (with checking)
			if (is_array($this->options["groupingView"]["groupField"]) || $this->options["multiSort"] == true)
			{
				$posted_sort = explode(",",$sidx);
				$sort_found = true;

				// sort grouped column if clicked
				$sort_dup = array();
				$cols_sort = $posted_sort;
				$last = trim(array_pop($cols_sort));
				foreach ($posted_sort as $sort_item)
				{
					$sort_item = trim($sort_item);
					$sort_x = explode(" ",$sort_item);
					$sort_dup[$sort_x[0]] = $sort_x[1];
				}
				// if last (sorted after group) is same as group field, override sort asc/desc
				$sort_dup[$last] = $sord;

				// fix to skip ` escape
				$sort_dup["1"] = "";

				$sidx_arr = array();
				foreach ($sort_dup as $k=>$v)
					$sidx_arr[] = $k . (empty($v) ? "" : " " . $v);
				$sidx = implode(",",$sidx_arr);

				// check if sort column exist (check tampered reuquest)
				foreach($posted_sort as $f)
				{
					// skip 1 in field desc, 1 asc
					if ($f == "1") continue;

					$f = strtolower($f);
					$f = str_replace("asc","",$f);
					$f = str_replace("desc","",$f);
					$f = trim($f);

					$field_found = false;
					foreach ($this->options["colModel"] as &$c)
						if (strtolower($c["name"]) == $f)
						{
							$field_found = true;
							break;
						}

					if ($field_found == false)
					{
						$sort_found = false;
						break;
					}
				}
			}
			else
			{
				// limit sort from $_GET only to available columns
				$sort_found = false;
				foreach ($this->options["colModel"] as &$c)
					if (strtolower($c["name"]) == strtolower($sidx))
					{
						$sort_found = true;
						break;
					}
			}

			// if sort field not from available cols
			if (!$sort_found) unset($sidx);

			// set sorting to default options, if single column not found in GET
			if(!$sidx) $sidx = $this->options["sortname"];

			$order_by_fx = false;

			$order_by = $sidx . " " . $sord;
			$order_by = explode(",",$order_by);	

			foreach($order_by as $k=>&$order_item)
			{
				$order_item = trim($order_item);
				preg_match("/(.*)[ ]+(asc|desc)/i", $order_item, $match);
				
				if (!empty($match))
				{
					$fld = trim($match[1]);
					$order = trim($match[2]);
				}
				// no space e.g. total in 'id desc, total, tax' ... set default as ASC
				else
				{
					$fld = $order_item;
					$order = "ASC";
				}

				// if sort field exist, remove 1 asc from end
				if (count($order_by) > 1)
				{
					if ($fld == "1")
					{
						unset($order_by[$k]);
						continue;
					}
				}

				$fc = $this->get_column($fld);

				// if set, use dbname for sorting
				if (!empty($fc["dbname"]))
					$fld = $fc["dbname"];

				// override if sortname is set
				if (!empty($fc["sortname"]))
					$fld = $fc["sortname"];

				if (strstr($fld,"(") !== false)
					$order_by_fx = true;

				// dont append order ASC/DESC if field is expression function
				if (strstr($fld,"(") !== false)
					continue;

				$order_item = $fld . " " . $order;
			}

			$order_by = implode(",",$order_by);	

			preg_match("/(.*)[ ](asc|desc)/i", $order_by, $match);
			$sidx = $match[1];
			$sord = $match[2];

			if(!$sidx) $sidx = "1";
			if(!$limit) $limit = "20";

			// use tilda sign for sort by + except multiple sort e.g. fix `gender asc, name` desc & sql func e.g. concat(a,b)
			if (!($sidx == "1" || strstr($sidx,",") !== false || $order_by_fx == true))
				$sidx = $this->wrap_field($sidx);

			// persist for export data
			if (isset($_GET["export"]))
			{
				if (!empty($_SESSION["jqgrid_{$grid_id}_sort_by"]))
					$sidx = $_SESSION["jqgrid_{$grid_id}_sort_by"];

				$sord = $_SESSION["jqgrid_{$grid_id}_sort_order"];
				$limit = $_SESSION["jqgrid_{$grid_id}_rows"];
				$having = $_SESSION["jqgrid_{$grid_id}_having"];
				$page = $_SESSION["jqgrid_{$grid_id}_page"];
			}
			else
			{
				$_SESSION["jqgrid_{$grid_id}_sort_by"] = $sidx;
				$_SESSION["jqgrid_{$grid_id}_sort_order"] = $sord;
				$_SESSION["jqgrid_{$grid_id}_rows"] = $limit;
				$_SESSION["jqgrid_{$grid_id}_page"] = $page;

				$having_tmp = "";
				if (!empty($this->having_clause))
					$having_tmp = " HAVING ".implode(" AND ",$this->having_clause);

				$_SESSION["jqgrid_{$grid_id}_having"] = $having_tmp;
			}

			### P ###
			// if export option is requested
			if (isset($_GET["export"]))
			{
				set_time_limit(0);
				$arr = array();

				// if no table defined, on_select event handler
				if (!isset($this->table) && !isset($this->select_command))
				{
					// custom export function if needed
					if (!empty($this->events["on_export"]))
					{
						$func = $this->events["on_export"][0];
						$obj = $this->events["on_export"][1];
						$continue = $this->events["on_export"][2];

						if ($obj)
							call_user_func(array($obj,$func),array("data" => &$arr, "grid" => &$this));
						else
							call_user_func($func,array("data" => &$arr, "grid" => &$this));

						if (!$continue)
							exit(0);
					}
				}
				// export data array (if grid loaded from array)
				else if (is_array($this->table))
				{
					$t = $this->table;

					// sorting of array column as defined
					$cols_tab = array();
					foreach ($this->options["colModel"] as &$c)
						$cols_tab[] = $c["name"];

					$arr_sorted = array();
					foreach($t as $row)
					{
						$item_sorted = array();
						foreach($cols_tab as $v)
							$item_sorted[$v] = $row[$v];

						$arr_sorted[] = $item_sorted;
					}
					$t = $arr_sorted;

					foreach($t[0] as $k => $v)
						$temp[$k] = ucwords($k);

					$arr[] = $temp;
					foreach ($t as $key => $value)
						$arr[] = $value;

					// custom export function if needed
					if (!empty($this->events["on_export"]))
					{
						$func = $this->events["on_export"][0];
						$obj = $this->events["on_export"][1];
						$continue = $this->events["on_export"][2];

						if ($obj)
							call_user_func(array($obj,$func),array("data" => $arr, "grid" => &$this));
						else
							call_user_func($func,array("data" => $arr, "grid" => &$this));

						if (!$continue)
							exit(0);
					}
				}
				// if grid loaded from db
				else
				{
					// by default export all
					$export_where = "";
					$export_having = "";

					// if range is filtered OR filters are passed in url (export selected feature)
					if ($this->options["export"]["range"] == "filtered" || !empty($_GET["filters"]))
					{
						$export_where = $_SESSION["jqgrid_{$grid_id}_filter"];
						$export_having = $_SESSION["jqgrid_{$grid_id}_having"];
					}

					$limit_sql= "";
					if ($this->options["export"]["paged"] == "1")
					{
						$offset = $limit*$page - $limit; // do not put $limit*($page - 1)
						if ($offset<0) $offset = 0;
						$limit_sql = "LIMIT $limit OFFSET $offset";
					}

					// preserve subqueries
					$matches_subsql = $this->remove_subsql();

					// if sql is set on on_select event
					if (!empty($this->internal["sql"]))
					{
						$SQL = $this->internal["sql"]." $limit_sql";
					}
					else if (($p = stripos($this->select_command,"GROUP BY")) !== false)
					{
						$start = substr($this->select_command,0,$p);
						$end = substr($this->select_command,$p);

						$SQL = $start.$export_where.$end.$export_having." ORDER BY $sidx $sord $limit_sql";
					}
					else
						$SQL = $this->select_command.$export_where." ORDER BY $sidx $sord $limit_sql";

					// re-adjust subqueries in sql
					$SQL = $this->add_subsql($SQL,$matches_subsql);

					// custom export function if needed
					if (!empty($this->events["on_export"]))
					{
						$func = $this->events["on_export"][0];
						$obj = $this->events["on_export"][1];
						$continue = $this->events["on_export"][2];

						if ($obj)
							call_user_func(array($obj,$func),array("sql" => $SQL, "grid" => &$this));
						else
							call_user_func($func,array("sql" => $SQL, "grid" => &$this));

						if (!$continue)
							exit(0);
					}

					$SQL = $this->prepare_sql($SQL,$this->db_driver);
					$result = $this->execute_query($SQL);
					foreach ($this->options["colModel"] as $c_tmp)
					{
						// dont export action column
						if ($c_tmp["name"] == "act") continue;

						$header[$c_tmp["name"]] = $c_tmp["title"];
					}

					$arr[] = $header;

					if ($this->con)
					{
						$rows = $result->GetRows();
						foreach($rows as $row)
						{
							$export_data = array();

							foreach($header as $k=>$v)
								$export_data[$k] = $row[$k];

							$arr[] = $export_data;
						}
					}
					else
					{
						while($row = mysql_fetch_array($result,MYSQL_ASSOC))
						{
							$export_data = array();

							foreach($header as $k=>$v)
								$export_data[$k] = $row[$k];

							$arr[] = $export_data;
						}
					}
				}

				$col_widths = array();
				// export only selected columns
				$cols_not_to_export = array();
				$cols_to_export = array();
				if ($this->options["colModel"])
				{
					foreach ($this->options["colModel"] as $c_tmp)
					{
						// dont export action column
						if ($c_tmp["name"] == "act") continue;

						// if as new php does not have cookies in request order php.ini
						$_REQUEST = array_merge($_REQUEST,$_COOKIE);

						// column chooser integration with export - primary used cookie but to allow $_GET, it is changed to $_REQUEST
						if (isset($_REQUEST["jqgrid_colchooser_{$grid_id}"]) && !empty($_REQUEST["jqgrid_colchooser_hide_{$grid_id}"]))
						{
							$colchooser = explode(",",$_REQUEST["jqgrid_colchooser_hide_{$grid_id}"]);
							if (in_array($c_tmp["name"],$colchooser))
								$c_tmp["export"] = false;
						}

						if (isset($c_tmp["export"]) && $c_tmp["export"] === false)
							$cols_not_to_export[] = $c_tmp["name"];
						else
						{
							$cols_to_export[] = $c_tmp["name"];
							$col_widths[$c_tmp["name"]] = (!empty($c_tmp["width"]) ? $c_tmp["width"] : 'auto');
						}
					}
				}

				// custom on_data_display event execution (for export)
				if (!empty($this->events["on_data_display"]))
				{
					$func = $this->events["on_data_display"][0];
					$obj = $this->events["on_data_display"][1];

					// remove header
					$h = array_shift($arr);

					if ($obj)
						call_user_func(array($obj,$func),array("params" => &$arr));
					else
						call_user_func($func,array("params" => &$arr));

					// add header
					array_unshift($arr,$h);
				}

				// fix for d/m/Y date format in export. strtotime expects m/d/Y
				foreach($this->options["colModel"] as $cm)
				{
					// dont export action column
					if ($cm["name"] == "act") continue;

					foreach ($arr as &$rec)
					{
						// skip header from date format conversion
						if ($rec === $arr[0])
							continue;

						// fix for phpexcel export, was duplicating
						$rec[$cm["name"]] = trim($rec[$cm["name"]]);

						// dropdown mappings
						if ($cm["edittype"] == "select" && $cm["formatter"] == "select" && isset($cm["editoptions"]["value"]))
						{
							$rec[$cm["name"]] = trim($rec[$cm["name"]]);
							if (!empty($rec[$cm["name"]]))
							{
								$del = (isset($cm["editoptions"]["delimiter"]) ? $cm["editoptions"]["delimiter"] : ":");
								$sep = (isset($cm["editoptions"]["separator"]) ? $cm["editoptions"]["separator"] : ";");

								$sets = explode($sep,$cm["editoptions"]["value"]);
								$pair = array();
								foreach($sets as $s)
								{
									list($dd_key,$dd_val) = explode($del,$s);
									$pair[$dd_key] = $dd_val;
								}

								// if comma sep values in dropdown selection (selecr2 tags)
								$multi_var = explode(",",$rec[$cm["name"]]);

								if (count($multi_var)>1)
								{
									$tmp = array();
									foreach ($multi_var as $key => $value)
										$tmp[] = $pair[trim($value)];

									$rec[$cm["name"]] = implode(", ",$tmp);
								}
								else
									$rec[$cm["name"]] = $pair[$rec[$cm["name"]]];
							}
						}

						// show masked data in password
						if (isset($cm["formatter"]) && $cm["formatter"] == "password")
							$rec[$cm["name"]] = "*****";

						if (!empty($rec[$cm["name"]]) && ($cm["formatter"] == "date" || $cm["formatter"] == "datetime"))
						{
							$dt = $rec[$cm["name"]];

							$js_dt_fmt = $cm["formatoptions"]["newformat"];

							// if data has timestamp, make it date
							$src_fmt = $c["formatoptions"]["srcformat"];
							if ($src_fmt == "U")
								$dt = date("Y-m-d H:i:s",$dt);

							// replace non-dates to empty string
							if (strstr($dt,"1970-01-01") == false && strstr($dt,"0000-00-00") == false)
								$rec[$cm["name"]] = $this->custom_date_format($js_dt_fmt,$dt);
							else
								$rec[$cm["name"]] = "";
						}

						### P ###
						// Replace condition data in pdf export
						$col_name = $cm["name"];
						if (isset($cm["default"]) && !isset($rec[$col_name]))
							$rec[$col_name] = $cm["default"];

						// link data in grid to any given url
						if (!empty($cm["default"]))
						{
							// replace any param in link e.g. http://domain.com?id={id} given that, there is a $col["name"] = "id" exist
							$rec[$col_name] = $this->replace_row_data($rec,$cm["default"]);
						}

						// check conditional data
						if (!empty($cm["condition"][0]))
						{
							$r = true;

							// replace {} placeholders from conditional data
							$cond0 = $this->replace_row_data($rec,$cm["condition"][0]);
							$cond1 = $this->replace_row_data($rec,$cm["condition"][1]);
							$cond2 = $this->replace_row_data($rec,$cm["condition"][2]);

							// fix as $row was used in condition and $rec is var here
							$cond = str_replace("row","rec",$cond0);
							eval("\$r = ".$cond.";");

							$rec[$col_name] = ( $r ? $cond1 : $cond2);
							$rec[$col_name] = strip_tags($rec[$col_name]);
						}

						// check data filter (alternate of grid on_data_display, but for current column)
						if (!empty($cm["on_data_display"]))
						{
							$func = $cm["on_data_display"][0];
							$obj = $cm["on_data_display"][1];
							$param = $cm["on_data_display"][2];

							if (!empty($param))
								$params = array($rec,$param);
							else
								$params = array($rec);

							if ($obj)
								$rec[$col_name] = call_user_func_array(array($obj,$func),$params);
							else
								$rec[$col_name] = call_user_func_array($func,$params);
						}
					}
				}

				// remove db columns as well as virtual columns
				if (!empty($cols_to_export))
				{
					$export_arr = array();
					foreach($arr as $arr_item)
					{
						foreach($arr_item as $k=>$i)
						{
							if (!in_array($k, $cols_to_export))
							{
								unset($arr_item[$k]);
							}
						}
						$export_arr[] = $arr_item;
					}
					$arr = $export_arr;
				}

				// make export filename with date
				if (!isset($this->options["export"]["filename"]))
				{
					if (!empty($this->options["caption"]))
						$cap = $this->get_clean($this->options["caption"]);
					else
						$cap = $grid_id;

					$this->options["export"]["filename"] = $cap."_".date("Ymd");
				}

				if (!isset($this->options["export"]["sheetname"]))
					$this->options["export"]["sheetname"] = substr($this->options["caption"],0,30);

				if (!empty($this->options["export"]["sheetname"]))
				{
					// php excel invalid char list
					$invalidCharacters = array('*', ':', '/', '\\', '?', '[', ']');
					$this->options["export"]["sheetname"] = str_replace($invalidCharacters, '', $this->options["export"]["sheetname"]);
				}
									
				// fix for ie - http://support.microsoft.com/kb/316431
				if(preg_match('/(?i)msie /',$_SERVER['HTTP_USER_AGENT']))
					header('Cache-control: cache,must-revalidate');

				// custom export with data function if needed
				if (!empty($this->events["on_export_data"]))
				{
					$func = $this->events["on_export_data"][0];
					$obj = $this->events["on_export_data"][1];
					$continue = $this->events["on_export_data"][2];

					if ($obj)
						call_user_func(array($obj,$func),array("data" => $arr, "sql" => $SQL, "grid" => &$this));
					else
						call_user_func($func,array("data" => $arr, "sql" => $SQL, "grid" => &$this));

					if (!$continue)
						exit(0);
				}

				if ($this->options["export"]["format"] == "pdf")
				{
					// apply nl2br on each cell data
					foreach($arr as $k => &$v)
						$v = array_map("nl2br",$v);

					$orientation = $this->options["export"]["orientation"];
					if ($orientation == "landscape")
						$orientation = "L";
					else
						$orientation = "P";

					$paper = strtoupper($this->options["export"]["paper"]);

					// Using opensource TCPdf lib
					// for more options visit http://www.tcpdf.org/examples.php

					require_once(dirname(__FILE__).'/tcpdf/class.TCPDF.EasyTable.php');

					// create new PDF document
					$pdf = new TCPDF_EasyTable($orientation, PDF_UNIT, $paper, true, 'UTF-8', false);

					// set document information
					$pdf->SetCreator("www.gridphp.com");
					$pdf->SetAuthor('www.gridphp.com');
					$pdf->SetTitle('www.gridphp.com');
					$pdf->SetSubject($this->options["caption"]);
					$pdf->SetKeywords('www.gridphp.com');

					// remove default header/footer
					$pdf->setPrintHeader(true);
					$pdf->setPrintFooter(true);

					// set default monospaced font
					$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
					$pdf->setFontSubsetting(false);

					//set margins
					$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

					//set auto page breaks
					$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

					//set image scale factor
					$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

					// set some language dependent data:

					// lines for rtl pdf generation
					if (isset($this->options["direction"]) && $this->options["direction"] == "rtl")
					{
						$lg = Array();
						$lg['a_meta_charset'] = 'UTF-8';
						$lg['a_meta_dir'] = 'rtl';
						$lg['a_meta_language'] = 'fa';
						$lg['w_page'] = 'page';
						$pdf->setLanguageArray($lg);
					}

					// To set your custom font
					// $fontname = $pdf->addTTFfont('../../lib/inc/tcpdf/fonts/DejaVuSans.ttf', 'TrueTypeUnicode', '', 32);
					// set font http://www.tcexam.org/doc/code/classTCPDF.html#afd56e360c43553830d543323e81bc045

					// for special charset language, uncoment this line (for rtl, use html renderer)
					// $pdf->SetFont('cid0jp', '', 10);
					// $pdf->SetFont('dejavu sans', '', 10);
					// $pdf->SetFont('dejavu serif', '', 10);
					// $pdf->SetFont('free serif', '', 10);

					// add a page
					$pdf->AddPage();

					// output the HTML content
					if (isset($this->options["export"]["render_type"]) && $this->options["export"]["render_type"] == "html")
					{
						$html = "";
						// if customized pdf render is defined, use that
						if (!empty($this->events["on_render_pdf"]))
						{
							$func = $this->events["on_render_pdf"][0];
							$obj = $this->events["on_render_pdf"][1];
							if ($obj)
								$html = call_user_method($func,$obj,array("grid" => &$this, "data" => $arr, "pdf" => $pdf));
							else
								$html = call_user_func($func,array("grid" => &$this, "data" => $arr, "pdf" => $pdf));
						}
						else
						{
							$html .= "<h1>".$this->options["export"]["heading"]."</h1>";
							$html .= '<table border="0" cellpadding="4" cellspacing="2">';

							$i = 0;
							foreach($arr as $v)
							{
								$shade = ($i++ % 2) ? 'bgcolor="#efefef"' : '';
								$html .= "<tr>";
								foreach($v as $d)
								{
									// bold header
									if  ($i == 1)
										$html .= "<td bgcolor=\"lightgrey\"><strong>$d</strong></td>";
									else
										$html .= "<td $shade>$d</td>";
								}
								$html .= "</tr>";
							}

							$html .= "</table>";
						}

						$pdf->writeHTML($html, true, false, true, false, '');
						$pdf->Output($this->options["export"]["filename"].".pdf", 'D');
						die;
					}

				    $pdf->SetY(PDF_MARGIN_TOP, true );

					$pdf->SetFont('helvetica', '', 15);
					$pdf->SetHeaderCellsFontStyle('b');
					$pdf->Cell( 0, 10, $this->options["export"]["heading"], 0, 1 );

					$pdf->SetFont('helvetica', '', 12);
				    $pdf->SetHeaderCellsFontStyle(''); // 'b'
			        $pdf->SetHeaderCellsFillColor(77,77,77);
			        $pdf->SetHeaderCellsFontColor(255,255,255);
			        $pdf->SetFillColor(240,240,240); // for cells background
			        $pdf->SetCellFontColor(0,0,0);

			        $pdf->SetCellFillStyle(2);
			        $pdf->SetCellPadding(1);
			        $pdf->SetCellMinimumHeight(null);

					// if customized pdf render is defined, use that
					if (!empty($this->events["on_render_pdf"]))
					{
						$func = $this->events["on_render_pdf"][0];
						$obj = $this->events["on_render_pdf"][1];
						if ($obj)
							call_user_func(array($obj,$func),array("grid" => &$this, "pdf" => &$pdf, "data" => &$arr));
						else
							call_user_func($func,array("grid" => &$this, "pdf" => &$pdf, "data" => &$arr));
					}

					// auto set column widths based on grid column width
					$margins = $pdf->GetMargins();
					$pdf_page_width = $pdf->GetPageWidth() - $margins['left'] - $margins['right'];
			        $total_width = 0;

			        foreach ($col_widths as $key => $value)
			        {
			        	// if col width not defined, distribute equally
			        	if ($value == "auto")
			        	{
			        		$this->options["export"]["colwidth"] = "equal";
			        		break;
			        	} 

			        	$total_width += $value;
			        }

					$new_widths = array();

					// adjust equal width column if not defined
					if ($this->options["export"]["colwidth"] == "equal")
					{
						$cnt = count($col_widths);
						foreach ($col_widths as $key => $value)
						{
							$new_widths[$key] = $pdf_page_width / $cnt;
							$new_widths[] = $pdf_page_width / $cnt;
						}
					}
					else
					{
						foreach ($col_widths as $key => $value)
						{
							if (intval($this->options["export"]["colwidth"]) != 0)
							{
								$new_widths[$key] = $pdf_page_width * floatval($this->options["export"]["colwidth"])/100;
								$new_widths[] = $pdf_page_width * floatval($this->options["export"]["colwidth"])/100;
							}
							else
							{
								$new_widths[$key] = $pdf_page_width * ($value/$total_width);
								$new_widths[] = $pdf_page_width * ($value/$total_width);
							}
						}
					}

			        $pdf->SetCellWidths($new_widths);

					$h = $arr[0];
				    array_shift($arr);
				    $pdf->EasyTable($arr,$h);

				    if (strstr($this->options["export"]["filename"],".pdf") === false)
				    	$this->options["export"]["filename"] .= ".pdf";

					//Close and output PDF document
					$pdf->Output($this->options["export"]["filename"], 'D');
					die;
				}
				else if ($this->options["export"]["format"] == "csv")
				{
					if (strstr($this->options["export"]["filename"],".csv") === false)
						$this->options["export"]["filename"] .= ".csv";

		            header( 'Content-Type: text/csv' );
					header( 'Content-Disposition: attachment;filename='.$this->options["export"]["filename"]);

		            $fp = fopen('php://output', 'w');
		            foreach ($arr as $key => $value)
		            {
						// sanitize for csv/xls injection
						array_walk($value, array($this,"sanitize_csv_data"));
						fputcsv($fp, $value);
		            }
		            die;
				}
				else if ($this->options["export"]["format"] == "html")
				{
		            header( 'Content-Type: text/html' );

					$style = 'style="border:1px solid #ccc; font-family:arial; font-size:13px"';
					$hstyle = 'style="font-family:arial; font-size:35px; margin:10px 0px"';
				
					echo "<h1 $hstyle>".$this->options["export"]["heading"]."</h1>";
					echo "<table cellpadding='4'>";
		            foreach ($arr as $key => $value)
		            {
						if ($key == 0)
							$row_style = 'style="background-color:#d7dce2;font-weight:bold"';
						else if ($key % 2 == 0)
							$row_style = 'style="background-color:#fff;"';
						else
							$row_style = 'style="background-color:#f9f9f9;"';

						echo("<tr $row_style>");
						echo("<td $style>");
						echo(implode("</td><td $style>", $value));
						echo("</td>");
						echo("</tr>");
		            }
					echo "</table>";
		            die;
				}
				else if ($this->options["export"]["format"] == "word")
				{
					if (strstr($this->options["export"]["filename"],".docx") === false)
						$this->options["export"]["filename"] .= ".docx";
					
					if (empty($this->options["export"]["heading"]))
						$this->options["export"]["heading"] = $this->options["caption"];
					
		            header( 'Content-Type: text/html' );
					header( 'Content-Type: text/html' );
					header( 'Content-Disposition: attachment;filename='.$this->options["export"]["filename"]);

					ob_start();
					$style = 'style="border:1px solid #ccc; font-family:arial; font-size:13px"';
					echo "<table cellpadding='4'>";
		            foreach ($arr as $key => $value)
		            {
						if ($key == 0)
							$row_style = 'style="background-color:#d7dce2;font-weight:bold"';
						else if ($key % 2 == 0)
							$row_style = 'style="background-color:#fff;"';
						else
							$row_style = 'style="background-color:#f9f9f9;"';

						echo("<tr $row_style>");
						echo("<td $style>");
						echo(implode("</td><td $style>", $value));
						echo("</td>");
						echo("</tr>");
		            }

					$orientation_word = !empty($this->options["export"]["orientation"]) ? $this->options["export"]["orientation"]:"landscape";
					if ($orientation_word == "landscape")
						$orientation_word = "@page {size:29.7cm 21cm; mso-page-orientation:landscape; margin: 1.2cm 1.2cm 1.2cm 1.2cm; } @page Section1 { } div.Section1 {page:Section1 ;}";
					else
						$orientation_word = "";
	
					echo "</table>";
					$content = ob_get_clean();
		            
					echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
					<html xmlns:office="urn:schemas-microsoft-com:office:office"
						  xmlns:word="urn:schemas-microsoft-com:office:word"
						  xmlns="http://www.w3.org/TR/REC-html40">
					<head>
					<style>        
						'.$orientation_word.'
					</style>
					</head>
					<body>
					<div class=Section2>
					<h1>'.$this->options["export"]["heading"].'</h1>
					'.$content.'
					</div>
					</body>
					</html>';
					die;
				}
				else
				{
					if (version_compare(PHP_VERSION, '5.3', '>=') && file_exists(dirname(__FILE__) .'/excel/PHPExcel/IOFactory.php') && !isset($this->options["export"]["alt"]))
					{
						// create excel sheet
						include_once(dirname(__FILE__).'/excel/PHPExcel/IOFactory.php');
						include_once(dirname(__FILE__).'/excel/PHPExcel/Style/Alignment.php');
						$objPHPExcel = new PHPExcel();

						if ($this->options["export"]["heading"] !== false && $this->options["export"]["heading"] == "")
							$this->options["export"]["heading"] = $this->options["caption"];
						
						// For phpexcel api: https://github.com/PHPOffice/PHPExcel/tree/1.8/Documentation

						if (empty($this->options["export"]["sheetname"])) $this->options["export"]["sheetname"] = "Sheet 1";
						
						$objPHPExcel->getActiveSheet()->setTitle($this->options["export"]["sheetname"]);
						$startCell = 1;

						if ($this->options["export"]["heading"] !== false) {
							$objPHPExcel->getActiveSheet()->mergeCells('A1:Z1');
							$objPHPExcel->getActiveSheet()->setCellValue("A1",$this->options["export"]["heading"]);
							$objPHPExcel->getActiveSheet()->getStyle("A1")->getFont()->setBold(true);
							$objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(16);
							$objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(24);
							$objPHPExcel->getActiveSheet()->getStyle('A1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
							$startCell ++;
						}
						
						// sanitize for csv/xls injection
						array_walk_recursive($arr,array($this,"sanitize_csv_data"));
						$objPHPExcel->getActiveSheet()->fromArray($arr, NULL, "A$startCell");

						$def_arr = $arr;

						// autosize excel columns
						foreach(range('A','Z') as $columnID)
							$objPHPExcel->getActiveSheet()->getColumnDimension($columnID)->setAutoSize(true);
						
						// if customized pdf render is defined, use that
						if (!empty($this->events["on_render_excel"]))
						{
							$func = $this->events["on_render_excel"][0];
							$obj = $this->events["on_render_excel"][1];
							if ($obj)
								call_user_func(array($obj,$func),array("phpexcel" => &$objPHPExcel, "data" => &$arr));
							else
								call_user_func($func,array("phpexcel" => &$objPHPExcel, "data" => &$arr));
						}

						// if array changes, update it
						if ($arr !== $def_arr)
							$objPHPExcel->getActiveSheet()->fromArray($arr, NULL, "A2");
						
					

						// write excel2007 standard xlsx
						$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
						$filename = $this->options["export"]["filename"];
						header('Content-Type: application/vnd.ms-excel');
						header('Content-Disposition: attachment;filename="'.$filename.'.xlsx"');

						// if ssl and IE, remove above cache-control and add following
						if ( (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") || $_SERVER["SERVER_PORT"] == "443" )
						{
							header('Cache-Control: private');
							header('Pragma: token');
						}
						else
						{
							header('Cache-Control: max-age=0');
						}
						ob_end_clean();
						$objWriter->save('php://output');
				}
				else
				{
						include_once(dirname(__FILE__)."/excel/php-export-data.class.php");
						$excel = new ExportDataExcel('browser');

						if (strstr($this->options["export"]["filename"],".xls") === false &&
							strstr($this->options["export"]["filename"],".xlsx") === false )
							$this->options["export"]["filename"] .= ".xls";

						$excel->filename = $this->options["export"]["filename"];
						$excel->initialize();
						foreach($arr as $row)
						{
							$excel->addRow($row);
						}
						$excel->finalize();
					}

					die;
				}
			}

			### P ###
			// custom on select event execution

			$out = null;
			if (!empty($this->events["on_select"]))
			{
				$func = $this->events["on_select"][0];
				$obj = $this->events["on_select"][1];
				$continue = $this->events["on_select"][2];

				if ($obj)
					$out = call_user_func(array($obj,$func),array("params"=> $_REQUEST, "grid"=>$this));
				else
					$out = call_user_func($func,array("params"=> $_REQUEST, "grid"=>$this));

				if (!$continue)
					exit(0);
			}

			// preserve subqueries
			$matches_subsql = $this->remove_subsql();

			// if defined in on_select event
			if (!empty($this->internal["sql_count"]))
			{
				$sql_count = $this->internal["sql_count"];
			}
			else if (!empty($this->select_count))
			{
				$sql_count = $this->select_count.$wh;
			}
			else if (($p = stripos($this->select_command,"GROUP BY")) !== false)
			{
				$sql_count = $this->select_command;
				$p = stripos($sql_count,"GROUP BY");
				$start_q = substr($sql_count,0,$p);
				$end_q = substr($sql_count,$p);

				$having = "";
				if (!empty($this->having_clause))
				$having = "HAVING ".implode(" AND ",$this->having_clause);

				$sql_count = "SELECT count(*) as c FROM ($start_q $wh $end_q $having) pg_tmp";
			}
			else
			{
				$sql_count = $this->select_command.$wh;
				$sql_count = "SELECT count(*) as c FROM (".$sql_count.") pg_tmp";
			}

			// re-adjust subqueries in sql
			$sql_count = $this->add_subsql($sql_count,$matches_subsql);

			# print_r($sql_count);

			$result = $this->execute_query($sql_count);

			if ($this->con)
			{
				$row = $result->FetchRow();
			}
			else
			{
				$row = mysql_fetch_array($result,MYSQL_ASSOC);
			}
			$count = $row['c'];

			// fix for oracle, alias in capitals
			if (empty($count))
				$count = $row['C'];

			### F ###
			// if( $count > intval("5"."00") )
			// 	phpgrid_error("Large"." dataset"." with"." rows"." > "."5"."00".$this->buy_license);

			if( $count > 0 ) {
				$total_pages = ceil($count/$limit);
			} else {
				$total_pages = 0;
			}

			if ($page > $total_pages) $page=$total_pages;
			$start = $limit*$page - $limit; // do not put $limit*($page - 1)
			if ($start<0) $start = 0;

			$responce = new stdClass();
			$responce->page = $page;
			$responce->total = $total_pages;
			$responce->records = intval($count);

			// if client side management, send all records at first request
			if($this->options["loadonce"] == true)
				$limit = $count;

			if (!empty($this->internal["sql"]))
			{
				$SQL = $this->internal["sql"] . " LIMIT $limit OFFSET $start";
			}
			else if (($p = stripos($this->select_command,"GROUP BY")) !== false)
			{
				$start_q = substr($this->select_command,0,$p);
				$end_q = substr($this->select_command,$p);

				$having = "";
				if (!empty($this->having_clause))
					$having = "HAVING ".implode(" AND ",$this->having_clause);

				$SQL = "$start_q $wh $end_q $having ORDER BY $sidx $sord LIMIT $limit OFFSET $start";
			}
			else
			{
				$SQL = $this->select_command.$wh." ORDER BY $sidx $sord LIMIT $limit OFFSET $start";
			}

			// re-adjust subqueries in sql
			$SQL = $this->add_subsql($SQL,$matches_subsql);

			$SQL = $this->prepare_sql($SQL,$this->db_driver);

			// enable SQL query debugging flag
			if (isset($this->debug_sql) && $this->debug_sql) phpgrid_error($SQL);

			$result = $this->execute_query($SQL);

			if ($this->con)
			{
				$rows = $result->GetRows();

				// simulate artificial paging for mssql
				if (count($rows) > $limit)
				{
					// fix for last page
					if (count($rows) == $count)
					{
						$left = count($rows) % $limit;

						if ($left==0) $left = $limit;

						$rows = array_slice($rows,count($rows) - $left);
					}
					else
						$rows = array_slice($rows,count($rows) - $limit);
				}
			}
			else
			{
				$rows = array();
				while($row = mysql_fetch_array($result,MYSQL_ASSOC))
					$rows[] = $row;
			}

			### P ###
			// update extra data for tree grid ... skip hierarchy on search
			if ($this->options["treeGrid"] == true && $_REQUEST["_search"] != "true")
				$this->add_tree_data($rows);

			### P ###
			// custom on_data_display event execution
			if (!empty($this->events["on_data_display"]))
			{
				$func = $this->events["on_data_display"][0];
				$obj = $this->events["on_data_display"][1];

				if ($obj)
					call_user_func(array($obj,$func),array("params" => &$rows));
				else
					call_user_func($func,array("params" => &$rows));
			}

			// preserve userdata for response
			if (!empty($rows["userdata"]))
			{
				$userdata = $rows["userdata"];
				unset($rows["userdata"]);
			}

			foreach ($rows as $row)
			{
				$orig_row = $row;

				unset($c);

				// apply php level formatter for image url 30.12.10
				foreach($this->options["colModel"] as $c)
				{
					$col_name = $c["name"];

					### P ###
					if (isset($c["default"]) && !isset($row[$col_name]))
						$row[$col_name] = $c["default"];

					// link data in grid to any given url
					if (!empty($c["default"]))
					{
						// replace any param in link e.g. http://domain.com?id={id} given that, there is a $col["name"] = "id" exist
						$row[$col_name] = $this->replace_row_data($orig_row,$c["default"]);
					}

					// check conditional data
					if (!empty($c["condition"][0]))
					{
						$r = true;

						// replace {} placeholders from connditional data
						$cond0 = $this->replace_row_data($orig_row,$c["condition"][0]);
						$cond1 = $this->replace_row_data($orig_row,$c["condition"][1]);
						$cond2 = $this->replace_row_data($orig_row,$c["condition"][2]);

						eval("\$r = ".$cond0.";");
						$row[$col_name] = ( $r ? $cond1 : $cond2 );
					}

					// check data filter (alternate of grid on_data_display, but for current column)
					if (!empty($c["on_data_display"]))
					{
						$func = $c["on_data_display"][0];
						$obj = $c["on_data_display"][1];
						$param = $c["on_data_display"][2];

						if (!empty($param))
							$params = array($row,$param);
						else
							$params = array($row);

						if ($obj)
							$row[$col_name] = call_user_func_array(array($obj,$func),$params);
						else
							$row[$col_name] = call_user_func_array($func,$params);
					}
					### P-END ###

					// datetime formating fix
					if (!empty($row[$c["name"]]) && $c["formatter"] == "datetime")
					{
						$dt = $row[$c["name"]];
						$js_dt_fmt = $c["formatoptions"]["newformat"];

						// if data has timestamp, make it date
						$src_fmt = $c["formatoptions"]["srcformat"];
						if ($src_fmt == "U")
							$dt = date("Y-m-d H:i:s",$dt);

						$row[$c["name"]] = $this->custom_date_format($js_dt_fmt,$dt);
					}

					// link data in grid to any given url
					if (!empty($c["link"]))
					{
						// replace any param in link e.g. http://domain.com?id={id} given that, there is a $col["name"] = "id" exist
						// replace_row_data not used due to urlencode work
						foreach($this->options["colModel"] as $link_c)
						{
							// if there is url in data, don't urlencode
							if (strstr($orig_row[$link_c["name"]],"http://"))
								$link_row_data = $orig_row[$link_c["name"]];
							else
								$link_row_data = urlencode($orig_row[$link_c["name"]]);

							$c["link"] = str_replace("{".$link_c["name"]."}", $link_row_data, $c["link"]);
						}

						$attr = "";
						if (!empty($c["linkoptions"]))
							$attr = $c["linkoptions"];

						$row[$col_name] = htmlentities($row[$col_name],ENT_QUOTES, "UTF-8");
						$row[$col_name] = "<a $attr href='{$c["link"]}'>{$row[$col_name]}</a>";
					}

					// render row data as "src" value of <img> tag
					if (isset($c["formatter"]) && $c["formatter"] == "image")
					{
						$attr = array();
						foreach($c["formatoptions"] as $k=>$v)
							$attr[] = "$k='$v'";

						$attr = implode(" ",$attr);
						$row[$col_name] = "<img $attr src='".$row[$col_name] ."'>";
					}

					// show masked data in password
					if (isset($c["formatter"]) && $c["formatter"] == "password")
						$row[$col_name] = "*****";
				}

				// makes null to "" as well
				foreach($row as $k=>$r)
					$row[$k] = ($row[$k] === null) ? "" : $row[$k];

				// ignored \ removal
				// foreach($row as $k=>$r)
					// $row[$k] = stripslashes($row[$k]);

				$responce->rows[] = $row;
			}

			if (isset($this->options["utf8encode"]) && $this->options["utf8encode"] == true)
				$responce = array_utf8_encode_recursive($responce);
			
			// set custom userdata in footer (controlled with on_data_display event)
			if (!empty($userdata))
				$responce->userdata = $userdata;

			header('Content-Type: application/json');
			echo json_encode($responce);
			die;
		}

		### P ###
		// if loading from array
		if (is_array($this->table))
		{
			// replace null with ""
			foreach($this->table as &$row)
			{
				foreach($row as $k=>$r)
					$row[$k] = ($row[$k] === null) ? "" : $row[$k];
			}

			$this->options["data"] = $this->table;
			$this->options["datatype"] = "local";

			if (!isset($this->actions["rowactions"]))
				$this->actions["rowactions"] = false;

			if (!isset($this->actions["add"]))
				$this->actions["add"] = false;

			if (!isset($this->actions["edit"]))
				$this->actions["edit"] = false;

			if (!isset($this->actions["delete"]))
				$this->actions["delete"] = false;
		}

		// few overides - pagination fixes
		$this->options["pager"] = '#'.$grid_id."_pager";
		$this->options["jsonReader"] = array("repeatitems" => false, "id" => "0");

		// allow/disallow edit,del operations
		if ( ($this->actions["edit"] === false && $this->actions["delete"] === false))
			$this->actions["rowactions"] = false;

		// in excel view, only disable inline editing, leave rest
		if ($this->options["cellEdit"] === true)
			$this->actions["edit"] = false;

		// simulate field access right options
		$str_add_form = '';
		$str_edit_form = '';
		$str_delete_form = '';
		$str_edit_access = '';
		$str_inline_access = '';

		$str_add_access = '';
		$str_delete_access = '';
		$str_view_access = '';

		foreach($this->options["colModel"] as &$c)
		{
			// remove dbname when creating as it expose db structure
			unset($c["dbname"]);
			unset($c["sortname"]);

			// for re-edit fix of ckeditor etc
			if ($c["formatter"]=="html")
				unset($c["formatter"]);

			// auto reload & edit for link pattern fix
			if (!empty($c["link"]))
			{
				$this->options["reloadedit"] = true;

				$attr = "";
				if (!empty($c["linkoptions"]))
					$attr = $c["linkoptions"];

				$c["formatter"] = "function(cellval,options,rowdata){

					var l = '".$c["link"]."';
					var tmp = jQuery('#list1').getRowData(options.rowId);
					if (tmp) rowdata = tmp;

					for(r in rowdata)
						l = l.replace('{'+r+'}', rowdata[r]);

					return \"<a $attr href='\"+l+\"'>\"+cellval+\"</a>\";
				}";

				$c["unformat"] = "function(cellval,options,cell){ return jQuery(cell).text();}";

				unset($c["link"]);
				unset($c["linkoptions"]);
			}



			if (!empty($c["show"]))
			{
				if (isset($c["show"]["list"]))
				{
					if ($c["show"]["list"] === false)
						$c["hidden"] = true;
					else
						$c["hidden"] = false;
				}

				$str_pos = '';
				if ($c["formoptions"]["rowpos"])
				{
					$str_pos .= 'jQuery("#TblGrid_'.$grid_id.' tr:eq('.($c["formoptions"]["rowpos"]+1).') td:nth-child('.($c["formoptions"]["colpos"]*2).')").html("");';
					$str_pos .= 'jQuery("#TblGrid_'.$grid_id.' tr:eq('.($c["formoptions"]["rowpos"]+1).') td:nth-child('.($c["formoptions"]["colpos"]*2-1).')").html("");';
				}

				// changed .hide() to .remove() because validation rules apply with .hide()
				if ($c["show"]["edit"] === false)
				{
					// atleast make it readonly on inline mode (hide not possible)
					$c["editrules"]["readonly"] = true;

					// and hide in dialog, keep in bulk edits
					$str_edit_access .= ' if (!jQuery("#'.$grid_id.'").data("bulk-edit-count")) jQuery("#tr_'.$c["index"].'",formid).remove();';
					if (!empty($str_pos)) $str_edit_access .= $str_pos;
				}
				else
					$str_edit_access .= 'jQuery("#tr_'.$c["index"].'",formid).show();';

				if ($c["show"]["bulkedit"] === false)
				{
					$c["bulkedit"] = false;
				}

				if ($c["show"]["add"] === false)
				{
					$str_add_access .= 'jQuery("#tr_'.$c["index"].'",formid).remove();';
					if (!empty($str_pos)) $str_add_access .= $str_pos;
				}
				else
					$str_add_access .= 'jQuery("#tr_'.$c["index"].'",formid).show();';

				if ($c["show"]["view"] === false)
				{
					$str_view_access .= 'jQuery("#trv_'.$c["index"].'").hide();';
					if ($c["formoptions"]["rowpos"])
					{
						$str_pos = '';
						$str_pos .= 'jQuery("#ViewTbl_'.$grid_id.' tr:eq('.($c["formoptions"]["rowpos"]-1).') td:nth-child('.($c["formoptions"]["colpos"]*2).')").html("");';
						$str_pos .= 'jQuery("#ViewTbl_'.$grid_id.' tr:eq('.($c["formoptions"]["rowpos"]-1).') td:nth-child('.($c["formoptions"]["colpos"]*2-1).')").html("");';
						$str_view_access .= $str_pos;
					}
				}
				else
					$str_view_access .= 'jQuery("#trv_'.$c["index"].'").show();';

				unset($c["show"]);
			}

			// make readonly field while editing
			if (isset($c["editrules"]["readonly"]))
			{
				if ($c["editrules"]["readonly"] === true)
				{
					$tag = "input";

					// for text, use input tag name
					if (!empty($c["edittype"]) && $c["edittype"] !== "text")
						$tag = $c["edittype"];

					if (!empty($c["editrules"]["readonly-when"]))
					{
						$cond = $c["editrules"]["readonly-when"];

						if (is_array($cond) && count($cond) == 2)
						{
							// "readonly-when"=>array("==","male")
							if (!is_numeric($cond[1]))
								$cond[1] = '"'.$cond[1].'"';

							$str_edit_access .= 'if (jQuery("#tr_'.$c["index"].' .DataTD '.$tag.'",formid).val() '.$cond[0].' '.$cond[1].')';
							$str_inline_access .= 'if (jQuery("[name='.$c["index"].']:last").val() '.$cond[0].' '.$cond[1].')';
						}
						elseif (is_array($cond) && count($cond) == 3)
						{
							// "readonly-when"=>array("client_id","==","4")
							if (!is_numeric($cond[2]))
								$cond[2] = '"'.$cond[2].'"';

							$str_edit_access .= 'if (jQuery("[name='.$cond[0].']:last",formid).val() '.$cond[1].' '.$cond[2].')';
							$str_inline_access .= 'if (jQuery("[name='.$cond[0].']:last").val() '.$cond[1].' '.$cond[2].')';
						}
						elseif (is_string($cond))
						{
							// readonly-when for checkbox (checked / unchecked)
							if ($tag == "checkbox" && ($cond == "checked" || $cond == "unchecked"))
							{
								if ($cond == "checked")
									$con = 'if ( jQuery("input[name='.$c["index"].']:last").is(":checked") || jQuery(".DataTD input[name='.$c["index"].']:last").is(":checked") )';
								else if ($cond == "unchecked")
									$con = 'if ( !( jQuery("input[name='.$c["index"].']:last").is(":checked") || jQuery(".DataTD input[name='.$c["index"].']:last").is(":checked") ) )';

								$str_edit_access .= $con;
								$str_inline_access .= $con;
							}
							else
							{
								// "readonly-when"=>"function" - when return true, field will be readonly
								$str_edit_access .= "if ({$cond}(formid))";
								$str_inline_access .= "if ({$cond}())";
							}
						}
					}

					// make textbox hidden, for postback
					$text_val = '';
					$str_edit_access .= '{';

					if ($tag == "checkbox")
						$str_edit_access .= 'jQuery(".DataTD input[name='.$c["index"].']").attr("disabled","disabled");';
					else
					{
						if ($tag == "select")
							$text_val = 'jQuery(".DataTD [name='.$c["index"].'] option:selected",formid).text()';
						else
							$text_val = 'jQuery(".DataTD [name='.$c["index"].']").val()';

						if ($c["formoptions"]["rowpos"])
						{
							$str_edit_access .= 'jQuery("#TblGrid_'.$grid_id.' tr:eq('.($c["formoptions"]["rowpos"]+1).') td:nth-child('.($c["formoptions"]["colpos"]*2).')",formid).append('.$text_val.');';
							$str_edit_access .= 'jQuery("#TblGrid_'.$grid_id.' tr:eq('.($c["formoptions"]["rowpos"]+1).') td:nth-child('.($c["formoptions"]["colpos"]*2).') '.$tag.'",formid).hide();';
						}
						else
						{
							// skip readonly for bulk edit dialog
							$str_edit_access .= 'if (!jQuery("#'.$grid_id.'").data("bulk-edit-count")) jQuery("#TblGrid_'.$grid_id.' #tr_'.$c["index"].' .DataTD",formid).append('.$text_val.');';
							$str_edit_access .= 'if (!jQuery("#'.$grid_id.'").data("bulk-edit-count")) jQuery("#tr_'.$c["index"].' .DataTD '.$tag.'",formid).hide();';
						}
					}

					// remove required (*) from readonly
					$str_edit_access .= 'jQuery("#tr_'.$c["index"].' .DataTD font",formid).hide();';
					$str_edit_access .= '}';

					// .not("[id^='gs_']") is required to skip autofilters with same name tags
					// replaced :last due to frozen column autofilter is added in :last
					$str_inline_access .= '{';
					if ($tag == "checkbox")
					{
						$str_inline_access .= 'jQuery("input[name='.$c["index"].'][type=checkbox]").not("[id^=\'gs_\']").attr("disabled","disabled");';
					}
					else
					{
						$str_inline_access .= 'jQuery("'.$tag.'[name='.$c["index"].']").not("[id^=\'gs_\']").hide();';
						$str_inline_access .= 'jQuery("'.$tag.'[name='.$c["index"].']").not("[id^=\'gs_\']").parent().not(":has(span)").append("<span></span>");';

						if ($tag == "select")
						{
							$text_val = 'jQuery("'.$tag.'[name='.$c["index"].']").not("[id^=\'gs_\']").children("option:selected").text()';
							$str_inline_access .= 'jQuery("'.$tag.'[name='.$c["index"].']").not("[id^=\'gs_\']").parent().children("span").html('.$text_val.');';
						}
						else
						{
							$str_inline_access .= 'var v = jQuery("'.$tag.'[name='.$c["index"].']").not("[id^=\'gs_\']").val();';
							$str_inline_access .= 'jQuery("'.$tag.'[name='.$c["index"].']").not("[id^=\'gs_\']").parent().children("span").html(v);';
						}
					}

					$str_inline_access .= '}';

					// setting for inline add code block
					$this->internal["str_inline_access"] = $str_inline_access;
				}
			}
		}

		// set before show form events

		if (!empty($this->internal["add_options"]["beforeShowForm"]))
			$str_add_form = $str_add_access . $this->internal["add_options"]["beforeShowForm"];
		else
			$str_add_form = $str_add_access;

		if (!empty($this->internal["edit_options"]["beforeShowForm"]))
			$str_edit_form = $str_edit_access . $this->internal["edit_options"]["beforeShowForm"];
		else
			$str_edit_form = $str_edit_access;

		if (!empty($this->internal["delete_options"]["beforeShowForm"]))
			$str_delete_form = $str_delete_access . $this->internal["delete_options"]["beforeShowForm"];
		else
			$str_delete_form = $str_delete_access;

		if (!empty($this->internal["view_options"]["beforeShowForm"]))
			$str_view_form = $str_view_access . $this->internal["view_options"]["beforeShowForm"];
		else
			$str_view_form = $str_view_access;

		### P ###
		$fx = "";

		// append add options beforeShowForm implementation
		if ( !empty($this->options["add_options"]["beforeShowForm"]) )
			$fx = "var o=".$this->options["add_options"]["beforeShowForm"]."; o(formid);";
		$this->options["add_options"]["beforeShowForm"] = 'function(formid) { '.$str_add_form.$fx.' }';

		// append edit options beforeShowForm implementation
		if ( !empty($this->options["edit_options"]["beforeShowForm"]) )
			$fx = "var o=".$this->options["edit_options"]["beforeShowForm"]."; o(formid);";
		$this->options["edit_options"]["beforeShowForm"] = 'function(formid) { '.$str_edit_form.$fx.' }';

		// append del options beforeShowForm implementation
		if ( !empty($this->options["delete_options"]["beforeShowForm"]) )
			$fx = "var o=".$this->options["delete_options"]["beforeShowForm"]."; o(formid);";
		$this->options["delete_options"]["beforeShowForm"] = 'function(formid) { '.$str_delete_form.$fx.' }';

		// append view options beforeShowForm implementation
		if ( !empty($this->options["view_options"]["beforeShowForm"]) )
			$fx = "var o=".$this->options["view_options"]["beforeShowForm"]."; o(formid);";
		$this->options["view_options"]["beforeShowForm"] = 'function(formid) { '.$str_view_form. $fx . ' }';

		// focus / select newly inserted row
		if (empty($this->options["add_options"]["afterComplete"]))
		$this->options["add_options"]["afterComplete"] = "function (response, postdata) {
																r = JSON.parse(response.responseText);
																jQuery( document ).ajaxComplete(function() {
																	jQuery('#{$grid_id}').setSelection(r.id);
																	jQuery( document ).unbind('ajaxComplete');
																	});
															}";
		// event for dialog < > navigation
		$this->options["view_options"]["afterclickPgButtons"] = 'function(formid) { '.$str_view_access.' }';
		### P-END ###

		$reload_after_edit = "";
		// after save callback
		if (!empty($this->options["onAfterSave"]))
			$reload_after_edit .= "var fx_save = {$this->options["onAfterSave"]}; fx_save();";
		if ($this->options["reloadedit"] === true)
			$reload_after_edit .= "jQuery('#$grid_id').trigger('reloadGrid',[{current:true}]);";

		### P ###
		if (empty($this->options["add_options"]["success_msg"]))
			$this->options["add_options"]["success_msg"] = "Record added";
		if (empty($this->options["edit_options"]["success_msg"]))
			$this->options["edit_options"]["success_msg"] = "Record updated";
		if (empty($this->options["edit_options"]["success_msg_bulk"]))
			$this->options["edit_options"]["success_msg_bulk"] = "Record(s) updated";
		if (empty($this->options["delete_options"]["success_msg"]))
			$this->options["delete_options"]["success_msg"] = "Record deleted";


		if (empty($this->options["add_options"]["afterSubmit"]))
		$this->options["add_options"]["afterSubmit"] = 'function(response) { if(response.status == 200)
																				{
																					'.$reload_after_edit.'
																					if (response.responseText)
																					{
																						var data = JSON.parse(response.responseText);
																						if (data && data.msg)
																						{
																							fx_success_msg(data.msg,data.fade);
																							return [true,""];
																						}
																					}

																					fx_success_msg("'.$this->options["add_options"]["success_msg"].'",1);
																					return [true,""];
																				}
																			}';

		if (empty($this->options["edit_options"]["afterSubmit"]))
		$this->options["edit_options"]["afterSubmit"] = 'function(response) { if(response.status == 200)
																				{
																					'.$reload_after_edit.'

																					if (response.responseText)
																					{
																						var data = JSON.parse(response.responseText);
																						if (data && data.msg)
																						{
																							fx_success_msg(data.msg,data.fade);
																							return [true,""];
																						}
																					}

																					fx_success_msg("'.$this->options["edit_options"]["success_msg"].'",1);
																					return [true,""];
																				}
																			}';

		if (empty($this->options["delete_options"]["afterSubmit"]))
		$this->options["delete_options"]["afterSubmit"] = 'function(response) { if(response.status == 200)
																				{
																					fx_success_msg("'.$this->options["delete_options"]["success_msg"].'",1);
																			      	return [true,""];
																				}
																			}';
		### P-END ###

		// search options for templates
		$this->options["search_options"]["closeAfterSearch"] = true;
		
		// by default enabled adv search (9/8/21)
		$this->options["search_options"]["multipleSearch"] = true;
		
		### P ###
		// multiple search group function
		if ($this->actions["search"] == "group")
		{
			$this->options["search_options"]["multipleSearch"] = true;
			$this->options["search_options"]["multipleGroup"] = true;
		}

		if (!isset($this->options["search_options"]["sopt"]))
			$this->options["search_options"]["sopt"] = array('cn','eq','ne','lt','le','gt','ge','bw','bn','in','ni','ew','en','nc','nu','nn');

		// ### P ###
		// if pivot remove extras
		if (!empty($this->pivot_options))
		{
			unset($this->options["colModel"]);
			unset($this->options["colNames"]);
			unset($this->options["add_options"]);
			unset($this->options["edit_options"]);
			unset($this->options["delete_options"]);
			unset($this->options["view_options"]);
			unset($this->options["search_options"]);
			unset($this->options["export"]);
			unset($this->options["subGridOptions"]);
			unset($this->options["datatype"]);
			$this->options["pgtext"] = null;
			$this->options["pgbuttons"] = null;
			$this->options["rowList"] = array();
			$this->options["rowNum"] = 99999;
		}

		$out = json_encode_jsfunc($this->options);
		$out = substr($out,0,strlen($out)-1);

		// connect bulk edit unrequire // fix for required cols
		if ($this->actions["bulkedit"] === true)
		{
			$out_fx = "";
			// chain 'afterShowForm' function with base working
			if (!empty($this->options["edit_options"]["afterShowForm"]))
			{
				$out_fx = "var fx = ".$this->options["edit_options"]["afterShowForm"]."; fx(f);";
			}

			$this->options["edit_options"]["afterShowForm"] = "function(f){ $out_fx return fx_bulk_unrequire('{$grid_id}'); }";
		}

		// create Edit/Delete - Save/Cancel column in grid
		if ($this->actions["rowactions"] !== false)
		{
			$act_links = array();

			### P-START ###
			if ($this->internal["actionicon"] === true) // icons as action links
			{
				if ($this->actions["edit"] !== false)
					$act_links[] = "<a class=\"ui-custom-icon ui-icon ui-icon-pencil\" title=\"\" href=\"javascript:void(0);\" onclick=\"jQuery(this).dblclick();\"></a>";

				### P ###
				if ($this->actions["clone"] === true)
					$act_links[] = "<a class=\"ui-custom-icon ui-icon ui-icon-copy\" title=\"Clone this row\" href=\"javascript:void(0);\" onclick=\"fx_clone_row(\'$grid_id\',\''+cl+'\'); \"></a>";
				### P-END ###

				if ($this->actions["delete"] !== false)
					$act_links[] = "<a class=\"ui-custom-icon ui-icon ui-icon-trash\" title=\"Delete this row\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#$grid_id\').resetSelection(); jQuery(\'#$grid_id\').setSelection(\''+cl+'\'); jQuery(\'#del_$grid_id\').click(); \"></a>";

				$act_links = implode("", $act_links);

				$extraparam = "{}";
				if (!empty($this->options["edit_options"]["editData"]))
				{
					$extraparam = addslashes(json_encode($this->options["edit_options"]["editData"]));
					$extraparam = str_replace('"',"'",$extraparam);
				}

				// resize at gridcomplete event for better experience
				$full_width_resize_code = "";
				if ($this->options["responsive"] === true || $this->options["autoresize"] === true) 
				{
					$full_width_resize_code = "
						var gid = '$grid_id';

						// update for responsive subgrid, set parent colspan to expand all max columns
						if (jQuery('#gbox_'+gid).parent().closest('.subgrid-data').length)
							jQuery('#gbox_'+gid).parent().closest('.subgrid-data').attr('colspan',1000);

						newWidth = jQuery('#gbox_'+gid).parent().width() - 5;

						var oldWidth = jQuery('#'+gid).jqGrid('getGridParam', 'width');

						if (oldWidth !== newWidth && Math.abs(oldWidth - newWidth) > 10)
						{
							jQuery('#'+gid).jqGrid('setGridWidth', newWidth);
						}

						// not being called when datatype is local
						//setTimeout(function(){phpgrid_$grid_id.fx_grid_resize();},50);
					";
				}
				

				$out .= ",'gridComplete': function()
							{
								$full_width_resize_code
								var ids = jQuery('#$grid_id').jqGrid('getDataIDs');
								var save_text = jQuery.jgrid.nav.saveRow || 'Save this row';
								var restore_text = jQuery.jgrid.edit.bCancel;
								var edit_text = jQuery.jgrid.edit.editCaption;
								var del_text = jQuery.jgrid.del.caption;

								for(var i=0;i < ids.length;i++)
								{
									var cl = ids[i];

									be = '$act_links';

									// il_save, ilcancel, iledit are clicked for inlineNav button reset
									se = '<a class=\"ui-custom-icon ui-icon ui-icon-disk\" title=\"'+save_text+'\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#{$grid_id}_ilsave\').click(); if (jQuery(\'#$grid_id\').saveRow(\''+cl+'\',null,null,{$extraparam}) || jQuery(\'.editable\').length==0) { jQuery(this).parent().hide(); jQuery(this).parent().prev().show(); ". addslashes($reload_after_edit)." }\"></a>';
									ce = '<a class=\"ui-custom-icon ui-icon ui-icon-cancel\" title=\"'+restore_text+'\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#{$grid_id}_ilcancel\').click(); jQuery(\'#$grid_id\').restoreRow(\''+cl+'\'); jQuery(this).parent().hide(); jQuery(this).parent().prev().show();\"></a>';

									// for inline add option
									if (ids[i].indexOf('jqg') != -1)
									{
										se = '<a class=\"ui-custom-icon ui-icon ui-icon-disk\" title=\"'+save_text+'\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#{$grid_id}_ilsave\').click(); \">Save</a>';
										ce = '<a class=\"ui-custom-icon ui-icon ui-icon-cancel\" title=\"'+restore_text+'\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#{$grid_id}_ilcancel\').click(); jQuery(this).parent().hide(); jQuery(this).parent().prev().show();\">Cancel</a>';
										jQuery('#$grid_id').jqGrid('setRowData',ids[i],{act:'<span style=display:none id=\"edit_row_{$grid_id}_'+cl+'\">'+be+'</span>'+'<span id=\"save_row_{$grid_id}_'+cl+'\">'+se+ce+'</span>'});
									}
									else
										jQuery('#$grid_id').jqGrid('setRowData',ids[i],{act:'<span id=\"edit_row_{$grid_id}_'+cl+'\">'+be+'</span>'+'<span style=display:none id=\"save_row_{$grid_id}_'+cl+'\">'+se+ce+'</span>'});
								}

								// update locale
								jQuery('.ui-custom-icon.ui-icon-pencil').attr('title',edit_text);
								jQuery('.ui-custom-icon.ui-icon-trash').attr('title',del_text);
							}";
			}
			else // text based action links
			{
				if ($this->actions["edit"] !== false)
					$act_links[] = "<a title=\"Edit this row\" href=\"javascript:void(0);\" onclick=\"jQuery(this).dblclick();\">Edit</a>";

				### P ###
				if ($this->actions["clone"] === true)
					$act_links[] = "<a title=\"Clone this row\" href=\"javascript:void(0);\" onclick=\"fx_clone_row(\'$grid_id\',\''+cl+'\'); \">Clone</a>";
				### P-END ###

				if ($this->actions["delete"] !== false)
					$act_links[] = "<a title=\"Delete this row\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#$grid_id\').resetSelection(); jQuery(\'#$grid_id\').setSelection(\''+cl+'\'); jQuery(\'#del_$grid_id\').click(); \">Delete</a>";

				$act_links = implode(" | ", $act_links);

				$out .= ",'gridComplete': function()
							{
								var ids = jQuery('#$grid_id').jqGrid('getDataIDs');
								var save_text = jQuery.jgrid.nav.saveRow || 'Save this row';
								var restore_text = jQuery.jgrid.nav.restoreRow || 'Restore this row';

								for(var i=0;i < ids.length;i++)
								{
									var cl = ids[i];

									be = '$act_links';

									// il_save, ilcancel, iledit are clicked for inlineNav button reset
									se = ' <a title=\"'+save_text+'\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#{$grid_id}_ilsave\').click(); if (jQuery(\'#$grid_id\').saveRow(\''+cl+'\') || jQuery(\'.editable\').length==0) { jQuery(this).parent().hide(); jQuery(this).parent().prev().show(); ". addslashes($reload_after_edit)." }\">Save</a>';
									ce = ' | <a title=\"'+restore_text+'\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#{$grid_id}_ilcancel\').click(); jQuery(\'#$grid_id\').restoreRow(\''+cl+'\'); jQuery(this).parent().hide(); jQuery(this).parent().prev().show();\">Cancel</a>';

									// for inline add option
									if (ids[i].indexOf('jqg') != -1)
									{
										se = ' <a title=\"'+save_text+'\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#{$grid_id}_ilsave\').click(); \">Save</a>';
										ce = ' | <a title=\"'+restore_text+'\" href=\"javascript:void(0);\" onclick=\"jQuery(\'#{$grid_id}_ilcancel\').click(); jQuery(this).parent().hide(); jQuery(this).parent().prev().show();\">Cancel</a>';
										jQuery('#$grid_id').jqGrid('setRowData',ids[i],{act:'<span style=display:none id=\"edit_row_{$grid_id}_'+cl+'\">'+be+'</span>'+'<span id=\"save_row_{$grid_id}_'+cl+'\">'+se+ce+'</span>'});
									}
									else
										jQuery('#$grid_id').jqGrid('setRowData',ids[i],{act:'<span id=\"edit_row_{$grid_id}_'+cl+'\">'+be+'</span>'+'<span style=display:none id=\"save_row_{$grid_id}_'+cl+'\">'+se+ce+'</span>'});
								}
							}";
			}
		}

		$out .= ",'ondblClickRow': function (id, iRow, iCol, e) {";

		// double click editing option
		if ($this->actions["rowactions"] !== false && $this->actions["edit"] !== false && $this->options["cellEdit"] !== true)
		{
			$is_inline = "false";
			if ($this->actions["inline"] || $this->actions["inlineadd"])
				$is_inline = "true";

			$out .= "
					if (!e) e = window.event;
					var element = e.target || e.srcElement;
					var is_inline = {$is_inline};

					// only make sub/parent grid inline editable - previously both become editable if same rowid
					if (jQuery(element).closest('table').attr('id') != '{$grid_id}')
						return;

					// if no editable row, reset lastSel
					if(jQuery('.editable').length==0) lastSel = null;

					// if row already dblclicked, ignore
					if (id==lastSel)
						return;

					// for inlineNav mode fix
					if (is_inline)
					{
						// if dblclicked and then single clicked (unselect row) then dblclick other, cancel last editing
						if(id!==lastSel && lastSel != undefined)
							jQuery('#{$grid_id}_ilcancel').click();

						jQuery('#{$grid_id}').resetSelection();
					}

					if(id && id!==lastSel && id.indexOf('jqg') != 0)
					{
						// reset data msg, for new row edit without save last row
						if (typeof(lastSel) != 'undefined' && jQuery('#{$grid_id} > tbody > tr > td > .editable').length >0)
							if(confirm(jQuery.jgrid.edit.saveData))
							{
								jQuery('#$grid_id').saveRow(lastSel);
							}

						jQuery('#{$grid_id}').restoreRow(lastSel);

						// to enable autosave on dblclick new row + dont edit on validation error
						// if (typeof(lastSel) != 'undefined')
							// if (!jQuery('#$grid_id').saveRow(lastSel))
								// return;

						// disabled previously edit icons
						jQuery('#edit_row_{$grid_id}_'+lastSel).show();
						jQuery('#save_row_{$grid_id}_'+lastSel).hide();

						// highlight last off - row on multiselect dblclick
						if (!is_inline) jQuery('#{$grid_id}').setSelection(lastSel);
						lastSel=id;
					}

					// highlight - row on multiselect dblclick
					if (!is_inline) jQuery('#{$grid_id}').setSelection(id);

					// preserve horizontal scroll position
					var sl = jQuery('div.ui-jqgrid-bdiv').scrollLeft();
					jQuery('#$grid_id').editRow(id, true, function()
															{
																// focus on dblclicked element
																setTimeout(function(){ jQuery('input, select, textarea', element).focus(); },100);
																setTimeout(function(){ jQuery('input', element).attr('autocomplete',Math.random()); },100);
																setTimeout(function(){ jQuery('div.ui-jqgrid-bdiv').scrollLeft(sl); },100);
																jQuery('#$grid_id').jqGrid('unbindKeys');
															},
															function()
															{
																jQuery('#edit_row_{$grid_id}_'+id).show();
																jQuery('#save_row_{$grid_id}_'+id).hide();
																jQuery('#$grid_id').jqGrid('bindKeys',{'onEnter':function(id){ jQuery('tr.jqgrow[id='+id+']').dblclick(); } });
																$reload_after_edit
																return true;
															},null,{},
															function()
															{
																// force reload grid after inline save
																$reload_after_edit
															},null,
															function()
															{
																jQuery('#edit_row_{$grid_id}_'+id).show();
																jQuery('#save_row_{$grid_id}_'+id).hide();
																jQuery('#$grid_id').jqGrid('bindKeys',{'onEnter':function(id){ jQuery('tr.jqgrow[id='+id+']').dblclick(); } });
																$reload_after_edit
																return true;
															}
												);

					// for inlineNav edit button fix
					if (is_inline)
					{
						jQuery('#{$grid_id}').setSelection(id, true);
						jQuery('#{$grid_id}_iledit').click();
					}

					// hide edit and show save
					jQuery('#edit_row_{$grid_id}_'+id).hide();
					jQuery('#save_row_{$grid_id}_'+id).show();

					// frozen columns height adjustments on edit
					jQuery('.frozen-bdiv tr.jqgrow').each(function () {
						var h = jQuery('#'+jQuery.jgrid.jqID(this.id)).height();
						jQuery(this).height(h);
					});

					$str_inline_access";
		}

		// chain 'ondblClickRow' function with base working
		if (!empty($this->options["ondblClickRow"]))
		{
			$out .= "var fx = ".$this->options["ondblClickRow"]."; fx(id, iRow, iCol);";
			unset($this->options["ondblClickRow"]);
		}

		$out .= "}";

		### P ###
		// if subgrid is there, enable subgrid feature
		if (isset($this->options["subgridurl"]) && $this->options["subgridurl"] != '')
		{
			// we pass two parameters
			// subgrid_id is a id of the div tag created within a table
			// the row_id is the id of the row
			// If we want to pass additional parameters to the url we can use
			// the method getRowData(row_id) - which returns associative array in type name-value
			// here we can easy construct the following

			$pass_params = "false";
			if (!empty($this->options["subgridparams"]))
				$pass_params = "true";

			// chain 'subGridRowExpanded' function with base working
			if (!empty($this->options["subGridRowExpanded"]))
			{
				$str_fx = "var fx = ".$this->options["subGridRowExpanded"]."; fx();";
				unset($this->options["subGridRowExpanded"]);
			}

			$s = (strstr($this->options["subgridurl"], "?")) ? "&":"?";
			$out .= ",'subGridRowExpanded': function(subgridid, id)
											{
												var data = '{$s}subgrid='+subgridid+'&rowid='+id;
												if('$pass_params' == 'true')
												{
													var anm = '".$this->options["subgridparams"]."';
													anm = anm.split(',');
													var rd = jQuery('#".$grid_id."').jqGrid('getRowData', id);
													if(rd) {
														for(var i=0; i<anm.length; i++) {
															anm[i] = anm[i].trim();
															if(rd[anm[i]]) {
																data += '&' + anm[i] + '=' + escape(rd[anm[i]]);
															}
														}
													}
												}
												// added loading text on + click for subgrid
												jQuery('#'+jQuery.jgrid.jqID(subgridid)).html('<div style=\'margin:5px\'>'+jQuery.jgrid.defaults.loadtext+'</div>');

												jQuery('#'+jQuery.jgrid.jqID(subgridid)).load('".$this->options["subgridurl"]."'+data,{},function(){ ".$str_fx." });
											}";
		}

		// on error
		$out .= ",'loadError': function(xhr,status, err) {
					try
					{
						jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,'<div class=\"ui-state-error\">'+ xhr.responseText +'</div>',
													jQuery.jgrid.edit.bClose,{buttonalign:'right'});

						jQuery('#info_dialog').abscenter();					
					}
					catch(e) { alert(xhr.responseText);}
				}
				";

		// on cell edit error
		$out .= ",'errorCell': function(res,stat,err) {

					jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,
					'<div class=\"ui-state-error\">'+ res.responseText +'</div>',
					jQuery.jgrid.edit.bClose,
					{buttonalign:'right'}
					);

				}
				";

		// on row selection operation
		$out .= ",'onSelectRow': function(ids) { ";
				### P ###
				if (isset($this->options["detail_grid_id"]) && $this->options["detail_grid_id"] != '')
				{
					$detail_grid_id	= $this->options["detail_grid_id"];
					$d_grids = explode(",", $detail_grid_id);

					foreach($d_grids as $detail_grid_id)
					{
						$detail_url = $this->options["url"];

						// remove master grid's grid_id param
						$detail_url = str_replace('&grid_id=','&',$detail_url);
						$detail_url = str_replace('?grid_id=','?',$detail_url);

						// append grid_id param for detail grid
						$s = (strstr($this->options["url"], "?")) ? "&":"?";
						$detail_url .= $s."grid_id=". $detail_grid_id;

						// if master grid inside subgrid
						if (isset($_REQUEST["subgrid"]))
							$detail_grid_id = $_REQUEST["subgrid"]."_".$detail_grid_id;

						$out .= "
						var data = '';
						if ('{$this->options["subgridparams"]}'.length > 0)
						{
							var anm = '".$this->options["subgridparams"]."';
							anm = anm.split(',');
							var rd = jQuery('#".$grid_id."').jqGrid('getRowData', ids);
							if(rd) {
								for(var i=0; i<anm.length; i++) {
									anm[i] = anm[i].trim();
									if(rd[anm[i]]) {
										data += '&' + anm[i] + '=' + escape(rd[anm[i]]);
									}
								}
							}
						}

						// get multiselect as well, pass all ids to detail grid
						if (jQuery('#".$grid_id."').jqGrid('getGridParam','selarrrow').length)
							ids = jQuery('#".$grid_id."').jqGrid('getGridParam','selarrrow');
						else if (jQuery('#".$grid_id."').jqGrid('getGridParam','selrow') != null)
							ids = jQuery('#".$grid_id."').jqGrid('getGridParam','selrow');
						else
							ids = null;

						if(ids == null)
						{
							ids=0;
							if(jQuery('#".$detail_grid_id."').jqGrid('getGridParam','records') >0 )
							{
								jQuery('#".$detail_grid_id."').jqGrid('setGridParam',{datatype:'json',url:'".$detail_url."&rowid='+ids+data,editurl:'".$detail_url."&rowid='+ids+data,cellurl:'".$detail_url."&rowid='+ids+data,jqgrid_page:1});
								jQuery('#".$detail_grid_id."').trigger('reloadGrid',[{jqgrid_page:1}]);
							}

							// disable detail grid buttons if master row not selected
							jQuery('#".$detail_grid_id."_pager_left .ui-pg-button').not(':has(span.ui-separator)').addClass('ui-state-disabled');
							jQuery('#".$detail_grid_id."_ilsave, #".$detail_grid_id."_ilcancel').addClass('ui-state-disabled');
						}
						else
						{
							jQuery('#".$detail_grid_id."').jqGrid('setGridParam',{datatype:'json',url:'".$detail_url."&rowid='+ids+data,editurl:'".$detail_url."&rowid='+ids+data,cellurl:'".$detail_url."&rowid='+ids+data,jqgrid_page:1});
							jQuery('#".$detail_grid_id."').trigger('reloadGrid',[{jqgrid_page:1}]);

							// enable detail grid buttons if master row selected
							jQuery('#".$detail_grid_id."_pager_left .ui-pg-button').not(':has(span.ui-separator)').removeClass('ui-state-disabled');
							jQuery('#".$detail_grid_id."_ilsave, #".$detail_grid_id."_ilcancel').addClass('ui-state-disabled');

						}


						jQuery('#".$detail_grid_id."').data('jqgrid_detail_grid_params','&rowid='+ids+data);
						";
					}
				};

				### P ###
				// obseleted now
				if (!empty($this->events["js_on_select_row"]))
				{
					$out .= "if (typeof({$this->events["js_on_select_row"]}) != 'undefined') {$this->events["js_on_select_row"]}(ids);";
				}

				// chain 'onSelectRow' function with base working
				if (!empty($this->options["onSelectRow"]))
				{
					$out .= "var fx = ".$this->options["onSelectRow"]."; fx(ids);";
					unset($this->options["onSelectRow"]);
				}

		// closing of select row events
		$out .= "}";

		// fix for formatting, to apply on only new records of virtual scroll
		if($this->options["scroll"] == true)
		{
			$out .= ",'beforeRequest': function() {";
				$out .= "jQuery('#$grid_id').data('jqgrid_rows',jQuery('#$grid_id tr.jqgrow').length);";
			$out .= "}";
		}

		// on load complete operation
		$out .= ",'loadComplete': function(ids) {";

				// In case 'All' param is used in pager
				$out .= "jQuery('#{$grid_id}_pager option[value=\"All\"]').val(99999);";
				$out .= "jQuery('#{$grid_id}_toppager option[value=\"All\"]').val(99999);";

				// remove browser autocomplete from autofilter
				$out .= "jQuery('input', '.ui-search-input').attr('autocomplete','off');";

				// select 'All' in pager by default
				if ($this->options["rowNum"] == "99999")
				{
					$out .= "jQuery('#{$grid_id}_pager select.ui-pg-selbox').val(99999);";
					$out .= "jQuery('#{$grid_id}_toppager select.ui-pg-selbox').val(99999);";
				}

				$add_options = json_encode_jsfunc($this->options["add_options"]);
				$out .= "
					// if .php?form=add, show add form directly - remove grid list
					if ( get_querystring_value('form') == 'add' || '$dialog' == 'add')
					{
						if (get_querystring_value('grid_id') == '' || get_querystring_value('grid_id') == '{$grid_id}')
						{
							fx_show_add_form('{$grid_id}',false,true,$add_options);
						}
					}";

				$view_options = json_encode_jsfunc($this->options["view_options"]);
				$out .= "
					// if .php?form=view&rid=22, show view form directly with id 22 - remove grid list
					if ( get_querystring_value('form') == 'view' || '$dialog' == 'view')
					{
						if (get_querystring_value('grid_id') == '' || get_querystring_value('grid_id') == '{$grid_id}')
						{
							fx_show_view_form('{$grid_id}',false,true,$view_options);
						}
					}";

				$edit_options = json_encode_jsfunc($this->options["edit_options"]);
				$out .= "
					// if .php?form=edit&rid=22, show view form directly with id 22 - remove grid list
					if ( get_querystring_value('form') == 'edit' || '$dialog' == 'edit')
					{
						if (get_querystring_value('grid_id') == '' || get_querystring_value('grid_id') == '{$grid_id}')
						{
							fx_show_edit_form('{$grid_id}',false,true,$view_options);
						}
					}";
					
				### F ### uncomment 2 lines (no record message)
				// text for encrypt: 'You are using Free / Non-commercial version.<br> For Commercial Use & Advanced features <a href=\'http://www.gridphp.com/compare/?track=no-record\' target=\'_blank\' style=\'color:blue\'>Buy Licensed Version</a>.'
				// show no record message at center
				$out .= "if (jQuery('#{$grid_id}').getGridParam('records') == 0)
						{
							// var tag = 'Lbh ner hfvat Serr / Aba-pbzzrepvny irefvba.<oe />Sbe Pbzzrepvny Hfr & Nqinaprq srngherf <n uers=\'uggc://jjj.cuctevq.bet/pbzcner/?genpx=ab-erpbeq\' gnetrg=\'_oynax\' fglyr=\'pbybe:oyhr\'>Ohl Yvprafrq Irefvba</n>.'.rot13();
							// jQuery.jgrid.defaults.emptyrecords += '<br><br><br>' + tag;

							if (jQuery('#div_no_record_{$grid_id}').length==0)
								jQuery('#gbox_{$grid_id} .ui-jqgrid-bdiv').not('.frozen-bdiv').append('<div id=\"div_no_record_{$grid_id}\" align=\"center\" style=\"padding:30px 0;\">'+jQuery.jgrid.defaults.emptyrecords+'</div>');
							else
								jQuery('#div_no_record_{$grid_id}').show();
						}
						else
						{
							jQuery('#div_no_record_{$grid_id}').hide();
						}";

				### P ###
				if (isset($this->options["subGrid"]) && $this->options["subGrid"] == true)
				{
					// highlight expanded subgrid row
					ob_start();
					echo "
							jQuery('.ui-sgcollapsed').click(function(){ var row = jQuery(this).closest('tr').attr('id');
							var gid = jQuery(this).closest('.ui-jqgrid-btable').attr('id');
							jQuery('#'+gid).jqGrid('setSelection',row, false); });
					";
					$out .= ob_get_clean();
				}

				### P ###
				if (isset($this->options["detail_grid_id"]) && $this->options["detail_grid_id"] != '')
				{
					$detail_grid_id	= $this->options["detail_grid_id"];
					$d_grids = explode(",", $detail_grid_id);

					foreach($d_grids as $detail_grid_id)
					{
						$detail_url = $this->options["url"];
						$s = (strstr($this->options["url"], "?")) ? "&":"?";
						$detail_url .= $s."grid_id=". $detail_grid_id;

						$out .= "
								// if master reloaded with current selection: reloadLoad with {current:true}

								// multiselect pass all ids to detail grid
								if (jQuery('#".$grid_id."').jqGrid('getGridParam','selarrrow').length)
									rowids = jQuery('#".$grid_id."').jqGrid('getGridParam','selarrrow');
								else if (jQuery('#".$grid_id."').jqGrid('getGridParam','selrow') != null)
									rowids = jQuery('#".$grid_id."').jqGrid('getGridParam','selrow');
								else
									rowids = null;

								// get first id incase of multiselect
								var firstId = parseInt(rowids);
								var subgridParams = jQuery(this).getGridParam('subgridparams');
								var data = '';
								if (subgridParams && subgridParams.length > 0)
								{
									var anm = subgridParams;
									anm = anm.split(',');
									var rd = jQuery(this).jqGrid('getRowData', firstId);
									console.log(rd);
									if(rd) {
										for(var i=0; i<anm.length; i++) {
											anm[i] = anm[i].trim();
											if(rd[anm[i]]) {
												data += '&' + anm[i] + '=' + escape(rd[anm[i]]);
											}
										}
									}
								}

								jQuery('#".$detail_grid_id."').jqGrid('setGridParam',{url:'".$detail_url."&rowid='+rowids+'&data='+data, editurl:'".$detail_url."&rowid='+rowids+'&data='+data, jqgrid_page:1});
								jQuery('#".$detail_grid_id."').trigger('reloadGrid',[{jqgrid_page:1}]);
								jQuery('#".$detail_grid_id."').data('jqgrid_detail_grid_params','');

								// for multiselect, disable if no master row selected
								if (jQuery('#$grid_id').jqGrid('getGridParam','selarrrow').length == 0)
								{
									setTimeout(()=>{
									jQuery('#".$detail_grid_id."_pager_left .ui-pg-button').addClass('ui-state-disabled');
									},10);
								}

								// for non-multiselect, disable if no master row selected
								if (!jQuery('#$grid_id').jqGrid('getGridParam','selrow'))
								{
									setTimeout(()=>{
									jQuery('#".$detail_grid_id."_pager_left .ui-pg-button').addClass('ui-state-disabled');
									},10);
								}
								";
					}

				}

				// formatting fix for virtual scrolling
				$fix_format = "";
				if($this->options["scroll"] == true)
				{
					$fix_format .= "var last_rows = 0;
									if (typeof(jQuery('#$grid_id').data('jqgrid_rows')) != 'undefined')
										i = i + jQuery('#$grid_id').data('jqgrid_rows');
									";
				}


				// celledit option and readonly mode
				if ($this->options["cellEdit"] === true)
				{
					foreach($this->options["colModel"] as $t)
						if ($t["editrules"]["readonly"] == true)
							$fix_format .= "jQuery('#{$grid_id} tr.jqgrow:eq('+i+') td[aria-describedby={$grid_id}_{$t["name"]}]').addClass('not-editable-cell');";
				}

				// $out .= "fx_enable_copy();";

				$out .= "if(ids && ids.rows) jQuery.each(ids.rows,function(i){
							$fix_format
							";

						### P ###
						if (is_array($this->conditional_css) && count($this->conditional_css))
						{
							foreach ($this->conditional_css as $value)
							{
								// apply style on target column if defined
								if (empty($value["target"]))
									$value["target"] = $value["column"];

								// suppress warnings
								$value["class"] = (empty($value["class"]))?"":$value["class"];
								$value["css"] = (empty($value["css"]))?"":$value["css"];
								$value["cellcss"] = (empty($value["cellcss"]))?"":$value["cellcss"];
								$value["cellclass"] = (empty($value["cellclass"]))?"":$value["cellclass"];

								// if wrong column specified, skip formatting
								$out .= "if (typeof(this.{$value["column"]}) == 'undefined') return;";

								// using {column} placeholder in formatting value
								preg_match('/{(.*)}/', $value["value"], $match);
								if (count($match))
								{
									// if html remove it using text(), if string convert toString(), if numeric use parseFloat
									if ($value["op"] == "cn" || $value["op"] == "eq" || $value["op"] == "=")
										$value["value"] = "'+ ( /(<([^>]+)>)/ig.test(this.$match[1]) ? jQuery(this.$match[1]).text() : (jQuery.isNumeric(this.$match[1]) ? parseFloat(this.$match[1]) : this.$match[1].toString()) )+ '";
									else
										$value["value"] = "( /(<([^>]+)>)/ig.test(this.$match[1]) ? jQuery(this.$match[1]).text() : (jQuery.isNumeric(this.$match[1]) ? parseFloat(this.$match[1]) : this.$match[1].toString()) )";
								}

								// filter extras if not numeric
								$out .= "if (!jQuery.isNumeric(this.{$value["column"]}))
								this.{$value["column"]} = this.{$value["column"]}.replace(/(<([^>]+)>)/ig,'');
								";
								if ($value["op"] == "cn")
								{
									$out .= "
										if (this.{$value["column"]}.toString().toLowerCase().indexOf('{$value["value"]}'.toString().toLowerCase()) != -1)
										{
											if ('".$value["class"]."' != '')
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').addClass('".$value["class"]."');
											else if (\"".$value["css"]."\" != '')
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').css('background-image','revert').css({{$value["css"]}});
											else if ('".$value["cellclass"]."' != '')
											{
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').css('background-image','revert');
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+') td[aria-describedby={$grid_id}_{$value["target"]}]').addClass('".$value["cellclass"]."');
											}
											else if (\"".$value["cellcss"]."\" != '')
											{
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').css('background-image','revert');
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+') td[aria-describedby={$grid_id}_{$value["target"]}]').css('background','revert').css({{$value["cellcss"]}});
											}
										}";
								}
								else if ($value["op"] == "eq" || $value["op"] == "=" || $value["op"] == "==")
								{
									$out .= "
										if (this.{$value["column"]}.toString().toLowerCase() == '{$value["value"]}'.toString().toLowerCase())
										{
											if ('".$value["class"]."' != '')
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').addClass('".$value["class"]."');
											else if (\"".$value["css"]."\" != '')
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').css('background-image','revert').css({{$value["css"]}});
											else if ('".$value["cellclass"]."' != '')
											{
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').css('background-image','revert');
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+') td[aria-describedby={$grid_id}_{$value["target"]}]').addClass('".$value["cellclass"]."');
											}
											else if (\"".$value["cellcss"]."\" != '')
											{
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').css('background-image','revert');
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+') td[aria-describedby={$grid_id}_{$value["target"]}]').css('background','revert').css({{$value["cellcss"]}});
											}
										}";
								}
								else if ($value["op"] == "<" || $value["op"] == "<=" || $value["op"] == ">" || $value["op"] == ">=" || $value["op"] == "!=")
								{
									// for date comparison
									$value["value"] = preg_replace('/([0-9]{4})-([0-9]{2})-([0-9]{2})( ([0-9]{2}):([0-9]{2}):([0-9]{2}))?/m',"$1$2$3$5$6$7",$value["value"]);
									// if numeric, do parseFloat
									$out .= "
										if (this.{$value["column"]} && jQuery.isNumeric(this.{$value["column"]}.toString().replace(/[-: ]/g,'')))
											this.{$value["column"]} = parseFloat(this.{$value["column"]}.toString().replace(/[-: ]/g,''));

										if (this.{$value["column"]} {$value["op"]} {$value["value"]})
										{
											if ('".$value["class"]."' != '')
											{
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').addClass('".$value["class"]."');
											}
											else if (\"".$value["css"]."\" != '')
											{
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').css('background-image','revert').css({{$value["css"]}});
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+') a').css('background-image','revert').css({{$value["css"]}});
											}
											else if ('".$value["cellclass"]."' != '')
											{
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').css('background-image','revert');
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+') td[aria-describedby={$grid_id}_{$value["target"]}]').addClass('".$value["cellclass"]."');
											}
											else if (\"".$value["cellcss"]."\" != '')
											{
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+')').css('background-image','revert');
												jQuery('#$grid_id,#{$grid_id}_frozen').find('tr.jqgrow:eq('+i+') td[aria-describedby={$grid_id}_{$value["target"]}]').css('background','revert').css({{$value["cellcss"]}});
											}
										}";
								}
								// column formatting with css style
								else if (empty($value["op"]) && !empty($value["column"]) && !empty($value["css"]))
								{
									$out .= "
										{
											if (jQuery.browser.msie)
												jQuery('#$grid_id,#{$grid_id}_frozen').find('td[aria-describedby={$grid_id}_{$value["column"]}]').css('background','revert').css({{$value["css"]}});
											else
												jQuery('#$grid_id,#{$grid_id}_frozen').find('td[aria-describedby={$grid_id}_{$value["column"]}]').removeClass('.ui-widget-content').css({{$value["css"]}});
										}";
								}
								// column formatting with css class
								else if (empty($value["op"]) && !empty($value["column"]) && !empty($value["class"]))
								{
									$out .= "
										{
											jQuery('#$grid_id,#{$grid_id}_frozen').find('td[aria-describedby={$grid_id}_{$value["column"]}]').css('background-image','revert');
											jQuery('#$grid_id,#{$grid_id}_frozen').find('td[aria-describedby={$grid_id}_{$value["column"]}]').addClass('".$value["class"]."');
										}";
								}
							}
						}
						### P-END ###

			$out .= "});";

			### P ###
			if ($this->options["tooltip"] === true)
			{
				// tooltip
				$out .= "setTimeout(function(){fx_tooltip_init();},200);";
			}

			### P ###
			if ($this->options["grouping"] === true)
			{
				// adjust alignment of header/data
				$out .= "
				var colModel = jQuery('#{$grid_id}').jqGrid('getGridParam', 'colModel'); 
				if (colModel) {
					var c = colModel.length;
					jQuery(colModel).each(function(i) { if (colModel[i]['hidden'] == true) c--; });
					jQuery('#{$grid_id} .jqgroup > td').attr('colspan',c);
				}			
				";
			}

			### P ###
			if (is_array($this->options["sortrows"]))
			{
				// adjust alignment of header/data
				$out .= "
					jQuery('#$grid_id').jqGrid('sortableRows', {
						handle: '.ui-sorticon',
						update: function(event, ui) { 
							var grid = '$grid_id';
							myData = {};
							myData.sequence = jQuery('#'+grid).getDataIDs().join();
							myData.grid_id = grid;
							myData.page = jQuery('#'+grid).jqGrid('getGridParam', 'page');
							myData.oper = 'sortrows';
							jQuery.ajax({
								url: jQuery('#'+grid).jqGrid('getGridParam', 'url'),
								dataType: 'json',
								data: myData,
								type: 'POST',
								error: function(res, status) {
									try
									{
										jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,'<div class=\"ui-state-error\">'+ res.responseText +'</div>',
																	jQuery.jgrid.edit.bClose,{buttonalign:'right'});
					
										jQuery('#info_dialog').abscenter();
									}
									catch(e) { alert(res.status+' : '+res.statusText+'. Status: '+status); }
								},
								success: function( data ) {
								
									console.log('sort order changed')
								}
							});
						}
					});
				";
			}

			// move inline add icons at start (if exist)
			$out .= "
			jQuery('#pg_{$grid_id}_pager td[id$=ilcancel]').parent('tr').prepend(jQuery('#pg_{$grid_id}_pager td[id$=ilcancel]'));
			jQuery('#pg_{$grid_id}_pager td[id$=ilsave]').parent('tr').prepend(jQuery('#pg_{$grid_id}_pager td[id$=ilsave]'));
			jQuery('#pg_{$grid_id}_pager td[id$=iledit]').parent('tr').prepend(jQuery('#pg_{$grid_id}_pager td[id$=iledit]'));
			jQuery('#pg_{$grid_id}_pager td[id$=iladd]').parent('tr').prepend(jQuery('#pg_{$grid_id}_pager td[id$=iladd]'));

			// show row effect change
			jQuery('.ui-jqgrid table#{$grid_id} tr.jqgrow td').css({'opacity':1});
			";

			// obseleted now
			if (!empty($this->events["js_on_load_complete"]))
			{
				$out .= "if (typeof({$this->events["js_on_load_complete"]}) != 'undefined') {$this->events["js_on_load_complete"]}(ids);";
			}

			// chain 'loadComplete' function with base working
			if (!empty($this->options["loadComplete"]))
			{
				$out .= "var fx = ".$this->options["loadComplete"]."; fx(ids);";
				unset($this->options["loadComplete"]);
			}

		// closing of load complete events
		$out .= "}";

		// closing of param list
		$out .= "}";

		// Navigational grid params
		if (!isset($this->navgrid["param"]))
		{
			// remove edit dialog for celledit (excelview) - was conflicting
			if ($this->options["cellEdit"] === true)
				$this->actions["edit"] = false;

			$this->navgrid["param"]["edit"] = ($this->actions["edit"] === false) ? false:true;
			$this->navgrid["param"]["add"] = ($this->actions["add"] === false) ? false:true;
			$this->navgrid["param"]["del"] = ($this->actions["delete"] === false) ? false:true;
			$this->navgrid["param"]["view"] = ($this->actions["view"] === false) ? false:true;
			$this->navgrid["param"]["refresh"] = ($this->actions["refresh"] === false) ? false:true;
			### P ### -- turn false
			$this->navgrid["param"]["search"] = ($this->actions["search"] === false) ? false : true;

			// fix for del and delete text
			if (!empty($this->navgrid["param"]["delete"]))
				$this->navgrid["param"]["del"] = $this->navgrid["param"]["delete"];
		}

		// Generate HTML/JS code
		ob_start();
		?>
			<?php if (strstr($_SERVER["SERVER_NAME"],"gridphp.com") !== false) { ?>
				<script src="https://browser.sentry-cdn.com/4.0.6/bundle.min.js" crossorigin="anonymous"></script>
				<script>Sentry.init({ dsn: 'https://100e63dd4c5f46a5b1d124b4bd02945b@sentry.io/1239394' });</script>
			<?php } else if (false && strstr($_SERVER["SERVER_NAME"],"jqgrid") !== false) { ?>
				<script src="https://browser.sentry-cdn.com/4.0.6/bundle.min.js" crossorigin="anonymous"></script>
				<script>Sentry.init({ dsn: 'https://48810ead3d874930b7856b131090cc95@sentry.io/1294216' });</script>
			<?php } ?>
			<table id="<?php echo $grid_id?>"></table>
			<div id="<?php echo $grid_id."_pager"?>"></div>
			<style>
			.ui-jqgrid table#<?php echo $grid_id?> > tbody > tr.jqgrow td { height:<?php echo $this->options["rowheight"] ?>px; }
			<?php if ($this->options["roweffect"]) { ?>
			.ui-jqgrid table#<?php echo $grid_id?> tr.jqgrow td {
				-webkit-transition: height 0.2s, opacity 0.3s;
				transition: height 0.2s, opacity 0.3s;
				opacity: 0;
			}
			* { transition: background-color 0.1s ease, color 0s ease; }
			<?php } ?>

			<?php if ($this->options["height"] === "auto" || $this->options["height"] === "100%") { ?>
			.ui-jqgrid > #gview_<?php echo $grid_id?> > .ui-jqgrid-bdiv {
				overflow-y: hidden !important;
			}
			<?php } ?>

			<?php if ($this->options["cellEdit"] == true) { ?>
			.ui-jqgrid table#<?php echo $grid_id?> tr.ui-row-ltr td[role="gridcell"] { border-right-style:solid !important; }
			.ui-jqgrid table#<?php echo $grid_id?> tr.ui-row-rtl td[role="gridcell"] { border-left-style:solid !important; }
			<?php } ?>

			</style>
			<script>
			
			<?php ### F ### watermark for free ?>
			// setTimeout(() => {
			// 	if (!jQuery("body").data("watermarkInjection"))
			// 	{
			// 		jQuery("<style type='text/css'> .ui-jqgrid-btable > tbody { background-image: url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' version='1.1' height='120px' width='120px'><text transform='translate(20, 100) rotate(-45)' fill='rgb(245,45,45)' font-size='20'>Free Evaluation</text></svg>\") !important; } </style>").appendTo("head");
			// 		jQuery("<style type='text/css'> .water-row-ltr{ background: "+jQuery(".ui-row-ltr").css("background").replace("rgb","rgba").replace(")",",0.85)")+"; background-image: url();} </style>").appendTo("head");
			// 		jQuery("<style type='text/css'> .water-alt-rows{ background: "+jQuery(".ui-alt-rows").css("background").replace("rgb","rgba").replace(")",",0.85)")+"; background-image: url();} </style>").appendTo("head");
			// 		jQuery("body").data("watermarkInjection",true);
			// 	}
			// }, 2000);
			// setTimeout(() => {
			// 	jQuery(".ui-row-ltr").addClass("water-row-ltr");
			// 	jQuery(".ui-alt-rows").addClass("water-alt-rows");
			// }, 5000);
			<?php ### F-End ### ?>

			// wordpress 6.4.3 integration fix of using html comments
			<!--
			// dom ready callback
			if (!grid_init)
			{
				function grid_init(fn) 
				{
					/*
					// disturbing rendering of grid
					if (typeof(jQuery) != "undefined" && typeof(jQuery.ui) != "undefined")
					{
						fn();
					}
					else
					*/ 
					// alternate of window load
					if (document.addEventListener)
					{
						document.addEventListener("DOMContentLoaded", function(event) { 
							fn();
						});
					}
					else if (document.attachEvent) 
					{
						document.attachEvent("onreadystatechange", function(){
							if ( document.readyState === "complete" ) {
								fn();
							}
						});
					}
				}
			}

			var fx_ajax_file_upload;
			var fx_replace_upload;
			var fx_bulk_update;
			var fx_bulk_unrequire;
			var fx_get_dropdown;
			var fx_reload_dropdown;
			var fx_grid_resize;
			var fx_show_add_form;
			var fx_show_edit_form;
			var fx_show_view_form;
			var fx_show_view_dialog;
			var fx_tooltip_init;
			var fx_toolbar_init;
			var fx_enable_copy;
			var get_querystring_value;
			var link_select2;

			// callback when element in viewport - https://stackoverflow.com/a/66394121/385377
			var on_visible = function (element, callback) {
				new IntersectionObserver((entries, observer) => {
					entries.forEach(entry => {
					if(entry.intersectionRatio > 0) {
						callback(element);
						observer.disconnect();
					}
					});
				}).observe(element);
			}
						
			// namespace for object based functions
			var phpgrid_<?php echo $grid_id?> = {};

			// delay grid creation after dom ready
			var fx_create_grid = function(){
				var phpgrid = jQuery("#<?php echo $grid_id?>");
				var phpgrid_pager = jQuery("#<?php echo $grid_id."_pager"?>");

				// Change JQueryUI plugin names to fix name collision with Bootstrap.
				jQuery.widget.bridge('uibutton', jQuery.ui.button);

				// handler for "..." click on small screen devices
				fx_show_view_dialog = function (o)
				{
					var gid = jQuery(o).closest(".ui-jqgrid").attr("id").replace("gbox_","");
					var phpgrid = jQuery("#"+gid);
					var rowid =  jQuery(o).closest("tr").attr("id");

					var opts = phpgrid.jqGrid('getGridParam', 'view_options');
					phpgrid.jqGrid('viewGridRow', rowid, opts);
				}

				fx_enable_copy = function ()
				{
					jQuery(document).on('click', '.jqgrow td', function()
					{
						element = jQuery(this)[0];//.closest('td').prev('td')[0];
						var selection = window.getSelection();
						var range = document.createRange();
						range.selectNodeContents(element);
						selection.removeAllRanges();
						selection.addRange(range);
						//Losely based on http://stackoverflow.com/a/40734974/7668911
						try {
							var successful = document.execCommand('copy');
							selection = window.getSelection();
							selection.removeAllRanges();

							if(successful) {
								// jQuery('.res').html("Coppied");
							}
							else
							{
								alert("Unable to copy!");
							}
						} catch (err) {
							alert(err);
						}
					});
				}

				fx_tooltip_init = function ()
				{
					var is_mobile = false;
					if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) )
						is_mobile = true

					if (typeof tippy != 'undefined' && !is_mobile)
					{
						jQuery('[title]').each(function(){

							var element = jQuery(this);

							var opts = [];
							opts['arrow'] = true;
							opts['arrowType'] = 'round';
							opts['theme'] = 'light';
							opts['performance'] = false;
							opts['delay'] = [300,100];

							// if ellipsed or icons -> show tooltip (disabled as too slow)
							if (false && element[0].tagName.toLowerCase() == "td" && element.outerWidth() < element[0].scrollWidth)
								tippy(element[0],opts);
							else if (element.hasClass('ui-icon') || element.hasClass('ui-pg-button'))
								tippy(element[0],opts);
							// if header not fully visible commented, enabled on all
							else if (element.hasClass('ui-th-column')) // && element.find('div').outerWidth() < element.find('div')[0].scrollWidth)
								tippy(element[0],opts);
							else
							{
								// commented to show default tooltips as ellipses disabled now
								/* element.removeAttr('title');
								var t = tippy(element[0],opts);
								if (t.selector._tippy)
									t.selector._tippy.destroy();
								element.removeAttr('title'); */
							}

							// if chrome remove action icon tooltip as displaced
							if (jQuery.browser.opr && element.hasClass('ui-custom-icon')){
								var t = tippy(element[0],opts);
								t.selector._tippy.destroy();
							}
						});
					}
				}

				get_querystring_value = function (key)
				{
					return unescape(window.location.search.replace(new RegExp('^(?:.*[&\\?]' + escape(key).replace(/[\.\+\*]/g, '\\$&') + '(?:\\=([^&]*))?)?.*$', 'i'), '$1'));
				}

				link_select2 = function (id)
				{
					if(!jQuery().select2) return;

					jQuery('select[name='+id+'].editable, select[id='+id+']').select2({
																			onSelect: function() { jQuery(this).trigger('change'); }
																		});

					// required to get focus on select2 typing instead of first input of form
					jQuery(document).unbind('keypress').unbind('keydown');
				}

				<?php echo $this->render_js($grid_id,$out);?>

			};

			// subgrid need just fx call, js/css already loaded
			<?php if (isset($_REQUEST["subgrid"]) || ( isset($_REQUEST["oper"]) && $_REQUEST["oper"] == "ajaxload")) { ?>
				fx_create_grid();
			<?php } else {  ?>
				grid_init(fx_create_grid);
			<?php } ?>
			//-->
			</script>
		<?php

		// if reloading complete grid in a div via ajax
		if ($_GET["oper"]=="ajaxload")
		{
			echo ob_get_clean();
			die;
		}

		// set old error handler
		error_reporting($old_error_reporting);

		// minify JS output
		if (file_exists(dirname(__FILE__)."/jsmin.php"))
		{
			include_once(dirname(__FILE__)."/jsmin.php");
			return JSMIN::minify( ob_get_clean() );
		}
		else
			return ob_get_clean();
	}

	/**
	 * JS code related to grid rendering
	 */
	function render_js($grid_id,$out)
	{
	?>
		var lastSel;

		// hotkeys
		<?php if ($this->options["hotkeys"] !== false) { ?>
		jQuery(document).bind('keydown', 'e', function assets() { jQuery('#edit_<?php echo $grid_id ?>',".ui-jqgrid:first").click(); return false; });
		jQuery(document).bind('keydown', 'a', function assets() { jQuery('#add_<?php echo $grid_id ?>',".ui-jqgrid:first").click(); return false; });
		jQuery(document).bind('keydown', 'd', function assets() { jQuery('#del_<?php echo $grid_id ?>',".ui-jqgrid:first").click(); return false; });
		jQuery(document).bind('keydown', 'v', function assets() { jQuery('#view_<?php echo $grid_id ?>',".ui-jqgrid:first").click(); return false; });
		<?php } ?>

		<?php ### P ### ?>
		fx_show_add_form = function(grid_id, container, redirect, extra_opts)
		{
			redirect = redirect || true;
			var opts = jQuery.extend( extra_opts, {
				modal: false,
				jqModal: false,
				width: '100%',
				onClose: function () {
					return false; // don't allow to close the form
				},
				afterSubmit: function (response) {
					if(response.status == 200)
					{
						fx_success_msg('Record Added',1);

						// redirect to listing
						if (redirect)
							location.href = location.href.replace('form=add','');

						return [true,''];
					}
				}
			} );

			jQuery('#'+grid_id).jqGrid('editGridRow', 'new', opts);

			if (!container)
				jQuery('#gbox_'+grid_id).replaceWith(jQuery('#editmod'+grid_id));
			else
				jQuery('#'+container).replaceWith(jQuery('#editmod'+grid_id));

			// replace grid with add form
			jQuery('#editmod'+grid_id).css('position','initial');

			// hide overlay
			jQuery('#lui_'+grid_id).css('display','none');

			// remove cancel
			jQuery('#editmod'+grid_id+' .EditButton #cData').hide();
			jQuery('#editmod'+grid_id+' .ui-jqdialog-titlebar-close').hide();

			// adjust submit and align left
			jQuery('#editmod'+grid_id+' .navButton .fm-button').css('visibility','hidden').show();
			jQuery('#editmod'+grid_id+' .EditButton').css('text-align','inherit');
			jQuery('#editmod'+grid_id+' .EditButton').css('padding','10px 6px');
			jQuery('#editmod'+grid_id+' .CaptionTD').css('width','30%');
			jQuery('#editmod'+grid_id+' .navButton').css('width','30%');
			jQuery('#editmod'+grid_id+' .jqResize').hide();

			return;
		}

		<?php ### P ### ?>
		fx_show_view_form = function(grid_id, container, redirect, extra_opts)
		{
			redirect = redirect || true;
			var opts = jQuery.extend( extra_opts, {
				modal: false,
				jqModal: false,
				width: '100%',
				onClose: function () {
					// redirect to listing
					if (redirect)
						location.href = location.href.replace(/\??form=view&rid=[a-zA-Z0-9]+/,'');
				},
				beforeShowForm: function () {
					// inside dialog scroll
					var h = jQuery(window).height();
					jQuery("form.FormGrid").css("maxHeight", h);
				}
			});

			var rowid = get_querystring_value('rid');

			// check if wrong id
			var rowid = get_querystring_value('rid');
			if (jQuery.isEmptyObject(jQuery('#'+grid_id).jqGrid('getRowData',rowid)))
			{
				jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,'<div class=\"ui-state-error\">'+ jQuery.jgrid.errors.norecords +'</div>',
														jQuery.jgrid.edit.bClose,{buttonalign:'right'});
				jQuery('#info_dialog').abscenter();
				return;
			}

			jQuery('#'+grid_id).jqGrid('viewGridRow', rowid, opts);

			if (!container)
				jQuery('#gbox_'+grid_id).replaceWith(jQuery('#viewmod'+grid_id));
			else
				jQuery('#'+container).replaceWith(jQuery('#viewmod'+grid_id));

			// replace grid with add form
			jQuery('#viewmod'+grid_id).css('position','initial');

			// hide overlay
			jQuery('#lui_'+grid_id).css('display','none');

			if (!redirect) 
			{
				// remove cancel
				jQuery('#viewmod'+grid_id+' .EditButton #cData').hide();
				jQuery('#viewmod'+grid_id+' .ui-jqdialog-titlebar-close').hide();
				jQuery('#viewmod'+grid_id+' #Act_Buttons').hide();
			}

			// adjust submit and align left
			jQuery('#viewmod'+grid_id+' .navButton .fm-button').css('visibility','hidden').show();
			jQuery('#viewmod'+grid_id+' .EditButton').css('text-align','inherit');
			jQuery('#viewmod'+grid_id+' .EditButton').css('padding','10px 6px');
			jQuery('#viewmod'+grid_id+' .CaptionTD').css('width','30%');
			jQuery('#viewmod'+grid_id+' .navButton').css('width','30%');
			jQuery('#viewmod'+grid_id+' .ui-jqdialog-titlebar-close').hide();
			jQuery('#viewmod'+grid_id+' .jqResize').hide();

			return;
		}

		<?php ### P ### ?>
		fx_show_edit_form = function(grid_id, container, redirect, extra_opts)
		{
			redirect = redirect || true;
			var opts = jQuery.extend( extra_opts, {
				modal: false,
				jqModal: false,
				width: '100%',
				onClose: function () {
					// redirect to listing
					if (redirect)
						location.href = location.href.replace(/\??form=edit&rid=[a-zA-Z0-9]+/,'');
				},
				beforeShowForm: function () {
					// inside dialog scroll
					var h = jQuery(window).height();
					jQuery("form.FormGrid").css("maxHeight", h);
				}
			});

			// check if wrong id
			var rowid = get_querystring_value('rid');
			if (jQuery.isEmptyObject(jQuery('#'+grid_id).jqGrid('getRowData',rowid)))
			{
				jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,'<div class=\"ui-state-error\">'+ jQuery.jgrid.errors.norecords +'</div>',
														jQuery.jgrid.edit.bClose,{buttonalign:'right'});
				jQuery('#info_dialog').abscenter();
				return;
			}
				
			jQuery('#'+grid_id).jqGrid('editGridRow', rowid, opts);

			if (!container)
				jQuery('#gbox_'+grid_id).replaceWith(jQuery('#editmod'+grid_id));
			else
				jQuery('#'+container).replaceWith(jQuery('#editmod'+grid_id));

			// replace grid with add form
			jQuery('#editmod'+grid_id).css('position','initial');

			// hide overlay
			jQuery('#lui_'+grid_id).css('display','none');

			if (!redirect) 
			{
				// remove cancel
				jQuery('#editmod'+grid_id+' .EditButton #cData').hide();
				jQuery('#editmod'+grid_id+' .ui-jqdialog-titlebar-close').hide();
				jQuery('#editmod'+grid_id+' #Act_Buttons').hide();
			}

			// adjust submit and align left
			jQuery('#editmod'+grid_id+' .navButton .fm-button').css('visibility','hidden').show();
			jQuery('#editmod'+grid_id+' .EditButton').css('text-align','inherit');
			jQuery('#editmod'+grid_id+' .EditButton').css('padding','10px 6px');
			jQuery('#editmod'+grid_id+' .CaptionTD').css('width','30%');
			jQuery('#editmod'+grid_id+' .navButton').css('width','30%');
			jQuery('#editmod'+grid_id+' .ui-jqdialog-titlebar-close').hide();
			jQuery('#editmod'+grid_id+' .jqResize').hide();

			return;
		}

		<?php ### P ### ?>
		fx_clone_row = function (grid,id)
		{
			var msg = jQuery.jgrid.edit.cloneData || "Clone selected record?";
			var bCancel = {"text":jQuery.jgrid.edit.bCancel, "onClick":function(){ jQuery.jgrid.hideModal("#info_dialog"); }};
			var bClone = {"text":jQuery.jgrid.nav.clone, "onClick":function(){

				myData = {};
				myData.id = id;
				myData.grid_id = grid;
				myData.oper = 'clone';
				jQuery.ajax({
					url: jQuery("#"+grid).jqGrid('getGridParam', 'url'),
					dataType: "html",
					data: myData,
					type: "POST",
					error: function(res, status) {
						try
						{
							jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,'<div class=\"ui-state-error\">'+ res.responseText +'</div>',
														jQuery.jgrid.edit.bClose,{buttonalign:'right'});

							jQuery('#info_dialog').abscenter();
						}
						catch(e) { alert(res.status+" : "+res.statusText+". Status: "+status); }
					},
					success: function( data ) {
						<?php
						if (!empty($this->options["onAfterClone"])) {
							echo "var fx_save = {$this->options["onAfterClone"]}; fx_save();";
						}
						?>
					}
				});
				jQuery("#"+grid).trigger('reloadGrid',[{page:1}]);

				jQuery.jgrid.hideModal("#info_dialog");
			}};

			jQuery.jgrid.info_dialog("Clone","<div class='' style='padding:5px; text-align:left'>"+msg+"</div>",
									"",{buttonalign:"right", buttons:[bClone,bCancel]});

			jQuery("#info_dialog").abscenter();
		};

		var extra_opts = {};

		<?php ### P ### ?>
		if (typeof(opts) != 'undefined') extra_opts = opts;
		if (typeof(opts_<?php echo $grid_id?>) != 'undefined') extra_opts = opts_<?php echo $grid_id?>;

		// if bootstrap, increase subgrid icon width
		if (jQuery("link[href*='ui.jqgrid.bs']").length)
			extra_opts["subGridWidth"] = "33px";

		<?php
		// chain 'loadComplete' function with base working
		if (!empty($this->options["beforeGrid"]))
		{
			echo "(".$this->options["beforeGrid"].")();";
			unset($this->options["before_grid"]);
		}
		?>

		<?php if (empty($this->pivot_options)) { ?>
		var grid_<?php echo $grid_id?> = jQuery("#<?php echo $grid_id?>").jqGrid( jQuery.extend(<?php echo $out?>, extra_opts ) );
		<?php } else { ?>
		var grid_<?php echo $grid_id?> = jQuery("#<?php echo $grid_id?>").jqGrid('jqPivot','?grid_id=<?php echo $grid_id?>&_search=false&rows=99999&jqgrid_page=1', <?php echo json_encode($this->pivot_options) ?>, jQuery.extend(<?php echo $out?>, extra_opts ));
		return;
		<?php } ?>


		<?php ### P ### ?>
		// if .php?form=add, show add form directly - remove grid list
		if (get_querystring_value('form'))
			jQuery('#gbox_<?php echo $grid_id?>').hide();

		jQuery("#<?php echo $grid_id?>").jqGrid('navGrid','#<?php echo $grid_id."_pager"?>',
				<?php echo json_encode_jsfunc($this->navgrid["param"])?>,
				<?php echo json_encode_jsfunc($this->options["edit_options"])?>,
				<?php echo json_encode_jsfunc($this->options["add_options"])?>,
				<?php echo json_encode_jsfunc($this->options["delete_options"])?>,
				<?php echo json_encode_jsfunc($this->options["search_options"])?>,
				<?php echo json_encode_jsfunc($this->options["view_options"])?>
				);

		// Set grouping header using callGridMethod
		<?php
		if (!empty($this->group_header))
		{
			foreach($this->group_header as $g)
			{
				?>
				jQuery("#<?php echo $grid_id?>").jqGrid("setGroupHeaders", <?php echo json_encode_jsfunc($g)?>);
				<?php
			}
		}
		?>

		<?php ### P ### ?>
		<?php if ($this->actions["inlineadd"] !== false || $this->actions["inline"] === true) {

			// after save callback for inline add/edit
			$on_after_save = "";
			if (!empty($this->options["onAfterSave"]))
				$on_after_save = "var fx_save_inline = {$this->options["onAfterSave"]}; fx_save_inline();";

			$inline_param = array();
			$inline_param["add"] = ($this->actions["add"] === false) ? "false":"true";
			$inline_param["edit"] = ($this->actions["edit"] === false) ? "false":"true";
			$inline_param["save"] = ($this->actions["add"] === false && $this->actions["edit"] === false) ? "false":"true";
			$inline_param["cancel"] = ($this->actions["add"] === false && $this->actions["edit"] === false) ? "false":"true";
		?>
		jQuery('#<?php echo $grid_id?>').jqGrid('inlineNav','#<?php echo $grid_id."_pager"?>',{	"add":<?php echo $inline_param["add"] ?>,
																								"edit":<?php echo $inline_param["edit"] ?>,
																								"save":<?php echo $inline_param["save"] ?>,
																								"cancel":<?php echo $inline_param["cancel"] ?>,
		"addParams":{
						"addRowParams":
						{
							keys: true,
							"oneditfunc": function(id)
							{
								// remove text inside anchor (text-indent:-999 does not work with fontawesome)
								jQuery("#save_row_<?php echo $grid_id?>_"+id+" a").html('');

								jQuery("#edit_row_<?php echo $grid_id?>_"+id+" a:first").parent().hide().next().show();
								// jQuery("div.frozen-div, div.frozen-bdiv").hide();

								// focus first editable element
								jQuery(".editable:visible:first").focus();

								// show row effect change
								jQuery('.ui-jqgrid table#<?php echo $grid_id?> tr.jqgrow td').css({'opacity':1});
							},
							"afterrestorefunc": function(id)
							{
								jQuery("#save_row_<?php echo $grid_id?>_"+id+" a:last").parent().hide().prev().show();
								// jQuery(".frozen-div, .frozen-bdiv").show();
							},
							"aftersavefunc":function (id, res)
							{
								// set new id for row (in case of load session row id 'id')
								// http://stackoverflow.com/questions/15345391/displaying-jqg1-instead-of-id-returned-from-database-on-inline-add

								var result = jQuery.parseJSON(res.responseText);
								jQuery(this).jqGrid("setCell", id, "id", result.id);

								// but reload grid, to work properly
								jQuery('#<?php echo $grid_id?>').trigger("reloadGrid",[{jqgrid_page:1}]);

								<?php echo $on_after_save ?>
							},
							"errorfunc": function(id,res)
							{
								jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,'<div class=\"ui-state-error\">'+ res.responseText +'</div>',
															jQuery.jgrid.edit.bClose,{buttonalign:'right'});
							}
						}
		}
		,"editParams":{
							keys: true,
							"aftersavefunc":function (id, res)
							{
								// jQuery(".frozen-div, .frozen-bdiv").show();
								// but reload grid, to work properly
								jQuery('#<?php echo $grid_id?>').trigger("reloadGrid",[{jqgrid_page:1}]);

								<?php echo $on_after_save ?>
							},
							"errorfunc": function(id,res)
							{
								jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,'<div class=\"ui-state-error\">'+ res.responseText +'</div>',
															jQuery.jgrid.edit.bClose,{buttonalign:'right'});
								jQuery('#<?php echo $grid_id?>').trigger("reloadGrid",[{jqgrid_page:1}]);
							},
							"oneditfunc": function(id)
							{
								jQuery("#edit_row_<?php echo $grid_id?>_"+id+" a:first").parent().hide().next().show();
								// jQuery("div.frozen-div, div.frozen-bdiv").hide();

								// focus first editable element
								jQuery(".editable:visible:first").focus();

								// show row effect change
								jQuery('.ui-jqgrid table#<?php echo $grid_id?> tr.jqgrow td').css({'opacity':1});

								// impose readonly logic code
								<?php echo $this->internal["str_inline_access"] ?>
							},
							"afterrestorefunc": function(id)
							{
								jQuery("#save_row_<?php echo $grid_id?>_"+id+" a:last").parent().hide().prev().show();
								// jQuery(".frozen-div, .frozen-bdiv").show();
							}
		}});
		<?php } ?>

		<?php if ($this->actions["autofilter"] !== false) { ?>
		// auto filter with contains search

		var autofilter_opts = <?php echo json_encode_jsfunc($this->options["autofilter_options"])?> || {};
		jQuery("#<?php echo $grid_id?>").jqGrid('filterToolbar',jQuery.extend({stringResult: true,searchOnEnter : false, autosearchDelay:500, defaultSearch:'cn', 
																	beforeSearch: function(){

																	// daterange support for BT operator
																	var grid = jQuery("#<?php echo $grid_id?>");
														            var postData = grid.jqGrid('getGridParam','postData');
														            var searchData = jQuery.parseJSON(postData.filters);
																	for (var iRule=0; iRule < searchData.rules.length; iRule++) {
																		if (searchData.rules[iRule].op == "bt")
																		{
																			var vals = JSON.parse(searchData.rules[iRule].data);
																			searchData.rules.push({field:searchData.rules[iRule].field,op:"ge",data:vals.start});
																			searchData.rules.push({field:searchData.rules[iRule].field,op:"le",data:vals.end});
																			searchData.rules.splice(iRule,1);
																		}
																	}
																	grid.jqGrid('setGridParam',{'postData': { 'filters': JSON.stringify(searchData) } } );
																	return false;

																	}
																},
																autofilter_opts
																));
		// jQuery("#<?php echo $grid_id?>").jqGrid('filterToolbar',{stringResult: true,searchOnEnter : false, searchOperators : true});

		<?php } ?>

		<?php if ($this->actions["showhidecolumns"] !== false) { ?>
		// show/hide columns
		var select_cols_text = jQuery.jgrid.nav.showhidecol || 'Select Columns to display';
		var cols_text = jQuery.jgrid.nav.columns || 'Columns';
		jQuery("#<?php echo $grid_id?>").jqGrid('navButtonAdd',"#<?php echo $grid_id."_pager"?>",
			{
			caption:cols_text, 
			title:select_cols_text, 
			buttonicon :'ui-icon-note',
			onClickButton:function(){
				jQuery("#<?php echo $grid_id?>").jqGrid('columnChooser',
				{
					msel:'multipleSelect', 
					msel_opts: {filter:true,width:'100%',maxHeight:999,keepOpen:true,isOpen:true}, 
					width:'100%', 
					height:'330', 
					modal:true, 
					done:function()
						{ 
							var c = jQuery('#colchooser_<?php echo $grid_id?> select').val(); 
							var colModel = jQuery("#<?php echo $grid_id?>").jqGrid("getGridParam", "colModel"); 
							var str = new Array(), str_hide = new Array();

							if (c.length == colModel.length) 
								str[str.length] = ''; 
							else 
							{
								jQuery(colModel).each(function(i) { 
				
									switch(colModel[i]['name'])
									{
										case 'cb':
										case 'xs_view_dots':
										case 'act':
											return;
									}

									if (colModel[i].hidedlg == true)
										return;

									if (c.indexOf(i.toString()) == -1)
									{
										str_hide[str_hide.length] = colModel[i]['name']; 
									}
								});

								jQuery(c).each(function(i) { 
									str[str.length] = colModel[c[i]]['name']; 
								}); 

								str = str.join(",");
								str_hide = str_hide.join(",");

								Cookies.set('jqgrid_colchooser_<?php echo $grid_id ?>', str); 
								Cookies.set('jqgrid_colchooser_hide_<?php echo $grid_id ?>', str_hide); 

								// if selected all column, defaults to responsive (no cookies)
								if (str_hide == "")
								{
									Cookies.remove('jqgrid_colchooser_<?php echo $grid_id ?>');
									Cookies.remove('jqgrid_colchooser_hide_<?php echo $grid_id ?>');
								}

								jQuery('#<?php echo $grid_id ?>').trigger('resize');
							}
						}, 
					"dialog_opts" : {"width": 320,"minWidth": 320, "height": 450} 
				});

				// show scrollbar if more fields (for resize vertical)
				jQuery('#colchooser_<?php echo $grid_id?>').css('overflow','auto');

				<?php if (!empty($this->group_header)) { ?>
				// show optgroup for group headers in select columns
				setTimeout(function(){
					var groups = <?php echo json_encode($this->group_header); ?>;
					var s = jQuery('#colchooser_<?php echo $grid_id?> select');
					var colModel = jQuery("#<?php echo $grid_id?>").jqGrid("getGridParam", "colModel");
					for(var h in groups[0].groupHeaders)
					{
						var c=1;
						var pass = false;

						var headers = groups[0].groupHeaders[h];
						var col_name = headers.startColumnName;
						var col_group = headers.titleText;
						var col_count = headers.numberOfColumns;

						jQuery('option',s).each(function () {

							if (colModel[jQuery(this).val()].name == col_name || ( pass == true && c <= col_count )) {
								pass = true;
								c++;
								if (jQuery("optgroup[label='"+col_group+"']",s).length == 0)
									jQuery('<optGroup />').attr('label', col_group).appendTo(s);
								jQuery("optgroup[label='"+col_group+"']",s).append(jQuery(this));
								if (c > col_count) pass = false;
							}
						});
					}
					jQuery(s).multipleSelect('refresh');
				},200);
				<?php } ?>

				jQuery("#colchooser_<?php echo $grid_id?>").parent().abscenter();
			}
		});
		<?php } ?>

		<?php ### P ### ?>
		<?php if ($this->actions["bulkedit"] === true) { ?>

		var bulkedit_text = jQuery.jgrid.nav.bulkedit || 'Bulk Edit';
		var bulkeditskip_text = jQuery.jgrid.nav.bulkeditskip || 'Note: Blank fields will be skipped';
		jQuery("#<?php echo $grid_id?>").jqGrid('navButtonAdd',"#<?php echo $grid_id."_pager"?>",jQuery.extend({
				'id'      	   : 'bulkedit_<?php echo $grid_id?>',
				'caption'      : bulkedit_text,
				'buttonicon'   : 'ui-icon-pencil',
				'onClickButton': function()
				{
					var ids = jQuery('#<?php echo $grid_id?>').jqGrid('getGridParam','selarrrow');

					// don't process if nothing is selected
					if (ids.length == 0)
					{
						jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,'<div class=\"ui-state-error\">'+jQuery.jgrid.nav.alerttext+'</div>',
													jQuery.jgrid.edit.bClose,{buttonalign:'right'});
						return;
					}
					// added dummy value to blank dialog fields
					else if (ids.length == 1)
					{
						ids = [ids,-99];
					}

					// save to identify bulk edit dialog
					jQuery('#<?php echo $grid_id?>').data('bulk-edit-count',ids.length);

					<?php
					// remove non bulkedit columns
					foreach($this->options["colModel"] as $c)
					{
						if ($c["bulkedit"] === false)
						{
							?>
							jQuery('#<?php echo $grid_id?>').jqGrid('setColProp','<?php echo $c["name"]?>',{editable:false});
							<?php
						}
					}
					?>

					jQuery('#<?php echo $grid_id?>').jqGrid('editGridRow', ids, <?php echo json_encode_jsfunc($this->options["edit_options"])?>);

					<?php
					// add non bulkedit columns for normal edit
					foreach($this->options["colModel"] as $c)
					{
						if ($c["editable"] === true)
						{
							?>
							jQuery('#<?php echo $grid_id?>').jqGrid('setColProp','<?php echo $c["name"]?>',{editable:true});
							<?php
						}
					}

					// bulkedit afterShowForm function
					if (!empty($this->options["bulkedit_options"]["afterShowForm"]))
					{
						echo "var bulk_fx = ".$this->options["bulkedit_options"]["afterShowForm"]."; bulk_fx();";
					}
					?>

					jQuery('#edithd<?php echo $grid_id?> .ui-jqdialog-title').html(bulkedit_text);
					jQuery('#editmod<?php echo $grid_id?> .binfo').show();
					jQuery('#editmod<?php echo $grid_id?> .bottominfo').html(bulkeditskip_text);

					jQuery('#editmod<?php echo $grid_id?> select').each(function(i){

						// insert empty option
						if ( jQuery(this).children().first().val() == '' )
							jQuery("<option value='-'>- Empty -</option>").insertAfter(jQuery(this).not('.checkbox').children().first());

						// dont append blank option if already exist
						if ( jQuery(this).children().first().val() != '' )
							jQuery(this).prepend("<option value=''>-</option>");

					});

					// set dropdown blank
					jQuery('#editmod<?php echo $grid_id?> select').val('');

					return true;
				},
				'position': 'last'
		},<?php echo json_encode_jsfunc($this->options["bulkedit_options"]);?>));

		// function to un-require required field in bulkedit
		fx_bulk_unrequire = function(list)
		{
			var grid = jQuery("#"+list);

			var cnames = grid.jqGrid('getGridParam','colModel');
			var req_fields = [];
			var checkbox_fields = [];
			var is_bulkedit = false;

			for (var a=0;a < cnames.length;a++)
			{
				var p = grid.jqGrid('getColProp',cnames[a].name);

				if (p.editrules && p.editrules.required)
					req_fields[req_fields.length] = cnames[a].name;

				if (p.edittype && p.edittype.toLowerCase() == 'checkbox')
					checkbox_fields[checkbox_fields.length] = cnames[a].name;

				if (!grid.data('bulk-edit-req-fields-'+list))
					grid.data('bulk-edit-req-fields-'+list,req_fields);
			}

			// if bulk edit
			var count = grid.data('bulk-edit-count');
			grid.data('bulk-edit-count',0);

			// reload required fields
			req_fields = grid.data('bulk-edit-req-fields-'+list);

			if(count > 1)
				is_bulkedit = true;

			for (var a=0;a < req_fields.length;a++)
			{
				grid.jqGrid('setColProp',req_fields[a],{editrules:{'required':!is_bulkedit}});

				// remove required *

				if (is_bulkedit)
					jQuery("#tr_"+req_fields[a]+" TD.DataTD font").hide();
				else
					jQuery("#tr_"+req_fields[a]+" TD.DataTD font").show();
			}

			// disable checkbox in bulk edit
			for (var a=0;is_bulkedit && a < checkbox_fields.length;a++)
			{
				var vals = [];
				var p = grid.jqGrid('getColProp',checkbox_fields[a]);
				if (p.editoptions.value)
					vals = p.editoptions.value.split(":");

				// relace with dropdown
				str = '<select class="FormElement ui-widget-content ui-corner-all checkbox" onchange="jQuery(\'#'+checkbox_fields[a]+'\').val(this.value);" name="'+checkbox_fields[a]+'"><option value="'+vals[0]+'">Checked</option><option value="'+vals[1]+'">Un-Checked</option> </select>';
				jQuery("#tr_"+checkbox_fields[a]+" td.DataTD input").replaceWith(str);

				// replace with radio
				// str = '<input onclick="jQuery(\'#'+checkbox_fields[a]+'\').val(this.value);" type="radio" name="rd_'+checkbox_fields[a]+'" checked="checked" value="NULL"> Unchanged <input type="radio" onclick="jQuery(\'#'+checkbox_fields[a]+'\').val(this.value);" name="rd_'+checkbox_fields[a]+'" value="'+vals[0]+'"> Checked <input onclick="jQuery(\'#'+checkbox_fields[a]+'\').val(this.value);" type="radio" name="rd_'+checkbox_fields[a]+'" value="'+vals[1]+'"> Unchecked';
				// jQuery("#tr_"+checkbox_fields[a]+" td.DataTD").append(str);
				// jQuery("#"+checkbox_fields[a]).attr("checked","checked").val("NULL").hide();

				// remove checkbox
				// jQuery("#tr_"+checkbox_fields[a]).remove();
			}
		}
		<?php } ?>

		<?php ### P ### ?>
		<?php if (isset($this->actions["clone"]) && $this->actions["clone"] === true) { ?>
		// Clone button

		var clone_text = jQuery.jgrid.nav.clone || 'Clone';
		jQuery("#<?php echo $grid_id?>").jqGrid('navButtonAdd',"#<?php echo $grid_id."_pager"?>",jQuery.extend({caption:"",title:clone_text, buttonicon :'ui-icon-copy',
			onClickButton:function(){

				// for multiselect clone feature
				var is_multi_select = jQuery("#<?php echo $grid_id?>").jqGrid('getGridParam','multiselect');
				if (is_multi_select)
				{
					var selr = jQuery("#<?php echo $grid_id?>").jqGrid('getGridParam','selarrrow');
					selr = selr.join(',');
				}
				else
					var selr = jQuery("#<?php echo $grid_id?>").jqGrid('getGridParam','selrow');

				if (!selr)
				{
					var alertIDs = {themodal:'alertmod',modalhead:'alerthd',modalcontent:'alertcnt'};
					if (jQuery("#"+alertIDs.themodal).html() === null) {
					    jQuery.jgrid.createModal(alertIDs,"<div>"+jQuery.jgrid.nav.alerttext+
					        "</div><span tabindex='0'><span tabindex='-1' id='jqg_alrt'></span></span>",
					        {gbox:"#gbox_"+jQuery.jgrid.jqID(this.p.id),jqModal:true,drag:true,resize:true,
					        caption:jQuery.jgrid.nav.alertcap,
					        top:100,left:100,width:200,height: 'auto',closeOnEscape:true,
					        zIndex: null},"","",true);
					}
					jQuery.jgrid.viewModal("#"+alertIDs.themodal,
					    {gbox:"#gbox_"+jQuery.jgrid.jqID(this.p.id),jqm:true});
					jQuery("#jqg_alrt").focus();
					return;
				}

				fx_clone_row("<?php echo $grid_id?>",selr);
			}
		},<?php echo json_encode_jsfunc($this->options["clone_options"]);?>));
		<?php } ?>

		<?php ### P ### ?>
		<?php if ($this->actions["import"] === true) { ?>
		// Export to what is defined in file
		var import_text = jQuery.jgrid.nav["import"] || 'Import';
		jQuery("#<?php echo $grid_id?>").jqGrid('navButtonAdd',"#<?php echo $grid_id."_pager"?>",{id:'import_<?php echo $grid_id?>', caption:import_text,title:import_text, buttonicon :'ui-icon-file-txt',
			onClickButton:function(){
				jqgrid_process_import(import_text);
			}
		});

		function jqgrid_process_import(title)
		{
			var url;
			var prefix = ("<?php echo $this->options["url"]?>".indexOf("?") != -1) ? "&" : "?" ;
			url = "<?php echo $this->options["url"]?>" + prefix + "import=1&step=1";

			var iframe = jQuery('<iframe width="100%" height="98%" src="'+url+'" frameborder="0" marginwidth="0" marginheight="0" allowfullscreen></iframe>');
			var dialog = jQuery("<div></div>").append(iframe).appendTo("body").dialog({
				autoOpen: false,
				modal: true,
				title: title,
				resizable: false,
				width: "50%",
				close: function () {
					iframe.attr("src", url);
					setTimeout("jQuery('#<?php echo $grid_id?>').trigger('reloadGrid',[{page:1}])",500);
				}
			});

			var h = jQuery(window).height() * 0.9;
			dialog.dialog("option", "height", h);
			dialog.dialog("option", "title", title).dialog("open");
		}
		<?php } ?>

		<?php if ($this->actions["export"] === true || $this->actions["export_excel"] === true || $this->actions["export_pdf"] === true || $this->actions["export_csv"] === true || $this->actions["export_html"] === true || $this->actions["export_word"] === true) {
		$order_by = "&sidx=".$this->options["sortname"]."&sord=".$this->options["sortorder"]."&rows=".$this->options["rowNum"];
		?>
		function jqgrid_process_export(type)
		{
				type = type || "";
				var detail_grid_params = jQuery("#<?php echo $grid_id?>").data('jqgrid_detail_grid_params');
				detail_grid_params = detail_grid_params || "";

				var prefix = ("<?php echo $this->options["url"]?>".indexOf("?") != -1) ? "&" : "?" ;
				window.open("<?php echo $this->options["url"]?>" + prefix + "export=1&jqgrid_page=1&export_type="+type+"<?php echo $order_by?>"+detail_grid_params);
		}
		<?php } ?>

		<?php ### P ### ?>
		<?php if ($this->actions["export"] === true) { ?>
		// Export to what is defined in file
		var export_text = jQuery.jgrid.nav["export"] || 'Export';
		jQuery("#<?php echo $grid_id?>").jqGrid('navButtonAdd',"#<?php echo $grid_id."_pager"?>",{caption:export_text,title:export_text, buttonicon :'ui-icon-extlink',
			onClickButton:function(){
				jqgrid_process_export();
			}
		});
		<?php } ?>

		<?php ### P ### ?>
		<?php if (isset($this->actions["export_excel"]) && $this->actions["export_excel"] === true) { ?>
		// Export to excel
		jQuery("#<?php echo $grid_id?>").jqGrid('navButtonAdd',"#<?php echo $grid_id."_pager"?>",{caption:"Excel",title:"Excel", buttonicon :'ui-icon-extlink',
			onClickButton:function(){
				jqgrid_process_export('excel');
			}
		});
		<?php } ?>

		<?php ### P ### ?>
		<?php if (isset($this->actions["export_pdf"]) && $this->actions["export_pdf"] === true) { ?>
		// Export to pdf
		jQuery("#<?php echo $grid_id?>").jqGrid('navButtonAdd',"#<?php echo $grid_id."_pager"?>",{caption:"PDF",title:"PDF", buttonicon :'ui-icon-extlink',
			onClickButton:function(){
				jqgrid_process_export('pdf');
			}
		});
		<?php } ?>

		<?php ### P ### ?>
		<?php if (isset($this->actions["export_csv"]) && $this->actions["export_csv"] === true) { ?>
		// Export to csv
		jQuery("#<?php echo $grid_id?>").jqGrid('navButtonAdd',"#<?php echo $grid_id."_pager"?>",{caption:"CSV",title:"CSV", buttonicon :'ui-icon-extlink',
			onClickButton:function(){
				jqgrid_process_export('csv');
			}
		});
		<?php } ?>

		<?php ### P ### ?>
		<?php if (isset($this->actions["export_html"]) && $this->actions["export_html"] === true) { ?>
		// Export to html
		jQuery("#<?php echo $grid_id?>").jqGrid('navButtonAdd',"#<?php echo $grid_id."_pager"?>",{caption:"HTML",title:"HTML", buttonicon :'ui-icon-extlink',
			onClickButton:function(){
				jqgrid_process_export('html');
			}
		});
		<?php } ?>

		<?php ### P ### ?>
		<?php if (isset($this->actions["export_word"]) && $this->actions["export_word"] === true) { ?>
		// Export to word
		jQuery("#<?php echo $grid_id?>").jqGrid('navButtonAdd',"#<?php echo $grid_id."_pager"?>",{caption:"Word",title:"Word", buttonicon :'ui-icon-extlink',
			onClickButton:function(){
				jqgrid_process_export('word');
			}
		});
		<?php } ?>

		<?php ### P ### ?>
		function link_multiselect(elem)
		{
			var optLen = jQuery(elem).find("option").length;

			var $elem = jQuery(elem), id = elem.id,
				inToolbar = typeof id === "string" && id.substr(0, 3) === "gs_",
				options = {
					selectedList: 1,
					height: ((optLen>10)? jQuery(window).height() * 0.5 :"auto"),
					checkAllText: "All",
					uncheckAllText: "None",
					noneSelectedText: "Any",
					open: function () {
						var $menu = jQuery(elem).find(".ui-multiselect-menu:visible");
						$menu.css({minWidth:'auto'});
						$menu.width( jQuery('button.ui-multiselect').width()-2 );
						return;
					}
				},
				$options = $elem.find("option");

			if (inToolbar) {
				options.minWidth = 'auto';
			}
			$elem.multiselect(options).multiselectfilter({placeholder:''});
			jQuery('button.ui-multiselect span.ui-icon').css({margin:"0px 2px",color:"gray"});
			$elem.siblings('button.ui-multiselect').css({
				width: "100%",
				minWidth: "200px",
				height: "20px",
				fontWeight: "normal",
				backgroundColor: "white",
				color: "black",
				backgroundImage: "inherit",
				borderStyle: "inset",
				marginTop: "1px"
			});

			// fix for search dialog filter typing (2nd time)
			jQuery('.ui-multiselect-filter input').click(function(){
				jQuery(document).unbind('keypress').unbind('keydown').unbind('mousedown');
			});

			// adjust font size as per theme
			jQuery(".ui-multiselect-checkboxes li").css("font-size","inherit");
		};

		<?php ### P ### ?>
		function link_daterange_picker(el,opts)
		{
			// http://tamble.github.io/jquery-ui-daterangepicker/
			jQuery(function() {

				// fix for bootstrap5
				if(jQuery.fn.button.noConflict) {
					var bootstrapButton = jQuery.fn.button.noConflict();
					jQuery.fn.buttonBS = bootstrapButton; 
				}
				opts = (typeof(opts) == 'undefined') ? {} : opts;
				jQuery(el).daterangepicker(jQuery.extend({
					'change': function() {
						jQuery("#<?php echo $grid_id?>")[0].triggerToolbar();
					},
					'clear': function() {
						jQuery("#<?php echo $grid_id?>")[0].triggerToolbar();
					},
					'datepickerOptions': {'numberOfMonths':2, 'changeYear':true, 'maxDate':null}
				}, opts)
				);

				jQuery.jgrid.search.odata.push({oper:'bt', text:'between'})
			});
		}

		<?php ### P ### ?>
		function link_date_picker(el,fmt,toolbar,opts)
		{
			toolbar = toolbar || 0;
			setTimeout(function(){
				if(jQuery.ui)
				{
					if(jQuery.ui.datepicker)
					{
						// dont show dateicon if readonly or hidden
						if (jQuery(el).css("display") == "none") return;

						opts = (typeof(opts) == 'undefined') ? {} : opts;

						if (toolbar == 0)
						{
							jQuery(el)[0].style.setProperty('width', 'calc(88% - 30px)', 'important');
							jQuery(el).after(' <button>Calendar</button>').next()
							.uibutton({icons:{primary: 'ui-icon-calendar'}, text:false})
							.css({'font-size':'69%', 'margin-left':'2px','width':'20px', 'margin-top':'-2px'})
							.click(function(e){jQuery(el).datepicker('show');return false;});
						}

						jQuery(el).datepicker(
												jQuery.extend(
												{
												"disabled":false,
												"dateFormat":fmt,
										        "changeMonth": true,
										        "changeYear": true,
												"nextText": '',
												"showButtonPanel": true,
												"prevText": '',
												"firstDay": 1,
												"onSelect": function (dateText, inst)
															{
																// if toolbar and not search dialog
																if (toolbar && jQuery(el).closest('.searchFilter').length == 0)
																{
											                    	setTimeout(function () {
											                        jQuery("#<?php echo $grid_id?>")[0].triggerToolbar();
											                    	}, 50);
																}
																jQuery(el).trigger('change');
										                	}
        										},opts)
        									);

						// disable html autocomplete
						jQuery(el).attr("autocomplete", Math.random());

						// focus fix
						jQuery(document).unbind('keypress').unbind('keydown');

					}
				}
			},300);
		}

		<?php ### P ### ?>
		function link_datetime_picker(el,fmt,toolbar,opts)
		{
			setTimeout(function(){
				if(jQuery.ui)
				{
					if(jQuery.ui.datepicker)
					{
						// dont show dateicon if readonly or hidden
						if (jQuery(el).css("display") == "none") return;

						opts = (typeof(opts) == 'undefined') ? {} : opts;
						if (toolbar == 0)
						{
							jQuery(el)[0].style.setProperty('width', 'calc(88% - 30px)', 'important');
							jQuery(el).after(' <button>Calendar</button>').next()
							.uibutton({icons:{primary: 'ui-icon-calendar'}, text:false})
							.css({'font-size':'69%', 'margin-left':'2px', 'margin-top':'-2px'})
							.click(function(e){jQuery(el).datetimepicker('show');return false;});
						}

						jQuery(el).datetimepicker(
													jQuery.extend(
													{
													"disabled":false,
													"dateFormat":fmt,
													"changeMonth": true,
													"changeYear": true,
													"showButtonPanel": true,
													"nextText": '',
													"prevText": '',
													"onSelect": function (dateText, inst)
															{
																// if toolbar and not search dialog
																if (toolbar && jQuery(el).closest('.searchFilter').length == 0)
																{
											                    	setTimeout(function () {
											                        jQuery("#<?php echo $grid_id?>")[0].triggerToolbar();
											                    	}, 50);
																}
																jQuery(el).trigger('change');
										                	}
													}, opts)
												);

						// disable html autocomplete
						jQuery(el).attr("autocomplete", Math.random());
					}
				}
			},100);
		}

		<?php ### P ### ?>
		function link_editor(el)
		{
			// disable for inline edit
			// if (jQuery(el).closest('.jqgrow').length)
				// return;

			setTimeout(function(){
				// remove nbsp; from start of textarea
				if(el.previousSibling) el.parentNode.removeChild(el.previousSibling);

				jQuery(el).parent().css('padding-left','5px');
				jQuery(el).parent().css('white-space','initial');

				var editor = new Jodit(el,{useAceEditor: true});
			    editor.events.on('change', function() {
					editor.setEditorValue();
				});

			},50);
		}

		<?php ### P ### ?>
		fx_replace_upload = function(el,field)
		{
			var str_multiple = '';
			if (jQuery(el).attr("multiple") == "multiple")
				str_multiple = "multiple='multiple'";

			// for dialog, else inline
			if (jQuery(el).closest('.FormGrid').length)
				grid_id = jQuery(el).closest('.FormGrid').attr('id').replace('FrmGrid_','');
			else
				grid_id = jQuery(el).closest('.ui-jqgrid-btable').attr('id');

			grid = jQuery('#'+grid_id);
			grid_url = grid.getGridParam('url');

			// when using form=add option for upload
			if (grid_url == undefined)
				grid_url = location.href.replace('form=add','')+"&grid_id="+grid_id;

			var sel = jQuery("input[name='"+field+"'].editable, input[name='"+field+"'].FormElement");
			
			var request = {};
			request['field'] = field;
			request['file'] = jQuery(sel).val();
			request["oper"] = 'unlink';

			jQuery.ajax({
				url: grid_url,
				dataType: "json",
				data: request,
				type: "POST",
				error: function(res, status) {
				},
				success: function( data ) {
				}
			});

			// replace hidden input text with file upload
			jQuery(el).parent().append("<input "+str_multiple+" id='"+field+"_file' size='10' name='" + field + "_file[]" + "' type='file' onchange='return fx_ajax_file_upload(\""+field+"\",\""+grid_url+"\");' />");

			// remove msg
			jQuery(el).parent().children("span").remove();

			// remove delete button
			jQuery(el).parent().children("input[type=button]").remove();
		}

		function link_upload(el,field)
		{
			setTimeout(function(){

				var str_multiple = '';
				if (jQuery(el).attr("multiple") == "multiple")
					str_multiple = "multiple='multiple'";

				// for dialog, else inline
				if (jQuery(el).closest('.FormGrid').length)
					grid_id = jQuery(el).closest('.FormGrid').attr('id').replace('FrmGrid_','');
				else
					grid_id = jQuery(el).closest('.ui-jqgrid-btable').attr('id');

				grid = jQuery('#'+grid_id);
				grid_url = grid.getGridParam('url');

				// when using form=add option for upload
				if (grid_url == undefined)
					grid_url = location.href.replace('form=add','')+"&grid_id="+grid_id;

				if(jQuery(el).val() != '')
				{
					var msg = jQuery(el).val().split(",");
					for (var x=0;x < msg.length; x++)
						msg[x] = msg[x].substring(msg[x].lastIndexOf('/')+1);
					msg_html = msg.join('<br>&nbsp;');

					// edit
					jQuery(el).parent().append("<span id='"+field+"_name'>"+msg_html+" </span>");

                    // for multiple lines, move reset to new line
                    if (jQuery(el).val().split(",").length > 1)
                        jQuery("#"+field+"_name").append("<br>");

					var reset_text = jQuery.jgrid.search.Reset;

					jQuery(el).parent().append("<input style='display:block' class='reset_upload' type='button' value='"+reset_text+"' />");
					jQuery(el).parent().find('.reset_upload').click(function(){

						var sel = jQuery("input[name='"+field+"'].editable, input[name='"+field+"'].FormElement");
						fx_replace_upload(sel,field);
						jQuery(sel).val("");

					});
					jQuery(el).hide();
				}
				else
				{
					// add
					jQuery(el).parent().append("<input "+str_multiple+" id='"+field+"_file' size='10' name='" + field + "_file[]" + "' type='file' onchange='fx_ajax_file_upload(\""+field+"\",\""+grid_url+"\");' />");
					jQuery(el).hide();
				}

			},100);
		}

		<?php ### P ### ?>
		function validate_autocomplete(el)
		{
			if (jQuery("[name='"+el+"'].editable, [id='"+el+"']").val() == "")
				return [false,jQuery.jgrid.edit.msg.required];

			// console.log('val:'+jQuery(el).data('validate_ac_'+el));

			// if in edit dialog mode and not touched any field (pass validation)
			if ( jQuery(el).data('validate_ac_'+el) === undefined)
			{
				res = true;
			}
			else
			{
				var res = jQuery(el).data('validate_ac_'+el);
				if (res == true)
					jQuery(el).data('validate_ac_'+el,false);
			}

			// console.log('val now:'+jQuery(el).data('validate_ac_'+el));
			// alert(res);

			return [res,"Selection didn't match any item of list"];
		}

		function link_autocomplete(el,update_field,force_select,min_len)
		{
			setTimeout(function()
			{
				if(jQuery.ui)
				{
					if(jQuery.ui.autocomplete)
					{
						jQuery(el).autocomplete({	"appendTo":"body","disabled":false,"delay":0,
													"minLength":min_len,
													"autoFocus":true,
													"source":function (request, response)
															{
																request.element = el.name;
																request.oper = 'autocomplete';

																// console.log('new:false');
																jQuery(el).data('validate_ac_'+el.name,false);

																// Send whole row data in ajax - start

																if (jQuery(el).closest('.FormGrid').length)
																	grid_id = jQuery(el).closest('.FormGrid').attr('id').replace('FrmGrid_','');
																else
																	grid_id = jQuery(el).closest('.ui-jqgrid-btable').attr('id');

																grid = jQuery('#'+grid_id);

																// get editable and non-editable data, both
																var row = grid.getRowData(jQuery(el).closest('tr').attr('id'));
																for (var a in row) request[a] = row[a];

																// override html data (from above) with content of editable fields
																jQuery(".editable").each(function(){ request[jQuery(this).attr('name')] = jQuery(this).val(); });

																// for dialogs, load param from visible form selection
																jQuery(".FormGrid:visible .FormElement").each(function(){ request[jQuery(this).attr('id')] = jQuery(this).val(); });

																// dont send 'act' column data
																delete(request['act']);
																delete(request['xs_view_dots']);

																// Send whole row data in ajax - ends

																jQuery.ajax({
																	url: "<?php echo $this->options["url"]?>",
																	dataType: "json",
																	data: request,
																	type: "POST",
																	error: function(res, status) {
																		alert(res.status+" : "+res.statusText+". Status: "+status);
																	},
																	success: function( data ) {
																		response( data );
																	}
																});
															},
													"select":function (event, ui)
															{
																// change function to set target value
																var ival,self_field;

																if(ui.item) {
																	ival = ui.item.id || ui.item.value;
																}

																// if callback is defined for autocomplete, call it
																if (update_field instanceof Function)
																{
																	update_field(ui.item.data);

																	// reset variable to fill autocomplete field after callback function
																	self_field = el.name;
																}
																else
																{
																	self_field = update_field;
																}

																if(ival) {
																	// fix: autocomplete default edit (only update specific element)
																	if (self_field == el.name)
																	{
																		jQuery(el).val(ival);
																	}
																	else
																	{
																		jQuery("input[name='"+self_field+"'].editable, input[id='"+self_field+"']").val(ival);
																		jQuery("select[name='"+self_field+"'].editable, select[id='"+self_field+"']").val(ival);
																		jQuery("textarea[name='"+self_field+"'].editable, textarea[id='"+self_field+"']").val(ival);
																	}
																} else {
																	jQuery("input[name='"+self_field+"']").val("");
																	jQuery("select[name='"+self_field+"']").val("");
																	jQuery("textarea[name='"+self_field+"']").val("");
																}
																// console.log('select:'+jQuery(el).data('validate_ac_'+el.name));
																jQuery(el).data('validate_ac_'+el.name,true);
																// console.log('select:'+jQuery(el).data('validate_ac_'+el.name));

															},
													"change": function( event, ui )
															{
																if (!force_select) return;

																// if in edit dialog and not touched any field
																if (jQuery(el).data('validate_ac_'+el.name) == undefined) return;

																var select = jQuery(el);
																if (!ui.item || jQuery(this).val() != ui.item.value)
																{
																	// show error msg if new entry
																	jQuery('#FormError>td').html('<div>'+"&nbsp;Selection didn't match any item of list"+'</div>'); jQuery('#FormError').show().delay(3000).fadeOut();

																	// remove invalid value, as it didn't match anything
																	jQuery(this).val("");

																	// setTimeout(function(){jQuery(select).focus();},100);

																	// console.log('change:false');
																	jQuery(el).data('validate_ac_'+select.attr('name'),false);
																	return false;
																}
																else
																{
																	// console.log('change:true');
																	jQuery(el).data('validate_ac_'+select.attr('name'),true);
																}
															}
												});

						// disable html autocomplete
						jQuery(el).attr("autocomplete", Math.random());

					} // if jQuery.ui.autocomplete
				} // if jQuery.ui
			},200); // setTimeout
		} // link_autocomplete

		<?php ### P ### ?>
		fx_get_dropdown = function (o,field,for_search_bar)
		{
			// dont process hidden elements, removed for select2 dependent
			// if (!jQuery(o).is(":visible"))
				// return;

			if (for_search_bar)
			{
				// for inline edit and dialog edit
				var sel = '.ui-search-toolbar select[name='+field+']';
			}
			else
			{
				// for inline edit and dialog edit
				var sel = 'select[name='+field+'].editable,select[name='+field+'].FormElement,.edit-cell select[name='+field+']';
			}

			var request = {};
			request['value'] = jQuery(o).val();

			if (o.event == 'onload')
				request['event'] = 'onload';

			// for dialog, else inline
			if (jQuery(o).closest('.FormGrid').length)
				grid_id = jQuery(o).closest('.FormGrid').attr('id').replace('FrmGrid_','');
			else
				grid_id = jQuery(o).closest('.ui-jqgrid-btable').attr('id');

			grid = jQuery('#'+grid_id);

			// get editable and non-editable data, both
			var row = grid.getRowData(jQuery(o).closest('tr').attr('id'));
			for (var a in row)
				request[a] = row[a];

			// override html data (from above) with content of editable fields
			jQuery(".editable").each(function(){ request[jQuery(this).attr('name')] = jQuery(this).val(); });

			// for dialogs, load param from visible form selection
			jQuery("#FrmGrid_"+grid_id+":visible .FormElement").each(function(){ request[jQuery(this).attr('id')] = jQuery(this).val(); });

			// add source element's value
			request[jQuery(o).attr('name')] = jQuery(o).val();

			if (for_search_bar)
				jQuery(".ui-search-input input, .ui-search-input select").each(function(){ request[jQuery(this).attr('name')] = jQuery(this).val(); });

			// dont send 'act' column data
			delete(request['act']);
			delete(request['xs_view_dots']);
			delete(request[field]);

			// to detect internal ajax call
			request['nd'] = '12345';

			if (o.event == 'onload')
				request['src'] = field;
			else
				request['src'] = jQuery(o).attr('name');


			// if callback is set for dropdown
			if (field instanceof Function)
				request['return'] = "json";
			else
			{
				//preserve last value
				var last_val = jQuery(sel).val();
				// jQuery(sel).css('min-width',"70%");

				request['return'] = "option";
				request['target'] = field;
				// jQuery(sel).prepend("<option value='-1' selected='selected' disabled='disabled' style='display:none'>Loading...</option>");
			}

			jQuery.ajax({
						url: grid.getGridParam('url'),
						dataType: 'html',
						data: request,
						type: 'POST',
						error: function(res, status) {
							alert(res.status+' : '+res.statusText+'. Status: '+status);
						},
						success: function( data ) {

							if (for_search_bar == 1)
							{
								data = "<option value=\"\">-</option>" + data;

								var old_select_content = jQuery('select[name='+field+']').html();
								jQuery('select[name='+field+']').html(data);

								// only refresh if content is different
								if (data != old_select_content)
								{
									// invoke change event for dependents (slowing with extra call to adjust result)
									jQuery('select[name='+field+']').change();
								}

								// reload multiselect filter if exist
								try
								{
									jQuery('select[name='+field+']').multiselect("refresh");
									jQuery('select[name='+field+']').siblings('button.ui-multiselect').css({
										width: "98%"
									});
								}
								catch(err){};
							}
							else
							{
								// if callback is defined for dropdown, call it
								if (field instanceof Function)
								{
									field(data);
								}
								else
								{
									if (data.length)
										jQuery(sel).html(data);
									else
										jQuery(sel).html("<option value='' selected='selected'>Select...</option>");

									// if not select2
									if (!jQuery(sel).data('select2') && !jQuery(sel).attr('skipempty'))
									{
										jQuery(sel).children("option[value='']").remove();
										jQuery(sel).prepend("<option value='' selected='selected'></option>");
									}

									// for multiple option dropdown, select last values
									if(typeof(last_val) == "object" && last_val != null)
									{
										last_val = last_val.toString().split(",");
										jQuery(sel).val(last_val);
									}
									else
									{
										// reselect last option if exist, in new dropdown data
										if (jQuery(sel).children('option[value="'+last_val+'"]').length != 0)
											jQuery(sel).val(last_val);
									}

									// invoke change event for dependents
									jQuery(sel).change();

									// if select2, reset field as blank for new options
									if (jQuery(sel).data('select2'))
									{
										jQuery(sel).select2("val", request[field] || "");
									}

									// load (if any) new values in dropdown k:v values
									// if (o.event == 'onload')
									{
										// add new dropdown values in column
										var s = grid.getColProp(field).editoptions.value;
										var rec = s.split(";");
										var vals = new Array();
										for (var x in rec)
										{
											tmp = rec[x].split(":");
											vals[tmp[0]] = tmp[1];
										}

										var arr = new Array();
										jQuery('select[name='+field+'] option').each(function()
										{
											vals[jQuery(this).val()] = jQuery(this).text();
										});

										s = '';
										for(var x in vals)
											s += x+":"+vals[x]+";";

										// remove last ;
										s = s.substring(0,s.length-1);

										grid.setColProp(field,{editoptions:{value:s}});

										// reselect dropdown value for cellEdit mode, rest already working
										if (grid.getGridParam('cellEdit'))
										{
											val = request['value'] || request[request['src']];
											jQuery('.ui-jqgrid-btable select[name='+field+']').val(val);
										}
									}
								}
							}
						}
					});
		};

		// function to reload new values in dropdown (from add/edit dialog) - work with onload-sql
		fx_reload_dropdown = function (field,src)
		{
			// fill dropdown 1 based on row data
			var src = jQuery('select[name='+src+'].FormElement')[0];
			fx_get_dropdown(src,field);
		}

		<?php ### P ### ?>
		fx_success_msg = function (msg,fade)
		{
			var t = Math.max(0, ((jQuery(window).height() - jQuery('#info_dialog').outerHeight()) / 2) + jQuery(window).scrollTop());
			var l = Math.max(0, ((jQuery(window).width() - jQuery('#info_dialog').outerWidth()) / 2) + jQuery(window).scrollLeft());

			jQuery.jgrid.info_dialog("Info","<div class='ui-state-highlight' style='padding:5px;'>"+msg+"</div>",
												jQuery.jgrid.edit.bClose,{buttonalign:"right", left:l, top:t  });

			jQuery("#info_dialog").abscenter();

	      	if (fade == 1)
			{
				jQuery("#info_dialog").delay(1000).fadeOut();
				setTimeout('jQuery.jgrid.hideModal("#info_dialog");',1200);
			}
		};

		<?php ### P ### ?>
		fx_error_msg = function (msg,fade)
		{
			var t = Math.max(0, ((jQuery(window).height() - jQuery('#info_dialog').outerHeight()) / 2) + jQuery(window).scrollTop());
			var l = Math.max(0, ((jQuery(window).width() - jQuery('#info_dialog').outerWidth()) / 2) + jQuery(window).scrollLeft());

			jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,"<div class='ui-state-highlight' style='padding:5px;'>"+msg+"</div>",
												jQuery.jgrid.edit.bClose,{buttonalign:"right", left:l, top:t  });

			jQuery("#info_dialog").abscenter();

	      	if (fade == 1)
			{
				jQuery("#info_dialog").delay(1000).fadeOut();
				setTimeout('jQuery.jgrid.hideModal("#info_dialog");',1200);
			}
		};

		<?php ### P ### ?>
		fx_bulk_update = function (op,data,selection,grid)
		{
			if (typeof(grid) == "undefined")
				grid = '<?php echo $grid_id?>';

			if (typeof(selection) == 'undefined')
			{
				// for non multi select
				var selr_one = jQuery('#'+grid).jqGrid('getGridParam','selrow'); // array of id's of the selected rows when multiselect options is true. Empty array if not selection

				// for multi select
				var selr = [];
				selr = jQuery('#'+grid).jqGrid('getGridParam','selarrrow'); // array of id's of the selected rows when multiselect options is true. Empty array if not selection

				var colModel = jQuery('#'+grid).jqGrid("getGridParam", "colModel"); 

				// get field name
				var field = colModel[0]['name'];
				if (field == "cb") field = colModel[1]['name'];

				if (selr.length < 2 && selr_one)
					selr[0] = selr_one;

				// don't process if nothing is selected
				if (selr.length == 0)
				{
					jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,'<div class=\"ui-state-error\">Please select rows to edit</div>',
												jQuery.jgrid.edit.bClose,{buttonalign:'right'});
					return;
				}

				var str = selr[0];
				for (var x=1;x < selr.length;x++)
				{
					str += ',' + selr[x];
				}
			}
			else
				str = selection;

			// call ajax to update date in db
			var request = {};
			request["oper"] = 'edit';
			request[field] = str;
			// passing id for backward compat
			request['id'] = str;
			request['bulk'] = op;
			if (data)
				request['data'] = data;

			jQuery.ajax({
				url: jQuery('#'+grid).jqGrid('getGridParam','url'),
				dataType: 'html',
				data: request,
				type: 'POST',
				error: function(res, status) {
					jQuery.jgrid.info_dialog(jQuery.jgrid.errors.errcap,'<div class=\"ui-state-error\">'+ res.responseText +'</div>',
							jQuery.jgrid.edit.bClose,{buttonalign:'right'});
				},
				success: function( data ) {
					if (data != "")
					{
						data = JSON.parse(data);
						if (typeof(data.msg) != "undefined")
							fx_success_msg(data.msg,data.fade);
					}
					else
						fx_success_msg("<?php echo $this->options["edit_options"]["success_msg_bulk"]?>",1);

					// reload grid for data changes
					jQuery('#'+grid).jqGrid().trigger('reloadGrid',[{jqgrid_page:1}]);
				}
			});

		};

		<?php ### P ### ?>
		fx_ajax_file_upload = function (field,upload_url)
		{
			var selector = jQuery("input[name='"+field+"'].editable, input[name='"+field+"'].FormElement");

			// bug fix for chrome, multiple upload issue
			if (jQuery.browser.chrome)
			{
				if (jQuery(selector).data("invoked") == 1)
					return;
				jQuery(selector).data("invoked",1);
			}

			//starting setting some animation when the ajax starts and completes

			jQuery(selector).parent().not(":has(span)").append('<span id="'+field+'_upload"></span>');
			jQuery("span#"+field+"_upload").html('<img title="Loading" alt="Loading" src="data:image/gif;base64,R0lGODlhKwALAPEAAP///5ycnM7OzpycnCH+GkNyZWF0ZWQgd2l0aCBhamF4bG9hZC5pbmZvACH5BAAKAAAAIf8LTkVUU0NBUEUyLjADAQAAACwAAAAAKwALAAACMoSOCMuW2diD88UKG95W88uF4DaGWFmhZid93pq+pwxnLUnXh8ou+sSz+T64oCAyTBUAACH5BAAKAAEALAAAAAArAAsAAAI9xI4IyyAPYWOxmoTHrHzzmGHe94xkmJifyqFKQ0pwLLgHa82xrekkDrIBZRQab1jyfY7KTtPimixiUsevAAAh+QQACgACACwAAAAAKwALAAACPYSOCMswD2FjqZpqW9xv4g8KE7d54XmMpNSgqLoOpgvC60xjNonnyc7p+VKamKw1zDCMR8rp8pksYlKorgAAIfkEAAoAAwAsAAAAACsACwAAAkCEjgjLltnYmJS6Bxt+sfq5ZUyoNJ9HHlEqdCfFrqn7DrE2m7Wdj/2y45FkQ13t5itKdshFExC8YCLOEBX6AhQAADsAAAAAAAAAAAA=" />&nbsp;(Uploading...)');
			jQuery("input#"+field+"_file").hide();

			/*
			preparing ajax file upload
			url: the url of script file handling the uploaded files
						fileElementId: the file type of input element id and it will be the index of  $_FILES Array()
			dataType: it support json, xml
			secureuri:use secure protocol
			success: call back function when the ajax complete
			error: callback function when the ajax failed
			*/

			jQuery.extend({
				handleError: function( s, xhr, status, e )
				{
					if(xhr.responseText)
						console.log(xhr.responseText);
				}
			});

			// hide submit button till upload
			jQuery('#sData').hide();
			jQuery.ajaxFileUpload
			(
				{
					url:upload_url,
					secureuri:false,
					fileElementId:field+"_file",
					dataType: 'json',
					data: {"field":field+"_file"},
					success: function (data, status)
					{
						// bug fix for chrome, multiple upload issue
						if (jQuery.browser.chrome)
							jQuery("input#"+field).data("invoked",0);

						if(typeof(data.error) != 'undefined')
						{
							if(data.error != '')
							{
								//alert(data.error);
								jQuery("tr#FormError td.ui-state-error").html(data.error);
								jQuery("tr#FormError").show();
								jQuery("#"+field+"_upload").html("");

								// reset file upload
								jQuery("input#"+field+"_file").replaceWith(jQuery("input#"+field+"_file")[0].outerHTML);
								jQuery("input#"+field+"_file").show();
							}
							else
							{
								// show multiple uploads status
								var msg = data.msg.split(",");
								for (var x=0;x < msg.length; x++)
									msg[x] = msg[x].substring(msg[x].lastIndexOf('/')+1) + " (Uploaded) ";
								msg_html = msg.join('<br>&nbsp;');

                                // for multiple lines, move reset to new line
								if (msg.length > 1)
									msg_html += "<br>";

 								jQuery("#"+field+"_upload").html(msg_html);

								// hide error if displayed
								jQuery("tr#FormError td.ui-state-error").html("");
								jQuery("tr#FormError").hide();

								var o = jQuery(selector);
								jQuery(o).val(data.msg);

								var reset_text = jQuery.jgrid.search.Reset;

								jQuery(o).parent().append("<input style='display:block' class='reset_upload' type='button' value='"+reset_text+"' />");
								jQuery(o).parent().find('.reset_upload').click(function(){

									fx_replace_upload(selector,field);
									jQuery(selector).val("");

								});

								jQuery("#"+field+"_file").remove();

								// show image preview after upload
								var ext = data.msg.substr(-4,4).toLowerCase();

								if (data.msg.indexOf(",") == -1) // show preview when not multiple file upload (comma sep)
								if (ext.indexOf("jpg") != -1 || ext.indexOf("jpeg") != -1 || ext.indexOf("bmp") != -1 || ext.indexOf("png") != -1 || ext.indexOf("gif") != -1)
								{
									var i = "<br><img onclick='window.open(\""+data.msg+"\")' style='cursor:hand;cursor:pointer;max-height:150px; max-width:90%' src='"+data.msg+"'>";
									jQuery("#"+field+"_upload").append(i);
								}
							}
						}

						// show submit button again
						jQuery('#sData').show();
					},
					error: function (data, status, e)
					{
						// bug fix for chrome, multiple upload issue
						if (jQuery.browser.chrome)
							jQuery("input#"+field).data("invoked",0);

						alert(e);
						jQuery('#sData').show();
					}
				}
			)
			return false;
		};
		<?php ### P ### ?>

		<?php // Placeholder on search autofilter ?>
		jQuery(document).ready(function(){
			jQuery(".ui-search-input input").attr("placeholder",jQuery.jgrid.search.caption);
		
			// invoke css to hide bottom toolbar buttons instantly
			<?php if ($this->options["toolbar"] == "top") { ?>
				jQuery(".ui-jqgrid[id=gbox_<?php echo $grid_id?>]").attr('toolbar','top');
			<?php } ?>			
		});

		<?php
		// chain 'loadComplete' function with base working
		if (!empty($this->options["afterGrid"]))
		{
			echo "(".$this->options["afterGrid"].")();";
			unset($this->options["afterGrid"]);
		}
		?>

		<?php if (isset($this->options["toolbar"]) && $this->options["toolbar"] != "bottom") { ?>
		// Toolbar position button, 2 dom ready to delay copying toolbar
		fx_toolbar_init = function(){

			// copied toolbar object to include JS events as well
			<?php if ($this->options["toolbar"] == "top") { ?>
				jQuery('#<?php echo $grid_id?>_pager').insertBefore('#<?php echo $grid_id?>_toppager').attr('id','_toppager');
			<?php } else if ($this->options["toolbar"] == "both") { ?>
				var tbar = jQuery('#<?php echo $grid_id?>_pager').clone(true);
				tbar.insertBefore('#<?php echo $grid_id?>_toppager').attr('id','_toppager');
			<?php } ?>

			jQuery('#_toppager').removeClass("ui-jqgrid-pager");
			jQuery('#_toppager').addClass("ui-jqgrid-toppager");
			jQuery('#<?php echo $grid_id?>_toppager').remove();
			jQuery('#_toppager').attr('id','<?php echo $grid_id?>_toppager');

			jQuery("#<?php echo $grid_id ?>_toppager").append("<style>.ui-jqgrid #<?php echo $grid_id ?>_toppager > div > table:nth-child(1) > tbody > tr >  td:nth-child(2) { display:none; }" +
									".ui-jqgrid #<?php echo $grid_id ?>_toppager > div > table:nth-child(1) > tbody > tr >  td:nth-child(3) { display:none; }</style>");	

			<?php ### P ### ?>
			<?php
			// show only top icons for inline add
			if ($this->options["toolbar"] == "both" && ($this->actions["inlineadd"] !== false || $this->actions["inline"] === true) ) { ?>
			jQuery('.ui-jqgrid-pager #<?php echo $grid_id?>_pager_left').html('');
			<?php } ?>

			var filterButton = "";
			<?php if ($this->options["filterbutton"] === true) { ?>
				filterButton += "<style>#gbox_<?php echo $grid_id ?> tr.ui-search-toolbar { display:none; }</style>";
				filterButton += "<button style='margin-left:0px;margin-top:-5px; border-color: transparent;' id='<?php echo $grid_id ?>_filtersearch' type='button'>&nbsp;</button>";
			<?php } ?>

			<?php if ($this->options["globalsearch"] === true) { ?>

			jQuery("#<?php echo $grid_id ?>_toppager #<?php echo $grid_id ?>_pager_left")
							.append(jQuery("<table align='right' class='global-search ui-pg-table navtable' style='margin: -2px;text-align: right; width: 50%' cellpadding='0' cellspacing='0' border='0'><tr><td>" +
									"<input autocomplete='globalsearch' placeholder='' id='<?php echo $grid_id ?>_globalsearchtext' style='padding:5px; width:40%; border: 1px solid #cdcdcd'></input>" +
									"<button style='margin-top:-5px; border-color: transparent;' id='<?php echo $grid_id ?>_globalsearch' type='button'>&nbsp;</button><button style='display:none;margin-top:-5px; border-color: transparent;' id='<?php echo $grid_id ?>_filtercancel' type='button'>&nbsp;</button>"+filterButton+"</td></tr></table>"))
							.append("<style>.ui-jqgrid #<?php echo $grid_id ?>_toppager .ui-pg-table { height:100% }</style>");

			// global search placeholder
			var searchall_text = jQuery.jgrid.search.searchAll || 'Search all columns ...';
			jQuery('table.global-search input').attr('placeholder',searchall_text);

			jQuery(".ui-jqgrid .ui-userdata").css("height", "auto");
			jQuery("#<?php echo $grid_id ?>_globalsearchtext").keydown(function (e) {
				var key = e.charCode || e.keyCode || 0;
				clearTimeout(jQuery.data(this, 'timer'));
				// code is 13
				if (key === jQuery.ui.keyCode.ENTER)
					jQuery("#<?php echo $grid_id ?>_globalsearch").click();
				else
					jQuery(this).data('timer', setTimeout('jQuery("#<?php echo $grid_id ?>_globalsearch").click()', 500));
			});

			jQuery("#<?php echo $grid_id ?>_globalsearch").uibutton({ icons: { primary: "ui-icon-search" },text: false }).click(function ()
			{
				var phpgrid = jQuery("#<?php echo $grid_id?>");

				var postData = phpgrid.jqGrid("getGridParam", "postData"),
					colModel = phpgrid.jqGrid("getGridParam", "colModel"),
					rules = [], groups = [],
					searchText = jQuery("#<?php echo $grid_id ?>_globalsearchtext").val(),
					l = colModel.length,
					searchText = jQuery.trim(searchText),
					i,
					cm;

				var fs = new Array();
				for (i = 0; i < l; i++) {
					cm = colModel[i];
					if (cm.search !== false && (cm.stype === undefined || cm.stype === "text" || cm.stype === "select")) {
						fs.push(cm.name);
					}
				}

				if (fs.length>0 && searchText.length>0)
				{
					// space in words will narrow the results with all words
					if (phpgrid.jqGrid("getGridParam", "datatype") == "local")
					{
						var texts = searchText.split(" ");
						if (texts.length > 0)
						{
							for (t in texts)
							{
								rules = [];
								for (i in fs)
								{
									rules.push({
											field: fs[i],
											op: "cn",
											data: texts[t]
										});
								}
								groups[groups.length] = {'groupOp': "OR",'rules': rules};
							}
						}
					}
					else
					{
						// replace "blah blah" to 'blah blah' for exact search on backend and avoid removing strip()
						searchText = searchText.replace(/\"/g,"'");
						
						var texts = [];

						if (searchText.match(/'([^']+)'/g))
							texts[0] = searchText.replace(/'/g,"");
						else
							texts = searchText.split(" ");

						if (texts.length > 0)
						{
							for (t in texts)
							{
								rules = [];
								for (i in fs)
								{
									rules.push({
											field: fs[i],
											op: "cn",
											data: texts[t]
										});
								}
								groups[groups.length] = {'groupOp': "OR",'rules': rules};

							}
						}						
						postData.searchtype = 'global';
					}

					postData.filters = JSON.stringify({groupOp: "AND",groups: groups});
					phpgrid.jqGrid("setGridParam", { search: true });					

					// search / cancel button added
					jQuery('#<?php echo $grid_id ?>_globalsearch').hide();
					jQuery("#<?php echo $grid_id ?>_filtercancel").show();

				}
				else
				{
					postData.searchtype = '';
					postData.filters = '{}';
					phpgrid.jqGrid("setGridParam", { search: false });					
				}
				
				phpgrid.jqGrid("setGridParam", { "postData": postData });
				phpgrid.trigger("reloadGrid", [{page: 1, current: true}]);

				// reset individual filters for global search
				phpgrid.trigger("clearToolbar",false);

				// remove default values
				jQuery("input[id^=gs_]").val('');

				return false;
			});
			
			jQuery("#<?php echo $grid_id ?>_filtercancel").uibutton({ icons: { primary: "ui-icon-bars fa fa-ban" },text: false }).click(function ()
			{
				jQuery('#<?php echo $grid_id ?>_globalsearchtext').val('');
				jQuery('#<?php echo $grid_id ?>_globalsearch').click();
				jQuery('#<?php echo $grid_id ?>_globalsearch').show();
				jQuery("#<?php echo $grid_id ?>_filtercancel").hide();
				return false;
			});
			
			if (jQuery("#<?php echo $grid_id ?>_filtersearch").length)
			{
				jQuery("#<?php echo $grid_id ?>_filtersearch").uibutton({ icons: { primary: "ui-icon-bars fa fa-filter" },text: false }).click(function ()
				{
					jQuery("#<?php echo $grid_id ?>").trigger("toggleToolbar");
					return false;
				});
			}			
			<?php } ?>
			<?php ### P-end ### ?>
			}

			phpgrid_<?php echo $grid_id?>.fx_toolbar_init = fx_toolbar_init;

			jQuery(document).ready(function(){
				setTimeout(phpgrid_<?php echo $grid_id?>.fx_toolbar_init,200);
			});
		<?php } ?>

		<?php ### P ### ?>
		<?php if ($this->options["responsive"] === true || $this->options["autoresize"] === true) { ?>
		var screen_width;

		// optimizing showCol, hideCol speed in grid_resize function
		function set_column_vis(gid,col_name,vis) 
		{
			var hidden = jQuery("#"+gid).jqGrid("getColProp",col_name).hidden;
			
			if (hidden == false && vis == 'hide')
			{
				// alert('hiding '+col_name+','+gid);
				jQuery("#"+gid).jqGrid("hideCol",col_name);
			}

			if (hidden == true && vis == 'show')
			{
				// alert('showing '+col_name+','+gid);
				jQuery("#"+gid).jqGrid("showCol",col_name);
			}
		}


		jQuery(document).ready(function(){

			jQuery(".ui-jqgrid").addClass('ui-jqgrid-resp');

			fx_grid_resize = function ()
			{
				var gid = '<?php echo $grid_id ?>';
				
				if (!jQuery("#"+gid).is(":visible"))
					return;

				// if orientation changed
				if (screen_width != jQuery(window).innerWidth())
				{
					// starting point of screen sizes
					var screen_xl = 1200, screen_lg = 992, screen_md = 768, screen_sm = 544, screen_xs = 320;
					var w = screen_width = jQuery(window).innerWidth();
					<?php
					$modes = array("xs","sm","md","lg","xl");
					$responsive = array();

					foreach($modes as $m)
						$responsive[$m] = array();

					$show_dots = array();
					foreach($this->options["colModel"] as $c)
					{

						if ($c["hidden"]==true) continue;
						if (!isset($c["visible"])) continue;
						
						// skip responsive for grouping field
						if (is_array($this->options["groupingView"]["groupField"]))
							if (in_array($c["name"],$this->options["groupingView"]["groupField"]))
								continue;

						foreach($modes as $m)
						{
							if (in_array($m,$c["visible"]))
								$responsive[$m][$c["name"]] = "show";
							else
							{
								// show dots on this screen size, if some column is hidden excluding xs_dots/xs_items columns
								if ($c["viewable"] !== false)
									$show_dots[$m] = 1;

								$responsive[$m][$c["name"]] = "hide";
							}
						}
					}
					?>

					var screen_size;
					var show_dots = <?php echo json_encode(array_keys($show_dots)) ?>;

					// xs
					if (w < screen_sm)
					{
						screen_size = "xs";
						<?php
						foreach($responsive["xs"] as $c => $v)
							echo 'set_column_vis(gid,"'.$c.'","'.$v.'");';
						?>
					}
					// sm
					else if (w < screen_md)
					{
						screen_size = "sm";
						<?php
						foreach($responsive["sm"] as $c => $v)
							echo 'set_column_vis(gid,"'.$c.'","'.$v.'");';
						?>
					}
					// md
					else if (w < screen_lg)
					{
						screen_size = "md";
						<?php
						foreach($responsive["md"] as $c => $v)
							echo 'set_column_vis(gid,"'.$c.'","'.$v.'");';
						?>
					}
					// lg
					else if (w < screen_xl)
					{
						screen_size = "lg";
						<?php
						foreach($responsive["lg"] as $c => $v)
							echo 'set_column_vis(gid,"'.$c.'","'.$v.'");';
						?>
					}
					// xl
					else
					{
						screen_size = "xl";
						<?php
						foreach($responsive["xl"] as $c => $v)
						{
							echo 'set_column_vis(gid,"'.$c.'","'.$v.'");';
						}
						?>
					}

					<?php
					if (isset($this->options["search_options"]["autofilter"]))
					{
						switch ($this->options["search_options"]["autofilter"])
						{
							case "xs+":
								echo 'if (w >= screen_xs) jQuery(".ui-search-toolbar").show();';
							break;
							case "sm+":
								echo 'if (w >= screen_sm) jQuery(".ui-search-toolbar").show();';
							break;
							case "md+":
								echo 'if (w >= screen_md) jQuery(".ui-search-toolbar").show();';
							break;
							default:
							break;
						}
					}
					?>

					// show ... if any column is hidden
					if (show_dots.indexOf(screen_size) != -1)
						jQuery("#"+gid).jqGrid("showCol","xs_view_dots");
					else
						jQuery("#"+gid).jqGrid("hideCol","xs_view_dots");

				}

				<?php ### P ### ressponsive columns of grid ?>
				<?php if ($this->actions["showhidecolumns"] !== false) { ?>

				// check if state persistence cookie exist, don't use responsive hide/show
				var cookie_val = Cookies.get("jqgrid_colchooser_<?php echo $this->id ?>");
				var cookie_hide_val = Cookies.get("jqgrid_colchooser_hide_<?php echo $this->id ?>");

				// hide all then show, always show selected column even in small screen
				if (cookie_val)
				{
					cookie_val = cookie_val.split(",");
					for(c in cookie_val)
						if (cookie_val[c])
							set_column_vis(gid,cookie_val[c],'show');
				}

				if (cookie_hide_val)
				{
					cookie_hide_val = cookie_hide_val.split(",");
					for(c in cookie_hide_val)
						if (cookie_hide_val[c])
							set_column_vis(gid,cookie_hide_val[c],'hide');						
				}

				<?php } ?>

				var newWidth;
				<?php if ($this->options["fullscreen"] === true) { ?>
					newWidth = jQuery(window).innerWidth();
				<?php } else { ?>

					// if grid in tab, use tab width else window's width
					if (jQuery("#"+gid).closest(".tab-pane").length)
					{
						newWidth = jQuery("#"+gid).closest(".tab-pane").width()-2;
					}	
					else
					{
						// update for responsive subgrid, set parent colspan to expand all max columns
						if (jQuery("#gbox_"+gid).parent().closest('.subgrid-data').length)
							jQuery("#gbox_"+gid).parent().closest('.subgrid-data').attr("colspan",1000);

						newWidth = jQuery("#gbox_"+gid).parent().width() - 5;
					}
										
				<?php } ?>

				var oldWidth = jQuery("#"+gid).jqGrid("getGridParam", "width");
				//console.log(jQuery("#"+gid).closest(".tab-pane").width() + "/" + newWidth + "/" + oldWidth);
				if (oldWidth !== newWidth)
				{
					jQuery("#"+gid).jqGrid("setGridWidth", newWidth);

					// fix for column mis-alignment when using group header
					var groupHeaders = jQuery("#"+gid).jqGrid("getGridParam", "groupHeader");
					if (groupHeaders != null && groupHeaders.groupHeaders.length==0)
						groupHeaders = null;

					if (groupHeaders != null)
					 	jQuery("#"+gid).jqGrid("destroyGroupHeader").jqGrid("setGroupHeaders", groupHeaders);
				}

				<?php if ($this->options["autoheight"] === true && $this->options["fullscreen"] !== true) { ?>
				// adjust height on resize

				var g = "#gbox_"+gid+" ";
				// remaining offset
				var h_offset = (jQuery(g+'.ui-jqgrid-titlebar').is(":visible")?jQuery(g+'.ui-jqgrid-titlebar').outerHeight():0)
								+(jQuery(g+'.ui-jqgrid-hdiv').is(":visible")?jQuery(g+'.ui-jqgrid-hdiv').outerHeight():0)
								+(jQuery(g+'.ui-jqgrid-toppager').is(":visible")?jQuery(g+'.ui-jqgrid-toppager').outerHeight():0)
								+(jQuery(g+'.ui-jqgrid-pager').is(":visible")?jQuery(g+'.ui-jqgrid-pager').outerHeight():0);

				jQuery("#"+gid).jqGrid('setGridHeight', jQuery("#gbox_"+gid).parent().innerHeight()-h_offset);
				<?php } ?>
			}

			phpgrid_<?php echo $grid_id?>.fx_grid_resize = fx_grid_resize;
			jQuery(window).bind("resize",()=>{ setTimeout( ()=>{phpgrid_<?php echo $grid_id?>.fx_grid_resize();},0); });

			// invoke resize on visible - https://stackoverflow.com/a/66394121/385377
			on_visible(document.querySelector("#<?php echo $grid_id?>"), () => { setTimeout(()=>{ phpgrid_<?php echo $grid_id?>.fx_grid_resize();},0); });
		});
		<?php } ?>

		<?php ### P ### ?>
		<?php if ($this->options["fullscreen"] === true) { ?>

		if (!fx_fullscreen)
		{
			var fx_fullscreen = function () {

				// if multi grids on same page
				jQuery(".ui-jqgrid").each(function(i,o){

					var gid = jQuery(o).attr("id").replace("gbox_","");
					// if not set on this grid, break
					if (gid !== '<?php echo $this->id ?>')
						return;

					// remaining offset
					var h_offset = jQuery('.ui-jqgrid-titlebar').outerHeight()
									+jQuery('.ui-jqgrid-hdiv').outerHeight()
									+jQuery('.ui-jqgrid-toppager').outerHeight()
									+jQuery('.ui-jqgrid-pager').outerHeight()+3;

					jQuery("#gbox_"+gid).css({'width':'100%', 'position':'fixed', 'top':'0px', 'left':'0px'});
					jQuery("#"+gid).jqGrid('setGridHeight', jQuery(window).innerHeight()-h_offset);
					jQuery("#"+gid).jqGrid("setGridWidth", jQuery(window).innerWidth());
				});
			};
		}
		jQuery(document).ready(function(){
			jQuery(window).bind("resize", fx_fullscreen).trigger("resize");
		});
		<?php } ?>

		<?php if ($this->options["resizable"] === true) { ?>
		jQuery("#<?php echo $grid_id?>").jqGrid('gridResize',{});
		<?php } ?>

		// bind arrow keys navigation
		jQuery("#<?php echo $grid_id?>").jqGrid('bindKeys',{'onEnter':function(rowid){ jQuery("tr.jqgrow[id="+rowid+"]").dblclick(); } });

		<?php ### P ### ?>
		<?php if ($this->internal["frozen"] === true) { ?>
		on_visible(document.querySelector("#<?php echo $grid_id?>"),function(){ setTimeout( ()=>{ fx_freeze_grid('<?php echo $grid_id?>');},200); })
		fx_freeze_grid = function(id)
		{
			var grid = jQuery('#'+id)[0];

			jQuery(grid).jqGrid('setFrozenColumns');

			jQuery('.frozen-div').css('border-bottom','0px');
			jQuery('.frozen-bdiv').css('min-height','auto');

			// fix for bs4
			if (jQuery("link[href*='ui.jqgrid.bs']").length)
			{
				jQuery.browser = {};
				jQuery.browser.msie=1;
			}

			// check for horizontal scrollbar for frozen cols height
			var offset = 0;
			if (jQuery(grid.grid.bDiv).get(0).scrollWidth > jQuery(grid.grid.bDiv).width())
				var offset = 16;
			
			// fix for height pixel
			jQuery(grid.grid.fbDiv).height(jQuery(grid.grid.bDiv).height()-offset);

			// adjust height
			/*
			jQuery(grid.grid.bDiv).scroll(function () { normalize_height(id); });
			jQuery(window).scroll(function () { normalize_height(id); });
			*/

			jQuery(window).resize(function () { normalize_height(id); });

			// sync frozen rows height
			jQuery("#"+id).bind("mousedown click dblclick mouseup",function(){
				t = setInterval("normalize_height('"+id+"')",100);
				setTimeout(function(){clearInterval(t);},1000);
			});

			jQuery(window).bind("keydown keyup",function(){
				normalize_height(id);
			});

			jQuery(grid).bind('jqGridLoadComplete', function () {
				setTimeout(function(){normalize_height(id);},200);
			});

			// event to trigger normalize height
			normalize_height(id);

			// enable tooltips on frozen columns as well
			jQuery(".frozen-div th[data-original-title]").each(function(){
				jQuery(this).attr("title", jQuery(this).attr("data-original-title"));
			});
			fx_tooltip_init();
		}

		normalize_height = function (grid_id)
		{
			var grid = jQuery('#'+grid_id)[0];
			if (!grid) return;

			if (jQuery('.frozen-bdiv tr.jqgrow').length==0)
			{
				setTimeout(function(){
					// adjust height of rows (for multi line cell content)
					jQuery('.frozen-bdiv tr.jqgrow').each(function () {
							var h = jQuery('#'+jQuery.jgrid.jqID(this.id)).height();
							jQuery(this).height(h);

							jQuery('#'+jQuery.jgrid.jqID(this.id)).height(h);
						});
				},200);
			}
			else
			{
				jQuery('.frozen-bdiv tr.jqgrow').each(function () {

					// get exact fraction height
					var h = document.getElementById(this.id).getBoundingClientRect()["height"];
					
					// for bootstrap
					if (jQuery("link[href*='ui.jqgrid.bs']").length)
						jQuery(this).outerHeight(h);
					else
						jQuery(this).height(h);
					
				});
			}

			// sync top position
			jQuery(grid.grid.fbDiv).css('top',jQuery(grid.grid.bDiv).position().top);
			jQuery(grid.grid.fhDiv).css('top',jQuery(grid.grid.hDiv).position().top);

			// sync footer position
			if (jQuery(grid.grid.sDiv).length)
			{
				jQuery(grid.grid.fsDiv).css('top',jQuery(grid.grid.sDiv).position().top);
				jQuery(grid.grid.fsDiv).css('z-index','1');
			}

			// sync scrolling position
			jQuery(grid.grid.fbDiv).scrollTop(jQuery(grid.grid.bDiv).scrollTop());
			jQuery(grid.grid.fbDiv).scrollLeft(jQuery(grid.grid.bDiv).scrollLeft());

		}
		<?php } ?>

		jQuery("#<?php echo $grid_id?>").triggerHandler("jqGridAfterGridComplete");
		// center position div (abs)
		jQuery.fn.abscenter = function () {
			this.css("position","absolute");
			setTimeout(()=>{
				this.css("top", Math.max(0, ((jQuery(window).height() - jQuery(this).outerHeight()) / 4)) + "px");
				this.css("left", Math.max(0, ((jQuery("body").width() - jQuery(this).outerWidth()) / 2)) + "px");
			},0);
			return this;
		};

		// center position div (abs)
		jQuery.fn.fixedcenter = function () {
			this.css("position","fixed");
			setTimeout(()=>{
				this.css("top", Math.max(0, ((jQuery(window).height() - jQuery(this).outerHeight()) / 4)) + "px");
				this.css("left", Math.max(0, ((jQuery("body").width() - jQuery(this).innerWidth()) / 2)) + "px");
			},0);
			return this;
		};

		// simulate ENTER on dialogs, and tabbing to submit,cancel
		jQuery.extend(jQuery.jgrid.edit, {
				onInitializeForm: function ($form) {
					jQuery("#sData, #cData").attr("tabIndex",0);

					jQuery("td.DataTD>.FormElement, #sData").keypress(function (e) {
						if (e.which === jQuery.ui.keyCode.ENTER && e.target.tagName != "TEXTAREA") {
							jQuery("#sData", $form.next()).trigger("click");
							return false;
						}
					});

					jQuery("#cData").keypress(function (e) {
						if (e.which === jQuery.ui.keyCode.ENTER) {
							jQuery("#cData", $form.next()).trigger("click");
							return false;
						}
					});
				}
			});

		// dialog display effect, not used anymore as jqModal:false. See 'viewModal' inside core js.
		jQuery.extend(jQuery.jgrid, {
			showModal : function(h) {
				h.w.show("fade","easeOutExpo",300);
			},
			closeModal : function(h) {
				h.w.hide().attr("aria-hidden", "true");
				if(h.o) {h.o.remove();}
			}
		});

		// fix for error dialog center
		jQuery(document).ready(function ()
		{
			jQuery.jgrid.jqModal = jQuery.extend(jQuery.jgrid.jqModal || {}, {
				beforeOpen: function(){ jQuery("#info_dialog").abscenter(); }
			});
		});

		<?php if ($this->actions["inlineadd"] !== false || $this->actions["inline"] === true) { ?>
		// fix for autocomplete shown as undefined while add
		jQuery.fn.fmatter.autocomplete = function (cellval, opts) { return (cellval == undefined) ? '' : cellval; };
		jQuery.fn.fmatter.datetime = function (cellval, opts) { return (cellval == undefined) ? '' : cellval; };
		<?php } ?>

		<?php if ($this->options["pastefromexcel"] === true) { ?>
		// paste from excel, inline and dialog form
		jQuery(document).bind('paste', function(ev) {

			var elem = jQuery(ev.target);

			// skip if paste start from textarea
			if (jQuery(elem)[0].tagName.toLowerCase() == "textarea") return;

			var data = ev.originalEvent.clipboardData.getData('text/plain');
			var arr = data.split('\t');

			// skip for one item paste
			if (arr.length == 1) return;

			if (jQuery(elem).hasClass('editable') || jQuery(elem).hasClass('FormElement') )
			{
				for(var i=0;i<arr.length;i++)
				{
					elem.val(arr[i]);

					if (jQuery(elem).hasClass('editable'))
						elem = jQuery(elem).parent().next().find('.editable');
					else if (jQuery(elem).hasClass('FormElement'))
						elem = jQuery(elem).closest('tr').next().find('.FormElement');

					if (elem.length == 0) break;
				}
			}
			return false;
		});
		<?php } ?>

		<?php
		### P ###
		if (isset($this->internal["js_dependent_dropdown"]))
			echo $this->internal["js_dependent_dropdown"];
		?>

	<?php
	}


	function prepare_sql($sql,$db)
	{
		// temp function for preg_replace_callback
		if (!function_exists("change_sql_limit"))
		{
			function change_sql_limit($matches)
			{
				return "SELECT TOP ".(intval($matches[2])+intval($matches[3])). " ".$matches[1];
			}
		}

		// remove new line to match regex
		$sql = str_replace("\n","",$sql);

		if (stripos($db,"mssql") !== false || stripos($db,"sqlsrv") !== false || stripos($db,"dblib") !== false)
		{
			$sql = preg_replace("/SELECT (.*) LIMIT ([0-9]+) OFFSET ([0-9]+)/i","select top ($2+$3) $1",$sql);
			#pr($sql,1);
		}
		else if (stripos($db,"access") !== false || stripos($db,"teradata") !== false)
		{
			$sql = preg_replace_callback("/SELECT (.*) LIMIT ([0-9]+) OFFSET ([0-9]+)/i","change_sql_limit",$sql);
		}
		else if (stripos($db,"firebird") !== false)
		{
			$sql = preg_replace("/SELECT (.*) LIMIT ([0-9]+) OFFSET ([0-9]+)/i","select FIRST $2 SKIP $3 $1",$sql);
		}
		else if (stripos($db,"informix") !== false)
		{
			$sql = preg_replace("/SELECT (.*) LIMIT ([0-9]+) OFFSET ([0-9]+)/i","select SKIP $3 FIRST $2 $1",$sql);
		}
		else if (stripos($db,"oci8") !== false)
		{
			preg_match("/(.*) LIMIT ([0-9]+) OFFSET ([0-9]+)/i",$sql,$matches);

			if (count($matches))
			{
				$query = $matches[1];
				$limit = $matches[2];
				$offest = $matches[3];

				$offset_min = $offest;
				$offset_max = $offest + $limit;

				$sql = "
					SELECT * FROM (
						SELECT a.*,rownum rnum
						FROM ($query) a
					)
					WHERE rnum > $offset_min AND rnum <= $offset_max
				";
			}
		}
		// @todo: not tested in detail
		else if (stripos($db,"db2") !== false)
		{
			preg_match("/(.*) LIMIT ([0-9]+) OFFSET ([0-9]+)/i",$sql,$matches);

			if (count($matches))
			{
				$query = $matches[1];
				$limit = $matches[2];
				$offest = $matches[3];

				$offset_min = $offest;
				$offset_max = $offest + $limit;

				$sql = "SELECT b.* FROM (SELECT a.*,row_number() over() as rnum FROM ($query) a) b WHERE b.rnum > $offset_min AND b.rnum <= $offset_max";
			}
		}

		return $sql;
	}

	// replace any param in data pattern e.g. http://domain.com?id={id} given that, there is a $col["name"] = "id" exist
	function replace_row_data($row,$str)
	{
		foreach($this->options["colModel"] as $link_c)
		{
			$link_row_data = $row[$link_c["name"]];
			$str = str_replace("{".$link_c["name"]."}", $link_row_data, $str);
		}
		return $str;
	}

	function addslashes_mssql($str)
	{
	 	if (is_array($str))
	 	{
			foreach($str AS $id => $value)
			{
	 			$str[$id] = addslashes_mssql($value);
	 		}
	 	}
	 	else
	 	{
	 		$str = str_replace("'", "''", $str);
	 	}

	 	return $str;
	}

	function escape_string($v)
	{
		if (strpos($this->db_driver, "mssql") !== false
				|| strpos($this->db_driver,"db2") !== false
				|| strpos($this->db_driver,"dblib") !== false
				|| strpos($this->db_driver,"oci") !== false
				|| strpos($this->db_driver, "postgres") !== false
				|| strpos($this->db_driver, "sqlite") !== false
				|| strpos($this->db_driver, "informix") !== false
				)
			$v = $this->addslashes_mssql($v);
		else
			$v = addslashes($v);

	 	return $v;
	}

	// preserve subqueries
	function remove_subsql()
	{
		$match = array();

		$start = array();
		$end = array();

		for($c=0;$c<strlen($this->select_command);$c++)
		{
			if ( $this->select_command[$c] == "(")
			{
				$start[] = $c;
			}
			elseif ( $this->select_command[$c] == ")")
			{
				$m = substr( $this->select_command, $start[count($start)-1] +1, $c - $start[count($start)-1] -1);
				$m = trim($m);

				// match only subqueries, exluding count(*), in (14,15) etc
				if (substr( strtolower($m), 0, 6) === "select")
					$match[] = substr( $this->select_command, $start[count($start)-1], $c - $start[count($start)-1] + 1 );
	
				array_pop($start);
			}
		}

		// put placeholder for subqueries
		for($i=count($match)-1;$i>=0;$i--)
			$this->select_command = str_replace($match[$i],"{".$i."}",$this->select_command);

		return $match;
	}

	// re-adjust subqueries in sql
	function add_subsql($sql,$match)
	{
		// replace placeholder in rev order
		// first subqueries then agg fxs
		for($i=count($match)-1;$i>=0;$i--)
			$sql = str_replace("{".$i."}",$match[$i],$sql);

		return $sql;
	}

	// wrap fields with space names ``,"",[]
	function wrap_field($field)
	{
		// add tilde sign for mysql
		if (strpos($this->db_driver, "mysql") !== false || !isset($this->db_driver))
		{
			$field = "`".$field."`";
			// if dbname table.field alias
			$field = str_replace(".","`.`",$field);
		}
		elseif (strpos($this->db_driver, "mssql") !== false || strpos($this->db_driver, "dblib") !== false || strpos($this->db_driver, "db2") !== false || strpos($this->db_driver,"postgres") !== false)
		{
			$field = '"'.$field.'"';
			// if dbname table.field alias
			$field = str_replace(".",'"."',$field);
		}

		return $field;
	}

	### P ###
	function add_tree_data(&$data)
	{
		$id = $this->options["treeConfig"]["id"];
		$p_id = $this->options["treeConfig"]["parent"];
		$table = $this->table;

		global $sort_data;
		$sort_data = array();
		function update_tree_level($grid, &$datas, $depth = 0, $parent = "")
		{
			global $sort_data;
			$id = $grid->options["treeConfig"]["id"];
			$p_id = $grid->options["treeConfig"]["parent"];

			if($depth > 1000) return ''; // Make sure not to have an endless recursion
			for($i=0, $ni=count($datas); $i < $ni; $i++){
				if($datas[$i][$p_id] == $parent){
					$datas[$i]["level"] = intval($depth);
					$sort_data[] = $datas[$i];
					update_tree_level($grid, $datas, $depth+1, $datas[$i][$id]);
				}
			}
		}

		// for loaded=false case,
		if (!isset($_REQUEST["nodeid"]))
			$node = 0;
		else
			$node = intval($_REQUEST["nodeid"]);

		if (!isset($_REQUEST["n_level"]) || !is_numeric($_REQUEST["n_level"]))
			$n_lvl = 0;
		else
			$n_lvl = intval($_REQUEST["n_level"])+1;

		// update tree level
		update_tree_level($this,$data,$n_lvl,$node);

		$data = $sort_data;

		$SQL = "SELECT t1.{$id} FROM {$table} AS t1 LEFT JOIN {$table} AS t2 ON t1.{$id} = t2.{$p_id} WHERE t2.{$id} IS NULL OR t2.{$id} = 0";
		$SQL = $this->prepare_sql($SQL,$this->db_driver);
		$result = $this->execute_query($SQL);

		if ($this->con)
		{
			$rows = $result->GetRows();
		}
		else
		{
			$rows = array();
			while($r = mysql_fetch_array($result,MYSQL_ASSOC))
				$rows[] = $r;
		}

		foreach($data as &$row)
		{
			$row["loaded"] = "true";
			$row["expanded"] = "true";

			if ($this->options["treeConfig"]["loaded"] === false)
				$row["loaded"] = "false";

			if ($this->options["treeConfig"]["expanded"] === false)
				$row["expanded"] = "false";

			$row["isLeaf"] = "false";

			foreach($rows as $r)
			{
				if ($row[$id] == $r[$id])
				{
					$row["isLeaf"] = "true";
					break;
				}
			}
		}
	}

	// fix for date > 2038 with php 5.2
	function custom_date_format($fmt, $date, $srcfmt = "")
	{
		// for daterange picker, if posted date is in correct format, ignore custom format
		if (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$date))
			$srcfmt = "Y-m-d";

		// Remove unwanted format specifiers
		if ($srcfmt != "")
		{
			$f = preg_split("/[t\/:_;.,\t\s-]/",$srcfmt);
			$d = preg_split("/[t\/:_;.,\t\s-]/",$date);
			
			foreach($f as $k=>$v)
				if ($v == "D")
					$date = str_replace($d[$k],"",$date);
		}
	
		if (floatval(PHP_VERSION) >= 5.2)
		{
			// phpgrid_error($fmt.",".$srcfmt.",".$date.",".date_format(date_create($date),$fmt));
			$date = date_format(date_create($date),$fmt);
			
		}
		else
		{
			// fix for d/m/Y or d/m/y date format. strtotime expects m/d/Y
			if (strstr($srcfmt,"d/m/Y"))
			{
				$date = preg_replace('/(\d+)\/(\d+)\/(\d+)/i','$2/$1/$3',$date);
			}
			// fix for d-m-y (2 digit year) for strtotime
			else if (strstr($srcfmt,"m-d-Y"))
			{
				$date = preg_replace('/(\d+)-(\d+)-(\d+)/i','$2-$1-$3',$date);
			}
			// fix for d-m-y (2 digit year) for strtotime
			else if (strstr($srcfmt,"m-d-y"))
			{
				$date = preg_replace('/(\d+)-(\d+)-(\d+)/i','$3-$2-$1',$date);
			}
			else if (strstr($srcfmt,"d/M/Y") || strstr($srcfmt,"d-M-Y"))
			{
				$date = preg_replace('/[\/\-]/i',' ',$date);
			}
			$date = date($fmt,strtotime($date));
		}

		return $date;
	}

	// get clean name e.g. for file purpose
	function get_clean($str)
	{
		$str = trim($str);
		$str = strtolower($str);

		// kill anything that is not a letter, digit, space
		$str = preg_replace ("/[^a-zA-Z0-9]/", "_", $str);
		$str = preg_replace ("/[_]+/", "_", $str);
		$str = trim($str,'_');
		return $str;
	}

	function sanitize_csv_data(&$data) {
		// Escape double quotes with double quotes
		$data = str_replace('"', '""', $data);
	
		// If the data contains special characters such as '=', '+', '-', '@', etc.,
		// it is better to wrap the data in double quotes to prevent any formula execution
		if (preg_match('/[=+\-@].*/', $data)) {
			$data = '"' . $data . '"';
		}
	
		return $data;
	}

	function sanitize_xss($v)
	{
		if (!class_exists('HTMLPurifier') && file_exists(dirname(__FILE__) . "/htmlpurifier/HTMLPurifier.standalone.php"))
		{
			include_once(dirname(__FILE__)."/htmlpurifier/HTMLPurifier.standalone.php");
			$v = html_entity_decode($v,ENT_QUOTES,'UTF-8');
			$purifier = new HTMLPurifier(HTMLPurifier_Config::createDefault());
			$v = $purifier->purify($v);
			return $v;
		}
		else
		{
			$v = html_entity_decode($v,ENT_QUOTES,'UTF-8');

			// source: http://www.phphaven.com/article.php?id=66
			// return strip_tags($v,"<a><abbr><acronym><address><article><aside><b><bdo><big><blockquote><br><caption><cite><code><col><colgroup><dd><del><details><dfn><div><dl><dt><em><figcaption><figure><font><h1><h2><h3><h4><h5><h6><hgroup><hr><i><img><ins><li><map><mark><menu><meter><ol><p><pre><q><rp><rt><ruby><s><samp><section><small><span><strong><style><sub><summary><sup><table><tbody><td><tfoot><th><thead><time><tr><tt><u><ul><var><wbr>");

			// fallback for old version
			// source: http://stackoverflow.com/a/1741568
			$data = $v;
			// Fix &entity\n;
			$data = str_replace( array( '&amp;', '&lt;', '&gt;' ), array( '&amp;amp;', '&amp;lt;', '&amp;gt;' ), $data );
			$data = preg_replace( '/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data );
			$data = preg_replace( '/(&#x*[0-9A-F]+);*/iu', '$1;', $data );
			$data = html_entity_decode( $data, ENT_COMPAT, 'UTF-8' );

			// Remove any attribute starting with "on" or xmlns
			$data = preg_replace( '#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data );

			// Remove javascript: and vbscript: protocols
			$data = preg_replace( '#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data );
			$data = preg_replace( '#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data );
			$data = preg_replace( '#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data );

			// Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
			$data = preg_replace( '#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data );
			$data = preg_replace( '#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data );
			$data = preg_replace( '#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data );

			// Remove namespaced elements (we do not need them)
			$data = preg_replace( '#</*\w+:\w[^>]*+>#i', '', $data );

			do
			{
				// Remove really unwanted tags
				$old_data = $data;
				$data = preg_replace( '#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data );
			}
			while ( $old_data !== $data );

			// we are done...
			return $data;
		}
	}

	// xss fix for url querystring values
	function sanitize_xss_url($url)
	{
		$qstr = parse_url($url, PHP_URL_QUERY);
		if($qstr)
		{
			$qstr = explode("&",$qstr);
			foreach($qstr as &$qs)
			{
				$pair = explode("=",$qs);
				// clean query string value
				$pair[1] = $this->sanitize_xss($pair[1]);
				$qs = $pair[0].'='.$pair[1];
			}
			$qstr = implode("&",$qstr);
			$url = substr($url, 0, strpos($url, "?"))."?".$qstr;
		}
		return $url;
	}

	function is_secure()
	{
		if (
			( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| ( ! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
			|| ( ! empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
			|| (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
			|| (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443)
			|| (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https')
		) {
			return true;
		} else {
			return false;
		}
	}

	function get_searched_value($gid,$field,$op)
	{
		$json = json_decode($_SESSION["jqgrid_{$gid}_searchstr"]);
		
		foreach($json->rules as $r)
		{
			if ($r->field == $field && $r->op == $op)
				return $r->data;
		}
		return false;
	}
}

// In PHP 5.2 or higher we don't need to bring this in
if (!function_exists('json_encode'))
{
	require_once dirname(__FILE__).'/JSON.php';
	function json_encode($arg)
	{
		global $services_json;
		if (!isset($services_json)) {
			$services_json = new Services_JSON();
		}
		return $services_json->encode($arg);
	}

	function json_decode($arg)
	{
		global $services_json;
		if (!isset($services_json)) {
			$services_json = new Services_JSON();
		}
		return $services_json->decode($arg);
	}
}

/**
 * Common function to display errors
 */
if (!function_exists('phpgrid_error'))
{
	function phpgrid_error($msg)
	{
		header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');

		if (is_array($msg) || is_object($msg))
		{
			ob_start();
			print_r($msg);
			die(ob_get_clean());
		}

		die($msg);
	}
}

/**
 * Common function to display custom success messages
 */
if (!function_exists('phpgrid_msg'))
{
	function phpgrid_msg($msg,$fade=1)
	{
		die(json_encode(array("msg"=>$msg, "fade"=>$fade)));
	}
}

/**
 * Internal debug function
 */
if (!function_exists('dd'))
{
	function dd($arr, $exit=0)
	{
		echo "<pre>";
		print_r($arr);
		echo "</pre>";

		if ($exit)
			die;
	}
}

/**
 * Function to encode JS function reference from PHP array
 * http://www.php.net/manual/en/function.json-encode.php#105749
 */
function json_encode_jsfunc($input=array(), $funcs=array(), $level=0)
{
	foreach($input as $key=>$value)
	{
		if (is_array($value))
		{
			$ret = json_encode_jsfunc($value, $funcs, 1);
			$input[$key]=$ret[0];
			$funcs=$ret[1];
		}
		else
		{
			$t_value = preg_replace("/ /","",$value);
			if (substr($t_value,0,9)=='function(')
			{
				$func_key="#".rand()."#";
				$funcs[$func_key]=$value;
				$input[$key]=$func_key;
			}
			// for json data, incase of local array
			else if (substr($value,0,2)=='[{')
			{
				$func_key="#".rand()."#";
				$funcs[$func_key]=$value;
				$input[$key]=$func_key;
			}
		}
	}
  	if ($level==1)
	{
		return array($input, $funcs);
	}
  	else
	{
		$input_json = json_encode($input);
	  	foreach($funcs as $key=>$value)
		{
			$input_json = str_replace('"'.$key.'"', $value, $input_json);
		}
	  	return $input_json;
	}
}

/**
 * Function to encode and decode from utf8
 * http://www.php.net/manual/en/function.json-encode.php#106417
 */
function array_utf8_encode_recursive($dat)
{
	if (is_string($dat)) {
		return utf8_encode($dat);
	}
	if (is_object($dat)) {
		$ovs= get_object_vars($dat);
		$new=$dat;
		foreach ($ovs as $k =>$v)    {
			$new->$k=array_utf8_encode_recursive($new->$k);
		}
		return $new;
	}

	if (!is_array($dat)) return $dat;
	$ret = array();
	foreach($dat as $i=>$d) $ret[$i] = array_utf8_encode_recursive($d);
	return $ret;
}

function array_utf8_decode_recursive($dat)
{
	if (is_string($dat)) {
		return utf8_decode($dat);
	}
	if (is_object($dat)) {
		$ovs= get_object_vars($dat);
		$new=$dat;
		foreach ($ovs as $k =>$v)    {
			$new->$k=array_utf8_decode_recursive($new->$k);
		}
		return $new;
	}

	if (!is_array($dat)) return $dat;
	$ret = array();
	foreach($dat as $i=>$d) $ret[$i] = array_utf8_decode_recursive($d);
	return $ret;
}

if (!defined("is_mobile"))
{
	function is_mobile()
	{
		$useragent=$_SERVER['HTTP_USER_AGENT'];
		if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4)))
			return true;
		else
			return false;
	}
}