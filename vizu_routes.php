<?php

function distance2Points($pointA, $pointB) {
 	
    $lat1=doubleval($pointA['lat']);
    $lon1=doubleval($pointA['lon']);
        
    $lat2=doubleval($pointB['lat']);
    $lon2=doubleval($pointB['lon']);
     
	$distance=0;

	$lat1=deg2rad ($lat1);
	$lat2=deg2rad ($lat2);
	$lon1=deg2rad ($lon1);
	$lon2=deg2rad ($lon2);

	$distance = 6366000*2*asin( sqrt(pow((sin(($lat1 - $lat2) / 2)),2)+	cos($lat1) * cos($lat2) *(pow(sin((($lon1-$lon2)/2)),2)	)));
	
	return	$distance;
}

function is_updated($file_name){
        $today_yearday=time();
        $file_yearday=filemtime($file_name);

	$ecart=$today_yearday-$file_yearday;
	if ($ecart<600)
		return true;
	else return false;
	
}

// Obtenir le nom de la route sans espace (ie D22)
if (!isset($_GET["dep"]) && !isset($_GET["type"])) {
	echo "Usage : http://127.0.0.1/opendata22/vizu_osm.php?&type=rondpoint&dep=DD";
	echo "<br> DD  : code du dértement 2 chiffres (ie 22) ou une chiffre et une lettre (Corse : 2A ou 2B)";
	exit(1);
}
$dep=$_GET["dep"];
$type=$_GET["type"];

