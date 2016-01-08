<!DOCTYPE html>
<html>
    <head>
      <title>Conference Reservations</title>
      <link type='text/css' rel='stylesheet' href='style.css'/>
    </head>
	<body>
<?php
require_once "cms_include.php";

class Conference {
	//create all variables for a conference call
	public $callID;
	public $callDatetime;
	public $callDuration;
	public $chairName;
	public $notes;
	public $chairNumber;
	public $clientRef;
	public $company;
	public $confTitle;
	public $dialType;
	public $numDILines;
	public $numDOLines;
	public $resDate;
	public $schedulerTel;
	public $scheduler;
	public $takenBy;
	public $conferenceType;
	public $bridge;
	public $leadop;
	public $isCancelled;
	public $accountNumber;
	public $lastEditedBy;
	public $lastEdited;
	
	public function __construct($callDatetime, $callDuration, $chairName, $chairNumber, $clientRef, $company, $confTitle, $dialType, $numDILines, $numDOLines, $schedulerTel, $scheduler, $conferenceType, $bridge, $leadop, $isCancelled, $accountNumber, $resDate) {
		$this->callID = rand(1,999);
		$this->callDatetime = $callDatetime;
		$this->callDuration = $callDuration;
		$this->chairName = $chairName;
		$this->notes = $notes;
		$this->chairNumber = $chairNumber;
		$this->clientRef = $clientRef;
		$this->company = $company;
		$this->confTitle = $confTitle;
		$this->dialType = $dialType;
		$this->numDILines = $numDILines;
		$this->numDOLines = $numDOLines;
		$this->schedulerTel = $schedulerTel;
		$this->scheduler = $scheduler;
		$this->conferenceType = $conferenceType;
		$this->bridge = $bridge;
		$this->leadop = $leadop;
		$this->isCancelled = $isCancelled;
		$this->accountNumber = $accountNumber;
		$this->resDate = $resDate;
		$this->takenBy = gethostname();
		$this->lastEditedBy = gethostname();
		$this->lastEdited = $lastEdited;
	}
	
	public function writeToDB() {
		CMSConnect();
		$conferenceWriteSQL = "INSERT INTO tbl_conference_reservations VALUES (
			'$this->callID',
			'$this->callDatetime',
			'$this->callDuration',
			'$this->chairName',
			'$this->notes',
			'$this->chairNumber',
			'$this->clientRef',
			'$this->company',
			'$this->confTitle',
			'$this->dialType',
			'$this->numDILines',
			'$this->numDOLines',
			'$this->resDate',
			'$this->schedulerTel',
			'$this->scheduler',
			'$this->takenBy',
			'$this->conferenceType',
			'$this->bridge',
			'$this->leadop',
			'$this->isCancelled',
			'$this->accountNumber',
			'$this->lastEdited',
			'$this->lastEditedBy')
			";
		//echo $conferenceWriteSQL;
		$conferenceWriteResult = mysql_query($conferenceWriteSQL) or die("Query failed : " . mysql_error());
		
	}
	
	public function displayInternal($confID) {
		$confReadSQL = "SELECT * FROM tbl_conference_reservations WHERE call_id = $confID";
		$confReadResult = mysql_query($confReadSQL) or die("Query failed : " . mysql_error());
		while ($confReadArray = mysql_fetch_array($confReadResult)) {
			
		}
	}
	
}
function showBlankConference() {
		echo "<form action='reservation.php?action=writeNew' method='post'>";
			echo "<table width='100%' border=1 cellpadding=0 cellspacing=1>";
				echo "<tr>";
					echo "<td align='center' colspan='4'><h1>General Call Details</h1></td>";
				echo "</tr>";
				echo "<tr>";
					echo "<td  width='25%'><p>Company:<input name='company' type='text'></p></td>";
					echo "<td  width='25%'><p>Conference Title:<input name='confTitle' type='text'</p></td>";
					echo "<td  width='25%'><p>Account Number:<input name='accountNumber' type='text'></p></td>";
					echo "<td  width='25%'><p>Client Reference:<input name='clientRef' type='text'</p></td>";
				echo "</tr>";
				echo "<tr>";
					echo "<td  width='25%'>";
						echo "<p>Date of call: <input name='callDate' maxlength='10'></p>";
						echo "</td>";
						echo "<td width='25%'><p>Time of call: <input name='callTime' maxlength='5'>";
						echo "<br>24-hour time</p>";
					echo "</td>";
					echo "<td  width='25%'><p>Expected Duration of call:<input name='callDuration' maxlength='5'></p></td>";
				echo "</tr>";
				echo "<tr>";
					echo "<td  width='25%'><p>Scheduler:<br></font><font face=Verdana><input name='scheduler' type='text'></p></td>";
					echo "<td  width='25%'><p>Telephone Number:<br><input name='schedulerTel' type='text'></p></td>";
					echo "<td  width='25%'><p>Chairperson Name:<br><input name='chairName' type='text'></p></td>";
					echo "<td  width='25%'><p>Telephone Number:<br><input name='chairNumber' type='text'></p></td>";
				echo "</tr>";
				echo "<tr>";
					echo "<td><p>Number of Dial-In lines:<br><input name='numDILines'></p></td>";
					echo "<td><p>Number of Dial-Out lines:</font><br><input name='numDOLines'></p></td>";
					echo "<td><p>Main Bridge:<input name='bridge'></p></td>";
					echo "<td><p>Lead Operator:<input name='operator'></p></td>";
				echo "</tr>";
				echo "<tr>";
					echo "<td colspan=4><p>Notes: <input name=notes size=100></p></td>";
				echo "</tr>";
				echo "<tr>";
					echo "<td colspan='2' width='50%'><p>Dialling Details:</p><br>";
						echo "<select name='dialType'>";
							echo "<option>Dial In</option>";
							echo "<option>Dial Out</option>";
							echo "<option>Hybrid</option>";
						echo "</select>";
					echo "</td>";
					echo "<td colspan=2 width=50%><p>Conference Type:<br>";
						echo "<select name='conferenceType'>";
							echo "<option>Audio</option>";
							echo "<option>Video</option>";
							echo "<option>Room</option>";
							echo "<option>Webcast</option>";
						echo "</select></td>";
				echo "</tr>";
				echo "<tr>";
					echo "<td align='center' colspan='4'> <input type='Submit' value='Submit'></td> ";
				echo "</tr>";
			echo "</table>";
		echo "</form>";		
	}

	
	$action = $_GET['action'];
	//echo $action;
	switch ($action) {
	case "new" :
		showBlankConference();
	break;
	case "writeNew" :
		//get the data from the new conference form
		$confDataArray = $_POST;
		$callDateTime = $confDataArray['callDate'] . " " . $confDataArray['callTime'];
		$resDateArray = getdate();
		$resDate = $resDateArray[year] . "-" . $resDateArray[mon] . "-" . $resDateArray[mday] . " " . $resDateArray[hours] . ":" . $resDateArray[minutes] . ":" . $resDateArray[seconds];
		//print_r($confDataArray);
		$conferenceData = new Conference($callDateTime, $confDataArray[callDuration], $confDataArray[chairName], $confDataArray[chairNumber], $confDataArray[clientRef], $confDataArray[company], $confDataArray[confTitle], $confDataArray[dialType], $confDataArray[numDILines], $confDataArray[numDOLines], $confDataArray[schedulerTel], $confDataArray[scheduler], $confDataArray[conferenceType], $confDataArray[bridge], $confDataArray[leadop], 'No', $confDataArray[accountNumber], $resDate);
		$conferenceData->writeToDB();
		
	break;
	default :
		echo "No action defined";
	break;

}
?>