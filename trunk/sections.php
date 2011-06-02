<?php

/*
 *  Copyright (c) Niels Orsleff Justesen <njustesen@gmail.com> and Nicholas Mossor Rathmann <nicholas.rathmann@gmail.com> 2007-2011. All Rights Reserved.
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

/*************************
 *
 *  Login
 *
 *************************/

function sec_login() {

    global $lng, $settings;

    $_URL_forgotpass = "index.php?section=login&amp;forgotpass=1";
    if (isset($_GET['forgotpass'])) {
        if (!isset($_POST['_retry'])) {
            title($lng->getTrn('login/forgotpass'));
        }
        if (isset($_GET['cid']) && isset($_GET['activation_code'])) {
            $c = new Coach($_GET['cid']);
            status($new_passwd = $c->confirmActivation($_GET['activation_code']));
            echo "<br><br>";
            echo $lng->getTrn('login/temppasswd')." <b>$new_passwd</b><br>\n";
            echo '<a href="'.urlcompile(T_URL_PROFILE,T_OBJ_COACH,$_GET['cid'],false,false).'&amp;subsec=profile">'.$lng->getTrn('login/setnewpasswd').'.</a>';
        }
        else if (isset($_POST['coach_AC']) && isset($_POST['email'])) {
            $cid = get_alt_col('coaches', 'name', $_POST['coach_AC'], 'coach_id');
            $c = new Coach($cid);
            $correct_user = ($c->mail == $_POST['email']);
            status($correct_user, $correct_user ? '' : $lng->getTrn('login/mismatch'));
            if ($correct_user) {
                $c->requestPasswdReset();
                echo "<br><br>";
                echo $lng->getTrn('login/resetpasswdmail').'.';
            }
            else {
                // Return to same page.
                unset($_POST['coach']);
                unset($_POST['email']);
                $_POST['_retry'] = true;
                sec_login();
            }
        }
        else {
            ?>
            <div class='boxCommon'>
                <h3 class='boxTitle<?php echo T_HTMLBOX_COACH;?>'><?php echo $lng->getTrn('login/forgotpass');?></h3>
                <div class='boxBody'>
                <form method="POST" action="<?php echo $_URL_forgotpass;?>">
                    <?php echo $lng->getTrn('login/loginname');?><br>
                    <input type="text" name="coach_AC" size="20" maxlength="50">
                    <br><br>
                    Email<br>
                    <input type="text" name="email" size="20" maxlength="50">
                    <br><br>
                    <input type="submit" name="reqAC" value="<?php echo $lng->getTrn('common/submit');?>">
                </form>
                </div>
            </div>
            <?php
        }
    }
    else {
        title($lng->getTrn('menu/login'));
        ?>
        <div class='boxCommon'>
            <h3 class='boxTitle<?php echo T_HTMLBOX_COACH;?>'><?php echo $lng->getTrn('menu/login');?></h3>
            <div class='boxBody'>
            <form method="POST" action="index.php">
                <?php echo $lng->getTrn('login/loginname');?><br>
                <input type="text" name="coach" size="20" maxlength="50"><br><br>
                <?php echo $lng->getTrn('login/passwd');?><br>
                <input type="password" name="passwd" size="20" maxlength="50">
                <div style='display: none;'><input type='text' name='hackForHittingEnterToLogin' size='1'></div>
                <br><br>
                <?php echo $lng->getTrn('login/remember');?>
                <input type='checkbox' name='remember' value='1'>
                <br><br>
                <input type="submit" name="login" value="<?php echo $lng->getTrn('login/loginbutton');?>">
            </form>
            <br><br>
            <?php
            if (Module::isRegistered('Registration') && $settings['allow_registration']) {
                echo "<a href='handler.php?type=registration'><b>Register</b></a>";
            }
            echo "<br><br>";
            echo "<a href='$_URL_forgotpass'><b>".$lng->getTrn('login/forgotpass').'</b></a>';
            ?>
            </div>
        </div>
        <?php
    }
}

/*************************
 *
 *  MAIN
 *
 *************************/