if ($type=="rondpoint"){

	$file_name=$type."/".$dep.".json";
	$file_updated=is_updated($file_name);

	if (!$file_updated){
		// URL de l'API Overpass OSM
		$url_overpass='http://overpass-api.de/api/interpreter?data=[out:json];(area[%22ref:INSEE%22=%22'.$dep.'%22][admin_level=%226%22]-%3E.zone;way(area.zone)[%22junction%22=%22roundabout%22];node(area.zone)[%22highway%22=%22mini_roundabout%22];);out%20meta;%3E;out%20skel;';
		// Lecture des donnnées au format JSON
		$json_data=json_decode(file_get_contents($url_overpass),true);
		
		// Extraction des données  et conversion en GeoJSON
		$ways=array();
		$nodes=array();

                $routes=array("features" =>array(),"type" =>"FeatureCollection");

		$elements=$json_data["elements"];
		foreach  ($elements as $element) {
			if ($element["type"]=="way")
				array_push($ways, $element);
			else if ($element["type"]=="node"){
				if (isset($element["tags"]["highway"])){
					$coord=array(floatval($element["lon"]),floatval($element["lat"]));
					$node_geojson=array(
						"geometry" => array(
                                                	"type" => "Point",
	                                                "coordinates" => $coord
        	                                        ),
                	                        "properties" => array(
                                	                "timestamp" => $element["timestamp"],
                                        	        "version" => $element["version"],
                                               		"changeset" => $element["changeset"],
                                                	"user" => $element["user"],
                                                	"uid" => $element["uid"]
                                                	),
                                        	"type" => "Feature"

					);
                        		foreach ($element["tags"] as $key => $value){
                                		$key_tag="tag.".$key;
                                		$node_geojson["properties"][$key_tag]=$value;
                        		}

					array_push($routes["features"], $node_geojson);
				}
				else
                                	$nodes[$element["id"]]=array (
                                                "lat" =>$element["lat"],
                                                "lon" =>$element["lon"]
                                        );
			}
		}

		$distance_totale=0;
		$surface_totale=0;

		foreach  ($ways as $way) {
			$distance=0;
			$way_geojson=array(
					"geometry" => array(
						"type" => "LineString",
						"coordinates" => array()
						),
					"properties" => array(
						"timestamp" => $way["timestamp"],
						"version" => $way["version"],
						"changeset" => $way["changeset"],
						"user" => $way["user"],
						"uid" => $way["uid"]
						),
					"type" => "Feature"
				);

			
			foreach ($way["tags"] as $key => $value){
				$key_tag="tag.".$key;
				$way_geojson["properties"][$key_tag]=$value;
			}
				
			
			$point_prec_lat_lon=null;
			foreach  ($way["nodes"] as $node_way) {
				$point=array(floatval($nodes[$node_way]["lon"]),floatval($nodes[$node_way]["lat"]));
				$point_lat_lon=$nodes[$node_way];
				array_push($way_geojson["geometry"]["coordinates"], $point);
				if ($point_prec_lat_lon)
					$distance+=distance2Points($point_lat_lon,$point_prec_lat_lon);
				$point_prec_lat_lon=$point_lat_lon;
			}

			$way_geojson["properties"]["longueur"]=round($distance);
			
			$distance_totale+=$distance;

			$rayon=$distance/(2*M_PI);
			$surface=M_PI*$rayon*$rayon;
			$surface_totale+=$surface;

			$way_geojson["properties"]["superficie"]=round($surface);

			array_push($routes["features"], $way_geojson);
		}
		
		$fp = fopen($file_name, 'w');
		fwrite($fp, json_encode($routes));
		fclose($fp);		
	}	
	else {
		$routes=json_decode(file_get_contents($file_name),true);
	}
	
	// JSON => Leaflet
	$routes_osm_json=json_encode($routes);
	  
	$dir="Routes-".$dep;	
	$file_geojson=$dir."/giratoires.geojson";
	$json_data=json_decode(file_get_contents($file_geojson),true);
	$giratoires_json=json_encode($json_data);
	
	$dep_json=json_encode($dep);	
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Ronds-points du <?php echo $dep_json; ?></title>
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
		var routes_opendata=<?php echo $giratoires_json; ?>;
		var routes_osm=<?php echo $routes_osm_json; ?>;		
console.log("Nb rond-points OSM: "+routes_osm.features.length);		
		var tiles_osmfr = L.tileLayer('http://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png',{
				attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
				maxZoom : 18,
				opacity: 0.7
				});
				
		var tiles_ign = L.tileLayer('http://{s}.tile.openstreetmap.fr/route500/{z}/{x}/{y}.png',{
				attribution: 'IGN Route 500&reg; - routes',			
				maxZoom : 18,
				opacity: 0.7
				});		

		function getOptions(feature) {
 			if  (feature.properties["tag.ref"])
                                color="red";
                        else if (feature.properties["tag.ref:FR:CG22"])
                                color="green";
                        else
                                color="blue";

			var geojsonMarkerOptions = {
				radius: 2,
				fillColor: color,
				color: color,
				weight: 1,
				opacity: 1,
				fillOpacity: 0.8
			};		
			return geojsonMarkerOptions;
		};

		function style_osm(feature, layer) {
			if  (feature.properties["tag.ref"])
				color="red";
			else if (feature.properties["tag.ref:FR:CG22"])
				color="green";	
			else
				color="blue";	
			
			var geojsonMarkerOptions = {
				fillColor: color,
				color: color,
				weight: 5,
				opacity: 0.7,
				lineCap: "round"
			};		
			return geojsonMarkerOptions;
		}
		
		var style_opendata = {"color": "orange","weight": 10,"opacity": 0.7,"lineCap" : "round" };
	 
		function popup_osm (feature, layer) {	
			var popup=  '<b>Source: OpenStreetMap</b>'+
						'<br><br><b>Méta-données</b>'+
						'<br>User: '+feature.properties.user+
						'<br>Uid: '+feature.properties.uid+
						'<br>Version: '+feature.properties.version+
						'<br>Changeset: '+feature.properties.changeset+
						'<br>Timestamp: '+feature.properties.timestamp+			
						'<br><br><b>Tags:</b>';
			var keys=Object.keys(feature.properties);
			var k=0;
			for (k =0;  k<keys.length;k++){
				var key=keys[k];			
					if (key.slice(0,3)=='tag')
						popup+='<br>'+key.slice(4)+': '+feature.properties[key];
			}							
			popup+='<br><br><b>Données caculées</b>';
			popup+='<br>Longueur: '+feature.properties.longueur+' m';
			popup+='<br>Superficie: '+feature.properties.superficie+' m2';									

			if (feature.geometry.type=='LineString')
				popup+='<br><br><a href="http://www.openstreetmap.org/edit?#map=18/'+ feature.geometry.coordinates[0][1].toString()+'/'+feature.geometry.coordinates[0][0].toString()+'" target="_blank">Edition OSM</a>';									
			else if (feature.geometry.type=='Point')
				popup+='<br><br><a href="http://www.openstreetmap.org/edit?#map=18/'+ feature.geometry.coordinates[1].toString()+'/'+feature.geometry.coordinates[0].toString()+'" target="_blank">Edition OSM</a>';									
			
			layer.bindPopup(popup);
		};
	  
		function popup_opendata (feature, layer) {	
			layer.bindPopup(
				"<b>Source: Conseil General Cotes d'Armor - 2014</b><br>Rond-Point, Rérence: "+
				feature.properties.name[0]+' '+feature.properties.name.slice(1)+	
				'<br><br><a href="http://www.openstreetmap.org/edit?#map=18/'+ feature.geometry.coordinates[0][1].toString()+'/'+feature.geometry.coordinates[0][0].toString()+'" target="_blank">Edition OSM</a>'											
			)
		};
	  
		var geojson_opendata = L.geoJson(routes_opendata,{style: style_opendata,onEachFeature: popup_opendata});
		var geojson_osm = L.geoJson(routes_osm,{style: style_osm,onEachFeature:popup_osm,
			pointToLayer: function (feature, latlng) {return L.circleMarker(latlng, getOptions(feature));}});			
		var map = L.map('map',{layers: [tiles_osmfr,  geojson_opendata,geojson_osm, ]}).fitBounds(geojson_osm.getBounds());
		var baseMaps = {"OSM France": tiles_osmfr,"IGN Routes": tiles_ign};
		var overlayMaps = {"Open Data": geojson_opendata,"OSM":geojson_osm};
		L.control.layers(baseMaps, overlayMaps).addTo(map);	
	</script>
</body>
</html>
