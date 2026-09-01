# DPCalendar ticket booking flow

Stand: 2026-08-30  
Meilenstein: [CopyMyPage dev0.0.20 (GitHub-Meilenstein 21)](https://github.com/marc-heinrich/CopyMyPage/milestone/21)  
Leit-Issue: GitHub #143

## Quellenrang und Übergabestatus

- Ein fortsetzender Agent lädt vor jeder Arbeit `C:\Users\User\Documents\CopyMyPage\AGENTS.md` und `C:\Users\User\Documents\CopyMyPage\DESIGN.md` vollständig. Vor Änderungen an Formularen, Formularzuständen oder regulären Aktionsbuttons ist zusätzlich `C:\Users\User\Documents\CopyMyPage\docs\UI_STYLE_GUIDE.md` vollständig zu lesen. Deren aktuelle Arbeits-, Sicherheits- und Designregeln bleiben diesem Fachvertrag übergeordnet.
- Autoritative Implementierungsquelle ist ausschließlich `C:\wamp\www\joomla6`. Diese Datei beschreibt den dort zuletzt am 2026-08-30 geprüften Stand und trennt vorhandene Funktionen von Zielverträgen und offenen Freigabepunkten.
- Der Repository-Baum `C:\Users\User\Documents\CopyMyPage` ist die vom Benutzer gepflegte Distributions- und Dokumentationsquelle. Änderungen aus dem Live-Baum dürfen nicht ungefragt dorthin zurückkopiert werden.
- Der Text von GitHub-Issue #143 bildet noch den älteren fünfteiligen Ablauf mit „Kundendaten“ als Schritt 2 und einem deaktivierten Sitzplan-Übergang ab. Er ist historischer Kontext; für die Fortsetzung gelten der Live-Code und dieses Dokument.
- Flüchtige Daten wie aktive Warenkörbe, Sitzhaltungen, DPCalendar-Tickets und Eventkapazitäten sind vor jedem Test neu aus der Datenbank zu lesen. Ein hier genannter Snapshot ist keine Testvorbedingung.
- Komponenten- und Systempluginmanifest sowie der lokale Joomla-Schemastand stehen auf `0.0.20`. Die Basismigration `0.0.19.sql` enthält weiterhin Warenkorb, Kundendaten und Sitzinventar; `0.0.20.sql` ergänzt am Warenkorb die für den verbindlichen Step-4-Abschluss benötigten Felder `payment_provider`, `terms_accepted_at`, `terms_snapshot` und den Index auf `booking_id`.
- Die einzelnen `version`-Werte von Web Assets in `media/com_copymypage/joomla.asset.json` sind unabhängige Cache-Revisionen einzelner Dateien. Sie bestimmen weder Paketversion noch Joomla-Schemastand und dürfen zur sicheren Cache-Invalidierung abweichen.

### Aktueller Stand in einem Satz

Die Schritte 1 bis 4 der fünfstufigen sichtbaren Checkout-Anzeige sind in der lokalen Joomla-Instanz implementiert. Schritt 4 ist nicht mehr nur eine read-only Bestellübersicht: Der GET baut weiterhin eine autoritative, fail-closed Momentaufnahme auf, während der neue POST-Task `orderreview.checkout` Bedingungen, Zahlungsart, DPCalendar-Endpreis, Warenkorbrevision und Sitzzuordnungen erneut prüft, genau eine DPCalendar-Buchung samt Tickets erzeugt, Warenkorb und Sitze umwandelt und anschließend zum nativen DPCalendar-`pay`- beziehungsweise `order`-Layout weiterleitet. Noch nicht implementiert ist eine eigene statusgesicherte CopyMyPage-Vollzugsmeldung für Schritt 5. Der echte PayPal-Sandbox-Rundlauf, verlassene Zahlungen/Timeouts sowie E-Mail-, Ticket-, QR-Code- und Einlassprüfung bleiben offen. Die übrigen Parallel-, Negativ-, Backend- und DPCalendar-Guard-Kriterien bleiben davon unabhängige Produktivfreigabe-Gates.

| Bereich | Stand am 2026-08-30 | Nächste Grenze |
| --- | --- | --- |
| Schritt 1 – Ticketauswahl und Warenkorb | implementiert und regressionsgeprüft | automatischer Navbar-Ablauf wird noch manuell langzeitgetestet und darf ohne neuen Fehlerbericht nicht geändert werden |
| Schritt 2 – Sitzplatzwahl | sichtbarer Ablauf und Browserruntime einschließlich 178-Sitz-Stresstest abgeschlossen; serverseitiges Datenmodell, Import, Inventar und Mutationen sind implementiert | offene Produktivfreigabe-Gates aus dem Prüfkatalog separat schließen, insbesondere Parallel-/Negativtests, vollständig fehlende Eventzuordnung fail-closed und öffentlicher DPCalendar-Buchungs-Guard |
| Schritt 3 – Kundendaten | View, Route, Service, Controller, Joomla-Form, Warenkorbentwurf, Gast-/Login-Weiche, optionale Joomla-Registrierung und clientseitige Formularführung implementiert; vier Nutzerfälle sowie Desktop und Mobile geprüft | offene manuelle Kontoanlage mit Live-CAPTCHA und produktionsnahe Negativtests separat abnehmen; konkrete UI-Fehler gezielt bearbeiten |
| Schritt 4 – Prüfen und bestätigen | `view=orderreview`, `OrderReviewService`, `OrderCheckoutService`, POST-Controller, Zahlungsanbieterauswahl, Pflichtzustimmung, AGB-Snapshot, DPCalendar-Buchung/Tickets, Warenkorbumwandlung und deterministische Sitzverknüpfung sind implementiert; die responsive Darstellung wurde gemeinsam nachjustiert | vollständige Negativ-/Parallel-/Rollback-Abnahme der schreibenden Übergabe ergänzen; den rechtlichen Startertext redaktionell und juristisch freigeben |
| Schritt 5 – Zahlung und Abschluss | native DPCalendar-`pay`-/`order`-Ziele und PayPal-Callbackmechanik sind vorhanden; kostenlose Bestellungen erreichen unmittelbar `order`, kostenpflichtige zunächst Buchungsstatus `3` und `pay` | eigene statusgesicherte Vollzugsmeldung umsetzen; PayPal-Sandbox auf HTTPS, Abbruch/Fehler/Timeout, E-Mail, Download, Ticket und QR-Code abnehmen |

## Zielbild

CopyMyPage ergänzt DPCalendar um einen verständlichen, updatefesten Einstieg für den gemeinsamen Kauf von Tickets zu mehreren unabhängigen Veranstaltungen. DPCalendar bleibt für die eigentliche Buchung, Bezahlung, Ticket-Erzeugung und QR-Code-Prüfung zuständig. DPCalendar-Core-Dateien und offizielle Zahlungsplugins werden nicht verändert.

Der Ablauf beginnt auf der Landingpage:

1. Das Ticketmodul zeigt die aktuell verfügbare Menge je Veranstaltung.
2. „Ticket holen“ öffnet die zentrale CopyMyPage-Ticketauswahl.
3. Die auf der Landingpage gewählte Veranstaltung ist im UIkit Accordion geöffnet; weitere öffentliche Ticketveranstaltungen sind eingeklappt.
4. Der Gast kann Mengen für mehrere unabhängige Veranstaltungen in einem temporären Warenkorb reservieren.
5. Eine erfolgreiche Warenkorbänderung reserviert die gewählte Menge für 15 Minuten. Währenddessen wird sie bei anderen Besuchern nicht mehr als verfügbar angezeigt.
6. Der neue zweite Schritt ordnet jeder reservierten Ticketmenge konkrete Plätze im Gemeindesaal zu. Die Sitzwahl konkretisiert die vorhandene Mengenreservierung und zählt nicht als zusätzliche Reservierung.
7. Der Gemeindesaal verwendet grundsätzlich einen festen Sitzplan mit höchstens 200 Plätzen. Eine zweite, eigenständige Sitzplanvariante kann später für den Kinderkarneval verwendet werden.
8. Alle online angebotenen Sitze sind fachlich gleichwertig. Erwachsenen-/Kindertarife, Preiszonen, Sonderplätze und automatische Sitzregeln beeinflussen die Sitzwahl nicht.
9. Auf kleinen Displays bleibt der Saal in einem begrenzten, zoombaren und verschiebbaren Viewport. Direkte Tischwahl, Zoomtasten, Mausrad mit Strg und Zwei-Finger-Zoom ergänzen einander, ohne der vollständigen Seite horizontalen Überlauf zu geben.
10. Schritt 3 erfasst die Kundendaten. Schritt 4 prüft die Bestellung, erfasst Zahlungsart und Zustimmung und übergibt Warenkorb sowie Sitzzuordnungen verbindlich an DPCalendar. Im aktuellen Live-Code wechseln die Sitze dabei bereits innerhalb derselben Transaktion von `held` auf `booked`; Schritt 5 muss deshalb den davon getrennten DPCalendar-Zahlungsstatus zuverlässig als erfolgreich, ausstehend, abgebrochen oder fehlgeschlagen darstellen.

## Verbindliche Abgrenzung

- Keine DPCalendar-Warteliste im CopyMyPage-Ablauf.
- Keine nachträgliche Stornierung durch Kunden in der ersten Ausbaustufe.
- Entfernen aus dem noch nicht abgeschlossenen Warenkorb und automatischer Ablauf einer Reservierung bleiben möglich.
- Keine direkte Änderung an `#__dpcalendar_events`, `#__dpcalendar_bookings` oder `#__dpcalendar_tickets` durch die Reservierungsschicht.
- `capacity_used` bleibt der von DPCalendar verwaltete Stand abgeschlossener beziehungsweise aktivierter Tickets.
- Temporäre Reservierungen liegen ausschließlich in CopyMyPage-eigenen Tabellen.
- DPCalendar-Räume enthalten nur eine einfache Raumdefinition und bleiben unverändert. Sitzpläne, Sitzstatus und die Verknüpfung zu DPCalendar-Tickets gehören vollständig CopyMyPage.
- Für Veranstaltungen mit fester Sitzwahl müssen alle Onlinebuchungen über den CopyMyPage-Ablauf erfolgen. Ein paralleler nativer DPCalendar-Buchungsweg ohne Sitzzuordnung ist für diese Events unzulässig. Das bloße Ausblenden des DPCalendar-Buchungsbuttons genügt nicht; auch direkte Site-Aufrufe der Buchungsformular-View und der Tasks `bookingform.add` beziehungsweise `bookingform.save` müssen serverseitig abgefangen werden. Dieser Guard fehlt auch in `dev0.0.20` noch und blockiert die Produktivfreigabe.
- Der noch zu implementierende DPCalendar-Schutz darf nur das Erzeugen einer neuen öffentlichen Buchung sperren. Die inzwischen verwendeten Zahlungs-, Rückkehr-, Ticket- und QR-Code-Aufrufe einer bereits durch CopyMyPage erzeugten Buchung müssen erreichbar bleiben. Die interne Step-4-Übergabe verwendet DPCalendar-Modelle und den DPCalendar-Site-Controller direkt und benötigt keinen freigeschalteten öffentlichen Nebenweg.
- Der erste Ausbau bildet höchstens zwei feste, versionierte Sitzplanvarianten ab: den Standardplan und bei Bedarf eine Variante für den Kinderkarneval. Ein allgemeiner grafischer Saalplan-Editor gehört nicht zum Umfang.
- Alle Sitze sind gleichwertig. Es gibt keine sitzabhängigen Preise, Bereiche, Sonderplätze, Begleitplatzregeln, Lückenprüfung oder automatische Bestplatzlogik.
- Telefonische Sonderwünsche werden nicht als eigener Online-Buchungsprozess nachgebaut. Physisch vorhandene, aber nicht online angebotene Plätze bleiben im CopyMyPage-Inventar und werden veranstaltungsbezogen als intern gesperrt markiert.
- Das Frontend unterscheidet fremd gehaltene, endgültig gebuchte und intern gesperrte Plätze nicht. Alle drei Zustände erscheinen einheitlich als „nicht verfügbar“; interne Sperrgründe verlassen den Server nicht.
- Die Ticketauswahl verwendet nur positive Kalender-IDs, die ein veröffentlichtes `mod_copymypage_tickets` in der Position `tickets` ausdrücklich auswählt. Die Moduloption „Alle“ wird für diesen Buchungsablauf absichtlich nicht als Freigabe interner Kalender interpretiert.
- DPCalendar-Buchungsoptionen zusätzlich zum Ticketpreis gehören noch nicht zum ersten Schritt.

## Architektur und Zuständigkeiten

```text
Landingpage / mod_copymypage_tickets
        │
        ├── bezieht freigegebene Events und Buchbarkeit aus TicketCatalogService
        ├── stellt dessen gemeinsame Verfügbarkeitsdaten mit Modul-Sprachstrings dar
        ├── fragt die Verfügbarkeit periodisch read-only ab
        └── verlinkt mit event_id auf die Ticketauswahl
                │
                ▼
com_copymypage / view=ticketselection
        │
        ├── UIkit Accordion für alle freigegebenen Ticketveranstaltungen
        ├── AJAX-Mutationen mit CSRF-Schutz
        ├── normaler POST als funktionsfähiger Fallback
        └── serverseitig autoritativer Warenkorb
                │
                ▼
CopyMyPage-Reservierungstabellen
        │
        ├── aktive Session / Ablaufzeit
        ├── Veranstaltung, Preisart, Menge und Preis-Snapshot
        └── eine Mengenreservierung unabhängig von der Sitzdarstellung
                │
                ▼
com_copymypage / view=seatselection
        │
        ├── lädt die dem Event zugewiesene Sitzplanversion
        ├── zeigt höchstens 200 Sitze responsiv und barrierearm
        ├── hält ausgewählte Sitze atomar für denselben Warenkorb
        └── gibt Schritt 3 nur bei vollständig gehaltenen Sitzen frei
                │
                ▼
CopyMyPage-Sitzinventar
        │
        ├── verfügbar / intern gesperrt / gehalten / gebucht
        ├── Event, Sitz, Warenkorb und DPCalendar-Ticket-Zuordnung
        └── keine zweite Menge, keinen zweiten Preis und keinen zweiten Timer
                │
                ▼
com_copymypage / view=customerdata
        │
        ├── prüft Warenkorb und vollständige Sitzhaltung erneut serverseitig
        ├── bildet Gast, optionale Registrierung und vorhandenes Joomla-Konto ab
        ├── speichert genau einen Rechnungsdatenentwurf je Warenkorb
        └── verlängert nur beim erfolgreichen Speichern Revision und gemeinsame Haltefrist
                │
                ▼
com_copymypage / view=orderreview
        │
        ├── prüft aktiven Warenkorb, Revision, Mengen, Preise und vollständige eigene Sitze
        ├── validiert den gespeicherten Rechnungsdatenentwurf erneut und gibt ihn datensparsam aus
        ├── berechnet mit DPCalendar Endpreis, gemeinsame Zahlungsanbieter und Bedingungen
        ├── fasst Events, Preisarten, Sitzkennungen, Rechnungsdaten und Gesamtsumme read-only zusammen
        └── bestätigt per POST Revision, signierten Checkoutzustand, Zahlungsart und Zustimmung
                │
                ▼
DPCalendar-Buchung und -Tickets + umgewandelter CopyMyPage-Warenkorb
        │
        ├── Sitze werden deterministisch mit Ticket-IDs verknüpft und auf booked gesetzt
        ├── kostenlos: DPCalendar-Status 1 → layout=order
        └── kostenpflichtig: DPCalendar-Status 3 → layout=pay → Zahlungsplugin/Callback
                │
                ▼
Schritt 5 – statusgesicherter Abschluss / Tickets / QR-Code (CopyMyPage-Ansicht noch offen)
```

### CopyMyPage-Tabellen

#### Bestehende Tabellen aus Schritt 1

`#__copymypage_ticket_carts`

- kennt nur den SHA-256-Hash des zufälligen Session-Tokens;
- numerischer Status: `0 = aktiv`, `1 = umgewandelt`, `2 = abgelaufen`, `3 = freigegeben`;
- enthält Benutzer-ID und Ablaufzeit; ein umgewandelter Warenkorb enthält die DPCalendar-Buchungs-ID und den gewählten Zahlungsanbieter;
- speichert bei der Umwandlung den UTC-Zeitpunkt der Zustimmung sowie einen versionierten JSON-Snapshot. Dieser enthält die akzeptierten Beitragsinhalte einschließlich IDs, Titel, Änderungszeit, Hash, URL und zugehörigen Event-IDs sowie Warenkorbrevision, Checkoutsignatur, Event-IDs, Zahlungsanbieter, Gebühr, Währung und Endbetrag;
- besitzt seit `0.0.20` einen Index auf `booking_id`, damit der zugehörige umgewandelte Warenkorb bei DPCalendar-Buchungsereignissen gezielt gefunden werden kann;
- besitzt eine monoton steigende `revision`. Sie schützt Mutationen desselben Warenkorbs vor veralteten Antworten und überschreibenden Anfragen aus mehreren Tabs;
- das unverhashte Token bleibt ausschließlich in der Joomla-Session und wird erst nach erfolgreichem Commit der Umwandlung entfernt. Der nicht mehr benötigte Kundendatenentwurf wird innerhalb derselben Transaktion gelöscht.

`#__copymypage_ticket_cart_items`

- gehört zu genau einem Warenkorb;
- enthält Event-ID, DPCalendar-Preisindex und Menge;
- speichert Preis, Währung und Preisbezeichnung als Snapshot;
- ist je Warenkorb, Event und Preisindex eindeutig.

#### Implementierte Tabelle aus Schritt 3

`#__copymypage_ticket_customers`

- enthält genau einen Kundendatenentwurf je temporärem Warenkorb und besitzt dafür einen eindeutigen `cart_id`-Index;
- speichert Vorname, Nachname, E-Mail, Straße, Hausnummer, Postleitzahl, Ort, ISO-Ländercode und den aufgelösten Ländernamen;
- speichert Region und Telefon optional; bei einer Region werden sowohl Code als auch aufgelöster Name abgelegt;
- führt `user_id` für die beim Speichern angemeldete Joomla-Identität und optional `account_user_id` für ein im Ablauf neu angelegtes Konto;
- speichert weder Benutzername noch Passwort, Passwortbestätigung, CAPTCHA-Wert, Session-Token oder andere Registrierungsgeheimnisse;
- wird bei Ablauf oder ausdrücklicher Freigabe des Warenkorbs entfernt; zusätzlich erzwingt der Fremdschlüssel auf `cart_id` mit `ON DELETE CASCADE` denselben Lebenszyklus;
- wurde mit Schritt 3 im Basisschema `0.0.19` eingeführt und durch das Update `0.0.20` nicht strukturell verändert.

#### Implementierte Tabellen aus Schritt 2

Die Basismigration `0.0.19.sql` legt die beiden temporären Warenkorbtabellen, die Kundendatentabelle und fünf normalisierte Sitzplatztabellen an. Die nachgelagerte Migration `0.0.20.sql` erweitert ausschließlich den bestehenden Warenkorb um die Step-4-Audit- und Buchungsverknüpfungsfelder. Alle Tabellen verwenden InnoDB und die Sitzinventare bleiben CopyMyPage-eigen. Die lokale Joomla-Schematabelle meldet für `com_copymypage` den Stand `0.0.20`.

`#__copymypage_seat_layouts`

- beschreibt eine unveränderliche Version eines Sitzplans, beispielsweise `gemeindesaal-test` Version 1, `gemeindesaal-2027` Version 1 oder später `kinderkarneval` Version 1;
- enthält Titel, stabilen Alias, Versionsnummer, Status, Breite und Höhe des logischen Koordinatensystems, normalisierte Geometrie und einen kanonischen SHA-256-Definitionshash;
- ist je Alias und Version eindeutig;
- wird nie unter derselben Alias-/Versionskombination mit abweichendem Hash überschrieben. Änderungen erzeugen eine neue Version.

`#__copymypage_layout_tables`

- gehört zu genau einer Sitzplanversion;
- enthält stabilen Tischcode, sichtbare Tischnummer, Beschriftung, Form, X-/Y-Koordinate, Breite, Höhe, Rotation und Sortierreihenfolge;
- ist je Layout und Tischcode, Tischnummer sowie Sortierreihenfolge eindeutig;
- bildet die durchnummerierten Tische mit variabler Kapazität ab; ein Reihenmodell ist nicht Bestandteil des aktuellen Vertrags.

`#__copymypage_seats`

- gehört zu genau einem Eintrag in `#__copymypage_layout_tables` und damit mittelbar zu genau einer Sitzplanversion;
- enthält eine stabile Sitzkennung, Sitznummer, X-/Y-Koordinate und eine innerhalb des Tischs eindeutige räumliche Sortierreihenfolge;
- enthält weder Preis noch Veranstaltung, Warenkorb oder dynamischen Verfügbarkeitsstatus;
- ist je Tisch und Sitzkennung, Sitznummer sowie Sortierreihenfolge eindeutig;
- wird vom Import zusammen mit allen Tischen auf höchstens 200 Sitze pro Sitzplanversion begrenzt.

`#__copymypage_event_seating`

- ordnet genau einem positiven DPCalendar-Event genau eine Sitzplanversion zu;
- besitzt den numerischen Bereitschaftsstatus `0 = draft` oder `1 = ready`. Beim erstmaligen Wechsel zu `ready` sind veröffentlichtes Layout, exakte Materialisierung, zulässige Sitzstatus, keine aktive Warenkorbmenge, keine nativen DPCalendar-Tickets und `capacity_used = 0` geprüft. Eine Abweichung zwischen DPCalendar-`capacity` und Layoutsitzanzahl bleibt derzeit Diagnose und verhindert `ready` nicht;
- enthält eine monoton steigende `inventory_version` für kompakte Statusabfragen und das Erkennen veralteter Browserstände;
- bindet ein Event bereits mit der ersten `event_seating`-Zeile an genau dieses Layout. `assignDraft()` lehnt eine andere `layout_id` auch ohne Reservierung ab; eine `ready`-Zuordnung kann derzeit ebenfalls nicht neu zugewiesen werden;
- enthält Erstellungs-, Änderungs- und Bereitschaftsmetadaten, aber keine kopierten Eventtitel, Termine, Preise oder DPCalendar-Kapazitätswerte.

`#__copymypage_event_seats`

- materialisiert für jedes Event und jeden Sitz seiner Sitzplanversion genau eine Inventarzeile;
- verwendet die numerischen Zustände `0 = available`, `1 = held`, `2 = booked` und `3 = blocked`;
- enthält bei `held` den CopyMyPage-Warenkorb, den zugeordneten DPCalendar-Preisindex und eine deterministische Zuordnungsreihenfolge;
- enthält bei `booked` zusätzlich die DPCalendar-Ticket-ID; die DPCalendar-Buchungs-ID wird nicht redundant gespeichert, weil sie über Warenkorb beziehungsweise Ticket ermittelbar ist;
- darf einen internen Sperrvermerk enthalten, der niemals an das Frontend ausgegeben wird;
- ist je Event und Sitz, je Warenkorb/Event/Zuordnungsreihenfolge sowie über eine vorhandene DPCalendar-Ticket-ID eindeutig und besitzt zusätzliche Statusindizes;
- dupliziert `expires_at` nicht. Ein gehaltener Sitz übernimmt Gültigkeit und Ablauf ausschließlich vom referenzierten Warenkorb.

Die implementierten Tabellen bilden logische Referenzen zu DPCalendar-Events, Warenkörben und Tickets ab, verändern aber keine DPCalendar-Tabelle. Physische Fremdschlüssel bestehen innerhalb von Layout und Inventar; auf DPCalendar-Events/-Tickets und CopyMyPage-Warenkörbe wird bewusst nur logisch verwiesen, damit Lebenszyklus, Updates und Deinstallation kontrolliert behandelt werden können.

### Reservierungsregel

Zielvertrag der ersten Ausbaustufe: Alle über den CopyMyPage-Ticketkatalog verkauften Veranstaltungen sind sitzpflichtig. Fehlt die Zeile in `#__copymypage_event_seating`, steht sie noch auf `draft`, ist die Materialisierung unvollständig oder stimmen Event und Layout nicht überein, muss das Event fail-closed und damit nicht reservierbar sein. Ein fehlender Sitzplan darf nicht als „keine Sitzwahl erforderlich“ interpretiert werden.

**Offene Sicherheitsgrenze auch in `dev0.0.20`:** Ein vorhandenes, aber nicht vollständig `ready`-fähiges Inventar wird bereits fail-closed behandelt. Fehlt die Event-Zuordnung vollständig, liefert `SeatSelectionService::getInventoryConstraints()` jedoch keinen Constraint; Schritt 1 fällt dann noch auf die allgemeine Mengenberechnung zurück. Diese Lücke ist vor einer produktiven Freigabe aller sitzpflichtigen Katalogevents zu schließen und mit Regressionstests abzusichern.

Die folgende Mengenformel ist daher im Live-Stand noch ein Kompatibilitäts-Fallback. Fachlich soll sie nach Schließen der Lücke nur noch für einen später ausdrücklich eingeführten Modus ohne feste Sitzplätze gelten. Ein solcher `general_admission`-Modus gehört nicht zur ersten Ausbaustufe:

```text
verfügbar = capacity - DPCalendar capacity_used - Summe aktiver CopyMyPage-Reservierungen
```

Bei unbegrenzter Kapazität bleibt `verfügbar` unbegrenzt. Eine Warenkorbmutation sperrt innerhalb einer Datenbanktransaktion zuerst den eigenen Warenkorb und danach den betroffenen DPCalendar-Eventdatensatz. Erst hinter dieser gemeinsamen Event-Sperre liest sie Warenkorbpositionen, Katalogevent und fremde Reservierungen und schreibt anschließend den neuen Stand. Diese Reihenfolge ist für die lokale Isolation `REPEATABLE-READ` verbindlich: Vor der Event-Sperre darf kein gewöhnliches `SELECT` einen veralteten Transaktions-Snapshot festschreiben. Damit können auch zwei bereits gefüllte Warenkörbe bei einer gleichzeitigen Erhöhung nicht überbuchen. Ein reiner Browserstand ist niemals autoritativ.

Das Markieren abgelaufener Warenkorbzeilen ist opportunistische Bereinigung und darf eine laufende Mutation bei konkurrierenden Indexsperren nicht abbrechen. Fachlich maßgeblich bleibt in jeder Warenkorb- und Summenabfrage zusätzlich `expires_at > now`; ein abgelaufener Warenkorb zählt deshalb auch dann sofort nicht mehr, wenn sein gespeicherter Status erst später bereinigt wird.

Die eigene bereits reservierte Menge wird bei einer Aktualisierung zunächst herausgerechnet. Eine erfolgreiche Änderung verlängert den gesamten Warenkorb wieder auf die konfigurierte Dauer. Reine Statusabfragen verlängern ihn nicht.

Für ein Event mit Sitzplan begrenzt zusätzlich das online freigegebene Sitzinventar die Menge:

```text
DPCalendar-Rest = capacity - capacity_used
Online-Sitzkapazität = alle Event-Sitze außer blocked
Sitz-Rest       = Online-Sitzkapazität - endgültig gebuchte Sitze
verfügbar       = min(DPCalendar-Rest, Sitz-Rest) - Summe aktiver Warenkorbmengen
```

Bei unbegrenzter DPCalendar-Kapazität ist `Sitz-Rest` allein maßgeblich. Ergebnisse werden immer auf mindestens `0` begrenzt. Die `Online-Sitzkapazität` ist der stabile veranstaltungsbezogene Verkaufspool. Sie umfasst Sitze in den Zuständen `available`, `held` und `booked`, jedoch keine intern gesperrten Sitze. Aktive Warenkorbmengen werden genau einmal abgezogen, unabhängig davon, ob ihre konkreten Sitze bereits gewählt wurden. Die Sitzwahl verändert daher niemals `#__copymypage_ticket_cart_items.quantity` und wird nicht ein zweites Mal in der Landingpage-Verfügbarkeit abgezogen.

Für Sitzveranstaltungen soll die DPCalendar-Kapazität der physischen Sitzanzahl entsprechen. Die kleinere der beiden Grenzen bleibt dennoch serverseitig maßgeblich. Damit führen eine versehentlich zu hohe DPCalendar-Kapazität oder veranstaltungsbezogen gesperrte Hotline-Plätze nicht zu Online-Überbuchungen.

Eine Backend-Sperre ist nur für einen aktuell verfügbaren Sitz erlaubt. Nach der Sperre müssen weiterhin mindestens so viele nicht gebuchte Onlineplätze existieren wie aktive Warenkorbmengen. Gehaltene oder gebuchte Sitze dürfen nicht still überschrieben werden.

### Sitzplatz-Zustandsautomat

Der Tabellen- und Servicevertrag unterstützt folgende Übergänge. Auswahl/Freigabe, Sperren/Freigeben sowie die Umwandlung bei der Step-4-Übergabe sind angeschlossen:

```text
available ── Benutzer wählt Sitz ──► held
held      ── Abwahl / Mengenreduktion / Entfernen / Ablauf ──► available
available ── autorisiertes Backend ──► blocked
blocked   ── autorisiertes Backend ──► available
held      ── Step-4-Commit mit DPCalendar-Ticket-ID ──► booked
booked    ── DPCalendar-Abbruch / Stornierung / Erstattung / Löschung ──► available
```

- `booked` ist im öffentlichen Ablauf endgültig und kann nicht über die Sitzwahl oder den Hotline-Dialog verändert werden.
- `OrderCheckoutService` gruppiert DPCalendar-Tickets und gehaltene Sitze nach Event und Preisindex, sortiert beide Seiten stabil und schreibt Ticket-ID und Status `booked` innerhalb derselben Transaktion. Dieser Wechsel erfolgt aktuell vor dem externen PayPal-Abschluss: kostenpflichtige Buchungen befinden sich danach zunächst im DPCalendar-Status `3`.
- Der frühe `booked`-Status verhindert, dass der alte Warenkorbtimer einen bereits an DPCalendar übergebenen Sitz wieder freigibt. Er ist kein Nachweis einer erfolgreichen Zahlung; der Zahlungsstatus bleibt ausschließlich DPCalendar-eigen.
- Das CopyMyPage-Systemplugin gibt verknüpfte Sitze frei und setzt den umgewandelten Warenkorb auf `released`, wenn eine DPCalendar-Buchung gelöscht wird oder in den Zustand Stornierung (`6`), Erstattung (`7`) beziehungsweise Papierkorb (`-2`) wechselt.
- Für dauerhaft verlassene DPCalendar-Buchungen im zahlungsausstehenden Zustand `3` ist noch keine automatische Timeout-/Bereinigungsroutine nachgewiesen. Diese Lücke ist vor Produktivbetrieb zu schließen, damit ein abgebrochener Browserlauf Sitze nicht unbegrenzt blockiert.
- Abgelaufene `held`-Zeilen gelten sofort als verfügbar, auch wenn die opportunistische Bereinigung den gespeicherten Status noch nicht zurückgesetzt hat. Die nächste schreibende Operation darf sie innerhalb ihrer Transaktion zurücksetzen und übernehmen.
- Fremd gehaltene, gebuchte und gesperrte Sitze werden öffentlich nur als `unavailable` ausgegeben. Nur der eigene aktive Warenkorb erhält für seine Sitze den Zustand `selected`.

### Implementierte Service-Aufteilung für Schritt 2

- `TicketCartContextService` kapselt bereits Session-Token, Auflösung und Sperren des aktuellen Warenkorbs, Revision, Transaktionshilfen und die gemeinsame Ablaufprüfung. Neue Sitzservices verwenden diesen vorhandenen Dienst und duplizieren die Logik nicht.
- `TicketCatalogService` ist die einzige fachliche Quelle für freigegebene DPCalendar-Events, Kalender-Buchungsrecht, Verkaufsfenster, DPCalendar-Kapazität und den daraus mit einer übergebenen aktiven Haltemenge gebildeten neutralen Verfügbarkeitsstatus. `TicketReservationService` aggregiert die aktiven CopyMyPage-Haltungen. Beim initialen Modulrendern ergänzt `TicketsHelper` Modultexte; der periodische Komponenten-Snapshot enthält bereits übersetzte Statuslabels.
- `TicketReservationService` bleibt Eigentümer der Mengen- und Kapazitätsreservierung. Für zugeordnete Sitzveranstaltungen fragt er die zusätzliche effektive Kapazitätsgrenze beim Sitzinventar ab und stimmt abhängige Sitzhaltungen bei Mengenänderung, Entfernen und Leeren ab. Die oben beschriebene Lücke bei vollständig fehlender Zuordnung bleibt offen.
- `SeatLayoutService` liest ausschließlich freigegebene JSON-Dateien aus `components/com_copymypage/data/seat-layouts`, validiert sie strikt und importiert unveränderliche Layoutversionen. Pfadgrenze, Dateigröße, erlaubte Schlüssel, Typen, Koordinaten, Eindeutigkeit, maximale Sitzanzahl und kanonischer Hash werden serverseitig geprüft.
- Die Value Objects `SeatLayoutDefinition`, `LayoutTableDefinition` und `SeatDefinition` tragen die validierte statische Definition ohne zweite Parse-Logik.
- `EventSeatInventoryService` verwaltet Event-Zuordnung, Materialisierung, Bereitschaft, Kapazitätsdiagnostik, Backend-Sperrmethoden und `inventory_version`. Methoden zum Sperren und Freigeben sind vorhanden; eine bedienbare Backend-Oberfläche dafür fehlt noch.
- `SeatSelectionService` projektiert ausschließlich Events des aktiven Warenkorbs, ersetzt die gewünschte Sitzmenge eines Events atomar, schlägt Sitze deterministisch vor und liefert den vollständigen serverseitigen Zustand zurück.
- Die inzwischen in `OrderCheckoutService` implementierte Übergabe ordnet gehaltene Sitze nach Event, Preisindex und stabiler Zuordnungsreihenfolge den von DPCalendar erzeugten Tickets zu. Sie verwendet DPCalendar-Modelle und CopyMyPage-eigene Zuordnungen, keine Sitzspalten oder direkten Writes in DPCalendar-Core-Tabellen.

Direkte Sitzmutationen besitzen ihre eigene Transaktion. Ruft eine bestehende Warenkorbmutation die Sitzabstimmung auf, verwendet sie transaktionsfähige interne Methoden ohne verschachtelte Transaktion. Für warenkorbbezogene Mutationen gilt die Ressourcenreihenfolge Warenkorb → DPCalendar-Events aufsteigend → Event-Inventare aufsteigend → sämtliche Event-Sitze in stabiler Tisch-/Sitzsortierung. Backend-Inventarmutationen benötigen keine Warenkorbsperre und verwenden DPCalendar-Event → Event-Inventar → Event-Sitze. Kein Pfad darf zuerst einen Sitz und danach den zugehörigen Warenkorb oder Eventdatensatz sperren.

### Implementierte Service-Aufteilung für Schritt 3 und 4

- `CustomerDataService` bleibt Eigentümer des warenkorbgebundenen Kundendatenentwurfs. `getReviewCustomerData()` verlangt einen aktiven, vollständig sitzbelegten Warenkorb, lädt genau dessen Entwurf, validiert ihn erneut gegen den aktuellen Formular- und Adresskatalog und projiziert ausschließlich die für Rechnung und Bestellprüfung benötigten Felder. Registrierungsgeheimnisse, Benutzername, Passwort, CAPTCHA und Sessiondaten gehören nicht zum Review-DTO.
- `OrderReviewService` ist die alleinige fachliche Quelle der Step-4-Ansicht. Er verbindet den validierten Kundendatenausschnitt mit den vorhandenen read-only Zuständen aus `TicketReservationService` und `SeatSelectionService`; Template und View bauen diese Prüfungen nicht parallel nach.
- Der Service arbeitet fail-closed. Bereits eine abgelaufene oder nicht fortsetzbare Position, eine leere Auswahl, eine abweichende Warenkorb-/Sitzrevision, eine abweichende Gesamtmenge, ein unvollständiges Event, eine ungültige Sitzkennung, eine Mengenabweichung zwischen Preisarten und Warenkorbposition oder ein nicht exakt zuordenbares Event blockiert die gesamte Übersicht. Es wird keine teilweise oder aus verschiedenen Revisionen zusammengesetzte Bestellung dargestellt.
- `OrderReviewService` schreibt weiterhin keine Daten und verlängert weder Reservierungsfrist noch Revision. `HtmlView` ergänzt dessen Projektion über `OrderCheckoutService::getViewState()` um DPCalendar-Endpreis, veröffentlichte Bedingungen, gemeinsame Zahlungsanbieter, Gebühren, Checkoutbereitschaft und eine serverseitig signierte Momentaufnahme.
- `OrderCheckoutService` ist Eigentümer des finalen Step-4-POSTs. Er verwendet DPCalendars eigene Preis-Pipeline, bildet die Schnittmenge der je Event erlaubten aktiven Zahlungsanbieter, löst veranstaltungsspezifische oder die globale DPCalendar-Bedingung auf und führt den transaktionalen Einweg-Commit aus.
- `TicketCartContextService::convertCart()` speichert Buchungs-ID, Anbieter und Zustimmungsnachweis, erhöht die Revision, löscht den Kundendatenentwurf und entfernt das Sessiontoken erst nach erfolgreichem Commit.
- Das CopyMyPage-Systemplugin registriert `OrderCheckoutService` im Joomla-Container und hört auf DPCalendar-Löschungen sowie Zustandswechsel zu `6`, `7` oder `-2`, um verknüpfte Sitze kontrolliert wieder freizugeben.
- `HtmlView` setzt private `no-store`-/`no-cache`-Header sowie `noindex, nofollow`; das Template rendert Zusammenfassung, Checkoutfelder oder den blockierten Zustand. Der GET bleibt nebenwirkungsfrei, der POST ist davon klar getrennt.

## HTTP- und JavaScript-Vertrag

### Implementierter Vertrag aus Schritt 1

Implementierte Controller-Tasks:

- `ticketcart.availability`: öffentliche, read-only Verfügbarkeit für bekannte Event-IDs;
- `ticketcart.reserve`: Mengen eines Events atomar ersetzen beziehungsweise reservieren;
- `ticketcart.remove`: ein Event aus dem Warenkorb entfernen;
- `ticketcart.clear`: den gesamten temporären Warenkorb freigeben.

Mutationen sind POST-only und benötigen Joomla-CSRF-Token. JSON-Antworten enthalten den neuen serverseitigen Warenkorb und die neu berechnete Verfügbarkeit. Ohne JavaScript leiten dieselben Aktionen nach dem POST zurück auf die Ticketauswahl und verwenden Joomla-Systemmeldungen.

`ticketcart.reserve`, `ticketcart.remove` und `ticketcart.clear` verwenden den gemeinsamen Revisionsvertrag bereits. Jeder gerenderte Warenkorbzustand und jede Mutationsantwort enthält `cartRevision`. Eine Anfrage für einen vorhandenen Warenkorb sendet `expectedCartRevision`; beim erstmaligen Anlegen gilt `0`. Verändert die Mutation den Zustand, erhöht der Server die gesperrte Warenkorbzeile genau einmal. Stimmt die erwartete Revision nicht, bleibt der Zustand unverändert und der Server antwortet mit HTTP 409 sowie dem aktuellen Warenkorb. Ist der angeforderte Sollzustand bereits vollständig erreicht, wird die Wiederholung idempotent erfolgreich beantwortet, ohne Ablauf oder Revision erneut zu verändern. Der normale POST-Fallback übermittelt dieselbe Revision als verborgenes Feld.

Die Landingpage fragt die Verfügbarkeit in einem moderaten Intervall sowie bei erneut sichtbarem beziehungsweise fokussiertem Fenster ab. Das ist nur eine Komfortaktualisierung; die endgültige Kapazitätsprüfung erfolgt immer in der Transaktion von `ticketcart.reserve`.

### Implementierter HTTP-Vertrag für Schritt 2

`TicketseatsController` stellt drei fachliche Tasks bereit:

- `ticketseats.state`: private, read-only GET-Zustandsabfrage für genau ein Event des aktuellen aktiven Warenkorbs;
- `ticketseats.assign`: atomarer, idempotenter Ersatz der vollständigen gewünschten Sitzmenge dieses Events;
- `ticketseats.suggest`: atomare, deterministische Auswahl eines passenden vollständigen Vorschlags.

`ticketseats.state` akzeptiert nur eine positive Event-ID aus dem aktuellen Warenkorb. Eine inzwischen nicht mehr katalogfähige Position bleibt sichtbar, wird aber mit `continuable = false` und `complete = false` ausgegeben, damit sie sicher entfernt werden kann. Alle Antworten sind privat und `no-store`; fremde Warenkorb-IDs, Sperrgründe und differenzierte Fremdzustände verlassen den Server nicht.

Der aktuell ausgegebene Zustand ist pro Warenkorb und Event gegliedert:

```text
selectionState
    allComplete
    selectedEventId
    cart: active, cartRevision, expiresAt, secondsLeft, totalTickets
    events[]
        id, title, dateLabel, dateTime, continuable
        ready, complete, requiredCount, selectedCount
        inventoryVersion, materializedCount, message
        layout: id, title, width, height, areas[]
        layout.tables[]
            id, code, number, label, shape
            x, y, width, height, rotation, sortOrder
            seats[]: id, code, number, label, x, y, sortOrder, status
        selectedSeats[]: id, label, tableNumber, seatNumber
```

Der öffentliche Sitzstatus ist ausschließlich `available`, `selected` oder `unavailable`. `unavailable` fasst fremd gehaltene, gebuchte und intern gesperrte Sitze zusammen. Das Frontend darf daraus nur eine Darstellung ableiten; der Zustand bleibt bis zur nächsten serverseitigen Mutation unverbindlich. Die State-Route liefert für ihren Event einen kompakten Ausschnitt mit `allComplete`, `cartRevision`, `expiresAt` und `event`.

`ticketseats.assign` verwendet POST und Joomla-CSRF-Schutz. Der Browser sendet eine Event-ID, `expectedCartRevision` und die vollständige gewünschte Menge eindeutiger Sitz-IDs, keine einzelnen „belegen“- oder „freigeben“-Deltas. Zulässig sind `0` bis zur im Warenkorb reservierten Gesamtmenge. Dadurch kann dieselbe Anfrage gefahrlos wiederholt werden, eine Abwahl wird durch das Fehlen der Sitz-ID ausgedrückt und eine leere Liste gibt alle eigenen Sitze dieses Events frei.

Die Mutation:

1. löst den aktuellen Warenkorb ausschließlich über dessen Session-Token auf und sperrt ihn;
2. prüft Ablauf und `expectedCartRevision`; bei einer veralteten Revision ist nur ein bereits erreichter identischer Sollzustand als idempotenter Erfolg zulässig;
3. prüft Event, `ready`-Sitzinventar, unveränderte Sitzplanversion und aktuelle Warenkorbmenge;
4. verlangt positive, eindeutige Sitz-IDs und begrenzt die Eingabe auf höchstens 200 Werte; Duplikate sind ein Validierungsfehler;
5. sperrt sämtliche materialisierten Sitze des Events in der stabilen Reihenfolge Tisch-`sort_order` → Sitz-`sort_order` → Inventar-ID;
6. behandelt Sitze eines abgelaufenen Warenkorbs als wieder verfügbar;
7. weist nur aktuell verfügbare oder bereits eigene Sitze zu;
8. sortiert die gewählten Sitze nach der serverseitigen Layoutreihenfolge und ordnet sie deterministisch den nach Preisindex sortierten Warenkorbpositionen zu;
9. ersetzt die bisherige Auswahl vollständig und erhöht bei einer tatsächlichen Änderung `inventory_version` sowie `cartRevision` genau einmal; nur eine tatsächliche Änderung verlängert den gemeinsamen Warenkorb;
10. liefert immer den neuen autoritativen Zustand zurück.

Eine Teilmenge wird bereits serverseitig gehalten, damit ein angeklickter Sitz nicht bis zum abschließenden „Weiter“ für andere Benutzer offen bleibt. `complete` ist erst bei exakt so vielen ausgewählten Sitzen wie reservierten Tickets wahr. Nur dann darf der folgende Schritt serverseitig betreten werden.

`ticketseats.suggest` verwendet dieselbe Warenkorb-, Revisions-, Transaktions- und Konfliktgrenze. Erst nach dem ausdrücklichen Benutzerklick bevorzugt es den kleinsten einzelnen Tisch, an dem die Gruppe vollständig Platz findet; falls kein Tisch ausreicht, verwendet es die deterministische Layoutreihenfolge über mehrere Tische. Es bewertet keine Sitzqualität. Der Vorschlag ist kein bloßer Clientzustand, sondern wird wie eine manuelle Auswahl unmittelbar gehalten.

Bei einem Sitz- oder Revisionskonflikt bleibt die letzte erfolgreiche Auswahl bestehen. Die Fehlerantwort verwendet HTTP 409 und enthält den aktuellen Eventzustand; daraus übernimmt das Frontend die nun autoritativen Sitzstatus und stellt den Fokus nachvollziehbar wieder her. Reine Zustandsabfragen verlängern den Warenkorb nicht.

Ohne JavaScript rendert dieselbe View semantische Checkboxen für die Sitze und sendet die vollständige Auswahl mit normalem POST. Nach erfolgreichem oder fehlgeschlagenem POST erfolgt eine HTTP-303-Rückleitung mit Joomla-Systemmeldung. Der Fallback besitzt keine Live-Haltung pro Klick, verwendet beim Absenden aber dieselbe atomare Servermutation.

`ticketcart.reserve`, `ticketcart.remove` und `ticketcart.clear` stimmen Sitzzuordnungen innerhalb ihrer vorhandenen Transaktion ab:

- unveränderte Eventmenge bewahrt gültige Sitze;
- eine Erhöhung bewahrt die Auswahl und markiert Schritt 2 wieder als unvollständig;
- eine Reduzierung behält die räumlich zuerst sortierten Sitze bis zur neuen Menge und gibt den Rest frei;
- das Entfernen eines Events oder Leeren des Warenkorbs gibt alle zugehörigen `held`-Sitze frei;
- eine Preisartenänderung verändert keine Sitzqualität, ordnet die erhaltenen Sitze aber erneut deterministisch den aktuellen Preisindizes zu;
- jede tatsächliche Warenkorbänderung erhöht `cartRevision`; `inventory_version` steigt bei der Reconciliation nur, wenn abgelaufene Haltungen normalisiert, Sitze freigegeben oder `price_index` beziehungsweise `assignment_order` vorhandener Sitzzeilen geändert werden. Eine reine Mengenerhöhung ohne Änderung einer Sitzzeile erhöht sie nicht.

### ES6- und WebAsset-Vertrag für Schritt 2

- Implementiert sind das WebAsset `copymypage.ticket-seats`, `TicketSeatsAssetItem`, die lesbare ES6-Quelle `media/com_copymypage/js/copymypage-seat-selection.js` und die dazugehörige Min-Datei. Nach der Step-2-Abschlussrevision stehen die unabhängigen Cache-Revisionen auf `copymypage.ticket-seats = 0.0.29` und `template = 0.0.48`.
- `SeatSelectionService::getClientConfig()` ist die Quelle für Endpunkte, 15-Sekunden-Polling, Markup-Attribute, Selektoren, Feldnamen und technische Grenzen. Das AssetItem führt diese Werte unter `copymypage.params.com.ticketSeats` zusammen.
- Die eigene Fachlogik verwendet Vanilla ES6 mit `fetch`, `Set`, `WeakMap`, `WeakSet` und Event-Delegation ohne jQuery. WebAsset und Markup hängen bewusst von `uikit` und `uikit.icons` für Accordion und Icons ab. Die Laufzeit initialisiert Wurzeln idempotent bei DOM-Bereitschaft und `joomla:updated`; bei einem bereits bekannten Root werden auch nachträglich eingefügte Eventknoten vollständig für Zoom, Tisch-Navigation und Fallback-Submitstatus initialisiert, ohne Root-Listener zu duplizieren.
- Ein Sitzklick sendet die vollständige gewünschte Auswahl automatisch. Dasselbe Eventformular wird während seines Requests mit dem gültigen Attributwert `aria-busy="true"` gegen Doppelabsenden und überlappend gestartetes Polling geschützt; nach Ende des Requests wird das Attribut entfernt. Zur Interaktionssperre wird das Sitz-Fieldset als Gruppe deaktiviert, statt bei jedem Request alle bis zu 200 Sitzinputs einzeln umzuschalten. Verschiedene Eventformulare können parallel schreiben und werden über `cartRevision`/HTTP 409 auf den autoritativen Serverzustand zurückgeführt. Es gibt keine viewweite Schreibqueue.
- Pro autoritativer Antwort werden die vorhandenen Sitzknoten genau einmal nach Sitz-ID indexiert. Unveränderte Modifierklassen, Markierungen, Checkboxzustände, Zähler, Statuswerte und bereits identische Listen der eigenen Sitze werden nicht erneut in das DOM geschrieben. Beim 178-Platz-Plan sank die gemessene Mutationsmenge für sechs aufeinanderfolgende Auswahlen dadurch von `6.930` auf `330` MutationObserver-Records.
- Die sichtbare Entfernen-Aktion in „Deine ausgewählten Plätze“ ist mit JavaScript ein nativer `button[type="button"]` mit konkretem `aria-label` und `aria-controls` zur zugehörigen Sitzcheckbox. `data-cmp-seat-remove` wird aus dem serverkontrollierten Selector abgeleitet und nicht als paralleler Clientwert dupliziert. Aktivierung mit Maus, Enter oder Leertaste löst dieselbe vollständige `ticketseats.assign`-Mutation aus; anschließend wird der Fokus auf die zugehörige Sitzcheckbox zurückgeführt.
- Statusabfragen verändern weder Warenkorbablauf noch Inventar. Das 15-Sekunden-Polling pausiert bei verborgenem Dokument und läuft bei `visibilitychange` und `pageshow` wieder an; die Sitzlaufzeit besitzt derzeit keinen eigenen `window.focus`-Listener.
- Der servergerenderte Checkbox-/POST-Ablauf bleibt ohne JavaScript funktionsfähig und verwendet dieselben Mutationsservices und HTTP-303-Rückleitungen.
- Der derzeitige State-Endpunkt liefert Layout und Status gemeinsam. Eine spätere Trennung in cachebare Geometrie und reine Inventardeltas ist eine mögliche Optimierung, aber kein implementierter Vertrag.

### Implementierter HTTP-, PRG- und JavaScript-Vertrag für Schritt 3

`CustomerdataController` stellt drei Tasks bereit:

- `customerdata.save`: POST-only Speichern des gemeinsamen Rechnungsdatenentwurfs und optionales Anlegen eines Joomla-Kontos;
- `customerdata.login`: POST-only Anmeldung eines bestehenden Joomla-Kontos innerhalb des Step-3-Ablaufs;
- `customerdata.regions`: private GET-Abfrage der zum gewählten Land gehörenden Regionen.

`customerdata.save` benötigt ein gültiges Joomla-CSRF-Token und `expectedCartRevision`. Der Service prüft zunächst einen aktiven Warenkorb mit vollständig gehaltenen Sitzen, validiert Rechnungs- und gegebenenfalls Kontodaten und beginnt erst danach die Schreibtransaktion. Innerhalb der Transaktion sperrt er den aktuellen Warenkorb, prüft Ablauf, Checkout-Bereitschaft und Revision erneut, sperrt einen vorhandenen Kundendatenentwurf und schreibt genau eine Zeile je Warenkorb. Eine veraltete Revision, zwischenzeitlich geänderte Sitzwahl oder abgelaufene Reservierung wird nicht übergangen.

Wird die optionale Kontoerstellung gewählt, validiert und registriert `CustomerDataService` über das Joomla-Core-Modell `com_users` einschließlich der installierten Datenschutz-, CAPTCHA-, Passwort- und Aktivierungsregeln. Der erzeugte Joomla-Benutzer wird über `account_user_id` mit dem Entwurf verbunden. Die konfigurierte Standardgruppe und Aktivierungsart bleiben Joomla-eigen; CopyMyPage legt keine parallele Benutzer- oder Passwortlogik an.

Erst ein vollständig erfolgreicher Speichervorgang ruft `TicketCartContextService::advanceCart()` auf. Dadurch steigen Warenkorbrevision und gemeinsame Ablaufzeit genau einmal. Der bloße GET-Aufruf von Schritt 3, das Umschalten der Ansichten, die Regionsabfrage und die Anzeige von Schritt 4 verlängern die Reservierung nicht.

Validierungsfehler verwenden Post/Redirect/Get mit HTTP 303. Über die Joomla-Session werden nur Rechnungsfelder, der optionale Benutzername und der Datenschutzstatus wiederhergestellt; Passwort, Passwortbestätigung und CAPTCHA werden weder persistiert noch im PRG-Zustand behalten. Erfolgreiches Speichern leitet ebenfalls mit HTTP 303 zu `view=orderreview` weiter. Der sichtbare Button heißt gemäß UI-Vertrag „Weiter zur Zusammenfassung“, startet keine Zahlung und betritt den geschützten Prüfschritt 4.

`customerdata.login` verwendet `Application::login()` und damit die Joomla-Authentifizierung einschließlich möglichem Geheimschlüssel- und Remember-me-Feld. Eine fehlgeschlagene Anmeldung kehrt per HTTP 303 zurück und hält einmalig den Reiter „Mit Konto“ aktiv; nach erfolgreicher Anmeldung wird Schritt 3 neu aufgebaut und aus Benutzerprofil beziehungsweise CopyMyPage-Profiladresse vorbefüllt. `customerdata.regions` benötigt ein gültiges GET-CSRF-Token und dieselbe serverseitige Eintrittsprüfung wie das Formular.

Das WebAsset `copymypage.customer-data` steht auf Cache-Revision `0.0.21`; die nach den aktuellen Step-4-Designanpassungen gemeinsam verwendete Stylesheet-Revision `template` steht auf `0.0.68`. `CustomerDataAssetItem` bezieht Selektoren und Markup-Vertrag ausschließlich aus `CustomerDataService::getClientConfig()` und führt sie unter `copymypage.params.com.customerData` zusammen. Die Vanilla-ES6-Laufzeit initialisiert Wurzeln idempotent bei DOM-Bereitschaft und `joomla:updated`.

Die JavaScript-Laufzeit ist ausschließlich progressive Formularführung: Sie synchronisiert Reiterzustand und Weiter-Button, schlägt bei leerem Benutzernamen die eingegebene E-Mail-Adresse vor und aktiviert die Konto-Pflichtfelder nur bei eingeschaltetem Kontoschalter. Ein `MutationObserver` erfasst auch ein nachträglich gerendertes CAPTCHA und deaktiviert alle Controls des eingeklappten optionalen Kontoblocks, damit unsichtbare Pflichtfelder den Gastkauf nicht blockieren. Die serverseitige Prüfung bleibt für alle Varianten autoritativ; ohne JavaScript kann das Rechnungsformular weiterhin normal per POST abgesendet werden.

### Implementierter GET-, POST- und JavaScript-Vertrag für Schritt 4

- Ein normaler GET auf `view=orderreview` lässt `OrderReviewService::getViewState()` den gemeinsamen Warenkorb-/Kunden-/Sitzstand neu aufbauen und `OrderCheckoutService::getViewState()` DPCalendar-Endpreis, Bedingungen und Zahlungsanbieter ergänzen. Der GET bleibt nebenwirkungsfrei.
- Die Antwort ist privat und nicht cachebar: Joomla-Seitencache wird deaktiviert, `Cache-Control` lautet `no-store, no-cache, must-revalidate, private, max-age=0`, zusätzlich werden `Pragma: no-cache` und `robots = noindex, nofollow` gesetzt.
- Ein GET auf die Übersicht verändert weder `expires_at` noch `revision`, Warenkorbpositionen, Sitzinventar, Kundendaten oder DPCalendar. Fehlt eine gemeinsame gültige Momentaufnahme oder eine notwendige veröffentlichte Bedingung, Währung beziehungsweise gemeinsame Zahlungsart, bleibt der Checkout fail-closed.
- `orderreview.checkout` ist POST-only und verlangt Joomla-CSRF-Token, eine dezimal gültige `expectedCartRevision`, die Pflichtzustimmung, bei einem positiven Endpreis einen erlaubten Zahlungsanbieter und eine 64-stellige Checkoutsignatur.
- Die HMAC-SHA-256-Signatur bindet Warenkorb-ID und -Revision, Grundpreis, Währung, alle dargestellten Bedingungs-IDs/-Hashes sowie Anbieter-IDs, Gebühren und Anbieter-Endbeträge an das Joomla-Secret. Unmittelbar vor dem Commit werden alle Projektionen hinter Warenkorb-, Event- und Sitzsperren erneut aufgebaut und mit `hash_equals()` verglichen.
- Der Commit erzeugt über das DPCalendar-Administratormodell zunächst eine Buchung im Reviewzustand `2`, prüft die erzeugten Tickets, verknüpft sie deterministisch mit den gesperrten Sitzen, wandelt den Warenkorb um und ruft anschließend DPCalendars `BookingController::confirm()` auf. Erwartet werden Status `3` für kostenpflichtige beziehungsweise Status `1` für kostenlose Buchungen sowie exakt der zuvor dargestellte Anbieter-Endbetrag.
- Nach erfolgreichem Commit antwortet der Controller mit HTTP 303 zum DPCalendar-`pay`- beziehungsweise `order`-Layout. Fachliche Konflikte kehren mit Joomla-Fehlermeldung und HTTP 303 zur neu aufgebauten Step-4-Ansicht zurück; unerwartete Fehler werden ohne interne Details protokolliert und ebenfalls dorthin zurückgeleitet.
- Der Checkout ist kein AJAX-Ablauf. Das WebAsset `copymypage.order-review` steht auf Cache-Revision `0.0.1`, initialisiert idempotent bei DOM-Bereitschaft, `pageshow` und `joomla:updated` und synchronisiert ausschließlich den Disabled-/ARIA-Zustand des Absende-Buttons mit der Pflichtcheckbox. Die Zustimmung wird unabhängig davon nochmals serverseitig verlangt.
- Das Asset hängt zusätzlich vom vorhandenen `copymypage.content.drawer` ab. Bedingungen werden dadurch im selben zugänglichen Drawer geöffnet; der echte Beitragslink bleibt als Fallback erhalten.

## Bedien- und Darstellungsvertrag für Schritt 2

### Allgemeiner Ablauf

- Die View `seatselection` ist der aktive sichtbare Schritt 2. Mit ihrer Einführung wächst die sichtbare Checkout-Anzeige von vier auf fünf Schritte: Auswahl, Sitzplätze, Kundendaten, Prüfen und Zahlung. Die spätere Abschlussseite ist ein Ergebnis und keine sechste Eingabemarke.
- Ein direkter Aufruf ohne aktiven Warenkorb rendert derzeit die leere Sitzwahl mit erklärendem Hinweis und Rückkehr zur Ticketauswahl; es erfolgt keine automatische Weiterleitung. Ein abgelaufener Warenkorb kann nicht über Sitz- oder URL-Parameter wiederhergestellt werden.
- Angezeigt werden ausschließlich Events, für die im aktuellen Warenkorb tatsächlich Tickets liegen. Jedes Event erhält einen eigenen UIkit-Accordion-Abschnitt; zunächst öffnet sich das erste unvollständige Event. Fehlt ein vollständig materialisiertes `ready`-Inventar, zeigt die View einen blockierenden Konfigurationsfehler und das Event bleibt unvollständig.
- Der bekannte Saalvertrag verwendet nummerierte Tische mit variablen Kapazitäten, einen breiten Mittelgang sowie links und rechts je zwei Tischspalten. Die Besucher sitzen seitlich versetzt zur Bühne und nicht wie im Kino in geraden Sitzreihen. Bühne, Gang, Ein- und Ausgänge sowie Tische dienen der Orientierung; ausschließlich Sitze sind auswählbar.
- Die Statuslegende unterscheidet verfügbar, eigene Auswahl und nicht verfügbar zusätzlich zur Farbe durch Form, Symbol beziehungsweise Text. Intern gesperrte, fremd gehaltene und gebuchte Sitze sehen öffentlich gleich aus.
- Ein Fortschritt wie „3 von 4 Plätzen gewählt“, eine einfache umbrechende Textaufzählung der ausgewählten Plätze mit jeweils einem roten X und ein `aria-live`-Status geben direkte Rückmeldung. Die Einträge sind keine Navpills oder großflächigen Aktionsbuttons. „Plätze vorschlagen“ kann eine vollständige Auswahl unmittelbar serverseitig halten.
- Die frühere manuelle Hauptaktion „Auswahl speichern“ bleibt als normaler POST-Fallback im DOM und unterhalb der Sitzaufzählung mittig angeordnet. Sobald die ES6-Laufzeit den Root erfolgreich initialisiert hat, wird ihre Aktionsgruppe ausgeblendet, weil jeder Sitzwechsel bereits automatisch gespeichert wird.
- Der Zustand `allComplete` wird autoritativ berechnet. `continueUrl` führt inzwischen zu `view=customerdata`; „Weiter zu den Kundendaten“ wird nur bei vollständig gehaltenen Sitzen bedienbar. Die Step-3-View validiert denselben Zustand beim GET und vor jeder Mutation erneut serverseitig.
- Die Navigation liegt unterhalb des Accordions: „Zurück zur Ticketauswahl“ verwendet einen linken Chevron und ist einschließlich Text mit dem gedämpften Texttoken gestaltet. Der zustandsabhängige Vorwärtsbutton „Weiter zu den Kundendaten“ verwendet einen rechten Chevron. Der ältere allgemeine Fortsetzungshinweis unterhalb des Accordions wurde entfernt.
- Der leere Zustand zeigt die schlichte Meldung „Dein Warenkorb enthält keine Tickets …“ ohne zusätzliches Statusicon; der Text bleibt innerhalb des Hinweiscontainers und bricht auf kleinen Displays um.
- Der Warenkorb bleibt ab Schritt 2 eine Zusammenfassung. „Auswahl ändern“ führt bewusst zu Schritt 1 zurück; der frühere Drawer-Button „Weiter zum Sitzplan“ wurde entfernt, weil ein kontextloser Warenkorblink in späteren Schritten irreführend zurück zu Schritt 2 führen würde. Vor- und Zurückaktionen des Checkout-Ablaufs gehören unter das jeweilige Accordion. Alle dennoch aus einem zweiten Tab eintreffenden Warenkorbänderungen werden beim nächsten Statusabgleich serverseitig berücksichtigt.
- Sitzwechsel sind bis zur späteren DPCalendar-Übergabe möglich. Ein Klick wird automatisch gespeichert; nach der Antwort zeigt die View den autoritativen Serverstand und meldet Konflikte verständlich. Ein vollständiges Event wird ohne linken grünen Kartenschatten durch den mit der Erfolgslegende harmonisierten, grün gefüllten UIkit-Haken und den Textstatus gekennzeichnet.
- Der Warenkorb zeigt ab Schritt 2 unter der Ticketzeile zusätzlich „N Sitze ausgewählt“. Unter „Warenkorb leeren“ steht außerhalb dessen Formulars der gedämpfte Link „Zur Ticketauswahl“ mit `target="_top"`, damit der same-origin Drawer die oberste Seite wechselt und keinen Eltern-/Formularkonflikt erzeugt.

### Desktop und größere Displays

- Der komplette Sitzplan liegt anhand seiner logischen Koordinaten in einem begrenzten Viewport. Beim Einstieg und nach „Gesamtansicht“ wird die vollständige Karte unabhängig von Displaygröße und Ausrichtung in den verfügbaren Viewport eingepasst; erst danach vergrößert der Benutzer gezielt. Die Seitenspalte selbst erhält keinen horizontalen Überlauf.
- Maus, Tastatur und Touch verwenden denselben autoritativen Auswahlzustand. Sitze bleiben semantische Checkboxen und sind über die normale Fokusreihenfolge sowie Leertaste bedienbar.
- Explizite Tasten für Vergrößern, Verkleinern und den als „Gesamtansicht“ beschrifteten Zoom-Reset sowie Strg+Mausrad steuern die Darstellung. Die beiden Zoomrichtungen verwenden eigene kräftige CopyMyPage-SVG-Lupen ohne Buttonrand; der Reset behält als abgesetzte Aktion seinen Rand und das UIkit-Refresh-Icon. Der Reset stellt das aktuelle Fit-to-view wieder her, auch wenn dessen Maßstab auf kleinen Displays unter `1` liegt. Die direkte Tischliste zentriert und fokussiert einen Tisch beim aktuellen Zoom, ohne den Sitzstatus zu verändern.

### Kleine Displays bis hin zum iPhone

- Höchstens 200 Sitze werden vollständig im DOM gehalten; Virtualisierung ist für diese Grenze nicht erforderlich.
- Der Saal bleibt in einem begrenzten, horizontal und vertikal verschiebbaren Viewport. Der geräteabhängige Fit-to-view-Maßstab bildet die untere Ausgangsgrenze; anschließend wird in Schritten von `0,25` bis höchstens `3` vergrößert. Zwei-Finger-Zoom ergänzt die immer sichtbaren Zoomtasten.
- Eine direkte Tischwahl zentriert und fokussiert den gewünschten Tisch beim aktuellen Zoom. Die horizontalen Tisch-Navigationspills besitzen auf kleinen Displays dynamische linke/rechte UIkit-Chevrons, dezente runde Schatten und Kantenverläufe. Nur die Richtung mit weiterem Inhalt ist sichtbar; die Controls liegen bündig an der Inhaltskante des übergeordneten `uk-container`.
- Bühne, Mittelgang, Ein-/Ausgänge, Tischlabels, Auswahlzähler und die eigene Auswahl bleiben verständlich. Die einfache Textaufzählung erlaubt über native, rot hervorgehobene X-Buttons das direkte Entfernen einer bereits gewählten Position.
- Hochformat ist vollständig nutzbar. Querformat verbessert nur die Übersicht und darf keine Voraussetzung sein.
- Vertikales Seitenscrollen außerhalb des Karten-Viewports bleibt nativ. Innerhalb des Viewports werden Pan-/Zoom-Gesten so begrenzt, dass die Seite nicht versehentlich dauerhaft eingefangen wird.

### Barrierefreiheit

- Jeder Sitz ist ein semantisches Formularelement mit einem programmatisch ermittelbaren Namen wie „Tisch 1, Sitz 4“ und einem zusätzlichen sichtbaren Zustand.
- `disabled`, `checked`, sichtbare Symbole und Textstatus entsprechen demselben Serverzustand. Farbe allein transportiert keine Information.
- Tische sind fokussierbare, benannte Orientierungselemente. Die separat gerenderten Sitzcheckboxen sind derzeit nicht semantisch in diese Tische gruppiert; ihre Reihenfolge folgt aber der serverseitigen Tisch-/Sitzsortierung und bleibt nach Statusupdates stabil.
- Ein zurückhaltendes `aria-live`-Element meldet Auswahlanzahl, erfolgreiche Serverbestätigung und Konflikte. Eine gezielte Ablaufansage ist noch nicht implementiert und bleibt Abnahmepunkt.
- Nach einem Sitzkonflikt stellt die Laufzeit den Fokus nach Möglichkeit auf den zuvor aktiven Sitz, den Submitter, das vorherige Element oder den Accordion-Trigger wieder her; die Statusmeldung wird derzeit nicht gezielt fokussiert. Nach dem Entfernen über die Textaufzählung kehrt der Fokus gezielt zur nun abgewählten Sitzcheckbox zurück.
- `prefers-reduced-motion` schaltet das sanfte Zentrieren eines direkt gewählten Tischs auf unmittelbares Scrollen um. Der Zoom selbst wird ohne eigene Animation gesetzt; der normale POST-Fallback bleibt die funktionsfähige Alternative zur ES6-Interaktion.

### Minimaler Backend-Ablauf

- Für die erste Ausbaustufe gibt es keinen freien grafischen Saalplan-Designer. Sitzpläne werden als geprüfte, versionierte JSON-Dateien in `components/com_copymypage/data/seat-layouts` bereitgestellt, einmalig in die normalisierten CopyMyPage-Tabellen importiert und danach aus der Datenbank gelesen. Browser-Upload und Frontend-Auswertung der JSON-Datei sind nicht implementiert.
- Die Importvalidierung ist strikt und akzeptiert ausschließlich das definierte Schema mit Alias, Version, Canvas, Orientierungsflächen, Tischen und höchstens 200 Sitzen. Unbekannte beziehungsweise fehlende Schlüssel, falsche Typen, ungültige Werte, Pfade außerhalb des festen Verzeichnisses, Symlinks, Dateien über 512 KiB und Teilimporte werden abgewiesen.
- Das vorhandene CopyMyPage-Systemplugin ergänzt den DPCalendar-Eventeditor ACL-geschützt um ein CopyMyPage-Sitzplatz-Feldset. Das Feld ist ein echtes Auswahlfeld für die serverseitig freigegebenen JSON-Dateien, kein freies Texteingabefeld. Nach dem Speichern werden Import, `draft`-Zuordnung, Materialisierung und Bereitschaftsprüfung ausgeführt.
- Zusätzlich existiert die Administrator-View `com_copymypage&view=eventseating` für Import, Zuordnung, Bereitschaft und Diagnose. Schreibende Aktionen sind POST- und CSRF-geschützt und benötigen `copymypage.seating.configure`.
- `EventSeatInventoryService` kann verfügbare Plätze fachlich sperren und wieder freigeben. Eine visuelle beziehungsweise formularbasierte Backend-Bedienung für Hotline-/Backup-Sperren ist noch nicht vorhanden und wird erst in dem vom Benutzer separat zu beauftragenden Backend-Paket ergänzt.
- Gehaltene und gebuchte Sitze sind im Backend nur lesbar. Eine Sperre wird abgewiesen, wenn danach weniger Onlineplätze als aktive Warenkorbmengen verbleiben würden.
- Layoutzuordnung, Bereitschaft, Sperren und Freigaben benötigen eine eigene Joomla-Berechtigung und speichern Benutzer-ID beziehungsweise Zeitstempel. Interne Vermerke erscheinen weder im öffentlichen HTML noch in JSON-Antworten.
- Eine telefonische Buchung, manuelle Sitzübertragung oder ein Backend-Override gebuchter Plätze gehört nicht zu Schritt 2. Die Hotline kann Plätze durch Sperren aus dem Onlineverkauf nehmen; die weitere Offlineabwicklung bleibt außerhalb dieses Flows.

### Vorhandener Test-Sitzplan

`components/com_copymypage/data/seat-layouts/gemeindesaal-test-v1.json` ist bewusst nur eine flexible Entwicklungsgrundlage:

- `schemaVersion = 1`, Alias `gemeindesaal-test`, Version `1`, logische Fläche `1200 × 960`;
- Bühne, breiter Mittelgang, Haupteingang sowie linker und rechter Ausgang als nicht interaktive Orientierungsflächen;
- zwölf nummerierte Tische in je zwei linken und zwei rechten Tischspalten über drei logische Ebenen;
- variable Tischkapazitäten `4, 6, 6, 4, 6, 8, 8, 6, 4, 8, 8, 4`, insgesamt 72 Plätze;
- stabile Codes wie `T01-S01` und sichtbare Bezeichnungen nach dem Vertrag „Tisch 1, Sitz 4“.

Der Plan ist nicht als reale Saalvermessung freigegeben. Da die Alias-/Versionskombination unveränderlich ist, wird eine inhaltlich geänderte reale Definition als neue Version importiert. Sie kann derzeit nur einem noch unzugeordneten beziehungsweise neuen Event zugewiesen werden; ein bereits vorhandener `event_seating`-Datensatz lässt sich nicht auf eine andere Layout-ID umstellen.

### Bereitgestellter Sitzplan 2027

`components/com_copymypage/data/seat-layouts/gemeindesaal-2027-v1.json` ist die aus `Sitzplan_2027.json` und `Sitzplan_2027.png` abgeleitete, kanonische CopyMyPage-Vorlage für die nächsten Tests. Die Quelldatei aus dem Download-Verzeichnis ist ein komprimierter diagrams.net-/Draw.io-Export und wird nicht direkt zur Laufzeit verarbeitet.

- `schemaVersion = 1`, Alias `gemeindesaal-2027`, Version `1`, Titel „Gemeindesaal – Sitzplan 2027“, logische Fläche `1500 × 1050`;
- acht Tische mit den Kapazitäten `28, 10, 32, 32, 10, 28, 10, 28`, insgesamt 178 Plätze;
- ungerade Sitznummern liegen jeweils auf der oberen und gerade Sitznummern auf der unteren Tischseite; an den Stirnseiten liegen keine Sitze;
- Musik, Bühne und Loge links sowie Ausgang und Gaststätte unten dienen ausschließlich der Orientierung;
- der horizontal beschriftete, nicht interaktive „Mittelgang“ liegt zwischen Tisch 4/5 und Tisch 6/7. Er ist als Orientierungsfläche modelliert, damit die Beschriftung im vorhandenen Renderer horizontal bleibt;
- alle 178 physischen Sitze sind in der Definition enthalten und zunächst online vorgesehen. Die JSON-Datei enthält bewusst keine dynamischen Status oder dauerhafte Hotline-Sperren. `available`, eigener `selected`-Zustand und öffentlich einheitliches `unavailable` entstehen erst aus dem veranstaltungsbezogenen Sitzinventar;
- spätere Hotline-/Backup-Sperren bleiben veranstaltungsbezogene `blocked`-Zustände. Eine physische Korrektur des Plans überschreibt Version 1 nicht, sondern erzeugt mindestens `gemeindesaal-2027` Version 2.

Der produktive `SeatLayoutService` akzeptiert die gebündelte Datei und meldet genau acht Tische sowie 178 Sitze. Zusätzlich geprüft wurden ganzzahlige und innerhalb des Canvas liegende Koordinaten, eindeutige Tisch-/Sitzcodes, Nummern und Sortierwerte, die exakten Tischkapazitäten, ein minimaler Abstand von 50 logischen Einheiten zwischen Sitzmittelpunkten sowie kollisionsfreie Sitzflächen am Mittelgang. Eine unabhängige Desktop-/Mobile-Vorschau ergab acht Tische, 178 Sitze, alle sechs Orientierungselemente und einen auf kleinen Displays horizontal verschiebbaren Plan ohne Seitenüberlauf. Die Live-Runtime skaliert nicht unter Zoom `1`; ihre Sitz-Hitboxen bleiben daher mindestens `2,75rem` beziehungsweise 44 Pixel groß und können bis Zoom `3` vergrößert werden.

Die Definition ist inzwischen als Layout-ID `5` importiert und dem eigens neu angelegten DPCalendar-Event `11` („4. Veranstaltung“) zugeordnet. Das Inventar steht auf `ready`, enthält exakt 178 materialisierte Sitze und der Sitzplan wird nach Bestätigung des Benutzers in der echten `seatselection`-View fehlerfrei angezeigt. Die bereits mit `gemeindesaal-test` Version 1 verbundenen Events bleiben ausdrücklich unverändert.

### Lokaler Datenbanksnapshot des Audits

Dieser Snapshot vom 2026-08-25 dient nur der Orientierung und ist vor jedem weiteren Test neu zu erheben:

- veröffentlichtes Layout: Datenbank-ID `4`, `gemeindesaal-test` Version `1`, zwölf Tische und 72 Layoutsitze;
- Event-IDs `5`, `6` und `9`: jeweils Layout `4`, Status `ready`, exakt 72 materialisierte Event-Sitze;
- neues veröffentlichtes Layout: Datenbank-ID `5`, `gemeindesaal-2027` Version `1`, acht Tische und 178 Layoutsitze;
- neues Testevent `11` („4. Veranstaltung“): Layout `5`, Status `ready`, exakt 178 materialisierte Event-Sitze und fehlerfreie sichtbare Darstellung. Beim erneuten read-only Snapshot waren flüchtig 173 Sitze verfügbar und 5 vom aktiven Testwarenkorb gehalten; gebuchte oder gesperrte Sitze gab es nicht;
- die bestehenden Event-IDs `5`, `6` und `9` sowie ihre Zuordnung zu Layout `4` wurden durch Import und Testevent nicht verändert;
- für die bestehenden Events 5, 6 und 9: jeweils 72 verfügbare, keine gehaltenen, gebuchten oder gesperrten Inventarsitze sowie zum ursprünglichen Bestandsaudit keine DPCalendar-Buchungen oder -Tickets;
- aktive CopyMyPage-Warenkörbe sind ausdrücklich flüchtig und können diesen Stand schon im nächsten Request ändern. Ein zwischenzeitlich beobachteter aktiver Warenkorb ist deshalb keine dauerhafte Ausgangslage;
- beim Dev-Event `5` war die native DPCalendar-Wartelistenoption aktiv. Sie ändert den verbindlichen CopyMyPage-Vertrag „keine Warteliste“ nicht und ist vor realer Freigabe als Eventkonfiguration zu prüfen;
- `ticket_reservation_minutes` war nicht als eigener Komponentenwert gespeichert, sodass der geprüfte Code-/Formularstandard von 15 Minuten gilt;
- die Datenbank arbeitet mit InnoDB, `utf8mb4_unicode_ci` und `REPEATABLE-READ`.

Vor Parallel-, Kapazitäts- oder Aktivierungstests sind mindestens aktive/nicht abgelaufene `#__copymypage_ticket_carts`, deren Items, alle Event-Sitzstatus, DPCalendar `capacity`/`capacity_used` sowie vorhandene DPCalendar-Buchungen und -Tickets erneut gemeinsam zu prüfen.

## Technischer Vorbereitungsschnitt zwischen Schritt 1 und Schritt 2

Schritt 1 bleibt fachlich und gestalterisch abgenommen. Seine Mengenreservierung, Preis-Snapshots, Sessionbindung, CSRF-Prüfung, POST-Fallbacks und DPCalendar-Event-Sperre bilden die Grundlage. Seit 2026-08-22 wurden auch die Sitzdaten, Services, Backend-Zuordnung, View und Step-1-Integration umgesetzt. Offen sind nicht mehr pauschal „alle Sitzanteile“, sondern die unten einzeln genannten Sicherheits-, Backend-, Abnahme- und Folgeprozessgrenzen.

### Read-only-Ausgangsbefund vom 2026-08-21

Die folgenden Punkte dokumentieren den damaligen Ausgangszustand und sind nicht als aktueller Codebefund zu lesen. Ihr heutiger Erledigungsstand folgt unmittelbar danach.

- Die beiden CopyMyPage-Warenkorbtabellen verwenden InnoDB; die lokale Datenbank arbeitet mit `REPEATABLE-READ`. Transaktionen und Zeilensperren können daher für das Sitzinventar weiterverwendet werden.
- `TicketReservationService` sperrt bei einer Mengenmutation bereits zuerst den aktuellen Warenkorb und danach den DPCalendar-Eventdatensatz. Session-Token, Warenkorbauflösung, Ablauf und Sperrmethoden sind jedoch private Bestandteile dieses Services und noch nicht modular für einen Sitzservice nutzbar.
- `TicketReservationService::buildCartState()` überspringt derzeit eine gespeicherte Warenkorbposition, sobald ihr Event nicht mehr im aktuellen öffentlichen Ticketkatalog vorkommt. Dadurch kann eine noch aktive Mengenreservierung unsichtbar werden und den Übergang zu Schritt 2 blockieren.
- Der öffentliche DPCalendar-Eventaufruf für Event 9 bietet weiterhin den nativen CTA „Veranstaltung buchen!“ mit `bookingform.add`. Ein bloßer CopyMyPage-Einstieg verhindert daher aktuell weder einen direkten Formularaufruf noch eine native Buchung ohne Sitzzuordnung.
- Die sichtbare Schrittanzeige besitzt erwartungsgemäß noch vier Marken und der Warenkorb-Weiter-Button ist als Entwicklungshaltepunkt deaktiviert. Beides wird erst gemeinsam mit der erreichbaren `seatselection`-View umgestellt.
- Die Dev-Events 6 und 9 besitzen jeweils DPCalendar-Kapazität `200`, `max_tickets = 200` und `capacity_used = 0`. Event 5 ist mit Kapazität `1` und `capacity_used = 1` bereits ausverkauft. Diese Werte sind vor der späteren Aktivierung erneut autoritativ zu prüfen.
- Zum Prüfzeitpunkt existierte ein aktiver Dev-Warenkorb mit zwei reservierten Tickets für Event 9. Dieser Zustand ist flüchtig. Vor Layoutzuordnung und Aktivierung muss erneut geprüft werden, ob aktive Warenkörbe vorhanden sind; sie werden entweder kontrolliert auslaufen gelassen, freigegeben oder ausdrücklich migriert.

### Stand der erforderlichen Anpassungen am 2026-08-25

1. [x] `TicketCartContextService` übernimmt Session-Token, Hashprüfung, Warenkorbauflösung, Ablauf, Statuskonstanten, `FOR UPDATE`-Sperren, Revision und Transaktionshilfen. `TicketReservationService` verwendet denselben Dienst; die Logik ist nicht in einem zweiten Service kopiert.
2. [x] `#__copymypage_ticket_carts.revision`, `TicketcartController`, servergerenderte Formulare, Warenkorb-DTO und `copymypage-ticket-cart.js` verwenden den gemeinsamen Revisionsvertrag. Veraltete Änderungen liefern HTTP 409; ein bereits erreichter identischer Sollzustand bleibt ohne neue Revision und ohne Timerverlängerung idempotent.
3. [x] `TicketCatalogService` kapselt Eventabfrage, positive Kalenderfreigabe, DPCalendar-Buchungsrecht, Verkaufsfenster, Kapazität, Preisaufbereitung und den sprachneutralen Verfügbarkeitsstatus. `TicketsHelper` lädt Events und Verfügbarkeit ausschließlich über diesen Service und ergänzt nur Modultexte, Bild-, Termin- und CTA-Darstellung. Die Komponente ergänzt getrennt ihre eigenen Sprachlabels.
4. [x] Die Sperrreihenfolge der Mengenmutation lautet Warenkorb → DPCalendar-Event → Warenkorbpositionen. Bei `REPEATABLE-READ` liegt der erste gewöhnliche Lesezugriff hinter der Event-Sperre. Die globale Ablaufmarkierung ist best effort; alle fachlichen Abfragen filtern Ablaufzeiten weiterhin autoritativ.
5. [x] Warenkorbpositionen, deren Event nachträglich geschlossen, unveröffentlicht, gelöscht oder aus dem freigegebenen Modulkatalog entfernt wurde, bleiben als nicht fortsetzbar sichtbar. Vorhandene DPCalendar-Daten werden unabhängig vom öffentlichen Katalog nur zur sicheren Anzeige geladen; fehlt das Event vollständig, erscheint eine neutrale Event-ID. Entfernen und Leeren bleiben möglich.
6. [ ] `TicketReservationService` berücksichtigt bei zugeordneten Events innerhalb der bestehenden Event-Sperre Inventarbereitschaft und effektive Onlinekapazität. `draft`, unvollständige Materialisierung und ungültige Kapazität sperren bereits; bei vollständig fehlender `#__copymypage_event_seating`-Zeile greift aber noch der allgemeine Mengenfallback. Erst nach dessen fail-closed-Umstellung ist dieser Punkt erfüllt.
7. [x] Mengenänderung einschließlich deterministischer Reduzierung, Evententfernung und Warenkorbleerung stimmen vorhandene `held`-Sitze über interne Methoden innerhalb der äußeren Warenkorbtransaktion ab. Abgelaufene Haltungen gelten fachlich sofort als verfügbar und können bei der nächsten Mutation bereinigt beziehungsweise übernommen werden.
8. [x] Die Step-4-Warenkorbumwandlung erzeugt eine DPCalendar-Buchung und deren Tickets, ordnet sie pro Event und Preisindex deterministisch den gehaltenen Sitzen zu und führt `held → booked` innerhalb derselben Transaktion aus. Die davon getrennten Zahlungs-, Timeout- und Abschlusskriterien bleiben unten offen.
9. [ ] Das CopyMyPage-Systemplugin schützt auf der Site alle öffentlichen DPCalendar-Einstiege, die eine neue Buchung für ein im CopyMyPage-Ticketkatalog sitzpflichtiges Event erzeugen könnten. Der Schutz gilt unabhängig davon, ob dessen Inventar noch `draft` oder bereits `ready` ist. Ein Override ersetzt den sichtbaren DPCalendar-CTA; Administratorzugriffe, spätere Zahlungsrückläufe und vorhandene Ticket-/Buchungsansichten bleiben erreichbar.
10. [x] Die sichtbare Anzeige besitzt fünf Schritte. Der zustandsabhängige Button „Weiter zum Sitzplan“ führt bei einem fortsetzbaren Warenkorb zu `view=seatselection` und erhält nach Entfernen, Leeren oder Ablauf wieder vollständig seinen nicht fokussierbaren Disabled-Zustand. Sitz-State und Sitzmutationen besitzen serverseitige Warenkorbgrenzen sowie einen normalen POST-Fallback.

### Verbindliche Aktivierungsreihenfolge vor Produktivbetrieb

Der lokale Entwicklungsstand hat bereits `ready`-Testinventare und eine erreichbare Sitzwahl, obwohl der DPCalendar-Guard und der vollständige Missing-Assignment-Fail-closed-Vertrag noch fehlen. Er ist deshalb nicht als produktiv freigegebener Endzustand zu interpretieren. Vor Produktivbetrieb gilt weiterhin folgende Reihenfolge ohne ungeschütztes Übergangsfenster:

1. Datenbankmigration, gemeinsame Warenkorbgrundlage, Sitzservices, Guard und Views werden zunächst bei noch deaktiviertem Sitzverkauf installiert.
2. Der Standardplan wird geprüft importiert. Für jedes noch unzugeordnete freigegebene Event wird das Inventar vollständig materialisiert und bleibt zunächst `draft`. Bereits mit dem Testlayout verbundene Events benötigen zuvor den ausdrücklich freigegebenen Reassignment-/Migrationsweg; direkte Datenbankänderungen sind kein zulässiger Ersatz.
3. Hotline-/Backend-Plätze werden gesperrt; Sitzanzahl, DPCalendar-Kapazität, `capacity_used`, aktive Warenkorbmengen und gegebenenfalls bereits existierende DPCalendar-Tickets werden gegeneinander validiert. Ein Event mit vorhandenen nativen Tickets darf nicht ohne eine ausdrücklich geklärte Sitzzuordnung aktiviert werden.
4. Unmittelbar vor der Umschaltung werden aktive Warenkörbe erneut geprüft. Es darf keinen unberücksichtigten Warenkorb geben, der vor dem Sitzinventar entstanden ist.
5. Event-Inventarstatus `ready`, seat-aware Reservierungsprüfung, DPCalendar-Guard und Weiterleitung zu Schritt 2 werden als eine gemeinsame fachliche Freigabe aktiviert. Schlägt ein Teil fehl, bleibt das Event nicht buchbar.
6. Erst danach beginnen Parallel-, Ablauf-, Fallback-, Mehrtab-, DPCalendar-Bypass- und Responsive-Tests.

## Umsetzung in vertikalen Schritten

### Schritt 1 – Auswahl und echte Reservierung

- lebende Dokumentation anlegen;
- CopyMyPage-Tabellen und konfigurierbare Reservierungsdauer anlegen;
- gemeinsamen Katalog- und Reservierungsservice erstellen;
- neue View `ticketselection` mit UIkit Accordion erstellen;
- ausgewähltes Event geöffnet anzeigen;
- mehrere Veranstaltungen in einem Session-Warenkorb reservieren, aktualisieren und entfernen;
- Landingpage-Link auf die Auswahl umstellen;
- Landingpage-Anzeige um aktive Reservierungen und periodische Statusabfrage ergänzen;
- Ablauf, Parallelzugriff, POST-Fallback und kleine/große Displays prüfen.

Unter dem Accordion der Ticketauswahl steht „Weiter zum Sitzplan“. Das Linkziel ist nur vorhanden, wenn der Warenkorb `continuable` ist; bei leerem, freigegebenem oder abgelaufenem Warenkorb bleibt das Element ohne `href` und trägt `disabled`, `aria-disabled="true"` sowie `tabindex="-1"`. Entfernen, Leeren und Ablauf synchronisieren diesen Zustand clientseitig wieder zurück auf disabled. Der Warenkorb beziehungsweise Drawer enthält bewusst keinen eigenen Schritt-Link mehr.

Status am 2026-08-25: Schritt 1 einschließlich Revisionsschutz, Parallelmutation, gemeinsamer Katalog-/Verfügbarkeitsschicht, sitzplatzbezogener Kapazitätsgrenze, abhängiger Sitzabstimmung und Übergang zur Sitzwahl ist in der lokalen Joomla-Instanz implementiert und technisch verifiziert. Der zuletzt korrigierte automatische Navbar-Ablauf wird noch manuell langzeitgetestet; dieser Ablauf ist eingefroren und darf ohne einen neuen konkreten Fehlerbericht nicht verändert werden.

### Implementierte Bausteine in Schritt 1

- `TicketCatalogService` ist die gemeinsame Quelle für kommende freigegebene Events, Kalender-Buchungsrechte, Verkaufsfenster, DPCalendar-Bestand, Preisaufbereitung und die aus einer übergebenen Haltemenge berechnete sprachneutrale Verfügbarkeit. `TicketReservationService` aggregiert aktive CopyMyPage-Haltungen. Kürzere konfigurierte Katalogzeiträume werden nicht mehr ungewollt auf mindestens 18 Monate angehoben.
- `TicketCartContextService` verwaltet Session-Token, Warenkorb, Revision, Ablauf und Transaktionszustand. Die Ablaufmarkierung ist konfliktverträgliche Bereinigung; jede fachliche Abfrage filtert die Gültigkeit zusätzlich direkt.
- `TicketReservationService` verwaltet Mengen, Preis-Snapshots und die transaktionale Kapazitätsprüfung. Seine verbindliche Sperrreihenfolge Warenkorb → Event → Positionen verhindert sowohl Überbuchung durch veraltete `REPEATABLE-READ`-Snapshots als auch die zuvor mögliche inverse Positions-/Event-Sperre. Es berücksichtigt inzwischen zugeordnete Sitzinventare und stimmt Sitzhaltungen bei Reserve/Reduktion, Entfernen und Leeren ab.
- `TicketcartController` stellt Availability-, Reserve-, Remove- und Clear-Endpunkte mit CSRF-Schutz und POST-Fallback bereit.
- View, Model und Template `ticketselection` bilden die zentrale UIkit-Accordion-Auswahl und den gemeinsamen Warenkorb ab.
- Die CopyMyPage-Basket-View und der Drawer beziehen diesen Ticketwarenkorb direkt aus `TicketReservationService`; das vorhandene J2Commerce-`BasketModel` ist für diesen Ablauf keine Zustandsquelle.
- `TicketCartAssetItem` und `copymypage-ticket-cart.js` initialisieren den AJAX-Ablauf idempotent über die zentrale CopyMyPage-WebAsset-Architektur. Der servergerenderte Navbar-Indikator trägt bei aktivem Warenkorb dessen ISO-Ablaufzeit und wird durch einen einmaligen Timer sowie bei Fokus, erneuter Sichtbarkeit und `pageshow` geprüft. Ablauf und Mutationen synchronisieren den Status außerdem aus dem same-origin Drawer-Dokument; der direkte Elternfenster-Abgleich bleibt durch die bestehende `postMessage`-Synchronisierung ergänzt.
- Das Landingpage-Modul verlinkt auf die Auswahl, bezieht Event-, Buchbarkeits- und Verfügbarkeitsdaten aus `TicketCatalogService` und aktualisiert sie alle 25 Sekunden sowie bei Fokus beziehungsweise erneuter Sichtbarkeit. Derselbe bereits vorhandene Availability-Request liefert nur für die eigene Sitzung zusätzlich `cart.active` und `cart.expiresAt`; die Modul-Laufzeit übergibt diese Werte an die zentrale Warenkorb-Runtime und implementiert keine zweite Navbar-Logik.
- Die Reservierungsdauer ist über `ticket_reservation_minutes` konfigurierbar; der Standard beträgt 15 Minuten, zulässig sind 5 bis 60 Minuten.
- Installations-, Update- und Deinstallations-SQL enthalten Warenkorb- und Sitztabellen. Ticketwarenkorb, Sitzschema und sichtbares Sitzwahlpaket wurden mit `dev0.0.19` konsolidiert; die Step-4-Checkout-/Audit-Erweiterung gehört zu `dev0.0.20`. Joomla meldet aktuell ebenfalls den Schemastand `0.0.20`.
- Der Übergang von Schritt 1 zu `view=seatselection` und bei vollständiger Sitzwahl weiter zu `view=customerdata` ist aktiv. Beide Zielviews schützen ihren Eintritt zusätzlich serverseitig.

### Dateikarte für die nahtlose Fortsetzung

Alle folgenden Pfade sind relativ zu `C:\wamp\www\joomla6`:

- Step-1-Fachlogik: `components/com_copymypage/src/Service/TicketCatalogService.php`, `TicketCartContextService.php` und `TicketReservationService.php`;
- Step-1-HTTP/UI: `components/com_copymypage/src/Controller/TicketcartController.php`, `src/Model/TicketselectionModel.php`, `src/View/Ticketselection/HtmlView.php` und `tmpl/ticketselection/default.php`;
- Warenkorb: `components/com_copymypage/src/View/Basket/HtmlView.php`, `tmpl/basket/default.php` und `templates/copymypage/html/layouts/copymypage/tickets/cart.php`; `tmpl/ticketselection/default.php.bak` ist keine Laufzeitquelle;
- Step-1-Assets: `components/com_copymypage/src/WebAsset/AssetItem/TicketCartAssetItem.php`, `media/com_copymypage/js/copymypage-ticket-cart.js` sowie jeweilige Min-Datei;
- Landingpage-Modul: `modules/mod_copymypage_tickets/src/Helper/TicketsHelper.php`, `tmpl/tickets_default.php`, `components/com_copymypage/src/WebAsset/AssetItem/TicketsAssetItem.php` und `media/com_copymypage/js/copymypage-tickets.js`;
- Step-2-Fachlogik: `components/com_copymypage/src/Service/SeatLayoutService.php`, `EventSeatInventoryService.php`, `SeatSelectionService.php` sowie `src/ValueObject/SeatLayoutDefinition.php`, `LayoutTableDefinition.php` und `SeatDefinition.php`;
- Step-2-HTTP/UI: `components/com_copymypage/src/Controller/TicketseatsController.php`, `src/Model/SeatselectionModel.php`, `src/View/Seatselection/HtmlView.php` und `tmpl/seatselection/default.php`;
- Step-2-Assets: `components/com_copymypage/src/WebAsset/AssetItem/TicketSeatsAssetItem.php`, `media/com_copymypage/js/copymypage-seat-selection.js`, Min-Datei, `media/com_copymypage/css/template.css`, Min-Datei, `media/com_copymypage/images/icons/seat-zoom-in.svg`, `seat-zoom-out.svg` und `media/com_copymypage/joomla.asset.json`;
- Step-2-Warenkorb-Rückmeldung: `templates/copymypage/html/layouts/copymypage/tickets/cart.php`, `components/com_copymypage/src/Service/TicketCartContextService.php` und die Basket-/Drawer-Templates aus der Step-1-Dateikarte;
- Step-3-Formular und Vertrag: `components/com_copymypage/forms/customerdata.xml`, `src/Service/CustomerDataService.php`, `src/Controller/CustomerdataController.php`, `src/Model/CustomerdataModel.php`, `src/View/Customerdata/HtmlView.php` und `tmpl/customerdata/default.php`;
- Step-3-Assets: `components/com_copymypage/src/WebAsset/AssetItem/CustomerDataAssetItem.php`, `media/com_copymypage/js/copymypage-customer-data.js`, Min-Datei, `media/com_copymypage/css/template.css`, Min-Datei und `media/com_copymypage/joomla.asset.json`;
- Step-4-Fachlogik und Zusammenfassung: `components/com_copymypage/src/Service/OrderReviewService.php`, `OrderCheckoutService.php`, `TicketCartContextService.php`, `src/Controller/OrderreviewController.php`, `src/Model/OrderreviewModel.php`, `src/View/Orderreview/HtmlView.php` und `tmpl/orderreview/default.php`;
- Step-4-Darstellung und Laufzeit: `media/com_copymypage/css/template.css`, Min-Datei, `media/com_copymypage/js/copymypage-order-review.js`, Min-Datei, `media/com_copymypage/images/icons/ticket-selection.svg`, `seat-selection.svg` und `media/com_copymypage/joomla.asset.json`;
- Step-4-Bedingungen und Installation: `administrator/components/com_copymypage/script.php`, `terms/terms-and-conditions.de-DE.html`, `sql/updates/mysql/0.0.20.sql` sowie Installations-/Deinstallations-SQL;
- gemeinsame Registrierung und Routen: `plugins/system/copymypage/src/Extension/CopyMyPage.php` und `components/com_copymypage/src/Service/Router.php`;
- Step-3-/Step-4-Sprachtexte: `components/com_copymypage/language/de-DE/com_copymypage.ini`, `components/com_copymypage/language/en-GB/com_copymypage.ini`, `components/com_copymypage/language/es-ES/com_copymypage.ini`, `components/com_copymypage/language/fr-FR/com_copymypage.ini` und `components/com_copymypage/language/it-IT/com_copymypage.ini`;
- Backend-Verknüpfung: `plugins/system/copymypage/forms/dpcalendar_event_seating.xml`, `plugins/system/copymypage/src/Field/EventseatingField.php`, `plugins/system/copymypage/src/Extension/CopyMyPage.php`, `administrator/components/com_copymypage/forms/eventseating.xml`, `administrator/components/com_copymypage/src/Controller/EventseatingController.php`, `administrator/components/com_copymypage/src/Model/EventseatingModel.php`, `administrator/components/com_copymypage/src/View/Eventseating/HtmlView.php` und `administrator/components/com_copymypage/tmpl/eventseating/default.php`;
- Persistenz und Layoutdefinitionen: `administrator/components/com_copymypage/sql/updates/mysql/0.0.19.sql`, `0.0.20.sql`, `components/com_copymypage/data/seat-layouts/gemeindesaal-test-v1.json` und `components/com_copymypage/data/seat-layouts/gemeindesaal-2027-v1.json`.

### Abgenommener UI-Stand von Schritt 1

Die Ticketauswahl ist eine eigenständige CopyMyPage-View. Sie ersetzt für die Mehrfachauswahl nicht die DPCalendar-Geschäftslogik, sondern ergänzt sie um einen verständlichen, reservierungsfähigen Einstieg.

#### Einstieg und Orientierung

- Das Landingpage-Modul verlinkt mit Event-ID und Anker auf `view=ticketselection`.
- Das angeklickte Event wird beim Aufruf geöffnet und direkt in den sichtbaren Bereich gescrollt; alle anderen buchbaren Veranstaltungen bleiben zunächst eingeklappt.
- Die Seite hat keinen fachlichen Kopfbereich und keine eingebettete Erfolgsnachricht. Stattdessen zeigen die DPCalendar-Schrittmarken den aktiven ersten Schritt.
- Systemmeldungen werden über die vorhandene CopyMyPage-/Joomla-Modal-Infrastruktur dargestellt, nicht als dauerhaftes Element im Seitenkopf.
- Der Hintergrund der View nutzt `--cmp-color-background-default`.

#### Veranstaltungsauswahl

- Alle freigegebenen öffentlichen Ticketveranstaltungen erscheinen in einem UIkit-Accordion.
- Direkt unter der Überschrift „Veranstaltungen“ erklärt der Einführungstext: „Wähle zuerst die Anzahl der Tickets für deine gewünschten Veranstaltungen. Im nächsten Schritt wählst du die passenden Sitzplätze aus.“
- Accordion-Karten folgen dem Kontaktformular-Kartenvertrag: weißer Hintergrund, keine sichtbare Außenkante, `--cmp-border-radius-lg` und `--cmp-box-shadow-elevated`.
- Nur aktuell buchbare Veranstaltungen erhalten den Accordion-Trigger und das Plus-Symbol. Ausverkaufte, geschlossene oder nicht verfügbare Veranstaltungen bleiben sichtbar, können aber nicht geöffnet werden.
- Der Abschnittstitel „Veranstaltungen“ ist zentriert. Der Trigger verwendet im Interaktionszustand `--cmp-color-muted`.
- Pro Preisart kann eine Menge ausgewählt werden. Der Button „In den Warenkorb“ ist serverseitig beim ersten Rendern und clientseitig bei jeder Mengenänderung deaktiviert, solange alle Mengen `0` sind.
- Der deaktivierte Primärbutton bleibt nicht absendbar, fängt auch einen möglichen Enter-Submit ab und zeigt mit `not-allowed` den erwarteten Verbotscursor. Sein normaler Primärbutton-Hover wird dabei nicht aktiviert.
- Auf großen Displays steht „Warenkorb ansehen“ links und die Warenkorbaktion rechts; auf kleinen Displays bleibt der Button oberhalb und der Link mittig darunter.
- Unterhalb des gesamten Accordions steht rechts ein zweiter Primärbutton „Weiter zum Sitzplan“ mit Chevron. Er verwendet denselben autoritativen `cart.continuable`-Zustand wie der Warenkorb und wird nach Warenkorbleerung äußerlich wie funktional wieder deaktiviert.

#### Warenkorb und Drawer

- Der Warenkorb liegt ausschließlich im vorhandenen CopyMyPage-Drawer. Das Navbar-Warenkorb-Icon erhält bei aktivem Warenkorb den roten Statuspunkt.
- Die Warenkorbkarte nutzt nahezu die komplette verfügbare Drawer-Breite (maximal `42rem`) und bleibt im Drawer zentriert.
- Sie verwendet denselben Kartenvertrag wie Kontaktformular und Accordion: kein sichtbarer Rahmen, `--cmp-border-radius-lg`, `--cmp-box-shadow-elevated`.
- Der Drawer zeigt seinen Titel nur einmal; die doppelte sichtbare Bezeichnung „Warenkorb“ wurde entfernt.
- Die Warenkorbposition, Abstände und Kartenbreite sind für die direkte Warenkorbseite sowie für den Drawer geprüft. Auf kleinen Displays nutzt die Karte die verfügbare Breite ohne horizontalen Überlauf.
- Warenkorbpositionen enthalten Eventtitel, Datum, Preisarten und Summen. Das frühere erklärende Lead-Element wurde bewusst entfernt, damit der Drawer kompakt bleibt.
- „Warenkorb leeren“ besitzt ein dekoratives Papierkorb-Icon und behält den gemeinsamen Buttonvertrag sowie einen verständlichen Textnamen. Der frühere Drawer-Button „Weiter zum Sitzplan“ ist entfernt; Schrittnavigation wird ausschließlich in der jeweiligen Checkout-View angeboten.
- Entfernen, Leeren und Ablauf aktualisieren Auswahlfelder, den Weiter-Button unter dem Ticketselection-Accordion und die globale Warenkorbstatusanzeige konsistent. Läuft der Warenkorb vollständig ab, verschwindet der rote Navbar-Punkt auf jeder bereits geöffneten Seite anhand der serverseitig ausgegebenen Ablaufzeit ohne Reload. Auf der Landingpage gleicht der vorhandene Availability-Request den Indikator zusätzlich mit dem autoritativen Sitzungsstatus ab; das Ablauf-Emoji bleibt weiterhin ausgeblendet. Dieser Ablauf befindet sich im manuellen Langzeittest und bleibt bis zu einem neuen Fehlerbericht unverändert.

#### Landingpage-Ticketmodul

- Die Landingpage zeigt die DPCalendar-Kapazität abzüglich aller aktiven, nicht abgelaufenen CopyMyPage-Reservierungen über sämtliche Joomla-Sitzungen. Ein in der aktuellen Sitzung leerer Warenkorb kann daher korrekt neben einem reduzierten globalen Restbestand stehen. Browsercache-Löschung und das Löschen von DPCalendar-Buchungen beseitigen fremde beziehungsweise verlorene CopyMyPage-Haltungen nicht; sie enden über `expires_at` oder werden ausdrücklich freigegeben. Eventfilter, Buchbarkeit und Kapazitätsstatus werden nicht parallel im Modul nachgebaut, sondern aus dem gemeinsamen `TicketCatalogService` bezogen.
- Der Availability-Snapshot führt `capacity`, `held`, `nativeUsed`, `used`, `remaining`, `status` und `progress`. Im Basiskatalog gilt zunächst `used = nativeUsed + held`. Bei einem vorhandenen Sitzconstraint werden `capacity` und `remaining` auf die wirksame kleinere DPCalendar-/Sitzgrenze begrenzt und anschließend `used = capacity - remaining` neu gebildet. So verwenden Restbestand, Fortschrittsbalken und Text denselben finalen Snapshot, ohne eine Sitzwahl doppelt abzuziehen.
- Die Kartenüberschrift ist reiner Text und kein zusätzlicher Link. Der Buchungsweg startet ausschließlich über „Ticket holen“.
- Desktop/Tablet verwenden den Swiper-Coverflow mit sichtbarer räumlicher Staffelung; kleine Displays verwenden Swipe und dynamische Punkte.
- Die sichtbaren Chevron-Navigationen liegen um `var(--cmp-spacing-lg)` innerhalb des Swipers. Dadurch bleibt ihr an den Back-to-top-Button angelehntes Schattenbild sichtbar und wird nicht am `uk-container` abgeschnitten.

### Schritt 2 – Sitzplatzwahl im Gemeindesaal

Bereits implementiert:

- strikter, versionierter JSON-Import mit dem 72-Platz-Testplan sowie dem als Layout-ID 5 importierten und dem neuen Testevent 11 zugeordneten 178-Platz-Plan `gemeindesaal-2027` Version 1 und fünf normalisierten Sitztabellen;
- `SeatLayoutService`, `EventSeatInventoryService`, `SeatSelectionService`, Definition-Value-Objects und gemeinsame Nutzung von Warenkorb, Ablauf, Revision und Transaktionsgrenzen;
- DPCalendar-Eventzuordnung über das bestehende Systemplugin sowie eine getrennte Administrator-Diagnoseview;
- `seatselection`-Model, -View und -Template mit UIkit-Accordion ausschließlich für Warenkorb-Events;
- `TicketseatsController` mit `state`, `assign` und `suggest`, privatem `no-store`, POST/CSRF für Mutationen, HTTP 409 bei Konflikten und normalem POST-Fallback;
- servergerenderter Sitzplan mit Bühne, Mittelgang, Ein-/Ausgängen, Tischen, Legende, Auswahlzähler, einfacher Sitztextliste, direkter Rückmeldung und deterministischem Platzvorschlag;
- vollständige Fit-to-view-Ausgangsperspektive in jedem geprüften Viewport, eigene kräftige SVG-Lupen, „Gesamtansicht“, Strg+Mausrad, Zwei-Finger-Zoom, begrenzter Pan-Viewport und direkte Tischfokussierung;
- kleine, dynamische UIkit-Chevrons mit runden Schatten und Kantenverläufen für die horizontal überlaufende Tisch-Navigation auf kleinen Displays;
- einfache umbrechende Sitztextliste mit nativen roten X-Buttons, automatischem Speichern und weiterhin vorhandenem, nur bei aktiver ES6-Laufzeit ausgeblendetem POST-Fallback;
- konsistente Success-Darstellung, Warenkorbzeile „N Sitze ausgewählt“ sowie der Drawer-sichere Link „Zur Ticketauswahl“ unterhalb von „Warenkorb leeren“;
- gültiger Busy-/Polling-Guard, vollständige Initialisierung neuer Eventknoten bei `joomla:updated` und selektive DOM-Aktualisierung ohne Vollchurn aller 178 Sitze;
- Step-1-Kapazitätsgrenze für zugeordnete Inventare sowie Abstimmung vorhandener Sitze bei Mengenänderung, Entfernen und Leeren;
- fünfstufige Anzeige und funktionaler Übergang aus einem fortsetzbaren Step-1-Warenkorb;
- Navigation unter dem Accordion mit gedämpftem Zurück-Button, zustandsabhängig aktivem „Weiter zu den Kundendaten“, bereinigtem Leerzustand ohne Icon sowie entferntem kontextlosen Sitzplan-Link im Warenkorb-Drawer.

Noch offen beziehungsweise vor Produktivfreigabe zwingend zu erledigen:

- vollständig fehlende Event-Sitzzuordnung in Landingpage, Step 1 und direkter Reservierung fail-closed behandeln;
- native öffentliche DPCalendar-Buchungserzeugung für CopyMyPage-Sitzveranstaltungen serverseitig sperren, ohne Zahlungsrückläufe oder vorhandene Buchungen/Tickets zu blockieren;
- Backend-Bedienung für Hotline-/Backup-Sperren separat spezifizieren und umsetzen; die vorhandenen Servicemethoden allein sind kein nutzbarer Admin-Ablauf;
- den bereits in Event 11 auf `ready` gesetzten Plan in den noch offenen serverseitigen Parallel-, Konflikt-, Ablauf-, Manipulations- und reinen POST-Fallback-Szenarien weiter prüfen. Tastatur, Fit-to-view, Zoom, Tisch-Navigation, wiederholte Interaktion und mehrere Displaygrößen sind am 2026-08-26 browserseitig abgenommen. Die Kinderkarneval-Variante bleibt optional und liegt noch nicht vor;
- vor dem Wechsel der bereits mit dem Testlayout verbundenen Dev-Events einen sicheren Prozess festlegen: derzeit kann eine vorhandene `event_seating`-Zeile nicht auf eine neue Layout-ID umgestellt werden;
- den verbleibenden Step-2-Prüfkatalog einschließlich Parallelkonflikt, Ablauf, 200-Sitz-Grenzfall, Manipulationsfällen und reinem POST-Fallback nachvollziehbar abnehmen;
- die inzwischen vorhandene DPCalendar-Buchungs-/Ticketübergabe in den noch offenen Parallel-, Rollback-, Abbruch- und Timeoutfällen abnehmen und die endgültige Ticketbeschriftung „Tisch X, Sitz Y“ in Ticket-, QR- und Einlassdarstellung integrieren.

Schritt 2 erzeugt weiterhin keine DPCalendar-Buchung und kein DPCalendar-Ticket. Er speichert ausschließlich die konkrete, an den bestehenden Warenkorb gebundene Sitzwahl. Ein einzelnes Event ist fachlich vollständig, wenn es `ready` und katalogfähig ist und exakt so viele eigene `held`-Sitze wie reservierte Tickets besitzt. Der Übergang zu Schritt 3 ist in diesem Zustand aktiv; für eine Produktivfreigabe bleiben die ausdrücklich offenen Sicherheits- und Parallelkriterien zwingend.

### Schritt 3 – Kundendaten

Status am 2026-08-28: Schritt 3 ist in der lokalen Joomla-Instanz als `view=customerdata` implementiert. Die View zeigt ausschließlich das Kundendatenformular; eine zusätzliche Warenkorbzusammenfassung oberhalb des Formulars wurde bewusst nicht übernommen. Überschrift und Einleitung lauten „Deine Angaben zur Bestellung“ und „Gib deine Rechnungsdaten an oder melde dich mit deinem Kundenkonto an. Die Angaben werden für Bestellbestätigung und Rechnung benötigt.“

#### Eintritt, Speicherung und Übergang

- `CustomerDataService::canEnter()` verlangt einen aktiven, nicht abgelaufenen Warenkorb der aktuellen Joomla-Session und `SeatSelectionService::isCheckoutReady()`. Damit müssen alle Positionen fortsetzbar sein und jedes sitzpflichtige Event exakt die reservierte Menge als eigene `held`-Sitze besitzen.
- Der Step-2-Link wird nur bei `allComplete` aktiv. Ein direkter oder manipulierter Step-3-Aufruf umgeht den Serverguard nicht; ohne vollständigen Zustand rendert die View einen blockierenden Hinweis und die Rückkehr zum Sitzplan.
- `customerdata.save` validiert den Guard vor und innerhalb der Schreibtransaktion erneut. Zusätzlich muss die gesendete `expectedCartRevision` zur gesperrten Warenkorbzeile passen.
- Gültige Angaben werden als genau ein Entwurf in `#__copymypage_ticket_customers` gespeichert oder aktualisiert. Ein erfolgreicher Save erhöht Revision und Ablaufzeit des gemeinsamen Warenkorbs mit `advanceCart()` genau einmal. Read-only-Aufrufe verlängern den Timer nicht.
- Nach erfolgreichem Speichern leitet HTTP 303 zu `view=orderreview`. Dessen Guard verlangt weiterhin einen aktiven, checkout-bereiten Warenkorb und einen erneut gültig geprüften Kundendatenentwurf.
- Schritt 3 erzeugt weder DPCalendar-Buchung noch DPCalendar-Ticket, startet keine Zahlung und ändert keinen Sitz von `held` zu `booked`.

#### Vier abgebildete Nutzerfälle

1. **Gast kauft nur Tickets:** Unterhalb der Einleitung sieht ein Gast die UIkit-Pill-Weiche „Mit Konto“ / „Ohne Konto“. „Ohne Konto“ ist der Standard und zeigt ausschließlich das Rechnungsformular sowie die optionale Kontoerstellung. Bleibt der Kontoschalter aus, werden nur die Rechnungsdaten gespeichert.
2. **Gast kauft Tickets und legt ein Konto an:** Der Schalter „Kundenkonto anlegen“ öffnet Benutzername, Passwort, Passwortbestätigung, Joomla-Datenschutzfeld und – entsprechend der Joomla-Konfiguration – CAPTCHA. Die eingegebene Rechnungs-E-Mail wird als Benutzername vorgeschlagen, solange der Benutzer keinen eigenen Wert gesetzt hat. Passwortregeln und Stärkeanzeige stammen aus dem Joomla-Registrierungsformular. Ein Hinweis erklärt Aktivierungslink beziehungsweise Administratorfreigabe.
3. **Vorhandenes Konto mit vollständiger Rechnungsanschrift:** „Mit Konto“ öffnet ein eingebettetes Joomla-Anmeldeformular. Nach erfolgreicher Anmeldung wird Schritt 3 neu geladen, die Gastweiche entfällt und Name, E-Mail sowie die vorhandene CopyMyPage-Profiladresse werden in das Rechnungsformular übernommen. Der Benutzer prüft die Angaben und speichert sie bestellungsbezogen.
4. **Vorhandenes Konto ohne vollständige Rechnungsanschrift:** Joomla-Name und E-Mail sowie alle tatsächlich vorhandenen Adressteile werden vorbefüllt. Fehlende Pflichtfelder bleiben sichtbar leer; „Weiter zur Zusammenfassung“ bleibt deaktiviert, bis die Rechnungsanschrift vollständig und gültig ist. Das dauerhafte Profil wird dabei nicht stillschweigend verändert.

Eine fehlgeschlagene Anmeldung kehrt zum aktiven Reiter „Mit Konto“ zurück und zeigt die Joomla-Fehlermeldung. Eine erfolgreiche Anmeldung verwendet denselben aktuellen Warenkorb; es wird kein zweiter Warenkorb erzeugt. Für bereits angemeldete Benutzer wird die Gast-/Kontoweiche nicht gerendert.

#### Formular- und UI-Vertrag

- Die dritte von fünf Schrittmarken ist aktiv. Das Layout verwendet die vorhandenen CopyMyPage-Formular-, Karten-, Feld- und Buttonverträge aus `docs/UI_STYLE_GUIDE.md` und die bestehenden `--cmp-*`-Tokens.
- Pflichtfelder für alle Käufe sind Vorname, Nachname, E-Mail-Adresse, Straße, Hausnummer, Postleitzahl, Ort und Land. Region und Telefon sind optional; eine eingegebene Region muss zum serverseitig aufgelösten Land gehören.
- Joomla-Formfilterung, E-Mail-Prüfung, Längenlimits und serverseitige Länder-/Regionskataloge bleiben autoritativ. Die Felder besitzen passende `autocomplete`-Attribute.
- Die optionale Kontoerstellung ist ausschließlich für Gäste verfügbar, wenn Joomla die Benutzerregistrierung erlaubt und für diesen Warenkorb noch kein Konto verknüpft ist. Verdeckte Kontofelder werden vollständig deaktiviert und sind dann weder Pflichtfelder noch Teil der Formsubmission.
- Unterhalb des gesamten Formularblocks existieren genau zwei Checkout-Aktionen: „Zurück zum Sitzplan“ links und „Weiter zur Zusammenfassung“ rechts. Auf kleinen Displays werden beide auf volle Breite gestapelt; die Primäraktion steht oberhalb der Rückkehraktion.
- Die Primäraktion gehört per `form`-Attribut zum Rechnungsformular und ist clientseitig im Reiter „Mit Konto“ sowie bei ungültigen aktiven Formularfeldern deaktiviert. Diese Komfortprüfung ersetzt nicht die serverseitige Validierung; ohne JavaScript bleibt der normale POST-Fallback erhalten.
- Die View und der Step-4-Einstieg sind privat, `no-store`, `noindex` und `nofollow`. Labels, Pflichtfeldsemantik, Fokuszustände, Joomla-Fehler und native Browservalidierung bleiben erhalten.

#### Konten-, Profil- und Datenschutzvertrag

- Vorbefüllung ist eine einmalige Projektion in den Bestellentwurf. Schritt 3 schreibt keine Rechnungsdaten in das dauerhafte Joomla-/CopyMyPage-Profil zurück.
- Die Kontoerstellung verwendet das installierte Joomla-Core-Registrierungsmodell. Name wird aus Vor- und Nachname zusammengesetzt; die Rechnungs-E-Mail wird als Registrierungs-E-Mail verwendet. Joomla bleibt Quelle für Benutzergruppe, Passwortregeln, Datenschutzplugin, CAPTCHA, Aktivierung und Aktivierungsnachricht.
- In der Kundendatentabelle werden nur Rechnungsdaten und Joomla-Benutzer-IDs gespeichert. Benutzername, Passwort, Passwortbestätigung und CAPTCHA werden dort nicht abgelegt.
- Nach einem Validierungsfehler bewahrt der PRG-Zustand Rechnungsfelder, optionalen Benutzernamen und Datenschutzstatus auf. Passwörter und CAPTCHA werden ausdrücklich verworfen.
- Läuft ein Warenkorb ab oder wird er freigegeben, entfernt `TicketCartContextService` den Kundendatenentwurf. Der Datenbank-Fremdschlüssel schützt zusätzlich den Lebenszyklus bei einer Löschung des Warenkorbs.

#### Verifizierter Stand und bewusste Grenze

Der vollständige Browserlauf deckte Gast ohne Konto, das Öffnen und Validieren der optionalen Kontoerstellung, fehlerhafte und erfolgreiche Anmeldung, vollständige Profilvorbefüllung, Ergänzung einer fehlenden Rechnungsanschrift, Buttonzustände, Desktop-/Mobile-Anordnung und den geschützten Übergang zu Schritt 4 ab. Die tatsächliche Anlage eines neuen Joomla-Kontos wurde wegen des live aktiven CAPTCHA nicht automatisiert abgeschlossen; dieser letzte interaktive Unterfall bleibt Teil des Benutzertests. Syntax-, Lint-, XML-, JSON-, Sprach-, Source-/Min- und CSS-`calc()`-Prüfungen waren erfolgreich. Alle angelegten Testbenutzer, Profil- und Datenschutzdatensätze, Testwarenkörbe, Sitzhaltungen und temporären Artefakte wurden anschließend entfernt.

### Schritt 4 – Prüfen und bestätigen

Status am 2026-08-30: Schritt 4 ist in der lokalen Joomla-Instanz als geschützte Bestellprüfung mit verbindlichem Abschluss-POST implementiert. Die vierte von fünf Schrittmarken ist aktiv. Der Einstieg aus Schritt 3 heißt weiterhin „Weiter zur Zusammenfassung“ und der bloße GET auf `view=orderreview` startet keine Zahlung. Erst „Zahlungspflichtig bestellen“ beziehungsweise „Kostenlos bestellen“ führt den transaktionalen Commit und die DPCalendar-Übergabe aus.

#### Eintritt und autoritative Momentaufnahme

- `OrderReviewService::getViewState()` baut die Übersicht bei jedem Request neu aus `CustomerDataService`, `TicketReservationService` und `SeatSelectionService` auf. Es gibt keinen im Browser fortgeschriebenen oder separat gespeicherten Review-Zustand.
- `CustomerDataService::getReviewCustomerData()` verlangt zunächst einen aktiven Warenkorb und `SeatSelectionService::isCheckoutReady()`. Der warenkorbgebundene Kundendatenentwurf wird mit den aktuellen Formular-, Länder- und Regionsregeln erneut validiert. Ausgegeben werden ausschließlich Vorname, Nachname, E-Mail, Straße, Hausnummer, Postleitzahl, Ort, Land, optionale Region und optionales Telefon.
- Benutzername, Passwort, Passwortbestätigung, CAPTCHA, Aktivierungstoken, Session-Token und sonstige Konto- oder Registrierungsgeheimnisse gelangen nicht in den Review-Zustand.
- Anschließend verlangt `OrderReviewService` einen aktiven, fortsetzbaren, nicht abgelaufenen und nicht leeren Warenkorb sowie eine vollständige, nicht leere Sitzwahl. `cartRevision` und `totalTickets` müssen in Warenkorb- und Sitzprojektion identisch sein.
- Jedes Sitz-Event muss eine positive Event-ID, einen vollständigen Zustand und exakt die geforderte Zahl gültiger eigener Sitz-IDs mit nicht leerer Beschriftung besitzen. Jede Warenkorbposition muss fortsetzbar sein, mindestens eine gültige Preiszeile besitzen und sich genau einem Sitz-Event zuordnen lassen.
- Die Summe der Preisartenmengen muss je Position der Ticketmenge entsprechen; die Zahl der Sitzplätze muss ebenfalls exakt dieser Menge entsprechen. Abschließend müssen aggregierte Ticketzahl und Eventzahl über Warenkorb und Sitzprojektion übereinstimmen.
- Scheitert eine dieser Prüfungen, wird die gesamte Ansicht blockiert. Es erscheinen weder Teilpreise noch teilweise Kundendaten oder eine aus unterschiedlichen Revisionen zusammengesetzte Zusammenfassung. Der Rückweg „Kundendaten bearbeiten“ bleibt verfügbar.

#### Checkoutvorbereitung, Bedingungen und Zahlungsanbieter

- `OrderCheckoutService::getViewState()` ergänzt die read-only Projektion ohne Mutation. Mit DPCalendars Pipeline-Stufen `CollectEventsAndTickets` und `SetupForNew` wird aus `event_id[event][tickets][price]` derselbe Basispreis samt Währung berechnet, den DPCalendar für die spätere Buchung verwendet. Jede Event-/Preisindexmenge muss unverändert in der Pipeline erhalten bleiben.
- Aktive DPCalendar-Zahlungsplugins liefern die grundsätzlich verfügbaren Anbieter. Pro Warenkorb bleibt nur die Schnittmenge der Anbieter übrig, die für alle enthaltenen Events erlaubt ist. Anbietergebühr und Endbetrag werden nach derselben Preis-/Steuerlogik berechnet, die DPCalendar beim Bestätigen anwendet.
- Bei einem positiven Endbetrag ist eine gültige Zahlungsart Pflicht. Bei einem Endbetrag von `0` wird keine Zahlungsart verlangt und die kostenlose Buchung später direkt zum DPCalendar-`order`-Layout geführt.
- Für jedes Event wird zuerst dessen eigener DPCalendar-Beitrag `terms`, andernfalls der globale Komponentenwert `event_form_terms` aufgelöst. Alle benötigten Beiträge müssen veröffentlicht und vollständig ladbar sein. Ein fehlender Beitrag, eine fehlende Währung oder eine leere gemeinsame Anbieter-Schnittmenge blockiert den Checkout fail-closed.
- Das Installations-/Updatescript von `dev0.0.20` legt bei Bedarf die Kategorie „CopyMyPage Rechtliches“ und den deutschsprachigen Beitrag „Allgemeine Geschäftsbedingungen“ aus `terms/terms-and-conditions.de-DE.html` an. Vorhandener redaktioneller Inhalt wird nicht überschrieben; nur ein leerer beziehungsweise der bekannte lokale Testplatzhalter wird mit der Vorlage gefüllt. Eine bereits gesetzte DPCalendar-Standardbedingung bleibt unangetastet, andernfalls wird der neue Beitrag als `event_form_terms` eingetragen.
- Die gebündelte AGB-Datei ist ausdrücklich nur ein technischer Starttext mit Anbieterplatzhaltern und Hinweis auf eine erforderliche rechtliche Prüfung. Ihre automatische Bereitstellung ist keine redaktionelle oder juristische Freigabe für den Produktivbetrieb.
- Eine HMAC-SHA-256-Checkoutsignatur bindet die dargestellten Bedingungshashes, Zahlungsanbieter, Gebühren, Endbeträge, Grundpreis, Währung, Warenkorb-ID und Revision an das Joomla-Secret. Änderungen zwischen Anzeige und Absenden führen zu einer neuen Prüfung statt zur Übernahme veralteter Browserwerte.

#### Sichtbare Zusammenfassung, Zustimmung und Navigation

- Die Seite trägt die Überschrift „Bestellung prüfen und bestätigen“ und erläutert, dass Auswahl und Rechnungsdaten vor der Bezahlung noch einmal geprüft werden sollen.
- Die frühere redundante Zwischenüberschrift „Zusammenfassung“ samt Wiederholungstext ist entfernt. „Tickets und Sitzplätze“ bildet nun den sichtbaren Einstieg in den Inhaltsblock, ist zentriert und erhält als dekorative, `aria-hidden` gesetzte Icongruppe das lokale Ticket- und Sitz-SVG. Schrift und Icons sind präsenter als die folgenden Abschnittsüberschriften, bleiben aber kleiner als die Seitenüberschrift.
- Direkt unter „Tickets und Sitzplätze“ sowie unmittelbar oberhalb der Gesamtsumme liegen zwei gleich starke, über die volle Inhaltsbreite durchgezogene Divider. Die Abstände innerhalb der Zahlungs- und Zustimmungsabschnitte bleiben responsiv; insbesondere besitzt die Zahlungsanbieterauswahl einen klaren Abstand zu ihrem Einführungstext.
- Je Veranstaltung erscheinen Titel und Termin. Jede Preisart zeigt „Menge × Bezeichnung“, darunter den Einzelpreis und rechts die Zeilensumme. Nur diese Zeilensumme verwendet ein UIkit-Badge mit `var(--cmp-color-text-muted)` und weißer Schrift; die Bestellgesamtsumme bleibt als normaler hervorgehobener Text außerhalb eines Badges.
- Die konkreten serverseitigen Sitzbeschriftungen werden je Event als einfache umbrechende Liste ausgegeben. Die Rechnungsdaten zeigen Name, Straße/Hausnummer, Postleitzahl/Ort, optional Region, Land, E-Mail und optional Telefon.
- Veranstaltungstitel, „Rechnungsdaten“, „Zahlungsart“, „Zustimmung“ und „Gesamt“ verwenden dieselbe Abschnittsgröße. Zahlungsanbieter erscheinen als auswählbare Karten mit Anbietertext, optionaler Gebühr und dem vollständigen anbieterbezogenen Gesamtbetrag.
- Die Zustimmung besteht aus genau einem gemeinsamen Formularblock ohne blaue linke Borderlinie. Checkbox, Satzanfang und Bedingungslinks bilden einen zusammenhängenden Satz; der Satzpunkt gehört zum letzten Linktext und kann deshalb auf schmalen Displays nicht allein in die nächste Zeile fallen.
- Der Bedingungslink öffnet den vorhandenen CopyMyPage-Content-Drawer mit Fragmenttransport, sodass der Benutzer den Bestellprozess nicht verlässt. Der reguläre Beitragslink bleibt als Fallback und der Drawer bietet weiterhin das bewusste Öffnen auf einer eigenen Seite an.
- Das Orderreview-WebAsset hält die Primäraktion deaktiviert, bis die Pflichtcheckbox markiert ist. `required`, serverseitige Zustimmungsprüfung und Checkoutsignatur bleiben die autoritative Grenze; JavaScript ist nur progressive Bedienhilfe.
- Die Navigation enthält „Kundendaten bearbeiten“ mit dem gemeinsamen Zurück-Buttonvertrag sowie den echten Submit „Zahlungspflichtig bestellen“ oder „Kostenlos bestellen“. Auf kleinen Displays werden die Aktionen über den bestehenden Customerdata-Navigationsvertrag gestapelt.

#### Verbindlicher Commit und DPCalendar-Übergabe

- Das Anzeigen oder erneute Laden von Schritt 4 erhöht weder Warenkorbrevision noch Reservierungsfrist. Es verändert keine Warenkorbposition, keinen Kundendatenentwurf, keine Sitzhaltung und keinen DPCalendar-Datensatz.
- `OrderreviewController::checkout()` akzeptiert ausschließlich POST mit gültigem Joomla-CSRF-Token. Fehlende Zustimmung, ungültige Revision, unbekannter Anbieter oder ungültige Signatur werden abgewiesen und per HTTP 303 zur neu aufgebauten Prüfung zurückgeführt.
- `OrderCheckoutService::checkout()` sperrt den aktiven Warenkorb, danach alle Eventdatensätze in aufsteigender Reihenfolge und anschließend die eigenen gehaltenen Sitze. Hinter diesen Sperren werden Orderreview, DPCalendar-Preis, Bedingungen, Anbieter und Signatur vollständig neu berechnet.
- Das DPCalendar-Administratormodell erzeugt genau eine Buchung für alle unabhängigen Warenkorb-Events und die zugehörigen Tickets zunächst im Reviewzustand `2`. Die erzeugten Ticketgruppen müssen den erwarteten Mengen pro Event und Preisindex exakt entsprechen.
- DPCalendar-Tickets und CopyMyPage-Sitze werden innerhalb derselben Datenbanktransaktion nach Event, Preisindex und stabiler Reihenfolge gekoppelt. Jeder Sitz erhält genau eine Ticket-ID und wechselt von `held` zu `booked`.
- Der Warenkorb wechselt auf `converted`, erhält Buchungs-ID, Zahlungsanbieter, Zustimmungszeit und den vollständigen versionierten Akzeptanzsnapshot. Kundendatenentwurf und Sessiontoken werden erst im erfolgreichen Umwandlungsablauf entfernt.
- Anschließend ruft CopyMyPage DPCalendars Site-Controller `BookingController::confirm()` auf. Ein kostenpflichtiger Auftrag muss danach Status `3`, den gewählten Anbieter und exakt den dargestellten Endbetrag besitzen; ein kostenloser Auftrag Status `1`. Jede Abweichung rollt die Datenbanktransaktion zurück.
- Nach dem Commit führt HTTP 303 bei kostenpflichtigen Buchungen zu `layout=pay`, bei kostenlosen unmittelbar zu `layout=order`. Der Warenkorb kann nach Entfernung seines Sessiontokens nicht erneut über Schritt 4 abgesendet werden.
- Löschung, Stornierung, Erstattung oder Papierkorbstatus einer verknüpften DPCalendar-Buchung lösen über das CopyMyPage-Systemplugin die Freigabe der Sitze und den Warenkorbstatus `released` aus. Eine automatische Behandlung dauerhaft verlassener Status-`3`-Buchungen ist dagegen noch offen.

#### Step-4-Dateikarte für die Fortsetzung

Alle Pfade sind relativ zu `C:\wamp\www\joomla6`:

- fachliche Projektion und Commit: `components/com_copymypage/src/Service/OrderReviewService.php`, `OrderCheckoutService.php`, `TicketCartContextService.php` und `src/Service/CustomerDataService.php`;
- HTTP-/View-Schicht: `components/com_copymypage/src/Controller/OrderreviewController.php`, `src/Model/OrderreviewModel.php`, `src/View/Orderreview/HtmlView.php` und `tmpl/orderreview/default.php`;
- DI-Registrierung: `plugins/system/copymypage/src/Extension/CopyMyPage.php`;
- gemeinsame Darstellung und Laufzeit: `media/com_copymypage/css/template.css`, `template.min.css`, `js/copymypage-order-review.js`, Min-Datei und `joomla.asset.json`; die Cache-Revisionen stehen auf `template = 0.0.68` und `copymypage.order-review = 0.0.1`;
- lokale Icons: `media/com_copymypage/images/icons/ticket-selection.svg` und `seat-selection.svg`;
- Bedingungen/Installation: `administrator/components/com_copymypage/script.php`, `terms/terms-and-conditions.de-DE.html`, `sql/updates/mysql/0.0.20.sql` sowie Installations-/Deinstallations-SQL;
- Sprachtexte: `components/com_copymypage/language/de-DE/com_copymypage.ini`, `components/com_copymypage/language/en-GB/com_copymypage.ini`, `components/com_copymypage/language/es-ES/com_copymypage.ini`, `components/com_copymypage/language/fr-FR/com_copymypage.ini` und `components/com_copymypage/language/it-IT/com_copymypage.ini`.

### Schritt 5 – Zahlung, Vollzugsmeldung und Tickets

Die sichtbare CopyMyPage-Schrittanzeige besitzt insgesamt fünf Marken. Die frühere Dokumenttrennung in „Schritt 5 – Zahlung“ und „Schritt 6 – Abschluss“ ist damit überholt: Zahlung und anschließende Vollzugsmeldung gehören zum fünften und letzten sichtbaren Schritt. Die Abschlussseite ist keine erneute Warenkorbzusammenfassung, sondern das statusabhängige Ergebnis der in Schritt 4 verbindlich abgegebenen Bestellung.

#### Bereits vorhandener nativer DPCalendar-Ablauf

- Kostenpflichtige Buchungen erreichen DPCalendar mit Status `3` und `layout=pay`. Das Layout ruft `onDPPaymentNew` auf; das installierte PayPal-Plugin erzeugt über die PayPal Orders API eine Transaktion, speichert deren ID an der Buchung und leitet zum PayPal-Freigabelink weiter.
- Die vom Plugin gebildete Rückkehr-URL führt zu `task=booking.pay`. Dort lädt DPCalendar die Buchung, ruft `onDPPaymentCallBack` auf und lässt das PayPal-Plugin die Order abfragen beziehungsweise nach Freigabe serverseitig erfassen. In der installierten Pluginversion ist dies ein Browser-Rücklauf mit nachgelagertem API-Aufruf, kein eigener CopyMyPage-Webhook.
- Das PayPal-Plugin ordnet `COMPLETED` dem DPCalendar-Status `1`, `CREATED` beziehungsweise `APPROVED` dem Status `4` und sonstige Ergebnisse dem Status `6` zu. Bei erfolgreicher Verarbeitung leitet DPCalendar zu `layout=order`; Abbruchpfade führen über `paycancel` zur Löschung beziehungsweise Cancel-Ansicht.
- Kostenlose Buchungen erhalten bereits beim Step-4-Commit DPCalendar-Status `1` und werden ohne Zahlungsanbieter unmittelbar zum `order`-Layout geleitet.
- Das native `order`-Layout zeigt „Danke für Ihre Buchung“ sowie den konfigurierten Buchungstext. Seine View schützt den Layoutaufruf jedoch nicht selbst auf einen erfolgreichen Zahlungsstatus. Ein manuell angehängtes `layout=order` kann deshalb auch für eine Buchung im Status `3` die Dankesmeldung rendern und ist ausdrücklich kein Zahlungsnachweis.

#### Noch zu implementierender CopyMyPage-Abschlussvertrag

- Schritt 5 benötigt eine eigene schmale View beziehungsweise einen updatefesten Override/Integrationspunkt, der die DPCalendar-Buchung autoritativ lädt und die Erfolgsmeldung ausschließlich für einen fachlich freigegebenen Zustand ausgibt. Für den aktuellen vollständigen PayPal-Abschluss ist dies Status `1`.
- Status `3` muss als „Zahlung ausstehend“ mit sicherer Fortsetzung oder Wiederaufnahme behandelt werden; Status `4`, `6`, `7` und `10` benötigen jeweils bewusst festgelegte Anzeigen und Aktionen. Die URL beziehungsweise der Layoutname darf niemals den Erfolg bestimmen.
- Die fünfte CopyMyPage-Schrittmarke, Bestellnummer, Veranstaltung, Ticketart und Sitzkennung sollen verständlich angezeigt werden, ohne Schritt 4 als zweite Bestellprüfung zu duplizieren.
- DPCalendar-Ticket-, Download- und QR-Code-Ausgabe werden über schmale Integrationspunkte um die bereits gespeicherte CopyMyPage-Sitzzuordnung ergänzt. E-Mail, Rechnung/Beleg, Download, QR-Code und Einlassprüfung bleiben Bestandteil der Endabnahme.
- Verlassene Status-`3`-Buchungen benötigen einen belastbaren Timeout-/Reconciliation-Vertrag. Er muss DPCalendar-Buchung, umgewandelten Warenkorb, Tickets und `booked`-Sitze gemeinsam behandeln und gegen verspätete oder wiederholte Zahlungsrückläufe abgesichert sein.
- Darstellung und Zustandsverzweigungen können lokal mit kontrollierten Testbuchungen sowie dem kostenlosen Status-`1`-Pfad geprüft werden. Der echte PayPal-Sandbox-Rundlauf einschließlich Redirect, Capture, Transaktions-ID und Rückkehr bleibt bis zu einer geeigneten HTTPS-Testumgebung ausdrücklich offen.

## Offene Entscheidungen

- Für die aktuelle Testphase werden alle 178 Sitze aus `gemeindesaal-2027` Version 1 online angeboten. Welche konkreten Plätze später veranstaltungsbezogen als Hotline-/Backup-Plätze blockiert werden, ist noch festzulegen; eine Sperre gehört ins Eventinventar und nicht als dauerhaft fehlender Sitz in die Layoutdefinition.
- Der reale Grundriss und die Tischkapazitäten liegen für Version 1 vor. Weitere physische Präzisierungen sind kein Startblocker mehr. Falls die Testdarstellung von der endgültigen Saalbestuhlung abweicht, liefert der Benutzer die Korrekturen später; daraus entsteht eine neue unveränderliche Layoutversion.
- Der 72-Platz-Plan `gemeindesaal-test` Version 1 bleibt den bestehenden Dev-Events 5, 6 und 9 zugeordnet. `gemeindesaal-2027` Version 1 wird mit dem neu angelegten Testevent 11 erprobt; bestehende Zuordnungen werden nicht dafür umgestellt.
- Ob und wie stark die Kinderkarneval-Bestuhlung vom Standardplan abweicht, wird erst nach Vorliegen des zweiten Grundrisses entschieden. Bis dahin ist sie nur als unterstützte zweite Variante vorgesehen.
- Der DPCalendar-Eventeditor kann bereits eine serverseitig bereitgestellte JSON-Datei auswählen. Ob zusätzlich ein sicherer Backend-Upload benötigt wird, ist noch keine freigegebene Anforderung; weitere Backend-Anpassungen werden separat mit dem Benutzer abgestimmt.
- Die gewünschte schnelle Auswahl einer neuen JSON-Version ist für ein neues beziehungsweise noch unzugeordnetes Event möglich. Für ein bereits zugeordnetes Event fehlt derzeit ein kontrollierter Draft-Reassignment-/Entfernungsablauf; dieser darf nicht durch direkte Datenbankänderungen ersetzt werden.
- Der technische AGB-Beitrag und die Step-4-Zustimmung sind vorhanden. Vor Produktivbetrieb müssen Anbieterangaben, Veranstalterrolle, Stornierung, Widerruf, Haftung, Streitbeilegung und gegebenenfalls veranstaltungsspezifische Ergänzungen redaktionell vervollständigt und rechtlich geprüft werden.
- Welche maximale Ticketmenge pro Event beziehungsweise Preisart soll unabhängig von DPCalendar gelten?
- Der Warenkorb wechselt derzeit beim Step-4-Commit direkt auf `converted` und die Sitze auf `booked`. Festzulegen ist die maximale zulässige Lebensdauer einer zahlungsausstehenden DPCalendar-Buchung im Status `3` und welcher Job beziehungsweise Callback sie danach sicher auflöst.
- Für DPCalendar-Status `1`, `3`, `4`, `6`, `7` und `10` ist verbindlich festzulegen, welche Step-5-Anzeige, Wiederaufnahme, manuelle Prüfung oder Freigabe gilt. Insbesondere darf eine verspätete PayPal-Rückkehr nicht gegen eine bereits freigegebene Sitzzuordnung laufen.
- Zu entscheiden ist, ob der Abschluss als eigene CopyMyPage-View mit DPCalendar-Datenquelle oder als schmaler Template-Override des DPCalendar-`order`-/`pay`-Ablaufs umgesetzt wird. In beiden Fällen muss der Statusguard serverseitig und updatefest sein.
- Zu entscheiden ist, ob eine Joomla-Scheduled-Task sowohl abgelaufene aktive Warenkörbe als auch verlassene umgewandelte Zahlungsbuchungen reconciliert und wie Administratoren unklare Zahlungen manuell nachprüfen können.

## Prüfkatalog

### Abgenommen in Schritt 1

- [x] Zwei parallele Sessions konkurrieren um das letzte Ticket; bei einem Vorbehalt von 199 aus 200 Tickets war genau eine der beiden gleichzeitigen Anfragen erfolgreich.
- [x] Zwei bereits mit je 99 von 200 Tickets gefüllte Warenkörbe erhöhen unter einer vorgeschalteten Event-Sperre gleichzeitig auf je 101. Genau eine Mutation gewinnt, die andere erhält die autoritative Kapazitätsmeldung und die Summe bleibt `200`. Damit ist der `REPEATABLE-READ`-Fall mit vorbestehenden Warenkorbpositionen gezielt abgedeckt.
- [x] Die eigene reservierte Menge wird bei weiteren Mengenänderungen berücksichtigt.
- [x] Ein gezielt abgelaufener Warenkorb reduziert die Verfügbarkeit nicht mehr und wird beim nächsten Request als abgelaufen markiert.
- [x] Eine CSRF-fehlerhafte Mutation wird ohne Warenkorbänderung abgewiesen.
- [x] Ein ausverkauftes Event bleibt sichtbar, bietet aber weder Reservierung noch Warteliste an.
- [x] Mehrere Events lassen sich gemeinsam reservieren; Einzelentfernung, Gesamtsumme und vollständiges Leeren wurden geprüft.
- [x] Landingpage-Bestand und Fortschritt reagieren auf Reservieren und Freigeben einer anderen Session. Isoliert geprüft: Ausgang `72 verfügbar / 0 von 72 / 0 %`; zweite Joomla-Session hält `5`; erste Session sieht trotz leerem eigenem Warenkorb `67 verfügbar / 5 von 72 / 7 %`; nach Freigabe wieder `72 / 0 / 0 %`. Der Testwarenkorb wurde anschließend freigegeben.
- [x] Der normale Joomla-POST-Fallback reserviert und leert den Warenkorb mit HTTP-303-Rückleitung und Systemmeldung.
- [x] Desktop (1440 × 1000), Tablet (768 × 1024) und kleines Display (390 × 844) zeigen keinen horizontalen Überlauf; das ausgewählte Event ist geöffnet.
- [x] DPCalendar `capacity_used` blieb während aller Reservierungstests unverändert.
- [x] PHP-Lint, XML-, JSON-, JavaScript- und Sprachdateiprüfungen sowie die alphabetische Sprachsortierung wurden ausgeführt.
- [x] Drei aufeinanderfolgende Statusabfragen ließen `expires_at` und `modified` unverändert.
- [x] Manipulierte Event-ID, Preisindex sowie überhöhte und negative Menge wurden jeweils serverseitig abgewiesen; es blieb kein aktiver Warenkorb zurück.
- [x] Zwei Tabs derselben Joomla-Session sehen Mengen und Warenkorb gemeinsam; das Leeren im zweiten Tab wird nach Reload im ersten sichtbar.
- [x] Ein identischer Retry mit alter Revision bleibt erfolgreich, verändert weder `cartRevision` noch `expiresAt`; eine abweichende Anfrage mit alter Revision erhält HTTP 409 und den aktuellen Zustand.
- [x] Landingpage-Modul und Komponente beziehen Eventfilter, Buchungsfenster und neutralen Verfügbarkeitsstatus aus demselben `TicketCatalogService`; die modulseitigen Duplikate wurden entfernt.
- [x] Läuft der Warenkorb im same-origin Drawer vollständig ab, wechseln beide Navbar-Indikatoren auch bei absichtlich unterdrücktem `postMessage` unmittelbar auf `empty`. Zusätzlich tragen beide servergerenderten Indikatoren die gültige Ablaufzeit, wechseln auf der Landingpage per Einmal-Timer ohne offenen Drawer auf `empty` und werden durch den vorhandenen Availability-Request gegen einen autoritativ leeren Sitzungswarenkorb korrigiert. Papierkorb-Icon, rechter Weiter-Chevron und der zustandsabhängige Sitzplan-Button einschließlich Rückkehr zu disabled nach Leeren/Ablauf wurden im iPhone-Viewport geprüft. Der automatische Navbar-Ablauf bleibt bis zu einem neuen Fehlerbericht unverändert im manuellen Langzeittest.
- [x] Wiederholte `joomla:updated`-Events erzeugen weder doppelte Warenkorb-Requests noch zusätzliche Swiper-Strukturen oder Fokus-Listener.

### Abgenommenes Step-2-Teilpaket: Sitzplan 2027

- [x] Die gebündelte Datei `gemeindesaal-2027-v1.json` wird vom produktiven `SeatLayoutService` als Schema 1, Alias `gemeindesaal-2027`, Version 1 mit acht Tischen und 178 Sitzen akzeptiert.
- [x] Tischkapazitäten, Koordinatengrenzen, ganzzahlige Werte, eindeutige Codes, Nummern, Sortierwerte und Sitzpositionen wurden unabhängig geprüft. Es bestehen keine Sitzduplikate, keine außerhalb des Canvas liegenden Elemente und keine Sitzkollision mit dem Mittelgang.
- [x] Die normalisierte Geometrie wurde gegen die bereitgestellte PNG-/Draw.io-Referenz abgeglichen. Bühne, Musik, Loge, Ausgang, Gaststätte, Tischanordnung und der horizontal beschriftete Mittelgang zwischen Tisch 4/5 und Tisch 6/7 sind vorhanden.
- [x] Eine temporäre Desktop- und Mobile-Vorschau renderte 178 Sitze, acht Tische und sechs Orientierungsflächen fehlerfrei; der Plan bleibt auf kleinem Viewport verschiebbar. Die temporären Prüfartefakte wurden anschließend entfernt.
- [x] Die Definition wurde als Layout-ID 5 importiert, für das neue Event 11 vollständig mit 178 Event-Sitzen materialisiert und auf `ready` gesetzt. Der Sitzplan wird in der echten `seatselection`-View fehlerfrei angezeigt. Layout-ID 4 und die Zuordnungen der Events 5, 6 und 9 zum 72-Platz-Testplan blieben unverändert.

Diese Teilabnahme bewertet die statische Vorlage, ihren erfolgreichen Import, die Eventzuordnung und die sichtbare Darstellung. Parallel-, Negativ-, Ablauf-, reiner Fallback- und spätere Buchungsszenarien des 178-Platz-Testevents bleiben davon getrennte Abnahmepunkte.

### Abgenommene Step-2-UI-/Runtime-Revision vom 2026-08-26

- [x] Der komplette 178-Platz-Plan startet bei `1440 × 1000`, `768 × 1024`, `390 × 844` und `667 × 375` vollständig sichtbar und ohne Seitenüberlauf. Zwölf aufeinanderfolgende Viewport-/Orientierungswechsel erhalten Fit-to-view.
- [x] Zoom-in, Zoom-out und Gesamtansicht bleiben nach 80 abwechselnden Zoomaktionen innerhalb der erlaubten Grenzen. Lupenfarbe und Deckkraft sind identisch; nur Gesamtansicht behält den sichtbaren Buttonrand.
- [x] Die mobile Tisch-Navigation zeigt Chevrons nur bei weiterem Inhalt, erreicht das Ende, aktualisiert beide Richtungen, besitzt runde Schatten/Kantenverläufe und liegt bündig an der `uk-container`-Inhaltskante.
- [x] Dreißig wiederholte `joomla:updated`-Events verdoppeln den Zoom-Clickhandler nicht. Ein synthetisch nachträglich eingefügtes Event innerhalb eines bereits initialisierten Roots erhält dennoch seine eigene Zoom- und Navigationsinitialisierung.
- [x] Während jeder Sitzmutation lautet der beobachtete Busy-Zustand exakt `aria-busy="true"` und wird danach entfernt. Die Polling-/Submit-Guards und das Busy-CSS verwenden denselben Wert.
- [x] Sechs Sitzplätze wurden nacheinander per Tastatur ausgewählt; Fokus, Zähler, vollständiger Eventzustand, Success-Haken und ausgewählte Textliste blieben konsistent. Die X-Aktionen sind native, fokussierbare Buttons mit konkreten zugänglichen Namen; Enter entfernte genau einen Sitz und führte den Fokus zum Sitz zurück.
- [x] Zwölf weitere abwechselnde Sitzmutationen endeten ohne Zustandsdrift bei sechs Checkboxen und sechs Listeneinträgen. Die lokale durchschnittliche End-to-End-Zeit betrug `498 ms`, das Maximum `587 ms`.
- [x] Sechs aufeinanderfolgende Sitzänderungen erzeugten `330` statt der vor der Optimierung gemessenen `6.930` DOM-MutationObserver-Records. Der 178-Sitz-Status wird weiterhin vollständig ausgewertet, aber nur tatsächlich abweichende DOM-Zustände werden geschrieben.
- [x] Der Warenkorb-Drawer zeigte „6 Sitze ausgewählt“ unter „6 × Ticket · 84.00 €“. Der Link „Zur Ticketauswahl“ lag unter „Warenkorb leeren“, außerhalb dessen Formulars, verwendete `target="_top"` und navigierte ohne Drawerfehler.
- [x] Der komplette Lauf meldete keine Browser-Console-Errors, unbehandelten Page-Errors oder relevanten Netzwerkfehler. Der isolierte Testwarenkorb wurde auch nach einem zwischenzeitlichen Diagnoseabbruch jeweils vollständig geleert.
- [x] PHP-Lint für das geänderte Seatselection-Template, JavaScript-Syntax für Source und Min, JSON-Validität, CSS-`calc()`-Spacing sowie bytegenaue frische Terser-/clean-css-Rebuilds der beiden Min-Dateien waren erfolgreich.

### Verbindlich für Schritt 2

Die folgenden Kästchen sind Abnahmekriterien, keine bloße Liste vorhandener Klassen. Sie bleiben offen, bis der jeweilige End-to-End- beziehungsweise Negativtest nachvollziehbar ausgeführt wurde. Die Existenz des inzwischen auf `dev0.0.20` stehenden Codes allein gilt nicht als Abnahme; im Live-Baum wurde beim Audit keine automatisierte Step-2-Regressionssuite gefunden.

- [ ] Der Layoutimport akzeptiert höchstens 200 Sitze und lehnt fehlende Pflichtwerte, doppelte Sitzkennungen, doppelte Sortierwerte, ungültige Koordinaten und Teilimporte ab.
- [ ] Eine bereits von einem Event-Inventar verwendete Sitzplanversion bleibt unverändert; eine geänderte Geometrie benötigt eine neue Version.
- [ ] Das Zuweisen einer Sitzplanversion materialisiert genau eine Event-Sitzzeile pro Layoutsitz und kann mit derselben Layout-ID im Draft idempotent wiederholt werden. Der Wechsel zu `ready` verlangt exakte Materialisierung, zulässige Zustände, keine aktiven Warenkorbmengen, keine nativen Tickets und `capacity_used = 0`; eine abweichende DPCalendar-`capacity` bleibt sichtbar diagnostiziert und die kleinere Laufzeitgrenze maßgeblich.
- [ ] Fehlende Zuordnung, `draft`-Status, Teilmaterialisierung oder ungültige Inventarzustände machen ein sitzpflichtiges Event in Landingpage, Schritt 1 und direkten Mutationen nicht buchbar. Eine reine Abweichung der DPCalendar-`capacity` wird diagnostiziert und durch die kleinere gemeinsame Laufzeitgrenze sicher begrenzt.
- [ ] Öffentlicher DPCalendar-CTA, direkter Aufruf der Buchungsformular-View sowie `bookingform.add` und `bookingform.save` können für ein sitzpflichtiges CopyMyPage-Ticket-Event unabhängig von `draft` oder `ready` keine native Buchung ohne CopyMyPage-Sitzzuordnung erzeugen.
- [ ] Erlaubte DPCalendar-Zahlungsrückläufe sowie bestehende Buchungs-, Ticket- und QR-Code-Ansichten werden vom Guard nicht versehentlich blockiert.
- [ ] Zwei parallele Sessions konkurrieren um denselben freien Sitz; genau eine Mutation gewinnt und die andere erhält den autoritativen Konfliktzustand.
- [ ] Eine atomare Ersetzung mit mindestens einem fremd belegten Sitz verändert die letzte erfolgreiche eigene Auswahl nicht teilweise.
- [x] Teilmengen von `0` bis zur getesteten Warenkorbmenge `6` werden unmittelbar gehalten und `complete` wird erst bei der exakten Anzahl wahr. Der Übergang zu Schritt 3 ist ausschließlich im vollständigen Zustand bedienbar und wird dort erneut serverseitig geprüft.
- [ ] Eine Sitzwahl verändert weder Warenkorbmenge noch DPCalendar `capacity_used` und wird in der Landingpage-Verfügbarkeit nicht doppelt abgezogen.
- [ ] Für ein Sitz-Event begrenzt die kleinere von DPCalendar-Restkapazität und Online-Sitz-Rest die neue Warenkorbmenge.
- [ ] Das Backend kann einen verfügbaren Sitz sperren und wieder freigeben, aber weder gehaltene noch gebuchte Sitze überschreiben.
- [ ] Eine Backend-Sperre wird abgewiesen, wenn danach weniger Onlineplätze als aktive Warenkorbmengen verbleiben würden.
- [ ] Interner Sperrvermerk, fremde Warenkorb-ID und die Ursache von `unavailable` erscheinen weder im HTML noch in JSON.
- [ ] Ein abgelaufener Warenkorb verliert seine Sitze ohne weitere Verlängerung; eine andere Session kann sie bei der nächsten atomaren Mutation übernehmen.
- [ ] Eine Warenkorberhöhung erhält die Auswahl und macht den Sitzschritt unvollständig; eine Reduzierung behält deterministisch die räumlich ersten Sitze und gibt den Rest frei.
- [ ] Evententfernung und vollständiges Leeren geben alle zugehörigen gehaltenen Sitze innerhalb derselben Transaktion frei.
- [ ] Warenkorb-, Event-Inventar- und Sitzsperren folgen in allen Pfaden der festgelegten Ressourcenreihenfolge; paralleles Reservieren, Sitzwechseln, Leeren und Backend-Sperren erzeugt keinen reproduzierbaren Deadlock.
- [ ] Manipulierte Event-, Layout- und Sitz-IDs, fremde Warenkörbe, Duplikate, mehr als 200 Werte, Überauswahl, GET-Mutationen und ungültige CSRF-Token werden ohne Zustandsänderung abgewiesen.
- [ ] Mehrere Events im selben Warenkorb besitzen unabhängige Inventare; Standard- und Kinderkarneval-Variante können parallel verschiedenen Events zugeordnet sein.
- [ ] Jede tatsächliche Sitzmutation über `ticketseats.assign` erhöht `cartRevision` genau einmal und verwendet den bereits in Schritt 1 abgenommenen Revisionsvertrag. Eine veraltete Revision erhält HTTP 409 und den aktuellen Sitz-/Warenkorbzustand; eine bereits vollständig erreichte idempotente Wiederholung verlängert weder Revision noch Ablauf.
- [ ] Zwei Tabs derselben Session sehen dieselben eigenen Sitze; ein veralteter Tab überschreibt dank `expectedCartRevision` keinen neueren Zustand unbemerkt.
- [ ] Ein nach der Reservierung geschlossenes, unveröffentlichtes, gelöschtes oder aus dem Modulkatalog entferntes Event bleibt als nicht fortsetzbare Warenkorbposition sichtbar und lässt sich zusammen mit seinen Sitzen sicher entfernen.
- [ ] Wiederholte Statusabfragen verändern weder Warenkorbablauf noch Sitzinventar und pausieren bei verborgenem Dokument.
- [ ] Der normale POST-Fallback wählt, ändert und leert Sitze mit derselben Servervalidierung und HTTP-303-Rückleitung.
- [ ] Wiederholte Initialisierung und `joomla:updated` erzeugen weder doppelte Controller, Poller, Mutation-Queues noch Interaktionslistener.
- [x] `copymypage-seat-selection.js` und die neu erzeugte Min-Datei besitzen denselben Stand; JavaScript-, JSON-, PHP-, XML- und Sprachdateiprüfungen laufen erfolgreich. Für die zusätzlich geänderte zentrale CSS-Quelle wurde `template.min.css` ebenfalls frisch und bytegenau reproduzierbar erzeugt; `calc()` blieb valide.
- [ ] Ein Plan mit 200 Sitzen rendert ohne N+1-Abfragen und ohne spürbare Blockierung; Statusupdates verändern nur betroffene DOM-Knoten.
- [ ] Desktop, Tablet, iPhone-Hochformat und kleines Display zeigen keinen Seitenüberlauf. Auf dem iPhone funktionieren Übersicht, Zoom, Pan, direkte Tischfokussierung und Sitzwahl ohne erzwungenes Querformat.
- [ ] Alle Sitze sind per Tastatur erreichbar, besitzen verständliche Namen und nicht ausschließlich farbliche Zustände; Auswahl, Konflikt und Ablauf werden angemessen angekündigt.
- [x] `prefers-reduced-motion`, Zoomkontrollen, direkte Tischwahl und Fokuswiederherstellung funktionieren ohne verdeckte Inhalte.
- [ ] Die Aktivierung wurde mit erneut geprüftem DPCalendar-Stand und ohne unberücksichtigte vorbestehende Warenkörbe durchgeführt; ein absichtlich fehlerhafter Teil der Freigabe lässt das Event fail-closed.

### Abgenommene Step-3-Implementierung vom 2026-08-28

- [x] `view=customerdata` ist nur bei aktivem, nicht abgelaufenem und vollständig mit eigenen Sitzen belegtem Warenkorb fachlich betretbar. Der direkte Aufruf ohne diesen Zustand zeigt den blockierenden Rückweg zum Sitzplan.
- [x] Der Gastmodus startet bei „Ohne Konto“, zeigt keine zusätzliche Warenkorbkarte und speichert eine vollständige Rechnungsanschrift ohne Konto in genau einem warenkorbgebundenen Entwurf.
- [x] Der optionale Kontoschalter blendet Joomla-Benutzername, Passwort, Passwortbestätigung, Stärkeanzeige, Datenschutz und CAPTCHA ein. Die E-Mail-Vorbelegung des Benutzernamens sowie das Deaktivieren aller versteckten Kontofelder einschließlich dynamisch eingefügtem CAPTCHA wurden geprüft.
- [x] Eine fehlerhafte Anmeldung hält „Mit Konto“ aktiv; eine erfolgreiche Anmeldung verwendet denselben Warenkorb und wechselt in das vorbefüllte Rechnungsformular ohne Gastweiche.
- [x] Ein Konto mit vollständiger CopyMyPage-Profiladresse wird mit Name, E-Mail und Anschrift vorbefüllt. Bei einem Joomla-Konto ohne Anschrift bleiben die Adresspflichtfelder leer und die Primäraktion deaktiviert, bis sie ergänzt sind.
- [x] „Weiter zur Zusammenfassung“ ist im Login-Reiter und bei ungültigen aktiven Rechnungs-/Kontofeldern deaktiviert. Ein gültiger Save führt per HTTP 303 zum geschützten `orderreview`-Einstieg; dort wurde keine Zahlung gestartet.
- [x] Desktop und kleines Display zeigen genau die beiden Checkout-Aktionen unterhalb des Formularblocks ohne horizontalen Überlauf. Desktop ordnet Rückkehr links und Primäraktion rechts an; mobil steht die Primäraktion oberhalb der Rückkehr.
- [x] Read-only-Anzeige und Login-/Regionsführung verlängern den Warenkorb nicht. Das erfolgreiche Speichern erhöht Revision und gemeinsame Reservierungsfrist genau einmal; der Entwurf bleibt bei Reload vorhanden.
- [x] Der Lauf erzeugte keine DPCalendar-Buchung, kein DPCalendar-Ticket, keine Zahlung und keinen Übergang `held → booked`.
- [x] PHP-Lint für alle geänderten PHP-Dateien, XML- und JSON-Validität, JavaScript-Syntax von Source und Min, alphabetische Sortierung aller fünf Sprachdateien, CSS-`calc()`-Prüfung sowie bytegenau reproduzierbare JavaScript-/CSS-Minifizierung waren erfolgreich.
- [x] Testbenutzer, Profiladresse, Datenschutzdatensätze, zwölf Testwarenkörbe, zugehörige Sitzhaltungen und temporäre Prüfartefakte wurden nach der Verifikation entfernt.
- [ ] Die tatsächliche optionale Joomla-Kontoanlage einschließlich live aktivem CAPTCHA, Aktivierungs-E-Mail und anschließender Aktivierung bleibt als manueller Benutzertest offen. UI, Joomla-Core-Validierungsweg und Pflichtfeldsteuerung wurden geprüft, der CAPTCHA-geschützte Submit jedoch nicht automatisiert abgeschlossen.
- [ ] Ergänzende produktionsnahe Negativtests für ungültiges CSRF-Token, veraltete Revision, Manipulation überlanger Werte und Ablauf exakt während der Eingabe bleiben zusammen mit dem Benutzer-Abnahmetest offen.

### Verifizierter Step-4-Stand und offene Abnahme vom 2026-08-30

- [x] Ein direkter Aufruf von `view=orderreview` ohne vollständigen Kundendatenentwurf beziehungsweise ohne weiterhin aktiven, fortsetzbaren Warenkorb und vollständige eigene Sitzhaltung liefert ausschließlich den blockierten Zustand und den Rückweg zu Schritt 3.
- [x] Der zuvor vollständig geprüfte Browserlauf `ticketselection → seatselection → customerdata.save → orderreview` leitete per HTTP 303 zur autoritativ aufgebauten Übersicht. Die read-only Projektion prüft weiterhin aktive Haltefrist, Fortsetzbarkeit, Revision, Mengen, Preisarten und exakte Sitzzuordnung fail-closed.
- [x] Der Kundendatenausschnitt enthält keine Konto-, Passwort-, CAPTCHA-, Aktivierungs- oder Sessiongeheimnisse. Der Step-4-GET verändert weder Warenkorbrevision noch Haltefrist und erzeugt für sich allein keine DPCalendar-Daten.
- [x] `OrderCheckoutService`, `OrderreviewController`, `TicketCartContextService::convertCart()`, Migration `0.0.20`, Containerregistrierung und DPCalendar-Lösch-/Statushooks bilden den beschriebenen Checkoutvertrag im Live-Code ab. Manifest und lokale Joomla-Schematabelle stehen auf `0.0.20`.
- [x] Der automatisch bereitgestellte AGB-Beitrag ist lokal veröffentlicht, als DPCalendar-Standardbedingung eingetragen und enthält den gebündelten Startertext. Der Drawer öffnet ihn innerhalb des Bestellprozesses; seine redaktionelle und rechtliche Produktivfreigabe ist damit ausdrücklich nicht erfolgt.
- [x] Checkbox und Absende-Button wurden clientseitig gekoppelt; der Button bleibt ohne Zustimmung deaktiviert und der Server lehnt einen POST ohne Zustimmung unabhängig vom Browserzustand ab.
- [x] Die gemeinsam abgestimmte Step-4-Darstellung enthält keine redundante Zusammenfassungsüberschrift mehr, zentriert „Tickets und Sitzplätze“ samt Icons, verwendet zwei volle gleich starke Divider, harmonisierte Abschnittsgrößen und Abstände, einen gemeinsamen Zustimmungsblock ohne blaue Border sowie den nicht allein umbrechenden Satzpunkt. Die Anpassungen wurden vom Benutzer auf der lokalen Ansicht bestätigt.
- [x] `template.css` und `template.min.css` besitzen den aktuellen funktionalen Stand; die Cache-Revision steht auf `0.0.68`. Das neue Orderreview-Script und seine Min-Datei werden als `copymypage.order-review = 0.0.1` geladen.
- [ ] Ein frischer isolierter End-to-End-Lauf des aktuellen schreibenden POSTs muss Warenkorb, DPCalendar-Buchung, sämtliche Tickets, Acceptance-Snapshot, deterministische Sitz-Ticket-Verknüpfung, Status `converted`/`booked`, Sessionbereinigung und HTTP-303-Ziel gemeinsam nachweisen. Der read-only Snapshot vom 2026-08-30 enthielt keine dauerhaft verknüpfte Testumwandlung; die Implementierung allein wird deshalb nicht als vollständige Abnahme ausgegeben.
- [ ] Mehrere unabhängige Events, mehrere Preisindizes, mehrere gemeinsame Zahlungsanbieter, Anbietergebühren, der kostenlose Pfad und ein während des Commits veränderter Preis-/Bedingungs-/Anbieterzustand sind jeweils separat zu prüfen.
- [ ] Ungültiges CSRF-Token, fehlende Zustimmung, manipulierte Anbieter-ID, manipulierte Signatur, veraltete Revision, konkurrierender Doppelklick/Mehrtab-Submit, zwischenzeitlich verlorener Sitz und ein erzwungener Fehler nach DPCalendar-Anlage müssen ohne doppelte oder teilweise Umwandlung enden.
- [ ] Löschung, Stornierung, Erstattung und Papierkorbstatus einer verknüpften Testbuchung müssen Sitze und Warenkorb exakt einmal freigeben; wiederholte Events dürfen keinen fremden oder bereits neu vergebenen Sitz verändern.
- [ ] Der echte PayPal-Sandbox-Ablauf einschließlich Transaktionsanlage, Freigabe, Capture, Rückkehr, DPCalendar-Status `1` und Vollzugsmeldung bleibt bis zu einer geeigneten HTTPS-Testumgebung offen.
- [ ] Die Step-5-Zustandsansicht, Schutz vor direkt erzwungenem `layout=order`, Timeout/Reconciliation für verlassene Status-`3`-Buchungen sowie E-Mail-, Rechnungs-, Ticket-, Download-, QR-Code- und Einlassprüfung bleiben offen.

## Änderungsprotokoll

- 2026-08-21: Ursprüngliches Zielbild, Sicherheitsgrenzen, Tabellenmodell, Reservierungsregel und fünf vertikale Schritte nach dem gemeinsamen Brainstorming festgehalten.
- 2026-08-21: Schritt 1 lokal implementiert; AJAX- und POST-Ablauf, Mehrfachauswahl, Parallelzugriff, Ablauf und Landingpage-Synchronisierung verifiziert. Ursprünglichen Haltepunkt vor Kundendaten und DPCalendar-Checkout dokumentiert.
- 2026-08-21: Neuen Schritt 2 als CopyMyPage-eigene Sitzplatzschicht festgelegt. Fester Standardplan mit höchstens 200 gleichwertigen Sitzen, optionale Kinderkarneval-Variante, Hotline-Sperren, Daten-/Service-/HTTP-Vertrag, atomare Zustandsübergänge, ES6-Laufzeit, mobile Orientierungslogik und Auswirkungen auf die nun sechs vertikalen Schritte dokumentiert.
- 2026-08-21: Read-only-Abgleich mit dem installierten Step-1-Code ergänzt. Fail-closed Inventarbereitschaft, Warenkorbrevision, feste Sperrreihenfolge, sichtbare Katalogabweichungen, serverseitiger Schutz vor nativer DPCalendar-Buchung und eine atomare Aktivierungsreihenfolge als Voraussetzungen für Schritt 2 festgelegt.
- 2026-08-22: Step-1-Restarbeiten abgeschlossen. Event-, Buchbarkeits- und sprachneutrale Verfügbarkeitslogik in `TicketCatalogService` zentralisiert; `TicketsHelper` auf den gemeinsamen Vertrag umgestellt; kürzere Katalogzeiträume korrigiert; Sperrreihenfolge für bereits gefüllte parallele Warenkörbe unter `REPEATABLE-READ` gehärtet; Ablaufbereinigung konfliktverträglich gemacht; Navbar-Indikator zunächst beim Drawer-Ablauf und anschließend auf jeder geöffneten Seite über den serverseitigen Ablaufzeitpunkt sowie den bereits vorhandenen Landingpage-Abgleich synchronisiert; Papierkorb- und Chevron-Icons ergänzt. Parallel-, Revisions-, HTTP-, Browser-, Syntax- und Assetprüfungen erfolgreich ausgeführt.
- 2026-08-22: Sitzschema und sichtbares Step-2-Paket innerhalb von `dev0.0.19` ergänzt: versionierter JSON-Import, fünf Sitzplatztabellen, Eventinventar, DPCalendar-Backend-Zuordnung, Sitzwahl-Services/-Controller/-View, Testplan mit 72 Plätzen, Accordion, Legende, Rückmeldung, Platzvorschlag und Zoom/Pan.
- 2026-08-25: Step-1-Übergang zur Sitzwahl vervollständigt: Einführungstext in der Ticketauswahl, zustandsabhängiger „Weiter zum Sitzplan“-Button unter dem Accordion und korrekte Rückkehr zu disabled nach Leeren/Ablauf. Landingpage-Snapshot um `used` vervollständigt und der Sitzungsfall `72 − 5 globale Holds = 67 verfügbar` inklusive Fortschrittsanzeige geprüft. Navbar-Ablauf für den laufenden manuellen Langzeittest eingefroren.
- 2026-08-25: Gesamtdokument gegen Live-Code, Datenbankschema und Issue #143 abgeglichen. Ist-Stand, Dateikarte, Testplan, Backend-Grenzen und flüchtiger Datenbanksnapshot ergänzt; vollständig fehlende Event-Zuordnung und fehlender öffentlicher DPCalendar-Buchungs-Guard als produktionsblockierende Lücken markiert. Der ältere Issue-Text bleibt nur historischer Kontext.
- 2026-08-25: DPCalendar-Anpassung verbindlich GitHub-Meilenstein 21 beziehungsweise `dev0.0.19` zugeordnet. Warenkorb- und Sitzschema in `0.0.19.sql` konsolidiert, höhere Zwischenmigrationen entfernt sowie Manifeste, `@since`-Angaben und lokale Joomla-Versionsmetadaten auf `0.0.19` normalisiert. Web-Asset-Versionen bleiben davon unabhängige Cache-Revisionen.
- 2026-08-25: Step-2-Übergabepunkt ergänzt. Die aus `Sitzplan_2027.json` und der PNG-Referenz normalisierte, vom produktiven Layoutservice akzeptierte Definition `gemeindesaal-2027` Version 1 mit acht Tischen und 178 zunächst vollständig online vorgesehenen Sitzen dokumentiert. Horizontalen Mittelgang, Orientierungselemente, Kapazitäten, Struktur- und Browserprüfungen sowie den bewusst unveränderten Datenbank-/Bestandseventstand festgehalten. Zusätzlich den aktuellen Seatselection-Navigations-, Leerzustands- und Drawer-Vertrag nachgetragen; der nächste Test erfolgt ausschließlich mit neu angelegten Events.
- 2026-08-25: Neues DPCalendar-Testevent 11 („4. Veranstaltung“) dokumentiert. `gemeindesaal-2027` Version 1 wurde als Layout-ID 5 importiert, mit 178 Event-Sitzen vollständig materialisiert und auf `ready` gesetzt; der Benutzer bestätigt die fehlerfreie Anzeige in der echten Sitzwahl. Die bisherigen Events 5, 6 und 9 bleiben unverändert beim 72-Platz-Testplan. Der aktuelle Bestand von 173 verfügbaren und 5 flüchtig gehaltenen Sitzen ist nur ein read-only Snapshot des laufenden Tests.
- 2026-08-26: Sichtbare Step-2-Sitzwahl und Browserruntime nach den gemeinsamen Designrunden abgeschlossen. Busy-State auf gültiges `aria-busy="true"` korrigiert, neue Eventknoten bei `joomla:updated` vollständig initialisiert, ausgewählte Sitze als native tastaturfähige X-Buttons umgesetzt und DOM-Updates durch einmalige Sitzindexierung sowie No-op-Guards von 6.930 auf 330 Records für sechs Mutationen reduziert. Source/Min-Dateien und Cache-Revisionen (`ticket-seats 0.0.29`, `template 0.0.48`) aktualisiert; vollständiger 178-Sitz-Responsive-/Zoom-/Tastatur-/Stress-/Drawer-Test ohne Fehler abgeschlossen und Testwarenkorb geleert.
- 2026-08-27: Step 3 als nächster Entwicklungsstart mit Eintritts-, Formular-, Datenschutz-, Persistenz- und Abnahmekriterien detailliert vorbereitet. Den sichtbaren Step-2-Abschluss bewusst von den weiterhin offenen Parallel-, Negativ-, Backend- und DPCalendar-Guard-Gates der späteren Produktivfreigabe getrennt.
- 2026-08-28: Schritt 3 in der lokalen Joomla-Instanz implementiert und dokumentiert. Vier Nutzerfälle, UIkit-Gast-/Kontoweiche, eingebettete Joomla-Anmeldung, vollständige Rechnungsanschrift, optionale Core-Registrierung, warenkorbgebundene Kundendatenpersistenz, Revisions-/PRG-Vertrag, responsive Aktionsanordnung und geschützter Übergang zu `view=orderreview` ergänzt. Browser-, Syntax-, Asset- und Bereinigungstests festgehalten; Step 4 als nächster Entwicklungsstart mit bewusst minimalem Guard-Scaffold abgegrenzt.
- 2026-08-29: Schritt 4 zunächst als autoritative read-only Bestellübersicht abgeschlossen. `OrderReviewService` führte den erneut validierten Kundendatenentwurf mit Warenkorb-, Preis- und vollständiger Sitzprojektion fail-closed zusammen; die damalige Step-5-Aktion blieb mangels Zahlungs-/DPCalendar-Vertrag bewusst deaktiviert. Dieser Eintrag beschreibt den historischen Zwischenstand vor der nachfolgenden `dev0.0.20`-Erweiterung.
- 2026-08-29: `dev0.0.20` um den verbindlichen Step-4-Abschluss erweitert. `OrderCheckoutService`, POST-only `orderreview.checkout`, HMAC-signierter Checkoutzustand, DPCalendar-Preis-/Anbieterermittlung, eine gemeinsame Mehr-Event-Buchung, deterministische Ticket-/Sitzkopplung, Warenkorbumwandlung und Weiterleitung zu `pay` beziehungsweise `order` ergänzt. Migration und Manifeste auf `0.0.20` angehoben; Lösch-, Stornierungs-, Erstattungs- und Papierkorbhooks geben verknüpfte Sitze wieder frei.
- 2026-08-30: Installations-/Updatebereitstellung eines editierbaren deutschen AGB-Starterbeitrags ergänzt und als DPCalendar-Standardbedingung gesetzt, ohne vorhandene redaktionelle Inhalte oder eine bestehende DPCalendar-Auswahl zu überschreiben. Step 4 zeigt die geltenden Beiträge in einem Drawer und speichert bei Zustimmung Inhalt, Metadaten, Hash, Eventbezug und Zahlungsdaten als versionierten Snapshot.
- 2026-08-30: Orderreview gemeinsam feinabgestimmt: redundante Zusammenfassungsüberschrift entfernt, Ticket-/Sitzüberschrift zentriert und hervorgehoben, Divider über volle Breite vereinheitlicht, Abschnittsschriften und Abstände harmonisiert, Zustimmung in einen Block ohne blaue Border zusammengeführt, Satzpunkt an den letzten Link gebunden und Absende-Button bis zur Pflichtzustimmung deaktiviert. Stylesheet-Cache-Revision auf `0.0.68`, Orderreview-WebAsset auf `0.0.1` gesetzt.
- 2026-08-30: Gesamtdokument erneut gegen den Live-Code, Installationsstand, lokale Schemaversion, AGB-Konfiguration und den installierten DPCalendar-/PayPal-Ablauf abgeglichen. Die sichtbare Schrittzahl auf fünf konsolidiert, den aktuellen frühen Übergang `held → booked` dokumentiert und Step 5 als statusgesicherte Vollzugsmeldung abgegrenzt. PayPal-HTTPS-End-to-End, erzwungenes `layout=order`, verlassene Status-`3`-Buchungen sowie Ticket-/QR-/E-Mail-Abnahme bleiben ausdrücklich offen.
