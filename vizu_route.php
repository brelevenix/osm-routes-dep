<?php
// Obtenir le nom de la route sans espace (ie D22)
if ((!isset($_GET["route"])) or (!isset($_GET["dep"]))){
	echo "Usage : http://127.0.0.1/opendata22/vizu_osm.php?route=RRR&dep=DD";
	echo "<br> RRR : nom de la route sans espace (ie D22)";
	echo "<br> DD  : code du dértement 2 chiffres (ie 22) ou une chiffre et une lettre (Corse : 2A ou 2B)";
	exit(1);
}
$route = $_GET["route"];
$dep = $_GET["dep"];

$route_osm=substr($route,1);
$first_char=$route[0]; // 'D', 'N'...
$last_char=substr($route_osm,-1);

if (!is_numeric($last_char))
	$query_route="[ref~%22[dD]%20".substr($route_osm,0,-1)."[".strtolower($last_char).strtoupper($last_char)."]";
else
	$query_route="[ref=%22".strtoupper($first_char)."%20".$route_osm;  

// URL de l'API Overpass OSM
$url_overpass='http://overpass-api.de/api/interpreter?data=[out:json];(area[%22ref:INSEE%22=%22'.$dep.'%22][admin_level=%226%22]-%3E.zone;way(area.zone)'.$query_route.'"];);out%20meta;%3E;out%20skel;';

// Lecture des donnnées au format JSON
$content = file_get_contents($url_overpass);
$json_data=json_decode($content,true);

// Extraction des données et conversion en GeoJSON
$ways=array();
$nodes=array();

$elements=$json_data["elements"];
foreach  ($elements as $element) {
	if ($element["type"]=="way")
		array_push($ways, $element);
	else if ($element["type"]=="node"){
		$nodes[$element["id"]]=array ( 
				"lat" =>$element["lat"],
				"lon" =>$element["lon"]
			);
	}
}

$routes=array("features" =>array(),"type" =>"FeatureCollection");
foreach  ($ways as $way) {
	$way_geojson=array(
			"geometry" => array(
				"type" => "LineString",
				"coordinates" => array()
				),
			"properties" => array(
				"category" => "osm",
				"timestamp" => $way["timestamp"],
				"version" => $way["version"],
				"changeset" => $way["changeset"],
				"user" => $way["user"],
				"uid" => $way["uid"]
				),
			"type" => "Feature"
		);
	
	foreach  ($way["nodes"] as $node_way) {
		$point=array(floatval($nodes[$node_way]["lon"]),floatval($nodes[$node_way]["lat"]));
		array_push($way_geojson["geometry"]["coordinates"], $point);
	}
	array_push($routes["features"], $way_geojson);
}

// JSON => Leaflet
$routes_osm_json=json_encode($routes);
  
$opendata_file ="Routes-".$dep."/".$route.".geojson"; 
if (file_exists($opendata_file))
	$routes_opendata_json=file_get_contents($opendata_file);
else
	$routes_opendata_json="null";

$dep_json=json_encode($dep);
	
?>

<!DOCTYPE html>
<html>
<head>
  <title>Route <?php echo $route; ?> du <?php echo $dep_json; ?></title>
  <meta charset="utf-8" />
  <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.css" />
  <style type="text/css">
  body {padding:0;margin:0;}
  html, body, #map {height:100%;}
  </style>
  <script src="http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.js"></script>
  
</head>
<body>
	<div id="map"></div>
	<script>
	
		var routes_osm=<?php echo $routes_osm_json; ?>;
		var routes_opendata=<?php echo $routes_opendata_json; ?>;
		var dep=<?php echo $dep_json; ?>;	
		var tiles_osmfr = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png',{
				attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
				maxZoom : 18,
				opacity: 0.7
				});
				
		var tiles_ign = L.tileLayer('http://{s}.tile.openstreetmap.fr/route500/{z}/{x}/{y}.png',{
				attribution: 'IGN Route 500&reg; - routes',			
				maxZoom : 18,
				opacity: 0.7
				});		

		var style_osm = {"color": "blue","weight": 5,"opacity": 0.7,"lineCap" : "round" };
		var style_opendata = {"color": "orange","weight": 15,"opacity": 0.7,"lineCap" : "round" };
	  
		function popup_osm (feature, layer) {	
			layer.bindPopup(
				'<b>Source: OpenStreetMap</b>'+
				'<br>User: '+feature.properties.user+
				'<br>Uid: '+feature.properties.uid+
				'<br>Version: '+feature.properties.version+
				'<br>Changeset: '+feature.properties.changeset+
				'<br>Timestamp: '+feature.properties.timestamp+
				'<br><br><a href="http://www.openstreetmap.org/edit?#map=16/'+ feature.geometry.coordinates[0][1].toString()+'/'+feature.geometry.coordinates[0][0].toString()+'" target="_blank">Edition OSM</a>'											
			)
		};
	  
		function popup_opendata (feature, layer) {	
			layer.bindPopup(
				"<b>Source: Conseil General Cotes d'Armor - 2014</b><br>Route: "+
				feature.properties.name+	
				'<br><br><a href="http://www.openstreetmap.org/edit?#map=16/'+ feature.geometry.coordinates[0][1].toString()+'/'+feature.geometry.coordinates[0][0].toString()+'" target="_blank">Edition OSM</a>'											
			)
		};
	  
		var geojson_opendata = L.geoJson(routes_opendata,{style: style_opendata,onEachFeature: popup_opendata});
		var geojson_osm = L.geoJson(routes_osm,{style: style_osm,onEachFeature:popup_osm});	
		
		var map = L.map('map', {    layers: [tiles_osmfr, geojson_osm, geojson_opendata ]}).fitBounds(geojson_osm.getBounds());
		var baseMaps = {"OSM France": tiles_osmfr,"IGN Routes": tiles_ign};
		var overlayMaps = {"Open Data": geojson_opendata,"OSM":geojson_osm};
		L.control.layers(baseMaps, overlayMaps).addTo(map);	
	</script>
</body>
</html>
