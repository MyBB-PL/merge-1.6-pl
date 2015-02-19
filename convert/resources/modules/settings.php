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

class Converter_Module_Settings extends Converter_Module
{

	/**
	 * Update setting in the database
	 *
	 * @param name The name of the setting being inserted
	 * @param value The value of the setting being inserted
	 */
	public function update_setting($name, $value)
	{
		global $db, $output;

		$this->debug->log->trace0("Aktualizacja ustawień {$name}");
		
		$output->print_progress("start", "Aktualizacja ustawień ".htmlspecialchars_uni($name));

		$modify = array(
			'value' => $db->escape_string($value)
		);

		$this->debug->log->datatrace('$value', $value);

		$db->update_query("settings", $modify, "name='{$name}'");

		$this->increment_tracker('settings');

		$output->print_progress("end");
	}
}

?>