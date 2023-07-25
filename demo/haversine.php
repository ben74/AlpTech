<?php
namespace Alptech\Wip;
require_once '../../../autoload.php';
/*
En gros :
1) index sql lat, lon, where rectangle afin de limiter l'analyse de trop de points sur toute la planète ...
2) le cpu sera consommé soit dans le sql soit dans le php et cela revient au même
 */

$lat = 48.8566969;$lon = 2.3514616;$dist = 70;$tests=0;// search for it
$table = 'latlon';$db = 'haversine';fun::setStatic('conf', ['haversine'=>['h'=>'127.0.0.1','u'=>'root','p'=>'b','db'=>$db]]);


extract(fun::args());

/*
play it this way ::
cd vendor/alptech/wip/demo
gzip -d latlon.sql.gz;mysql -pb haversine < latlon.sql
phpx  haversine.php lat=46 lon=6 dist=70 '{"lat":46,"lon":6,"dist":45}'
*/
chdir(__DIR__);


#harversine :: fastest (if there are indexes ..)
$time = $deviation = $oids = $nids = [];

if (isset($_POST)) extract($_POST);
if(!isset($argv)){
?>
<form method=post action='?'>
    <table>
        <tr>
            <td>lat</td>
            <td><input name=lon value='<?= $lat ?>'></td>
        </tr>
        <tr>
            <td>lon</td>
            <td><input name=lon value='<?= $lon ?>'></td>
        </tr>
        <tr>
            <td>dist</td>
            <td><input name=dist value='<?= $dist ?>'></td>
        </tr>
        <tr>
            <td colspan=2><input type=submit accesskey=s></td>
        </tr>
    </table>
</form>
<pre style='white-space:pre-wrap;word-break:all;max-width:99vw;'>Results mostly depends on indexes, repetition, and wished distance .. deviation in percentage of returned results delta .. the more the distance increases, the more the bounding rectangle and the hypothenuse calculus will deviate from haversine formulae<br>
<?php
}

$x = fun::shortDist($lat, $lon, 1, $dist);

$_km = [$lat - $x[0], $lon - $x[2]];# how much degrees in lat/lon for 1km ? ( only Lon distance is different from equatorial to pole where it is null
$_rect = fun::shortDist($lat, $lon, $dist);# boudaries rectangle => shall be refined to ellipsis

if('haversine simple'){
    $s = "select (6371 * acos(cos(radians(latitude)) * cos(radians($lat)) * cos(radians($lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($lat)))) as distance,a.* from $table a having distance <= $dist order by distance asc -- would be fast once cached so allways consider first input";
    $a = microtime(1);
    $last=$x[] = fun::sql($s, $db);
    $k='haver:' . count(end($x));
    $time[$k.':elements, always consider first result not in cache'] = round(microtime(1) - $a,6);#real results !
    $oids =array_map(function($a){return $a['id'];},end($x));
    $deviation[$k]='0 -- is the most reliable result ever =)';
}
if(!$oids)$oids=[1];

if('2) haversine with where'){
    $s = "select (6371 * acos(cos(radians(latitude)) * cos(radians($lat)) * cos(radians($lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($lat)))) as distance,a.* from $table a where (6371 * acos(cos(radians(latitude)) * cos(radians($lat)) * cos(radians($lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($lat)))) <= $dist order by distance asc";
    $a = microtime(1);
    $last=$x[] = fun::sql($s, $db);
    $time['haver: adding where limitation :' . count(end($x))] = round(microtime(1) - $a,6);#real results !
}

if('3) harversine within rectangle, limits computations on huge sets'){
    $s = "select (6371 * acos(cos(radians(latitude)) * cos(radians($lat)) * cos(radians($lon) -radians(longitude)) + sin(radians(latitude)) * sin(radians($lat)))) as distance,a.* from $table a where latitude between $_rect[0] and $_rect[1] and longitude between $_rect[2] and $_rect[3] having distance <= $dist order by distance asc";
    $a = microtime(1);
    $last=$x[] = fun::sql($s, $db);
    $time['rectangle boundaries+haver:slighthly faster?:' . count(end($x))] = round(microtime(1) - $a,6);

    $nids =array_map(function($a){return $a['id'];},end($x));
    if(!$nids)die('no results');

    $deviation['bound+haver:slighthly faster?:'] = (count(array_diff($oids, $nids)) + count(array_diff($nids, $oids))) * 100 / count($oids);
}

