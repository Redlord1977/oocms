<HTML>
<HEAD>
<TITLE>Chorus Call South Africa - Call Management System</TITLE>
<meta http-equiv="refresh" content="60">
</HEAD>

<BODY>
<?PHP
	require_once "cms_include.php";
	CMSConnect();
    $today = date("Ymd");
    $today_display = date ("F dS, Y");
    $tomorrow  = mktime(0, 0, 0, date("m")  , date("d")+1, date("Y"));
    $tomorrow_display = date("F dS, Y", $tomorrow);
    $tomorrow = date("Ymd", $tomorrow);
    $yesterday  = mktime(0, 0, 0, date("m")  , date("d")-1, date("Y"));
    $yesterday_display = date("F dS, Y", $yesterday);
    $yesterday = date("Ymd", $yesterday);
    $day_after_tomorrow  = mktime(0, 0, 0, date("m")  , date("d")+2, date("Y"));
    $day_after_tomorrow_display = date("F dS, Y", $day_after_tomorrow);
    $day_after_tomorrow = date("Ymd", $day_after_tomorrow);
    $two_days_after_tomorrow  = mktime(0, 0, 0, date("m")  , date("d")+3, date("Y"));
    $two_days_after_tomorrow_display = date("F dS, Y", $two_days_after_tomorrow);
    $two_days_after_tomorrow = date("Ymd", $two_days_after_tomorrow);

	// First, Display Yesterdays calls for printing billings forms, or editing
	echo "<table width='100%' border='1' cellspacing='0' cellpadding='1' bordercolor='white' rules=rows>\n";
	echo "<tr><td colspan=11 bgcolor='#99cccc'><Font face ='Verdana' size=3><b><p align=center>Calls yesterday - $yesterday_display </b></td></tr>\n";
    DisplayCallsOnDate($yesterday, $yesterday);
    echo "</table>\n";

	//Now, show todays calls for printing resforms and editing
    echo "<table width='100%' border='1' cellspacing='0' cellpadding='1' bordercolor='white' rules=rows>\n";
    echo "<tr><td colspan=11 bgcolor='#FF0000'><Font face ='Verdana' size=3><b><p align=center>Calls today - $today_display </b></td></tr>\n";
    DisplayCallsOnDate($today, $today);
    echo "</table>\n";

	//Now, show tomorrows calls for printing resforms and making confirmation calls
    echo "<table width='100%' border='1' cellspacing='0' cellpadding='1' bordercolor='white' rules=rows>\n";
    echo "<tr><td colspan=11 bgcolor='#99cccc'><Font face ='Verdana' size=3><b><p align=center>Calls tomorrow - $tomorrow_display </b></td></tr>\n";
    DisplayCallsOnDate($tomorrow, $tomorrow);
    echo "</table>\n";

	//Now, show day after tomorrows calls for printing resforms and making confirmation calls
    echo "<table width='100%' border='1' cellspacing='0' cellpadding='1' bordercolor='white' rules=rows>\n";
    echo "<tr><td colspan=11 bgcolor='#99cccc'><Font face ='Verdana' size=3><b><p align=center>Calls on $day_after_tomorrow_display </b></td></tr>\n";
    DisplayCallsOnDate($day_after_tomorrow, $day_after_tomorrow);
    echo "</table>\n";

	//Now, show calls two days away for printing resforms and making confirmation calls
    echo "<table width='100%' border='1' cellspacing='0' cellpadding='1' bordercolor='white' rules=rows>\n";
    echo "<tr><td colspan=11 bgcolor='#99cccc'><Font face ='Verdana' size=3><b><p align=center>Calls on $two_days_after_tomorrow_display </b></td></tr>\n";
    DisplayCallsOnDate($two_days_after_tomorrow, $two_days_after_tomorrow);
    echo "</table>\n";
?>