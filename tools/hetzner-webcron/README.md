# CopyMyPage: zentraler Hetzner-Web-Cron

Dieser Ordner enthält den auf dem am 05.11.2026 auf `copymypage.de` technisch
abgenommenen Runner aus Issue #185 aufbauenden Webcron. Die Joomla-Offline-Erkennung
wurde am 11.11.2026 lokal ergänzt und mit isolierten Fixtures geprüft; diese
Erweiterung ist noch nicht auf Hetzner abgenommen. Der dort eingerichtete Cronjob
ist derzeit bewusst deaktiviert. Konfiguration und Schlüssel liegen hier als
Vorlagen vor.

Der Cron gehört außerhalb des öffentlich erreichbaren Joomla-Webroots auf den
Server. Er wird separat eingerichtet und ist keine installierbare
Joomla-Erweiterung. Deshalb liegt er unter `tools/`, außerhalb von `src/`,
dessen Unterverzeichnisse das Release-Skript als Erweiterungen verpackt.

## Dateien

| Repository-Datei | Datei im privaten Cron-Verzeichnis | Aufgabe |
| --- | --- | --- |
| `hetzner-webcron.sh` | gleicher Name | Gemeinsame Sperre, Zeitlimit, Start des PHP-Runners. |
| `scheduler-central.php` | gleicher Name | Konfiguration, Task-Erkennung, HTTPS-Aufruf und native Erfolgskontrolle. |
| `cron-config.json.example` | `cron-config.json` | Installationsabhängige Einstellungen; Vorlage ist deaktiviert. |
| `webcron.key.example` | `webcron.key` | Platzhalter für den geheimen Joomla-Web-Cron-Schlüssel. |

`webcron.key` enthält ausschließlich den Wert des Joomla-Web-Cron-Schlüssels:
keine Task-ID und keine vollständige URL. Den echten Wert nur auf dem Zielserver
eintragen, ohne Leerzeichen oder abschließenden Zeilenumbruch. Die Beispieldatei
ist kein verwendbarer Schlüssel. Echte Schlüssel und lokale Konfiguration sind
per `.gitignore` ausgeschlossen.

Der Runner erzeugt folgende Dateien selbst; sie werden weder kopiert noch
versioniert:

- `joomla-webcron.lock`: gemeinsame Prozesssperre; während des Betriebs erhalten.
- `general-cursor`: zuletzt versuchte Task-ID für eine faire Reihenfolge.
- `webcron-executions.log`: angehängte JSON-Protokollzeilen mit UTC-Zeit.
- `pending-request.json`: dauerhafte Sperre bei unklarem Ausführungsergebnis.
- `.central-*`: vorübergehende Antwort- und Cursor-Dateien.

Eine vorhandene `pending-request.json` niemals allein für einen erneuten Versuch
entfernen. Zuerst das tatsächliche Ergebnis anhand von Joomla-Scheduler-Historie,
Task-Zählern, Sperren und fachlichem Zustand klären. Eine laufende Sperrdatei darf
nicht gelöscht oder durch eine neue Datei ersetzt werden.

## Konfiguration und Portabilität

`cron-config.json.example` nach `cron-config.json` im privaten Zielverzeichnis
übernehmen und dort anpassen:

- `joomla_root`: tatsächlicher absoluter Joomla-Pfad; daraus werden die vorhandene
  `configuration.php`, Datenbankverbindung und der Tabellenpräfix gelesen.
- `webcron_url`: HTTPS-Basisroute `…/component/ajax/`, gegebenenfalls mit
  Sprachpräfix, ohne Query-Parameter oder Schlüssel.
- `tasks.native_cleanup`: tatsächliche ID der Routine
  `plg_task_dpcalendar_booking_deletions`.
- `tasks.seat_release`: tatsächliche ID der Routine
  `copymypage.release_reservations`.
- `tasks.system_tasks`: `all_due`; weitere aktive, fällige Aufgaben werden
  dynamisch erkannt. Nicht fällige, deaktivierte und CLI-exklusive Aufgaben
  werden nicht erzwungen. Native Sperren stoppen den Zyklus.
