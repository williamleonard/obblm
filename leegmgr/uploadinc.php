<?php

/*
 *
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

/*
 * Author William Leonard, 2009
 *
 * Note: XXXXXX
 */

error_reporting(0); 

function uploadpage () {

	Print "
	<!-- The data encoding type, enctype, MUST be specified as below -->
	<form enctype='multipart/form-data' action='$PHP_SELF' method='POST'>
	<!-- MAX_FILE_SIZE must precede the file input field -->
	<input type='hidden' name='MAX_FILE_SIZE' value='30000' />
	<!-- Name of input element determines name in $_FILES array -->
	Send this file: <input name='userfile' type='file' />
	<input type='submit' value='Send File' />
	</form>";

	if (isset($_FILES['userfile'])) {
		$uploaddir = '/var/www/uploads/';
		$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

		if (strlen($_FILES['userfile']['tmp_name'])>3)
		{
			$zip = zip_open($_FILES['userfile']['tmp_name']);
			Print "<br>Retrieved a file.<br>";
		}

		if ($zip  &&  $_FILES['userfile']['type'] == "application/x-zip-compressed") {
			Print "<br>Retrieved a zip file.<br>";

			while ($zip_entry = zip_read($zip)) {

				if (strpos(zip_entry_name($zip_entry),".xml") > 1 ) {
					Print "<br>Reading XML file from the zip file.<br>";
					$xmlresults = zip_entry_read($zip_entry, 10240);
					zip_entry_close($zip_entry);
				}

			}

			zip_close($zip);

			if ( isset($xmlresults) ) {
				Print "<br>Parsing the XML.<br>";
				$p = xml_parser_create();
				xml_parse_into_struct($p, $xmlresults, $vals, $index);
				xml_parser_free($p);
				Print "<br>Parsing data further.<br>";
				parse_results($index, $vals);
			}

			else {
				Print "<br>The zip file does not contain the results xml file.<br>";
			}

		}

		else {
			Print "<br>You must upload a zip file with the results in it.<br>";
		}

	}

}

