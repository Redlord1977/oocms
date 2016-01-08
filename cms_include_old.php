<style type="text/css">
.Header1 {
	font-family: Tahoma, Geneva, sans-serif;
	font-size: x-large;
}
.Header2 {
	font-family: Tahoma, Geneva, sans-serif;
	font-size: medium;
	font-weight: bold;
}
.Header3 {
	font-family: Tahoma, Geneva, sans-serif;
	font-size: small;
}
.BodyText {
	font-family: Tahoma, Geneva, sans-serif;
	font-size: small;
}
</style>
<?php
session_start();

//set colors for headings
$color1 = '#99CCCC';	//green
$color2 = '#DDDDDD';	//light gray
$color3 = '#CC0000';	//red
$color4 = '#81DAF5';	//blue
require_once 'cms_system_variables.php';  //load system variables
require_once 'calendar/tc_calendar.php';

function get_timezone_offset($remote_tz, $origin_tz = null) {
    if($origin_tz === null) {
        if(!is_string($origin_tz = date_default_timezone_get())) {
            return false; // A UTC timestamp was returned -- bail out!
        }
    }
    $origin_dtz = new DateTimeZone($origin_tz);
    $remote_dtz = new DateTimeZone($remote_tz);
    $origin_dt = new DateTime("now", $origin_dtz);
    $remote_dt = new DateTime("now", $remote_dtz);
    $offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
    return $offset;
}

function isOfficeOpen($time) {
	$workingTime = str_replace(":", "", substr($time, 6, 6));
	//echo "<br> $workingTime <br>";
	if (($workingTime < '0700') OR ($workingTime > '1800')) {
		RETURN FALSE;
	} else {
		RETURN TRUE;
	}
}

function getRemoteTime($originTime, $remote_tz, $origin_tz) {
	//convert origin time to UNIX time
	$unixTimeOrigin = strtotime($originTime);
	$offset = get_timezone_offset($remote_tz, $origin_tz);
	//echo "<br>$offset<br>";
	$unixRemoteTime = $unixTimeOrigin - $offset;
	//return time at remote office
	$remoteTime = date('M d H:i', $unixRemoteTime);
	RETURN $remoteTime;
}

function sanitate($array) {
   foreach($array as $key=>$value) {
      if(is_array($value)) { sanitate($value); }
      else { $array[$key] = mysql_real_escape_string($value); }
   }
   return $array;
}
function WriteRecurrences($call_id) {
	//get the recurrence details from tbl_recurrence
	$recurrence_sql = "SELECT * FROM tbl_recurrence WHERE conf_id = '$call_id'";
	//echo "<br>$recurrence_sql<br>";
	$recurrence_result = mysql_query($recurrence_sql) or die("Unable to read from tbl_recurrence.  The sql was: <br>" . $recurrence_sql . " <br> the error was:" . mysql_error());
	while ($recurrence_data = mysql_fetch_array($recurrence_result)) {
		
		$start_date = $recurrence_data[callDate];
		//echo $start_date;
		$end_date = $recurrence_data[recurUntil];
		$unix_start_date = strtotime($start_date);
		$unix_end_date = strtotime($end_date);
		$unix_working_date = ($unix_start_date + 86400);
		//deal with business day recurrence
		if ($recurrence_data[recurrence_type] == '2') {
			while ($unix_working_date <= $unix_end_date) {
				//check if working date is NOT Sunday or Sunday
				$day_of_week = date(N, $unix_working_date);
				//echo $day_of_week;
				if (("$day_of_week" != 6) AND ("$day_of_week" != 7)) {
					//get original call details and write to DB
					//set date of reservation and taken by and edited by
					$last_edited_by = $_SESSION[email];
					$taken_by = $_SESSION[email];
					$res_date = date(Y-m-d);
					//first get ALL the details of the call being copied and write to a new conference
					//general details first
					$read_sql="SELECT call_datetime, call_duration, chair_name, notes, chair_number, client_ref, company, conf_title, dial_type, num_di_lines, num_do_lines, res_date, scheduler_tel, scheduler, conference_type, bridge, leadop, account_number FROM tbl_conference_reservations WHERE call_id = '$call_id'";
					$read_result = mysql_query($read_sql) or die("Unable to read the general details of the reservation. The error was:<br>" . mysql_error());
					$call_datetime = date('Y-m-d H:i:s', $unix_working_date);
					while ($read_data = mysql_fetch_array($read_result)) {
						$write_sql="INSERT INTO tbl_conference_reservations VALUES ('', '$call_datetime', '$read_data[call_duration]', '$read_data[chair_name]', '$read_data[notes]', '$read_data[chair_number]', '$read_data[client_ref]', '$read_data[company]', '$read_data[conf_title]', '$read_data[dial_type]', '$read_data[num_di_lines]', '$read_data[num_do_lines]', '$res_date', '$read_data[scheduler_tel]', '$read_data[scheduler]', '$taken_by', '$read_data[conference_type]', '$read_data[bridge]', '$read_data[leadop]', 'No', '$read_data[account_number]', '', '$taken_by')"; 
						echo "<br> $write_sql <br>";
						$write_result = mysql_query($write_sql) or die("Unable to write the general details of the reservation. The error was:<br>" . mysql_error());
						$new_call_id = mysql_insert_id();
						//we need to write the data for the different conference types to the db for the new call
						switch ($read_data[conference_type]):
						case 'Audio':
							$read_sql = "SELECT * FROM tbl_audio_dial_parties WHERE call_id = '$call_id'";
							$read_result = mysql_query($read_sql) or die("Unable to read the dial out parties. The error was:<br>" . mysql_error());
							while ($read_data = mysql_fetch_array($read_result)) {
								$read_data = sanitate($read_data);
								$write_sql = "INSERT INTO tbl_audio_dial_parties VALUES ('', '$new_call_id', '$read_data[party_name]', '$read_data[party_number]', '$read_data[party_notes]')";
								$write_result = mysql_query($write_sql) or die("Unable to write the audio dial out details of the reservation. The error was:<br>" . mysql_error());
							}
						break;
						case 'Video':
							$read_sql = "SELECT * FROM tbl_video_sites_link WHERE conf_id = '$call_id'";
							$read_result = mysql_query($read_sql) or die("Unable to read the video parties. The error was:<br>" . mysql_error());
							while ($read_data = mysql_fetch_array($read_result)) {
								$read_data = sanitate($read_data);
								$write_sql = "INSERT INTO tbl_video_sites_link VALUES ('', '$new_call_id', '$read_data[site_name]', '$read_data[bandwidth]', '$read_data[dial_type]', '$read_data[site_id]')";
								
								$write_result = mysql_query($write_sql) or die("Unable to write the video site details of the reservation. The error was:<br>" . mysql_error());
							}			
						break;
						case 'Room':
							$read_sql = "SELECT * FROM tbl_remote_room_sites  WHERE conf_id = '$call_id'";
							$read_result = mysql_query($read_sql) or die("Unable to read the room details. The error was:<br>" . mysql_error());
							while ($read_data = mysql_fetch_array($read_result)) {
								$read_data = sanitate($read_data);
								$write_sql = "INSERT INTO tbl_remote_room_sites VALUES ('$new_call_id', '$read_data[company_name]', '$read_data[site_name]', '$read_data[scheduler_name]', '$read_data[scheduler_phone]', '$read_data[site_country]', '$read_data[site_city]', '$read_data[tech_name]', '$read_data[tech_phone]', '$read_data[tech_email]', '$read_data[trigger_number]', '$read_data[bandwidth]', '$read_data[network]', '', '', '', '', '')";
								$write_result = mysql_query($write_sql) or die("Unable to write the room remote site details of the reservation. The error was:<br>" . mysql_error());
							}
						break;
						endswitch;
					}
					//now the services
					$read_sql = "SELECT * FROM tbl_service_link WHERE call_id = '$call_id'";
					$read_result = mysql_query($read_sql) or die("Unable to read the service data. The error was:<br>" . mysql_error());
					while ($read_data = mysql_fetch_array($read_result)) {
						$read_data = sanitate($read_data);
						$write_sql = "INSERT INTO tbl_service_link VALUES ('$new_call_id', '$read_data[service_id]', '$read_data[info1]', '$read_data[info2]', '$read_data[info3]', '$read_data[info5]', '$read_data[info5]', '$read_data[info6]', '$read_data[info7]', '$read_data[info8]', '$read_data[info9]', '$read_data[info10]', '$read_data[rate]')";
						$write_result = mysql_query($write_sql) or die("Unable to write the service details of the reservation. The error was:<br>" . mysql_error());
					}
					//now the numbers list
					$read_bridges_sql = "SELECT * FROM tbl_bridges_link WHERE call_id = '$call_id' ORDER BY bridge_id";
					//echo "<br> $read_bridges_sql <br>\n";
					$read_bridges_result = mysql_query($read_bridges_sql) or die("Read Bridge list query failed. <br> The sql was: <br> $read_bridges_sql <br> The error was: <br>  " . mysql_error());
					while ($read_bridges_data = mysql_fetch_array($read_bridges_result)) {
						$read_bridges_data = sanitate($read_bridges_data);
						$write_bridges_sql = "INSERT INTO tbl_bridges_link VALUES ('$read_bridges_data[bridge_id]', '$new_call_id')";
						//echo "<br> $write_bridges_sql <br>\n";
						$write_bridges_result = mysql_query($write_bridges_sql) or die("Unable to write the used bridges details of the reservation. <br> The sql was: <br> $write_bridges_sql <br> The error was: <br>  " . mysql_error());
					}
					//put the reservation date taken in the tranaction log
					//first write the pc and reason to the DB
					$origin_pc = $_SESSION[email];
					$transaction_history_sql = "INSERT INTO tbl_modified_info SET origin_pc = '$origin_pc', description = 'New reservation copied', call_id = '$new_call_id'";
					// echo "<br>$transaction_history_sql<br>";
					$transaction_history_result = mysql_query($transaction_history_sql) or die("Could not update tbl_modified_info. <br> The sql was: " . $transaction_history_sql . " <br> The error was:<br>" . mysql_error());
							
				}
				//increment the day by 1
				$unix_working_date = ($unix_working_date + 86400);
			}
		}
		//deal with weekly recurrence
		if ($recurrence_data[recurrence_type] == '3') {
			$unix_start_date = strtotime($start_date);
			$unix_end_date = strtotime($end_date);
			$unix_working_date = ($unix_start_date + 604800);
			while ($unix_working_date <= $unix_end_date) {
					//get original call details and write to DB
					//set date of reservation and taken by and edited by
					$last_edited_by = $_SESSION[email];
					$taken_by = $_SESSION[email];
					$res_date = date(Y-m-d);
					//first get ALL the details of the call being copied and write to a new conference
					//general details first
					$call_datetime = date('Y-m-d H:i', $unix_working_date);
					$read_sql="SELECT call_datetime, call_duration, chair_name, notes, chair_number, client_ref, company, conf_title, dial_type, num_di_lines, num_do_lines, res_date, scheduler_tel, scheduler, conference_type, bridge, leadop, account_number FROM tbl_conference_reservations WHERE call_id = '$call_id'";
					$read_result = mysql_query($read_sql) or die("Unable to read the general details of the reservation. The error was:<br>" . mysql_error());
					while ($read_data = mysql_fetch_array($read_result)) {
						$read_data = sanitate($read_data);
						$write_sql="INSERT INTO tbl_conference_reservations VALUES ('', '$call_datetime', '$read_data[call_duration]', '$read_data[chair_name]', '$read_data[notes]', '$read_data[chair_number]', '$read_data[client_ref]', '$read_data[company]', '$read_data[conf_title]', '$read_data[dial_type]', '$read_data[num_di_lines]', '$read_data[num_do_lines]', '$res_date', '$read_data[scheduler_tel]', '$read_data[scheduler]', '$taken_by', '$read_data[conference_type]', '$read_data[bridge]', '$read_data[leadop]', 'No', '$read_data[account_number]', '', '$taken_by')"; 
						$write_result = mysql_query($write_sql) or die("Unable to write the general details of the reservation. The error was:<br>" . mysql_error());
						$new_call_id = mysql_insert_id();
						//we need to write the data for the different conference types to the db for the new call
						switch ($read_data[conference_type]):
						case 'Audio':
							$read_sql = "SELECT * FROM tbl_audio_dial_parties WHERE call_id = '$call_id'";
							$read_result = mysql_query($read_sql) or die("Unable to read the dial out parties. The error was:<br>" . mysql_error());
							while ($read_data = mysql_fetch_array($read_result)) {
								$read_data = sanitate($read_data);
								$write_sql = "INSERT INTO tbl_audio_dial_parties VALUES ('', '$new_call_id', '$read_data[party_name]', '$read_data[party_number]', '$read_data[party_notes]')";
								$write_result = mysql_query($write_sql) or die("Unable to write the audio dial out details of the reservation. The error was:<br>" . mysql_error());
							}
						break;
						case 'Video':
							$read_sql = "SELECT * FROM tbl_video_sites_link WHERE conf_id = '$call_id'";
							$read_result = mysql_query($read_sql) or die("Unable to read the video parties. The error was:<br>" . mysql_error());
							while ($read_data = mysql_fetch_array($read_result)) {
								$read_data = sanitate($read_data);
								$write_sql = "INSERT INTO tbl_video_sites_link VALUES ('', '$new_call_id', '$read_data[site_name]', '$read_data[bandwidth]', '$read_data[dial_type]', '$read_data[site_id]')";
								$write_result = mysql_query($write_sql) or die("Unable to write the video site details of the reservation. The error was:<br>" . mysql_error());
							}			
						break;
						case 'Room':
							$read_sql = "SELECT * FROM tbl_remote_room_sites  WHERE conf_id = '$call_id'";
							$read_result = mysql_query($read_sql) or die("Unable to read the room details. The error was:<br>" . mysql_error());
							while ($read_data = mysql_fetch_array($read_result)) {
								$read_data = sanitate($read_data);
								$write_sql = "INSERT INTO tbl_remote_room_sites VALUES ('$new_call_id', '$read_data[company_name]', '$read_data[site_name]', '$read_data[scheduler_name]', '$read_data[scheduler_phone]', '$read_data[site_country]', '$read_data[site_city]', '$read_data[tech_name]', '$read_data[tech_phone]', '$read_data[tech_email]', '$read_data[trigger_number]', '$read_data[bandwidth]', '$read_data[network]', '', '', '', '', '')";
								$write_result = mysql_query($write_sql) or die("Unable to write the room remote site details of the reservation. The error was:<br>" . mysql_error());
							}
						break;
						endswitch;
					}
					//now the services
					$read_sql = "SELECT * FROM tbl_service_link WHERE call_id = '$call_id'";
					$read_result = mysql_query($read_sql) or die("Unable to read the service data. The error was:<br>" . mysql_error());
					while ($read_data = mysql_fetch_array($read_result)) {
						$read_data = sanitate($read_data);
						$write_sql = "INSERT INTO tbl_service_link VALUES ('$new_call_id', '$read_data[service_id]', '$read_data[info1]', '$read_data[info2]', '$read_data[info3]', '$read_data[info5]', '$read_data[info5]', '$read_data[info6]', '$read_data[info7]', '$read_data[info8]', '$read_data[info9]', '$read_data[info10]', '$read_data[rate]')";
						$write_result = mysql_query($write_sql) or die("Unable to write the service details of the reservation. The error was:<br>" . mysql_error());
					}
					//now the numbers list
					$read_bridges_sql = "SELECT * FROM tbl_bridges_link WHERE call_id = '$call_id' ORDER BY bridge_id";
					//echo "<br> $read_bridges_sql <br>\n";
					$read_bridges_result = mysql_query($read_bridges_sql) or die("Read Bridge list query failed. <br> The sql was: <br> $read_bridges_sql <br> The error was: <br>  " . mysql_error());
					while ($read_bridges_data = mysql_fetch_array($read_bridges_result)) {
						$read_data = sanitate($read_data);
						$write_bridges_sql = "INSERT INTO tbl_bridges_link VALUES ('$read_bridges_data[bridge_id]', '$new_call_id')";
						//echo "<br> $write_bridges_sql <br>\n";
						$write_bridges_result = mysql_query($write_bridges_sql) or die("Unable to write the used bridges details of the reservation. <br> The sql was: <br> $write_bridges_sql <br> The error was: <br>  " . mysql_error());
					}
					//put the reservation date taken in the tranaction log
					//first write the pc and reason to the DB
					$origin_pc = $_SESSION[email];
					$transaction_history_sql = "INSERT INTO tbl_modified_info SET origin_pc = '$origin_pc', description = 'New reservation copied', call_id = '$new_call_id'";
					// echo "<br>$transaction_history_sql<br>";
					$transaction_history_result = mysql_query($transaction_history_sql) or die("Could not update tbl_modified_info. <br> The sql was: " . $transaction_history_sql . " <br> The error was:<br>" . mysql_error());
					//increment the week by 1
				$unix_working_date = ($unix_working_date + 604800);		
				}
				
			}
		}
}
function EditCheck($call_id){
	$checked_sql = "SELECT * FROM tbl_checks WHERE call_id = '$call_id'";
	$result_checked = mysql_query($checked_sql) or die("Unable to read from tbl_checks.  The sql was: <br>" . $checked_sql . " <br> the error was:" . mysql_error());
	$checked_data = mysql_fetch_array($result_checked);
	$checked = $checked_data[checked_by];
	$prest = $checked_data[preset_by];
	$loaded = $checked_data[loaded_by];	
	echo "<td><font size='2' face=Verdana><b>Checked By: </b>$checked_data[checked_by]</td>\n";
	echo "<td><font size='2' face=Verdana><b>Preset By: </b>$checked_data[preset_by]</td>\n";
	echo "<td><font size='2' face=Verdana><b>Loaded By: </b>$checked_data[loaded_by]</td>\n";
	echo "<table width=100% border='1' cellspacing='0' cellpadding='0' bordercolor='black' rules=rows>\n";
	echo "<tr bgcolor=#99CCCC>\n";
	echo "<td bgcolor='#99CCCC' colspan=3><div align='center'><font face=Verdana><b>Conference Checks</b></font></div></td>";
	echo "</tr>";	
	echo "<tr>";
	echo "<td width=33%><font size='2' face=Verdana><b>Checked By</b></td>";
	echo "<td width=33%><font size='2' face=Verdana><b>Preset Created By</b></td>";
	echo "<td width=33%><font size='2' face=Verdana><b>Loaded to Bridge By</b></td>";
	echo "</tr>";
	echo "<form action='reservation.php?action=commitchecks&call_id=$call_id' method='post' enctype='multipart/form-data'>\n";
	//get the list from the db
	echo "<tr>\n";
	
	echo "<td>\n";
	$checked_sql="SELECT * FROM tbl_operators ORDER BY name";
	$checked_result = mysql_query($checked_sql) or die("Could not read from tbl_operators. <br> The sql was: " . $checked_sql . " <br> The error was:<br>" . mysql_error());
	echo "<select name=checked>\n";
	while ($checked_data = mysql_fetch_array($checked_result)) {
		if ($checked == $checked_data[name]) echo "<option selected>$checked_data[name]</option>\n";
		else echo "<option>$checked_data[name]</option>\n";
	}
	echo "</select></td>\n";
	
	echo "<td>\n";
	$preset_sql="SELECT * FROM tbl_operators ORDER BY name";
	$preset_result = mysql_query($preset_sql) or die("Could not read from tbl_operators. <br> The sql was: " . $checked_sql . " <br> The error was:<br>" . mysql_error());
	echo "<select name=preset>\n";
	while ($preset_data = mysql_fetch_array($preset_result)) {
		if ($preset == $preset_data[name]) echo "<option selected>$preset_data[name]</option>\n";
		else echo "<option>$preset_data[name]</option>\n";
	}
	echo "</select></td>\n";
	
	echo "<td>\n";
	$op_sql="SELECT * FROM tbl_operators ORDER BY name";
	$op_result = mysql_query($op_sql) or die("Could not read from tbl_operators. <br> The sql was: " . $op_sql . " <br> The error was:<br>" . mysql_error());
	echo "<select name=loaded>\n";
	while ($op_data = mysql_fetch_array($op_result)) {
		if ($loaded == $op_data[name]) echo "<option selected>$op_data[name]</option>\n";
		else echo "<option>$op_data[name]</option>\n";
	}
	echo "</select></td>\n";
	echo "<tr>\n";
	
	echo "<td colspan=3 align='center'><input type='submit' value='Confirm Checks'></td>\n";
	echo "</form>\n";
	echo "</tr>";
}
function CommitChecks($call_id){
	$checks = $_POST;
	$checks_sql = "REPLACE INTO tbl_checks SET checked_by = '$checks[checked]' , preset_by = '$checks[preset]', loaded_by = '$checks[loaded]', call_id = '$call_id'";
	//echo "<br>$checks_sql<br>";
	$result_checks = mysql_query($checks_sql) or die("Unable to update tbl_checks.  The sql was: <br>" . $checks_sql . " <br> the error was:" . mysql_error());


}
function DisplayChecks($call_id){
	$checked_sql = "SELECT * FROM tbl_checks WHERE call_id = '$call_id'";
	echo "<table width=100% border='1' cellspacing='0' cellpadding='0' bordercolor='black' rules=rows>\n";
	echo "<tr bgcolor=#99CCCC>\n";
	echo "<td bgcolor='#99CCCC' colspan=3><div align='center'><font face=Verdana><b>Conference Checks</b></font></div></td>";
	echo "</tr>";	
	echo "<tr>";
	$result_edits = mysql_query($checked_sql) or die("Unable to read from tbl_checks.  The sql was: <br>" . $checked_sql . " <br> the error was:" . mysql_error());
	while ($checked_data = mysql_fetch_array($result_edits)){
		echo "<td><font size='2' face=Verdana><b>Checked By: </b>$checked_data[checked_by]</td>\n";
		echo "<td><font size='2' face=Verdana><b>Preset By: </b>$checked_data[preset_by]</td>\n";
		echo "<td><font size='2' face=Verdana><b>Loaded By: </b>$checked_data[loaded_by]</td>\n";
	}
}	
function ShowTaskChanges($project_id) {
	$sql_edits = "SELECT * FROM tbl_project_track WHERE project_id = '$project_id' ORDER BY time_edited DESC";
	$result_edits = mysql_query($sql_edits) or die("Unable to read from tbl_project_track.  The sql was: <br>" . $sql_edits . " <br> the error was:" . mysql_error());
	echo "<table width=100% border='1' cellspacing='0' cellpadding='0' bordercolor='black' rules=rows>\n";
	echo "<tr bgcolor=#99CCCC>\n";
	echo "<td bgcolor='#99CCCC' colspan=3><div align='center'><font face=Verdana><b>Task Edits</b></font></div></td>";
	echo "</tr>";	
	echo "<tr>";
	echo "<td><font size='2' face=Verdana><b>Edited By</b></td>";
	echo "<td><font size='2' face=Verdana><b>Edited At</b></td>";
	echo "<td><font size='2' face=Verdana><b>Reason for Edit</b></td>";
	echo "</tr>";
	while ($edit_details = mysql_fetch_array($result_edits)) {
		echo "<tr> ";
		echo "<td><font size='2' face=Verdana>$edit_details[origin_pc]</td>";
		echo "<td><font size='2' face=Verdana>$edit_details[time_edited]</td>";
		echo "<td><font size='2' face=Verdana>$edit_details[description]</td>";
		echo "</tr>";
	}
	echo "</table>\n";
}
function ShowTaskDetails($project_id) {
	//get the details from the db
	$project_sql = "SELECT * FROM tbl_project WHERE project_id = '$project_id'";
	$project_result = mysql_query($project_sql) or die("Unable to read from tbl_project.  The sql was: <br>" . $project_sql . " <br> the error was:" . mysql_error());
	while ($project_data = mysql_fetch_array($project_result)) {
		echo "<table width=100% border='1' cellspacing='0' cellpadding='1' bordercolor='#7CDEDE'>\n";
		echo "<tr><td colspan=3 align='center' bgcolor='#008080'><Font color=white face ='Verdana' size=4><b>Chorus Call South Africa</b></font></td></tr>\n";
		echo "<tr><td colspan=3 align='center' bgcolor='#DDF7F7'><Font face ='Verdana' size=3><b>$project_data[project_title] Task Details</b></td></tr>\n";
		echo "<tr>\n";
		echo "<td><b><Font face ='Verdana' size=2>Task:</b> $project_data[project_title]</td>\n";
		echo "<td><b><Font face ='Verdana' size=2>Opened By:</b> $project_data[opened_by]</td>\n";
		$date = date_create($project_data[opened_at]);
		$opened_on = date_format($date, 'Y-m-d');
		echo "<td><b><Font face ='Verdana' size=2>Opened At:</b> $opened_on</td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td><b><Font face ='Verdana' size=2>Assigned To:</b> $project_data[assigned_to_technician]</td>\n";
		$date = date_create($project_data[deadline]);
		$complete_by = date_format($date, 'Y-m-d');
		echo "<td><b><Font face ='Verdana' size=2>Complete By:</b> $complete_by</td>\n";
		echo "<td><b><Font face ='Verdana' size=2>Urgency:</b> $project_data[urgency]</td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td colspan=3 align=center bgcolor='#DDF7F7'><Font face ='Verdana' size=3><b>Task Description:</b></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td colspan=3><Font face ='Verdana' size=2>$project_data[project_description]</td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td colspan=3 align=center bgcolor='#DDF7F7'><Font face ='Verdana' size=3><b>Technician Comments:</b></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td colspan=3><Font face ='Verdana' size=2>$project_data[technician_comments]</td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td colspan=3 align=center bgcolor='#DDF7F7'><Font face ='Verdana' size=3><b>User Comments:</b></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td colspan=3><Font face ='Verdana' size=2>$project_data[user_comments]</td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td><b><Font face ='Verdana' size=2>Active:</b> $project_data[active]</td>\n";
		echo "<td><b><Font face ='Verdana' size=2>Completed Technician:</b> $project_data[completed_by_tehnician]</td>\n";
		echo "<td><b><Font face ='Verdana' size=2>Completed Date:</b> $project_data[completed_date]</td>\n";
		echo "</tr>\n";
		echo "</table>\n";
	}
}
function add_business_days($startdate,$buisnessdays,$holidays,$dateformat){
  $i=0;
  $dayx = strtotime($startdate);
  while($i <= $buisnessdays){
   $day = date('N',$dayx);
   $date = date('Y-m-d',$dayx);
   if($day < 6 && !in_array($date,$holidays))$i++;
   $dayx = strtotime($date.' +1 day');
  }
  return date($dateformat,$dayx);
 }
