<?php
/*
* rewrite of schema visualisation by https://github.com/peterdd
*/

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
#$referenced = array(); // target_table => array(table => array(left => target_column))
#$lefts = array(); // float => bool

$tcounter=0;
$top=0;
$minleft=0;
$maxheight=0;
/**
* TODO: width calculate based on area required for the schema, needs sum area of each table to estimate a final appealing rectangle layout
* maybe get current viewport width of browser from request to detect rough orientation (portrait - landscape)
*/
$viewportwidth=1200; # ranges from 400(very few tables) to 4000(e.g. magento) or more
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

$t = 0;
foreach ($tables as $table => $table_status) {
	$f = 0;
	foreach (fields($table) as $name => $field) {
		$f++;
	}
	$tables[$table]['fcount'] = $f;
	$tables[$table]['refcount'] = 0;
	$tables[$table]['refcolor'] = $t % $ccount;
	$t++;
}

$fcount = array_column($tables, 'fcount');
if(isset($_POST['sort']) && $_POST['sort']=='fieldcount'){
	array_multisort($fcount, SORT_ASC, $tables);
}
if(isset($_POST['sort']) && $_POST['sort']=='fieldcount_desc'){
	array_multisort($fcount, SORT_DESC, $tables);
}
#echo print_r($tables);
$monowidth = 6;
foreach ($tables as $table => $table_status) {
	if (is_view($table_status)) {
		continue;
	}

	$tablewidth=40;
	if ($hasfontbold && function_exists('imagettfbbox')){
		$b = imagettfbbox(8, 0, $fontbold, $table);
		$tablewidth = max([$b[0],$b[2],$b[4],$b[6]]) - min([$b[0],$b[2],$$b[4],$b[6]]);
	} else {
		$tablewidth = strlen($table)*$monowidth;
	}

	$schema[$table]["fields"] = array();
	$schema[$table]["refcolor"]= $tables[$table]['refcolor'];
	$pos = 10;
	foreach (fields($table) as $name => $field) {
		$pos += 11;
		$field["pos"] = $pos;
		$schema[$table]["fields"][$name] = $field;
		if ($hasfont && function_exists('imagettfbbox')){
			$b = imagettfbbox(8, 0, $font, $field['field']);
			$fieldwidth = max([$b[0],$b[2],$b[4],$b[6]]) - min([$b[0],$b[2],$$b[4],$b[6]]);
		} else {
			$fieldwidth = strlen($field['field'])*$monowidth;
		}
		$tablewidth = max($tablewidth, $fieldwidth);
	}

	if ( ($minleft+$tablewidth) > $viewportwidth ) {
		$top = $top + $maxheight + 20;
		$maxheight = 0;
		$minleft = 0;
	}
	$maxheight = max($maxheight, $pos);

	$schema[$table]['pos'] = array( $minleft, $top );
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
			#$referenced[$val['table']][$table][] = $val['target'];
		}
	}
	$tcounter++;
}
$schemawidth=$viewportwidth;
$schemaheight=$top + $maxheight;

#echo '<pre>';print_r($debugfk);die();
#echo '<pre>';print_r($schema);die();

