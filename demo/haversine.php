<?php
namespace Alptech\Wip;
#play it this way :: gzip -d latlon.sql.gz;mysql < latlon.sql
#phpx C:\Users\ben\home\d9\vendor\alptech\wip\demo\haversine.php
chdir(__DIR__);
require_once '../../../autoload.php';
#harversine :: fastest (if there are indexes ..)
$time = $deviation = $oids = $nids = [];
$table = 'latlon';
$_lat = 48.8566969;
$_lon = 2.3514616;
$_dist = 5;
$db = 'silverb';

if (isset($_POST)) extract($_POST);
?>
<form method=post action='?'>
    <table>
        <tr>
            <td>lat</td>
            <td><input name=_lon value='<?= $_lat ?>'></td>
        </tr>
        <tr>
            <td>lon</td>
            <td><input name=_lon value='<?= $_lon ?>'></td>
        </tr>
        <tr>
            <td>dist</td>
            <td><input name=_dist value='<?= $_dist ?>'></td>
        </tr>
        <tr>
            <td colspan=2><input type=submit accesskey=s></td>
        </tr>
    </table>
</form>
<pre style='white-space:pre-wrap;word-break:all;max-width:99vw;'>Results mostly depends on indexes, repetition, and wished distance .. deviation in percentage of returned results delta .. the more the distance increases, the more the bounding rectangle and the hypothenuse calculus will deviate from haversine formulae<br>
<?php
$x = fun::shortDist($_lat, $_lon, 1);
$_km = [$_lat - $x[0], $_lon - $x[2]];# how much degrees in lat/lon for 1km ? ( only Lon distance is different from equatorial to pole where it is null
$_rect = fun::shortDist($_lat, $_lon, $_dist);# boudaries rectangle => shall be refined to ellipsis

$s = "select (6371 * acos(cos(radians(latitude)) * cos(radians($_lat)) * cos(radians($_lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($_lat)))) as distance,a.* from $table a having distance <= $_dist order by distance asc";
$a = microtime(1);
$x[] = fun::sql($s, $db);
$time['haver:' . count(end($x))] = microtime(1) - $a;#real results !
foreach (end($x) as $t) {
    $oids[] = $t['id'];
}

$s = "select (6371 * acos(cos(radians(latitude)) * cos(radians($_lat)) * cos(radians($_lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($_lat)))) as distance,a.* from $table a where (6371 * acos(cos(radians(latitude)) * cos(radians($_lat)) * cos(radians($_lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($_lat)))) <= $_dist order by distance asc";
$a = microtime(1);
$x[] = fun::sql($s, $db);
$time['haver:where :' . count(end($x))] = microtime(1) - $a;#real results !

$s = "select (6371 * acos(cos(radians(latitude)) * cos(radians($_lat)) * cos(radians($_lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($_lat)))) as distance,a.* from $table a where latitude between $_rect[0] and $_rect[1] and longitude between $_rect[2] and $_rect[3] having distance <= $_dist order by distance asc";
$a = microtime(1);
$x[] = fun::sql($s, $db);
$time['bound+haver:slighthly faster?:' . count(end($x))] = microtime(1) - $a;

foreach (end($x) as $t) {
    $nids[] = $t['id'];
}
$deviation['bound+haver:slighthly faster?:'] = (count(array_diff($oids, $nids)) + count(array_diff($nids, $oids))) * 100 / count($oids);

#2nde :: pas si mal :: closest ellipsis approximation
$s = "select SQRT(pow(($_lat-latitude)/$_km[0],2) + pow(($_lon-longitude)/$_km[1],2)) as distance,a.* from $table a having distance <= $_dist order by distance asc";
$a = microtime(1);
$x[] = fun::sql($s, $db);
$time['hyp:' . count(end($x))] = microtime(1) - $a;

$nids = [];
foreach (end($x) as $t) {
    $nids[] = $t['id'];
}
$deviation['hyp:'] = (count(array_diff($oids, $nids)) + count(array_diff($nids, $oids))) * 100 / count($oids);

/*
Rappel : Rayon Terre: 6347 km en moyenne
1° longitude à 45° de lat = (39879:circonférence terre : Pi*r*2 ) cos(45) / 360° = 78,567 km
1° lat = 39879/360=111km
 */
if ('#3rd) Rectangle :: obtention de plus de résultats, plus rapidement, à affiner .. ( distance 1° longitude précalculée pour le milieu de la latitude ) ') {

    $s = "select a.* from $table a where latitude between $_rect[0] and $_rect[1] and longitude between $_rect[2] and $_rect[3] -- le plus rapide en sql c'est évident, et plus avec des indexs encore";
    $a = microtime(1);
    $z = fun::sql($s, $db);
    $k = 'rect:before refining distance results : ';
    $time[$k . count($z)] = microtime(1) - $a;
    $nids = [];
    foreach ($z as $t) {
        $nids[] = $t['id'];
    }
    $deviation[$k] = (count(array_diff($oids, $nids)) + count(array_diff($nids, $oids))) * 100 / count($oids);

    $y['excluded from results with too big distance'] = 0;
    foreach ($z as &$t) {
        $rd = fun::distance($_lat, $t['latitude'], $_lon, $t['longitude']);// Then apply real haversine formulae for getting exact precise distances
        if ($rd > $_dist) {
            $y['excluded from results with too big distance']++;
            $t = null;
        }
    }
    unset($t);
    $z = array_filter($z);
    $x[] = $z;

    $k = 'rect:affine:';
    $nids = [];
    foreach ($z as $t) {
        $nids[] = $t['id'];
    }
    $deviation[$k] = (count(array_diff($oids, $nids)) + count(array_diff($nids, $oids))) * 100 / count($oids);
    $time[$k . count($z)] = microtime(1) - $a;
}
print_r(compact('time', 'deviation'));
return; ?>

Rayon Terre: 6347 km en moyenne
1° longitude ) 45° de lat = (39879:circonférence terre) cos(45):courbure / 360 = 78,567 km
1° lat = 39879/360=111km


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


#harversine formulae
static function distance($lat1, $lat2, $lng1, $lng2)
{
    $pi80 = M_PI / 180;#1 rad
    $lat1 *= $pi80;
    $lng1 *= $pi80;
    $lat2 *= $pi80;
    $lng2 *= $pi80;

    $r = 6372.797; // rayon moyen de la Terre en km
    $dlat = $lat2 - $lat1;
    $dlng = $lng2 - $lng1;
    $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $km = $r * $c;
    return $km;
}