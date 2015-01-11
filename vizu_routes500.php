<?php

$dep = @$_GET["dep"];
$filename="http://osm13.openstreetmap.fr/~cquest/routes/routes-".$dep.".csv";

$routes=array("features" =>array(),"type" =>"FeatureCollection");

$handle = fopen($filename,'r');
$start=true;

$routes_no=array();
	
while ( ($data = fgetcsv($handle) ) !== FALSE ) {
    if (($start==false) and (!in_array($data[0],$routes_no))){

		$km_osm=floatval($data[1]);
		$km_ign=floatval($data[2]);
		if ($km_ign>0){
			$ecart=round(100*($km_osm-$km_ign)/$km_ign);
		} else
		$ecart=0;
		$point=array(
			"geometry" => array(
				"type" => "Point",
				"coordinates" => array(floatval($data[4]),floatval($data[3]))
				),
			"properties" => array(
				"nom" => $data[0],
				"km_OSM" => $km_osm,
				"km_IGN" => $km_ign,
				"ecart" => $ecart
				),
			"type" => "Feature"
		);
		array_push($routes['features'], $point);
	}
	$start=false;
}
$routes_json=json_encode($routes);
$dep_json=json_encode($dep);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Routes  du <?php echo $dep_json; ?></title>
  <meta charset="utf-8" />
  <link rel="stylesheet" href="http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.css" />
  <style type="text/css">
  body {  padding: 0; margin: 0;  }
  html, body, #map {  height: 100%;  }
  </style>
  <script src="http://cdn.leafletjs.com/leaflet-0.7.2/leaflet.js"></script>
  <script src="http://code.jquery.com/jquery-2.1.0.min.js"></script>
  
</head>
<body>
	<div id="map"></div>
	<script>
		var routes=<?php echo $routes_json; ?>;
		var dep=<?php echo $dep_json; ?>;	
			
		var tiles_osmfr = L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png',{
				attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
				maxZoom : 18,
				opacity: 0.8
				});
									
		function toHex(d) {
			return  ("0"+(Number(Math.round(d)).toString(16))).slice(-2).toUpperCase()
		}

		function getOptions(feature) {

			var ecart=Math.abs(feature.properties.ecart);
			if (ecart>100) ecart=100;
			
			var km_osm=feature.properties.km_OSM;
			var km_ign=feature.properties.km_IGN;
			
			var color_empty = '#FF0000';
			if (km_osm=="0") color = color_empty;
			else color =  '#00'+toHex(2.55*(100-ecart))+toHex(2.55*ecart);
						
			taille=5;
			ecart_km=Math.abs(km_ign-km_osm);
			if (ecart_km>taille) taille=ecart_km;

			var geojsonMarkerOptions = {
				radius: taille,
				fillColor: color,
				color: color,
				weight: 1,
				opacity: 1,
				fillOpacity: 0.8
			};		
			feature.properties.color=color;
			return geojsonMarkerOptions;
		};
 	  
		var geojson_routes = L.geoJson(routes, {	
			pointToLayer: function (feature, latlng) {return L.circleMarker(latlng, getOptions(feature));},

			onEachFeature: function (feature, layer) {
				var popup='Route: '+feature.properties.nom+
							  '<br>KM IGN:'+feature.properties.km_IGN+
							  '<br>KM OSM:'+feature.properties.km_OSM+
							  '<br>% ecart:'+feature.properties.ecart;
					if (dep=='22')
							popup+='<br><a href="vizu_route.php?dep=22&route='+feature.properties.nom+'" target="_blank">'+feature.properties.nom+'</a>';
					layer.bindPopup(popup);
			}
		});
		
		var map = L.map('map', {    layers: [tiles_osmfr, geojson_routes ]}).fitBounds(geojson_routes.getBounds());
		var baseMaps = {"OSM France": tiles_osmfr};
		var overlayMaps = {"Ecart Routes OSM/IGN Routes 500": geojson_routes};
		L.control.layers(baseMaps, overlayMaps).addTo(map);	
  
  </script>
</body>
</html>
