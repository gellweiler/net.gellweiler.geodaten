<?php
/**
 * Update geodata for all address records.
 */
function civicrm_api3_job_Geodataupdateall($params) {
  geodaten_update_all();
}
