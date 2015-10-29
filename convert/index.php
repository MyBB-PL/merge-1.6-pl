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
 * NOT TO BE DISTRIBUTED WITH THE MYBB PACKAGE
 */

$load_timer = microtime(true);

header('Content-type: text/html; charset=utf-8');
@set_time_limit(0);
@ini_set('display_errors', true);
@ini_set('memory_limit', -1);

$merge_version = "1.6.14";
$version_code = 1614;

// Load core files
define("MYBB_ROOT", dirname(dirname(__FILE__)).'/');
define("MERGE_ROOT", dirname(__FILE__).'/');
define("IN_MYBB", 1);
define("WRITE_LOGS", 1);
define("TIME_NOW", time());

if(function_exists('date_default_timezone_set') && !ini_get('date.timezone'))
{
	date_default_timezone_set('GMT');
}

require_once MERGE_ROOT.'resources/class_debug.php';
$debug = new Debug;

$debug->log->trace0("Uruchomiono MyBB Merge System: \$version_code: {$version_code} \$merge_version: {$merge_version}");

require_once MYBB_ROOT."inc/config.php";
if(!isset($config['database']['type']))
{
	if($config['dbtype'])
	{
		die('MyBB musi zostać zaktualizowane przed uruchomieniem MyBB Merge System.');
	}
	else
	{
		die('MyBB musi zostać zainstalowane przed uruchomieniem MyBB Merge System.');
	}
}

// If we have register globals on and we're coming from the db config page it seems to screw up the $config variable
if(@ini_get("register_globals") == 1)
{
	$config_copy = $config;
}

require_once MYBB_ROOT."inc/class_core.php";
$mybb = new MyBB;

if(@ini_get("register_globals") == 1)
{
	$config = $config_copy;
	unset($config_copy);
}

require_once MYBB_ROOT."inc/class_error.php";
require_once MERGE_ROOT."resources/class_error.php";
$error_handler = new debugErrorHandler();

// Include the files necessary for converting
require_once MYBB_ROOT."inc/class_timers.php";
$timer = new timer;

require_once MYBB_ROOT.'inc/class_datacache.php';
$cache = new datacache;

require_once MYBB_ROOT."inc/functions_rebuild.php";
require_once MYBB_ROOT."inc/functions.php";
require_once MYBB_ROOT."inc/settings.php";
$mybb->settings = $settings;

if(substr($mybb->settings['uploadspath'], 0, 2) == "./" || substr($mybb->settings['uploadspath'], 0, 3) == "../")
{
	$mybb->settings['uploadspath'] = MYBB_ROOT.$mybb->settings['uploadspath'];
}
else
{
	$mybb->settings['uploadspath'] = $mybb->settings['uploadspath'];
}

require_once MYBB_ROOT."inc/class_xml.php";

// Include the converter resources
require_once MERGE_ROOT."resources/functions.php";
require_once MERGE_ROOT.'resources/output.php';
$output = new converterOutput;

require_once MERGE_ROOT.'resources/class_converter.php';

$mybb->config = $config;

require_once MYBB_ROOT."inc/db_".$config['database']['type'].".php";
switch($config['database']['type'])
{
	case "sqlite":
		$db = new DB_SQLite;
		break;
	case "pgsql":
		$db = new DB_PgSQL;
		break;
	case "mysqli":
		$db = new DB_MySQLi;
		break;
	default:
		$db = new DB_MySQL;
}

// Check if our DB engine is loaded
if(!extension_loaded($db->engine))
{
	// Throw our super awesome db loading error
	$mybb->trigger_generic_error("sql_load_error");
}

if(function_exists('mb_internal_encoding'))
{
	@mb_internal_encoding("UTF-8");
}

// Connect to the installed MyBB database
define("TABLE_PREFIX", $config['database']['table_prefix']);
$db->connect($config['database']);
$db->set_table_prefix(TABLE_PREFIX);
$db->type = $config['database']['type'];

// Start up our main timer so we can aggregate performance data
$start_timer = microtime(true);

// Get the import session cache if exists
$import_session = $cache->read("import_cache", 1);

// Setup our arrays if they don't exist yet
if(!$import_session['resume_module'])
{
	$import_session['resume_module'] = array();
}

if(!$import_session['disabled'])
{
	$import_session['disabled'] = array();
}

if(!$import_session['resume_module'])
{
	$import_session['resume_module'] = array();
}

if($mybb->version_code < 1600 || $mybb->version_code >= 1700)
{
	$output->print_error("MyBB Merge System do uruchomienia wymaga MyBB w wersji 1.6.");
}

