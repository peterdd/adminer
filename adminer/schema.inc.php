<?php
/*
* rewrite of schema visualisation by https://github.com/peterdd
*/

$starttimeschema=microtime(true);

page_header(lang('Database schema'), "", array(), h(DB . ($_GET["ns"] ? ".$_GET[ns]" : "")));

# Arial-like free font for calculation string length in pixel
# installed this debian package: fonts-liberation2
$font='/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf';
$fontbold='/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf';

$hasfont=file_exists($font);
$hasfontbold=file_exists($fontbold);

$table_pos = array();
$table_pos_js = array();
$SCHEMA = ($_GET["schema"] ? $_GET["schema"] : $_COOKIE["adminer_schema-" . str_replace(".", "_", DB)]);
// $_COOKIE["adminer_schema"] was used before 3.2.0
//! ':' in table name
preg_match_all('/([^:]+):([\-0-9.]+)x([\-0-9.]+)(_|$)/', $SCHEMA, $matches, PREG_SET_ORDER);
foreach ($matches as $i => $match) {
	$table_pos[$match[1]] = array($match[2], $match[3]);
	$table_pos_js[] = "\n\t'" . js_escape($match[1]) . "': [ $match[2], $match[3] ]";
}

$schema = array(); // table => array("fields" => array(name => field), "pos" => array(top, left), "references" => array(table => array(left => array(source, target))))
$referenced = array(); // target_table => array(table => array(left => target_column))
#$lefts = array(); // float => bool

$lineheight=11; # field height in px
$tcounter=0;
$top=0;
$minleft=0;
$maxheight=0;
/**
* TODO: width calculate based on area required for the schema, needs sum area of each table to estimate a final appealing rectangle layout
* maybe get current viewport width of browser from request to detect rough orientation (portrait - landscape)
*/
$tables=table_status('', true);
#echo print_r($tables);

/**
* TODO: autogenerate best colors depending on current schema data
* TODO: for dark themes
* for bright themes
* linecolors when not used by 'ON DELETE' or 'ON UPDATE' line svg/css rules
*/
$linecolors=array(
	'#c00','#003','#900','#c60','#060', '#090','#099','#036','#009','#909','#309'
);

$ccount = count($linecolors);
$em=1;
$t = 0;
$area=0;
# temporal solution..
$tavgwidth=100;

foreach ($tables as $table => $table_status) {
	$f = 0;
	foreach (fields($table) as $name => $field) {
		$f++;
	}
	$tables[$table]['fcount'] = $f;
	$tables[$table]['refcount'] = 0;
	$tables[$table]['refcolor'] = $t % $ccount;
	if($table_pos[$table]){
		# orig store obscure em values
		$tables[$table]['pos'][0]=$table_pos[$table][0];
		$tables[$table]['pos'][1]=$table_pos[$table][1];
	}
	$area+=$tavgwidth*$lineheight*(1+$f);
	#echo $area.'<br>';
	$t++;
}

$layout = false;
$fcount = array_column($tables, 'fcount');
if (isset($_POST['layout'])){
	if ($_POST['layout'] === 'name'){
		$layout = 'name';
	} elseif ($_POST['layout'] === 'fieldcount'){
		$layout = 'fieldcount';
		array_multisort($fcount, SORT_ASC, $tables);
	} elseif ($_POST['layout'] === 'fieldcount_desc'){
		$layout = 'fieldcount_desc';
		array_multisort($fcount, SORT_DESC, $tables);
	} elseif ($_POST['layout'] === 'cookie'){
		$layout = 'cookie';
	} elseif ($_POST['layout'] === 'spring'){
		$layout = 'spring';
	}
} else{
	$layout='name';
}


/* depends on
	- area of tables
	- layout packing algorithm
	- how many connections so there is more space when there arre connection, unconnected tables nedd less space between.
	- an maybe existing stored (cookie) coord layout if exists and bigger than calculated
*/

if($layout=='name'){
	$viewportwidth=sqrt($area*4);
} else {
	$viewportwidth=sqrt($area*3);
}
$viewportwidth=$viewportwidth*1.3333; # a more landscape view instead quadratic

$legend= isset($_POST['legend']) ? true : false;
$miniinfo= isset($_POST['miniinfo']) ? true : false;
$minimap= isset($_POST['minimap']) ? true : false;
/* TODO: need info if minimap checkbox unset due not send or user set explicit off -> 2 radio boxes with values 0 and 1? */
if ($viewportwidth>1000) {
	$minimap=true;
}
if(isset($_POST['showfields'])){
	switch($_POST['showfields']){
		case 'nofields': $showfields='nofields'; break;
		case 'pkfields': $showfields='pkfields'; break;
		case 'pkfkfields': $showfields='pkfkfields'; break;
		case 'indexfields':$showfields='indexfields'; break;
		case 'allfields': $showfields='allfields'; break;
		default: $showfields='allfields';
	}
} else {
	$showfields='allfields';
}

if(isset($_POST['showtables'])){
	switch($_POST['showtables']){
		case 'connected': $showtables='connected'; break;
		case 'connected': $showtables='all'; break;
		default: $showtables='all';
	}
} else {
	$showtables='all';
}


# These have 3-states: unsent, sent 0, sent 1, so we cannot use a simple input[type=checkbox]
# default values when not sent in request, e.g when opening the schema view first that has only minimal url/params
$showrefpkt=0;
$showrefdelete=1;
$showrefupdate=1;
if(isset($_POST['showrefpkt'])){
	# keep independent from $showrefpkt default value
	if($_POST['showrefpkt']==1){
		$showrefpkt=1;
	} else if ($_POST['showrefpkt']==0){
		$showrefpkt=0;
	}
}

if(isset($_POST['showrefdelete'])){
	# keep independent from $showrefdelete default value
	if($_POST['showrefdelete']==1){
		$showrefdelete=1;
	} else if ($_POST['showrefdelete']==0){
		$showrefdelete=0;
	}
}

