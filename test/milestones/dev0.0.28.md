# Testindex – CopyMyPage dev0.0.28

[GitHub-Meilenstein 29](https://github.com/marc-heinrich/CopyMyPage/milestone/29) · [Issue #189: native Bereinigung und Sitzplatzfreigabe](https://github.com/marc-heinrich/CopyMyPage/issues/189) · [Issue #190: Callback-Bootstrap-Regression](https://github.com/marc-heinrich/CopyMyPage/issues/190)

Dieser Index beschreibt den für dev0.0.28 übernommenen Testbestand. Er enthält auch weiterverwendete ältere Regressionen. Die Dateien bleiben an ihren fachlichen Pfaden; spätere Meilensteine können auf dieselben Tests verweisen.

## Testfälle und erwartetes Verhalten

| Testdatei | Zweck und erwartetes Ergebnis | Umgebung / Grenze |
| --- | --- | --- |
| [action-log-provider-check.php](../com_copymypage/action-log-provider-check.php) | Issue #192: kompatible Action-Log-Feldtypen, vollständiger Prüfbeleg und sichere Joomla-Ausgabe in beiden Modi | Installierter Cleanup-Kandidat und Joomla-Helper; synthetischer Audit-Payload, keine DB; Transaktionsprüfung im nativen Cleanup-Treiber |
| [booking-callback-bootstrap.php](../com_copymypage/booking-callback-bootstrap.php) | Callback-Bootstrap und Sitz-/Buchungszuordnung; bestehende Guards greifen vor dem Providerzugriff | Repository-Code, installierte Joomla-/DPCalendar-Klassen, SQLite `:memory:`; simulierte Buchungsmodelle und Locks |
| [native-reservation-mysql.php](../com_copymypage/native-reservation-mysql.php) | Persistente Zahlungsphasen, Callback-/Cleanup-Sperren und Konkurrenz zwischen Requests schützen unklare Zahlungen | Synthetische MySQL-Tabellen, echte Verbindungen und PHP-Testworker; Provider-HTTP simuliert |
| [reservation-cleanup-mysql.php](../com_copymypage/reservation-cleanup-mysql.php) | Gespeicherte Cart-Ablaufzeit ist maßgeblich; native Buchungen und unklare Bindungen bleiben geschützt; Fehler rollen Änderungen vollständig zurück | Isolierte MySQL-Testdatenbank; kein regulärer Joomla-/Provider-/Mailablauf |
| [native-dpcalendar-cleanup-mysql.php](../com_copymypage/native-dpcalendar-cleanup-mysql.php) | Native Task-/Modelllöschung prüft Task-Ersteller und ACL, Zahlungsbefund und Sitzintegrität; sichere Löschung schließt Cart und Inventar atomar ab | Echte native Task-/Modellklassen und MySQL-Verbindungen; synthetische Daten, simuliertes Provider-HTTP |
| [native-checkout-scheduler-matrix.php](../com_copymypage/native-checkout-scheduler-matrix.php) | Abbruch, geschlossener Browser, wiederholter Start, bezahlte/unklare Captures und verspätete Callbacks; optional simulierte zentrale Runner-Kopplung | Wird vom nativen Cleanup-Treiber eingebunden; echte lokale Scheduler-Einstiege mit simulierten äußeren Grenzen |
| [ticket-cart-server-time.php](../com_copymypage/ticket-cart-server-time.php) | UTC-Ablaufzeit und Restsekunden bleiben bei unterschiedlichen Zeitzonen konsistent; abgelaufene Warenkörbe werden abgewiesen | Eigenständiger PHP-Test, SQLite im Arbeitsspeicher und feste Testuhr |
| [ticket-cart-clock.test.cjs](../com_copymypage/ticket-cart-clock.test.cjs) | Browseruhr-Abweichungen, Countdown und erneute Initialisierung; gleicher Vertrag für JavaScript-Source und Min-Datei | Node.js, simuliertes DOM und kontrollierte Uhr; keine Browser-/HTTP-/Reservierungsaktionen |

Startbefehle und Voraussetzungen stehen im [zentralen Test-README](../README.md). MySQL-Läufe sind schreibende Tests und benötigen die dort genannte Isolationsprüfung.

## Synthetische Sitzplan-Fixtures

- [gemeindesaal-test-v1.json](../fixtures/seat-layouts/gemeindesaal-test-v1.json): kleiner Testplan für gezielte lokale Importszenarien.
- [gemeindesaal-2027-hotline-test-v1.json](../fixtures/seat-layouts/gemeindesaal-2027-hotline-test-v1.json): Hotline-Testvariante des Sitzplans 2027.

Beide JSON-Dateien sind unverändert aus dem zuvor ausgelagerten Bestand übernommen. Sie werden nicht automatisch importiert oder in Erweiterungspakete aufgenommen.

## Bisherige Abnahme und verbleibende Grenzen

Der lokale Abnahmebericht vom 15.11.2026 dokumentiert erfolgreiche Checkout-/Scheduler-Integration mit synthetischen Tabellen in `joomla6_test` und simuliertem PayPal-HTTP. Das ist historische Beweislage für die damaligen installierten Quellen; die Umstellung auf diese Repository-Testpfade ist eine gesonderte Änderung.

Eine echte PayPal-Sandbox-Abnahme einschließlich der tatsächlich gelieferten Rückwegfelder, eine vollständige Browser-Checkout-Abnahme und die Qualifikation des realen Hetzner-Runners bleiben separate Aufgaben. Ein späterer Runnerlauf benötigt seinen zum eingesetzten Quellstand passenden Qualifikationsvertrag.

Die MySQL-Suiten und eine reale Scheduler-/Provider-Ausführung werden durch die Ablageänderung nicht automatisch erneut abgenommen.

## Neu geprüft am 16.11.2026

Quellen: lokaler Repository-Arbeitsstand für dev0.0.28 nach Anpassung der Testpfade. Umgebung: PHP 8.4.0, Node.js 24.14.0; der Callback-Test verwendet installierte Joomla-6.2.0-/DPCalendar-10.12.4-Abhängigkeiten aus `joomla6` und CopyMyPage-Klassen aus `src/`.

| Prüfung | Gemessenes Ergebnis | Daten / Schnittstellen |
| --- | --- | --- |
| JavaScript-Warenkorbuhr | 22/22 Tests bestanden | Kontrollierte Uhr und simuliertes DOM; jeweils Source und Min-Datei |
| PHP-Serverzeit | 36/36 Assertions bestanden | UTC, Europe/Berlin und America/New_York; SQLite im Arbeitsspeicher |
| Callback-Bootstrap | 15/15 Tests bestanden | SQLite `:memory:`, Buchungsmodell und Locks simuliert; 0 Provider-/Mailaufrufe |
| PHP-Lint | 6/6 PHP-Dateien bestanden | Ausschließlich Syntaxprüfung |
| CLI-Startoptionen des nativen Cleanup-Treibers | Zwei Kombinationen in unterschiedlicher Reihenfolge bestanden | Optionslisten aus der Testdatei geprüft; Suite und Datenbank nicht gestartet |

Die MySQL-Suiten wurden bei dieser Ablageänderung nicht ausgeführt. Damit liegt noch keine neue MySQL-Abnahme der angepassten Repository-Einstiege vor. Ebenso wurden keine Browser-, echten Zahlungs-, Mail- oder öffentlichen Schedulerabläufe ausgeführt.

## Issue #192: geprüft am 18.11.2026

Geprüfter Produktivcode: `C:/wamp/www/joomla6/components/com_copymypage/src/Service/ReservationCleanupService.php`, SHA-256 `f2d396953687e9e9c67dab3f982027097e3ddb131923ebb89433a694c21e2ac4`. Die Datei enthält ausschließlich den freigegebenen Fix zur JSON-String-Serialisierung von `providerCheck`. Repository-`src/` und die installierte Testkopie wurden nicht synchronisiert; `--cleanup-candidate` lädt ausdrücklich die korrigierte Live-Klasse. Die übrigen CopyMyPage-Klassen kommen aus Repository-`src/`, die nativen Plugins aus `joomla6`, Joomla-/DPCalendar-Komponentenklassen aus `joomla6_test`.

**Testumgebung:** PHP 8.4.0, Joomla 6.2.0, DPCalendar 10.12.4, MySQL 9.1.0. Vor dem Schreiben wurden das Fehlen von Reparse Points einschließlich der Elternpfade, die getrennten Datenbanken und DB-Benutzer sowie ausschließlich auf `joomla_db_test` begrenzte Schreibrechte geprüft. Test-Temp-/Log-/Cache-/Uploadpfade sind innerhalb von `joomla6_test`, Joomla-Sessions verwenden die getrennte Datenbank; die CLI-Fixtures verwenden Sessions und Logger im Arbeitsspeicher. Der PHP-Sessionpfad wurde pro Integrationsprozess auf das Test-Tempverzeichnis begrenzt. Mailversand ist aus, reguläre Test-Scheduler-Aufgaben sind inaktiv, und es wurden keine dazugehörigen Windows-Tasks gefunden. Die Tests erzeugen ausschließlich neue zufällig präfixierte synthetische Tabellen; vorhandene Joomla-Daten werden nicht als Testdaten verändert.

| Prüfung | Gemessenes Ergebnis | Grenze |
| --- | --- | --- |
| Action-Log-Payload und Joomla-Ausgabe | 11/11 Fälle bestanden | Tatsächlicher Audit-Block des Kandidaten; ohne Datenbank, Sprachplugin-Ermittlung ausgelassen |
| Gegenprobe mit unverändertem Repository-Kandidaten | Erwartet mit Exitcode 1 zurückgewiesen | Keine Quellcode-Mutation; strukturierter Top-Level-Wert erkannt |
| Vollständige native Cleanup-/Checkout-/Scheduler-Integration, `mysqli` | 493 Assertions bestanden, Exitcode 0 | Echte native Modelle, Scheduler und MySQL-Verbindungen; synthetische Tabellen |
| Vollständige native Integration, PDO MySQL (`mysql`) | 493 Assertions bestanden, Exitcode 0 | Gleicher korrigierter Kandidat und dieselben fachlichen Fälle |
| PHP-Lint | 4/4 geänderte PHP-Dateien bestanden | Produktivdatei und drei Testdateien |
| Diff-Review | Nur freigegebener Produktiv-Eingriff; übriger Live-Dateiinhalt unverändert | Ergänzungen ausschließlich unter `test/` |

Die nativen Läufe prüfen zusätzlich die tatsächlich gespeicherten Prüfbelege mit dem unveränderten Joomla-Helper in beiden Ausgabemodi. Alle sechs Provider-Prüffelder bleiben erhalten. Strukturierte und skalare synthetische Prüfbelege sind durch zweifaches JSON-Dekodieren vollständig rekonstruierbar; `null` und fehlende Werte bleiben `null`, Sonderzeichen bleiben erhalten und HTML wird escaped. Ungültiges UTF-8 löst im inneren Serializer eine `JsonException` aus. Ein zusätzlicher echter Audit-Serialisierungsfehler nach nativer Löschung rollt Buchung, Tickets, Sitze, Cart, Inventar und Audit vollständig zurück.

Zahlungs-/Capture-Sperren, sichere gegenüber zurückgehaltenen Löschungen, späte Callbacks, Sitzfreigabe, Idempotenz, Konkurrenzsperren und verschachtelte Transaktionen bestehen die vorhandene Matrix. Live-/Sandbox-Endpunkte und Provider-Transaktionen sind vollständig simuliert; externe Netzwerk-/Mailfunktionen und URL-Streams waren technisch gesperrt. Kein Browser-, Hetzner-, echter Provider- oder Mail-Abnahmelauf. Das Hetzner-Deployment, die gesonderte Korrektur der Alt-Einträge 535/567/599 und die anschließende Dashboard-/Listen-/CSV-Abnahme bleiben offen.

Nach Abschluss aller Läufe wurde der Restbestand synthetischer `cmp189n_*`-Tabellen lesend geprüft: **0**. Die Tabellen wurden auch bei den zuvor abgebrochenen Assertions durch den vorhandenen `finally`-Block entfernt.
