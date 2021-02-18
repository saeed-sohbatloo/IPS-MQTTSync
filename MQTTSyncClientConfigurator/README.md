# MQTTSyncClientConfigurator
   Dieses Modul wird dazu benötigt, um auf dem Client System die Instanzen anzulegen, welche die Werte des Master Systems auf dem Slave System widerspiegeln.

   ## Inhaltverzeichnis
   1. [Voraussetzungen](#1-voraussetzungen)
   2. [Konfiguration](#2-konfiguration)
   3. [Spenden](#3-spenden)
   4. [Lizenz](#4-lizenz)
   
## 1. Voraussetzungen

* mindestens IPS Version 5.5
* IP-Symcon System mit konfiguriertem MQTT Sync Server

## 2. Konfiguration in IP-Symcon

* Als Parent wird der MQTT Client von Symcon benötigt, dieser muss so konfiguriert sein, dass er auf den MQTT server des Master IP-Symcons zugreift.
* Das MQTT Topic ist das selbe Topic, welches bei dem MQTT Sync Server auf dem Master System hinterlegt ist.
* Als letztes muss die Konfiguration von dem MQTT Sync Server übernommen werden, dazu muss auf dem IP-Symcon Master System in der Instanz MQTT Sync Server der Button "Daten synchronisieren" im Actions Bereich ausgeführt werden.
* Nun sollte sich die Liste füllen und die Instanzen können über den Konfigurator angelegt werden.

## 3. Spenden

Dieses Modul ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:    

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=EK4JRP87XLSHW" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a> <a href="https://www.amazon.de/hz/wishlist/ls/3JVWED9SZMDPK?ref_=wl_share" target="_blank">Amazon Wunschzettel</a>

## 4. Lizenz

[CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)