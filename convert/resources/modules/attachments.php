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

class Converter_Module_Attachments extends Converter_Module
{
	public $default_values = array(
		'import_aid' => 0,
		'pid' => 0,
		'posthash' => '',
		'uid' => 0,
		'filename' => '',
		'filetype' => '',
		'filesize' => 0,
		'attachname' => '',
		'downloads' => 0,
		'dateuploaded' => 0,
		'visible' => 1,
		'thumbnail' => ''
	);

	/**
	 * Insert attachment into database
	 *
	 * @param attachment The insert array going into the MyBB database
	 */
	public function insert($data)
	{
		global $db, $output;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		$unconverted_values = $data;

		// Call our currently module's process function
		$data = $converted_values = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values
		$data = $this->process_default_values($data);

		foreach($data as $key => $value)
		{
			$insert_array[$key] = $db->escape_string($value);
		}

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("attachments", $insert_array);
		$aid = $db->insert_id();

		if(!defined("IN_TESTING"))
		{
			$this->after_insert($unconverted_values, $converted_values, $aid);
		}

		$this->increment_tracker('attachments');

		$output->print_progress("end");

		return $aid;
	}

	public function check_attachments_dir_perms()
	{
		global $import_session, $output;

		if($import_session['total_attachments'] <= 0)
		{
			return;
		}

		$this->debug->log->trace0("Checking attachment directory permissions again");

		if($import_session['uploads_test'] != 1)
		{
			// Check upload directory is writable
			$uploadswritable = @fopen(MYBB_ROOT.'uploads/test.write', 'w');
			if(!$uploadswritable)
			{
				$this->debug->log->error("Katalog załączników jest niezapisywalny");
				$this->errors[] = 'Katalog zawierający wysłane przez użytkowników pliki (uploads/) nie jest zapisywalny. Nadaj mu poprawne uprawnienia <a href="http://wiki.mybboard.net/index.php/CHMOD%20Files" target="_blank">chmod</a> i spróbuj ponownie.';
				@fclose($uploadswritable);
				$output->print_error_page();
			}
			else
			{
				@fclose($uploadswritable);
			  	@my_chmod(MYBB_ROOT.'uploads', '0777');
			  	@my_chmod(MYBB_ROOT.'uploads/test.write', '0777');
				@unlink(MYBB_ROOT.'uploads/test.write');
				$import_session['uploads_test'] = 1;
				$this->debug->log->trace1("Katalog załączników jest zapisywalny");
			}
		}
	}

	public function test_readability($table, $path_column)
	{
		global $mybb, $import_session, $output;

		if($import_session['total_attachments'] <= 0)
		{
			return;
		}

		$this->debug->log->trace0("Sprawdzanie dostępności załączników z wybranej ścieżki");

		if($mybb->input['uploadspath'])
		{
			$import_session['uploadspath'] = $mybb->input['uploadspath'];
			if(substr($import_session['uploadspath'], strlen($import_session['uploadspath'])-1, 1) != '/')
			{
				$import_session['uploadspath'] .= '/';
			}
		}

		if(strpos($mybb->input['uploadspath'], "localhost") !== false)
		{
			$this->errors[] = "<p>Nie można używać adresu \"localhost\" w adresie. Użyj swojego adresu IP (upewnij się, że port 80 jest otwarty w routerze bądź zaporze).</p>";
			$import_session['uploads_test'] = 0;
		}

		if(strpos($mybb->input['uploadspath'], "127.0.0.1") !== false)
		{
			$this->errors[] = "<p>You may not use \"127.0.0.1\" in the URL. Please use your Internet IP Address (Please make sure Port 80 is open on your firewall and router).</p>";
			$import_session['uploads_test'] = 0;
		}

		$readable = $total = 0;
		$query = $this->old_db->simple_select($table, $path_column);
		while($attachment = $this->old_db->fetch_array($query))
		{
			++$total;

			if(method_exists($this, "generate_raw_filename"))
			{
				$filename = $this->generate_raw_filename($attachment);
			}
			else
			{
				$filename = $attachment[$path_column];
			}

			// If this is a relative or absolute server path, use is_readable to check
			if(strpos($import_session['uploadspath'], '../') !== false || my_substr($import_session['uploadspath'], 0, 1) == '/' || my_substr($import_session['uploadspath'], 1, 1) == ':')
			{
				if(@is_readable($import_session['uploadspath'].$filename))
				{
					++$readable;
				}
			}
			else
			{
				if(check_url_exists($import_session['uploadspath'].$filename))
				{
					++$readable;
				}
			}
		}
		$this->old_db->free_result($query);

		// If less than 5% of our attachments are readable then it seems like we don't have a good uploads path set.
		if((($readable/$total)*100) < 5)
		{
			$this->debug->log->error("Nie można odczytać wystarczającej ilości załączników: ".(($readable/$total)*100)."%");
			$this->errors[] = 'Załączniki nie mogły zostać odczytane. Nadaj im odpowiednie uprawnienia <a href="http://wiki.mybboard.net/index.php/CHMOD%20Files" target="_blank">chmod</a>, aby zezwolić na ich odczytanie. Upewnij się także, że ścieżka do folderu z załącznikami jest poprawna. Jeżeli mimo wykonania powyższych czynności, nadal napotykasz na problemy, spróbuj użyć bezwzględnej ścieżki do folderu z załącznikami zamiast adresu URL (na przykład: /var/www/htdocs/path/to/your/old/forum/uploads/ or C:/path/to/your/old/forum/upload/). Upewnij się również, że dostęp do tego folderu nie jest blokowany przez plik htaccess';
			$import_session['uploads_test'] = 0;
		}
	}
}

?>