if(isset($_POST['showrefupdate'])){
	# keep independent from $showrefupdate default value
	if($_POST['showrefupdate']==1){
		$showrefupdate=1;
	} else if ($_POST['showrefupdate']==0){
		$showrefupdate=0;
	}
}

$showquerylog=0;
if(isset($_POST['showquerylog'])){
        $showquerylog=1;
} 

#echo '<pre>';print_r($tables);die();
$monowidth = 6;
foreach ($tables as $table => $table_status) {
	if (is_view($table_status)) {
		continue;
	}

	$tablewidth=40; # minimal table width in px
	if ($hasfontbold && function_exists('imagettfbbox')){
		$b = imagettfbbox(8, 0, $fontbold, $table);
		$tablewidth = max([$b[0],$b[2],$b[4],$b[6]]) - min([$b[0],$b[2],$$b[4],$b[6]]);
	} else {
		$tablewidth = strlen($table)*$monowidth;
	}

	$schema[$table]["fields"] = array();
	$schema[$table]["refcolor"]= $tables[$table]['refcolor'];
	$pos = $lineheight; # below table name assuming tablename is same height as field height.
	foreach (fields($table) as $name => $field) {
		$field["pos"] = $pos;
		$schema[$table]["fields"][$name] = $field;
		if ($hasfont && function_exists('imagettfbbox')){
			$b = imagettfbbox(8, 0, $font, $field['field']);
			$fieldwidth = max([$b[0],$b[2],$b[4],$b[6]]) - min([$b[0],$b[2],$$b[4],$b[6]]);
		} else {
			$fieldwidth = strlen($field['field'])*$monowidth;
		}
		$tablewidth = max($tablewidth, $fieldwidth);
		$pos += $lineheight;
	}

	if ( ($minleft+$tablewidth) > $viewportwidth ) {
		$top = $top + $maxheight + 20;
		$maxheight = 0;
		$minleft = 0;
	}
	$maxheight = max($maxheight, $pos);

	if($layout=='cookie'){
		$schema[$table]['pos'] = $tables[$table]['pos'];
	}else{
		$schema[$table]['pos'] = array( $minleft, $top );
	}
	$schema[$table]['w'] = $tablewidth;
	$minleft = $minleft+$tablewidth+20;
	#print_r($schema[$table]);
	#echo '('.$schema[$table]["pos"][0].' '.$schema[$table]["pos"][1].') ';

	foreach ($adminer->foreignKeys($table) as $val) {
		#$debugfk[] = $val;

		if (!$val['db'] || $val['db'] == DB) {
			#if ($table_pos[$table][1] || $table_pos[$val["table"]][1]) {
			#	$left = min($table_pos[$table][1], $table_pos[$val["table"]][1]);
			#}
			#$schema[$table]['references'][$val['table']][] = array($val['source'], $val['target']);
			$schema[$table]['references'][] = $val;
			$schema[$val['target']]['refcount']++;
			$referenced[$val['table']][$table][] = $val['target'];
		}
	}
	$tcounter++;
}

#echo '<pre>'; print_r($referenced);die();
$schemawidth= $viewportwidth;
$schemaheight= $top + $maxheight;

#echo '<pre>';print_r($debugfk);die();
#echo '<pre>';print_r($schema);die();

if (isset($_POST['lines']) && $_POST['lines']=='snake') {
	$lines='snake';
} else {
	$lines='';
}
?>
<form action="" method="post" id="layoutform">
<fieldset>
<legend>layout</legend>
<?php /* maybe replace with a select if there are too many possibilities. */ ?>
<button <?= $layout == 'name' ? 'class="isactive" ':'' ?>name="layout" value="name">table name</button>
<button <?= $layout == 'fieldcount' ? 'class="isactive" ':'' ?>name="layout" value="fieldcount">fieldcount</button>
<button <?= $layout == 'fieldcount_desc' ? 'class="isactive" ':'' ?>name="layout" value="fieldcount_desc">fieldcount desc</button>
<br>
<button <?= $layout == 'cookie' ? 'class="isactive" ':'' ?>name="layout" value="cookie" title="read positions from adminer_schema cookie">cookie coords</button>
<?php 
# TODO: Test if connection is mysql/mariadb and phpmyadmin's pma__ tables exists and accessible by current user
if(true): ?>
<button <?= $layout == 'pma' ? 'class="isactive" ':'' ?>name="layout" value="pma" title="read positions from phpmyadmin designer pma__ tables">pma coords (TODO)</button>
<?php endif; ?>
<button <?= $layout == 'spring' ? 'class="isactive" ':'' ?>name="layout" value="spring">spring (TODO)</button>
</fieldset>
</form>
<input name="showfields" value="nofields" form="layoutform" type="radio" id="s_shownofields"<?= $showfields=='nofields' ? ' checked="checked"' : '' ?>/>
<input name="showfields" value="pkfields" form="layoutform" type="radio" id="s_showpkfields"<?= $showfields=='pkfields' ? ' checked="checked"' : '' ?>/>
<input name="showfields" value="pkfkfields" form="layoutform" type="radio" id="s_showpkfkfields"<?= $showfields=='pkfkfields' ? ' checked="checked"' : '' ?>/>
<input name="showfields" value="indexfields" form="layoutform" type="radio" id="s_showindexfields"<?= $showfields=='indexfields' ? ' checked="checked"' : '' ?>/>
<input name="showfields" value="allfields" form="layoutform" type="radio" id="s_showallfields"<?= $showfields=='allfields' ? ' checked="checked"' : '' ?>/>

<input name="showtables" value="all" form="layoutform" type="radio" id="s_showalltables"<?= $showtables=='all' ? ' checked="checked"' : '' ?>/>
<input name="showtables" value="connected" form="layoutform" type="radio" id="s_showconntables"<?= $showtables=='connected' ? ' checked="checked"' : '' ?>/>

