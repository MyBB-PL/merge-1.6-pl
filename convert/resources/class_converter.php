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

class Converter
{
	/**
	 * An array of custom defined errors (i.e. attachments directory permission error)
	 */
	var $errors = array();

	var $debug;

	/**
	 * Class constructor
	 */
	function __construct()
	{
		global $debug;

		$this->debug = &$debug;
		return 'MyBB';
	}

	/**
	 * Create a database connection on the old database we're importing from
	 *
	 */
	function db_connect()
	{
		global $import_session, $cache;

		$this->debug->log->trace0("Ustawianie połączenia do importu bazy danych.");

		// Attempt to connect to the db
		require_once MYBB_ROOT."inc/db_{$import_session['old_db_engine']}.php";

		switch($import_session['old_db_engine'])
		{
			case "sqlite":
				$this->old_db = new DB_SQLite;
				break;
			case "pgsql":
				$this->old_db = new DB_PgSQL;
				break;
			case "mysqli":
				$this->old_db = new DB_MySQLi;
				break;
			default:
				$this->old_db = new DB_MySQL;
		}
		$this->old_db->type = $import_session['old_db_engine'];
		$this->old_db->connect(unserialize($import_session['connect_config']));
		$this->old_db->set_table_prefix($import_session['old_tbl_prefix']);

		define('OLD_TABLE_PREFIX', $import_session['old_tbl_prefix']);
	}

	function db_configuration()
	{
		global $mybb, $output, $import_session, $db, $dboptions, $dbengines, $dbhost, $dbuser, $dbname, $tableprefix;

		// Just posted back to this form?
		if($mybb->input['dbengine'])
		{
			$config_data = $mybb->input['config'][$mybb->input['dbengine']];

			if(strstr($mybb->input['dbengine'], "sqlite") !== false && (strstr($config_data['dbname'], "./") !== false || strstr($config_data['dbname'], "../") !== false))
			{
				$errors[] = "Nie możesz używać względnych adresów URL dla baz danych SQLite. Użyj ścieżki systemu plików, na przykład /home/user/database.db.";
			}
			else if(!file_exists(MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php"))
			{
				$errors[] = 'Wybrano niepoprawny typ silnika bazy danych. Upewnij się, że wybrano go z listy poniżej.';
			}
			else
			{
				// Attempt to connect to the db
				require_once MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php";

				switch($mybb->input['dbengine'])
				{
					case "sqlite":
						$this->old_db = new DB_SQLite;
						break;
					case "pgsql":
						$this->old_db = new DB_PgSQL;
						break;
					case "mysqli":
						$this->old_db = new DB_MySQLi;
						break;
					default:
						$this->old_db = new DB_MySQL;
				}
				$this->old_db->error_reporting = 0;

				$connect_config['type'] = $mybb->input['dbengine'];
				$connect_config['database'] = $config_data['dbname'];
				$connect_config['table_prefix'] = $config_data['tableprefix'];
				$connect_config['hostname'] = $config_data['dbhost'];
				$connect_config['username'] = $config_data['dbuser'];
				$connect_config['password'] = $config_data['dbpass'];
				$connect_config['encoding'] = $config_data['encoding'];

				$connection = $this->old_db->connect($connect_config);
				if(!$connection)
				{
					$errors[] = "Nie można połączyć się z bazą danych na serwerze '{$config_data['dbhost']}' z podaną nazwą użytkownika i hasłem. Czy na pewno te dane są poprawne?";
				}

				if(empty($errors))
				{
					// Need to check if it is actually installed here
					$this->old_db->set_table_prefix($config_data['tableprefix']);

					$check_table = "";
					switch($import_session['board'])
					{
						case "ipb2":
							$check_table = "forum_perms";
							break;
						case "mybb":
							$check_table = "usergroups";
							break;
						case "phpbb2":
							$check_table = "topics";
							break;
						case "phpbb3":
							$check_table = "user_group";
							break;
						case "punbb":
							$check_table = "groups";
							break;
						case "smf":
						case "smf2":
							$check_table = "boards";
							break;
						case "vbulletin3":
							$check_table = "forumpermission";
							break;
						case "xmb":
							$check_table = "vote_desc";
							break;
						case "bbpress":
							$check_table = "usermeta";
							break;
					}

					if($check_table && !$this->old_db->table_exists($check_table))
					{
						$errors[] = "Baza danych silnika {$this->plain_bbname} nie mogła zostać odnaleziona w '{$config_data['dbname']}'. Upewnij się, że baza danych silnika {$this->plain_bbname} istnieje w tej bazie danych z podanym prefiksem do tabel.";
					}
				}

				// No errors? Save import DB info and then return finished
				if(!is_array($errors))
				{
					$output->print_header("Konfiguracja bazy danych silnika {$this->plain_bbname}");
					
					echo "<br />\nSprawdzanie dostępu do bazy danych... <span style=\"color: green\">zakończone powodzeniem.</span><br /><br />\n";
					flush();

					$import_session['old_db_engine'] = $mybb->input['dbengine'];
					$import_session['old_db_host'] = $config_data['dbhost'];
					$import_session['old_db_user'] = $config_data['dbuser'];
					$import_session['old_db_pass'] = $config_data['dbpass'];
					$import_session['old_db_name'] = $config_data['dbname'];
					$import_session['old_tbl_prefix'] = $config_data['tableprefix'];
					$import_session['connect_config'] = serialize($connect_config);
					$import_session['encode_to_utf8'] = intval($mybb->input['encode_to_utf8']);

					// Create temporary import data fields
					create_import_fields();

					sleep(2);

					$import_session['flash_message'] = "Pomyślnie skonfigurowano i połączono z bazą danych.";
					return "finished";
				}
			}
		}

		$output->print_header("Konfiguracja bazy danych silnika {$this->plain_bbname}");

		// Check for errors
		if(is_array($errors))
		{
			$error_list = error_list($errors);
			echo "<div class=\"error\">
			      <h3>Błąd</h3>
				  <p>Wygląda na to, że w podane dane do bazy danych są błędne:</p>
				  {$error_list}
				  <p>Po naprawieniu powyższych błędów będzie można kontynuować proces importu.</p>
				  </div>";

		}
		else
		{
			echo "<p>Podaj dane do bazy danych istniejącej instalacji silnika {$this->plain_bbname}.</p>";
			if($import_session['old_db_engine'])
			{
				$mybb->input['dbengine'] = $import_session['old_db_engine'];
			}
			else
			{
				$mybb->input['dbengine'] = $mybb->config['database']['type'];
			}

			if($import_session['old_db_host'])
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbhost'] = $import_session['old_db_host'];
			}
			else
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbhost'] = 'localhost';
			}

			if($import_session['old_tbl_prefix'])
			{
				$mybb->input['config'][$mybb->input['dbengine']]['tableprefix'] = $import_session['old_tbl_prefix'];
			}
			else
			{
				$prefix_suggestion = "";
				switch($import_session['board'])
				{
					case "ipb2":
						$prefix_suggestion = "ibf_";
						break;
					case "mybb":
						$prefix_suggestion = "mybb_";
						break;
					case "phpbb2":
						$prefix_suggestion = "phpbb_";
						break;
					case "phpbb3":
						$prefix_suggestion = "phpbb_";
						break;
					case "punbb":
						$prefix_suggestion = "punbb_";
						break;
					case "smf":
					case "smf2":
						$prefix_suggestion = "smf_";
						break;
					case "vbulletin3":
						$prefix_suggestion = "";
						break;
					case "xmb":
						$prefix_suggestion = "xmb_";
						break;
					case "bbpress":
						$prefix_suggestion = "bb_";
						break;
				}
				$mybb->input['config'][$mybb->input['dbengine']]['tableprefix'] = $prefix_suggestion;
			}

			if($import_session['old_db_user'])
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbuser'] = $import_session['old_db_user'];
			}
			else
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbuser'] = '';
			}