// Are we done? Generate the report!
if(isset($mybb->input['reportgen']) && !empty($import_session['board']))
{
	$debug->log->event("Generowanie raportu końcowego");

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$import_session['board'].".php";
	$class_name = strtoupper($import_session['board'])."_Converter";

	$board = new $class_name;

	// List of statistics we'll be using
	$import_stats = array(
		'total_usergroups' => 'grupy użytkowników',
		'total_users' => 'użytkowników',
		'total_categories' => 'kategorii',
		'total_forums' => 'działów',
		'total_forumperms' => 'uprawnień do działów',
		'total_moderators' => 'moderatorów',
		'total_threads' => 'wątków',
		'total_posts' => 'postów',
		'total_attachments' => 'załączników',
		'total_polls' => 'ankiet',
		'total_pollvotes' => 'głosów w ankietach',
		'total_privatemessages' => 'prywatnych wiadomości',
		'total_events' => 'wydarzeń',
		'total_icons' => 'ikon',
		'total_smilies' => 'emotikon',
		'total_settings' => 'ustawień',
		'total_attachtypes' => 'typów załączników'
	);

	$begin_date = gmdate("r", $import_session['start_date']);
	$end_date = gmdate("r", $import_session['end_date']);

	$import_session['newdb_query_count'] = my_number_format($import_session['newdb_query_count']);
	$import_session['olddb_query_count'] = my_number_format($import_session['olddb_query_count']);
	$import_session['total_query_time_friendly'] = my_friendly_time($import_session['total_query_time']);

	if(empty($import_session['total_query_time_friendly']))
	{
		$import_session['total_query_time_friendly'] = "0 sekund";
	}

	$generation_time = gmdate("r");

	$year = gmdate("Y");

	$debug->log->trace2("Generowanie raportu w formacie {$mybb->input['reportgen']}");

	// Did we request it in plain txt format?
	if($mybb->input['reportgen'] == "txt")
	{
		$ext = "txt";
		$mime = "text/plain";

		// Generate the list of all the modules we ran (Threads, Posts, Users, etc)
		$module_list = "";
		foreach($board->modules as $key => $module)
		{
			if(in_array($key, $import_session['completed']))
			{
				$module_list .= htmlspecialchars_decode($module['name'])."\r\n";
			}
		}

		if(empty($module_list))
		{
			$module_list = "brak\r\n";
		}

		$errors = "";
		if(!empty($import_session['error_logs']))
		{
			foreach($board->modules as $key => $module)
			{
				if(array_key_exists($key, $import_session['error_logs']))
				{
					$errors .= "{$module['name']}:\r\n";
					$errors .= "\t".implode("\r\n\t", $import_session['error_logs'][$key])."\r\n";
				}
			}
		}

		if(empty($errors))
		{
			$errors = "brak\r\n";
		}

		// This may seem weird but it's not. We determine the longest length of the title,
		// so we can then pad it all neatly in the txt file.
		$max_len = 0;
		foreach($import_stats as $key => $title)
		{
			if(array_key_exists($key, $import_session) && strlen($title) > $max_len)
			{
				$max_len = strlen($title);
			}
		}

		// Generate the list of stats we have (Amount of threads imported, amount of posts imported, etc)
		foreach($import_stats as $key => $title)
		{
			if(array_key_exists($key, $import_session))
			{
				$title = "{$title}: ";

				// Determine the amount of spaces we need to line it all up nice and neatly.
				$title = str_pad($title, $max_len+2);
				$import_totals .= "{$title}".my_number_format($import_session[$key])."\r\n";
			}
		}
		
		$output = <<<EOF
MyBB Merge System - raport importu
--------------------------------------------------------
Witamy w raporcie wygenerowanym przez MyBB Merge System.
Ten raport zawiera krótki przegląd informacji na temat przeprowadzonych działań.

Główne informacje
-------
	Połączone forum:    {$board->plain_bbname}
	Import rozpoczęto:  {$begin_date}
	Import zakończono:  {$end_date}

Statystyki zapytań do bazy danych
-------------------------
	Liczba zapytań do bazy danych MyBB:             {$import_session['newdb_query_count']}
	Liczba zapytań do bazy danych starego silnika:  {$import_session['olddb_query_count']}
	Łączny czas wykonywania zapytań:                {$import_session['total_query_time_friendly']}

Moduły
-------
Praca poniższych modułów została zakończona:
{$module_list}

Statystyki importu
-----------------
Zaimportowano następujące elementy z silnika {$board->bbname}:
{$import_totals}

Błędy
------
Podczas importu system napotkał poniższe błędy:
{$errors}

Problemy
---------
Tabela "mybb_debuglogs" znajdująca się w bazie danych forum zawiera
informacje debugowania na temat przeprowadzonych działań. W przypadku problemów
zwróć się o pomoc na http://community.mybb.com/.

--------------------------------------------------------
Raport wygenerowano: {$generation_time}
EOF;
	}

	// Ah, our users requests our pretty html format!
	if($mybb->input['reportgen'] == "html")
	{
		$ext = "html";
		$mime = "text/html";

		// Generate the list of all the modules we ran (Threads, Posts, Users, etc)
		foreach($board->modules as $key => $module)
		{
			if(in_array($key, $import_session['completed']))
			{
				$module_list .= "<li>{$module['name']}</li>\n";
			}
		}

		if(empty($module_list))
		{
			$module_list = "<li>brak</li>\n";
		}

		// Generate the list of stats we have (Amount of threads imported, amount of posts imported, etc)
		foreach($import_stats as $key => $title)
		{
			if(array_key_exists($key, $import_session))
			{
				$import_totals .= "<dt>{$title}</dt>\n";
				$import_totals .= "<dd>".my_number_format($import_session[$key])."</dd>\n";
			}
		}

		if(empty($import_totals))
		{
			$import_totals = "<dt>brak</dt>\n";
		}

		$errors = "";
		if(!empty($import_session['error_logs']))
		{
			foreach($board->modules as $key => $module)
			{
				if(array_key_exists($key, $import_session['error_logs']))
				{
					$errors .= "<li><strong>{$module['name']}:</strong>\n";
					$errors .= "<ul><li>".implode("</li>\n<li>", $import_session['error_logs'][$key])."</li></ul>\n";
					$errors .= "</li>";
				}
			}
		}

		if(empty($errors))
		{
			$errors = "<li>brak</li>\n";
		}

		$output = <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>Raport wygenerowany przez MyBB Merge System</title>
	<style type="text/css">
		body {
			font-family: Verdana, Arial, sans-serif;
			font-size: 12px;
			background: #efefef;
			color: #000000;
			margin: 0;
		}

		#container {
			margin: auto auto;
			width: 780px;
			background: #fff;
			border: 1px solid #ccc;
			padding: 20px;
		}

		h1 {
			font-size: 25px;
			margin: 0;
			background: #ddd;
			padding: 10px;
		}

		h2 {
			font-size: 18px;
			margin: 0;
			padding: 10px;
			background: #efefef;
		}

		h3 {
			font-size: 14px;
			clear: left;
			border-bottom: 1px dotted #aaa;
			padding-bottom: 4px;
		}

		ul, li {
			padding: 0;
		}

		#general p, #modules p, #import p, ul, li, dl {
			margin-left: 30px;
		}

		dl dt {
			float: left;
			width: 300px;
			padding-bottom: 10px;
			font-weight: bold;
		}

		dl dd {
			padding-bottom: 10px;
		}

		#footer {
			border-top: 1px dotted #aaa;
			padding-top: 10px;
			font-style: italic;
		}

		.float_right {
			float: right;
		}
	</style>
