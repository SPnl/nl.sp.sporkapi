<?php

/**
 * Contact.Getsporkdata API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_contact_Getsporkdata_spec(&$spec) {
  $spec['afdeling_id'] = [
    'api.required' => 0,
    'name' => 'afdeling_id',
    'title' => 'Afdeling Id (int)',
    'type' => CRM_Utils_Type::T_INT
  ];
}

function prefixArray(&$item, $key, $prefix) {
  $item = "$prefix$key";
};

function prefixRange($name, $length) {
  $arr = array_fill(0, $length, null);
  array_walk($arr, "prefixArray", ":$name");
  return $arr;
}

function createNamedArray(&$value, $keys, $boolKeys = [], $intKeys = [], $floatKeys = []) {
  if (empty($value)) {
    return [];
  }
  $values = explode("\x01", $value);
  $namedArray = [];
  foreach ($values as &$value) {
    if (empty($value)) {
      continue;
    }
    $combo = array_combine($keys, explode("\x02", $value));
    if ($combo !== false) {
      foreach ($boolKeys as $boolKey) {
        $combo[$boolKey] = (bool)$combo[$boolKey];
      }
      foreach ($intKeys as $intKey) {
        $combo[$intKey] = intval($combo[$intKey]);
      }
      foreach ($floatKeys as $floatKey) {
        $combo[$floatKey] = (float)$combo[$floatKey];
      }
      $namedArray[]= $combo;
    }
  }
  return $namedArray;
}

/**
 * Contact.Getsporkdata API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contact_Getsporkdata($params) {
  $loggedInContactID = CRM_Core_Session::singleton()->getLoggedInContactID();
  if (!array_key_exists('afdeling_id', $params)) {
    throw new API_Exception(/*errorMessage*/ 'Please include afdeling_id' . var_export($params,true), 400);
  }

  preg_match('/^([^:]+):\/\/([^:]+):([^@]+)@([^\/]+)\/([^\?\/]+)\?/', CIVICRM_DSN, $dsn);
  $db = new PDO("{$dsn[1]}:host={$dsn[4]};dbname={$dsn[5]}", $dsn[2], $dsn[3], array(
    PDO::ATTR_PERSISTENT => true,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"
  ));
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $select = [ "SP_Afdeling" => [-1], "SP_Regio" => [-1], "SP_Provincie" => [-1]];
  if (!empty($params['check_permissions'])) {
    if (!CRM_Core_Permission::check("access all contacts (view)")) {
      // if the contact has no drupal contact view access,
      // check the basic users access in toegangsgegevens
      // fetch the contacts (sp-geostelsels) that the user has access to
      // magic variables: 3 = the id of Organization (is a reserved value hat should not change)
      // BTW the civicrm_contact_type shouldn't change either (and if the label change, we should change it here too)
      $stmt = $db->prepare(<<<SQL
SELECT
  contact_sub_type,
  display_name,
  id
FROM
  civicrm_contact
JOIN
  (
    SELECT
      CAST(CONCAT('%', x'01', name, x'01', '%') AS BINARY) AS contact_sub_names
    FROM
      civicrm_contact_type
    WHERE
      parent_id = 3 AND
      is_active AND
      BINARY label IN ('SP-Afdeling', 'SP-Regio', 'SP-Provincie')
  ) alias
  ON BINARY contact_sub_type LIKE contact_sub_names
WHERE
  id IN (
    SELECT
      toegang_tot_contacten_van
    FROM
      civicrm_value_toegangsgegevens
    WHERE
      type = 'AfdelingMember' AND
      parent_id IS NULL AND
      (link IS NULL OR link = 'OR') AND
      group_id IS NULL AND
      entity_id = :contactId
  ) AND
  contact_type = 'Organization';
SQL
      );
      $stmt->bindParam(':contactId', $loggedInContactID, PDO::PARAM_INT);
      $stmt->execute();
      $values = $stmt->fetchAll();
      foreach ($values as $row) {
        foreach ($select as $key => $value) {
          if (strpos($row['contact_sub_type'], "\x01$key\x01") !== false) {
            $select[$key][] = (int)$row['id'];
          }
        }
      }
    } else {
      $select['ViewAll'] = true;
    }
  } else {
    $select['ViewAll'] = true;
  }
  $afdelingParams = prefixRange('afdeling', count($select['SP_Afdeling']));
  $afdelingParamList = implode($afdelingParams, ',');

  $regioParams = prefixRange('regio', count($select['SP_Regio']));
  $regioParamList = implode($regioParams, ',');

  $provincieParams = prefixRange('provincie', count($select['SP_Provincie']));
  $provincieParamList = implode($provincieParams, ',');

  //--  CASE WHEN c.is_deceased AND c.deceased_date THEN c.deceased_date ELSE c.is_deceased END AS deceased2,
  //-- this way gender uses expression_cache, which is fast
  //-- for some reason we cannot get civicrm_option_value nor civicrm_location_type, civicrm_membership_status and civicrm_membership_type materialized / cached..
  //-- CREATE INDEX BWBloose_index_scan ON civicrm_log (entity_table, entity_id, modified_date); -- created the index to do a loose index scan on civicrm_log
  $stmt2 = $db->prepare(<<<SQL
SELECT
  c.id,
  (SELECT gov.label FROM civicrm_option_value gov JOIN civicrm_option_group gog ON gog.name = 'gender' AND gog.is_active AND gov.option_group_id = gog.id AND gov.is_active WHERE gov.value = c.gender_id) AS gender,
  vl.voorletters_1 AS initials,
  c.first_name,
  c.middle_name,
  c.last_name,
  c.display_name,
  c.birth_date AS birthday,
  COALESCE(NULLIF(c.is_deceased, 1), c.deceased_date, 1) AS deceased,
  c.is_deceased,
  (c.is_deceased = 1 AND COALESCE(c.deceased_date, MAX(ll.modified_date)) > NOW() - interval 6 month) AS deceased_recent,
  c.do_not_email,
  c.do_not_phone,
  c.do_not_mail,
  c.do_not_sms,
  c.is_opt_out,
  GROUP_CONCAT(DISTINCT CONCAT(ltp.display_name, x'02', ptov.label, x'02', p.phone, x'02', p.phone_numeric, x'02', COALESCE(p.phone_ext,''), x'02', p.is_primary) SEPARATOR x'01') AS phone,
  GROUP_CONCAT(DISTINCT CONCAT(lte.display_name, x'02', e.email, x'02', e.is_primary, x'02', e.is_billing, x'02', e.on_hold, x'02', e.is_bulkmail) SEPARATOR x'01') AS email,
  GROUP_CONCAT(DISTINCT CONCAT(lta.display_name, x'02', a.is_primary, x'02', a.is_billing, x'02', COALESCE(a.street_address, ''), x'02', COALESCE(a.street_name, ''), x'02', COALESCE(a.street_number, -1), x'02', COALESCE(a.street_unit, ''), x'02', COALESCE(a.postal_code, ''), x'02', COALESCE(a.city, ''), x'02', COALESCE(a.geo_code_1, -1), x'02', COALESCE(a.geo_code_2, -1), x'02', COALESCE(va.gemeente_24, ''), x'02', COALESCE(va.buurt_25, ''), x'02', COALESCE(va.buurtcode_26, '')) SEPARATOR x'01') AS address,
  GROUP_CONCAT(DISTINCT CASE WHEN mt.name IN ('Lid SP', 'Lid SP en ROOD') THEN CONCAT(m.join_date, x'02', m.start_date, x'02', m.end_date, x'02', ms.name) END SEPARATOR x'01') AS membership_normal,
  GROUP_CONCAT(DISTINCT CASE WHEN mt.name IN ('Lid SP en ROOD', 'Lid ROOD') THEN CONCAT(m.join_date, x'02', m.start_date, x'02', m.end_date, x'02', ms.name) END SEPARATOR x'01') AS membership_youth,
  GROUP_CONCAT(DISTINCT CONCAT(g.id, x'02', g.title) SEPARATOR x'01') AS groups,
  aa.actief_182 AS active,
  aa.activiteiten_183 AS activities,
  c.modified_date AS modified
FROM
  civicrm_contact c
LEFT JOIN
  civicrm_value_migratie_1 vl ON c.id = vl.entity_id
LEFT JOIN
  civicrm_value_geostelsel geo ON c.id = geo.entity_id
LEFT JOIN
  civicrm_phone p ON c.id = p.contact_id
LEFT JOIN
  civicrm_option_group ptog ON ptog.name = 'phone_type' AND ptog.is_active
LEFT JOIN
  civicrm_option_value ptov ON ptov.option_group_id = ptog.id AND p.phone_type_id = ptov.value AND ptov.is_active
LEFT JOIN
  civicrm_location_type ltp ON ltp.id = p.location_type_id AND ltp.is_active
LEFT JOIN
  civicrm_email e ON c.id = e.contact_id
LEFT JOIN
  civicrm_location_type lte ON lte.id = e.location_type_id AND lte.is_active
LEFT JOIN
  civicrm_address a ON c.id = a.contact_id
LEFT JOIN
  civicrm_location_type lta ON lta.id = a.location_type_id AND lta.is_active
LEFT JOIN
  civicrm_value_adresgegevens_12 va ON va.entity_id = a.id
LEFT JOIN
  civicrm_membership m ON m.contact_id = c.id
LEFT JOIN
  civicrm_membership_type mt ON m.membership_type_id = mt.id AND mt.is_active AND mt.name IN ('Lid SP', 'Lid SP en ROOD', 'Lid ROOD')
LEFT JOIN
  civicrm_membership_status ms ON ms.id = m.status_id
LEFT JOIN
  civicrm_group_contact gc ON gc.group_id IN (2658, 6514) AND c.id = gc.contact_id AND status = 'Added'
LEFT JOIN
  civicrm_group g ON gc.group_id = g.id AND g.is_active AND g.id IN (2658, 6514)
LEFT JOIN
  civicrm_value_actief_sp_62 aa ON aa.entity_id = c.id
LEFT JOIN
  civicrm_log ll FORCE INDEX(BWBloose_index_scan) ON ll.entity_id = c.id AND ll.entity_table = 'civicrm_contact'
WHERE
  c.is_deleted = 0
  AND c.contact_type = 'Individual'
  AND
  (
    geo.afdeling IN ($afdelingParamList)
    OR
    geo.regio IN ($regioParamList)
    OR
    geo.provincie IN ($provincieParamList)
    OR
    :viewAll
  ) AND
  geo.afdeling = :afdelingId
GROUP BY
  c.id
HAVING
  BIT_OR(
    m.end_date > NOW() - interval 6 month
    OR
    g.id IS NOT NULL
  )
  AND
  (c.is_deceased = 0 OR deceased_recent = 1);
SQL
  );
  $viewAll = !empty($select['ViewAll']);
  // We would like to use PARAM_BOOL here, but the MySQL PDO seems broken, see https://bugs.php.net/bug.php?id=66632
  $stmt2->bindParam(':viewAll', $viewAll, PDO::PARAM_INT);
  $afdelingId = (int)$params['afdeling_id'];
  $stmt2->bindParam(':afdelingId', $afdelingId, PDO::PARAM_INT);
  for ($i = 0; $i < count($select['SP_Afdeling']); $i++) { 
    $stmt2->bindParam(":afdeling$i", $select['SP_Afdeling'][$i], PDO::PARAM_INT);
  }
  for ($i = 0; $i < count($select['SP_Regio']); $i++) { 
    $stmt2->bindParam(":regio$i", $select['SP_Regio'][$i], PDO::PARAM_INT);
  }
  for ($i = 0; $i < count($select['SP_Provincie']); $i++) { 
    $stmt2->bindParam(":provincie$i", $select['SP_Provincie'][$i], PDO::PARAM_INT);
  }
  $stmt2->execute();
  //$stmt2->debugDumpParams();
  $values = $stmt2->fetchAll();
  foreach ($values as &$value) {
    if ($value['deceased'] === '0' || $value['deceased'] === '1') {
      $value['deceased'] = (bool)$value['deceased'];
    }
    unset($value['is_deceased']);
    unset($value['deceased_recent']);
    $value['do_not_email'] = $value['do_not_email'] !== 0;
    $value['do_not_phone'] = $value['do_not_phone'] !== 0;
    $value['do_not_mail'] = $value['do_not_mail'] !== 0;
    $value['do_not_sms'] = $value['do_not_sms'] !== 0;
    $value['is_opt_out'] = $value['is_opt_out'] !== 0;

    $value['phone'] = createNamedArray($value['phone'], ['location', 'type', 'number', 'nummeric', 'ext', 'primary'], ['primary']);
    $value['email'] = createNamedArray($value['email'], ['location', 'email', 'primary', 'billing', 'onHold', 'bulkmail'], ['primary', 'billing', 'onHold', 'bulkmail']);
    $value['address'] = createNamedArray($value['address'], ['location', 'primary', 'billing', 'address', 'streetName', 'houseNumber', 'houseNumberSuffix', 'zipcode', 'city', 'lat', 'lng', 'municipality', 'neighborhood', 'cbscode'], ['primary', 'billing'], ['houseNumber'], ['lat', 'lng']);
    $value['membership_normal'] = createNamedArray($value['membership_normal'], ['join', 'start', 'end', 'state']);
    $value['membership_youth'] = createNamedArray($value['membership_youth'], ['join', 'start', 'end', 'state']);
    $value['groups'] = createNamedArray($value['groups'], ['id', 'title'], [], ['id']);
  }
  return civicrm_api3_create_success($values, $params, 'Contact', 'getsporkdata');
}