function sec_main() {

    global $settings, $rules, $coach, $lng, $leagues;

    MTS('Main start');

    list($sel_lid, $HTML_LeagueSelector) = HTMLOUT::simpleLeagueSelector();
    $IS_GLOBAL_ADMIN = (is_object($coach) && $coach->ring == Coach::T_RING_GLOBAL_ADMIN);

    /*
     *  Was any main board actions made?
     */

    if (isset($_POST['type']) && is_object($coach) && $coach->isNodeCommish(T_NODE_LEAGUE, $sel_lid)) {
        if (get_magic_quotes_gpc()) {
            if (isset($_POST['title'])) $_POST['title'] = stripslashes($_POST['title']);
            if (isset($_POST['txt']))   $_POST['txt']   = stripslashes($_POST['txt']);
        }
        $msg = isset($_POST['msg_id']) ? new Message((int) $_POST['msg_id']) : null;
        switch ($_POST['type'])
        {
            case 'msgdel': status($msg->delete()); break;
            case 'msgnew':
                status(Message::create(array(
                    'f_coach_id' => $coach->coach_id,
                    'f_lid'      => ($IS_GLOBAL_ADMIN && isset($_POST['BC']) && $_POST['BC']) ? Message::T_BROADCAST : $sel_lid,
                    'title'      => $_POST['title'],
                    'msg'        => $_POST['txt'])
                )); break;
            case 'msgedit': status($msg->edit($_POST['title'], $_POST['txt'])); break;
        }
    }

    /*
     *  Now we are ready to generate the HTML code.
     */
	if (Module::isRegistered('LeaguePref')) {
		$l_pref= LeaguePref::getLeaguePreferences();
		$league_name = $l_pref->league_name;
		$welcome = $l_pref->welcome;
	} else {
		$league_name = $settings['league_name'];
		$welcome = $settings['welcome'];
	}


    ?>
    <div class="main_head"><?php echo $league_name; ?></div>
    <div class='main_leftColumn'>
        <div class="main_leftColumn_head">
            <?php
            echo "<div class='main_leftColumn_welcome'>\n";
            echo $welcome;
            echo "</div>\n";
            echo "<div class='main_leftColumn_left'>\n";
            echo $HTML_LeagueSelector;
            echo "</div>\n";
            echo "<div class='main_leftColumn_right'>\n";
            if (is_object($coach) && $coach->isNodeCommish(T_NODE_LEAGUE, $sel_lid)) {
                echo "<a href='javascript:void(0);' onClick=\"slideToggle('msgnew');\">".$lng->getTrn('main/newmsg')."</a>&nbsp;\n";
            }
            if (Module::isRegistered('RSSfeed')) {echo "<a href='handler.php?type=rss'>RSS</a>\n";}
            echo "</div>\n";
            ?>
            <div style="display:none; clear:both;" id="msgnew">
                <br><br>
                <form method="POST">
                    <textarea name="title" rows="1" cols="50"><?php echo $lng->getTrn('common/notitle');?></textarea><br><br>
                    <textarea name="txt" rows="15" cols="50"><?php echo $lng->getTrn('common/nobody');?></textarea><br><br>
                    <?php
                    if ($IS_GLOBAL_ADMIN) {
                        echo $lng->getTrn('main/broadcast');
                        ?><input type="checkbox" name="BC"><br><br><?php
                    }
                    ?>
                    <input type="hidden" name="type" value="msgnew">
                    <input type="submit" value="<?php echo $lng->getTrn('common/submit');?>">
                </form>
            </div>
        </div>

        <?php
        /*
            Generate main board.

            Left column is the message board, consisting of both commissioner messages and game summaries/results.
            To generate this table we create a general array holding the content of both.
        */
        $j = 1;
        foreach (TextSubSys::getMainBoardMessages($settings['fp_messageboard']['length'], $sel_lid, $settings['fp_messageboard']['show_team_news'], $settings['fp_messageboard']['show_match_summaries']) as $e) {
            echo "<div class='boxWide'>\n";
                echo "<h3 class='boxTitle$e->cssidx'>$e->title</h3>\n";
                echo "<div class='boxBody'>\n";
                    $fmtMsg = fmtprint($e->message); # Basic supported syntax: linebreaks.
                    echo "
                    <div id='e$j' class='expandable'>$fmtMsg</div>
                    <script type='text/javascript'>
                      $('#e$j').expander({
                        slicePoint:       300,
                        expandText:       '".$lng->getTrn('main/more')."',
                        collapseTimer:    0,
                        userCollapseText: ''
                      });
                      </script>";
                    echo "<br><hr>\n";
                    echo "<table class='boxTable'><tr>\n";
                        switch ($e->type)
                        {
                            case T_TEXT_MATCH_SUMMARY:
                                echo "<td align='left' width='100%'>".$lng->getTrn('main/posted')." ".textdate($e->date)." " . (isset($e->date_mod) ? "(".$lng->getTrn('main/lastedit')." ".textdate($e->date_mod).") " : '') .$lng->getTrn('main/by')." $e->author</td>\n";
                                echo "<td align='right'><a href='index.php?section=matches&amp;type=report&amp;mid=$e->match_id'>".$lng->getTrn('common/view')."</a></td>\n";
                                break;
                            case  T_TEXT_MSG:
                                echo "<td align='left' width='100%'>".$lng->getTrn('main/posted')." ".textdate($e->date)." ".$lng->getTrn('main/by')." $e->author</td>\n";
                                if (is_object($coach) && ($IS_GLOBAL_ADMIN || $coach->coach_id == $e->author_id)) { // Only admins may delete messages, or if it's a commissioner's own message.
                                    echo "<td align='right'><a href='javascript:void(0);' onClick=\"slideToggle('msgedit$e->msg_id');\">".$lng->getTrn('common/edit')."</a></td>\n";
                                    echo "<td align='right'>";
                                    echo inlineform(array('type' => 'msgdel', 'msg_id' => $e->msg_id), "msgdel$e->msg_id", $lng->getTrn('common/delete'));
                                    echo "</td>";
                                }
                                break;
                            case T_TEXT_TNEWS:
                                echo "<td align='left' width='100%'>".$lng->getTrn('main/posted')." ".textdate($e->date)."</td>\n";
                                break;
                        }
                        ?>
                    </tr></table>
                    <?php
                    if ($e->type == T_TEXT_MSG) {
                        echo "<div style='display:none;' id='msgedit$e->msg_id'>\n";
                        echo "<hr><br>\n";
                        echo '<form method="POST">
                            <textarea name="title" rows="1" cols="50">'.$e->title.'</textarea><br><br>
                            <textarea name="txt" rows="15" cols="50">'.$e->message.'</textarea><br><br>
                            <input type="hidden" name="type" value="msgedit">
                            <input type="hidden" name="msg_id" value="'.$e->msg_id.'">
                            <input type="submit" value="'.$lng->getTrn('common/submit').'">
                        </form>';
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
            <?php
            $j++;
        }
        ?>

    </div>
    <?php
    MTS('Board messages generated');

    /*
        The right hand side column optionally (depending on settings) contains standings, latest game results, touchdown and casualties stats.
        We will now generate the stats, so that they are ready to be printed in correct order.
    */

    echo "<div class='main_rightColumn'>\n";
    $boxes_all = array_merge($settings['fp_standings'], $settings['fp_leaders'], $settings['fp_latestgames']);
    usort($boxes_all, create_function('$a,$b', 'return (($a["box_ID"] > $b["box_ID"]) ? 1 : (($a["box_ID"] < $b["box_ID"]) ? -1 : 0) );'));
    $boxes = array();


    foreach ($boxes_all as $box) {
        # These fields distinguishes the box types.
        if      (isset($box['fields'])) {$box['dispType'] = 'standings';}
        else if (isset($box['field']))  {$box['dispType'] = 'leaders';}
        else                            {$box['dispType'] = 'latestgames';}
        switch ($box['type']) {
            case 'league':     $_type = T_NODE_LEAGUE; break;
            case 'division':   $_type = T_NODE_DIVISION; break;
            case 'tournament': $_type = T_NODE_TOURNAMENT; break;
            default: $_type = T_NODE_LEAGUE;
        }
        $box['type'] = $_type;
    	if (Module::isRegistered('LeaguePref')) {
    		global $tours;
    		//print_r($tours);
    		// dynamically switch the front page to show the tables for the preferred tournament
			switch ($box['id']) {
				case 'prime':
					if ($l_pref->p_tour > 0) {
						$box['id'] = $l_pref->p_tour ;
						$box['title'] = $tours[$l_pref->p_tour]['tname'] . " " . $box['title'];
					} else {
						$box['id'] = 1;
						$box['title'] = 'Please set league primary tournament';
					}
					break;
				case 'second':
					if ($l_pref->s_tour > 0) {
						$box['id'] = $l_pref->s_tour ;
						$box['title'] = $tours[$l_pref->s_tour]['tname'] . " " . $box['title'];
					} else {
						$box['id'] = 1;
						$box['title'] = 'Please set league secondary tournament';
					}
					break;
			}
    	}

        $boxes[] = $box;
    }

    // Used in the below standings dispType boxes.
    global $core_tables, $ES_fields;
    $_MV_COLS = array_merge(array_keys($core_tables['mv_teams']), array_keys($ES_fields));
    $_MV_RG_INTERSECT = array_intersect(array_keys($core_tables['teams']), array_keys($core_tables['mv_teams']));

    // Let's print those boxes!
    foreach ($boxes as $box) {

    switch ($box['dispType']) {

        case 'standings':
            $_BAD_COLS = array(); # Halt on these columns/fields.
            switch ($box['type']) {
                case T_NODE_TOURNAMENT:
                    if (!get_alt_col('tours', 'tour_id', $box['id'], 'tour_id')) {
                        break 2;
                    }
                    $tour = new Tour($box['id']);
                    $SR = array_map(create_function('$val', 'return $val[0]."mv_".substr($val,1);'), $tour->getRSSortRule());
                    break;

                case T_NODE_DIVISION:
                    $_BAD_COLS = array('elo', 'swon', 'slost', 'sdraw', 'win_pct'); # Divisions do not have pre-calculated, MV, values of these fields.
                    // Fall through!
                case T_NODE_LEAGUE:
                default:
                    global $hrs;
                    $SR = $hrs[$box['HRS']]['rule'];
                    foreach ($SR as &$f) {
                        $field = substr($f,1);
                        if (in_array($field, $_MV_RG_INTERSECT)) {
                            if (in_array($field, $_BAD_COLS)) { # E.g. divisions have no win_pct record for teams like the mv_teams table (for tours) has.
                                fatal("Sorry, the element '$field' in your specified house sortrule #$box[HRS] is not supported for your chosen type (ie. tournament/division/league).");
                            }
                            $f = $f[0]."rg_".substr($f,1);
                        }
                        else {
                            $f = $f[0]."mv_".substr($f,1);
                        }
                    }
                    break;
            }
            $teams = Stats::getRaw(T_OBJ_TEAM, array($box['type'] => $box['id']), $box['length'], $SR, false);
            ?>
            <div class='boxWide'>
                <h3 class='boxTitle<?php echo T_HTMLBOX_STATS;?>'><?php echo $box['title'];?></h3>
                <div class='boxBody'>
                    <table class="boxTable">
                        <?php
                        echo "<tr>\n";
                        foreach ($box['fields'] as $title => $f) {
                            echo "<td><i>$title</i></td>\n";
                        }
                        echo "</tr>\n";
                        foreach ($teams as $t) {
                            echo "<tr>\n";
                            foreach ($box['fields'] as $title => $f) {
                                if (in_array($f, $_MV_COLS)) {
                                    $f = 'mv_'.$f;
                                }
                                echo "<td>";
                                if ($settings['fp_links'] && $f == 'name')
                                    echo "<a href='".urlcompile(T_URL_PROFILE,T_OBJ_TEAM,$t['team_id'],false,false)."'>$t[name]</a>";
                                elseif (is_numeric($t[$f]) && !ctype_digit(($t[$f][0] == '-') ? substr($t[$f],1) : $t[$f]))
                                    echo sprintf('%1.2f', $t[$f]);
                                else
                                    echo in_array($f, array('tv')) ? $t[$f]/1000 : $t[$f];
                                echo "</td>\n";
                            }
                            echo "</tr>\n";
                        }
                        ?>
                    </table>
                    <?php
                    if (isset($box['infocus']) && $box['infocus']) {
                        echo "<hr>";
                        _infocus($teams);
                    }
                    ?>
                </div>
            </div>
            <?php
            MTS('Standings table generated');
            break;

        case 'latestgames':
			showGames($box);
			MTS('Latest matches table generated');
            break;

        case 'upcominggames':
			showGames($box);
            MTS('Upcoming matches table generated');
            break;

        case 'leaders':

            $f = 'mv_'.$box['field'];
            $players = Stats::getRaw(T_OBJ_PLAYER, array($box['type'] => $box['id']), $box['length'], array('-'.$f), false)
            ?>
            <div class="boxWide">
                <h3 class='boxTitle<?php echo T_HTMLBOX_STATS;?>'><?php echo $box['title'];?></h3>
                <div class='boxBody'>
                    <table class="boxTable">
                        <tr>
                            <td><i><?php echo $lng->getTrn('common/name');?></i></td>
                            <?php
                            if ($box['show_team']) {
                                ?><td><i><?php echo $lng->getTrn('common/team');?></i></td><?php
                            }
                            ?>
                            <td><i>#</i></td>
                            <td><i><?php echo $lng->getTrn('common/value');?></i></td>
                        </tr>
                        <?php
                        foreach ($players as $p) {
                            echo "<tr>\n";
                            echo "<td>".(($settings['fp_links']) ? "<a href='".urlcompile(T_URL_PROFILE,T_OBJ_PLAYER,$p['player_id'],false,false)."'>$p[name]</a>" : $p['name'])."</td>\n";
                            if ($box['show_team']) {
                                echo "<td>".(($settings['fp_links']) ? "<a href='".urlcompile(T_URL_PROFILE,T_OBJ_TEAM,$p['owned_by_team_id'],false,false)."'>$p[f_tname]</a>" : $p['f_tname'])."</td>\n";
                            }
                            echo "<td>".$p[$f]."</td>\n";
                            echo "<td>".$p['value']/1000 ."k</td>\n";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
            <?php
            MTS('Leaders standings generated');
            break;
    }
    }
    ?>
    </div>
    <div class="main_foot">
        <a href="index.php?section=about">Please doante if you enjoy this software</a><br><br>
        <a TARGET="_blank" href="http://nicholasmr.dk/index.php?sec=obblm">OBBLM official website</a><br><br>
        This web site is completely unofficial and in no way endorsed by Games Workshop Limited.
        <br>
        Bloodquest, Blood Bowl, the Blood Bowl logo, The Blood Bowl Spike Device, Chaos, the Chaos device, the Chaos logo, Games Workshop, Games Workshop logo, Nurgle, the Nurgle device, Skaven, Tomb Kings, and all associated marks, names, races, race insignia, characters, vehicles, locations, units, illustrations and images from the Blood Bowl game, the Warhammer world are either (R), TM and/or (C) Games Workshop Ltd 2000-2006, variably registered in the UK and other countries around the world. Used without permission. No challenge to their status intended. All Rights Reserved to their respective owners.
    </div>
    <?php
}

function showGames($box) {
	global $lng, $settings;
	if ($box['length'] <= 0) {
		break;
	}
	$upcoming = isset($box['upcoming']) ? $box['upcoming'] : false;
	$matches = Match::getMatches($box['length'], $box['type'], $box['id'], $upcoming)
	?>
	<div class="boxWide">
		<h3 class='boxTitle<?php echo T_HTMLBOX_MATCH;?>'><?php echo $box['title'];?></h3>
		<div class='boxBody'>
			<table class="boxTable">
				<?php
				if (sizeof($matches) == 0) {
				?>
				<tr>
					<td style="text-align: center;" width="100%"><i><?php echo $lng->getTrn('main/nomatches');?></i></td><td> </td>
				</tr>
				<?php
				} else {
				?>
				<tr>
					<td style="text-align: right;" width="50%"><i><?php echo $lng->getTrn('common/home');?></i></td><td> </td>
					<td style="text-align: left;" width="50%"><i><?php echo $lng->getTrn('common/away');?></i></td><td> </td>
				</tr>
				<?php
					foreach ($matches as $m) {
						echo "<tr valign='top'>\n";
						$t1name = ($settings['fp_links']) ? "<a href='".urlcompile(T_URL_PROFILE,T_OBJ_TEAM,$m->team1_id,false,false)."'>$m->team1_name</a>" : $m->team1_name;
						$t2name = ($settings['fp_links']) ? "<a href='".urlcompile(T_URL_PROFILE,T_OBJ_TEAM,$m->team2_id,false,false)."'>$m->team2_name</a>" : $m->team2_name;
						echo "<td style='text-align: right;'>$t1name</td>\n";
						if ($upcoming) {
							echo "<td><nobr>?&mdash;?</nobr></td>\n";
						} else {
							echo "<td><nobr>$m->team1_score&mdash;$m->team2_score</nobr></td>\n";
						}
						echo "<td style='text-align: left;'>$t2name</td>\n";
						echo "<td><a href='index.php?section=matches&amp;type=report&amp;mid=$m->match_id'>Show</a></td>";
						echo "</tr>";
					}
				}
				?>
			</table>
		</div>
	</div>
	<?php
}

$_INFOCUSCNT = 1; # HTML ID for _infocus()
function _infocus($teams) {

    //Create a new array of teams to display
    $ids = array();
    foreach ($teams as $team) {
        if (!$team['retired']) {
            $ids[] = $team['team_id'];
        }
    }

    if (empty($teams)) {
        return;
    }

    global $lng, $_INFOCUSCNT;

    //Select random team
    $teamKey = array_rand($ids);
    $teamId = $ids[$teamKey];
    $team = new Team($teamId);
    $teamLink =  "<a href='".urlcompile(T_URL_PROFILE,T_OBJ_TEAM,$teamId,false,false)."'>$team->name</a>";

    //Create $logo_html
    $img = new ImageSubSys(IMGTYPE_TEAMLOGO, $team->team_id);
    $logo_html = "<img border='0px' height='60' alt='Team picture' src='".$img->getPath($team->f_race_id)."'>";

    //Create $starPlayers array used to display the three most experienced players on the team
    $starPlayers = array();
    foreach ($team->getPlayers() as $p) {
        if ($p->is_dead || $p->is_sold) {
            continue;
        }
        $starPlayers[] = array('name' => preg_replace('/\s/', '&nbsp;', $p->name), 'spp' => $p->mv_spp);
    }

    //Sort the array
    usort($starPlayers, create_function('$objA,$objB', 'return ($objA["spp"] < $objB["spp"]) ? +1 : -1;'));
    $starPlayers = array_slice($starPlayers, 0, 3); # Show only 3 Star players

    ?>
    <style type="text/css">
        /* InFocus Mod */
        #inFocusBox<?php echo $_INFOCUSCNT;?> .leftContentTd{
            font-weight: bold;
            padding-right: 1em;
        }

        #inFocusBox<?php echo $_INFOCUSCNT;?> .teamLogo {
            float: left;
            margin: 0 36px 0 20px;
        }

        #inFocusBox<?php echo $_INFOCUSCNT;?> .teamName {
            font-weight: bold;
        }

        #inFocusContent<?php echo $_INFOCUSCNT;?> {
            position:relative;
            left: 160px;
            height: 80px;
        }

        #inFocusContent<?php echo $_INFOCUSCNT;?> P {
            font-weight: bold;
            margin-top: 5px;
            margin-bottom: 5px;
        }

        #inFocusContent<?php echo $_INFOCUSCNT;?> DIV {
            position:absolute;
            top:0;
            left:0;
            z-index:8;
        }

        #inFocusContent<?php echo $_INFOCUSCNT;?> DIV.invisible {
            display: none;
        }

        #inFocusContent<?php echo $_INFOCUSCNT;?> DIV.inFocus {
            z-index:10;
            display: inline;
        }

        #inFocusContent<?php echo $_INFOCUSCNT;?> DIV.last-inFocus {
            z-index:9;redeclare compare_spp
        }
    </style>
    <div id="inFocusBox<?php echo $_INFOCUSCNT;?>" >
        <h3><?php echo $lng->getTrn('main/infocus').': '.$teamLink; ?></h3><br>
        <div style='clear:both;'>
            <div class='teamLogo'>
                <?php echo $logo_html; ?>
            </div>
            <div id="inFocusContent<?php echo $_INFOCUSCNT;?>">
                <div class="inFocus">
                    <table>
                        <tr><td class="leftContentTd"><?php echo $lng->getTrn('common/coach'); ?></td><td><?php echo $team->f_cname; ?></td></tr>
                        <tr><td class="leftContentTd"><?php echo $lng->getTrn('common/race'); ?></td><td><?php echo $team->f_rname; ?></td></tr>
                        <tr><td class="leftContentTd"><?php echo 'TV'; ?></td><td><?php echo (string)($team->tv / 1000); ?>k</td></tr>
                    </table>
                </div>
                <div class="invisible">
                    <p><?php echo $lng->getTrn('common/stars'); ?></p>
                    <table>
                        <?php
                        foreach($starPlayers as $player) {
                            echo "<tr><td class='leftContentTd'>".$player['name']."</td><td>".$player['spp']." spp</td></tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
    /*
     * This script creates a slideshow of all <div>s in the "inFocusContent" div
     *
     * Based on an example by Jon Raasch:
     *
     * http://jonraasch.com/blog/a-simple-jquery-slideshow
     */
    function nextContent<?php echo $_INFOCUSCNT;?>() {
        var $currentDiv = $('#inFocusContent<?php echo $_INFOCUSCNT;?> DIV.inFocus');
        var $nextDiv = $currentDiv.next().length ? $currentDiv.next() : $('#inFocusContent<?php echo $_INFOCUSCNT;?> DIV:first');
        $currentDiv.addClass('last-inFocus');

        //Fade current out
        $currentDiv.animate({opacity: 0.0}, 500, function() {
            $currentDiv.removeClass('inFocus last-inFocus');
            $currentDiv.addClass('invisible');
        });

        //Fade next in
        $nextDiv.css({opacity: 0.0})
            .addClass('inFocus')
            .animate({opacity: 1.0}, 500, function() {
            });
    }

    $(function() {
        setInterval( "nextContent<?php echo $_INFOCUSCNT;?>()", 5000 );
    });
    </script>

    <?php
    $_INFOCUSCNT++;
}

