<?php
/**
 * Update geodata for all addresses that have no geodata.
 */
function civicrm_api3_job_Geodataupdate($params) {
  geodaten_update();
}
