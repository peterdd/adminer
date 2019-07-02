<?php
page_header(lang('Database schema'), "", array(), h(DB . ($_GET["ns"] ? ".$_GET[ns]" : "")));

# Arial-like free font
$font='/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf';
$fontbold='/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf';

$hasfont=file_exists($font);
$hasfontbold=file_exists($fontbold);

$table_pos = array();
$table_pos_js = array();
$SCHEMA = ($_GET["schema"] ? $_GET["schema"] : $_COOKIE["adminer_schema-" . str_replace(".", "_", DB)]); // $_COOKIE["adminer_schema"] was used before 3.2.0 //! ':' in table name
preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~', $SCHEMA, $matches, PREG_SET_ORDER);
foreach ($matches as $i => $match) {
	$table_pos[$match[1]] = array($match[2], $match[3]);
	$table_pos_js[] = "\n\t'" . js_escape($match[1]) . "': [ $match[2], $match[3] ]";
}

$schema = array(); // table => array("fields" => array(name => field), "pos" => array(top, left), "references" => array(table => array(left => array(source, target))))
$referenced = array(); // target_table => array(table => array(left => target_column))
$lefts = array(); // float => bool

$tcounter=0;
$top=0;
$minleft=0;
$maxheight=0;
/* TODO: width calculate based on area required for the schema, so needs so sum area of each table to calculate a square final layout */
$viewportwidth=1000; # in px
$tables=table_status('',true);
#echo print_r($tables);
foreach ($tables as $table => $table_status) {
	$f=0;
	foreach (fields($table) as $name => $field) {
		$f++;
	}
	$tables[$table]['fcount']=$f;
}

$fcount = array_column($tables, 'fcount');
if(isset($_POST['sort']) && $_POST['sort']=='fieldcount'){
	array_multisort($fcount, SORT_ASC, $tables);
}
if(isset($_POST['sort']) && $_POST['sort']=='fieldcount_desc'){
	array_multisort($fcount, SORT_DESC, $tables);
}
#echo print_r($tables);
$monowidth=6;
foreach ($tables  as $table => $table_status) {
	if (is_view($table_status)) {
		continue;
	}

	$tablewidth=0;
	if ($hasfontbold && function_exists('imagettfbbox')){
		$b = imagettfbbox(8, 0, $fontbold, $table);
		$tablewidth = max([$b[0],$b[2],$b[4],$b[6]]) - min([$b[0],$b[2],$$b[4],$b[6]]);
	} else {
		$tablewidth = strlen($table)*$monowidth;
	}

	$schema[$table]["fields"] = array();
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
		$maxheight=0;
		$minleft=0;
	}
	$maxheight = max($maxheight, $pos);

	# TODO first x, then y
	$schema[$table]['pos'] = array( $minleft, $top );
	$minleft=$minleft+$tablewidth+20;
	#echo '('.$schema[$table]["pos"][0].' '.$schema[$table]["pos"][1].') ';

	foreach ($adminer->foreignKeys($table) as $val) {
		if (!$val["db"]) {
			if ($table_pos[$table][1] || $table_pos[$val["table"]][1]) {
				$left = min($table_pos[$table][1], $table_pos[$val["table"]][1]);
			}
			$schema[$table]["references"][$val["table"]][] = array($val["source"], $val["target"]);
			$referenced[$val["table"]][$table][] = $val["target"];
		}
	}
	$tcounter++;
}
$schemawidth=$viewportwidth;
$schemaheight=$top + $maxheight;


$sort=false;
if (isset($_POST['sort'])){
	if ($_POST['sort']==='name'){
		$sort='name';
	} elseif ($_POST['sort']==='fieldcount'){
		$sort='fieldcount';
	} elseif ($_POST['sort']==='fieldcount_desc'){
		$sort='fieldcount_desc';
	} elseif ($_POST['sort']==='cookie'){
		$sort='cookie';
	}
}
?>
<form action="" method="post" id="sortform">
<button <?= $sort=='name' ? 'disabled="disabled" ':'' ?>name="sort" value="name">Name</button>
<button <?= $sort=='fieldcount' ? 'disabled="disabled" ':'' ?>name="sort" value="fieldcount">Fields</button>
<button <?= $sort=='fieldcount_desc' ? 'disabled="disabled" ':'' ?>name="sort" value="fieldcount_desc">Fields desc</button>
<button <?= $sort=='cookie' ? 'disabled="disabled" ':'' ?>name="sort" value="cookie">Coords Cookie</button>
</form>
<input name="showfields" type="radio" id="s_shownofields">
<label id="shownofieldslabel" for="s_shownofields">no</label>
<input name="showfields" type="radio" id="s_showpkfields">
<label id="showpkfieldslabel" for="s_showpkfields">PK</label>
<input name="showfields" type="radio" id="s_showallfields">
<label id="showallfieldslabel" for="s_showallfields">all</label>
<div id="minimap">
	<div id="whereami"></div>
	<div id="visible"><div id="dragme"></div></div>