function parse_results($index, $vals) {

	$i_hash = $index['HASH'][0];
	$hash = $vals[$i_hash][value];

	$i_gate = $index[GATE][0];
	$gate = $vals[$i_gate][value];

	$i_hometeam = $index[RESULT][0];
	$hometeam = $vals[$i_hometeam][attributes][TEAM];
	if ( checkCoach ( $hometeam ) )
	{
		Print "<br>The currently logged in coach owns this team.<br>";
	}
	else
	{
		Print "<br>The currently logged in coach does not own this team.<br>";
		exit (-1);
	}

	$i_awayteam = $index[RESULT][10];
	$awayteam = $vals[$i_awayteam][attributes][TEAM];
	if ( !$awayteam ) $awayteam = $vals[$index[RESULT][9]][attributes][TEAM];
	if ( !$awayteam ) $awayteam = $vals[$index[RESULT][11]][attributes][TEAM];
	if ( !$awayteam ) $awayteam = $vals[$index[RESULT][8]][attributes][TEAM];
	if ( !$awayteam ) $awayteam = $vals[$index[RESULT][12]][attributes][TEAM];

	$i_homewinnings = $index[WINNINGS][0];
	$homewinnings = $vals[$i_homewinnings][value];

	$i_awaywinnings = $index[WINNINGS][1];
	$awaywinnings = $vals[$i_awaywinnings][value];

	$i_homescore = $index[SCORE][0];
	$homescore = $vals[$i_homescore][value];

	$i_awayscore = $index[SCORE][1];
	$awayscore = $vals[$i_awayscore][value];

	$i_homeff = $index[FANFACTOR][0];
	$homeff = $vals[$i_homeff][value];

	$i_awayff = $index[FANFACTOR][1];
	$awayff = $vals[$i_awayff][value];

	####PLAYER DATA BEGIN
	$i = 0;
	#Print count ( $index[PLAYERS] )."<br>";
	
	#Print "<b>Home Player Indexes</b><br>";
	Print "<br>Parsing the home player indexes<br>";
	While ( $index[PLAYERS][$i] < $index[RESULT][10]) {
		#Print $i_homeplayers[$i] = $index[PLAYERS][$i]."<br>";
		$i_homeplayers[$i] = $index[PLAYERS][$i];
		$i++;
	}

	$h = 0;
	Print "<br>Parsing the away player indexes<br>";
	While ( $i < count ( $index[PLAYERS] )) {
		#Print $i_awayplayers[$h] = $index[PLAYERS][$i]."<br>";
		$i_awayplayers[$h] = $index[PLAYERS][$i];
		$h++;
		$i++;
	}
	#***BEGIN HOME TEAM***

	$i = $i_homeplayers[0];

	$h = 0;
	$countihomerplayers = $i_homeplayers[(count($i_homeplayers)-1)];
	Print "<br>Parsing home player match statistics.<br>";
	while ( $i < ($countihomerplayers-1) ) {
		
		if ( $vals[$i][attributes][PLAYER] > 0 ) {

			$homeplayers[$h][number] = $vals[$i][attributes][PLAYER];
			
			$j = $i;
			while ( $vals[$j][type] != "close" ) {
				switch ($vals[$j][tag]) {
					case "COMPLETIONS":
						$homeplayers[$h][completions] = $vals[$j][value];
						break;
					case "TOUCHDOWNS":
						$homeplayers[$h][touchdowns] = $vals[$j][value];
						break;
					case "INTERCEPTIONS":
						$homeplayers[$h][interceptions] = $vals[$j][value];
						break;
					case "CASUALTIES":
						$homeplayers[$h][casualties] = $vals[$j][value];
						break;
					case "MVPS":
						$homeplayers[$h][mvps] = $vals[$j][value];
						break;
					case "PASSING":
						$homeplayers[$h][passing] = $vals[$j][value];
						break;
					case "RUSHING":
						$homeplayers[$h][rushing] = $vals[$j][value];
						break;
					case "BLOCKS":
						$homeplayers[$h][blocks] = $vals[$j][value];
						break;
					case "FOULS":
						$homeplayers[$h][fouls] = $vals[$j][value];
						break;
					case "EFFECT":
						$homeplayers[$h][effect] = $vals[$j][value];
						break;
				}

				$j=$j+1;
			}

			$h++;
		}
		$i=$i+1;
		

	}

	#***END HOME TEAM***
	#***BEGIN AWAY TEAM***
	$h = 0;
	$j = 0;

	$countiawayrplayers = $i_awayplayers[(count($i_awayplayers)-1)];

	Print "<br>Parsing away player match statistics.<br>";	
	while ( $i < ($countiawayrplayers-1) ) {
		

		if ( $vals[$i][attributes][PLAYER] > 0 ) {

			$awayplayers[$h][number] = $vals[$i][attributes][PLAYER];
			
			$j = $i;
			while ( $vals[$j][type] != "close" ) {
				switch ($vals[$j][tag]) {
					case "COMPLETIONS":
						$awayplayers[$h][completions] = $vals[$j][value];
						break;
					case "TOUCHDOWNS":
						$awayplayers[$h][touchdowns] = $vals[$j][value];
						break;
					case "INTERCEPTIONS":
						$awayplayers[$h][interceptions] = $vals[$j][value];
						break;
					case "CASUALTIES":
						$awayplayers[$h][casualties] = $vals[$j][value];
						break;
					case "MVPS":
						$awayplayers[$h][mvps] = $vals[$j][value];
						break;
					case "PASSING":
						$awayplayers[$h][passing] = $vals[$j][value];
						break;
					case "RUSHING":
						$awayplayers[$h][rushing] = $vals[$j][value];
						break;
					case "BLOCKS":
						$awayplayers[$h][blocks] = $vals[$j][value];
						break;
					case "FOULS":
						$awayplayers[$h][fouls] = $vals[$j][value];
						break;
					case "EFFECT":
						$awayplayers[$h][effect] = $vals[$j][value];
						break;
				}

				$j=$j+1;
			}

			$h++;
		}
		$i=$i+1;
		

	}

	
	#***END AWAY TEAM***
	Print "<br>Creating unique match id.<br>";
	$hash = XOREncrypt ( $hometeam.$gate.$homescore.$homewinnings, $awayteam.$gate.$awayscore.$awaywinnings);
	Print "<br>Beginning reporting process.<br>";
	report ( $homeplayers, $awayplayers, $gate, $hometeam, $homescore, $homewinnings, $homeff, $awayteam, $awayscore, $awaywinnings, $awayff, $hash );
	####PLAYER DATA END

}

