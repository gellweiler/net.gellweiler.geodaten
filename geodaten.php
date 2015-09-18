<?php

require_once 'geodaten.civix.php';

/**
 * Implementation of hook_civicrm_pre().
 * 
 * Add extra geodata info to address.
 */
function geodaten_civicrm_post(
  $op, //operation
  $objectName,
  $objectId,
  &$objectRef
) {
  if ($objectName == 'Address' && ($op == 'edit' || $op == 'create')) {
    if (!empty($objectRef->geo_code_1) && !empty($objectRef->geo_code_2)) {
      $lat = $objectRef->geo_code_1;
      $lon = $objectRef->geo_code_2;

      $geodata = geodaten_get_geodata($lat, $lon);

      // Validate geodata.
      if (empty($geodata['rs'])) {
        CRM_Core_Session::setStatus
          ('Konnte keinen Regionalschlüssel zur Adresse finden.', 'Regionalschlüssel');
      } else if (!is_numeric($geodata['rs'])) {
        unset($geodata['rs']);
        CRM_Core_Session::setStatus
          ('Der Regionalschlüssel war keine Zahl.', 'Regionalschlüssel');
      }

      if (empty($geodata['bundesland'])) {
        CRM_Core_Session::setStatus
          ('Konnte kein Bundesland zur Adresse finden.', 'Bundesland');
      }
      if (empty($geodata['kreis'])) {
        CRM_Core_Session::setStatus
          ('Konnte keinen Kreis zur Adresse finden.', 'Kreis');
      }
      if (empty($geodata['gemeinde'])) {
        CRM_Core_Session::setStatus
          ('Konnte keine Gemeinde zur Adresse finden.', 'Gemeinde');
      }

      // Save geodata in custom fields.
      require_once 'CRM/Core/BAO/CustomValueTable.php';

      $custom_fields = geodaten_get_custom_fields_from_table('geodaten');

      $custom_values = array(
        'entityID' => $objectId,
      );
      foreach ($custom_fields as $column => $field) {
        $custom_values[$field]
          = !empty($geodata[$column]) ? $geodata[$column] : '';
      }

      CRM_Core_BAO_CustomValueTable::setValues($custom_values);
    } else {
      CRM_Core_Session::setStatus
        ('Konnte nicht Längen und Breitengrad der Adresse bestimmen.', 'Längen/Breitengrad');
    }
  }
}

/**
 * Update all addresses.
 */
function geodaten_update_all() {
  set_time_limit(0);

  // Get all addresses. Query directly against the db instead
  // of using the api for performance reasons.
  $address = CRM_Core_DAO::executeQuery(
    "SELECT id, geo_code_1 as lat, geo_code_2 as lon FROM civicrm_address"
  );

  geodaten_update_from_result_set($address);
}

/**
 * Update addresses without geodata.
 */
function geodaten_update() {
  set_time_limit(0);

  // Get all addresses wihtout geodata. Query directly against the db instead
  // of using the api for performance reasons.
  $address = CRM_Core_DAO::executeQuery(
<<<EOF
SELECT id, geo_code_1 as lat, geo_code_2 as lon FROM civicrm_address
WHERE id NOT IN (
  SELECT entity_id FROM geodaten
  WHERE rs NOT LIKE ''
)
EOF
  );

  geodaten_update_from_result_set($address);
}

/**
 * Update geodata for a result set of a query against civicrm_address.
 */
function geodaten_update_from_result_set($address) {
  $custom_fields = geodaten_get_custom_fields_from_table('geodaten');

  if ($address->N == 0) {
    break;
  }

  // Walk through all addresses and create geodata entity
  while($address->fetch()) {
    if (!empty($address->lat) && !empty($address->lon)) {
      // Values for api call to create set of geodata
      // information.
      $values = array('entity_id' => $address->id);

      // Get geodata and add geodata to values.
      $geodata = geodaten_get_geodata($address->lat, $address->lon);
      
      if (!empty($geodata)) {
        foreach ($geodata as $column => $value) {
          $values[$custom_fields[$column]] = $value;
        }
      
        // Save geodata.
        $status = civicrm_api3('CustomValue', 'create', $values);
      } else {
        echo "Could not get geodata for "
        . "$address->id($address->lat,$address->lon)\n";
      }

      sleep(2);
    }
  }
}

