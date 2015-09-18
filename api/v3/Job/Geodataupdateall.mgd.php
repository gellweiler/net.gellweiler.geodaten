<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// http://wiki.civicrm.org/confluence/display/CRMDOC42/Hook+Reference
return array (
  0 => 
  array (
    'name' => 'Cron:Job.geodataupdateall',
    'entity' => 'Job',
    'params' => 
    array (
      'version' => 3,
      'name' => 'Geodaten für alle Adressen aktualisieren',
      'description' =>
<<<EOF
Diesen Job ausführen, wenn es Änderungen an der Struktur
des Regionalschlüssels oder an den Namen oder Grenzen von Gemeinden,
Kreisen und Bundesländern gibt. Generell ist es sinnvoll, diesen Job
ca. einmal im Jahr auszuführen.
EOF
,
      'run_frequency' => 'Daily',
      'api_entity' => 'Job',
      'api_action' => 'geodataupdateall',
      'parameters' => '',
      'is_active' => 0,
    ),
  ),
);
