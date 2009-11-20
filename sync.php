<?php

/*
 *  Copyright (c) Nicholas Mossor Rathmann <nicholas.rathmann@gmail.com> 2009. All Rights Reserved.
 *      
 *
 *  This file is part of OBBLM.
 *
 *  OBBLM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  OBBLM is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *   
 */

define('NO_STARTUP', true); # header.php hint.
require('header.php'); // Includes and constants.
require('lib/class_sqlcore.php');
?>
<html>
<head>
</head>
<body>
<br>
<big>
    <center>
        <b>OBBLM MySQL data synchronisation</b>
    </center>
</big>
<br>
<small>
<?php
if (isset($_POST['proc'])) {
    $conn = mysql_up(true);
    echo (SQLCore::syncGameData()) 
        ? "<font color='green'>OK &mdash; Synchronize game data with database</font><br>\n" 
        : "<font color='red'>FAILED &mdash; Error whilst synchronizing game data with database</font><br>\n";
    
    echo (SQLCore::installTableIndexes())
        ? "<font color='green'>OK &mdash; applied table indexes</font><br>\n"
        : "<font color='red'>FAILED &mdash; could not apply one more more table indexes</font><br>\n";

    echo (SQLCore::installProcsAndFuncs(true))
        ? "<font color='green'>OK &mdash; created MySQL functions/procedures</font><br>\n"
        : "<font color='red'>FAILED &mdash; could not create MySQL functions/procedures</font><br>\n";
    echo ($result = mysql_query("CALL ".$_POST['proc'])) 
        ? "<font color='green'>OK &mdash; ran <b>$_POST[proc]</b></font><br>\n"
        : "<font color='red'>FAILED &mdash; could not run <b>$_POST[proc]</b>: '".mysql_error()."'</font><br>\n";
        
    echo "<br>";
}
?>
<b>MySQL procedure:</b>
<br>
<form method="POST">
    <INPUT TYPE=RADIO NAME="proc" VALUE="syncAll()">syncAll() - may take a few minutes!<br>
    <INPUT TYPE=RADIO NAME="proc" VALUE="syncAllMVs()">syncAllMVs()<br>
    <INPUT TYPE=RADIO NAME="proc" VALUE="syncAllDProps()">syncAllDProps()<br>
    <INPUT TYPE=RADIO NAME="proc" VALUE="syncAllRels()">syncAllRels()<br>
    <br>
    <input type="submit" name='submit' value="Run">
</form>

</small>
<hr>
<b>DB structure status:</b><br><br>
<table><tr valign='top'>
<td>
<i>Installed table indexes:</i><br>
<small>
<?php
$conn = mysql_up();
$result = mysql_query("SELECT index_name, table_name  FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = '$db_name'");
while ($row = mysql_fetch_row($result)) {
    echo "$row[0] ($row[1])<br>\n";
}
?>
</small>
</td>
<td>
<i>Installed table triggers:</i><br>
<small>
<?php
$result = mysql_query("SELECT trigger_name FROM INFORMATION_SCHEMA.TRIGGERS");
while ($row = mysql_fetch_row($result)) {
    echo "$row[0]<br>\n";
}
?>
</small>
</td>
</tr></table>
</body>
</html>