function report ( $homeplayers, $awayplayers, $gate, $hometeam, $homescore, $homewinnings, $homeff, $awayteam, $awayscore, $awaywinnings, $awayff, $hash ) {

	Print "<br>Connecting to the database.<br>";
	mysql_up();
	
	### BEGIN REPORT MATCHES TABLE

	$matchfields = addMatch ( $hash, $hometeam, $awayteam, $gate, $homeff, $awayff, $homewinnings, $awaywinnings, $homescore, $awayscore );

	
	###END REPORT MATCHES TABLE

	###BEGIN REPORT MATCH_DATA TABLE

	$i = 0;
#######	
	$match = new Match( $matchfields[match_id] );
#######

	$team = new Team( $matchfields[hometeam_id] );
	$players = $team->getPlayers();

	while ( $i < count( $homeplayers ) ) {
		$homeplayer_nr = $homeplayers[$i][number];
		$t = 0;
		foreach ( $players as $p  )
		{
			if ( $p->nr == $homeplayer_nr && !$p->is_dead && !$p->is_sold ) {
				$f_player_id = $p->player_id;
				break;
			}
		}
		
		$mvp = $homeplayers[$i][mvps];
		if ($mvp == NULL) $mvp = 0;
		$cp = $homeplayers[$i][completions];
		if ($cp == NULL) $cp = 0;
		$td = $homeplayers[$i][touchdowns];
		if ($td == NULL) $td = 0;
		$intcpt = $homeplayers[$i][interceptions];
		if ($intcpt == NULL) $intcpt = 0;
		$bh = $homeplayers[$i][casualties];
		if ($bh == NULL) $bh = 0;
		#$si = $homeplayers[$i]
		#$ki = $homeplayers[$i]
		switch ($homeplayers[$i][effect]) {
			case NULL:
				$injeffect = 1;
				break;
			case "m":
				$injeffect = 2;
				break;
			case "m, n":
				$injeffect = 3;
				break;
			case "m, -ma":
				$injeffect = 4;
				break;
			case "m, -av":
				$injeffect = 5;
				break;
			case "m, -ag":
				$injeffect = 6;
				break;
			case "m, -st":
				$injeffect = 7;
				break;
			case "d":
				$injeffect = 8;
				break;
		}
		
		$inj = $injeffect;
		
#######		#Begin using match class to enter player data
		$match->entry( $input = array ( "player_id" => $f_player_id, "mvp" => $mvp, "cp" => $cp, "td" => $td, "intcpt" => $intcpt, "bh" => $bh, "si" => 0, "ki" => 0, "inj" => $inj ) );
#######		#End using match class to enter player data
		$i=$i+1;
	}
	##ADD EMPTY RESULTS FOR PLAYERS WITHOUT RESULTS MAINLY FOR MNG
	foreach ( $players as $p  )
	{
		if (  !$p->is_dead && !$p->is_sold ) {
			$player = new Player ( $p->player_id );
			$p_matchdata = $player->getMatchData( $matchfields[match_id] );
			if ( !$p_matchdata[inj] ) {
				$match->entry( $input = array ( "player_id" => $p->player_id, "mvp" => 0, "cp" => 0,"td" => 0,"intcpt" => 0,"bh" => 0,"si" => 0,"ki" => 0, "inj" => 1 ) );
			}
		}
	}	

	##END ADD EMPTY RESULTS FOR PLAYERS WITHOUT RESULTS MAINLY FOR MNG

	####BEGIN AWAY PLAYER REPORT

	$i = 0;

	$team = new Team( $matchfields[awayteam_id] );
	$players = $team->getPlayers();

	while ( $i < count( $awayplayers ) ) {
		$awayplayer_nr = $awayplayers[$i][number];

		foreach ( $players as $p  )
		{
			if ( $p->nr == $awayplayer_nr && !$p->is_dead && !$p->is_sold ) {
				$f_player_id = $p->player_id;
				break;
			}
		}
		
		$mvp = $awayplayers[$i][mvps];
		if ($mvp == NULL) $mvp = 0;
		$cp = $awayplayers[$i][completions];
		if ($cp == NULL) $cp = 0;
		$td = $awayplayers[$i][touchdowns];
		if ($td == NULL) $td = 0;
		$intcpt = $awayplayers[$i][interceptions];
		if ($intcpt == NULL) $intcpt = 0;
		$bh = $awayplayers[$i][casualties];
		if ($bh == NULL) $bh = 0;
		#$si = $awayplayers[$i]
		#$ki = $awayplayers[$i]
		switch ($awayplayers[$i][effect]) {
			case NULL:
				$injeffect = 1;
				break;
			case "m":
				$injeffect = 2;
				break;
			case "m, n":
				$injeffect = 3;
				break;
			case "m, -ma":
				$injeffect = 4;
				break;
			case "m, -av":
				$injeffect = 5;
				break;
			case "m, -ag":
				$injeffect = 6;
				break;
			case "m, -st":
				$injeffect = 7;
				break;
			case "d":
				$injeffect = 8;
				break;
		}
		
		$inj = $injeffect;
		
		$match->entry( $input = array ( "player_id" => $f_player_id, "mvp" => $mvp, "cp" => $cp,"td" => $td,"intcpt" => $intcpt,"bh" => $bh,"si" => 0,"ki" => 0, "inj" => $inj ) );
		$i=$i+1;
	}

	####END AWAY PLAYER REPORT

	##ADD EMPTY RESULTS FOR AWAY PLAYERS WITHOUT RESULTS MAINLY FOR MNG

	foreach ( $players as $p  )
	{
		if (  !$p->is_dead && !$p->is_sold ) {
			$player = new Player ( $p->player_id );
			$p_matchdata = $player->getMatchData( $matchfields[match_id] );
			if ( !$p_matchdata[inj] ) {
				$match->entry( $input = array ( "player_id" => $p->player_id, "mvp" => 0, "cp" => 0,"td" => 0,"intcpt" => 0,"bh" => 0,"si" => 0,"ki" => 0, "inj" => 1 ) );
			}
		}
	}	

	##END ADD EMPTY RESULTS FOR AWAY PLAYERS WITHOUT RESULTS MAINLY FOR MNG

	###END REPORT MATCH_DATA TABLE
	$match->toggleLock();
	Print "<br>Successfully uploaded entire report<br>";


}

