[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Version](https://img.shields.io/badge/Symcon%20Version-5.1%20%3E-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![StyleCI](https://styleci.io/repos/185949944/shield?style=flat)](https://styleci.io/repos/185949944)

# IPS-MQTTSync
   DIeses Modul ermöglicht es Daten aus IP-Symcon zu MQTT zu pushen oder Scripte in IP-Symocn über MQTT auszuführen.
 
   ## Inhaltverzeichnis
   1. [Voraussetzungen](#1-voraussetzungen)
   2. [Installation](#3-installation)
   3. [Konfiguration in IP-Symcon](#4-konfiguration-in-ip-symcon)
   4. [Spenden](#5-spenden)
   5. [Lizenz](#6-lizenz)
   
## 1. Voraussetzungen

* mindestens IPS Version 5.1
* MQTT Server (IPS Modul) 


## 2. Installation
Über den IP-Symcon Module Store.

## 3. Konfiguration in IP-Symcon

Die Instanz (MQTTSync) wird unter Kern Instanzen angelegt.
Dort kann ein MQTTTopic vergeben werden, Standard ist symcon.

In der Liste können verschiedene Objekte mit einem dazugehörigem Topic angegeben werden.

Wird eine Instanz angegeben, wird bei einer Variablenänderung unterhalb der Instanz ein MQTT Paket mit allen Variablen, die unterhalb dieser Instanz liegen erzeugt und gepusht.

Beispiel:

``
Topic: mqttsync/symcon/Shelly 1 Test, Payload: [{"ID":19500,"Name":"Status","Value":false},{"ID":57199,"Name":"Leistung","Value":0},{"ID":37912,"Name":"\u00dcbertemperatur","Value":true},{"ID":49144,"Name":"Temperatur","Value":12}]
``

Wir nur eine Variable angegeben, wird die Variable bei einer Änderung über MQTT gepusht.
Beispiel:

``
Topic: mqttsync/symcon/ScriptTest, Payload: {"ID":12345,"Name":"Test Variable","Value":55}
``

Wird ein Script angegeben gibt es zwei Möglichkeiten, das Script kann per MQTT Befehl gestartet werden oder innerhlab eines Scriptes kann ein MQTT Kommando erzeugt werden und per MQTT gepusht werden.

Beispiel Variante 1:

Das Script welches in der Liste hinterlegt wurde, hat das Topic ScriptTest erhalten.
Es wird ein leeres Payload zu dem Topic: mqttsync/symcon/TestScript gepusht.
Nun wird das Script ausgeführt.

Beispiel Variante 2:

Das Script erhält folgenden Inhalt:

``
$Payload['ID'] = 12345;
$Payload['Name'] = 'Test Variable';
$Payload['Value'] = 55;

MQTTSync_sendData(41233,json_encode($Payload));  
``

Wird nun das Script ausgeführt, wird in der Liste nach dem richtigen Topic gesucht und das Payload per MQTT gepusht.
Das würde so aussehen:

``
Topic: mqttsync/symcon/ScriptTest, Payload: {"ID":12345,"Name":"Test Variable","Value":55}
``

## 4. Spenden

Dieses Modul ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:    

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>

## 5. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)