			if($import_session['old_db_name'])
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbname'] = $import_session['old_db_name'];
			}
			else
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbname'] = '';
			}
		}

		$import_session['autorefresh'] = "";
		$mybb->input['autorefresh'] = "no";

		$output->print_database_details_table($this->plain_bbname);

		$output->print_footer();
	}

	/**
	 * Checks if the current module is done importing or not
	 *
	 */
	function check_if_done()
	{
		global $import_session;

		$this->debug->log->trace2("Sprawdzanie pozostałej do zaimportowania zawartości: {$import_session['module']}");

		$module_name = str_replace(array("import_", ".", ".."), "", $import_session['module']);

		$this->debug->log->datatrace("total_{$module_name}, start_{$module_name}", array($import_session['total_'.$module_name], $this->trackers['start_'.$module_name]));

		// If there are more work to do, continue, or else, move onto next module
		if($import_session['total_'.$module_name] - $this->trackers['start_'.$module_name] <= 0 || $import_session['total_'.$module_name] == 0)
		{
			$import_session['disabled'][] = 'import_'.$module_name;
			$import_session['flash_message'] = "Pomyślnie zakończono import {$this->settings['friendly_name']}.";
			return "finished";
		}
	}

	/**
	 * Used for modules if there are handleable errors during the import process
	 *
	 */
	function set_error_notice_in_progress($error_message)
	{
		global $output, $import_session;

		$this->debug->log->error($error_message);

		$import_session['error_logs'][$import_session['module']][] = $error_message;

		$output->set_error_notice_in_progress($error_message);
	}
}

?>