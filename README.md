# Composer install

```
composer config repositories.spork git git@spork.sp.nl:SP/nl.sp.sporkapi.git
composer require spnl/nl.sp.sporkapi @dev -vvv
```

# Manual install

Download a [release](/code/SP/nl.sp.sporkapi/releases).
```
scp nl.sp.sporkapi-*.zip remoteserver:
ssh remoteserver
sudo mv nl.sp.sporkapi-*.zip /installpath-www/sites/default/modules/extensions/
```
Go to `/civicrm/admin/extensions?reset=1`, Ctrl+F `sporkapi`, and enable (`/civicrm/admin/extensions?action=disable&id=nl.sp.sporkapi&key=nl.sp.sporkapi`).

# Add oauth2 client

Add oauth2 client: `/admin/structure/oauth2-servers/manage/main/clients`
Pick a random client secret, e.g. `openssl rand -base64 32 | tr +/ -_ | tr -d =`

# Test API from cli:
sudo -u www-data php5 ~/builds-from-source/cv/bin/cv api contact.getsporkdata afdeling_id=1 | jq .
echo '{"afdeling_id": 1}' | sudo -u www-data php5 ~/builds-from-source/cv/bin/cv api contact.getsporkdata --in=json | jq .
curl 'http://localhost/oauth2/api/civiapi.json?entity=Contact&action=getsporkdata&afdeling_id=1&key=XXX' -H 'Authorization: Bearer YYY'

Delft = 806867
Gouda = 806976
Rotterdam = 806976