- `runtime` und `binaries`: geprüfte Zeitbudgets und vorhandene Linux-Programme.
- `payment_recovery.enabled`: bleibt `false`. Eine Aktivierung benötigt eine
  gesonderte fachliche und technische Abnahme.

Die IDs `5` und `6` sind der geprüfte Stand von `copymypage.de`, keine universellen
IDs. Auf jeder anderen Installation IDs, Routinen, Intervalle, Ersteller und
Berechtigungen erneut prüfen. Die Konfiguration verändert keine Joomla-Tasks.

Die Übertragbarkeit gilt für entsprechend qualifizierte CopyMyPage-Installationen.
Die Implementierung prüft feste Quellcode-Hashes von Joomla, DPCalendar und
CopyMyPage einschließlich des installierten `PaymentIntegrationContract`.
Abweichende Versionen führen zu `qualified_source`; Hashes nicht ungeprüft
ersetzen oder die Prüfung umgehen. Der gesamte Repository-Stand kann inzwischen
vom damals qualifizierten installierten Stand abweichen.

Vorausgesetzt werden Linux, Bash, PHP CLI mit `mysqli`, `proc_open`, `flock` und
`fsync` sowie `curl`, `flock` und GNU `timeout`. Geprüft wurde PHP 8.4.27 mit
Joomla 6.2.0, DPCalendar 10.12.4 und CopyMyPage 0.0.27. Windows/Git Bash ersetzt
keine Prüfung der Linux-Rechte und Sperrweitergabe.

## Kontrollierte Einrichtung auf einer weiteren Installation

1. Isolierte Tests und Quellcode-Qualifikation der Zielinstallation durchführen.
   Den bestehenden Scheduler bis zur erfolgreichen Abnahme erhalten.
2. Ein eigenes privates Verzeichnis außerhalb des Joomla-Webroots verwenden:
   Verzeichnisrechte `0700`, vier Betriebsdateien `0600`, gleicher Eigentümer wie
   der Cron-Prozess, keine Symlinks. Der Aufruf erfolgt über `/bin/bash`.
3. Beide Vorlagen unter den Betriebsnamen bereitstellen und lokal auf dem
   Zielserver ausfüllen. `enabled` bleibt zunächst `false`; Recovery bleibt aus.
   `CMP_CRON_PHP` kann bei Bedarf den Bootstrap-PHP-Pfad `/usr/bin/php` ersetzen.
4. `php scheduler-central.php config-check` validiert nur das JSON-Schema.
   `php scheduler-central.php preflight` prüft zusätzlich Quellen, Dateirechte und
   den tatsächlichen Scheduler-Zustand lesend. Während Lazy noch aktiv ist, ist
   `scheduler.scheduler.ready` erwartungsgemäß `false`. Fehler und Sperren klären.
5. Erst nach Abnahme kontrolliert umschalten: konkurrierende Trigger ausschließen,
   laufende Tasks auslaufen lassen, Lazy deaktivieren und Web-Cron aktivieren.
   Schlüsselübereinstimmung, Task-Logging, Plugins und `ready: true` nachweisen.
   Anschließend den Runner mit `enabled: true` für den freigegebenen Test starten.
6. Genau einen exklusiven Hetzner-Job im Fünf-Minuten-Takt einrichten, zum Beispiel:

   ```cron
   */5 * * * * /bin/bash /ABSOLUTER/PRIVATER/PFAD/hetzner-webcron.sh
   ```

   Manuelle und automatische Läufe anhand nativer Scheduler-Historie und
   Protokoll abnehmen. Keine parallelen Lazy-, CLI- oder weiteren Web-Cron-Trigger.