</head>
<body>
<div id="container">
	<h1>MyBB Merge System</h1>
	<h2>Raport z dokonanego importu</h2>
	<p>Witamy w raporcie wygenerowanym przez MyBB Merge System. Ten raport zawiera krótki przegląd informacji na temat przeprowadzonych działań.</p>
	<div id="general">
		<h3>Główne statystyki</h3>
		<p>Podczas importu połączono forum oparte o silnik {$board->plain_bbname} z Twoim aktualnym forum.</p>
		<dl>
			<dt>Import rozpoczęto</dt>
			<dd>{$begin_date}</dd>

			<dt>Import zakończono</dt>
			<dd>{$end_date}</dd>
		</dl>
	</div>
	<div id="database">
		<h3>Statystyki zapytań do bazy danych</h3>
		<dl>
			<dt>Liczba zapytań do bazy danych MyBB</dt>
			<dd>{$import_session['newdb_query_count']}</dd>
			
			<dt>Liczba zapytań do bazy danych silnika {$board->plain_bbname}</dt>
			<dd>{$import_session['olddb_query_count']}</dd>

			<dt>Łączny czas wykonywania zapytań</dt>
			<dd>{$import_session['total_query_time_friendly']}</dd>
		</dl>
	</div>
	<div id="modules">
		<h3>Moduły</h3>
		<p>Praca poniższych modułów została zakończona:</p>
		<ul>
		{$module_list}
		</ul>
	</div>
	<div id="import">
		<h3>Statystyki importu</h3>
		<p>Zaimportowano następujące elementy z silnika {$board->bbname}:</p>
		<dl>
		{$import_totals}
		</dl>
	</div>
	<div id="errors">
		<h3>Błędy</h3>
		<p>Podczas importu system napotkał poniższe błędy:</p>
		<ul>
		{$errors}
		</ul>
	</div>
	<div id="problems">
		<h3>Problemy</h3>
		<p>Tabela "mybb_debuglogs" znajdująca się w bazie danych forum zawiera informacje debugowania na temat przeprowadzonych działań. W przypadku problemów zwróć się o pomoc na <a href="http://community.mybb.com/">forum wsparcia MyBB</a>.</p>
	</div>
	<div id="footer">
		<div class="float_right"><a href="http://www.mybboard.net">MyBB</a> © 2002-{$year} <a href="http://www.mybboard.net">MyBB Group</a>, Polskie tłumaczenie © 2007-{$year} <a href="http://www.mybboard.pl">Polski Support MyBB</a></div>
		<div>Raport wygenerowano {$generation_time}</div>
	</div>