$sort = false;
if (isset($_POST['sort'])){
	if ($_POST['sort'] === 'name'){
		$sort = 'name';
	} elseif ($_POST['sort'] === 'fieldcount'){
		$sort = 'fieldcount';
	} elseif ($_POST['sort'] === 'fieldcount_desc'){
		$sort = 'fieldcount_desc';
	} elseif ($_POST['sort'] === 'cookie'){
		$sort = 'cookie';
	} elseif ($_POST['sort'] === 'spring'){
		$sort = 'springw';
	}
}
?>
<form action="" method="post" id="sortform">
<fieldset>
<legend>layout</legend>
<button <?= $sort == 'name' ? 'disabled="disabled" ':'' ?>name="sort" value="name">table name</button>
<button <?= $sort == 'fieldcount' ? 'disabled="disabled" ':'' ?>name="sort" value="fieldcount">fieldcount</button>
<button <?= $sort == 'fieldcount_desc' ? 'disabled="disabled" ':'' ?>name="sort" value="fieldcount_desc">fieldcount desc</button>
<button <?= $sort == 'cookie' ? 'disabled="disabled" ':'' ?>name="sort" value="cookie" title="read positions from adminer_schema cookie">cookie coords (TODO)</button>
<?php 
# TODO: Test if connection is mysql/mariadb and phpmyadmin's pma__ tables exists and accessible by current user
if(true): ?>
<button <?= $sort == 'pma' ? 'disabled="disabled" ':'' ?>name="sort" value="pma" title="read positions from phpmyadmin's designer pma__ tables">pma coords (TODO)</button>
<?php endif; ?>
<button <?= $sort == 'spring' ? 'disabled="disabled" ':'' ?>name="sort" value="spring">spring (TODO)</button>
</fieldset>
</form>
<input name="showfields" type="radio" id="s_shownofields"/>
<input name="showfields" type="radio" id="s_showpkfields"/>
<input name="showfields" type="radio" id="s_showpkfkfields"/>
<input name="showfields" type="radio" id="s_showindexfields"/>
<input name="showfields" type="radio" id="s_showallfields"/>
<input name="showtables" type="radio" id="s_showalltables"/>
<input name="showtables" type="radio" id="s_showconntables"/>
<fieldset id="showfieldsgroup">
<legend>field filter</legend>
<label class="radiogroup" id="shownofieldslabel" for="s_shownofields">no</label>
<label class="radiogroup" id="showpkfieldslabel" for="s_showpkfields">pk</label>
<label class="radiogroup" id="showpkfkfieldslabel" for="s_showpkfkfields">pk+fk</label>
<label class="radiogroup" id="showindexfieldslabel" for="s_showindexfields">index</label>
<label class="radiogroup" id="showallfieldslabel" for="s_showallfields">all</label>
</fieldset>
<fieldset id="showtablesgroup">
<legend>table filter</legend>
<label class="radiogroup" id="showalltableslabel" for="s_showalltables">all</label>
<label class="radiogroup" id="showconntableslabel" for="s_showconntables">connected (TODO)</label>
</fieldset>
<input type="checkbox" id="s_minimap" checked="checked"/>
<label for="s_minimap" title="-moz-element() works currently(2019) only in Firefox" id="showminimaplabel">minimap</label>
<input type="checkbox" id="s_miniinfo"/>
<label for="s_miniinfo" id="showminiinfolabel">info</label>
<input type="checkbox" id="s_legend"/>
<label for="s_legend" id="showlegendlabel">legend</label>
<div id="legend" style="position:fixed;background:#ccc;box-shadow:0 0 20px #000;max-width:620px;margin-left:auto;margin-right:auto;z-index:100;">
	<div style="display:inline-block;vertical-align:top;width:300px;">
	<div style="text-align:center">ON DELETE</div>
	<div>CASCADE <svg class="d_cascade" height="10" width="100"><line x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div>SET DEFAULT <svg class="d_setdefault" height="10" width="100"><line x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div>SET NULL <svg class="d_null" height="10" width="100"><line x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div>RESTRICT <svg class="d_restrict" height="10" width="100"><line x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div>unknown<svg class="d_unknown" height="10" width="100"><line x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	</div>
	<div style="display:inline-block;vertical-align:top;width:300px;">
	<div style="text-align:center">ON UPDATE</div>
	<div>CASCADE <svg class="u_cascade" height="10" width="100"><line x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div>SET DEFAULT <svg class="u_setdefault" height="10" width="100"><line x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div>SET NULL <svg class="u_null" height="10" width="100"><line x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div>RESTRICT <svg class="u_restrict" height="10" width="100"><line x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	<div>unknown<svg class="u_unknown" height="10" width="100"><line x1="10" y1="4" x2="90" y2="4"></line></svg></div>
	</div>
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
form#sortform button {cursor:pointer;padding:0 6px;}
form#sortform button:disabled {
	color:#fff;
	border-width:1px;
	border-radius:4px;
	background-color:#696;
	border-bottom-color:#9b9;
	border-right-color:#9b9;
	border-top-color:#363;
	border-left-color:#363;
}
#content{width:max-content;}
#sortform{display:inline-block;}
#schema{
	background:#fff;
	margin-left:0;
	height:<?= $schemaheight ?>px;width:<?= $schemawidth ?>px;
	font-size:10px;
	transform-origin:top left;
}
#schema svg {position:absolute;}

svg line, svg path {fill:none; stroke-width:1; stroke:rgba(0,0,0,0.5);}
svg.d_cascade line, svg.d_cascade path {}
svg.d_setnull line, svg.d_setnull path {stroke-dasharray: 0;}
svg.d_setdefault line, svg.d_setdefault path {stroke-dasharray: 0;}
svg.d_noaction line, svg.d_noaction path {stroke-dasharray: 0;}
svg.d_restrict line, svg.d_restrict path {stroke-dasharray: 0;}

