<?php

if('POST'===$_SERVER['REQUEST_METHOD'] && isset($_POST['url'])){
 $query = parse_url($_POST['url'], \PHP_URL_QUERY);
 parse_str($query, $params);
 if(isset($params['v'])){
	 $_GET['v'] = $params['v'];
 }else{
	 $p = explode('/', $_POST['url']);
	 $p = array_pop($p);
	 if(!empty($p)){
		  $_GET['v'] = $p;
	 }
 }
	
} 
$instances =  getInvidiousInstances(isset($_GET['v'])
									 ? '/'.$_GET['v']
									 : $_SERVER['REQUEST_URI'], 60 * 60, __DIR__.\DIRECTORY_SEPARATOR.'cache_instances.json');

shuffle($instances);

 $i = $instances[0];

//print_r($i);
header('Location: '.$i['target'], 302);
die('<a href="'.$i['target'].'">Go...</a>');
 
	   
	   
function getInvidiousInstances(?string $destinationPath = '', ?int $cacheLimit = 300, ?string $cacheFile = 'cache_instances.json')
{
    $url = "https://api.invidious.io/instances.json?sort_by=type,health";

    // Cache prüfen
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheLimit) {
        $json = file_get_contents($cacheFile);
    } else {
        $json = file_get_contents($url);
        if ($json !== false && $json !== "[]") {
            file_put_contents($cacheFile, $json);
        } else {
            // Fallback: alter Cache wenn vorhanden
            if (file_exists($cacheFile)) {
                $json = file_get_contents($cacheFile);
            } else {
                return []; // komplett fehlgeschlagen
            }
        }
    }

    $root = json_decode($json, true);
    if (!is_array($root)) {
        return [];
    }

    // Shuffle wie im JS
    shuffle($root);

    $result = [];
    $result2 = [];

    foreach ($root as $entry) {
        $name = $entry[0];
        $details = $entry[1];

        $healthKnown = isset($details['monitor']) && isset($details['monitor']['uptime'])
			  && ( is_float($details['monitor']['uptime']) || is_numeric($details['monitor']['uptime']));
        $health = $healthKnown
         //   ? (float)$details['monitor']['dailyRatios'][0]['ratio']
			 ? (float)$details['monitor']['uptime']
            : 0;

		 
		
        // Filter
        if ($details['type'] !== 'https' || $health <= 50) {
            continue;
        }

        $target = rtrim($details['uri'], "/") . $destinationPath;

        $result[] = [
            'name' => $name,
            'details' => $details,
            'health' => $health,
            'healthKnown' => $healthKnown,
            'target' => $target, 
        ];
        $result2[] = [
            'name' => $name,
            'details' => $details,
            'health' => $health,
            'healthKnown' => $healthKnown,
            'target' =>'https://'.parse_url( $target)['host'], 
        ];
    }

    // Sort wie im JS (descending health)
    usort($result, function ($a, $b) {
        return $b['health'] <=> $a['health'];
    });
    usort($result2, function ($a, $b) {
        return $b['health'] <=> $a['health'];
    });
     file_put_contents(__DIR__.'/instances.json', json_encode($result2));
    return $result;
}