<input name="showrefpkt" form="layoutform" type="radio" value="0" id="s_showrefpkt0"<?= $showrefpkt==0 ? ' checked="checked"' : '' ?>/>
<input name="showrefpkt" form="layoutform" type="radio" value="1" id="s_showrefpkt1"<?= $showrefpkt==1 ? ' checked="checked"' : '' ?>/>
<input name="showrefdelete" form="layoutform" type="radio" value="0" id="s_showrefdelete0"<?= $showrefdelete==0 ? ' checked="checked"' : '' ?>/>
<input name="showrefdelete" form="layoutform" type="radio" value="1" id="s_showrefdelete1"<?= $showrefdelete==1 ? ' checked="checked"' : '' ?>/>
<input name="showrefupdate" form="layoutform" type="radio" value="0" id="s_showrefupdate0"<?= $showrefupdate==0 ? ' checked="checked"' : '' ?>/>
<input name="showrefupdate" form="layoutform" type="radio" value="1" id="s_showrefupdate1"<?= $showrefupdate==1 ? ' checked="checked"' : '' ?>/>

<input type="checkbox" form="layoutform" name="minimap" id="s_minimap"<?= $minimap ? ' checked="checked"':'' ?>/>
<input type="checkbox" form="layoutform" name="miniinfo" id="s_miniinfo"<?= $miniinfo ? ' checked="checked"':'' ?>/>
<input type="checkbox" form="layoutform" name="legend" id="s_legend"<?= $legend ? ' checked="checked"':'' ?>/>

<fieldset id="showfieldsgroup">
<legend>field filter</legend>
<label class="radiogroup" id="shownofieldslabel" for="s_shownofields">no</label>
<label class="radiogroup" id="showpkfieldslabel" for="s_showpkfields">pk</label>
<label class="radiogroup" id="showpkfkfieldslabel" for="s_showpkfkfields">pk+fk</label>
<label class="radiogroup" id="showindexfieldslabel" for="s_showindexfields">index (TODO)</label>
<label class="radiogroup" id="showallfieldslabel" for="s_showallfields">all</label>
</fieldset>

<fieldset id="showtablesgroup">
<legend>table filter</legend>
<label class="radiogroup" id="showalltableslabel" for="s_showalltables">all</label>
<label class="radiogroup" id="showconntableslabel" for="s_showconntables">connected (TODO)</label>
</fieldset>

<fieldset id="referencesgroup">
<legend>references</legend>
<select name="lines" form="layoutform">
<option value="table">pk table color</option>
<option value="snake"<?= ($lines=='snake') ? ' selected="selected"':''; ?>>constraintcolors</option>
</select><button form="layoutform">ok</button>
<br/>
<?php
# only show label for the constraintscolor line style mode
if($lines=='snake'): ?>
<label class="checkboxgroup" id="showrefpkt0label" for="s_showrefpkt0">pk table color</label>
<label class="checkboxgroup" id="showrefpkt1label" for="s_showrefpkt1">pk table color</label>
<?php endif; ?>

<label class="checkboxgroup" id="showrefdelete0label" for="s_showrefdelete0">ON DELETE</label>
<label class="checkboxgroup" id="showrefdelete1label" for="s_showrefdelete1">ON DELETE</label>

<label class="checkboxgroup" id="showrefupdate0label" for="s_showrefupdate0">ON UPDATE</label>
<label class="checkboxgroup" id="showrefupdate1label" for="s_showrefupdate1">ON UPDATE</label>
</fieldset>

<div id="widgettoggles">
<label for="s_minimap" title="-moz-element() works currently(2019) only in Firefox" id="showminimaplabel">minimap</label>
<label for="s_miniinfo" id="showminiinfolabel">info</label>
<label for="s_legend" id="showlegendlabel">legend</label>
</div>

<label style="display:inline-block;background:#ddd;border-radius:4px;">
<input id="schemazoom" form="layoutform" name="zoom" type="range" value="<?= (isset($_POST['zoom']) && $_POST['zoom']<=1.5 && $_POST['zoom']>=0.1) ? (float) $_POST['zoom']:'1' ?>" min="0.1" max="1.5" step="0.1"/>
<span id="schemazoomvalue"></span>
</label>
<div id="legend" style="position:fixed;background:#eee;box-shadow:0 0 20px 0 #000;max-width:620px;left:auto;margin-left:auto;margin-right:auto;z-index:100;">
	<div style="display:inline-block;vertical-align:top;width:300px;">
	<div style="text-align:center">ON DELETE</div>
	<div><span>CASCADE</span><svg class="d_cascade" height="10" width="100"><line class="del" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div><span>SET DEFAULT</span><svg class="d_setdefault" height="10" width="100"><line class="del" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div><span>SET NULL</span><svg class="d_setnull" height="10" width="100"><line class="del" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div><span>NO ACTION</span><svg class="d_noaction" height="10" width="100"><line class="del" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div><span>RESTRICT</span><svg class="d_restrict" height="10" width="100"><line class="del" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div><span>unknown</span><svg class="d_unknown" height="10" width="100"><line class="del" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	</div>
	<div style="display:inline-block;vertical-align:top;width:300px;">
	<div style="text-align:center">ON UPDATE</div>
	<div><span>CASCADE</span><svg class="u_cascade" height="10" width="100"><line class="upd" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div><span>SET DEFAULT</span><svg class="u_setdefault" height="10" width="100"><line class="upd" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div><span>SET NULL</span><svg class="u_setnull" height="10" width="100"><line class="upd" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div><span>NO ACTION</span><svg class="u_noaction" height="10" width="100"><line class="upd" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div><span>RESTRICT</span><svg class="u_restrict" height="10" width="100"><line class="upd" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div><span>unknown</span><svg class="u_unknown" height="10" width="100"><line class="upd" x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	</div>
	<label for="s_legend" title="hide constraints legend" id="hidelegendlabel">X</label>
</div>
<div id="minimap">
	<div id="whereami"></div>
	<div id="visible"><div id="dragme"></div></div>
	<label for="s_minimap" title="hide minimap" id="hideminimaplabel">X</label>
