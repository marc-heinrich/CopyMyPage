# Testindex – CopyMyPage dev0.0.28

[GitHub-Meilenstein 29](https://github.com/marc-heinrich/CopyMyPage/milestone/29) · [Issue #189: native Bereinigung und Sitzplatzfreigabe](https://github.com/marc-heinrich/CopyMyPage/issues/189) · [Issue #190: Callback-Bootstrap-Regression](https://github.com/marc-heinrich/CopyMyPage/issues/190)

Dieser Index beschreibt den für dev0.0.28 übernommenen Testbestand. Er enthält auch weiterverwendete ältere Regressionen. Die Dateien bleiben an ihren fachlichen Pfaden; spätere Meilensteine können auf dieselben Tests verweisen.

## Testfälle und erwartetes Verhalten

| Testdatei | Zweck und erwartetes Ergebnis | Umgebung / Grenze |
| --- | --- | --- |
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