</div>
</body>
</html>
EOF;

	}

	header("HTTP/1.1 200 OK");
	header("Status: 200 OK");
	header('Content-Type: '.$mime);
	header("Content-Disposition: attachment; filename=\"report_".time().".{$ext}\"");
	header("Content-Length: ".strlen($output));

	echo $output;

	$debug->log->event("Wygenerowano raport");
	exit;
}


// The placement of this function is important and it should stay here. $import_session['finished_convert'] is set
// during $mybb->input['action'] == 'finish' which displays the last page and also displays the links to the Report Generations.
// The Report Generations are run right above this piece of code which we "exit;" before we reach this code so we don't clear out
// our statistics we've got saved. This will only run the next time someone visits the merge system script after we visit the
// 'finished' page and we're not downloading a report for the last merge.
if($import_session['finished_convert'] == '1')
{
	$debug->log->event("Uruchamianie czyszczenia po imporcie");

	// Delete import session cache
	$import_session = null;
	update_import_session();
}

if($mybb->input['board'])
{
	$debug->log->event("Ustawianie modułu forum: {$mybb->input['board']}");

	// Sanatize and check if it exists.
	$mybb->input['board'] = str_replace(".", "", $mybb->input['board']);

	$debug->log->trace1("Wczytywanie modułu forum: {$mybb->input['board']}");

	if(!file_exists(MERGE_ROOT."boards/".$mybb->input['board'].".php"))
	{
		$output->print_error("Wybrany moduł nie istnieje.");
	}

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$mybb->input['board'].".php";
	$class_name = strtoupper($mybb->input['board'])."_Converter";

	$board = new $class_name;

	if($board->requires_loginconvert == true)
	{
		$debug->log->trace1("plugin loginvonvert jest wymagany aby włączyć ten moduł");

		if(!file_exists(MYBB_ROOT."inc/plugins/loginconvert.php") && file_exists(MYBB_ROOT."convert/loginconvert.php"))
		{
			$debug->log->trace2("Próba przeniesienia pliku convert/loginconvert.php do inc/plugins/loginconvert.php");
			$writable = @fopen(MYBB_ROOT.'inc/plugins/loginconvert.php', 'wb');
			if($writable)
			{
				@fwrite($writable, file_get_contents(MYBB_ROOT."convert/loginconvert.php"));
				@fclose($writable);
				@my_chmod(MYBB_ROOT.'inc/plugins/loginconvert.php', '0555');
				$debug->log->trace2("Pomyślnie przeniesiono plik loginconvert.php do inc/plugins/");
			}
		}

		if(!file_exists(MYBB_ROOT."inc/plugins/loginconvert.php"))
		{
			$debug->log->error("Nie udało się automatycznie skonfigurować pliku loginconvert.php. Wykonywanie skryptu zostanie przerwane");

			$output->print_header("MyBB Merge System - import haseł użytkowników");
			
			echo "			<div class=\"error\">\n				";
			echo "<h3>Błąd</h3>";
			echo "MyBB Merge System nie może kontynuować dopóki do folderu z pluginami MyBB (inc/plugins) nie zostanie skopiowany plik loginconvert.php (znajdziesz go w katalogu z MyBB Merge System).";
			echo "\n			</div>";
			
			echo "<p>Więcej informacji można odnaleźć <a href=\"http://wiki.mybb.com/index.php/Running_the_Merge_System#loginconvert.php_plugin\" target=\"_blank\">tutaj</a> (j. ang.).</p>
				<p>Po skopiowaniu pliku naciśnij przycisk Dalej aby kontynuować.</p>
				<input type=\"hidden\" name=\"board\" value=\"".htmlspecialchars_uni($mybb->input['board'])."\" />";
			$output->print_footer();
		}

		$plugins_cache = $cache->read("plugins");
		$active_plugins = $plugins_cache['active'];

		$active_plugins['loginconvert'] = "loginconvert";

		$plugins_cache['active'] = $active_plugins;
		$cache->update("plugins", $plugins_cache);

		$debug->log->trace1("Aktywowano plugin loginconvert");
	}

	// Save it to the import session so we don't have to carry it around in the url/source.
	$import_session['board'] = $mybb->input['board'];
}