</div>
<div id="miniinfo">
	<div id="miniinfocontent"></div>
	<label for="s_miniinfo" title="hide miniinfo" id="hideminiinfolabel">X</label>
</div>
<style>
form#layoutform button {cursor:pointer;padding:0 6px;}
form#layoutform button.isactive {
	color:#fff;
	border-width:1px;
	border-radius:4px;
	background-color:#696;
	border-bottom-color:#9b9;
	border-right-color:#9b9;
	border-top-color:#363;
	border-left-color:#363;
}
/*
#content{width:max-content;}
*/
#layoutform{display:inline-block;}
#schema{
<?php 
	if(isset($_POST['zoom']) && $_POST['zoom']<=1.5 && $_POST['zoom']>=0.1){
		$zoom=(float)$_POST['zoom'];
		echo 'transform: scale('.$zoom.');'."\n";
		echo 'margin-bottom:'.( -(1 - $zoom) * $schemaheight).'px;'."\n";
	}
?>
	/* for debugging */
	border: 1px solid #600;
	/*background:#fef;*/
	box-sizing:border-box;

	margin-left:0;
	height:<?= ($schemaheight+2) ?>px;width:<?= $schemawidth ?>px;
	font-size:10px;
	transform-origin:top left;
}
#schema div a{ font-size:10px; line-height:10px; }
#schema div{ font-size:10px; line-height:10px; }
#schema svg {position:absolute;}

#legend{
	position:fixed;
	background:#eee;
	box-shadow:0 0 20px 0 #000;
	left:auto;
	bottom:80px;
	margin-left:auto;
	margin-right:auto;
	z-index:10;
}
#legend div span{width:100px;display:inline-block;text-align:right;}

