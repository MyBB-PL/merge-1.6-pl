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

class Converter_Module_Posts extends Converter_Module
{
	public $default_values = array(
		'tid' => 0,
		'replyto' => 0,
		'subject' => '',
		'username' => '',
		'fid' => 0,
		'uid' => 0,
		'import_uid' => 0,
		'username' => 0,
		'dateline' => 0,
		'message' => '',
		'ipaddress' => '',
		'longipaddress' => 0,
		'includesig' => 1,
		'smilieoff' => 0,
		'edituid' => 0,
		'edittime' => 0,
		'icon' => 0,
		'visible' => 1,
		'posthash' => '',
	);

	/**
	 * Insert post into database
	 *
	 * @param post The insert array going into the MyBB database
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

		unset($insert_array['import_pid']);
		unset($insert_array['import_uid']);

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("posts", $insert_array);
		$pid = $db->insert_id();

		$db->insert_query("post_trackers", array(
			'pid' => intval($pid),
			'import_pid' => intval($data['import_pid']),
			'import_uid' => intval($data['import_uid'])
		));

		$this->cache_posts[$data['import_pid']] = $pid;

		if(method_exists($this, "after_import"))
		{
			$this->after_import($unconverted_values, $converted_values, $pid);
		}

		$this->increment_tracker('posts');

		$output->print_progress("end");

		return $pid;
	}

	/**
	 * Rebuild counters, and lastpost information right after importing posts
	 *
	 */
	public function counters_cleanup()
	{
		global $db, $output, $import_session;

		$output->print_header("Przebudowa liczników");
		
		$this->debug->log->trace0("Przebudowa liczników wątków, działów i statystyk");
		
		$output->construct_progress_bar();
		
		echo "<br />\nPrzebudowywanie liczników wątków, działów i statystyk...(To może chwilę potrwać)<br /><br />\n";
		echo "<br />\nPrzebudowywanie liczników wątków... ";
		flush();

		// Rebuild thread counters, forum counters, user post counters, last post* and thread username
		$query = $db->simple_select("threads", "COUNT(*) as count", "import_tid != 0");
		$num_imported_threads = $db->fetch_field($query, "count");
		$progress = $last_percent = 0;

		if($import_session['counters_cleanup_start'] < $num_imported_threads)
		{
			$this->debug->log->trace1("Rebuilding thread counters");

			$progress = $import_session['counters_cleanup_start'];
			$query = $db->simple_select("threads", "tid", "import_tid != 0", array('order_by' => 'tid', 'order_dir' => 'asc', 'limit_start' => intval($import_session['counters_cleanup_start']), 'limit' => 1000));
			while($thread = $db->fetch_array($query))
			{
				rebuild_thread_counters($thread['tid']);

				++$progress;

				if(($progress % 5) == 0)
				{
					if(($progress % 100) == 0)
					{
						check_memory();
					}
					$percent = round(($progress/$num_imported_threads)*100, 1);
					if($percent != $last_percent)
					{
						$output->update_progress_bar($percent, "Przebudowa liczników dla wątku #{$thread['tid']}");
					}
					$last_percent = $percent;
				}
			}

			$import_session['counters_cleanup_start'] += $progress;

			if($import_session['counters_cleanup_start'] >= $num_imported_threads)
			{
				$this->debug->log->trace1("Zakończono przebudowę liczników wątków");
				$import_session['counters_cleanup'] = 0;
				echo "Zakończono.";
				flush();
				return;
			}
			$import_session['counters_cleanup'] = 1;
			return;
		}

		if($import_session['counters_cleanup_start'] >= $num_imported_threads)
		{
			$this->debug->log->trace1("Przebudowa liczników działów");
			echo "zakończono. <br />Przebudowywanie liczników działów... ";
			flush();

			$query = $db->simple_select("forums", "COUNT(*) as count", "import_fid != 0");
			$num_imported_forums = $db->fetch_field($query, "count");
			$progress = 0;

			$query = $db->simple_select("forums", "fid", "import_fid != 0", array('order_by' => 'fid', 'order_dir' => 'asc'));
			while($forum = $db->fetch_array($query))
			{
				rebuild_forum_counters($forum['fid']);
				++$progress;
				$output->update_progress_bar(round((($progress/$num_imported_forums)*50), 1)+100, "Przebudowa liczników dla działu #{$forum['fid']}");
			}

			$output->update_progress_bar(150);

			$query = $db->simple_select("forums", "fid", "usepostcounts = 0");
			while($forum = $db->fetch_array($query))
			{
				$fids[] = $forum['fid'];
			}

			if(is_array($fids))
			{
				$fids = implode(',', $fids);
			}

			if($fids)
			{
				$fids = " AND fid NOT IN($fids)";
			}
			else
			{
				$fids = "";
			}

			$this->debug->log->trace1("Przebudowywanie liczników użytkowników");
			echo "zakończono. <br />Przebudowywanie liczników użytkowników... ";
			flush();

			$query = $db->simple_select("users", "COUNT(*) as count", "import_uid != 0");
			$num_imported_users = $db->fetch_field($query, "count");
			$progress = $last_percent = 0;

			$query = $db->simple_select("users", "uid", "import_uid != 0");
			while($user = $db->fetch_array($query))
			{
				$query2 = $db->simple_select("posts", "COUNT(*) AS post_count", "uid='{$user['uid']}' AND visible > 0{$fids}");
				$num_posts = $db->fetch_field($query2, "post_count");
				$db->free_result($query2);
				$db->update_query("users", array("postnum" => intval($num_posts)), "uid='{$user['uid']}'");

				++$progress;
				$percent = round((($progress/$num_imported_users)*50)+150, 1);
				if($percent != $last_percent)
				{
					$output->update_progress_bar($percent, "Przebudowywanie licznika dla użytkownika #{$user['uid']}");
				}
				$last_percent = $percent;
			}
			// TODO: recount user posts doesn't seem to work

			$output->update_progress_bar(200, "Proszę czekać...");
			
			echo "zakończono.<br />";
			flush();

			sleep(3);
		}
	}
}

?>