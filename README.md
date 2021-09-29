**Wichtig diese Civicrm Erweiterung benötigt die PHP GEOS Erweiterung.**

Diese Erweiterung erweitert Adressen um die folgenden Informationen:

- [Regionalschlüssel](https://de.wikipedia.org/wiki/Amtlicher_Gemeindeschl%C3%BCssel#Regionalschl.C3.BCssel)
- Bundesland
- Regierungsbezirk
- Kreis
- Gemeinde

Diese Informationen finden sich unterhalb aller Adressen als benutzerdefinierte Gruppe. Die Daten werden automatisch nach Eingabe einer neuen Adresse oder Ändern einer bestehende Adresse aktualisiert. Außerdem stellt die Erweiterung zwei Jobs zur Verfügung, die es erlauben die Informationen für bestehende Adressen zu ergänzen oder die Informationen für alle Adressen neu zu bestimmen. Funktioniert nur für Adressen innerhalb Deutschlands. Die Daten werden per AJAX vom [Bundesamt für Kartographie und Geodäsie](http://www.bkg.bund.de/) abgefragt. Große Mengen an Adressen brauchen Zeit, um geocodiert zu werden, die Erweiterung arbeitet mit einer Latenz zwischen Abfragen, um nicht vom Bundesamt für Kartographie und Geodäsie gesperrt zu werden.

###### Sponsor
Diese Erweiterung wurde im Auftrag der [BIVA e.V.](http://www.biva.de) entwickelt.