svg.u_cascade line, svg.u_cascade path {stroke-width:1;}
svg.u_setnull line, svg.u_setnull path {stroke-width:1;}
svg.u_setdefault line, svg.u_setdefault path {stroke-width:1;}
svg.u_noaction line, svg.u_noaction path {stroke-width:1;}
svg.u_restrict line, svg.u_restrict path {stroke-width:1;}

#schema .table {
	/*background-color:#ddd;*/
	padding:0;
	font-family:<?= ($hasfont && function_exists('imagettfbbox')) ? 'sans-serif':'monospace'; ?>
}
.table div {background-color:#ccc;}
.table a {color:#009;font-weight:bold;}
.table span{display:block;line-height:11px; background:rgba(220,220,220,0.95);padding-left:2px;padding-right:2px;}
.table span.pk {background-color:#ff6;}
.table span.pk.fk {background-color:#ff6;
	/* the color stripes idea reduces readability of field name */
	/*background-image:repeating-linear-gradient(135deg, #ff0,  #ff0 4px, #cff 7px, #cff 10px);*/
	/*background-image:linear-gradient(#fff, #fff 5%, #fc0 5%, #cff 50%, #fc0 95%, #fff 95%, #fff 100%);*/
	background-image:linear-gradient(to right, #ff6, #cff);
}
.table span.fk {background-color:#cff;}
input[name=showfields]{display:none;}
input[name=showtables]{display:none;}
#s_shownofields:checked ~ #schema .table span{display:none; }
#s_showpkfields:checked ~ #schema .table span {display:none; }
#s_showpkfields:checked ~ #schema .table span.pk {display:block; }
#s_showpkfkfields:checked ~ #schema .table span {visibility:hidden; /* temp hack so svg lines still align to pk fk field */}
#s_showpkfkfields:checked ~ #schema .table span.pk {display:block;visibility:visible;/* temp hack */ }
#s_showpkfkfields:checked ~ #schema .table span.fk {display:block;visibility:visible;/* temp hack */ }
label.radiogroup {
	cursor:pointer;
	display:inline-block;
	background:#eee;
	border:1px solid #ccc;
	padding:0 6px;
	border-radius:4px;
}
h2{margin-bottom:0;}
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
#s_showalltables:checked  ~ fieldset #showalltableslabel,
#s_showconntables:checked  ~ fieldset #showconntableslabel,
#s_shownofields:checked  ~ fieldset #shownofieldslabel,
#s_showpkfields:checked  ~ fieldset #showpkfieldslabel,
#s_showpkfkfields:checked  ~ fieldset #showpkfkfieldslabel,
#s_showallfields:checked ~ fieldset #showallfieldslabel {
	background-color:#696;
	color:#fff;
	border-bottom-color:#9b9;
	border-right-color:#9b9;
	border-top-color:#363;
	border-left-color:#363;
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
	background: -moz-element(#schema) center / contain;
	border:1px solid #666;
	box-shadow:0 0 20px #999;
}
#minimap{display:none;}
/*#s_minimap{display:none;}*/
#hideminimaplabel, #showminimaplabel{cursor:pointer;background:#ddd;padding:0;border-radius:3px;z-index:10;}
#hideminimaplabel{display:none;position:absolute;top:-1em;right:0;}
#showminimaplabel{display:inline-block;}
#s_minimap:checked ~ #minimap {display:block;}
/*#s_minimap:checked ~ #showminimaplabel {display:none;}*/
#s_minimap:checked ~ #minimap #hideminimaplabel {display:inline-block;}
#legend {display:none;}
#s_legend:checked ~ #legend {display:block;}

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
	display:none;
	box-shadow:0 0 20px #999;
}
#miniinfocontent{overflow:auto;word-wrap:anywhere;}
/*#s_miniinfo{display:none;}*/
#hideminiinfolabel, #showminiinfolabel{cursor:pointer;background:#ddd;padding:0;border-radius:3px;}
#hideminiinfolabel{display:none;position:absolute;right:0;top:-1em;}
#showminiinfolabel{display:inline-block;}
#s_miniinfo:checked ~ #miniinfo {display:block;}
/*#s_miniinfo:checked ~ #showminiinfolabel {display:none;}*/
#s_miniinfo:checked ~ #miniinfo #hideminiinfolabel {display:inline-block;}
</style>
<script<?php echo nonce(); ?>>
var tablePos = {<?php echo implode(",", $table_pos_js) . "\n"; ?>};
var em=14.4; // I use px, but do not want change existing adminer scripts..
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
	document.getElementById('schemazoomvalue').innerHTML= zoom*100 +'%';
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
<label style="display:inline-block;background:#ddd;border-radius:4px;">
<input id="schemazoom" type="range" value='1' min="0.1" max="1.5" step="0.1"/>
<span id="schemazoomvalue"></span>
</label>
<div id="schema">
<?php

#echo '<pre>';print_r($schema);
$i=0;
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
				$max_y = max($y1, $y2);
				$h = abs($y1-$y2);
				if ($dx < 6){
					$dx = 20;
					$min_x = $min_x-20;
					$sx1 = 0;
					$sx2 = 0;
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
					$sy1 = $h;
					$sy2 = 1;
				} elseif ($y1==$y2){
					$h = 4;
					$sy1 = 1;
					$sy2 = 1;
				} else {
					$sy1 = 1;
					$sy2 = $h;
				}
			echo '<svg class="'.$cdel.' '.$cupd.'" id="ref-'.$name.'.'.$ref.':'.$pktable.'.'.$pkfield.'" height="'.$h.'" width="'.$dx.'" style="top:'.$min_y.'px; left:'.$min_x.'px">';
			if($sx1==$sx2){
				#echo '<path d="M20,0 c-20,0 -20,'.$h.' 0,'.$h.'" style="stroke-width:7;stroke:'.$pktablecolor.';opacity:0.2"/>';
				echo '<path d="M20,0 c-20,0 -20,'.$h.' 0,'.$h.'" style="stroke-width:1;stroke:'.$pktablecolor.'"/>';
				#echo '<path d="M20,0 c-20,0 -20,'.$h.' 0,'.$h.'" style="stroke-dasharray:3,8;stroke-width:3;stroke:#fff"/>';
			} else {
				#echo '<line x1="'.$sx1.'" y1="'.$sy1.'" x2="'.$sx2.'" y2="'.$sy2.'" style="stroke-width:3;stroke:'.$pktablecolor.';opacity:0.2"/>';
				echo '<line x1="'.$sx1.'" y1="'.$sy1.'" x2="'.$sx2.'" y2="'.$sy2.'" style="stroke-width:1;stroke:'.$pktablecolor.'"/>';
				#echo '<line x1="'.$sx1.'" y1="'.$sy1.'" x2="'.$sx2.'" y2="'.$sy2.'" style="stroke-dasharray:1,8;stroke-width:3;stroke:#000"/>';
			}
			echo '</svg>';

			$j++;
		}
	}
	$i++;
}

foreach ($schema as $name => $table) {
	# set table width too for exact svg line ends and collapsing columns does not autochange table width
	echo "<div class='table' style='left:".$table['pos'][0]."px;top:".$table['pos'][1]."px;width:".$table['w']."px'>";
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
		$class = trim($class);
		$val = '<span'.($class !='' ? ' class="'.$class.'"':''). type_class($field["type"]) . ' title="' . h($field["full_type"] . ($field["null"] ? " NULL" : '')) . '">' . h($field["field"]) . '</span>';
		echo $val;
	}
	echo "</div>";
}