#2nde :: pas si mal :: closest ellipsis approximation
$s = "select SQRT(pow(($lat-latitude)/$_km[0],2) + pow(($lon-longitude)/$_km[1],2)) as distance,a.* from $table a having distance <= $dist order by distance asc";
$a = microtime(1);
$x[] = fun::sql($s, $db);
$time['distance via hypothenuse ( approximation grossière, oui ):' . count(end($x))] = round(microtime(1) - $a,6);

$nids = [];
foreach (end($x) as $t) {
    $nids[] = $t['id'];
}
$deviation['hyp:'] = (count(array_diff($oids, $nids)) + count(array_diff($nids, $oids))) * 100 / count($oids);

#hyp + rectangle
$s = "select SQRT(pow(($lat-latitude)/$_km[0],2) + pow(($lon-longitude)/$_km[1],2)) as distance,a.* from $table a where latitude between $_rect[0] and $_rect[1] and longitude between $_rect[2] and $_rect[3]  having distance <= $dist order by distance asc";
$a = microtime(1);
$x[] = fun::sql($s, $db);
$time['rect+hyp:' . count(end($x))] = microtime(1) - $a;

$nids =array_map(function($a){return $a['id'];},end($x));
$deviation['rect+hyp:'] = (count(array_diff($oids, $nids)) + count(array_diff($nids, $oids))) * 100 / count($oids);


/*
Rappel : Rayon Terre: 6347 km en moyenne
1° longitude à 45° de lat = (39879:circonférence terre : Pi*r*2 ) cos(45) / 360° = 78,567 km
1° lat = 39879/360=111km
 */
if ('#5rd) Rectangle :: obtention de plus de résultats, plus rapidement, à affiner .. ( distance 1° longitude précalculée pour le milieu de la latitude ) ') {

    $s = "select a.* from $table a where latitude between $_rect[0] and $_rect[1] and longitude between $_rect[2] and $_rect[3] -- le plus rapide en sql c'est évident, et plus avec des indexs encore";
    $a = microtime(1);
    $z = fun::sql($s, $db);
    $k = 'rect:before refining distance results';
    $nids =array_map(function($a){return $a['id'];},$z);

    $time[$k . ':' . count($z)] = round(microtime(1) - $a, 6);
    $deviation[$k] = (count(array_diff($oids, $nids)) + count(array_diff($nids, $oids))) * 100 / count($oids);
    $a=microtime(1);
    foreach ($z as &$t) {
        $t['haversine']=$rd = fun::distance($lat, $t['latitude'], $lon, $t['longitude']);// Then apply real haversine formulae for getting exact precise distances
        if ($rd > $dist) {
            $y['excluded from results with too big distance']++;
            $t = null;
        }
    }unset($t);$z = array_filter($z);//remove  excluded
    $time['excludedElementTooFar ( so increase dist a little on approximation )'] = $y['excluded from results with too big distance'];
    $time['then calcl distances ( faster than pythagore )'] = round(microtime(1) - $a,6);

$k='outliers removed then... shall be 0 is correct then :)';
$nids =array_map(function($a){return $a['id'];},$z);
    $deviation[$k] = (count(array_diff($oids, $nids)) + count(array_diff($nids, $oids))) * 100 / count($oids);


    $kmPerLatDegrees = 6372.797* M_PI / 180;
    $y['excluded from results with too big distance'] = $totalDeviation=0;

if($tests and 'plus long de calculer via pythagore ...'){
    $a=microtime(1);
    foreach ($z as &$t) {// 650 ..
        $dLat = ($lat + $t['latitude']) / 2;//milieu
        $dLongAtMiddle = fun::distance($dLat, $dLat, $olon, $olon + 1);
        $dlong = ($lon-$t['longitude']) * $dLongAtMiddle;
        $dlat2 = ($lat - $t['latitude']) * $kmPerLatDegrees;
        $t['pythagore']=$distViaPythagore=sqrt(pow($dlong,2)+pow($dlat2,2));
        $t['deviation'] = $dev=abs($t['pythagore']-$t['haversine']);
        $totalDeviation+=$dev;
    }unset($t);
    $time[$k . count($z).': via pythagore'] = round(microtime(1) - $a,6);
    $time['--pyth/haversine avg deviation en km'] = $totalDeviation/count($z);
    $deviationsPythagore=array_map(function($a){return $a['deviation'];},$z);
    unset($t);
}
    $x[] = $z;

    $k = "--le calcul de la distance entre 2 points consommera toujours du temps cpu en sql ou php";
    $nids =array_map(function($a){return $a['id'];},$z);
    $deviation[$k] = (count(array_diff($oids, $nids)) + count(array_diff($nids, $oids))) * 100 / count($oids);
    //$time[$k] = round(microtime(1) - $a,6);
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