svg line, svg path {fill:none; stroke:#000;}
<?php if ($lines=='snake') : ?>
/*
update/delete rules for the alternating line color, pkcolor as thick light

pkcolor:lightgreenlightgreenlightgreenlightegreen...
updnoactionblue|delcascaderedredred|updnoactionblue|delcascaderedredred|...
pkcolor:lightgreenlightgreenlightgreenlightegreen...
*/

svg line, svg path {stroke-width:2px;}
svg line.pkt, svg path.pkt { stroke-width:5; stroke-opacity:0.4; stroke-linecap:round;}
svg line.del, svg path.del { stroke-dasharray: 8 6; stroke-dashoffset: 0;}
svg line.upd, svg path.upd { stroke-dasharray: 4 10; stroke-dashoffset: 5;}
svg.d_cascade .del, svg.u_cascade .upd {stroke:#c00;}
svg.d_setnull .del, svg.u_setnull .upd {stroke:#00f;}
svg.d_setdefault .del, svg.u_setdefault .upd {stroke:#c0c;}
svg.d_restrict .del, svg.u_restrict .upd {stroke:#000;}
svg.d_noaction .del, svg.u_noaction .upd {stroke:#090;}
svg.d_unknown .del, svg.u_unknown .upd {stroke:#999;}


/* update/delete rules for the alternating line color, pkcolor as thick light */

<?php else: ?>
/* pkcolor as linecolor, dasharray for delete rules, thick light dasharray for update rules */
svg line.upd, svg path.upd {stroke-linecap: round;}
svg.d_cascade line.del, svg.d_cascade path.del {}
svg.d_setdefault line.del, svg.d_setdefault path.del {stroke-dasharray: 2 8 14 8;}
svg.d_setnull line.del, svg.d_setnull path.del {stroke-dasharray: 16 16;}
svg.d_noaction line.del, svg.d_noaction path.del {stroke-dasharray: 2 6;}
svg.d_restrict line.del, svg.d_restrict path.del {stroke-dasharray: 1 8; stroke-width:6;}
svg.d_unknown line.del, svg.u_restrict path.del {stroke-dasharray: 2; stroke-width:1;}

svg.u_cascade line.upd, svg.u_cascade path.upd {stroke-width:6;stroke-opacity:0.2;}
svg.u_setdefault line.upd, svg.u_setdefault path.upd {stroke-width:6;stroke-opacity:0.2; stroke-dasharray: 6 4 18 4; stroke-linecap:butt;}
svg.u_setnull line.upd, svg.u_setnull path.upd   {stroke-width:6;stroke-opacity:0.2; stroke-dasharray: 16 12 4 0;}
svg.u_noaction line.upd, svg.u_noaction path.upd {stroke-width:6;stroke-opacity:0.2; stroke-dasharray: 1 7;}
svg.u_restrict line.upd, svg.u_restrict path.upd {stroke-width:11;stroke-opacity:0.4;
	stroke-dasharray: 2 6 1 0;
	stroke-linecap:butt;
	animation: strokeanim 2s infinite linear;
}
svg.u_unknown line.upd, svg.u_restrict path.upd  {stroke-width:6;stroke-opacity:0.2; stroke-dasharray: 2;}
<?php endif; ?>

#schema .table {
	/*background-color:#ddd;*/
	padding:0;
	font-family:<?= ($hasfont && function_exists('imagettfbbox')) ? 'sans-serif':'monospace'; ?>;
	box-sizing:border-box;
}
.table div {background-color:#ccc;}
.table a {color:#009;font-weight:bold;padding-left:1px;}
.table span{display:block;line-height:<?= $lineheight ?>px;
	/* maybe a bit faster without opacity in web browsers with many svg lines. Na, thats not the bottleneck .. */
	/*background:#eee;*/
	background:rgba(220,220,220,0.95);
	padding-left:1px;padding-right:1px;
}
.table span.pk {background-color:#ff6;}
.table span.pk.fk {background-color:#ff6;
	/* the color stripes idea reduces readability of field name */
	/*background-image:repeating-linear-gradient(135deg, #ff0,  #ff0 4px, #cff 7px, #cff 10px);*/
	/*background-image:linear-gradient(#fff, #fff 5%, #fc0 5%, #cff 50%, #fc0 95%, #fff 95%, #fff 100%);*/
	background-image:linear-gradient(to right, #ff6, #cff);
}
.table span.fk { background-color:#cff; }
input[name=showfields] { display:none; }
input[name=showtables] { display:none; }
#s_shownofields:checked ~ #schema .table span { display:none; }
#s_showpkfields:checked ~ #schema .table span { display:none; }
#s_showpkfields:checked ~ #schema .table span.pk { display:block; }
#s_showpkfkfields:checked ~ #schema .table span { visibility:hidden; /* temp hack so svg lines still align to pk fk field */ }
#s_showpkfkfields:checked ~ #schema .table span.pk { display:block;visibility:visible;/* temp hack */ }
#s_showpkfkfields:checked ~ #schema .table span.fk { display:block;visibility:visible;/* temp hack */ }

input[name=showrefpkt],
input[name=showrefdelete],
input[name=showrefupdate] {
	display:none;
}

#schema svg line.pkt, #schema svg path.pkt,
#schema svg line.del, #schema svg path.del,
#schema svg line.upd, #schema svg path.upd {
	display:none;
}
#s_showrefpkt1:checked    ~ #schema svg line.pkt, #s_showrefpkt1:checked    ~ #schema svg path.pkt { display:block; }
#s_showrefdelete1:checked ~ #schema svg line.del, #s_showrefdelete1:checked ~ #schema svg path.del { display:block; }
#s_showrefupdate1:checked ~ #schema svg line.upd, #s_showrefupdate1:checked ~ #schema svg path.upd { display:block; }

label.radiogroup, label.checkboxgroup {
	cursor:pointer;
	display:inline-block;
	background:#eee;
	border:1px solid #ccc;
	padding:0 6px;
	border-radius:4px;
}
h2{margin-bottom:0;}
div#widgettoggles{
	display:inline-block;
	max-width:150px;
	vertical-align:top;
}
fieldset, fieldset#showtablesgroup, fieldset#showfieldsgroup {
	background:#ddd;
	border-radius:4px;
	border:none;
	margin:0.5em 0;
	padding:0.5em;
}
fieldset legend{
	background:#ddd;
	border-radius:4px;
	padding-left:0.5em;
	padding-right:0.5em;
	margin-left:-0.5em; /* see fieldset padding-left */
}
#s_showalltables:checked ~ fieldset #showalltableslabel,
#s_showconntables:checked ~ fieldset #showconntableslabel,
#s_shownofields:checked ~ fieldset #shownofieldslabel,
#s_showpkfields:checked ~ fieldset #showpkfieldslabel,
#s_showpkfkfields:checked ~ fieldset #showpkfkfieldslabel,
#s_showindexfields:checked ~ fieldset #showindexfieldslabel,
#s_showallfields:checked ~ fieldset #showallfieldslabel,
#s_showrefpkt1:checked ~ fieldset #showrefpkt0label,
#s_showrefdelete1:checked ~ fieldset #showrefdelete0label,
#s_showrefupdate1:checked ~ fieldset #showrefupdate0label,
#s_minimap:checked ~ div #showminimaplabel,
#s_miniinfo:checked ~ div #showminiinfolabel,
#s_legend:checked ~ div #showlegendlabel {
	background-color:#696;
	color:#fff;
	border-bottom-color:#9b9;
	border-right-color:#9b9;
	border-top-color:#363;
	border-left-color:#363;
}

#s_showrefpkt0:checked ~ fieldset #showrefpkt0label,
#s_showrefpkt1:checked ~ fieldset #showrefpkt1label,
#s_showrefdelete0:checked ~ fieldset #showrefdelete0label,
#s_showrefdelete1:checked ~ fieldset #showrefdelete1label,
#s_showrefupdate0:checked ~ fieldset #showrefupdate0label,
#s_showrefupdate1:checked ~ fieldset #showrefupdate1label {
	display:none;
}

#minimap{
<?php
	$minimapmax = 40000;
	$minimapheight = sqrt($minimapmax*$schemaheight/$schemawidth);
	$minimapwidth  = sqrt($minimapmax*$schemawidth/$schemaheight);
?>
	width:<?= $minimapwidth ?>px;
	height:<?= $minimapheight ?>px;

	z-index:100;
	bottom:20px;
	right:0px;
	position:fixed;
	background-repeat: no-repeat;
	background-color:rgba(255,255,255,0.9);
	/*background: -moz-element(#schema) center / contain; */
	background-position: left top;
	background-repeat: no-repeat;
	background-size:100%;
	background-image: -moz-element(#schema);
	border:1px solid #666;
	box-shadow:0 0 20px #999;
}
/* no hit area gap between checkbox and label */
input[type=checkbox]{margin-right:0;cursor:pointer;border:4px solid #999;}
#minimap, #miniinfo, #legend {display:none;}
#s_minimap, #s_miniinfo, #s_legend {display:none;}

#s_minimap:checked ~ #minimap {display:block;}
#s_legend:checked ~ #legend {display:block;}
#s_miniinfo:checked ~ #miniinfo {display:block;}

#showminimaplabel, #showminiinfolabel, #showlegendlabel {
	cursor:pointer;
	background:#ddd;
	padding:0 10px;
	margin:4px 0;
	border-radius:3px;
	display:block;
}
#hideminimaplabel, #hideminiinfolabel, #hidelegendlabel {
	cursor:pointer;
	background:#999;
	padding:0 10px 0 10px;
	border-top-left-radius:3px;
	border-top-right-radius:3px;
	z-index:10;
	position:absolute;
	top:-20px;
	right:0;
	height:19px;
}

#whereami{
	border:1px solid rgba(255,0,0,0.6);
	width:2px;
	height:2px;
	box-sizing:border-box;
	position:absolute;
}
#visible{
	border: 1px solid rgba(100,100,100,0.4);
	width:0;
	height:0;
	box-sizing:content-box;
	position:absolute;
}
#visible #dragme {
	width:100%;
	height:100%;
	cursor:move;
}
#miniinfo{
	position:fixed;
	bottom:20px;
	z-index:10;
	vertical-align:bottom;
	width:320px;
	border: 1px solid #ccc;
	background-color:#fff;
	box-shadow:0 0 20px #999;
}
#miniinfocontent{overflow:auto;word-wrap:anywhere;}
</style>
<script<?php echo nonce(); ?>>
var schemawidth=<?= $schemawidth ?>;
var schemaheight=<?= $schemaheight ?>;
var tablePos = {<?php echo implode(",", $table_pos_js) . "\n"; ?>};
var em=1; // I use px, but do not want change existing adminer scripts..
document.addEventListener('DOMContentLoaded', function () {
	/* document.getElementById('schema').addEventListener('mousemove', updateMinimap); */
	document.getElementById('visible').addEventListener('click', dragMinimap);
	window.addEventListener('resize', updateMinimap);
	window.addEventListener('scroll', updateMinimap);
	document.getElementById('schemazoom').addEventListener('change', setzoom);
	/* mmh, will it recalculate the right sizes when after setzoom the boundingClientRect() values changed? Or is it race condition? */
	document.getElementById('schemazoom').addEventListener('change', updateMinimap);

	qs('#schema').onselectstart = function () { return false; };
	document.onmousemove = schemaMousemove;
	document.onmouseup = partialArg(schemaMouseup, '<?php echo js_escape(DB); ?>');

	setzoom();
	updateMinimap(this);
});
function dragMinimap(event){
	console.log(event.clientX);
	console.log(event.clientY);
	schema=document.getElementById('schema').getBoundingClientRect();
	minimap=document.getElementById('minimap').getBoundingClientRect();
	visible=document.getElementById('visible').getBoundingClientRect();
	/*
	console.log('schema:');
	console.log(schema);
	console.log('minimap:');
	console.log(minimap);
	*/
	sx=(event.clientX-minimap.left)/minimap.width;
	sy=(event.clientY-minimap.top)/minimap.height;
	/*
	console.log('sx:'+sx +' sy:'+sy);
	*/
	window.scrollTo(sx*schema.width,sy*schema.height);
}
function setzoom(){
	zoom=document.getElementById('schemazoom').value;
	console.log(zoom);
	document.getElementById('schema').style['transform']='scale('+zoom+')';
	// a trick to adjust the height of #schema element for browser window layout
	//if(schemazoom.value<1){
		document.getElementById('schema').style['margin-bottom']= - (1-schemazoom.value) * document.getElementById('schema').offsetHeight + 'px';
	//}
	// sometimes firefox calculated ugly 110.000000001%, so lets round it..
	document.getElementById('schemazoomvalue').innerHTML = Math.round(zoom*100) +'%';
}
/*
taken from https://stackoverflow.com/questions/5639346/what-is-the-shortest-function-for-reading-a-cookie-by-name-in-javascript
*/
function getCookieValue(a) {
    var b = document.cookie.match('(^|[^;]+)\\s*' + a + '\\s*=\\s*([^;]+)');
    return b ? b.pop() : '';
}
function updateMinimap(event) {
	schema=document.getElementById('schema').getBoundingClientRect();
	minimap=document.getElementById('minimap').getBoundingClientRect();
	// XXX TODO: #minimap background-image: -moz-element does not get a fullsize image copy of #schema if the #schema has transform:scale() >1
	// (maybe optimization of firefox to not render invisible areas?)
	// in theory, 'background-size:contain;' should always do the job, right?  but...
	document.getElementById('minimap').style['background-size']=(100/schemazoom.value)+'%';

	/* show coords cookie of current schema */
	var coords=getCookieValue('adminer_schema-' + '<?= DB ?>');
	//console.log(coords);
	/* due bad adminer_schema cookie serialization */
	//var coords2=coords.match(/([^%3A]+)%3A([\-0-9.]+)x([\-0-9.]+)(_|$)/g);
	//console.log(coords2);

	document.getElementById('miniinfocontent').innerHTML= 'schema:' + schema.width + ' x ' + schema.height
		/* + '<br>mouse:' + event.clientX + ' x ' + event.clientY */
		+ '<br/>window:' + window.innerWidth + ' x ' + window.innerHeight;

		// + '<br/>' + coords;

	if(event.clientX-schema.left >0 ){
		document.getElementById('whereami').style['left'] = minimap.width * (event.clientX-schema.left) / schema.width + 'px';
	}else{
		document.getElementById('whereami').style['left'] = 0;
	}

	if(event.clientY-schema.top >0){
		document.getElementById('whereami').style['top'] = minimap.height * (event.clientY-schema.top) / schema.height + 'px';
	}else{
		document.getElementById('whereami').style['top'] = 0;
	}
	if(schema.top<0){
		if((schema.height+schema.top) < window.innerHeight){
			document.getElementById('visible').style['height'] = minimap.height/schema.height * (schema.height+schema.top) + 'px';
		}else{
			document.getElementById('visible').style['height'] = minimap.height/schema.height * window.innerHeight + 'px';
		}
		document.getElementById('visible').style['border-top-width']=minimap.height/schema.height * -schema.top + 'px';
		document.getElementById('visible').style['top']=0+'px';
		if(schema.height + schema.top - window.innerHeight > 0){
			document.getElementById('visible').style['border-bottom-width']= minimap.height/schema.height * (schema.height + schema.top - window.innerHeight) + 'px';
		}else{
			document.getElementById('visible').style['border-bottom-width']= 0;
		}
	}else{
		if(schema.height < (window.innerHeight-schema.top)){
			document.getElementById('visible').style['height'] = minimap.height-2 + 'px';
			document.getElementById('visible').style['border-bottom-width']= 0;
		} else{
			document.getElementById('visible').style['height'] = minimap.height/schema.height * (window.innerHeight-schema.top) + 'px';
			document.getElementById('visible').style['border-bottom-width']= minimap.height/schema.height * (schema.height + schema.top - window.innerHeight) + 'px';
		}
		document.getElementById('visible').style['top']=0;
		document.getElementById('visible').style['border-top-width'] = 0;
	}

	if(schema.left<0){
		document.getElementById('visible').style['width'] = minimap.width/schema.width * window.innerWidth + 'px';
		document.getElementById('visible').style['left']=0;
		document.getElementById('visible').style['border-left-width'] = minimap.width/schema.width * -schema.left + 'px';
		if( schema.width + schema.left - window.innerWidth > 0){
			document.getElementById('visible').style['border-right-width']= minimap.width/schema.width * (schema.width + schema.left - window.innerWidth) + 'px';
		}else{
			document.getElementById('visible').style['border-right-width']= 0;
		}

	}else{

		document.getElementById('visible').style['width'] = minimap.width/schema.width * (window.innerWidth-schema.left) + 'px';
		document.getElementById('visible').style['left'] = 0;
		document.getElementById('visible').style['border-left-width'] = 0;
		if(schema.width + schema.left - window.innerWidth > 0){
			document.getElementById('visible').style['border-right-width']= minimap.width/schema.width * (schema.width + schema.left - window.innerWidth) + 'px';
		}else{
			document.getElementById('visible').style['border-right-width']= 0;
		}
	}
}
</script>
<div id="schema">
<?php

#echo '<pre>';print_r($schema);
$i=0;
$lineyoffset=$lineheight/2; # lineheight of fields
foreach ($schema as $name => $table) {
	#foreach ((array) $table["references"] as $target_name => $refs) {
	foreach ((array) $table['references'] as $refs) {
		#echo '<pre>'.$name.' references ';print_r($refs);echo '</pre>';die();
		$j=0;
		switch($refs['on_delete']){
			case 'CASCADE': $cdel='d_cascade'; break;
			case 'SET NULL': $cdel='d_setnull'; break;
			case 'SET DEFAULT': $cdel='d_setdefault'; break;
			case 'NO ACTION': $cdel='d_noaction'; break;
			case 'RESTRICT': $cdel='d_restrict'; break;
			default: $cdel='d_unknown';

		}
		switch($refs['on_update']){
			case 'CASCADE': $cupd='u_cascade'; break;
			case 'SET NULL': $cupd='u_setnull'; break;
			case 'SET DEFAULT': $cupd='u_setdefault'; break;
			case 'NO ACTION': $cupd='u_noaction'; break;
			case 'RESTRICT': $cupd='u_restrict'; break;
			default: $cupd='u_unknown';

		}
		foreach ($refs['source'] as $ref) {
				$fktable=$table;
				$fkfield=$table['fields'][$ref];
				# store if fields is a foreign key also in the field info of $schema for css class later
				$schema[$name]['fields'][$ref]['fk']=1;
				$pktable=$refs['table'];
				# TODO calculate proper color value of referenced table
				$pktablecolor=$linecolors[$schema[$pktable]['refcolor']];
				$pkfield=$refs['target'][$j];
				#echo '<pre>';print_r($schema[$pktable]);die();
 				#echo '<pre>';print_r($fkfield);die();
 				#echo '<pre>';print_r($pkfield);die();

				$x1 = $table["pos"][0];
				$w1 = $table['w'];
				$x2 = $schema[$pktable]['pos'][0];
				$w2 = $schema[$pktable]['w'];
				$min_x = min($x1, $x2);
				$max_x = max($x1+$w1, $x2+$w2);
				$dx=abs($x1-$x2); # when tables quite vertical aligned

				$y1 = $table['pos'][1] + $fkfield['pos'];
				$y2 = $schema[$pktable]['pos'][1] + $schema[$pktable]['fields'][$pkfield]['pos'];
				$min_y = min($y1, $y2);
				$max_y = max($y1, $y2)+$lineheight;
				$h = abs($y1-$y2)+$lineheight;
				$vertcurve=0;
				if ($dx < 6){
					if ($x1>$min_x) {
						$sx1=$x1-$min_x;
						$sx2 = 0;
					} elseif ($x2>$min_x) {
						$sx1 = 0;
						$sx2=$x2-$min_x;
					} else {
						$sx1 = 0;
						$sx2 = 0;
					}
					$min_x = $min_x-10;
					$vertcurve=1;
					$dx=10+$dx;
				} elseif ($x1>$x2){
					if ($x1 > $x2+$w2 ){
						$dx=$x1-$x2-$w2;
						$min_x = $x2+$w2;
						$sx1 = $dx;
						$sx2 = 0;
					} else {
						$dx = $x1-$x2;
						$sx1 = $dx;
						$sx2 = 0;
					}
				} else {
					if ($x2 > $x1+$w1){
						$dx = $x2-$x1-$w1;
						$min_x = $x1+$w1;
						$sx1 = 0;
						$sx2 = $dx;
					} else {
						$dx = $x2-$x1;
						$sx1 = 0;
						$sx2 = $dx;
					}
				}
				if ($y1>$y2){
					$sy1 = $h-$lineyoffset;
					$sy2 = 1+$lineyoffset;
				} elseif ($y1==$y2){
					$h = $lineheight;
					$sy1 = $lineyoffset;
					$sy2 = $lineyoffset;
				} else {
					$sy1 = 1+$lineyoffset;
					$sy2 = $h-$lineyoffset;
				}
			echo '<svg class="'.$cdel.' '.$cupd.'" id="ref-'.$name.'.'.$ref.':'.$pktable.'.'.$pkfield.'" height="'.$h.'" width="'.$dx.'" style="top:'.$min_y.'px; left:'.$min_x.'px">';
			if($vertcurve){
				# TODO: start/end docking points (horizontal line a few (4 maybe) pixel long)
				# TODO: pk/fk arrows/dots in correct direction
				if ($lines=='snake'){
					echo '<path class="pkt" d="M'.$dx.','.$lineyoffset.' c-'.$dx.',0 -'.$dx.','.($h-$lineyoffset).' 0,'.($h-2*$lineyoffset).'" style="stroke:'.$pktablecolor.'"/>';
				}
				echo '<path class="upd" d="M'.$dx.','.$lineyoffset.' c-'.$dx.',0 -'.$dx.','.($h-$lineyoffset).' 0,'.($h-2*$lineyoffset).'"'.($lines!='snake' ? ' style="stroke:'.$pktablecolor.'"' :'').'/>';
				echo '<path class="del" d="M'.$dx.','.$lineyoffset.' c-'.$dx.',0 -'.$dx.','.($h-$lineyoffset).' 0,'.($h-2*$lineyoffset).'"'.($lines!='snake' ? ' style="stroke:'.$pktablecolor.'"' :'').'/>';
			} else {
				# TODO: start/end docking points (horizontal line a few (4 maybe) pixel long)
				# TODO: pk/fk arrows/dots in correct direction
				if ($lines=='snake'){
					echo '<line class="pkt" x1="'.$sx1.'" y1="'.$sy1.'" x2="'.$sx2.'" y2="'.$sy2.'" style="stroke:'.$pktablecolor.'"/>';
				}
				echo '<line class="upd" x1="'.$sx1.'" y1="'.$sy1.'" x2="'.$sx2.'" y2="'.$sy2.'"'.($lines!='snake' ? ' style="stroke:'.$pktablecolor.'"' :'').'/>';
				echo '<line class="del" x1="'.$sx1.'" y1="'.$sy1.'" x2="'.$sx2.'" y2="'.$sy2.'"'.($lines!='snake' ? ' style="stroke:'.$pktablecolor.'"' :'').'/>';
			}
			echo '</svg>'."\n";

			$j++;
		}
	}
	$i++;
}

foreach ($schema as $name => $table) {
	# set table width too for exact svg line ends and collapsing columns does not autochange table width
	echo "\n<div class='table' id='".h(DB).".".h($name)."' style='left:".$table['pos'][0]."px;top:".$table['pos'][1]."px;width:".$table['w']."px'>";
	# we might put more info into that first row in future
	echo '<div>';
	echo '<a href="' . h(ME) . 'table=' . urlencode($name) . '">' . h($name) . "</a>";
	echo '</div>';
	echo script("qsl('div.table').onmousedown = schemaMousedown;");

	foreach ($table['fields'] as $field) {
		#if ($name=='tasks'){ echo '</div><pre>'; print_r($field); print_r($table);die(); }
		$class = '';
		if($field['primary']){
			$class='pk';
		}
		if($field['fk']==1){
			$class.=' fk';
		}
		$class.=' '.type_class($field["type"]);
		$class = trim($class);
		$val = '<span'.($class !='' ? ' class="'.$class.'"':''). ' title="' . h($field["full_type"] . ($field["null"] ? " NULL" : '')) . '">' . h($field["field"]) . '</span>';
		echo $val."\n";
	}
	echo "</div>";
}

$endtimeschema=microtime(true);
?>
</div>
<p class="links"><a href="<?php echo h(ME . "schema=" . urlencode($SCHEMA)); ?>" id="schema-link"><?php echo lang('Permanent link'); ?></a></p>
<input type="checkbox" id="s_querylog" name="showquerylog" form="layoutform"<?= $showquerylog ? ' checked="checked"' : '' ?>>
<label for="s_querylog" id="showqueryloglabel">Show Log</label>
<label for="s_querylog" id="hidequeryloglabel">Hide Log</label>
<style>
#s_querylog {display:none;}
#querylog {
	display:none;
	padding:0.5em;position:fixed;bottom:0;margin-left:auto;margin-right:auto;overflow:auto;height:50%;
	box-shadow: 0 0 10px #000;
	/*background-color:rgba(200,200,200,0.9);*/
	background-color:#ccc;
}
#querylog pre {margin-top:0.2em;}
#hidequeryloglabel{background:#999;padding:2px;display:none;position:fixed;bottom:0;left:0;border-radius:3px;z-index:10;}
#showqueryloglabel{background:#999;padding:2px;position:fixed;bottom:0;left:0;border-radius:3px;z-index:10;}
#s_querylog:checked ~ #querylog {display:block;}
#s_querylog:checked ~ #showqueryloglabel {display:none;}
#s_querylog:checked ~ #hidequeryloglabel {display:block;}