// Did we just start running a specific module (user import, thread import, post import, etc)
if($mybb->input['module'])
{
	$debug->log->event("Ustawianie modułu forum: {$mybb->input['module']}");

	// Set our $resume_module variable to the last module we were working on (if there is one)
	// incase we come back to it at a later time.
	$resume_module = $import_session['module'];

	if(!array_search($import_session['module'], $import_session['resume_module']))
	{
		$import_session['resume_module'][] = $resume_module;
	}

	// Save our new module we're working on to the import session
	$import_session['module'] = $mybb->input['module'];
}

// Otherwise show them the agreement and ask them to agree to it to continue.
if(!$import_session['first_page'] && !$mybb->input['first_page'])
{
	$debug->log->event("Pokazywanie strony powitania/umowy");
	
	define("BACK_BUTTON", false);
	
	$output->print_header("Witamy");
	
	echo "<script type=\"text/javascript\">function button_undisable() { document.getElementById('main_submit_button').disabled = false; document.getElementById('main_submit_button').className = 'submit_button'; } window.onload = button_undisable; </script>";
	
	echo "<p>Witamy w MyBB Merge System. MyBB Merge System został zaprojektowany w celu umożliwienia importu z innych silników forów dyskusyjnych do MyBB 1.6. W dodatku umożliwia on <i>połączenie</i> kilku forów opartych o MyBB w jedno.<br /><br /> Szczegółowy poradnik można przeczytać w angielskiej wiki: <a href=\"http://docs.mybb.com/Merge_System\" target=\"_blank\">Merge System</a></p>
		<input type=\"hidden\" name=\"first_page\" value=\"1\" />";
	
	echo '<input type="checkbox" name="allow_anonymous_info" value="1" id="allow_anonymous" checked="checked" /> <label for="allow_anonymous"> Wyślij anonimowe statystyki do twórców MyBB</label> (<a href="http://wiki.mybboard.net/index.php/Running_the_Merge_System#Anonymous_Statistics" style="color: #555;" target="_blank"><small>Jakie informacje są wysyłane?</small></a> (j. ang.))';
	
	$output->print_warning("MyBB Merge system <u><strong>nie służy</strong></u> do aktualizacji forów MyBB. Upewnij się, że przed rozpoczęciem procesu importu lub łączenia wszystkie pluginy, które mogą mieć wpływ na te procesy zostały <strong>wyłączone</strong> na obydwu silnikach. <strong>Wysoce zalecane</strong> jest również wykonanie kopii zapasowej bazy danych obu silników.", "Uwaga");

	echo '<noscript>';
	$output->print_warning('Wygląda na to, że w przeglądarce, której używasz, została wyłączona obsługa skryptów JavaScript. MyBB Merge System wymaga, aby ich obsługa była włączona w celu poprawnego działania. Po włączeniu obsługi JavaScript w przeglądarce, odśwież tę stronę.');
	echo '</noscript>';
	
	$output->print_footer("", "", 1, false, "Dalej", "id=\"main_submit_button\" disabled=\"disabled\"", "submit_button_disabled");
}