function XOREncryption($InputString, $KeyPhrase){
 
	$KeyPhraseLength = strlen($KeyPhrase);
 
	#Loop trough input string
	for ($i = 0; $i < strlen($InputString); $i++){
		#Get key phrase character position
		$rPos = $i % $KeyPhraseLength;

		#Magic happens here:
		$r = ord($InputString[$i]) ^ ord($KeyPhrase[$rPos]);
		#Replace characters
		$InputString[$i] = chr($r);
	}
	return $InputString;

}
 
function XOREncrypt($InputString, $KeyPhrase){

	$diff = strlen($InputString) - strlen($KeyPhrase);

	while ( $diff > 0 )
	{
		$KeyPhrase = $KeyPhrase." ";
		$diff = $diff - 1;
	}
	while ( $diff < 0 )
	{
		$InputString = $InputString." ";
		$diff = $diff + 1;
	}
	$InputString = XOREncryption($InputString, $KeyPhrase);
	$InputString = base64_encode($InputString);
	return $InputString;
}

function checkCoach ( $hometeam ) {

	if ( !mysql_fetch_array( mysql_query( "SELECT `owned_by_coach_id` FROM `teams` WHERE `owned_by_coach_id` = ".$_SESSION['coach_id']." and `name` = \"".$hometeam."\"" ) ) )
	{
		return 0;
	}

	return 1;

}

