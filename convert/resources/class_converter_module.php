<?php
/** 
* Polish Language File developed by mybboard.pl for MyBB Merge System
* Tłumaczenie: szulcu, Gigi
* Poprawki: Ekipa Polskiego Supportu MyBB (mybboard.pl)
* Wersja 1.0
**/
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Converter_Module
{
	public $board = null;

	public function __construct($converter_class)
	{
		global $import_session, $debug, $db;

		// Setup & Share our variables and classes
		require_once MERGE_ROOT."boards/".$import_session['board']."/bbcode_parser.php";
		require_once MERGE_ROOT.'resources/class_cache_handler.php';
		$this->bbcode_parser = new BBCode_Parser();
		$this->get_import = new Cache_Handler();

		// Setup our trackers
		$this->trackers = array();
		$query = $db->simple_select("trackers");
		while($tracker = $db->fetch_array($query))
		{
			$this->trackers['start_'.$tracker['type']] = $tracker['count'];
		}

		$this->board = &$converter_class;
		$this->old_db = &$this->board->old_db;
		$this->board->settings = &$this->settings;
		$this->board->get_import = &$this->get_import;
		$this->board->trackers = &$this->trackers;
		$this->debug = &$debug;
	}

	/**
	 * Fills an array of insert data with default MyBB values if they were not specified
	 *
	 */
	public function process_default_values($values)
	{
		return array_merge($this->default_values, $values);
	}

	public function check_table_type($tables)
	{
		global $output;

		if(!is_array($tables))
		{
			$tables = array($tables);
		}

		if($this->old_db->type == "mysqli" || $this->old_db->type == "mysql")
		{
			foreach($tables as $table)
			{
				$table_sql = $this->old_db->show_create_table($table);
				if(stripos($table_sql, "ENGINE=InnoDB") !== false)
				{
					$output->print_warning("Tabela \"{$table}\" jest aktualnie w formacie InnoDB. Wysoce zalecana jest konwersja jej do typu MyISAM. W innym wypadku mogą wystąpić poważne spadki w wydajności podczas pracy MyBB Merge System.");
					$this->debug->log->warning("{$table} jest w formacie InnoDB. Mogą z tego powodu wystąpić spadki wydajności");
				}
			}
		}
	}


	/**
	 * Used for unit testing by asserting certain values to make sure their outcome is true
	 *
	 */
	function assert($data, $match_data)
	{
		global $variable_name;

		$converted_data = $this->convert_data($data);

		foreach($match_data as $field => $value)
		{
			$value = str_replace("'", "\'", $value);
			$converted_data[$field] = str_replace("'", "\'", $converted_data[$field]);

			$variable_name = $field;
			assert("'{$value}' == '{$converted_data[$field]}'");
		}
	}

	function increment_tracker($type)
	{
		global $db;

		++$this->trackers['start_'.$type];

		$db->write_query("REPLACE INTO ".TABLE_PREFIX."trackers SET count=".intval($this->trackers['start_'.$type]).", type='".$db->escape_string($type)."'");
	}
}

?>