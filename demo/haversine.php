<?php 
namespace Alptech\Wip;
#play it this way :: gzip -d latlon.sql.gz;mysql < latlon.sql
#phpx C:\Users\ben\home\d9\vendor\alptech\wip\demo\haversine.php
chdir(__DIR__);
require_once '../../../autoload.php';
#harversine :: fastest (if there are indexes ..)
$time=$deviation=$oids=$nids=[];$table='latlon';$_lat=48.8566969;$_lon=2.3514616;$_dist=5;$db='silverb';

if(isset($_POST))extract($_POST);
?>
<form method=post action='?'><table>
<tr><td>lat</td><td><input name=_lon value='<?=$_lat?>'></td></tr>
<tr><td>lon</td><td><input name=_lon value='<?=$_lon?>'></td></tr>
<tr><td>dist</td><td><input name=_dist value='<?=$_dist?>'></td></tr>
<tr><td colspan=2><input type=submit accesskey=s></td></tr></table>
</form><pre style='white-space:pre-wrap;word-break:all;max-width:99vw;'>Results mostly depends on indexes, repetition, and wished distance .. deviation in percentage of returned results delta .. the more the distance increases, the more the bounding rectangle and the hypothenuse calculus will deviate from haversine formulae<br>
<?php
$x=fun::shortDist($_lat,$_lon,1);$_km=[$_lat-$x[0],$_lon-$x[2]];#how much degrees in lat/lon for 1km ?
$_rect=fun::shortDist($_lat,$_lon,$_dist);#boudaries rectangle => shall be refined to ellipsis

$s="select (6371 * acos(cos(radians(latitude)) * cos(radians($_lat)) * cos(radians($_lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($_lat)))) as distance,a.* from $table a having distance <= $_dist order by distance asc";
$a=microtime(1);$x[]=fun::sql($s,$db);$time['haver:'.count(end($x))]=microtime(1)-$a;#real results !
foreach(end($x) as $t){$oids[]=$t['id'];}

$s="select (6371 * acos(cos(radians(latitude)) * cos(radians($_lat)) * cos(radians($_lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($_lat)))) as distance,a.* from $table a where (6371 * acos(cos(radians(latitude)) * cos(radians($_lat)) * cos(radians($_lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($_lat)))) <= $_dist order by distance asc";
$a=microtime(1);$x[]=fun::sql($s,$db);$time['haver:where :'.count(end($x))]=microtime(1)-$a;#real results !

$s="select (6371 * acos(cos(radians(latitude)) * cos(radians($_lat)) * cos(radians($_lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($_lat)))) as distance,a.* from $table a where latitude between $_rect[0] and $_rect[1] and longitude between $_rect[2] and $_rect[3] having distance <= $_dist order by distance asc";
$a=microtime(1);$x[]=fun::sql($s,$db);$time['bound+haver:slighthly faster?:'.count(end($x))]=microtime(1)-$a;

foreach(end($x) as $t){$nids[]=$t['id'];} $deviation['bound+haver:slighthly faster?:']=(count(array_diff($oids,$nids))+count(array_diff($nids,$oids)))*100/count($oids);

#2nde :: pas si mal :: closest ellipsis approximation
$s="select SQRT(pow(($_lat-latitude)/$_km[0],2) + pow(($_lon-longitude)/$_km[1],2)) as distance,a.* from $table a having distance <= $_dist order by distance asc";
$a=microtime(1);$x[]=fun::sql($s,$db);$time['hyp:'.count(end($x))]=microtime(1)-$a;

$nids=[];foreach(end($x) as $t){$nids[]=$t['id'];} $deviation['hyp:']=(count(array_diff($oids,$nids))+count(array_diff($nids,$oids)))*100/count($oids);

#3rd=plus de résultats, plus rapidement, à affinier ..
$s="select a.* from $table a where latitude between $_rect[0] and $_rect[1] and longitude between $_rect[2] and $_rect[3]";
$a=microtime(1);
$z=fun::sql($s,$db);
$k='rect:before refining distance results : ';
$time[$k.count($z)]=microtime(1)-$a;
$nids=[];foreach($z as $t){$nids[]=$t['id'];} $deviation[$k]=(count(array_diff($oids,$nids))+count(array_diff($nids,$oids)))*100/count($oids);

$y['excluded from results with too big distance']=0;
foreach($z as &$t){
    $rd=fun::distance($_lat,$t['latitude'],$_lon,$t['longitude']);
    if($rd>$_dist){$y['excluded from results with too big distance']++;$t=null;}
}unset($t);
$z=array_filter($z);
$x[]=$z;

$k='rect:affine:';
$nids=[];foreach($z as $t){$nids[]=$t['id'];} $deviation[$k]=(count(array_diff($oids,$nids))+count(array_diff($nids,$oids)))*100/count($oids);
$time[$k.count($z)]=microtime(1)-$a;

print_r(compact('time','deviation'));
return;?>

CREATE TABLE `latlon` (`id` int(11) NOT NULL AUTO_INCREMENT,`latitude` varchar(50) DEFAULT NULL,`longitude` varchar(50) DEFAULT NULL,PRIMARY KEY (`id`),KEY `latlon_latitude_index` (`latitude`),KEY `latlon_longitude_index` (`longitude`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;

if('extract'){
    $s='select id,latitude,longitude from health_organizations where latitude is not null and longitude is not null';
    $x=fun::sql($s,'silver');
    #print_r($x);die;    
    foreach($x as $t){
        $s="insert into $table(id,latitude,longitude)values($t[id],'$t[latitude]','$t[longitude]')";
        fun::sql($s,'silverb');
    }
    die;
}
