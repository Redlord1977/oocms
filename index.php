<?php
require_once "cms_include.php";
	echo "<HTML>\n";
	echo "<HEAD>\n";
		echo "<TITLE>Call Management System: Chorus Call Johannesburg</TITLE>\n";
	echo "</HEAD>\n";

   echo "<frameset rows='40,*' frameborder=no framespacing=0 border=0> \n";
     echo "<frame src='title.php' title=Titlebar scrolling=no> \n";
	echo "<frameset cols='325,*' frameborder=no framespacing=0 border=0> \n";
		 echo "<frame src='menuframe.php' title='Main Menu' name=menuframe> \n";
		 echo "<frame src='content.php' title='Page Content' name=mainframe> \n";
	echo "</frameset>\n";
?>