Direkte Aufrufe `php scheduler-central.php run` ersetzen den Shell-Aufruf nicht:
der Runner verlangt den geerbten gesperrten Dateideskriptor und die passende
Konfigurations-Prüfsumme. HTTPS-Aufrufe verwenden POST, den Schlüssel aus der
Datei, keine Weiterleitung und keine blinden Wiederholungen. Datenbankzugriffe
des Runners sind lesend; Änderungen entstehen ausschließlich über native Tasks.

Bei einer Rückkehr zum Lazy Scheduler zuerst den Hetzner-Job deaktivieren und
laufende Prozesse vollständig auslaufen lassen. Unklare HTTP-Ergebnisse und
native Sperren vor einer Wiederaufnahme klären. Danach Runner deaktivieren und
Joomla kontrolliert zurückstellen; keine parallele Verarbeitung zulassen.

## Joomla-Offline-Modus

Ursache des bisherigen Fehlers: `database()` behandelte ein gesetztes
`JConfig::$offline` pauschal als `site_config`. Jetzt wird die tatsächliche
`configuration.php` gelesen und validiert. Für `offline` sind ausschließlich
`false`, `true`, `0`, `1`, `'0'` und `'1'` zulässig. Fehlende, syntaktisch defekte
oder unvollständige Konfigurationen bleiben technische Fehler; ein HTTP 503 ist
kein Offline-Nachweis.

Nach Quellcode-Qualifikation und Prüfung der bestehenden Pending-Sperre wird der
Scheduler-Zustand wie bisher lesend ermittelt. Konfigurationsfehler, native Sperren
und andere Schutzverletzungen haben Vorrang. Zeigt dieser Anfangszustand Joomla
offline, endet der Zyklus vor der Task-Verarbeitung: kein HTTPS-Aufruf, kein neuer
Cursor und keine neue `pending-request.json`, auch bei freigegebener Recovery.
Eine vorhandene Pending-Datei bleibt unverändert und führt weiterhin zu
`pending_request` mit Runner- und Wrapper-Exitcode `22`.

Für die eindeutige Übergabe ohne zusätzliche Zustandsdatei verwendet der PHP-Runner
intern Exitcode `10` für `site_offline`. `cycle_end.exit` enthält diesen tatsächlichen
Runner-Code. Der Shell-Wrapper setzt ihn auf den regulären externen Exitcode `0`
um und protokolliert ebenfalls `site_offline`. Monitoring muss diesen internen
Code als reguläre Aussetzung behandeln; alle bisherigen Exitcodes bleiben gleich.

Beispielhafte JSON-Zeilen (Zeit, Run-ID und Dauer sind illustrative Werte):

```json
{"utc":"2026-11-11T12:00:00Z","run":"20261111T120000Z-1234-abcdef","event":"cycle_end","result":"success","exit":0,"general_completed":1,"general_errors":0,"remaining_due":0,"duration_s":0.8}
{"utc":"2026-11-11T12:05:00Z","run":"20261111T120500Z-1235-fedcba","event":"cycle_end","result":"site_offline","exit":10,"general_completed":0,"general_errors":0,"remaining_due":null,"duration_s":0.02}
{"utc":"2026-11-11T12:05:00Z","event":"wrapper_end","result":"site_offline","exit":0,"lock_acquired":1,"duration_s":0}
```

`preflight` ergänzt das bestehende JSON um das boolesche Feld `site_offline`.
Auch `scheduler.site_offline` zeigt den aus der Konfiguration gelesenen Zustand;
Scheduler-Inventar, `scheduler.scheduler.ready` und `pending_request` bleiben
sichtbar. Offline allein lässt Preflight erfolgreich enden. Fehlerhafte Quellen
oder Konfigurationen werden weiterhin als Fehler ausgegeben. Preflight schreibt
keine Zustandsdateien und löst keine Tasks aus.