function EditConferenceChange($call_id){
	echo "<form action='reservation.php?action=commiteditreson&call_id=$call_id' method='post' enctype='multipart/form-data'>\n";
	echo "<table border='1' table width='100%' cellspacing='0' cellpadding='0' bordercolor='black' rules=rows>\n";
	echo "<tr bgcolor=#99CCCC>\n";
	echo "<td bgcolor='#99CCCC'><div align='center'><font face=Verdana color=red><b>Conference Change Details</b></font></div></td>";
	echo "</tr>";	
	echo "<tr>";
	echo "<td><font size='4' face=Verdana color=red><b>Reason for change: <input name=change_reason type='text' size=50></b></td>";
	echo "</tr>";
	echo "<tr> ";
	echo "<td align=center colspan=4><input type='submit' value='Submit'></td>";
	echo "</tr>";
	echo "</table>\n";
	echo "</form>";
}

function DisplayConferenceEdits($call_id) {
	$sql_edits = "SELECT * FROM tbl_modified_info WHERE call_id = '$call_id' ORDER BY datetime DESC";
	$result_edits = mysql_query($sql_edits) or die("Unable to read from tbl_modified_info.  The sql was: <br>" . $sql_edits . " <br> the error was:" . mysql_error());
	echo "<table border='0' width='100%' cellspacing='0'>\n";
	echo "<tr bgcolor=#99CCCC>\n";
	echo "<td colspan=3><div align='center'><font face=Verdana><b>Conference Edits</b></font></div></td>";
	echo "</tr>";	
	echo "<tr>";
		echo "<td width='33%'><font size='2' face=Verdana><b>Edited By</b></td>";
		echo "<td width='33%'><font size='2' face=Verdana><b>Edited At</b></td>";
		echo "<td width='33%'><font size='2' face=Verdana><b>Reason for Edit</b></td>";
	echo "</tr>";
	$row_count = 1;
	$color2 = "#FFFFFF";
	$color1 = "#DDDDDD";
	while ($edit_details = mysql_fetch_array($result_edits)) {
		$row_color = ($row_count % 2) ? $color1 : $color2;
		echo "<tr bgcolor=$row_color> ";
			echo "<td><font size='2' face=Verdana>$edit_details[origin_pc]</td>";
			echo "<td><font size='2' face=Verdana>$edit_details[datetime]</td>";
			echo "<td><font size='2' face=Verdana>$edit_details[description]</td>";
		echo "</tr>";
		$row_count++;
	}
	echo "<tr><td align=center><b><FONT size=3 face=Verdana><a href='display_conference.php?call_id=$call_id&view_type=client' View Client Form - Reservation $call_id</a></font></b></td></tr>\n";
	echo "</table>\n";
}