function sec_teamlist() {
    global $lng;
    title($lng->getTrn('menu/teams'));
    Team_HTMLOUT::dispList();
}

function sec_coachlist() {
    global $lng;
    title($lng->getTrn('menu/coaches'));
    Coach_HTMLOUT::dispList();
}

function sec_matcheshandler() {
    switch ($_GET['type'])
    {
        # Save all these subroutines in class_match_htmlout.php
        case 'tours':       Match_HTMLOUT::tours(); break;
        case 'tourmatches': Match_HTMLOUT::tourMatches(); break;
        case 'report':      Match_HTMLOUT::report(); break;
        case 'recent':      Match_HTMLOUT::recentMatches(); break;
        case 'upcoming':    Match_HTMLOUT::upcomingMatches(); break;
        case 'usersched':   Match_HTMLOUT::userSched(); break;
    }
}

function sec_objhandler() {
    $types = array(T_OBJ_PLAYER => 'Player', T_OBJ_TEAM => 'Team', T_OBJ_COACH => 'Coach', T_OBJ_STAR => 'Star', T_OBJ_RACE => 'Race');
    foreach ($types as $t => $classPrefix) {
        if ($_GET['obj'] == $t) {
            switch ($_GET['type'])
            {
                case T_URL_STANDINGS:
                    call_user_func(
                        array("${classPrefix}_HTMLOUT", 'standings'),
                        isset($_GET['node'])    ? $_GET['node']    : false,
                        isset($_GET['node_id']) ? $_GET['node_id'] : false
                    );
                    break;
                case T_URL_PROFILE:
                    if (!call_user_func(array($classPrefix, 'exists'), $_GET['obj_id'])) {
                        fatal("The specified ID does not exist.");
                    }
                    call_user_func(array("${classPrefix}_HTMLOUT", 'profile'), $_GET['obj_id']);
                    break;
            }
        }
    }
}

