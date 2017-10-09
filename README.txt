Test API from cli:
sudo -u www-data php5 ~/builds-from-source/cv/bin/cv api contact.getsporkdata afdeling_id=1 | jq .
echo '{"afdeling_id": 1}' | sudo -u www-data php5 ~/builds-from-source/cv/bin/cv api contact.getsporkdata --in=json | jq .
curl 'http://localhost/oauth2/api/civiapi.json?entity=Contact&action=getsporkdata&afdeling_id=1&key=XXX' -H 'Authorization: Bearer YYY'

Delft = 806867
Gouda = 806976
Rotterdam = 806976