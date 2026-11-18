# CopyMyPage – Tests und synthetische Fixtures

Diese versionierte Ablage enthält Regressionstests und synthetische Eingabedaten außerhalb der installierbaren Erweiterungen. Die Tests werden unter ihren fachlichen Pfaden gepflegt; Meilensteinindizes verknüpfen sie mit den jeweiligen Aufgaben und Abnahmen.

## Einstieg nach Aufgabe

- [Meilenstein CopyMyPage dev0.0.28](milestones/dev0.0.28.md): Checkout, native Buchungsbereinigung, Sitzplatzfreigabe und Warenkorbuhr.
- [PHP-Serverzeit](com_copymypage/ticket-cart-server-time.php) und [JavaScript-Warenkorbuhr](com_copymypage/ticket-cart-clock.test.cjs): Ablaufzeit und Zeitzonen ohne MySQL.
- [Callback-Bootstrap](com_copymypage/booking-callback-bootstrap.php): Joomla-/DPCalendar-Einstieg mit SQLite im Arbeitsspeicher.
- [Native Cleanup-Integration](com_copymypage/native-dpcalendar-cleanup-mysql.php): echter nativer Task-/Modellablauf mit synthetischen Tabellen und simuliertem Provider-HTTP.
- [Action-Log-Prüfbeleg, Issue #192](com_copymypage/action-log-provider-check.php): JSON-Datentypen, verlustfreie Rekonstruktion und echte Joomla-Ausgabe ohne Datenbankzugriff.
- [Sitzplan-Fixtures](fixtures/seat-layouts/): Testpläne für gezielte lokale Importversuche; keine automatische Installation oder Datenbankbefüllung.

## Quellen und Voraussetzungen

Die hier abgelegten Tests laden CopyMyPage-PHP-Klassen, Formulare und JavaScript-Dateien aus dem benachbarten `src/`. Die autoritative Joomla-Entwicklungsinstallation bleibt maßgeblich für den Produktivcode; vor einer Übernahme von Ergebnissen muss deren Stand mit dem geprüften Repositorystand verglichen werden.

Ausnahmen für die gezielte Prüfung von Issue #192: `action-log-provider-check.php` liest und lädt standardmäßig die autoritative `ReservationCleanupService.php` aus `joomla6`; `--candidate=...` wählt eine andere geprüfte Datei. Der Test führt deren tatsächlichen Audit-Payload-Block mit synthetischen Werten aus und verwendet den Joomla-Helper aus `joomla6_test` (änderbar mit `--joomla-root`). Nur die Sprachplugin-Ermittlung wird im datenbankfreien Test ausgelassen. Die vollständige native Integration prüft denselben Helper einschließlich Sprachplugin-Ermittlung gegen synthetische Tabellen.

`native-dpcalendar-cleanup-mysql.php --cleanup-candidate=...` lädt ausdrücklich diese installierte Cleanup-Klasse aus `joomla6` oder `joomla6_test`. Der ausgegebene Klassenpfad und SHA-256 belegen die Kandidatenwahl. Alle anderen CopyMyPage-Klassen bleiben aus `src/`; `--source-root` wählt weiterhin ausschließlich die nativen Plugins. Es findet kein Quellcode-Sync statt.

Benötigt werden je nach Suite PHP 8.4 mit SQLite beziehungsweise MySQL-Erweiterungen, Node.js sowie installierte Joomla-/DPCalendar-Abhängigkeiten. Referenzumgebung ist Joomla 6.2.0 mit DPCalendar 10.12.4.

`booking-callback-bootstrap.php` und `native-reservation-mysql.php` lesen die Joomla-/DPCalendar-Abhängigkeiten standardmäßig aus `C:/wamp/www/joomla6`. Dafür lässt sich `CMP_TEST_JOOMLA_ROOT` auf einen anderen installierten Bibliotheksstand setzen. Es werden dadurch keine CopyMyPage-Quelldateien aus dieser Installation gewählt.

Die MySQL-Suiten sind projektspezifisch an `C:/wamp/www/joomla6_test`, das getrennte Schema `joomla_db_test` und dessen beschränkten lokalen DB-Benutzer gebunden. `native-dpcalendar-cleanup-mysql.php` lädt Joomla-/DPCalendar-Komponentenklassen aus der Testinstallation; die originalen Task-/PayPal-Plugins kommen standardmäßig aus `joomla6` und können mit `--source-root` zwischen den beiden vorgesehenen Installationen gewählt werden. Diese Pfadwahl betrifft die nativen Plugins, während CopyMyPage aus `src/` geladen wird.

Eine frische Repositorykopie allein enthält die Joomla-/DPCalendar-Laufzeitbibliotheken nicht. Bei fehlenden Abhängigkeiten zuerst die dokumentierte lokale Umgebung bereitstellen; keine produktiven Konfigurationsdateien oder Zugangsdaten in `test/` ablegen.

## Ausführung aus dem Repository-Hauptverzeichnis

Offline-JavaScript-Test mit kontrollierter Uhr und simuliertem DOM:

```powershell
& 'C:\Program Files\nodejs\node.exe' --test test/com_copymypage/ticket-cart-clock.test.cjs
```

PHP-Tests ohne persistente Joomla-Datenbank:

```powershell
$cmpTestPhp = 'C:\wamp\bin\php\php8.4.0\php.exe'
$cmpTestDisabledIo = 'curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail'

& $cmpTestPhp -d xdebug.mode=off -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d "disable_functions=$cmpTestDisabledIo" test/com_copymypage/ticket-cart-server-time.php
& $cmpTestPhp -d xdebug.mode=off -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d "disable_functions=$cmpTestDisabledIo" test/com_copymypage/booking-callback-bootstrap.php
```

Erwartung: Exitcode `0`, bestandene Assertions beziehungsweise `PASS`-Ausgaben und keine ausgehenden Provider-/Mailaufrufe. Der Callback-Test ersetzt Buchungsmodell und SQL-Locks durch Fixtures; er qualifiziert keine MySQL-Sperren.

Gezielte, datenbankfreie Action-Log-Regression mit dem installierten Kandidaten:

```powershell
& $cmpTestPhp -d xdebug.mode=off -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d "disable_functions=$cmpTestDisabledIo" test/com_copymypage/action-log-provider-check.php
```

Erwartung: elf bestandene Fälle für strukturierte, skalare, fehlende und `null`-Prüfbelege, verlustfreies Dekodieren, beide Joomla-Ausgabemodi, Sonderzeichen und Fehler bei ungültigem UTF-8. Dieser Test allein prüft keine Transaktionen oder vollständigen Dashboard-Routen.

Die folgenden Befehle sind **schreibende Integrationsläufe**. Vor ihrer Ausführung sind ein entsprechender Testauftrag und eine frisch geprüfte Isolation nach `AGENTS.md`, Abschnitt 2, erforderlich. Die Suiten erzeugen und entfernen zufällig präfixierte synthetische Tabellen. `native-reservation-mysql.php` startet außerdem eigene PHP-Testworker; das muss vom Testauftrag umfasst sein.

```powershell
& $cmpTestPhp -d xdebug.mode=off -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d "disable_functions=$cmpTestDisabledIo" test/com_copymypage/reservation-cleanup-mysql.php
& $cmpTestPhp -d xdebug.mode=off -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d "disable_functions=$cmpTestDisabledIo" test/com_copymypage/native-reservation-mysql.php
& $cmpTestPhp -d xdebug.mode=off -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d "disable_functions=$cmpTestDisabledIo" test/com_copymypage/native-dpcalendar-cleanup-mysql.php --driver=mysqli
```

Für den nativen Cleanup-Treiber ist auch `--driver=mysql` für PDO MySQL vorgesehen. `--checkout-only` begrenzt den Lauf auf die Checkout-/Scheduler-Matrix. [native-checkout-scheduler-matrix.php](com_copymypage/native-checkout-scheduler-matrix.php) wird durch diesen Treiber eingebunden und ist kein eigenständig startbarer Test.

Für die vollständige Issue-#192-Regression den korrigierten Kandidaten ausdrücklich auswählen; die Suite prüft die tatsächlich gespeicherten Action-Logs und einen echten Audit-Serialisierungsfehler mit vollständigem Rollback. Der ungültige UTF-8-Wert wird nur in einer temporären synthetischen Tabelle erzeugt. Beide Treiber nacheinander prüfen:

```powershell
$cmpCleanupCandidate = 'C:/wamp/www/joomla6/components/com_copymypage/src/Service/ReservationCleanupService.php'
& $cmpTestPhp -d xdebug.mode=off -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d "disable_functions=$cmpTestDisabledIo" -d session.save_path=C:/wamp/www/joomla6_test/tmp test/com_copymypage/native-dpcalendar-cleanup-mysql.php --driver=mysqli "--cleanup-candidate=$cmpCleanupCandidate"
& $cmpTestPhp -d xdebug.mode=off -d zend.exception_ignore_args=1 -d allow_url_fopen=0 -d "disable_functions=$cmpTestDisabledIo" -d session.save_path=C:/wamp/www/joomla6_test/tmp test/com_copymypage/native-dpcalendar-cleanup-mysql.php --driver=mysql "--cleanup-candidate=$cmpCleanupCandidate"
```

Der optionale `--runner`-Pfad muss auf einen separat vorhandenen, geprüften zentralen Runner zeigen. Der lokale Runner aus `docs/` wird hier nicht mitgeliefert. Die Kernintegration verwendet simulierten Transport und ersetzt keine Qualifikation des realen Hetzner-Wrappers.

## Ergebnisse und Pflege

Gemessene Ergebnisse gehören mit Datum, Quellenstand, Treiber, Testdaten und simulierten beziehungsweise echten Schnittstellen in den zugehörigen Meilensteinindex. Ein historisch bestandener Test belegt keine erneute Ausführung nach einer Änderung.

`test/` ist für Git vorgesehen und bleibt außerhalb des Build-Eingangs `src/`. Lokale Konfigurationen sowie erzeugte Dateien unter `test/artifacts/`, `test/coverage/` und `test/results/` bleiben über `.gitignore` ausgeschlossen. Bestehende Tests unter `tools/hetzner-webcron/tests/` bleiben an ihrem bisherigen, separat dokumentierten Platz.