Jeder neue Cronprozess liest die Joomla-Konfiguration erneut. Online läuft der
nächste reguläre Zyklus ohne Sonderfreigabe oder neue Zustandsdatei wie bisher.
Der Zustand ist eine Momentaufnahme am Zyklusanfang. Wird Joomla erst während
eines laufenden Zyklus offline geschaltet, bleibt ein bereits begonnener Aufruf
der bisherigen HTTP- und nativen Erfolgskontrolle unterworfen: `https_uncertain`
oder `history_mismatch` werden niemals nachträglich zu `site_offline` umgedeutet.
Unklare Ergebnisse behalten die Pending-Sperre; es gibt keine automatische
Wiederholung. Für eine Wartungsumschaltung den Cron zuerst deaktivieren und einen
laufenden Zyklus auslaufen lassen.

## Lokale Regressionstests der Offline-Erweiterung

Die historischen, unten genannten Testskripte waren im Repository nicht enthalten.
Die neue Suite liegt unter `tests/` und benötigt keine echte Joomla-Installation,
keine Datenbankverbindung und keinen Netzwerkzugriff:

```sh
php tests/offline-test.php
bash -n hetzner-webcron.sh
bash -n tests/wrapper-test.sh
bash tests/wrapper-test.sh
```

Die PHP-Suite prüft echte Konfigurationsdateien in frischen Unterprozessen,
`runCycle()`, Pending-Dateien, native Historienbewertung und Hash-Prüfung mit
synthetischen Daten. Für `snapshot()` und `preflight()` ersetzt eine temporäre
Runner-Kopie ausschließlich die Umgebungsgrenzen `config()`, `database()` und
`qualifySources()` sowie den qualifizierten Fixture-Hash. SQL-Auswahl und
Scheduler-/Offline-Prüfungen bleiben unverändert. Ein `mysqli`-Testdouble liefert
die synthetischen Zeilen ohne Verbindung; daher wird die PHP-Erweiterung `mysqli`
auch für diese Suite benötigt. Keine produktiven Hashes werden angepasst.

Abgedeckt sind Online → Offline → Online, keine Task-/Pending-Erzeugung offline,
vorrangige Pending- und native Sperren, HTTP 503 nach Requeststart, weiterhin
sichtbarer `history_mismatch`, fehlende/defekte Konfiguration, Quellcodefehler
und lesender Preflight einschließlich Offline plus Pending. Temporäre Dateien
liegen ausschließlich in einem neuen Verzeichnis im System-Temp und werden
nach dem Test entfernt.

Die Wrapper-Suite führt den tatsächlichen Wrapper mit simuliertem PHP, `flock`
und `timeout` aus. Sie prüft Offline → extern `0`, unveränderte Fehlercodes,
Lock-Deskriptor/Argumente, UTC-Protokoll, Konfigurationsfehler vor dem Start und
ausbleibenden Runner-Start bei belegter Sperre. Das ersetzt keinen Linux-Test
der Kernel-Sperre, Dateirechte, Deskriptor-Vererbung oder des harten Zeitlimits.

Lokaler Stand vom 11.11.2026: PHP 8.4.0, Git Bash 5.2.37; PHP-Lint für alle drei
PHP-Dateien, PHP-Fixture-Suite und zehn Wrapper-Fälle erfolgreich. ShellCheck und
ein nutzbares Linux/WSL waren nicht verfügbar. Native PHP-Dateisperren wurden
lokal geprüft; die erneute Linux-Abnahme bleibt vor Inbetriebnahme erforderlich.

## Kontrollierte Übernahme auf copymypage.de

Erst nach gesonderter Freigabe; bis dahin bleibt der Hetzner-Job deaktiviert:

1. Laufende Prozesse ausschließen. Aktuelle Betriebsdateien sichern und mit den
   lokalen Änderungen vergleichen. Vorhandene Pending-Sperren fachlich klären;
   Lock-, Cursor-, Log-, Konfigurations- und Schlüsseldateien erhalten.
2. In einer privaten, isolierten Linux-Fixture die Suite und echte Prüfungen für
   Rechte, exklusive Konkurrenz, FD-Vererbung und Hard-Timeout durchführen.
   Kein Provider-/SMTP-I/O und keine produktiven Task-Aufrufe für diese Tests.