/**
 * Get extra address information about address from lat/lon.
 *
 * Uses the WFS service of the Bundesamt for Geodasie to get RS, Kreis,
 * Regierungsbezirk, Bundesland and Gemeinde.
 */
function geodaten_get_geodata($lat, $lon) {
  $result = array();

  // List of features to request mapping to the id to return in
  // the result set.
  // The Rs is extracted from the last feature in the list
  // that contains an rs.
  $features = array(
    'Bundesland' => 'bundesland',
    'Regierungsbezirk' => 'regierungsbezirk',
    'Kreis' => 'kreis',
    'Gemeinde' => 'gemeinde',
  );

  // Create bounding box from point.
  $bbox = "$lat,$lon,$lat,$lon,EPSG:4326";

  // Create urls for features. The WFS in use allows
  // only one feature per request. So create an url for each feature.
  $urls = array();
  foreach (array_keys($features) as $feature) {
    $urls[] = 'http://sg.geodatenzentrum.de/wfs_vg250?'
    . http_build_query(array(
      'request' => 'GetFeature',
      'service' => 'wfs',
      'version' => '1.2.0',
      'typeName' => "vg250:$feature",
      'propertyName' => 'vg250:GEN,vg250:RS',
      'bbox' => $bbox
    ));
  }

  // Create a curl multihandler
  // and curl handlers for urls to it.
  $mh = curl_multi_init();
  $chs = array();
  foreach ($urls as $url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_multi_add_handle($mh, $ch);
    $chs[] = $ch;
  }

  // Wait for all curl handlers to finish.
  do {
    curl_multi_exec($mh, $running);
  } while($running > 0);

  
  // Parse result of curl handlers.
  foreach ($chs as $ch) {
    $content = curl_multi_getcontent($ch);
    curl_multi_remove_handle($mh, $ch);

    $xml = simplexml_load_string($content);

    if ($xml !== FALSE) {
      foreach ($xml->children('gml', 'true') as $feature_member) {
        if ($feature_member->getName() == 'featureMember') {
          foreach ($feature_member->children('vg250', true) as $feature) {
            if (isset($features[$feature->getName()])) {
              if (!empty($feature->GEN[0])) {
                $result[$features[$feature->getName()]] = (string) $feature->GEN[0];
              }
              if (!empty($feature->RS[0])) {
                $result['rs'] = (string) $feature->RS[0];
              }
            }
          }
        }
      }
    }
  }

  return $result;
}

/**
 * Get a map that maps column names to custom field id
 * of custom fields for given table
 *
 * @return array
 *  Array that has the column names of the custom fields
 *  as keys and the matching custom field id as value.
 */
function geodaten_get_custom_fields_from_table($table) {
  $result = array();

  // Get columns of custom fields that are banking data fields.
  $custom_info = civicrm_api3('CustomField', 'get', array(
    'sequential' => 1,
    'return' => 'id,column_name',
    'custom_group_id' => geodaten_get_group_id_from_table($table),
  ));

  foreach ($custom_info['values'] as $field) {
    $result[$field['column_name']] = "custom_{$field['id']}";
  }

  return $result;
}

/**
 * Get the id of an custom group by its table.
 */
function geodaten_get_group_id_from_table($table) {
  // Find sepa custom field group.
  $sepa_group = civicrm_api3('CustomGroup', 'getsingle', array(
     'sequential' => 1,
     'return' => "id",
     'table_name' => $table,
  ));
  if (!is_array($sepa_group) || empty($sepa_group['id'])) {
    throw new Exception('Could not find custom sepa group.');
  }
  
  return $sepa_group['id'];
}

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function geodaten_civicrm_config(&$config) {
  _geodaten_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function geodaten_civicrm_xmlMenu(&$files) {
  _geodaten_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function geodaten_civicrm_install() {
  _geodaten_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function geodaten_civicrm_uninstall() {
  _geodaten_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function geodaten_civicrm_enable() {
  _geodaten_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function geodaten_civicrm_disable() {
  _geodaten_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function geodaten_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _geodaten_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function geodaten_civicrm_managed(&$entities) {
  _geodaten_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function geodaten_civicrm_caseTypes(&$caseTypes) {
  _geodaten_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function geodaten_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _geodaten_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