.v4{ background-color:transparent;}
.v3{ background-color:rgba(127,255,0,0.1);}
.v2{ background-color:rgba(255,255,0,0.2);}
.v1{ background-color:rgba(255,191,0,0.5);}
.v0{ background-color:rgba(255,127,0,0.5);}
.vbad{ background-color:rgba(255,0,0,0.5);}
</style>
<div id="querylog">
<pre>At least <?= count($GLOBALS['querylog']) ?> queries to load this page. (not all yet count)</pre>
<pre><?= count($tables) ?> tables</pre>
<pre><?= count($referenced) ?> referenced pk tables</pre>
<?php
$refcount=0;
foreach ($referenced as $t) {
  foreach ($t as $f) {
    $refcount++;
  }
}
?>
<pre><?= $refcount ?> references</pre>
<pre><?= round($endtimeschema-$starttimeschema, 6).' s for '.basename(__FILE__) ?></pre>
<?php
# $GLOBALS['querylog'] depends on where the queries are catches and logged (get_rows() for instance, but also others.
foreach ($GLOBALS['querylog'] as $q){
	if ($q[2]-$q[1] < 0.0001){
		$c='v4';
	} elseif ($q[2]-$q[1] < 0.001){
		$c='v3';
	} elseif ($q[2]-$q[1] < 0.01){
		$c='v2';
	} elseif ($q[2]-$q[1] < 0.1){
		$c='v1';
	} elseif ($q[2]-$q[1] < 1){
		$c='v0';
	} else{
		# all queries over >=1 second
		$c='vbad';
	}
	echo '<pre class="'.$c.'">'.round($q[2]-$q[1], 6).': '.htmlspecialchars($q[0]).'</pre>';
}
?>
</div>