function addMatch ( $hash, $hometeam, $awayteam, $gate, $homeff, $awayff, $homewinnings, $awaywinnings, $homescore, $awayscore ) {

	$tour_id = 1; #get from settings later or find from scheduled matches.
	$query = "SELECT hash FROM matches WHERE hash = \"".$hash."\"";
	$hashresults = mysql_query($query);
	$hashresults = mysql_fetch_array($hashresults);
	$hashresults = $hashresults['hash'];

	if ( $hashresults == $hash ) {
		Print "<br>Unique match id already exists: <b>".$hash."<br>";
		exit(-1);
	}
	else
	{
		Print "<br>Continue reporting, the unique match id does not alreadt exist.<br>";
	}

	$query = "SELECT team_id FROM teams WHERE name = \"".$hometeam."\"";
	$hometeam_id = mysql_query($query);
	if (!$hometeam_id) {
		Print "<br>The home team in the report does not exist on this site.<br>";
		die('Query failed: ' . mysql_error());
		exit(-1);
	}
	else
	{
		Print "<br>The home team in the report was found on the site.";
	}
	$hometeam_id = mysql_fetch_array($hometeam_id);
	$hometeam_id = $hometeam_id['team_id'];
	if ( !($hometeam_id >= 1) ) exit(-1);
	
	$query = "SELECT team_id FROM teams WHERE name = \"".$awayteam."\"";
	$awayteam_id = mysql_query($query);
	if (!$awayteam_id) {
		Print "<br>The away team in the report does not exist on this site.<br>";
		die('Query failed: ' . mysql_error());
		exit(-1);
	}
	else
	{
		Print "<br>The away team in the report was found on the site.";
	}
	$awayteam_id = mysql_fetch_array($awayteam_id);
	$awayteam_id = $awayteam_id['team_id'];

#######	#Begin using Match class to create match.
	$match = new Match();
	$match->create( $input = array("team1_id" => $hometeam_id, "team2_id" => $awayteam_id, "round" => 255, "f_tour_id" => 1, "hash" => $hash) );
	unset( $input );
#######	#End using Match class to create match.

	$query = "SELECT match_id FROM matches WHERE hash = \"".$hash."\"";
	$match_id = mysql_query($query);
	if (!$match_id) {
		Print "<br>Failed to retrive match_id.<br>";
		die('Query failed: ' . mysql_error());
		exit(-1);
	}
	$match_id = mysql_fetch_array($match_id);
	$match_id = $match_id['match_id'];

#######	#Begin using Match class to update match.
	$match = new Match($match_id);
	$match->update( $input = array("submitter_id" => 1, "stadium" => $hometeam_id, "gate" => $gate, "fans" => 0, "ffactor1" => $homeff, "ffactor2" => $awayff, "income1" => $homewinnings, "income2" => $awaywinnings, "team1_score" => $homescore, "team2_score" => $awayscore, "smp1" => 0, "smp2" => 0, "tcas1" => 0, "tcas2" => 0) );
#######	#End using Match class to update match.

	$matchfields = array( "tour_id" => $tour_id, "hometeam_id" => $hometeam_id, "awayteam_id" => $awayteam_id, "match_id" => $match_id ); # homecoach_id awaycoach_id
	return $matchfields;

}

?>