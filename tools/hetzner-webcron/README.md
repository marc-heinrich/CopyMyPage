# CopyMyPage: zentraler Hetzner-Web-Cron

Private Betriebsprogramme für den regulären Joomla-Webcron: gemeinsame Sperre,
begrenzte Task-Verarbeitung über HTTPS, native Erfolgskontrolle, Überwachung
und Logrotation. Die Quellprogramme und allgemeinen Vorlagen gehören ins
Repository; echte Konfigurationen, Zustandsdateien und installationsbezogene
Bestandsaufnahmen bleiben außerhalb der Versionierung.

Abnahmen, Betriebsstände und historische Nachweise werden in den jeweiligen
GitHub-Issues und Meilensteinen geführt:
[Cron-Architektur #185](https://github.com/marc-heinrich/CopyMyPage/issues/185),
[native Buchungslöschung #189](https://github.com/marc-heinrich/CopyMyPage/issues/189).
Diese Anleitung ist keine Produktivfreigabe.

Der Cron gehört außerhalb des öffentlich erreichbaren Joomla-Webroots auf den
Server. Er wird separat eingerichtet und ist keine installierbare
Joomla-Erweiterung. Deshalb liegt er unter `tools/`, außerhalb von `src/`,
dessen Unterverzeichnisse das Release-Skript als Erweiterungen verpackt.

## Dateien

| Repository-Datei | Datei im privaten Cron-Verzeichnis | Aufgabe |
| --- | --- | --- |
| `hetzner-webcron.sh` | gleicher Name | Gemeinsame Sperre, Zeitlimit, Start des PHP-Runners. |
| `scheduler-central.php` | gleicher Name | Konfiguration, Task-Erkennung, HTTPS-Aufruf und native Erfolgskontrolle. |
| `cron-operations.php` | gleicher Name | Gemeinsam gesperrtes Log, Rotation, lokaler Zustand und optionaler externer Alarmanschluss. |
| `cron-config.json.example` | `cron-config.json` | Installationsabhängige Einstellungen; Vorlage ist deaktiviert. |
| `operations-config.json.example` | `operations-config.json` | Größen-/Aufbewahrungsgrenzen und zunächst deaktiviertes Monitoring. |
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
- `operations.lock`: gemeinsame Sperre aller Log-/Zustandsschreiber und Rotation.
- `cron-health.json`: atomar ersetzte lokale Betriebsübersicht, kein Retry-Auftrag.
- `webcron-executions.YYYYMMDDTHHMMSSZ-<zufall>.log`: rotierte Segmente.
- `.operations-*`: vorübergehende Zustandsdateien.

Bei freigegebenem externem Monitoring zusätzlich `monitoring.url` lokal auf dem
Zielserver anlegen (0600): eigene HTTPS-UUID-Ping-URL eines unabhängigen
Healthchecks-kompatiblen Wächters. Sie enthält niemals den Joomla-Schlüssel.

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
- `native_cleanup.enabled`: standardmäßig `false`, auch bei alten Konfigurationen
  ohne diesen Abschnitt. Die native Routine wird dann aus der Queue ausgeschlossen.
  Eine gesonderte fachliche Freigabe benötigt zusätzlich den exakt geprüften
  `approved_definition_sha256` aus Preflight. Änderungen an Ersteller, Parametern
  oder Zeitplan sperren die Freigabe wieder. Dies ersetzt keine PayPal-Endprüfung.
  Bereits bestehende Lazy-/CLI-/Webcron-Trigger werden dadurch nicht umgestellt.

Die IDs in der Vorlage sind Beispiele. Auf jeder Installation IDs, Routinen,
Intervalle, Ersteller und Berechtigungen erneut prüfen. Die Konfiguration
verändert keine Joomla-Tasks.

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

## Überwachung und Logrotation

Alle neuen Logschreiber und die Rotation verwenden `operations.lock`. Die
aktuelle Datei wird erst unter dieser Sperre geöffnet. Umbenennen ersetzt
Copytruncate; unvollständige letzte Datensätze werden unverändert archiviert.
Keine zweite Rotation oder ein FTP-Leeren derselben Datei einrichten.

`operations-config.json` begrenzt standardmäßig Segmente auf 4 MiB, Archive auf
zwölf Dateien und deren Alter auf höchstens 30 Tage. Die Größen-/Anzahlgrenze
kann die Aufbewahrung verkürzen; vorhandene übergroße Altlogs werden einmal
ungekürzt archiviert. Joomla-Logs werden durch diesen Helfer nicht rotiert.

`php cron-operations.php check` prüft die Operations-Konfiguration lesend.
`php cron-operations.php health` liest den Betriebszustand ohne Schreibzugriff:
Exit 0 bedeutet gesund, Exit 1 einen ungesunden Zustand. Fehlende Abschlüsse,
Task-/Wrapperfehler, überlange Läufe, Backlog, Pending-Sperren und Probleme der
Alarmzustellung werden sichtbar. Fehler des Betriebshelfers nach einem sonst
richtigen Abschluss sind als Wrapper-Exit 23 erkennbar.

Für einen unabhängigen Healthchecks-kompatiblen Wächter `monitor_enabled`
bewusst aktivieren und die eigene HTTPS-UUID-Ping-URL in `monitoring.url`
hinterlegen (0600). Nur ein nachweislich fehlerfreier Abschluss meldet Erfolg;
Fehler melden `/fail`. Es werden keine Start-Pings, Joomla-Schlüssel, Tasklogs
oder Kundendaten versendet. Die geheime URL wird über stdin an curl übergeben,
ohne Redirects und mit maximal fünf Sekunden HTTP-Laufzeit.

Der externe Dienst und sein erreichbarer Alarmkanal müssen separat eingerichtet
und abgenommen werden. Lokales `health` allein erkennt einen vollständigen
Hostausfall nicht von außen. Die bekannte Joomla-Offline-Aussetzung erhält den
regulären Heartbeat; Wartungsdauer separat beobachten.

## Kontrollierte Einrichtung auf einer weiteren Installation

1. Isolierte Tests und Quellcode-Qualifikation der Zielinstallation durchführen.
   Den bestehenden Scheduler bis zur erfolgreichen Abnahme erhalten.
2. Ein eigenes privates Verzeichnis außerhalb des Joomla-Webroots verwenden:
   Verzeichnisrechte `0700`, sechs Betriebsdateien `0600`, gleicher Eigentümer wie
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

## Wiederverwendbare Tests

`tests/` enthält Testquellcode und eine abhängige synthetische Fixture, keine
aufbewahrten Testergebnisse. Die Programme erzeugen ihre Arbeitsdateien in neuen
System-Temp-Verzeichnissen und entfernen sie nach Gebrauch. Es werden keine echte
Joomla-Installation, Datenbankverbindung, Provider- oder SMTP-Zugriffe benötigt.
Die PHP-Suites brauchen auch für das Testdouble die Erweiterung `mysqli`.

| Datei | Prüfzweck |
| --- | --- |
| `tests/offline-test.php` | Konfiguration, Offline-/Online-Wechsel, native Historie, Fehler und verweigerte Wiederholungen. |
| `tests/preflight-fixture.php` | Abhängige Fixture mit synthetischen mysqli-Zeilen für den lesenden Preflight; nur über offline-test.php starten. |
| `tests/operations-test.php` | Alarmprotokoll mit simuliertem Transport, Betriebszustand, Löschfreigabe, Aufbewahrung und echte konkurrierende Dateischreiber. |
| `tests/wrapper-test.sh` | Tatsächlicher Wrapper mit simuliertem PHP, flock und timeout; Exitcodes und Aufrufprotokoll. |
| `tests/linux-test.sh` | Linux-Rechte, echte Kernel-Sperre, Deskriptorvererbung, Konkurrenz und Timeout; synthetischer Runner ohne HTTP. |

Aus diesem Verzeichnis ausführen:

```sh
php tests/offline-test.php
php tests/operations-test.php
bash -n hetzner-webcron.sh
bash -n tests/wrapper-test.sh
bash -n tests/linux-test.sh
bash tests/wrapper-test.sh
```

Die Preflight-Suite ersetzt in einer temporären Runner-Kopie ausschließlich
Umgebungsgrenzen und qualifiziert synthetische Quellen; produktive Hashes werden
nicht verändert. Die Operations-Suite simuliert die Alarmzustellung und prüft
keinen echten externen Alarmkanal.

Auf einem freigegebenen isolierten Linux-System zusätzlich ausführen:

```sh
bash tests/linux-test.sh
```

Windows/Git Bash und der simulierte Wrapper-Test ersetzen diese Linux-Abnahme
nicht. Erfolgreiche Fixtures ersetzen keinen freigegebenen echten Joomla-Webcronlauf
mit Prüfung der nativen Historie und fachlichen Bindungen. Die Testprogramme werden
nicht in das produktive Betriebsverzeichnis hochgeladen.
