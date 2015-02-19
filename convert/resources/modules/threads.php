<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

class Converter_Module_Threads extends Converter_Module
{
	public $default_values = array(
		'import_tid' => 0,
		'sticky' => 0,
		'fid' => 0,
		'firstpost' => 0,
		'dateline' => 0,
		'subject' => '',
		'prefix' => 0,
		'poll' => 0,
		'uid' => 0,
		'import_uid' => 0,
		'views' => 0,
		'closed' => 0,
		'totalratings' => 0,
		'notes' => '',
		'visible' => 1,
		'unapprovedposts' => 0,
		'numratings' => 0,
		'attachmentcount' => 0,
		'username' => '',
		'lastpost' => 0,
		'lastposter' => '',
		'lastposteruid' => 0,
		'replies' => 0,
		'icon' => 0,
		'deletetime' => 0,
	);

	/**
	 * Insert thread into database
	 *
	 * @param thread The insert array going into the MyBB database
	 */
	public function insert($data)
	{
		global $db, $output;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		// Call our currently module's process function
		$data = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values
		$data = $this->process_default_values($data);

		foreach($data as $key => $value)
		{
			$insert_array[$key] = $db->escape_string($value);
		}

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("threads", $insert_array);
		$tid = $db->insert_id();

		$this->cache_tids[$data['import_tid']] = $tid;

		$this->increment_tracker('threads');

		$output->print_progress("end");

		return $tid;
	}
}

?>