// Did we just pass the requirements check?
if($mybb->input['requirements_check'] == 1 && $import_session['requirements_pass'] == 1 && $mybb->request_method == "post")
{
	$debug->log->event("Pomyślnie sprawdzono wymagania");

	// Save the check to the import session and move on.
	$import_session['requirements_check'] = 1;

	update_import_session();
}
// Otherwise show our requirements check to our user
else if(!$import_session['requirements_check'] || ($mybb->input['first_page'] == 1 && $mybb->request_method == "post") || !$import_session['requirements_pass'])
{
	$debug->log->event("Pokazywanie strony wymagań");

	$import_session['allow_anonymous_info'] = intval($mybb->input['allow_anonymous_info']);
	$import_session['first_page'] = 1;

	define("BACK_BUTTON", false);

	$errors = array();
	$checks = array();

	$output->print_header("Sprawdzanie wymagań");

	$checks['version_check_status'] = '<span class="pass">aktualna</span>';

	// Check for a new version of the Merge System!
	require_once MYBB_ROOT."inc/class_xml.php";
	$contents = merge_fetch_remote_file("http://www.mybb.com/merge_version_check.php?ver=160");
	if($contents)
	{
		$parser = new XMLParser($contents);
		$tree = $parser->get_tree();

		$latest_code = $tree['mybb_merge']['version_code']['value'];
		$latest_version = "<strong>".$tree['mybb_merge']['latest_version']['value']."</strong> (".$latest_code.")";
		if($latest_code > $version_code)
		{
			$errors['version_check'] = "Używana przez Ciebie wersja MyBB Merge System jest nieaktualna! W związku z tym może ona nie działać właściwie do czasu aktualizacji. Najnowsza wersja to: <span style=\"color: #C00;\">".$latest_version."</span> (<a href=\"http://mybb.com/downloads/merge-system\" target=\"_blank\">Pobierz</a>)";
			$checks['version_check_status'] = '<span class="fail">nieaktualny</span>';
			$debug->log->warning("Używana wersja MyBB Merge System jest nieaktualna");
		}
	}

	// Uh oh, problemos mi amigo?
	if(!$contents || !$latest_code)
	{
		$checks['version_check_status'] = '<span class="pass"><i>nie można zweryfikować</i></span>';
		$debug->log->warning("Nie można zweryfikować wersji forum w porównaniu do najnowszej na mybb.com");
	}

	// Check upload directory is writable
	$attachmentswritable = @fopen(MYBB_ROOT.'uploads/test.write', 'w');
	if(!$attachmentswritable)
	{
		$errors['attachments_check'] = 'Katalog zawierający załączniki (/uploads/) nie ma praw zapisu. Nadaj mu odpowiednie uprawnienia <a href="http://wiki.mybb.com/index.php/CHMOD%20Files" target="_blank">chmod</a> przed kontynuowaniem.';
		$checks['attachments_check_status'] = '<span class="fail"><strong>niezapisywalny</strong></span>';
		@fclose($attachmentswritable);
		$debug->log->trace0("Katalog załączników jest niezapisywalny");
	}
	else
	{
		$checks['attachments_check_status'] = '<span class="pass">zapisywalny</span>';
		@fclose($attachmentswritable);
		@my_chmod(MYBB_ROOT.'uploads', '0777');
		@my_chmod(MYBB_ROOT.'uploads/test.write', '0777');
		@unlink(MYBB_ROOT.'uploads/test.write');
		$debug->log->trace0("Katalog załączników jest zapisywalny");
	}

	if(!empty($errors))
	{
		$output->print_warning(error_list($errors), "Sprawdzanie wymagań nie powiodło się:");
	}

	echo '<p><div class="border_wrapper">
			<div class="title">Sprawdzanie wymagań</div>
		<table class="general" cellspacing="0">
		<thead>
			<tr>
				<th colspan="2" class="first last">Wymagania</th>
			</tr>
		</thead>
		<tbody>
		<tr class="first">
			<td class="first">Wersja Merge System:</td>
			<td class="last alt_col">'.$checks['version_check_status'].'</td>
		</tr>
		<tr class="alt_row">
			<td class="first">Katalog zawierający logi:</td>
			<td class="last alt_col">'.$checks['attachments_check_status'].'</td>
		</tr>
		</tbody>
		</table>
		</div>
		</p>
		<input type="hidden" name="requirements_check" value="1" />';

	if(!empty($errors))
	{
		$import_session['requirements_pass'] = 0;
		echo '<p><strong>Po naprawieniu powyższych błedów naciśnij przycisk "Sprawdź ponownie" aby spróbować ponownie zweryfikować wymagania.</strong></p>';
		$output->print_footer("", "", 1, false, "Sprawdź ponownie");
	}
	else
	{
		$import_session['requirements_pass'] = 1;
		echo '<p><strong>Gratulujemy, wszystkie wymagania zostały spełnionie! Naciśnij przycisk "Dalej" aby kontynuować.</strong></p>';
		$output->print_footer("", "", 1, false);
	}
}

