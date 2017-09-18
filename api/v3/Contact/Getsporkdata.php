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
  //xdebug_break();
  $loggedInContactID = CRM_Core_Session::singleton()->getLoggedInContactID();
  //$vars = array();
  //CRM_Core_Session::singleton()->getVars(&$vars);
  //check if loggedInContactID has access to afdeling id (via )
  if (!array_key_exists('afdeling_id', $params)) {
    throw new API_Exception(/*errorMessage*/ 'Please include afdeling_id' . var_export($params,true), 400);
  }

  preg_match('/^(\w+):\/\/(\w+):(\w+)@(\w+)\/(\w+)\?/', CIVICRM_DSN, $dsn);
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
          if (strpos($row["contact_sub_type"], "\x01$key\x01") !== false) {
            $select[$key][] = (int)$row["id"];
          }
        }
      }
    } else {
      $select["ViewAll"] = true;
    }
  } else {
    $select["ViewAll"] = true;
  }
  $afdelingParams = prefixRange("afdeling", count($select["SP_Afdeling"]));
  $afdelingParamList = implode($afdelingParams, ",");

  $regioParams = prefixRange("regio", count($select["SP_Regio"]));
  $regioParamList = implode($regioParams, ",");

  $provincieParams = prefixRange("provincie", count($select["SP_Provincie"]));
  $provincieParamList = implode($provincieParams, ",");

  $stmt2 = $db->prepare(<<<SQL
SELECT
  c.id,
  c.display_name
FROM
  civicrm_contact c
LEFT JOIN
  civicrm_value_geostelsel geo ON c.id = geo.entity_id
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
  geo.afdeling = :afdelingId;
SQL
  );
//     $stmt2 = $db->prepare(<<<SQL
// SELECT   c.display_name FROM   civicrm_contact c LEFT JOIN   civicrm_value_geostelsel geo ON c.id = geo.entity_id WHERE   c.is_deleted = 0   AND   ( geo.afdeling IN (-1,806976)     OR     geo.regio IN (-1)     OR     geo.provincie IN (-1)     OR   :viewAll   ) AND   geo.afdeling = :afdelingId LIMIT 74 OFFSET 10
// SQL
//   );
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
  //var_dump($values[73]);
  //var_dump(bin2hex($values[73]['display_name']));
  $returnValues = $values; /*array(
    "where" => $select,
    "rowcount" => $stmt2->rowCount(),
    //"errorcode" => $stmt2->errorCode(),"errorinfo" => $stmt2->errorInfo(),
    //"afdeling_id" => $afdelingId,
    //"output2" => json_encode($values),
    "output" => $values,
    "id" => $loggedInContactID
  );*/
  // Spec: civicrm_api3_create_success($values = 1, $params = array(), $entity = NULL, $action = NULL)
  return civicrm_api3_create_success($returnValues, $params, 'Contact', 'getsporkdata');
}
