# CopyMyPage: zentraler Hetzner-Web-Cron

Dieser Ordner enthält den am 05.11.2026 auf `copymypage.de` technisch
abgenommenen und in die kontrollierte produktive Testphase übernommenen Runner
aus Issue #185. Shell- und PHP-Datei entsprechen bytegenau diesem geprüften
Serverstand. Die Konfiguration und der Schlüssel liegen hier als Vorlagen vor.

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

SHA-256 der unverändert übernommenen Programmdateien:

```text
f6028091b84471b3001348c1281ce2367bc33d60ab748d17d8e8de1ca4609d0e  hetzner-webcron.sh
6091bce0e6aefb5a6bc6e9b55e206a01553c3fba946c3517a530208956a049af  scheduler-central.php
```

Referenzen: [Issue #185](https://github.com/marc-heinrich/CopyMyPage/issues/185),
[verbindliche Projektregeln](../../AGENTS.md).