3. Nur `scheduler-central.php` und `hetzner-webcron.sh` ins vorhandene private
   Cron-Verzeichnis übernehmen; LF, Eigentümer und Betriebsrechte nach obigem
   Vertrag prüfen. Keine Joomla-/DPCalendar-Dateien oder Quellcode-Hashes ändern.
4. Bei weiterhin deaktiviertem Job PHP-Lint, `bash -n`, `config-check` und den
   lesenden `preflight` ausführen. `site_offline`, Scheduler-Bereitschaft,
   Quellcode-Qualifikation und `pending_request` kontrollieren. Bei Fehlern stoppen.
5. Monitoring auf den internen Offline-Code `10` und externen Wrapper-Code `0`
   abstimmen. Einen gesondert freigegebenen Offline-Lauf über den Wrapper abnehmen:
   keine Taskstarts, unveränderte Historie/Cursor, keine neue Pending-Datei,
   passende `cycle_end`-/`wrapper_end`-Zeilen.
6. Joomla kontrolliert online schalten. Erst nach Freigabe einen einzelnen
   Online-Lauf mit nativer Erfolgskontrolle abnehmen und anschließend den
   bestehenden Hetzner-Job kontrolliert reaktivieren. Keine parallelen Trigger.

Diese Schritte wurden im Rahmen der lokalen Implementierung nicht ausgeführt.
`fernbreitenbacher-carneval.de` ist nicht betroffen.

## Abnahmestand und Betrieb

Vor der Aufnahme ins Repository bereits nachgewiesen:

- 118 synthetische PHP-Prüfungen und 11 Shell-Wrapper-Prüfungen.
- 6 echte Linux-Prüfungen für Rechte, Sperrweitergabe, Konkurrenz, Timeout und
  dauerhafte Pending-Sperre in einer separaten Test-Fixture.
- 27 echte Joomla-/MySQL-/HTTP-Prüfungen in der isolierten lokalen Testinstallation
  mit synthetischen Buchungen und simulierten Payment-/SMTP-Grenzen.
- Ein manueller und drei automatische öffentliche Hauptläufe mit fünf nativen
  Erfolgsnachweisen für Tasks #5 und #6; Recovery blieb deaktiviert.

Diese historischen Nachweise ersetzen keine Abnahme einer anderen Installation.
Die spätere natürliche Ausführung der täglichen und monatlichen Systemtasks
war beim Abschluss der Umschaltung noch als Monitoring-Schritt offen.

Bei Fünf-Minuten-Polling kann eine Fünf-Minuten-Aufgabe knapp noch nicht fällig
sein und erst im nächsten Slot laufen. Für Task #6 wurde dadurch ein Abstand
von zehn Minuten beobachtet. Ein garantiertes Fünf-Minuten-Ausführungsintervall
ist mit diesem Stand nicht zugesichert.

`cycle_end`, `wrapper_end`, Fehler, ausbleibende Läufe, `remaining_due`, native
Task-Historie und eine eventuell vorhandene Pending-Sperre überwachen. Nach
Updates die Quellcode-Qualifikation erneut prüfen. Ein externer Alarmkanal und
automatische Logrotation sind in diesem Stand nicht eingerichtet. Bei
unverändertem Fünf-Minuten-Takt sind etwa 9–11 MB zusätzliches Protokoll pro Monat
zu erwarten. Archivierung und Leeren mit dem laufenden Cron abstimmen.

SHA-256 des historischen Serverstands vor der Offline-Erweiterung (nicht die
aktuellen Programmdateien):

```text
f6028091b84471b3001348c1281ce2367bc33d60ab748d17d8e8de1ca4609d0e  hetzner-webcron.sh
6091bce0e6aefb5a6bc6e9b55e206a01553c3fba946c3517a530208956a049af  scheduler-central.php
```

Referenzen: [Issue #185](https://github.com/marc-heinrich/CopyMyPage/issues/185),
[verbindliche Projektregeln](../../AGENTS.md).