// If no board is selected then we show the main page where users can select a board
if(!$import_session['board'])
{
	$debug->log->event("Pokazywanie listy for");
	$output->board_list();
}
// Show the completion page
elseif(isset($mybb->input['action']) && $mybb->input['action'] == 'completed')
{
	$debug->log->event("Pokazywanie strony przeprowadzania działań");

	$import_session['finished_convert'] = 1;
	$import_session['agreement'] = 0;
	$import_session['first_page'] = 0;

	$output->finish_conversion();
}
// Perhaps we have selected to stop converting or we are actually finished
elseif(isset($mybb->input['action']) && $mybb->input['action'] == 'finish')
{
	$debug->log->event("Pokazywanie strony czyszczenia po wykonaniu działań");

	define("BACK_BUTTON", false);

	$output->print_header("MyBB Merge System - ostatni krok: czyszczenie");

	// Delete import fields and update our cache's
	$output->construct_progress_bar();

	echo "<br />\nPrzeprowadzanie ostatecznego czyszczenia i konserwacji (to może chwilę potrwać)... \n";
	flush();

	delete_import_fields();

	$cache->update_stats();
	$output->update_progress_bar(30);

	$cache->update_usergroups();
	$output->update_progress_bar(60);

	$cache->update_forums();
	$output->update_progress_bar(90);

	$cache->update_forumpermissions();
	$output->update_progress_bar(120);

	$cache->update_moderators();
	$output->update_progress_bar(150);

	$cache->update_usertitles();
	$output->update_progress_bar(180);

	// Update import session cache
	$import_session['end_date'] = time();

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$import_session['board'].".php";
	$class_name = strtoupper($import_session['board'])."_Converter";

	$board = new $class_name;

	// List of statistics we'll be using
	$import_stats = array(
		'total_usergroups' => 'grup użytkowników',
		'total_users' => 'użytkowników',
		'total_cats' => 'kategorii',
		'total_forums' => 'działów',
		'total_forumperms' => 'uprawnień do działów',
		'total_mods' => 'moderatorów',
		'total_threads' => 'wątków',
		'total_posts' => 'postów',
		'total_attachments' => 'załączników',
		'total_polls' => 'ankiet',
		'total_pollvotes' => 'głosów w ankietach',
		'total_privatemessages' => 'prywatnych wiadomości',
		'total_events' => 'wydarzeń',
		'total_settings' => 'ustawień',
	);

	$post_data = array();

	// Are we sending anonymous data from the conversion?
	if($import_session['allow_anonymous_info'] == 1)
	{
		$debug->log->trace0("Wysyłanie anonimowych statystyk po imporcie");

		// Prepare data
		$post_data['post'] = "1";
		$post_data['title'] = $mybb->settings['bbname'];

		foreach($board->modules as $key => $module)
		{
			if(in_array($key, $import_session['completed']))
			{
				$post_data[$key] = "1";
			}
			else
			{
				$post_data[$key] = "0";
			}
		}

		// Generate the list of stats we have (Amount of threads imported, amount of posts imported, etc)
		foreach($import_stats as $key => $title)
		{
			if(array_key_exists($key, $import_session))
			{
				$post_data[$key]  = $import_session[$key];
			}
		}

		$post_data['newdb_query_count'] = intval($import_session['newdb_query_count']);
		$post_data['olddb_query_count'] = intval($import_session['olddb_query_count']);
		$post_data['start_date'] = $import_session['start_date'];
		$post_data['end_date'] = $import_session['end_date'];
		$post_data['board'] = $import_session['board'];
		$post_data['return'] = "1";
		$post_data['rev'] = $revision;
	}

	// Try and send statistics
	merge_fetch_remote_file("http://www.mybb.com/stats/mergesystem.php", $post_data);

	$import_session['allow_anonymous_info'] = 0;

	update_import_session();

	$output->update_progress_bar(200);

	echo "zakończono.<br />\n";
	flush();

	// We cannot do a header() redirect here because on some servers with gzip or zlib auto compressing content, it creates an  Internal Server Error.
	// Who knows why. Maybe it wants to send the content to the browser after it trys and redirects?
	echo "<br /><br />\nProszę czekać...<meta http-equiv=\"refresh\" content=\"2; url=index.php?action=completed\">";
	exit;
}
elseif($import_session['counters_cleanup'])
{
	$debug->log->event("Pokazywanie strony czyszczenia liczników");

	define("BACK_BUTTON", false);

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$import_session['board'].".php";
	$class_name = strtoupper($import_session['board'])."_Converter";

	$board = new $class_name;

	require_once MERGE_ROOT.'resources/class_converter_module.php';
	require_once MERGE_ROOT.'resources/modules/posts.php';
	$module = new Converter_Module_Posts($board);

	$module->counters_cleanup();

	update_import_session();

	// Now that all of that is taken care of, refresh the page to continue on to whatever needs to be done next.
	// We cannot do a header() redirect here because on some servers with gzip or zlib auto compressing content, it creates an  Internal Server Error.
	// Who knows why. Maybe it wants to send the content to the browser after it trys and redirects?
	echo "<meta http-equiv=\"refresh\" content=\"0; url=index.php\">";;
	exit;
}
// Otherwise that means we've selected a module to run or we're in one
elseif($import_session['module'] && $mybb->input['action'] != 'module_list')
{
	$debug->log->event("Uruchamianie wybranego modułu");

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$import_session['board'].".php";
	$class_name = strtoupper($import_session['board'])."_Converter";

	$board = new $class_name;

	// Are we ready to configure out database details?
	if($import_session['module'] == "db_configuration")
	{
		$debug->log->trace0("Konfigurowanie wybranego modułu");

		// Show the database details configuration
		$result = $board->db_configuration();
	}
	// We've selected a module (or we're in one) that is valid
	elseif($board->modules[$import_session['module']])
	{
		$debug->log->trace0("Ustawianie wybranego modułu");

		$module_name = str_replace(array("import_", ".", ".."), "", $import_session['module']);

		require_once MERGE_ROOT.'resources/class_converter_module.php';
		require_once MERGE_ROOT.'resources/modules/'.$module_name.'.php';
		require_once MERGE_ROOT."boards/".$import_session['board']."/".$module_name.".php";

		$importer_class_name = strtoupper($import_session['board'])."_Converter_Module_".ucfirst($module_name);

		$module = new $importer_class_name($board);

		// Open our DB Connection
		$module->board->db_connect();

		// See how many we have to convert
		$module->fetch_total();

		// Check to see if perhaps we're finished already
		if($module->board->check_if_done())
		{
			// If we have anything to do "on finish"
			if(method_exists($module, "finish"))
			{
				$module->finish();
			}

			$result = "finished";
		}
		// Otherwise, run the module
		else
		{
			// Get number of posts per screen from the form if it was just submitted
			if(isset($mybb->input[$module_name.'_per_screen']))
			{
				$import_session[$module_name.'_per_screen'] = intval($mybb->input[$module_name.'_per_screen']);

				// This needs to be here so if we "Pause" (aka terminate script execution) our "per screen" amount will still be saved
				update_import_session();
			}

			// Do we need to do any setting up or checking before we start the actual import?
			if(method_exists($module, "pre_setup"))
			{
				$module->pre_setup();

				// Incase we updated any $import_session variables while we were setting up
				update_import_session();
			}

			// Have we set our "per screen" amount yet?
			if($import_session[$module_name.'_per_screen'] <= 0 || $module->is_errors)
			{
				// Print our header
				$output->print_header($module->board->modules[$import_session['module']]['name']);

				// Do we need to check a table type?
				if(!empty($module->settings['check_table_type']))
				{
					$module->check_table_type($module->settings['check_table_type']);
				}
				$output->print_per_screen_page($module->settings['default_per_screen']);
			}
			else
			{
				// Yes, we're actually running a module now
				define("IN_MODULE", 1);

				// Print our header
				$output->print_header($module->board->modules[$import_session['module']]['name']);

				// A bit of stats to show the progress of the current import
				$output->calculate_stats();

				// Run, baby, run
				$result = $module->import();
			}

			$output->print_footer();
		}
	}
	// Otherwise we're trying to use an invalid module or we're still at the beginning
	else
	{
		$debug->log->trace0("Nieprawidłowy moduł lub brak postępu. Cofnij się do ostatniego kroku.");

		$import_session['resume_module'][] = $resume_module;
		$import_session['module'] = '';

		update_import_session();
		header("Location: index.php");
		exit;
	}

	// If the module returns "finished" then it has finished everything it needs to do. We set the import session
	// to blank so we go back to the module list
	if($result == "finished")
	{
		$debug->log->trace1("Ukończono pracę modułu. Uruchom czyszczenie, jeśli to konieczne.");

		// Once we finished running a module we check if there are any post-functions that need to be run
		// For instance, ususally we need to run a post-function on the forums to update the 'parentlist' properly
		if(method_exists($module, "cleanup"))
		{
			$debug->log->trace2("Uruchamianie modułu czyszczenia.");
			$module->cleanup();
		}

		// Once we finish with posts we always recount and update lastpost info, etc.
		if($import_session['module'] == "import_posts")
		{
			$debug->log->trace2("Uruchamianie czyszczenia liczników modułu import_posts.");
			$module->counters_cleanup();
		}

		// Check to see if our module is in the 'resume modules' array still and remove it if so.
		$key = array_search($import_session['module'], $import_session['resume_module']);
		if(isset($key))
		{
			unset($import_session['resume_module'][$key]);
		}

		// Add our module to the completed list and clear it from the current running module field.
		$import_session['completed'][] = $import_session['module'];
		$import_session['module'] = '';
		update_import_session();

		// Now that all of that is taken care of, refresh the page to continue on to whatever needs to be done next.
		if(!headers_sent())
		{
			header("Location: index.php");
		}
		else
		{
			echo "<meta http-equiv=\"refresh\" content=\"0; url=index.php\">";;
		}
		exit;
	}
}
// Otherwise we've selected a board but we're not in any module so we show the module selection list
else
{
	$debug->log->event("Pokazywanie listy modułów do wyboru.");

	// Set the start date for the end report.
	if(!$import_session['start_date'])
	{
		$import_session['start_date'] = time();
	}

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$import_session['board'].".php";
	$class_name = strtoupper($import_session['board'])."_Converter";

	$board = new $class_name;

	$output->module_list();
}
?>
