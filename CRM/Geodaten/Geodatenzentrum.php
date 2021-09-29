<?php

require_once(__DIR__ . '/../../geoPHP/geoPHP.inc');

/**
 * Interface to fetch information from sgx.geodatenzentrum.de.
 */
class CRM_Geodaten_Geodatenzentrum {
  public function __construct() {
  }

  /**
   * Unfortunately the bounding box filter will return a lot of results that do not really
   * contain the point we are looking for. There seems to be only a basic rectangular check.
   * This is why we need this function to make sure the point is really contained in the given shape.
   */
  public function checkPointIsContainedInGeom($geoJSON, $lon, $lat) {
    $shape = geoPHP::load(json_encode($geoJSON), 'json');
    $point = geoPHP::load("POINT($lon $lat)","wkt");

    geoPHP::geosInstalled(true);

    return $shape->contains($point);
  }

  /**
   * Get extra address information about address from lat/lon.
   *
   * Uses the WFS service of the Bundesamt for Geodasie to get RS, Kreis,
   * Regierungsbezirk, Bundesland and Gemeinde.
   */
  public function getGeodata($lat, $lon) {
    if(!is_numeric($lat) || !is_numeric($lon)) {
      throw new Exception("Latitude and Longitude must be numbers");
    }
    $result = array();

    // List of features to request mapping to the id to return in
    // the result set.
    // The Rs is extracted from the last feature in the list
    // that contains an rs.
    $features = array(
      'vg250_lan' => 'bundesland',
      'vg250_rbz' => 'regierungsbezirk',
      'vg250_krs' => 'kreis',
      'vg250_gem' => 'gemeinde',
    );

    // Create bounding box from the point.
    $bbox = "$lon,$lat,$lon,$lat,EPSG:4326";

    // Create urls for features. The WFS in use allows
    // only one feature per request. So create an url for each feature.
    $urls = array();
    foreach (array_keys($features) as $feature) {
      $urls[] = 'https://sgx.geodatenzentrum.de/wfs_vg250?'
        . http_build_query(array(
          'request' => 'GetFeature',
          'service' => 'wfs',
          'version' => '2.0.0',
          'typeNames' => $feature,
          'BBOX' => $bbox,
          'SRSNAME' => 'EPSG:4326',
          'resultType' => 'results',
          'outputFormat' => 'application/json'
        ));
    }

    // Create a curl multihandler
    // and add curl handlers for urls to it.
    $mh = curl_multi_init();
    $chs = array();
    foreach ($urls as $url) {
      $ch = curl_init();

      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
      curl_setopt($ch, CURLOPT_TIMEOUT, 20);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_multi_add_handle($mh, $ch);
      $chs[] = $ch;
    }

    // Wait for all curl handlers to finish.
    do {
      curl_multi_exec($mh, $running);
    } while($running > 0);

    // Parse result of curl handlers.
    $i = 0;
    foreach ($chs as $ch) {
      $content = curl_multi_getcontent($ch);
      curl_multi_remove_handle($mh, $ch);

      $json = json_decode($content, true);

      $rows = !empty($json['features']) ? $json['features'] : array();

      foreach ($rows as $row) {
        if(!empty($row['geometry'])) {
          if ($this->checkPointIsContainedInGeom($row['geometry'], $lon, $lat)) {
            $properties = !empty($row['properties']) ? $row['properties'] : array();

            $label = array_values($features)[$i];

            $result[$label] = (string)$properties['gen'];
            $result['rs'] = (string)$properties['rs'];
          }
        }
      }

      $i++;
    }

    return $result;
  }
}