function APSConnect() {
	$connectionInfo = array( "UID"=>"sa",
                         "PWD"=>"w1ow34f34",
                         "Database"=>"APS_SQL");
	$conn = sqlsrv_connect("192.168.2.133", $connectionInfo) or die( print_r( sqlsrv_errors(), true) . "<br>");
	return $conn;
}
function EditRemoteRoomDetails($call_id) {
	$remote_room_sql = "SELECT * FROM tbl_remote_room_sites WHERE conf_id = $call_id";
	$remote_room_result = mysql_query($remote_room_sql) or die("Unable to read from tbl_tbl_remote_room_sites.  The sql was: <br>" . $remote_room_sql . " <br> the error was:" . mysql_error());
	while ($room_details = mysql_fetch_array($remote_room_result))	
	{
		echo "<form action='reservation.php?action=commitremoteroom&call_id=$call_id' method='post' enctype='multipart/form-data'>\n";
		echo "<table border='1' table width='100%' cellspacing='0' cellpadding='0' bordercolor='black' rules=rows>\n";
		echo "<tr bgcolor=#99CCCC>\n";
		echo "<td colspan='4' align=center><font face=Verdana><b>Room Remote Site Information</b></font></td>\n";
		echo "</tr>\n";
		echo "<tr bgcolor=#99CCCC>\n";
		echo "<td colspan='4'><div align='center'><font size='3' face='Verdana, Arial, Helvetica, sans-serif'><strong>Company Information</strong></font></div></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#FFFFFF'>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Company Name: </b><input name=company_name type='text' value='$room_details[company_name]' size=15></font></td>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Site Name: </b><input name=site_name type='text' value='$room_details[site_name]' size=15></font></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#FFFFFF'>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Scheduler: <input name=scheduler_name type='text' value='$room_details[scheduler_name]' size=15></b></font></td>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Telephone: </b><input name=scheduler_phone type='text' value='$room_details[scheduler_phone]' size=15></font></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#FFFFFF'>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Country:</b> <input name=site_country type='text' value='$room_details[site_country]' size=15></font></td>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>City:</b> <input name=site_city type='text' value='$room_details[site_city]' size=15></font></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#99CCCC'>\n";
		echo "<td colspan='4'><div align='center'><font size='3'><strong><font face='Verdana, Arial, Helvetica, sans-serif'>Site Information</font></strong></font></div></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#FFFFFF'>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Technician: </b><input name=site_tech type='text' value='$room_details[site_tech]' size=15></font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Telephone: </b><input name=tech_phone type='text' value='$room_details[tech_phone]' size=15></font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Mobile: </b><input name=tech_mobile type='text' value='$room_details[tech_mobile]' size=15></font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Email: </b><input name=tech_email type='text' value='$room_details[tech_email]' size=15></font></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#FFFFFF'>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Trigger Number: </b><input name=site_trigger type='text' value='$room_details[site_trigger]' size=15></font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Call Bandwidth: </b><input name=max_bandwidth type='text' value='$room_details[max_bandwidth]' size=15></font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Room Phone: </b><input name=room_phone type='text' value='$room_details[room_phone]' size=15></font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Network: </b><input name=network_type type='text' value='$room_details[network_type]' size=15></font></td>\n";
		echo "</tr>\n";
		echo "<tr>";
		echo "<td colspan='4' bgcolor='#99CCCC'><div align='center'><font face=Verdana><b>Actual Conference Details</b></font></div></td>";
		echo "</tr>";	
		echo "<tr>";
		echo "<td colspan='2'><font size='2' face=Verdana><b>Start Time: </b><input name=actual_start_time type='text' value='$room_details[actual_start_time]' size=10></td>";
		echo "<td colspan='2'><font size='2' face=Verdana><b>End Time: </b><input name=actual_end_time type='text' value='$room_details[actual_end_time]' size=10></td>";
		echo "</tr>";		
		echo "<tr>";
		echo "<td colspan='2'><font size='2' face=Verdana><b>Dial Type: </b><input name=actual_dial_type type='text' value='$room_details[actual_dial_type]' size=10></td>";
		echo "<td colspan='2'><font size='2' face=Verdana><b>Bandwidth: </b><input name=actual_bandwidth  type='text' value='$room_details[actual_bandwidth]' size=10></td>";
		echo "</tr>";
		echo "<tr>";
		echo "<td colspan='2'><font size='2' face=Verdana><b>Dialled Number: </b><input name=actual_dialled_number type='text' value='$room_details[actual_dialled_number]' size=10></td>";
		echo "<td colspan='2'>&nbsp</td>";
		echo "</tr>";
		echo "<tr> ";
		echo "<td align=center colspan=4><input type='submit' value='Submit'></td>";
		echo "</tr>";
		echo "</table>\n";
		echo "</form>\n";
	}
}
function DisplayActualRoomTimes($call_id) {
	$remote_room_sql = "SELECT * FROM tbl_remote_room_sites WHERE conf_id = $call_id";
	$remote_room_result = mysql_query($remote_room_sql) or die("Unable to read from tbl_tbl_remote_room_sites.  The sql was: <br>" . $remote_room_sql . " <br> the error was:" . mysql_error());
	while ($room_details = mysql_fetch_array($remote_room_result))	
	{
		echo "<table border='0' width='100%' cellspacing='0' cellpadding='0'>\n";
		echo "<tr bgcolor=#99CCCC>\n";
		echo "<td colspan='4' bgcolor='#99CCCC'><div align='center'><font face=Verdana><b>Actual Conference Details</b></font></div></td>";
		echo "</tr>";	
		echo "<tr>";
		echo "<td><font size='2' face=Verdana><b>Start Time: </b>$room_details[actual_start_time]</td>";
		echo "<td><font size='2' face=Verdana><b>End Time: </b>$room_details[actual_end_time]</td>";
		echo "</tr>";		
		echo "<tr bgcolor='#DDDDDD'>";
		echo "<td><font size='2' face=Verdana><b>Dial Type: </b>$room_details[actual_dial_type]</td>";
		echo "<td><font size='2' face=Verdana><b>Bandwidth: </b>$room_details[actual_bandwidth]</td>";
		echo "</tr>";
		echo "<tr>";
		echo "<td><font size='2' face=Verdana><b>Dialled Number: </b>$room_details[actual_dialled_number]</td>";
		echo "<td>&nbsp</td>";
		echo "</tr>";
		echo "</table>\n";
	}
}
function DisplayRemoteRoomDetails($call_id) {
	$remote_room_sql = "SELECT * FROM tbl_remote_room_sites WHERE conf_id = $call_id";
	$remote_room_result = mysql_query($remote_room_sql) or die("Unable to read from tbl_tbl_remote_room_sites.  The sql was: <br>" . $remote_room_sql . " <br> the error was:" . mysql_error());
	while ($room_details = mysql_fetch_array($remote_room_result))
	{
		echo "<table border='0' width='100%' cellspacing='0'>\n";
		echo "<tr bgcolor=#99CCCC>\n";
		echo "<td colspan='4' align=center><font face=Verdana><b>Room Remote Site Information</b></font></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#99CCCC'>\n";
		echo "<td colspan='4'><div align='center'><font size='3' face='Verdana, Arial, Helvetica, sans-serif'>Company Information</font></div></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#FFFFFF'>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Company Name: </b>$room_details[company_name]</font></td>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Site Name: </b>$room_details[site_name]</font></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#DDDDDD'>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Scheduler: $room_details[scheduler_name]</b></font></td>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Telephone: </b>$room_details[scheduler_phone]</font></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#FFFFFF'>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Country:</b> $room_details[site_country]</font></td>\n";
		echo "<td colspan='2'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>City:</b> $room_details[site_city]</font></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#99CCCC'>\n";
		echo "<td colspan='4'><div align='center'><font size='3'><font face='Verdana, Arial, Helvetica, sans-serif'>Site Information</font></font></div></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#FFFFFF'>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Technician: </b>$room_details[site_tech]</font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Telephone: </b>$room_details[tech_phone]</font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Mobile: </b>$room_details[tech_mobile]</font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Email: </b>$room_details[tech_email]</font></td>\n";
		echo "</tr>\n";
		echo "<tr bordercolor='#FFFFFF' bgcolor='#DDDDDD'>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Trigger Number: </b>$room_details[site_trigger]</font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Call Bandwidth: </b>$room_details[max_bandwidth]</font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Room Phone: </b>$room_details[room_phone]</font></td>\n";
		echo "<td width='25%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><b>Network: </b>$room_details[network_type]</font></td>\n";
		echo "</tr>\n";
		echo "</table>\n";
	}
}
function format_date($datetime) {
	$adate=explode('-',$datetime);
	$Mon=Array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');
	$m=$adate[1]-1;
	$d=$adate[2];
	$y=substr($adate[0],2,2);
	$cdate=trim($d)."-".$Mon[$m]."-".trim($y);
	return $cdate;
}
function CMSConnect() {
	$link = @mysql_pconnect("$GLOBALS[cms_system_server]","$GLOBALS[cms_system_user]","$GLOBALS[cms_system_pword]") or die("Could not Connect" . mysql_error());
	mysql_select_db("$GLOBALS[cms_system_db]") or die("Could select the " . $GLOBALS[cms_system_db] . " database.  Error is: " . mysql_error());
}
function DisplayPageHeader() {
   global $color1, $color2, $color3, $color4;
	echo "<table border='0' table width='100%' cellspacing='0' cellpadding='0'>\n";
	echo "<tr>\n";
	echo "<td align=center><b><font face=Verdana size=5 color='$color3'><a href='content.php' target='_top'>Chorus Call South Africa: Call Management System</a></font></td>\n";
	echo "</tr>\n";
   echo "</table>\n";
}
function DisplayCallsOnDate($date_start, $date_end) {
    global $color1, $color2, $color3, $color4;
	$date_start = $date_start . "000000";
	$date_end = $date_end . "235959";
	$query = "SELECT * FROM tbl_conference_reservations WHERE call_datetime <= '$date_end' AND call_datetime >= '$date_start' AND is_cancelled = 'No' ORDER BY call_datetime, company, conf_title";
	$result = mysql_query($query) or die("Query failed : " . mysql_error());
	echo "<table border='0' table width='100%' cellspacing='0' cellpadding='1'>\n";
	echo "<tr>\n";
	if (substr($date_start,0,8) <> substr($date_end,0,8)) echo "<td><b><font face='Verdana' size=2>Date</font></b></td>\n";
	echo "<td><b><font face='Verdana' size=2>Time</font></b></td>\n";
	echo "<td><b><font face='Verdana' size=2>Res #</font></b></td>\n";
	echo "<td><b><font face='Verdana' size=2>Company</font></b></td>\n";
	echo "<td><b><font face='Verdana' size=2>Conference Name</b></td>";
	echo "<td><b><font face='Verdana' size=2>Type</font></b></td>\n";
	echo "<td><b><font face='Verdana' size=2>Bridge</font></b></td>\n";
	echo "<td><b><font face='Verdana' size=2>Operator</font></b></td>\n";
	echo "<td>&nbsp</td>";
	echo "<td>&nbsp</td>";
	echo "<td>&nbsp</td>";
	echo "<td>&nbsp</td>";
	echo "<td>&nbsp</td>";
	echo "</tr>";
	$color2 = "#FFFFFF";  
	$color1 = "#DDDDDD";  
	$row_count = 1;
	while ($line = mysql_fetch_array($result))
	{
		$bridge = $line['bridge'];
		$call_id = $line['call_id'];
		$conference_type = $line['conference_type'];
		$call_datetime = $line['call_datetime'];
		$call_time = substr($call_datetime, 11, 5);
		$call_date = substr ($call_datetime,0,10);
		$leadop = $line['leadop'];
		
		//sort out company and conference name string lengths
		$company = $line["company"];
		if (strlen($company) > 25) $company = (substr($company, 0, 25));
		$conf_title = $line['conf_title'];
		if (strlen($conf_title) > 35) $conf_title = (substr($conf_title, 0, 35));
		$row_color = ($row_count % 2) ? $color1 : $color2;
		if (($line[num_di_lines] >= 50) OR ($line[num_do_lines] >= 50)){
			echo "<tr bgcolor=$row_color>";
			if (substr($date_start,0,8) <> substr($date_end,0,8)) echo "<td><b><font face='Verdana' size=2>$call_date</font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2>$call_time</font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2>$call_id</font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2>$company</font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2>$conf_title</font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2>$conference_type</font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2>$bridge</font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2>$leadop</font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2><A HREF=display_conference.php?call_id=$call_id&view_type=internal>Internal</a></font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2><A HREF=display_conference.php?call_id=$call_id&view_type=client>Client</a></b></td>\n";
			echo "<td><b><font face='Verdana' size=2><A HREF=reservation.php?call_id=$call_id&action=editgeneral>Edit</a></font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2><A HREF=reservation.php?call_id=$call_id&action=copy>Copy</a></font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2><A HREF=reservation.php?call_id=$call_id&action=cancelconfirm>Cancel</a></font></b></td>\n";
			echo "<td><b><font face='Verdana' size=2><A HREF=script.php?call_id=$call_id>Script</a></font></b></td>\n";
			echo "</tr>\n";
		} else {
			echo "<tr bgcolor=$row_color>";
			if (substr($date_start,0,8) <> substr($date_end,0,8)) echo "<td><font face='Verdana' size=2>$call_date</font></td>\n";
			echo "<td><font face='Verdana' size=2>$call_time</font></td>\n";
			echo "<td><font face='Verdana' size=2>$call_id</font></td>\n";
			echo "<td><font face='Verdana' size=2>$company</font></td>\n";
			echo "<td><font face='Verdana' size=2>$conf_title</font></td>\n";
			echo "<td><font face='Verdana' size=2>$conference_type</font></td>\n";
			echo "<td><font face='Verdana' size=2>$bridge</font></td>\n";
			echo "<td><font face='Verdana' size=2>$leadop</font></td>\n";
			echo "<td><font face='Verdana' size=2><A HREF=display_conference.php?call_id=$call_id&view_type=internal>Internal</a></font></td>\n";
			echo "<td><font face='Verdana' size=2><A HREF=display_conference.php?call_id=$call_id&view_type=client>Client</a></td>\n";
			echo "<td><font face='Verdana' size=2><A HREF=reservation.php?call_id=$call_id&action=editgeneral>Edit</a></font></td>\n";
			echo "<td><font face='Verdana' size=2><A HREF=reservation.php?call_id=$call_id&action=copy>Copy</a></font></td>\n";
			echo "<td><font face='Verdana' size=2><A HREF=reservation.php?call_id=$call_id&action=cancelconfirm>Cancel</a></font></td>\n";
			echo "<td><font face='Verdana' size=2><A HREF=script.php?call_id=$call_id>Script</a></font></td>\n";
			echo "</tr>\n";
		
		}
		$row_count++;
	}
	
	echo "</table>\n";
}
function DisplayGeneralCallDetails($call_id) {
   global $color1, $color2, $color3, $color4;
	$general_sql = "SELECT * FROM tbl_conference_reservations WHERE call_id = '$call_id'";
	$general_result = mysql_query($general_sql) or die("Unable to read from tbl_conference_reservations.  The sql was: <br>" . $general_sql . " <br> the error was:" . mysql_error());;
	$general_data = mysql_fetch_array($general_result);
	echo "<table width='100%' border=0 cellpadding=1 cellspacing=0>\n";
		echo "<tr bgcolor='$color1'>\n";
			echo "<td colspan=3 align=center><b><FONT size=3 face=Verdana>$general_data[conference_type] Conference Details - Reservation $call_id</font></b></td>\n";
		echo "</tr>\n";
		echo "<tr bgcolor=#DDDDDD>\n";
			echo "<td width=33%><font face=Verdana size=2><b>Company: </b>$general_data[company] </font></td>\n";
			echo "<td width=33%><font face=Verdana size=2><b>Conference Name: </b> $general_data[conf_title] </font></td>\n";
			echo "<td><b><font face=Verdana size=2>Account Number: </b> $general_data[account_number] </td>";
		echo "</tr>\n";
		echo "<tr>\n";
			echo "<td><font face=Verdana size=2><b>Call Date: </b>" . substr($general_data[call_datetime],0,10) . " </font></td>\n";
			echo "<td><font face=Verdana size=2><b>Call Time: </b>" . substr($general_data[call_datetime],11,5) . " SAST/CAT</font></td>\n";
			echo "<td><b><font face=Verdana size=2>Duration: </b>" . substr($general_data[call_duration],0,5) . "</font></td>\n";
		echo "</tr>\n";
		echo "<tr bgcolor=#DDDDDD>\n";
			echo "<td><b><font face=Verdana size=2>Scheduler: </b>$general_data[scheduler]</font></td>\n";
			echo "<td><b><font face=Verdana size=2>Telephone: </b>$general_data[scheduler_tel]</font></td>\n";
			echo "<td><font face=Verdana size=2><b>Client Reference: </b>$general_data[client_ref]</font></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
			echo "<td><b><font face=Verdana size=2>Chairperson: </b>$general_data[chair_name]</font></td>\n";
			echo "<td><b><font face=Verdana size=2>Number: </b>$general_data[chair_number]</font></td>\n";
			echo "<td><b><font face=Verdana size=2>Main Bridge: </b>$general_data[bridge]</font></td>\n";
		echo "</tr>\n";
		echo "<tr bgcolor=#DDDDDD>\n";
			echo "<td><b><font face=Verdana size=2>Dial Out Parties: </B>$general_data[num_do_lines]</font></td>\n";
			echo "<td><b><font face=Verdana size=2>Dial In Parties: </b>$general_data[num_di_lines]</font></td>\n";
			echo "<td><b><font face=Verdana size=2>Lead Operator: </b>$general_data[leadop]</font></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
			echo "<td colspan=3><b><font face=Verdana size=2>Note: </b> $general_data[notes]</font></td>\n";
		echo "</tr>\n";
	echo "</table>\n";
}
function DisplayServices($call_id, $cms_system_currency, $conference_type) {
	global $color1, $color2, $color3, $color4;
	//display the billable header
	$row_count = 1;
	$color2 = "#FFFFFF";
	$color1 = "#DDDDDD";
	echo "<table border=0 cellspacing=0 width='100%'>\n";
	echo "<tr bgcolor='#99CCCC'>\n";
	echo "<td colspan=3><p align=center><font face=Verdana><b>Billable $conference_type Services</b></font></p></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td width='33%'><font face=Verdana size=3><b>Service</b></font></td>\n";
	echo "<td width='33%'><font face=Verdana size=3><b>Cost</b></font></td>\n";
	echo "<td width='33%'><font face=Verdana size=3><b>Details</b></font></td>\n";
	echo "</tr>\n";
	//get the service details data from tbl_service_link
	$service_details_sql = "SELECT * FROM tbl_service_link WHERE call_id='$call_id' ORDER BY service_id";
	$service_details_result = mysql_query($service_details_sql) or die("Service details query failed. <br> The sql was: <br> $service_details_sql <br> The error was: <br> " . mysql_error());
	while ($service_details_data = mysql_fetch_array($service_details_result))
	{
		$row_color = ($row_count % 2) ? $color1 : $color2;
		//first the billable services
		$service_header_sql = "SELECT * FROM tbl_service_list WHERE service_id = '$service_details_data[service_id]' AND billable = 'Yes' ORDER BY service_name";
		$service_header_result = mysql_query($service_header_sql) or die ("Service header query failed. <br> The sql was: <br> $service_header_sql <br> The error was: <br> " . mysql_error());
		while ($service_header_data = mysql_fetch_array($service_header_result)) {
			echo "<tr bgcolor=$row_color>\n";
			echo "<td><font face=Verdana size=2><b>$service_header_data[service_name]</td>\n";
			echo "<td><font face=Verdana size=2>$cms_system_currency $service_details_data[rate] per $service_header_data[billed_per]</b></td>\n";
				echo "<td>\n";
					if (!empty($service_header_data[header1])) echo "<font face=Verdana size=2><b>$service_header_data[header1]: </b>$service_details_data[info1]<br>\n";
					if (!empty($service_header_data[header2])) echo "<font face=Verdana size=2><b>$service_header_data[header2]: </b>$service_details_data[info2]<br>\n";
					if (!empty($service_header_data[header3])) echo "<font face=Verdana size=2><b>$service_header_data[header3]: </b>$service_details_data[info3]<br>\n";
					if (!empty($service_header_data[header4])) echo "<font face=Verdana size=2><b>$service_header_data[header4]: </b>$service_details_data[info4]<br>\n";
					if (!empty($service_header_data[header5])) echo "<font face=Verdana size=2><b>$service_header_data[header5]: </b>$service_details_data[info5]<br>\n";
					if (!empty($service_header_data[header6])) echo "<font face=Verdana size=2><b>$service_header_data[header6]: </b>$service_details_data[info6]<br>\n";
					if (!empty($service_header_data[header7])) echo "<font face=Verdana size=2><b>$service_header_data[header7]: </b>$service_details_data[info7]<br>\n";
					if (!empty($service_header_data[header8])) echo "<font face=Verdana size=2><b>$service_header_data[header8]: </b>$service_details_data[info8]<br>\n";
					if (!empty($service_header_data[header9])) echo "<font face=Verdana size=2><b>$service_header_data[header9]: </b>$service_details_data[info9]<br>\n";
					if (!empty($service_header_data[header10])) echo "<font face=Verdana size=2><b>$service_header_data[header10]: </b>$service_details_data[info10]<br>\n";
				echo "</td>\n";
			echo "</tr>\n";
			$row_count++;
		}
	}
	echo "</table>\n";
	//display the non-billable header
	$row_count = 1;
	$color2 = "#FFFFFF";
	$color1 = "#DDDDDD";
	echo "<table border=0 cellspacing=0 width='100%'>\n";
	echo "<tr bgcolor='#99CCCC'>\n";
		echo "<td colspan=2><p align=center><font face=Verdana><b>Non-Billable $conference_type Services</b></font></p></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
		echo "<td width='50%'><font face=Verdana size=3><b>Service</b></font></td>\n";
		echo "<td width='50%'><font face=Verdana size=3><b>Details</b></font></td>\n";
	echo "</tr>\n";
	//now the non-billable services
	$service_details_sql = "SELECT * FROM tbl_service_link WHERE call_id='$call_id' ORDER BY service_id";
	$service_details_result = mysql_query($service_details_sql) or die("Service details query failed. <br> The sql was: <br> $service_details_sql <br> The error was: <br> " . mysql_error());
	while ($service_details_data = mysql_fetch_array($service_details_result))
	{
		
		$service_header_sql = "SELECT * FROM tbl_service_list WHERE service_id = '$service_details_data[service_id]' AND billable <> 'Yes' ORDER BY service_name";
		$service_header_result = mysql_query($service_header_sql) or die ("Service header query failed. <br> The sql was: <br> $service_header_sql <br> The error was: <br> " . mysql_error());
		while ($service_header_data = mysql_fetch_array($service_header_result))
		{
			$row_color = ($row_count % 2) ? $color1 : $color2;
			echo "<tr bgcolor=$row_color>\n";
			echo "<td><font face=Verdana size=2><b>$service_header_data[service_name]</td>\n";
				echo "<td>\n";
					if (!empty($service_header_data[header1])) echo "<font face=Verdana size=2><b>$service_header_data[header1]: </b>$service_details_data[info1]<br>\n";
					if (!empty($service_header_data[header2])) echo "<font face=Verdana size=2><b>$service_header_data[header2]: </b>$service_details_data[info2]<br>\n";
					if (!empty($service_header_data[header3])) echo "<font face=Verdana size=2><b>$service_header_data[header3]: </b>$service_details_data[info3]<br>\n";
					if (!empty($service_header_data[header4])) echo "<font face=Verdana size=2><b>$service_header_data[header4]: </b>$service_details_data[info4]<br>\n";
					if (!empty($service_header_data[header5])) echo "<font face=Verdana size=2><b>$service_header_data[header5]: </b>$service_details_data[info5]<br>\n";
					if (!empty($service_header_data[header6])) echo "<font face=Verdana size=2><b>$service_header_data[header6]: </b>$service_details_data[info6]<br>\n";
					if (!empty($service_header_data[header7])) echo "<font face=Verdana size=2><b>$service_header_data[header7]: </b>$service_details_data[info7]<br>\n";
					if (!empty($service_header_data[header8])) echo "<font face=Verdana size=2><b>$service_header_data[header8]: </b>$service_details_data[info8]<br>\n";
					if (!empty($service_header_data[header9])) echo "<font face=Verdana size=2><b>$service_header_data[header9]: </b>$service_details_data[info9]<br>\n";
					if (!empty($service_header_data[header10])) echo "<font face=Verdana size=2><b>$service_header_data[header10]: </b>$service_details_data[info10]<br>\n";
				echo "</td>\n";
			echo "</tr>\n";
			$row_count++;
		}
	}
	echo "</table>\n";
	echo "</table>\n";
	echo "</table>\n";
	
}
function DisplayClientServices($call_id, $cms_system_currency, $conference_type) {
	global $color1, $color2, $color3, $color4;
	//display the billable header
	echo "<table border=0 cellspacing=0 width='100%'>\n";
		echo "<tr bgcolor='#99CCCC'>\n";
			echo "<td colspan=5><p align=center><font face=Verdana><b>Billable $conference_type Services</b></font></p></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
			echo "<td><font face=Verdana size=3><b>Service</b></font></td>\n";
			echo "<td><font face=Verdana size=3><b>Details</b></font></td>\n";
		echo "</tr>\n";
		//get the service details data from tbl_service_link
		$service_details_sql = "SELECT * FROM tbl_service_link WHERE call_id='$call_id' ORDER BY service_id";
		$service_details_result = mysql_query($service_details_sql) or die("Service details query failed. <br> The sql was: <br> $services_sql <br> The error was: <br> " . mysql_error());
		$row_count = 1;
		$color1 = "#FFFFFF";
		$color2 = "#DDDDDD";
		while ($service_details_data = mysql_fetch_array($service_details_result)) {
			//first the billable services
			$service_header_sql = "SELECT * FROM tbl_service_list WHERE service_id = '$service_details_data[service_id]' AND billable = 'Yes'";
			$service_header_result = mysql_query($service_header_sql) or die ("Service header query failed. <br> The sql was: <br> $service_header_sql <br> The error was: <br> " . mysql_error());
			while ($service_header_data = mysql_fetch_array($service_header_result))
			{
				$row_color = ($row_count % 2) ? $color1 : $color2;
				echo "<tr bgcolor=$row_color>\n";
						echo "<td><font face=Verdana size=2><b>$service_header_data[service_name]</td>\n";
						echo "<td>\n";
						if (!empty($service_header_data[header1])) echo "<font face=Verdana size=2><b>$service_header_data[header1]: </b>$service_details_data[info1]<br>\n";
						if (!empty($service_header_data[header2])) echo "<font face=Verdana size=2><b>$service_header_data[header2]: </b>$service_details_data[info2]<br>\n";
						if (!empty($service_header_data[header3])) echo "<font face=Verdana size=2><b>$service_header_data[header3]: </b>$service_details_data[info3]<br>\n";
						if (!empty($service_header_data[header4])) echo "<font face=Verdana size=2><b>$service_header_data[header4]: </b>$service_details_data[info4]<br>\n";
						if (!empty($service_header_data[header5])) echo "<font face=Verdana size=2><b>$service_header_data[header5]: </b>$service_details_data[info5]<br>\n";
						if (!empty($service_header_data[header6])) echo "<font face=Verdana size=2><b>$service_header_data[header6]: </b>$service_details_data[info6]<br>\n";
						if (!empty($service_header_data[header7])) echo "<font face=Verdana size=2><b>$service_header_data[header7]: </b>$service_details_data[info7]<br>\n";
						if (!empty($service_header_data[header8])) echo "<font face=Verdana size=2><b>$service_header_data[header8]: </b>$service_details_data[info8]<br>\n";
						if (!empty($service_header_data[header9])) echo "<font face=Verdana size=2><b>$service_header_data[header9]: </b>$service_details_data[info9]<br>\n";
						if (!empty($service_header_data[header10])) echo "<font face=Verdana size=2><b>$service_header_data[header10]: </b>$service_details_data[info10]<br>\n";
						echo "</td>\n";
				echo "</tr>\n";
				$row_count++;
			}
		}
	echo "</table>\n";
	//display the non-billable header
	echo "<table border=0 cellspacing=0 width='100%'>\n";
		echo "<tr bgcolor='#99CCCC'>\n";
			echo "<td colspan=4><p align=center><font face=Verdana><b>Non-Billable $conference_type Services</b></font></p></td>\n";
		echo "</tr>\n";
			echo "<tr>\n";
			echo "<td><font face=Verdana size=3><b>Service</b></font></td>\n";
			echo "<td><font face=Verdana size=3><b>Details</b></font></td>\n";
			echo "</tr>\n";
		//now the non-billable services
		$row_count = 1;
		$color1 = "#FFFFFF";
		$color2 = "#DDDDDD";
		$service_details_sql = "SELECT * FROM tbl_service_link WHERE call_id='$call_id' ORDER BY service_id";
		$service_details_result = mysql_query($service_details_sql) or die("Service details query failed. <br> The sql was: <br> $services_sql <br> The error was: <br> " . mysql_error());
		while ($service_details_data = mysql_fetch_array($service_details_result)) {
			
			$service_header_sql = "SELECT * FROM tbl_service_list WHERE service_id = '$service_details_data[service_id]' AND billable <> 'Yes'";
			$service_header_result = mysql_query($service_header_sql) or die ("Service header query failed. <br> The sql was: <br> $service_header_sql <br> The error was: <br> " . mysql_error());
			while ($service_header_data = mysql_fetch_array($service_header_result)) {
				$row_color = ($row_count % 2) ? $color1 : $color2;
				echo "<tr bgcolor=$row_color>\n";
					echo "<td><font face=Verdana size=2><b>$service_header_data[service_name]</td>\n";
					echo "<td>\n";
					if (!empty($service_header_data[header1])) echo "<font face=Verdana size=2><b>$service_header_data[header1]: </b>$service_details_data[info1]<br>\n";
						if (!empty($service_header_data[header2])) echo "<font face=Verdana size=2><b>$service_header_data[header2]: </b>$service_details_data[info2]<br>\n";
						if (!empty($service_header_data[header3])) echo "<font face=Verdana size=2><b>$service_header_data[header3]: </b>$service_details_data[info3]<br>\n";
						if (!empty($service_header_data[header4])) echo "<font face=Verdana size=2><b>$service_header_data[header4]: </b>$service_details_data[info4]<br>\n";
						if (!empty($service_header_data[header5])) echo "<font face=Verdana size=2><b>$service_header_data[header5]: </b>$service_details_data[info5]<br>\n";
						if (!empty($service_header_data[header6])) echo "<font face=Verdana size=2><b>$service_header_data[header6]: </b>$service_details_data[info6]<br>\n";
						if (!empty($service_header_data[header7])) echo "<font face=Verdana size=2><b>$service_header_data[header7]: </b>$service_details_data[info7]<br>\n";
						if (!empty($service_header_data[header8])) echo "<font face=Verdana size=2><b>$service_header_data[header8]: </b>$service_details_data[info8]<br>\n";
						if (!empty($service_header_data[header9])) echo "<font face=Verdana size=2><b>$service_header_data[header9]: </b>$service_details_data[info9]<br>\n";
						if (!empty($service_header_data[header10])) echo "<font face=Verdana size=2><b>$service_header_data[header10]: </b>$service_details_data[info10]<br>\n";
					echo "</td>\n";
				echo "</tr>\n";
				$row_count++;
			}
		}
	echo "</table>\n";
	
}
function DisplayDialOutParties($call_id) {
	global $color1, $color2, $color3, $color4;
	$dial_out_parties_sql = "SELECT * FROM tbl_audio_dial_parties WHERE call_id = '$call_id'";
	$dial_out_parties_result = mysql_query($dial_out_parties_sql) or die("Dial Out Parties query failed. <br> The sql was: <br> $dial_out_parties_sql <br> The error was: <br> " . mysql_error());
	echo "<table border=0 cellpadding=1 cellspacing=0 width='100%'>\n";
	echo "<tr bgcolor='#99CCCC'>";
	echo "<td colspan=4 align=center><font face=Verdana><b>Dial-Out Parties</b></font></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
		echo "<td>&nbsp</td>\n";
		echo "<td><font face=Verdana size=3><b>Party Name</b></font></td>\n";
		echo "<td><font face=Verdana size=3><b>Party Number</b></font></td>\n";
		echo "<td><font face=Verdana size=3><b>Party Notes</b></font></td>\n";
	echo "</tr>\n";
	$row_count = 1;
	$color2 = "#FFFFFF";
	$color1 = "#DDDDDD";
	while ($dial_out_parties_data = mysql_fetch_array($dial_out_parties_result))
	{
		$row_color = ($row_count % 2) ? $color1 : $color2;
		echo "<tr bgcolor=$row_color>";
			echo "<td width=2%><font face=Verdana size=2>$row_count.</font></td>\n";
			echo "<td><font face=Verdana size=2>$dial_out_parties_data[party_name]</font></td>\n";
			echo "<td><font face=Verdana size=2>$dial_out_parties_data[party_number]</font></td>\n";
			echo "<td><font face=Verdana size=2>$dial_out_parties_data[party_notes]</font></td>\n";
		echo "</tr>\n";
		$row_count++;
	}
	echo "<tr>\n";
	echo "<td align=center colspan=2><font face=Verdana size=2><b><a href=reservation.php?call_id=$call_id&action=editaudioparties>Edit Audio Parties</a></b></font></td>\n";
	echo "<td align=center colspan=2><font face=Verdana size=2><b><a href=reservation.php?call_id=$call_id&action=addaudioparties>Add Audio Parties</a></b></font></td>\n";
	echo "</tr>\n";
	echo "</table>\n";
}
function DisplayClientDialOutParties($call_id) {
	global $color1, $color2, $color3, $color4;
	$dial_out_parties_sql = "SELECT * FROM tbl_audio_dial_parties WHERE call_id = '$call_id'";
	$dial_out_parties_result = mysql_query($dial_out_parties_sql) or die("Dial Out Parties query failed. <br> The sql was: <br> $dial_out_parties_sql <br> The error was: <br> " . mysql_error());
	echo "<table cellpadding=1 cellspacing=0 width='100%'>\n";
	echo "<tr bgcolor='#99CCCC'>";
	echo "<td colspan=3 align=center><font face=Verdana><b>Dial-Out Parties</b></font></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
		echo "<td><font face=Verdana size=3><b>Party Name</b></font></td>\n";
		echo "<td><font face=Verdana size=3><b>Party Number</b></font></td>\n";
		echo "<td><font face=Verdana size=3><b>Party Notes</b></font></td>\n";
	echo "</tr>\n";
	$row_count = 1;
	$color2 = "#FFFFFF";
	$color1 = "#DDDDDD";
	while ($dial_out_parties_data = mysql_fetch_array($dial_out_parties_result))
	{
		$row_color = ($row_count % 2) ? $color1 : $color2;
		echo "<tr bgcolor=$row_color>";
		echo "<td><font face=Verdana size=2>$dial_out_parties_data[party_name]</font></td>\n";
		echo "<td><font face=Verdana size=2>$dial_out_parties_data[party_number]</font></td>\n";
		echo "<td><font face=Verdana size=2>$dial_out_parties_data[party_notes]</font></td>\n";
		echo "</tr>\n";
		$row_count++;
	}
	echo "</table>\n";
}
function EditIntlNumbers($call_id) {
	//set up the form and table
	echo "<form action='reservation.php?action=commitbridgeslist&call_id=$call_id' method='post' enctype='multipart/form-data'>\n";
	echo "<table border='1' table width='100%' cellspacing='0' cellpadding='0' bordercolor='black' rules=rows>\n";
	echo "<tr bgcolor=#99CCCC>\n";
		echo "<td colspan='5' align=center><font face=Verdana><b>Access Numbers to use</b></font></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
		echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><strong>Use</strong></font></td>\n";
		echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><strong>Number Type</strong></font></td>\n";
		echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><strong>Country</strong></font></td>\n";
		echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><strong>Bridge</strong></font></td>\n";
		echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><strong>Access Number</strong></font></div></td>\n";
	echo "</tr>\n";
	//get the full bridges list
	$bridges_list_sql = "SELECT * FROM tbl_bridges ORDER BY bridge_name, country, number_type";
	$bridges_list_result = mysql_query($bridges_list_sql) or die("Bridge list query failed. <br> The sql was: <br> $bridges_list_sql <br> The error was: <br>  " . mysql_error()); 
	//start the bridges list loop
	$counter=1;
	while ($bridge_list_data = mysql_fetch_array($bridges_list_result)) {
		//get the list of used bridges
		$used_bridges_sql = "SELECT * FROM tbl_bridges_link WHERE call_id = $call_id AND bridge_id = $bridge_list_data[id]";	
		$used_bridges_result = mysql_query($used_bridges_sql) or die("Used Bridge list query failed. <br> The sql was: <br> $used_bridges_sql <br> The error was: <br>  " . mysql_error());
		//check if current bridge is used.  if it is, the checkbox must be ticked, if not, blank checkbox
		if ($used_bridges_data = mysql_fetch_array($used_bridges_result)) {
			echo "<tr>\n";
				echo "<td><input type=hidden name=bridge_id_$counter value=$bridge_list_data[id]><input type=checkbox name=bridge_used_$counter value=Yes checked></td>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$bridge_list_data[number_type]</font></td>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$bridge_list_data[country]</font></td>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$bridge_list_data[bridge_name]</font></td>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$bridge_list_data[tel_number]</font></div></td>\n";
			echo "</tr>\n";
		
		}
		else {
			echo "<tr>\n";
				echo "<td><input type=hidden name=bridge_id_$counter value=$bridge_list_data[id]><input type=checkbox name=bridge_used_$counter value=Yes></td>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$bridge_list_data[number_type]</font></td>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$bridge_list_data[country]</font></td>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$bridge_list_data[bridge_name]</font></td>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$bridge_list_data[tel_number]</font></div></td>\n";
			echo "</tr>\n";
		
		}
		$counter++;
	}
	$counter--;
	//close the form and table
	echo "<input type=hidden name=counter value=$counter>\n";
	echo "<input type=hidden name=call_id value=$call_id>\n";
	echo "<td colspan='5' align=center><input type=submit value='Update List'></td>\n";
	echo "</table>\n";
	echo "</form>\n";
}
function DisplayUsedNumbersList($call_id) {
	//set up the table for the live call numbers
	echo "<table border='0' width='100%' cellspacing='0' cellpadding='0'>\n";
		echo "<tr bgcolor=#99CCCC>\n";
			echo "<td colspan='2' align=center><font face=Verdana><b>Live Call Access Numbers For Participants</b></font></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
			echo "<td width=50%><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><strong>Country</strong></font></td>\n";
			echo "<td width='50%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><strong>Access Number</strong></font></div></td>\n";
		echo "</tr>\n";
		//get the list of used bridges for the live call
		$used_bridges_sql = "SELECT * FROM vw_call_bridges WHERE call_id = $call_id AND number_type <> 'Playback' AND number_type <> 'Video' ORDER BY bridge_name";
		//echo "<br>$used_bridges_sql<br>";
		$used_bridges_result = mysql_query($used_bridges_sql) or die("Used Bridge list query failed. <br> The sql was: <br> $used_bridges_sql <br> The error was: <br>  " . mysql_error());
		$row_count = 1;
		$color2 = "#FFFFFF";
		$color1 = "#DDDDDD";
		while ($used_bridges_data = mysql_fetch_array($used_bridges_result)) {
			$row_color = ($row_count % 2) ? $color1 : $color2;
			echo "<tr bgcolor=$row_color>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$used_bridges_data[country]</font></td>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$used_bridges_data[tel_number]</font></div></td>\n";
			echo "</tr>\n";
			$row_count++;
		}
	echo "</table>\n";
	//set up the table for the playback numbers
	echo "<table border='0' width='100%' cellspacing='0' cellpadding='0'>\n";
		echo "<tr bgcolor=#99CCCC>\n";
			echo "<td colspan='2' align=center><font face=Verdana><b>Playback Access Numbers</b></font></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
			echo "<td width='50%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><strong>Country</strong></font></td>\n";
			echo "<td width='50%'><font size='2' face='Verdana, Arial, Helvetica, sans-serif'><strong>Access Number</strong></font></div></td>\n";
		echo "</tr>\n";
		//get the list of used bridges for the live call
		$used_bridges_sql = "SELECT * FROM vw_call_bridges WHERE call_id = $call_id AND number_type = 'Playback' ORDER BY bridge_name";
		$used_bridges_result = mysql_query($used_bridges_sql) or die("Used Bridge list query failed. <br> The sql was: <br> $used_bridges_sql <br> The error was: <br>  " . mysql_error());
		$row_count = 1;
		$color1 = "#FFFFFF";
		$color2 = "#DDDDDD";
		while ($used_bridges_data = mysql_fetch_array($used_bridges_result)) {
			$row_color = ($row_count % 2) ? $color1 : $color2;
			echo "<tr bgcolor=$row_color>";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$used_bridges_data[country]</font></td>\n";
				echo "<td><font size='2' face='Verdana, Arial, Helvetica, sans-serif'>$used_bridges_data[tel_number]</font></div></td>\n";
			echo "</tr>\n";
			$row_count++;
		}
	echo "</table>\n";
}
function DisplayDistinctBridges($call_id) {
	global $color1, $color2, $color3, $color4;
	$distinct_bridges_sql = "SELECT DISTINCT bridge_name FROM vw_call_bridges WHERE call_id = '$call_id' ORDER BY bridge_name";
	//echo "<br>$distinct_bridges_sql<br>";
	$distinct_bridges_result = mysql_query($distinct_bridges_sql) or die("Distinct Bridges query failed. <br> The sql was: <br> $distinct_bridges_sql <br> The error was: <br>  " . mysql_error());
	$color2 = "#FFFFFF";
	$color1 = "#DDDDDD";
	$row_color = ($row_count % 2) ? $color1 : $color2;
	echo "<table border=0 cellpadding=1 width='100%'>\n";
		echo "<tr><td bgcolor='#99CCCC' align=center><font face=Verdana size=3><b>This Reservation will be active on the following bridges:</b></font></td></tr>\n";
	while ($distinct_bridges_data = mysql_fetch_array($distinct_bridges_result)) {
		$row_color = ($row_count % 2) ? $color1 : $color2;
		echo "<tr bgcolor=$row_color><td colspan=6 align=center><font face=Verdana size=2>$distinct_bridges_data[bridge_name]</font></td></tr>\n";
		$row_count++;
	}
	echo "</table>\n";
}	
function DisplayEdits($call_id) {
	global $color1, $color2, $color3, $color4;
	$edited_sql = "SELECT taken_by, last_edited, last_edited_by, res_date from tbl_conference_reservations where call_id='$call_id'";
	$edited_result = mysql_query($edited_sql) or die("Edited query failed. <br> The sql was: <br> $edited_sql <br> The error was: <br>  " . mysql_error());
	while ($edited_data = mysql_fetch_array($edited_result))
	{
		echo "<table border=0 cellspacing=0 width='100%'>\n";
		echo "<tr>\n";
		echo "<td colspan=6 bgcolor='$color1' align=center><font face=Verdana size=2>This Reservation was taken on $edited_data[res_date] from the computer $edited_data[taken_by] </font></td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td colspan=6 bgcolor='$color1' align=center><font face=Verdana size=2>This call was last edited on $edited_data[last_edited] from the computer $edited_data[last_edited_by] </font></td>\n";
		echo "</tr>\n";
		echo "</table>\n";
	}
}
function MonthDropDown($selected_month) {
	$month = 1;
	while ($month <= 12)
	{
		if ($month == $selected_month)
		echo "<option selected value = '$month'>$month</option>\n";
		else
		echo "<option value = '$month'>$month</option>\n";
		$month++;
	}
}
function DayDropDown($selected_day) {
	$day = 1;
	while ($day <= 31)
	{
		if ($day == $selected_day)
		echo "<option selected value = '$day'>$day</option>\n";
		else
		echo "<option value = '$day'>$day</option>\n";
		$day++;
	}	
}
function YearDropDown($selected_year) {
	$first_year = '2001';
	$current_year  = date("Y");
	$next_year = date("Y")+1;
	$year = $first_year;
	while (($year >= 2001) AND ($year <= $next_year))
	{
		if ($year == $selected_year) 
		echo "<option selected value='$year'>$year</option>\n";
		else echo "<option value='$year'>$year</option>\n";
		$year++;
	}
}
function HourDropDown($selected_hour) {
	$hour = 0;
	while ($hour <= 23)
	{
		if ($hour == $selected_hour)
		echo "<option selected value = '$hour'>$hour</option>\n";
		else
		echo "<option value = '$hour'>$hour</option>\n";
		$hour++;
	}	
}
function MinuteDropDown($selected_minute) {
	$minute = 0;
	while ($minute <= 59)
	{
		if ($minute == $selected_minute)
		echo "<option selected value = '$minute'>$minute</option>\n";
		else
		echo "<option value = '$minute'>$minute</option>\n";
		$minute = $minute+5;
	}	
}	
function ValidEmail($email) {
	if (ereg('^[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+'.
              '@'.
              '[-!#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.'.
              '[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+$', $email)) 
	return true;
	else return false;
}
function EditGeneralCallDetails($call_id, $action) {
    global $color1, $color2, $color3, $color4;
    switch ($action):
		case 'edit':
			$general_sql = "SELECT * FROM tbl_conference_reservations WHERE call_id = '$call_id'";
			$general_result = mysql_query($general_sql) or die("Unable to read from tbl_conference_reservations.  The sql was: <br>" . $general_sql . " <br> the error was:" . mysql_error());
			$general_data = mysql_fetch_array($general_result);
			echo "<form action='reservation.php?call_id=$call_id&action=editservices' method='post' enctype='multipart/form-data'>";
			echo "<table width='100%' border=0 cellpadding=0 cellspacing=1>\n";
				echo "<tr bgcolor='$color1'> \n";
					echo "<td align=center  colspan=4><b><font size=4 face=Verdana>$general_data[conference_type] Call Details</font></b></td>\n";
				echo "</tr>\n";
				echo "<tr> \n";
					echo "<td width='25%'><font face=Verdana color='$color3' size=2>Company:</font><br></font><input name=company value='$general_data[company]'></td>\n";
					echo "<td width='25%'><font face=Verdana size=2 color='$color3'>Conference Title:</font><br><input name=conf_title value='$general_data[conf_title]'></td>\n";
					echo "<td width='25%'><font face=Verdana font size=2>Account Number:</font><br><input name=account_number value='$general_data[account_number]'></td>\n";
					echo "<td width='25%'><font face=Verdana size=2>Client Reference:<br></font><input name=client_ref value='$general_data[client_ref]'></td>\n";
				echo "</tr>\n";
				echo "<tr>\n";
					echo "<td><font face=Verdana font size=2>Date of call:<br>";
						//echo "<br>" . $general_data[call_datetime] . "<br>";
						$callDate = substr($general_data[call_datetime], 0, 10);
						$month = substr($general_data[call_datetime], 5,2);
						$day = substr($general_data[call_datetime], 8, 2);
						$hour = substr($general_data[call_datetime], 11, 2);
						$minute = substr($general_data[call_datetime], 14, 2);
						//echo "<br> hour: $hour <br>";
						echo "<input name=callDate maxlength=10 value=$callDate>\n";
					echo "<td  width=25%><font face=Verdana size=2><font face=Verdana font size=2>Time of call:<br>\n";
					echo "<select name=call_datetime_hh>\n";
						HourDropDown($hour);
					echo "</select>\n";
					echo "<select name=call_datetime_mm>\n";
						MinuteDropDown($minute);
					echo "</select>\n";
					echo "</font></font>\n";
					echo "<br><font face=Verdana color=#000000 size=2>24-hour time\n";
					echo "</td>\n";
						
				echo "<td><font face=Verdana font size=2>Expected Duration of call:<br>";
				echo "<select name=call_duration_hh>\n";
						$duration_hour = substr($general_data[call_duration], 0, 2);
						HourDropDown($duration_hour);
					echo "</select>\n";
				echo "<select name=call_duration_mm>";
				$duration_minute = substr($general_data[call_duration], 3, 2);
				MinuteDropDown($duration_minute);
				echo "</select>\n";
				echo "</td>\n";
				
				echo "</tr>\n";
				echo "<tr>\n";
					echo "<td><font face=Verdana><font size=2>Scheduler Name:<br></font><input name=scheduler value='$general_data[scheduler]'></font></td>\n";
					echo "<td><font face=Verdana><font size=2>Scheduler Number:<br></font><input name=scheduler_tel value='$general_data[scheduler_tel]'></font></td>\n";
					echo "<td><font face=Verdana><font size=2>Chairperson Name:<br></font><input name=chair_name value='$general_data[chair_name]'></font></td>\n";
					echo "<td><font face=Verdana><font size=2>Chairperson Number:</font><br><input name=chair_number value='$general_data[chair_number]'></font></td>\n";
				echo "</tr>\n";	
				echo "<tr>\n";	
					echo "<td><font face=Verdana size=2>Number of Dial-In lines:</font><br><input name=num_di_lines value='$general_data[num_di_lines]'></td>\n";
					echo "<td><font face=Verdana size=2>Number of Dial-Out lines:</font><br><input name=num_do_lines value='$general_data[num_do_lines]'></td>\n";
					echo "<td><font face=Verdana><font size=2>Main Bridge:</font><br>\n";
				//need to create a bridge list from the available video and audio bridges, based on country number
					BridgeDropDown($general_data[bridge]);
					echo "</td>\n";
					echo "<td><font face=Verdana><font size=2>Lead Operator:</font><br>\n";
					//need to do a db ops list as well
					OperatorDropDown($general_data[leadop]);
					echo "</td\n";
			echo "</tr>\n";
			echo "<tr> \n";
				echo "<td colspan=3><font face=Verdana><font size=2>Notes:<br></font><input name=notes size=100 value='$general_data[notes]'></font></td>\n";
				echo "</tr>\n";
				echo "<tr bgcolor='$color1'>\n";
				echo "<td align='center'><font size='2' face=Verdana font color='$color3'>Dialing Details:</font>\n";
				echo "<select name=dial_type>\n";
				if ($general_data[dial_type] == 'Dial In') echo "<option selected>Dial In</option>\n";
				else echo "<option>Dial In</option>";
				if ($general_data[dial_type] == 'Dial Out') echo "<option selected>Dial Out</option>\n";
				echo "<option>Dial Out</option>\n";
				if ($general_data[dial_type] == 'Hybrid') echo "<option selected>Hybrid</option>\n";
				echo "<option>Hybrid</option></div>\n";
				echo "</select>\n";
				echo "</td>\n";
				echo "<td>&nbsp</td>\n";
				echo "<td>&nbsp</td>\n";
				echo "<td align=center><font color='$color3' size='2' face='Verdana'>Conference Type:\n";
				echo "<select name='conference_type'>\n";
				if ($general_data[conference_type] == 'Audio') echo "<option selected>Audio</option>\n";
				else echo "<option>Audio</option>\n";
				if ($general_data[conference_type] == 'Video') echo "<option selected>Video</option>\n";
				else echo "<option>Video</option>\n";
				if ($general_data[conference_type] == 'Room') echo "<option selected>Room</option>\n";
				else echo "<option>Room</option>\n";
				if ($general_data[conference_type] == 'Webcast') echo "<option selected>Webcast</option>\n";
				else echo "<option>Webcast</option>\n";
				echo "</select>\n";
				echo "</font></td>\n";
				echo "</tr>\n";	
				echo "<tr> ";
				echo "<td colspan=4 align='center'><input type='submit' value='Save'></td>";
				echo "</tr>";
			echo "</table>";
			echo "</form>";
		break;
	  endswitch;
}
function EditServices($call_id) {
	global $color1, $color2, $color3, $color4;
	//first, get the conference type
	$conference_type_sql = "SELECT conference_type FROM tbl_conference_reservations WHERE call_id = '$call_id'";
	$conference_type_result = mysql_query($conference_type_sql) or die("Could not get the conference type from tbl_conference_reservations. <br> The sql was: " . $conference_type_sql . " <br> The error was:<br>" . mysql_error());
	$conference_type_data = mysql_fetch_array($conference_type_result);
	$conference_type = "service_" . strtolower($conference_type_data[conference_type]);
	echo "<form action='reservation.php?call_id=$call_id&action=commitservices' method='post' enctype='multipart/form-data'>\n";
	echo "<table border=1 cellpadding=1 cellspacing=0 bordercolor=black rules=rows width='100%'>\n";
	echo "<tr bgcolor='$color1'>\n";
	echo "<td colspan=6><p align=center><font face=Verdana><b>Billable " . ucfirst(substr($conference_type, 8, 5)) . " Services</b></font></p></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td><font face=Verdana size=3><b>Service</b></font></td>\n";
	echo "<td><font face=Verdana size=3><b>Cost</b></font></td>\n";
	echo "<td><font face=Verdana size=3><b>Details</b></font></td>\n";
	echo "</tr>\n";
	//get the billable service list
	$service_list_sql = "SELECT * FROM tbl_service_list where $conference_type = 'Yes' AND billable = 'Yes' AND is_active = 'Yes' ORDER BY service_name";
	$service_list_result = mysql_query($service_list_sql) or die("Could not get the service list from tbl_service_list. <br> The sql was: " . $service_list_sql . " <br> The error was:<br>" . mysql_error());
	while ($service_list_data = mysql_fetch_array($service_list_result))
	{		
		//get the used services
		$service_link_sql = "SELECT * FROM tbl_service_link where call_id = '$call_id' AND service_id = '$service_list_data[service_id]'";
//		echo "<br>$service_link_sql<br>";
		$service_link_result = mysql_query($service_link_sql) or die("Could not get the service list from tbl_service_list. <br> The sql was: " . $service_link_sql . " <br> The error was:<br>" . mysql_error());
		if ($service_link_data = mysql_fetch_array($service_link_result))
		{
			echo "<tr>\n";
			echo "<td><input name='$service_list_data[service_id]' type='checkbox' value='Yes' checked><font face=Verdana size=2><b>$service_list_data[service_name]</b></font></td>\n";
			echo "<td><font face=Verdana size=2><input name='$service_list_data[service_id]_rate' value='$service_link_data[rate]'> per $service_list_data[billed_per]</font></td>\n";
			echo "<td>";
			if(!empty($service_list_data[header1])) echo "<font face=Verdana size=2><b>$service_list_data[header1]: </b><input name='$service_list_data[service_id]_info1' value='$service_link_data[info1]'></font><br>\n";
			if(!empty($service_list_data[header2])) echo "<font face=Verdana size=2><b>$service_list_data[header2]: </b><input name='$service_list_data[service_id]_info2' value='$service_link_data[info2]'></font><br>\n";
			if(!empty($service_list_data[header3])) echo "<font face=Verdana size=2><b>$service_list_data[header3]: </b><input name='$service_list_data[service_id]_info3' value='$service_link_data[info3]'></font><br>\n";
			if(!empty($service_list_data[header4])) echo "<font face=Verdana size=2><b>$service_list_data[header4]: </b><input name='$service_list_data[service_id]_info4' value='$service_link_data[info4]'></font><br>\n";
			if(!empty($service_list_data[header5])) echo "<font face=Verdana size=2><b>$service_list_data[header5]: </b><input name='$service_list_data[service_id]_info5' value='$service_link_data[info5]'></font><br>\n";
			if(!empty($service_list_data[header6])) echo "<font face=Verdana size=2><b>$service_list_data[header6]: </b><input name='$service_list_data[service_id]_info6' value='$service_link_data[info6]'></font><br>\n";
			if(!empty($service_list_data[header7])) echo "<font face=Verdana size=2><b>$service_list_data[header7]: </b><input name='$service_list_data[service_id]_info7' value='$service_link_data[info7]'></font><br>\n";
			if(!empty($service_list_data[header8])) echo "<font face=Verdana size=2><b>$service_list_data[header8]: </b><input name='$service_list_data[service_id]_info8' value='$service_link_data[info8]'></font><br>\n";
			if(!empty($service_list_data[header9])) echo "<font face=Verdana size=2><b>$service_list_data[header9]: </b><input name='$service_list_data[service_id]_info9' value='$service_link_data[info9]'></font><br>\n";
			if(!empty($service_list_data[header10])) echo "<font face=Verdana size=2><b>$service_list_data[header10]: </b><input name='$service_list_data[service_id]_info10' value='$service_link_data[info10]'></font>\n";
			echo "</td>\n";					
			echo "</tr>\n";
		}
		else
		{
			echo "<tr>\n";
			echo "<td><input name='$service_list_data[service_id]' type='checkbox' value='Yes'><font face=Verdana size=2><b>$service_list_data[service_name]</b></font></td>\n";
			echo "<td><font face=Verdana size=2><input name='$service_list_data[service_id]_rate' value='$service_list_data[service_rate]'> per $service_list_data[billed_per]</font></td>\n";	
			echo "<td>";
			if(!empty($service_list_data[header1])) echo "<font face=Verdana size=2><b>$service_list_data[header1]: </b><input name='$service_list_data[service_id]_info1' value='$service_link_data[info1]'></font><br>\n";
			if(!empty($service_list_data[header2])) echo "<font face=Verdana size=2><b>$service_list_data[header2]: </b><input name='$service_list_data[service_id]_info2' value='$service_link_data[info2]'></font><br>\n";
			if(!empty($service_list_data[header3])) echo "<font face=Verdana size=2><b>$service_list_data[header3]: </b><input name='$service_list_data[service_id]_info3' value='$service_link_data[info3]'></font><br>\n";
			if(!empty($service_list_data[header4])) echo "<font face=Verdana size=2><b>$service_list_data[header4]: </b><input name='$service_list_data[service_id]_info4' value='$service_link_data[info4]'></font><br>\n";
			if(!empty($service_list_data[header5])) echo "<font face=Verdana size=2><b>$service_list_data[header5]: </b><input name='$service_list_data[service_id]_info5' value='$service_link_data[info5]'></font><br>\n";
			if(!empty($service_list_data[header6])) echo "<font face=Verdana size=2><b>$service_list_data[header6]: </b><input name='$service_list_data[service_id]_info6' value='$service_link_data[info6]'></font><br>\n";
			if(!empty($service_list_data[header7])) echo "<font face=Verdana size=2><b>$service_list_data[header7]: </b><input name='$service_list_data[service_id]_info7' value='$service_link_data[info7]'></font><br>\n";
			if(!empty($service_list_data[header8])) echo "<font face=Verdana size=2><b>$service_list_data[header8]: </b><input name='$service_list_data[service_id]_info8' value='$service_link_data[info8]'></font><br>\n";
			if(!empty($service_list_data[header9])) echo "<font face=Verdana size=2><b>$service_list_data[header9]: </b><input name='$service_list_data[service_id]_info9' value='$service_link_data[info9]'></font><br>\n";
			if(!empty($service_list_data[header10])) echo "<font face=Verdana size=2><b>$service_list_data[header10]: </b><input name='$service_list_data[service_id]_info10' value='$service_link_data[info10]'></font>\n";
			echo "</td>\n";					
			echo "</tr>\n";
		}
	}
	echo "</table>\n";
	//now deal with non-billable services
	echo "<table border=1 cellpadding=1 cellspacing=0 bordercolor=black rules=rows width='100%'>\n";
	echo "<tr bgcolor='$color1'>\n";
	echo "<td colspan=6><p align=center><font face=Verdana><b>Non-Billable " . ucfirst(substr($conference_type, 8, 5)) . " Services</b></font></p></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td><font face=Verdana size=3><b>Service</b></font></td>\n";
	echo "<td><font face=Verdana size=3><b>Details</b></font></td>\n";
	echo "</tr>\n";
	$service_list_sql = "SELECT * FROM tbl_service_list where $conference_type = 'Yes' AND billable <> 'Yes' ORDER BY service_name";
	$service_list_result = mysql_query($service_list_sql) or die("Could not get the service list from tbl_service_list. <br> The sql was: " . $service_list_sql . " <br> The error was:<br>" . mysql_error());
	while ($service_list_data = mysql_fetch_array($service_list_result))
	{		
		$service_link_sql = "SELECT * FROM tbl_service_link where call_id = '$call_id' AND service_id = '$service_list_data[service_id]'";
		$service_link_result = mysql_query($service_link_sql) or die("Could not get the service list from tbl_service_list. <br> The sql was: " . $service_link_sql . " <br> The error was:<br>" . mysql_error());
		if ($service_link_data = mysql_fetch_array($service_link_result))
		{
			echo "<tr>\n";
			echo "<td><font face=Verdana size=2><b>$service_list_data[service_name]</b></font></td>\n";
			echo "<td>";
			if(!empty($service_list_data[header1])) echo "<font face=Verdana size=2><b>$service_list_data[header1]: </b><input name='$service_list_data[service_id]_info1' value='$service_link_data[info1]'></font><br>\n";
			if(!empty($service_list_data[header2])) echo "<font face=Verdana size=2><b>$service_list_data[header2]: </b><input name='$service_list_data[service_id]_info2' value='$service_link_data[info2]'></font><br>\n";
			if(!empty($service_list_data[header3])) echo "<font face=Verdana size=2><b>$service_list_data[header3]: </b><input name='$service_list_data[service_id]_info3' value='$service_link_data[info3]'></font>\n";
			if(!empty($service_list_data[header4])) echo "<font face=Verdana size=2><b>$service_list_data[header4]: </b><input name='$service_list_data[service_id]_info4' value='$service_link_data[info4]'></font><br>\n";
			if(!empty($service_list_data[header5])) echo "<font face=Verdana size=2><b>$service_list_data[header5]: </b><input name='$service_list_data[service_id]_info5' value='$service_link_data[info5]'></font><br>\n";
			if(!empty($service_list_data[header6])) echo "<font face=Verdana size=2><b>$service_list_data[header6]: </b><input name='$service_list_data[service_id]_info6' value='$service_link_data[info6]'></font><br>\n";
			if(!empty($service_list_data[header7])) echo "<font face=Verdana size=2><b>$service_list_data[header7]: </b><input name='$service_list_data[service_id]_info7' value='$service_link_data[info7]'></font><br>\n";
			if(!empty($service_list_data[header8])) echo "<font face=Verdana size=2><b>$service_list_data[header8]: </b><input name='$service_list_data[service_id]_info8' value='$service_link_data[info8]'></font><br>\n";
			if(!empty($service_list_data[header9])) echo "<font face=Verdana size=2><b>$service_list_data[header9]: </b><input name='$service_list_data[service_id]_info9' value='$service_link_data[info9]'></font><br>\n";
			if(!empty($service_list_data[header10])) echo "<font face=Verdana size=2><b>$service_list_data[header10]: </b><input name='$service_list_data[service_id]_info10' value='$service_link_data[info10]'></font>\n";
			echo "<input type='hidden' name='$service_list_data[service_id]' value='Yes'>";
			echo "</td>\n";					
			echo "</tr>\n";
		}
		else
		{
			echo "<tr>\n";
			echo "<td><font face=Verdana size=2><b>$service_list_data[service_name]</b></font></td>\n";
			echo "<td>";
			if(!empty($service_list_data[header1])) echo "<font face=Verdana size=2><b>$service_list_data[header1]: </b><input name='$service_list_data[service_id]_info1' value='$service_link_data[info1]'></font><br>\n";
			if(!empty($service_list_data[header2])) echo "<font face=Verdana size=2><b>$service_list_data[header2]: </b><input name='$service_list_data[service_id]_info2' value='$service_link_data[info2]'></font><br>\n";
			if(!empty($service_list_data[header3])) echo "<font face=Verdana size=2><b>$service_list_data[header3]: </b><input name='$service_list_data[service_id]_info3' value='$service_link_data[info3]'></font><br>\n";
			if(!empty($service_list_data[header4])) echo "<font face=Verdana size=2><b>$service_list_data[header4]: </b><input name='$service_list_data[service_id]_info4' value='$service_link_data[info4]'></font><br>\n";
			if(!empty($service_list_data[header5])) echo "<font face=Verdana size=2><b>$service_list_data[header5]: </b><input name='$service_list_data[service_id]_info5' value='$service_link_data[info5]'></font><br>\n";
			if(!empty($service_list_data[header6])) echo "<font face=Verdana size=2><b>$service_list_data[header6]: </b><input name='$service_list_data[service_id]_info6' value='$service_link_data[info6]'></font><br>\n";
			if(!empty($service_list_data[header7])) echo "<font face=Verdana size=2><b>$service_list_data[header7]: </b><input name='$service_list_data[service_id]_info7' value='$service_link_data[info7]'></font><br>\n";
			if(!empty($service_list_data[header8])) echo "<font face=Verdana size=2><b>$service_list_data[header8]: </b><input name='$service_list_data[service_id]_info8' value='$service_link_data[info8]'></font><br>\n";
			if(!empty($service_list_data[header9])) echo "<font face=Verdana size=2><b>$service_list_data[header9]: </b><input name='$service_list_data[service_id]_info9' value='$service_link_data[info9]'></font><br>\n";
			if(!empty($service_list_data[header10])) echo "<font face=Verdana size=2><b>$service_list_data[header10]: </b><input name='$service_list_data[service_id]_info10' value='$service_link_data[info10]'></font>\n";
			echo "<input type='hidden' name='$service_list_data[service_id]' value='Yes'>";
			echo "</td>\n";					
			echo "</tr>\n";
		}
	}
	echo "<tr>";
	echo "<td colspan='2' align='center'><input type='submit' value='Submit'></td>\n";
	echo "</table>\n";
	echo "</form>\n";
}
function BridgeDropDown($selected_bridge) {
	echo "<select name=bridge>\n";
	//get the list from the db
	$bridge_sql="SELECT DISTINCT bridge_name FROM tbl_bridges ORDER BY bridge_name";
	$bridge_result = mysql_query($bridge_sql) or die("Could not read from tbl_operators. <br> The sql was: " . $op_sql . " <br> The error was:<br>" . mysql_error());
	while ($bridge_data = mysql_fetch_array($bridge_result)) {
		if ($selected_bridge == $bridge_data[bridge_name])echo "<option selected>$bridge_data[bridge_name]</option>\n";
		else echo "<option>$bridge_data[bridge_name]</option>\n";
	}
	echo "</select></td>\n";
}
function OperatorDropDown($selected_operator) {
	echo "<select name=leadop>\n";
	//get the list from the db
	$op_sql="SELECT * FROM tbl_operators ORDER BY name";
	$op_result = mysql_query($op_sql) or die("Could not read from tbl_operators. <br> The sql was: " . $op_sql . " <br> The error was:<br>" . mysql_error());
	while ($op_data = mysql_fetch_array($op_result)) {
		if ($selected_operator == $op_data[name])echo "<option selected>$op_data[name]</option>\n";
		else echo "<option>$op_data[name]</option>\n";
	}
	echo "</select></td>\n";
}
function CommitGeneralDetails($call_id, $commit_type, $edited_details) {
	$edited_details = sanitate($edited_details);
 	//first, collapse the date time and duration
	// Implode Call Date & Time into one variable for insertion into DB.  Check for single-digit months and days
	$call_datetime = $edited_details[callDate];
	$call_datetime .= " $edited_details[call_datetime_hh]";
	$call_datetime .= ":$edited_details[call_datetime_mm]";
	$call_datetime .= ":00";
	//echo $call_datetime;
	//Implode Duration.  check for single digit hours or minutes
	if (strlen($edited_details[call_duration_hh]) == 1) $edited_details[call_duration_hh] = "0".$edited_details[call_duration_hh];
	if (strlen($edited_details[call_duration_mm]) == 1) $edited_details[call_duration_mm] = "0".$edited_details[call_duration_mm];
	$call_duration = "";
	$call_duration .= $edited_details[call_duration_hh];
	$call_duration .= $edited_details[call_duration_mm];
	$call_duration .= "00";
	//strip any non-numeric chars from telephone number.
	$telno = $edited_details[scheduler_tel];
	$telno_clean = preg_replace("/([^0-9])/","",$telno);
	//echo "<br> $telno_clean <br>";
	//last_edited_by, res_date and taken_by must be set automatically, and PC name
	$last_edited_by = $_SESSION[email];
	$taken_by = $_SESSION[email];
	$res_date = date(Ymd);
	//if no account number is entered, set to 'TBC'
	if (empty($edited_details[account_number])) $edited_details[account_number] = "TBC";
	if ($commit_type ==	'edit')
	{
		//now write to db
		$general_details_sql = "UPDATE tbl_conference_reservations  SET call_datetime='$call_datetime' , call_duration = '$call_duration' , chair_name = '$edited_details[chair_name]' , notes = '$edited_details[notes]' , chair_number = '$edited_details[chair_number]' , client_ref = '$edited_details[client_ref]' , company = '$edited_details[company]' , conf_title = '$edited_details[conf_title]' , num_do_lines = '$edited_details[num_do_lines]' , num_di_lines = '$edited_details[num_di_lines]' , scheduler_tel = '$telno_clean' , scheduler = '$edited_details[scheduler]', conference_type = '$edited_details[conference_type]' , conf_title = '$edited_details[conf_title]' , bridge = '$edited_details[bridge]', leadop = '$edited_details[leadop]', last_edited_by = '$last_edited_by' , account_number = '$edited_details[account_number]' WHERE call_id = '$call_id'"; 
		$general_details_result = mysql_query($general_details_sql) or die("Could not update the general details to tbl_conference_reservations. <br> The sql was: " . $general_details_sql . " <br> The error was:<br>" . mysql_error());;
		RETURN $call_id;
	}
	if ($commit_type == 'newcall')
	{
		$general_sql = "INSERT INTO tbl_conference_reservations VALUES ('', '$call_datetime', '$call_duration', '$edited_details[chair_name]', '$edited_details[notes]', '$edited_details[chair_number]', '$edited_details[client_ref]', '$edited_details[company]', '$edited_details[conf_title]', '$edited_details[dial_type]', '$edited_details[num_di_lines]', '$edited_details[num_do_lines]', '$res_date', '$telno_clean', '$edited_details[scheduler]', '$taken_by', '$edited_details[conference_type]', '$edited_details[bridge]', '$edited_details[leadop]', 'No', '$edited_details[account_number]', '', '$taken_by')";
		$general_result = mysql_query($general_sql) or die ("Unable to write the general call data to tbl_conference_reservations.  The sql was: <br>" . $general_sql . " <br> the error was:" . mysql_error());
		$call_id = mysql_insert_id();
		RETURN $call_id;
		//echo $new_call_id;
	}
}
function CommitServices($call_id, $service_details) {
 	//get the call type
 	$service_details = sanitate($service_details);
	$conference_type_sql = "SELECT conference_type FROM tbl_conference_reservations WHERE call_id = '$call_id'";
 	$conference_type_result = mysql_query($conference_type_sql)or die ("Unable to get the call type data from tbl_conference_reservations.  The sql was: <br>" . $call_type_sql . " <br> the error was:" . mysql_error());
 	$conference_type_data = mysql_fetch_array($conference_type_result);
 	$call_type = strtolower($conference_type_data[conference_type]);
 	$call_type = "service_" . $call_type;
	//get a list of service ids for this call type
	$service_id_list_sql = "SELECT service_id FROM tbl_service_list WHERE $call_type = 'Yes'";
	$service_id_list_result = mysql_query($service_id_list_sql)or die ("Unable to get the service id list from tbl_service_list.  The sql was: <br>" . $service_id_list_sql . " <br> the error was:" . mysql_error());
	while ($service_id_list_data = mysql_fetch_array($service_id_list_result))
	{
	 	$service_id = $service_id_list_data[service_id];
		if ($service_details[$service_id]=='Yes')
		{
			$info1_name = $service_id . "_info1";
			$info1 = $service_details[$info1_name];
			
			$info2_name = $service_id . "_info2";
			$info2 = $service_details[$info2_name];
			
			$info3_name = $service_id . "_info3";
			$info3 = $service_details[$info3_name];
			
			$info4_name = $service_id . "_info4";
			$info4 = $service_details[$info4_name];
			
			$info5_name = $service_id . "_info5";
			$info5 = $service_details[$info5_name];
			
			$info6_name = $service_id . "_info6";
			$info6 = $service_details[$info6_name];
			
			$info7_name = $service_id . "_info7";
			$info7 = $service_details[$info7_name];
			
			$info8_name = $service_id . "_info8";
			$info8 = $service_details[$info8_name];
			
			$info9_name = $service_id . "_info9";
			$info9 = $service_details[$info9_name];
			
			$info10_name = $service_id . "_info10";
			$info10 = $service_details[$info10_name];
			
						
			$rate_name = $service_id . "_rate";
			$rate = $service_details[$rate_name];
			$commit_service_sql = "REPLACE INTO tbl_service_link VALUES ('$call_id', '$service_id', '$info1', '$info2', '$info3', '$info4', '$info5', '$info6', '$info7', '$info8', '$info9', '$info10', '$rate')";
			//echo "<br>$commit_service_sql<br>";
			$commit_service_result = mysql_query($commit_service_sql)or die ("Unable to write the service list to tbl_service_link.  The sql was: <br>" . $commit_service_sql . " <br> the error was:" . mysql_error());
		}
		else
		{
			$remove_service_sql = "DELETE FROM tbl_service_link WHERE call_id = '$call_id' AND service_id = '$service_id' LIMIT 1";
			$remove_service_result = mysql_query($remove_service_sql)or die ("Unable to remove the service from tbl_service_link.  The sql was: <br>" . $remove_service_sql . " <br> the error was:" . mysql_error());
		}
	}
}
function CommitDialOuts($call_id, $dial_out_parties, $party_count) {
	$counter=1;
	while ($counter <= $party_count)
	{
		$dial_out_parties = sanitate($dial_out_parties);
		$user_request = "party_name_" . $counter;
		$dial_out_name = $dial_out_parties[$user_request];
		$user_request = "party_number_" . $counter;
		$dial_out_number = $dial_out_parties[$user_request];
		$user_request = "party_notes_" . $counter;
		$dial_out_notes = $dial_out_parties[$user_request];
		$user_request = "party_id_" . $counter;
		$uid = $dial_out_parties[$user_request];
		$dial_out_party_sql = "REPLACE INTO tbl_audio_dial_parties (uid, call_id, party_name, party_number, party_notes) VALUES ('$uid', '$call_id', '$dial_out_name', '$dial_out_number', '$dial_out_notes')";
		$dial_out_party_result = mysql_query($dial_out_party_sql)or die ("Unable to write the parties to tbl_audio_dial_parties.  The sql was: <br>" . $dial_out_party_sql . " <br> the error was:" . mysql_error());
		$counter++;
	}	
}
function EditAudioDialOut($call_id) {
	global $color1, $color2, $color3, $color4;
	$party_count_sql = "SELECT COUNT(uid) AS party_count FROM tbl_audio_dial_parties WHERE call_id = '$call_id'";
	$party_count_result = mysql_query($party_count_sql) or die("Query failed : " . mysql_error());
	$party_count_array = mysql_fetch_array($party_count_result);
	echo "<form action='reservation.php?call_id=$call_id&action=commitdialouts&party_count=$party_count_array[party_count]' method='post' enctype='multipart/form-data'>\n";
	$dial_out_sql = "SELECT uid, party_name, party_number, party_notes FROM tbl_audio_dial_parties WHERE call_id = '$call_id'";
	$dial_out_result = mysql_query($dial_out_sql) or die("Query failed : " . mysql_error());
	echo "<table border=1 cellpadding=1 cellspacing=0 bordercolor=black rules=rows width='100%'>\n";
	echo "<tr bgcolor='$color1'>\n";
	echo "<td colspan=4><p align=center><font face=Verdana><b>" . ucfirst(substr($conference_type, 8, 5)) . " Dial Out Parties</b></font></p></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td><font face=Verdana size=3><b>Name</b></font></td>\n";
	echo "<td><font face=Verdana size=3><b>Number</b></font></td>\n";
	echo "<td><font face=Verdana size=3><b>Notes</b></font></td>\n";
	echo "<td>&nbsp</td>\n";
	echo "</tr>\n";
	$counter=1;
	while ($dial_out_data = mysql_fetch_array($dial_out_result)) 
	{
		echo "<tr>\n";
		$party_id = $dial_out_data[uid];
		$party_name = $dial_out_data[party_name];
		$party_number = $dial_out_data[party_number];
		$party_notes = $dial_out_data[party_notes];
		echo "<input type = 'hidden' name = 'party_id_$counter' value='$party_id'>\n";
		echo "<td><font face=Verdana size=2><input name='party_name_$counter' value = '$party_name'</font></td>\n";
		echo "<td><font face=Verdana size=2><input name='party_number_$counter' value = '$party_number'</FONT></td>\n";
		echo "<td><font face=Verdana size=2><input name='party_notes_$counter' value = '$party_notes'</font></td>\n";
		echo "<td><font face=Verdana size=2><a href ='reservation.php?action=confirmdeleteaudioparty&call_id=$call_id&party_id=$party_id'>Delete party</a></font></td>\n";
		echo "</tr>\n";
		$counter++;
    }	
   echo "<tr>\n";
	echo "<td colspan=6 align=center><font face=Verdana size=2><b><a href='reservation.php?call_id=$call_id&action=addaudioparties''>Add audio parties</a></b></font><br><input type='submit' value='Submit'></td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</form>\n";
}
function DisplayVideoSites($call_id) {
	$counter=1;
	echo "<table width=100% border=0 cellspacing=0>\n";
	echo "<tr bgcolor=#99CCCC><td colspan='5' align=center><font face=Verdana><b>Video Sites</b></font></td></tr>\n";
	echo "<tr>\n";
	echo "<td><font face=Verdana size=2><b>Site Number</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Site Name</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Trigger Number</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Bandwidth</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Dial Type</b></font></td>\n";
	echo "</tr>\n";
	//get the list of sites in the call
	$site_sql = "SELECT * FROM tbl_video_sites_link WHERE conf_id=$call_id";
	$site_result = mysql_query($site_sql) or die("Could not read from tbl_video_sites_link. <br> The sql was: " . $site_sql . " <br> The error was:<br>" . mysql_error());
	$row_count = 1;
	$color2 = "#FFFFFF";
	$color1 = "#DDDDDD";
	while ($site_data = mysql_fetch_array($site_result)){
		//get the site details for the current site from the stored site table
		$site_name_sql = "SELECT company_name, site_name, site_trigger FROM tbl_stored_video_sites WHERE site_id = $site_data[site_id]";
		$site_name_result = mysql_query($site_name_sql) or die("Could not read from tbl_stored_video_sites. <br> The sql was: " . $site_name_sql . " <br> The error was:<br>" . mysql_error());
		while ($site_name_data = mysql_fetch_array($site_name_result)){
			$row_color = ($row_count % 2) ? $color1 : $color2;
			echo "<tr bgcolor=$row_color>\n";
			echo "<td><font face=Verdana size=2><b>Site $counter</b></font></td>\n";
			echo "<td><font face=Verdana size=2><a href=admin/index.php?action=viewvideosite&site_id=$site_data[site_id] target=new>$site_name_data[company_name] - $site_name_data[site_name]</a></font></td>\n";
			echo "<td><font face=Verdana size=2>$site_name_data[site_trigger]</font></td>\n";
			echo "<td><font face=Verdana size=2>$site_data[bandwidth]</font></td>\n";
			echo "<td><font face=Verdana size=2>$site_data[dial_type]</font></td>\n";
			echo "</tr>\n";
			$counter++;
			$row_count++;
		}
	}
	$counter--;
	echo "<td colspan=2 align=center><font face=Verdana size=2><b><a href=reservation.php?call_id=$call_id&action=editvideosites&number_sites=$counter>Edit Video Sites</a></b></font></td>\n";
	echo "<td>&nbsp</td>\n";
	echo "<td colspan=2 align=center><font face=Verdana size=2><b><a href=reservation.php?call_id=$call_id&action=addvideosites>Add Video Sites</a></b></font></td>\n";
	echo "</tr>\n";
}
function DisplayClientVideoSites($call_id) {
	$counter=1;
	echo "<table width=100% border=1 cellpadding=1 cellspacing=0 BORDERCOLOR=black rules=rows>\n";
	echo "<tr bgcolor=#99CCCC><td colspan='5' align=center><font face=Verdana><b>Video Sites</b></font></td></tr>\n";
	echo "<tr>\n";
	echo "<td><font face=Verdana size=2><b>Site Number</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Site Name</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Trigger Number</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Bandwidth</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Dial Type</b></font></td>\n";
	echo "</tr>\n";
	//get the list of sites in the call
	$site_sql = "SELECT * FROM tbl_video_sites_link WHERE conf_id=$call_id";
	$site_result = mysql_query($site_sql) or die("Could not read from tbl_video_sites_link. <br> The sql was: " . $site_sql . " <br> The error was:<br>" . mysql_error());
	while ($site_data = mysql_fetch_array($site_result)){
		//get the site details for the current site from the stored site table
		$site_name_sql = "SELECT company_name, site_name, site_trigger FROM tbl_stored_video_sites WHERE site_id = $site_data[site_id]";
		$site_name_result = mysql_query($site_name_sql) or die("Could not read from tbl_stored_video_sites. <br> The sql was: " . $site_name_sql . " <br> The error was:<br>" . mysql_error());
		while ($site_name_data = mysql_fetch_array($site_name_result)){
			echo "<tr>\n";
			echo "<td><font face=Verdana size=2><b>Site $counter</b></font></td>\n";
			echo "<td><font face=Verdana size=2>$site_name_data[company_name] - $site_name_data[site_name]</font></td>\n";
			echo "<td><font face=Verdana size=2>$site_name_data[site_trigger]</font></td>\n";
			echo "<td><font face=Verdana size=2>$site_data[bandwidth]</font></td>\n";
			echo "<td><font face=Verdana size=2>$site_data[dial_type]</font></td>\n";
			echo "</tr>\n";
			$counter++;
		}
	}
}
function video_sites_dropdown($site_id, $site_number) {
	echo "<select name=site_number_$site_number>\n";
	//get the list from the db
	$video_site_sql="SELECT company_name, site_name, site_id FROM tbl_stored_video_sites ORDER BY company_name, site_name";
	$video_site_result = mysql_query($video_site_sql) or die("Could not read from tbl_stored_video_sites. <br> The sql was: " . $video_site_sql . " <br> The error was:<br>" . mysql_error());
	while ($video_site_data = mysql_fetch_array($video_site_result)) {
		if ($site_id == $video_site_data[site_id])echo "<option value=$video_site_data[site_id] selected>$video_site_data[company_name] - $video_site_data[site_name]</option>\n";
		else echo "<option value=$video_site_data[site_id]>$video_site_data[company_name] - $video_site_data[site_name]</option>\n";
	}
	echo "</select>\n";
}
function EditVideoSites($call_id, $number_sites) {
	echo "<form action='reservation.php?action=commitvideo&call_id=$call_id' method='post' enctype='multipart/form-data'>\n";
	//set up the table
	echo "<table width=100% border=1 cellpadding=1 cellspacing=0 BORDERCOLOR=black rules=rows>\n";
	echo "<tr bgcolor=#99CCCC><td colspan='5' align=center><font face=Verdana><b>Video Sites</b></font></td></tr>\n";
	echo "<tr>\n";
	echo "<td><font face=Verdana size=2><b>Site Number</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Site Name</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Bandwidth</b></font></td>\n";
	echo "<td><font face=Verdana size=2><b>Dial Type</b></font></td>\n";
	echo "</tr>\n";
	//first get sites in the conference already
	$counter = 1;
	echo "<INPUT TYPE=HIDDEN NAME=number_sites VALUE=$number_sites>\n";
	$site_sql = "SELECT * FROM tbl_video_sites_link WHERE conf_id=$call_id";
	$site_result = mysql_query($site_sql) or die("Could not read from tbl_video_sites_link. <br> The sql was: " . $site_sql . " <br> The error was:<br>" . mysql_error());
	while ($site_data = mysql_fetch_array($site_result)){
		//now edit the sites
		echo "<tr><td><font face=Verdana size=2><b>Site $counter </b></td>";
		echo "<td>\n";
		echo "<input type=hidden name=site_uid_$counter value=$site_data[uid]>\n";
		video_sites_dropdown($site_data[site_id], $counter);
		echo "</td>\n";
		//bandwidth
		echo "<td>\n";
		echo "<select name=site_bandwidth_$counter>\n";
		if ($site_data[bandwidth] == "128K") echo "<option value = 128K selected>128K</option>\n"; else echo "<option value = 128K>128K</option>\n";
		if ($site_data[bandwidth] == "256K") echo "<option value = 256K selected>256K</option>\n"; else echo "<option value = 256K>256K</option>\n";
		if ($site_data[bandwidth] == "384K") echo "<option value = 384K selected>384K</option>\n"; else echo "<option value = 128K>384K</option>\n";
		if ($site_data[bandwidth] == "512K") echo "<option value = 512K selected>512K</option>\n"; else echo "<option value = 512K>512K</option>\n";
		if ($site_data[bandwidth] == "768K") echo "<option value = 768K selected>768K</option>\n"; else echo "<option value = 768K>768K</option>\n";
		echo "</select>\n";
		echo "</td>\n";
		//dial_type
		echo "<td>\n";
		echo "<select name=site_dial_type_$counter>\n";
		if ($site_data[dial_type] == "Dial In") echo "<option value = 'Dial In' selected>Dial In</option>\n"; else echo "<option value = 'Dial In'>Dial In</option>\n";
		if ($site_data[dial_type] == "Dial Out") echo "<option value = 'Dial Out' selected>Dial Out</option>\n"; else echo "<option value = 'Dial Out'>Dial Out</option>\n";
		echo "</select>\n";		
		echo "</td>\n";
		echo "</tr>\n";
		$counter++;
	}
	//submit form
	echo "<tr><td align=center colspan=4><input type='submit' value='Submit'></td></tr>";	
	echo "</form>\n";
}
function CommitVideoSites($call_id, $number_sites) {
	$site_data = $_POST;
	$counter = 1;
	while ($counter <= $number_sites)
	{
		$_REQUEST = sanitate($_REQUEST);
		$user_request = "site_uid_" . $counter;
		$site_uid = $_REQUEST[$user_request];
		$user_request = "site_number_" . $counter;
		$site_id = $_REQUEST[$user_request];
		$user_request = "site_bandwidth_" . $counter;
		$bandwidth = $_REQUEST[$user_request];
		$user_request = "site_dial_type_" . $counter;
		$dial_type = $_REQUEST[$user_request];
		$sql = "REPLACE INTO tbl_video_sites_link (uid, site_id, conf_id, bandwidth, dial_type) VALUES ('$site_uid', '$site_id', '$call_id', '$bandwidth', '$dial_type')";
		$result = mysql_query($sql);
		if (!$result) 
		{
			die('Invalid query: ' . mysql_error());
		}
		$counter++;
	}
}
function shorten_string($string, $wordsreturned) {
   $retval = $string;    //    Just in case of a problem
   $array = explode(" ", $string);
   if (count($array)<=$wordsreturned) $retval = $string;
	else {
		array_splice($array, $wordsreturned);
		$retval = implode(" ", $array);
	}
      return $retval;
}
?>