?>
</div>
<p class="links"><a href="<?php echo h(ME . "schema=" . urlencode($SCHEMA)); ?>" id="schema-link"><?php echo lang('Permanent link'); ?></a></p>
<input type="checkbox" id="s_querylog">
<label for="s_querylog" id="showqueryloglabel">Show Log</label>
<label for="s_querylog" id="hidequeryloglabel">Hide Log</label>
<style>
.v4{ background-color:transparent;}
.v3{ background-color:rgba(127,255,0,0.1);}
.v2{ background-color:rgba(255,255,0,0.2);}
.v1{ background-color:rgba(255,191,0,0.5);}
.v0{ background-color:rgba(255,127,0,0.5);}
.vbad{ background-color:rgba(255,0,0,0.5);}
</style>
<div id="querylog">
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
<style>
#s_querylog {display:none;}
#querylog {display:none;padding:0.5em;position:fixed;bottom:0;margin-left:auto;margin-right:auto;overflow:auto;height:50%;background-color:rgba(200,200,200,0.9);}
#querylog pre {margin-top:0.2em;}
#hidequeryloglabel{background:#999;padding:2px;display:none;position:fixed;bottom:0;left:0;border-radius:3px;z-index:10;}
#showqueryloglabel{background:#999;padding:2px;position:fixed;bottom:0;left:0;border-radius:3px;z-index:10;}
#s_querylog:checked ~ #querylog {display:block;}
#s_querylog:checked ~ #showqueryloglabel {display:none;}
#s_querylog:checked ~ #hidequeryloglabel {display:block;}
</style>
