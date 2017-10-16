# Spork API - a CiviCRM extension

## Install

### Composer
```
composer config repositories.spork git git@spork.sp.nl:SP/nl.sp.sporkapi.git
composer require spnl/nl.sp.sporkapi @dev -vvv
```

### Manual

Download a [release](/code/SP/nl.sp.sporkapi/releases).
```
scp nl.sp.sporkapi-*.zip remoteserver:
ssh remoteserver
sudo mv nl.sp.sporkapi-*.zip /installpath-www/sites/default/modules/extensions/
```
Go to `/civicrm/admin/extensions?reset=1`, Ctrl+F `sporkapi`, and enable (`/civicrm/admin/extensions?action=disable&id=nl.sp.sporkapi&key=nl.sp.sporkapi`).

## Database

Some minor extra DB index for some speedup:
```bash
echo 'CREATE INDEX BWBloose_index_scan ON civicrm_log (entity_table, entity_id, modified_date);' | mysql -u civicrmtst test_civicrm -p$(grep -m 1 '^d.*mysql' /home/civicrmtst/public_html/sites/default/civicrm.settings.php | sed -r 's/.*civicrmtst:(.*)@.*/\1/')
```

## Test API

Direct from the cli (using cv or curl and jq for formatting / coloring the JSON):
```
sudo -u www-data cv api contact.getsporkdata afdeling_id=1 | jq .
echo '{"afdeling_id": 1}' | sudo -u www-data cv api contact.getsporkdata --in=json | jq .
curl 'https://***/oauth2/api/civiapi.json?entity=Contact&action=getsporkdata&afdeling_id=1&key=XXX' -H 'Authorization: Bearer YYY' | jq .
# Delft = 806867
# Gouda = 806899
# Rotterdam = 806976
```