</div>
<div id="miniinfo"></div>
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
}
#schema svg {position:absolute;}
#schema svg path {stroke:rgb(0,0,255);stroke-width:1;fill:none;opacity:0.5; }
#schema svg line {stroke:rgb(0,102,0);stroke-width:1;opacity:0.5; }
.table {
	background-color:#ddd;
	font-family:<?= ($hasfont && function_exists('imagettfbbox')) ? 'sans-serif':'monospace'; ?>
}
.table span{display:block;line-height:11px;}
.table i span {font-style:normal;background-color:#ff6;}
input[name=showfields]{display:none;}
#s_shownofields:checked ~ #schema .table span{display:none; }
#s_showpkfields:checked ~ #schema .table span {display:none; }
#s_showpkfields:checked ~ #schema .table i span {display:block; }
#showallfieldslabel, #showpkfieldslabel, #shownofieldslabel {
	cursor:pointer;
	display:inline-block;
	background:#eee;
	border:1px solid #ccc;
	padding:0 6px;
	border-radius:4px;
}
#s_shownofields:checked  ~ #shownofieldslabel,
#s_showpkfields:checked  ~ #showpkfieldslabel,
#s_showallfields:checked ~ #showallfieldslabel {
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
#whereami{
	border:1px solid rgba(255,0,0,0.6);
	width:2px;
	height:2px;
	box-sizing:border-box;
	position:absolute;
}
#visible{
	border: 1px solid rgba(100,100,100,0.3);
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
	right:200px;
	z-index:10;
	vertical-align:bottom;
	width:200px;
	border: 1px solid #ccc;
	background-color:#fff;
}
</style>
<script>
var tablePos = {<?php echo implode(",", $table_pos_js) . "\n"; ?>};
var em=14.4; // I use px, but do not want change existing adminer scripts..
document.addEventListener('DOMContentLoaded', function () {
	/* document.getElementById('schema').addEventListener('mousemove', updateMinimap); */
	document.getElementById('visible').addEventListener('click', dragMinimap);
	window.addEventListener('resize', updateMinimap);
	window.addEventListener('scroll', updateMinimap);

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
function updateMinimap(event) {
	schema=document.getElementById('schema').getBoundingClientRect();
	minimap=document.getElementById('minimap').getBoundingClientRect();
	/*
	document.getElementById('miniinfo').innerHTML= 'schema:' + schema.width + ' x ' + schema.height
		+ '<br>mouse:' + event.clientX + ' x ' + event.clientY
		+ '<br>window:' + window.innerWidth + ' x ' + window.innerHeight;
	*/
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
foreach ($schema as $name => $table) {
	$i=0;
	foreach ((array) $table["references"] as $target_name => $refs) {
		$i++;
		$j=0;
		foreach ($refs as $left => $ref) {
			$j++;
			foreach ($ref[0] as $key => $source) {
				$x1 = $table["pos"][0];
				$x2 = $schema[$target_name]["pos"][0];
				$min_x = min($x1, $x2);
				$max_x = max($x1, $x2);
				$w=abs($x1-$x2);

				$y1 = $table["pos"][1] + $table["fields"][$source]["pos"];
				$y2 = $schema[$target_name]["pos"][1] + $schema[$target_name]["fields"][$ref[1][$key]]["pos"];
				$min_y = min($y1, $y2);
				$max_y = max($y1, $y2);
				$h=abs($y1-$y2);
				if($x1>$x2){
					$sx1=$w;
					$sx2=0;
				}elseif($x1==$x2){
					$w=2;
					$sx1=0;
					$sx2=0;
				}else{
					$sx1=0;
					$sx2=$w;
				}
				if($y1>$y2){
					$sy1=$h;
					$sy2=0;
				}elseif($y1==$y2){
					$h=2;
					$sy1=0;
					$sy2=0;
				}else{
					$sy1=0;
					$sy2=$h;
				}
			}
			echo '<svg class="del1 upd1" id="ref-'.$name.'.'.$i.'-'.$j.':'.$target_name.'.'.$ref[1][$key].'" height="'.$h.'" width="'.($w+10).'" style="top:'.$min_y.'px;left:'.($min_x-10).'px">';
			if($sx1==$sx2){
				echo '<path d="M10,0 c-10,0 -10,'.$h.' 0,'.$h.'" class="selfref" />';
			} else {
				echo '<line x1="'.($sx1+10).'" y1="'.$sy1.'" x2="'.($sx2+10).'" y2="'.$sy2.'" />';
			}
			echo '</svg>';
		}
	}
}

foreach ($schema as $name => $table) {
	echo "<div class='table' style='left:".$table["pos"][0]."px;top:".$table["pos"][1]."px'>";
	echo '<a href="' . h(ME) . 'table=' . urlencode($name) . '"><b>' . h($name) . "</b></a>";
	echo script("qsl('div.table').onmousedown = schemaMousedown;");

	foreach ($table["fields"] as $field) {
		$val = '<span' . type_class($field["type"]) . ' title="' . h($field["full_type"] . ($field["null"] ? " NULL" : '')) . '">' . h($field["field"]) . '</span>';
		echo ($field["primary"] ? "<i>$val</i>" : $val);
	}
	echo "</div>";
}

?>
</div>
<p class="links"><a href="<?php echo h(ME . "schema=" . urlencode($SCHEMA)); ?>" id="schema-link"><?php echo lang('Permanent link'); ?></a></p>
<input type="checkbox" id="s_querylog">
<label for="s_querylog" id="showqueryloglabel">Show Log</label>
<label for="s_querylog" id="hidequeryloglabel">Hide Log</label>
<div id="querylog">
<?php
foreach ($GLOBALS['querylog'] as $q){
	echo '<pre>'.htmlspecialchars($q).'</pre>';
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