/*************************
 *
 *  RULES
 *
 *************************/

function sec_rules() {

    global $lng, $settings;

    title($lng->getTrn('menu/rules'));
    list($sel_lid, $HTML_LeagueSelector) = HTMLOUT::simpleLeagueSelector();
    echo $HTML_LeagueSelector;
    echo "<br><br>";
	if (Module::isRegistered('LeaguePref')) {
		$l_pref= LeaguePref::getLeaguePreferences();
		echo $l_pref->rules;
	} else {
		echo $settings['rules'];
	}
}

/*************************
 *
 *  ABOUT
 *
 *************************/

function sec_about() {

    global $lng, $credits;
    title("About OBBLM");
    ?>
    If you enjoy this software, please support the further development of it by donating.<br>
    <br>
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
    <input type="hidden" name="cmd" value="_s-xclick">
    <input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----
    ">
    <input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
    <img alt="" border="0" src="https://www.paypal.com/da_DK/i/scr/pixel.gif" width="1" height="1">
    </form>
    <br>
    <p>
        <b>OBBLM version <?php echo OBBLM_VERSION; ?></b><br><br>
        Online Blood Bowl League Manager is an online game management system for Game Workshop's board game Blood Bowl.<br>
        <br>
        The authors of this program are
        <ul>
            <li> <a href="mailto:nicholas.rathmann@gmail.com">Nicholas Mossor Rathmann</a>
            <li> Niels Orsleff Justesen</a>
        </ul>
        With special thanks to <?php $lc = array_pop($credits); echo implode(', ', $credits)." and $lc"; ?>.<br><br>
        Bugs reports and suggestions are welcome.
        <br>
        OBBLM consists of valid HTML 4.01 transitional document type pages.
        <br><br>
        <img src="http://www.w3.org/Icons/valid-html401" alt="Valid HTML 4.01 Transitional" height="31" width="88">
        <br><br>
        <b>Modules loaded:</b><br>
        <?php
        $mods = array();
        foreach (Module::getRegistered() as $modname) {
            list($author,$date,$moduleName) = Module::getInfo($modname);
            $mods[] = "<i>$moduleName</i> ($author, $date)";
        }
        echo implode(', ', $mods);
        ?>
    </p>

    <?php
    title("Documentation");
    echo "See the <a TARGET='_blank' href='".DOC_URL."'>OBBLM documentation wiki</a>";

    ?>

    <?php title("Disclaimer");?>
    <p>
        By installing and using this software you hereby accept and understand the following disclaimer
        <br><br>
        <b>This web site is completely unofficial and in no way endorsed by Games Workshop Limited.</b>
        <br><br>
        Bloodquest, Blood Bowl, the Blood Bowl logo, The Blood Bowl Spike Device, Chaos, the Chaos device, the Chaos logo, Games Workshop, Games Workshop logo, Nurgle, the Nurgle device, Skaven, Tomb Kings,
        and all associated marks, names, races, race insignia, characters, vehicles, locations, units, illustrations and images from the Blood Bowl game, the Warhammer world are either ®, TM and/or © Games Workshop Ltd 2000-2006,
        variably registered in the UK and other countries around the world. Used without permission. No challenge to their status intended. All Rights Reserved to their respective owners.
    </p>

    <?php title("License");?>
    <p>
        Copyright (c) Niels Orsleff Justesen and Nicholas Mossor Rathmann 2007-2011. All Rights Reserved.
        <br><br>
        OBBLM is free software; you can redistribute it and/or modify
        it under the terms of the GNU General Public License as published by
        the Free Software Foundation; either version 3 of the License, or
        (at your option) any later version.
        <br><br>
        OBBLM is distributed in the hope that it will be useful,
        but WITHOUT ANY WARRANTY; without even the implied warranty of
        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
        GNU General Public License for more details.
        <br><br>
        You should have received a copy of the GNU General Public License
        along with this program.  If not, see http://www.gnu.org/licenses/.
    </p>
    <?php
}
?>
