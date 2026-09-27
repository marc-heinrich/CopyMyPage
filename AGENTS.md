# Repository Guidelines

## 1. Geltung und Rangfolge

Diese Datei enthält die verbindlichen allgemeinen Arbeitsregeln für KI-Agenten bei CopyMyPage.

Sie gilt für Entwicklungsarbeiten in der lokalen Joomla-Instanz:

`C:\wamp\www\joomla6`

sofern für ein betroffenes Unterverzeichnis keine spezifischere `AGENTS.md` existiert.

Bei Konflikten gilt folgende Reihenfolge:

1. ausdrückliche Anweisung des Benutzers im aktuellen Auftrag,
2. spezifischere `AGENTS.md` im betroffenen Unterverzeichnis,
3. diese `AGENTS.md`,
4. ein ausdrücklich genannter GitHub-Issue für Scope und Abnahmekriterien,
5. aufgabenbezogen geladene Projektreferenzen,
6. bestehende Implementierung und etablierte Projektkonventionen.

Bestehender Code wird nicht allein deshalb geändert, weil er von allgemeinen Empfehlungen, Framework-Standards oder persönlichen Präferenzen des Agenten abweicht. Diese Datei ist kein Auftrag zur allgemeinen Modernisierung oder Bereinigung.

---

## 2. Entwicklungsumgebung und Pfade

Die tatsächliche Joomla-Entwicklung erfolgt ausschließlich in:

`C:\wamp\www\joomla6`

Dort liegen die maßgeblichen installierten Komponenten, Module, Plugins, Templates, Medien und Sprachdateien.

Das Verzeichnis:

`C:\Users\User\Documents\CopyMyPage`

ist Quell-, Dokumentations- und Distributionsablage. Änderungen werden nicht eigenständig nach `src`, `pkg_copymypage`, `dist` oder andere Repositorybereiche übertragen. Diese Synchronisierung übernimmt grundsätzlich der Benutzer, sofern sie nicht ausdrücklich beauftragt wird.

Existiert dieselbe Datei in Live-Instanz und Repository, ist für Entwicklungsarbeiten standardmäßig die Live-Datei maßgeblich.

Reine Projekt- und Agentendokumentation wie `AGENTS.md`, `DESIGN.md` und Dateien unter `docs/` wird an ihrem tatsächlichen Repository-Standort bearbeitet. Dafür ist kein Live→Repo-Sync erforderlich. Die Live-Vorrangregel gilt nur für Entwicklungsartefakte, die sowohl in der Live-Instanz als auch im Repository vorhanden sind.

---

## 3. Benutzeränderungen schützen

Vor jeder Änderung werden die konkret betroffenen Live-Dateien erneut gelesen.

Ungewöhnlicher, unvollständiger oder fehlerhaft wirkender Code kann eine laufende oder absichtliche Benutzeränderung sein. Nicht unmittelbar beauftragte Bereiche werden deshalb nicht zurückgesetzt, bereinigt, formatiert, refaktoriert oder überschrieben.

Nur Dateien und Codebereiche ändern, die zur konkreten Aufgabe erforderlich sind. Wenn mehrere Lösungen möglich sind, die kleinste klare Änderung bevorzugen, die das gewünschte Verhalten vollständig herstellt.

Unbeauftragte Refactorings, Umbenennungen, Architekturänderungen, Abhängigkeitsupdates, Framework-Wechsel und UI-Redesigns unterlassen.

---

## 4. Analyse, Umsetzung und Freigabe

Bei reinen Prüf-, Analyse-, Review-, Ursachenforschungs- oder Planungsaufträgen zunächst ausschließlich read-only arbeiten. Dazu nur die tatsächlich erforderlichen Quellen laden.

Anschließend knapp zusammenfassen:

- Befund,
- wahrscheinliche Ursache,
- vorgesehene Umsetzung.

Danach auf ausdrückliche Freigabe warten.

Enthält der Auftrag bereits eine eindeutige Handlungsanweisung wie „ändere“, „behebe“, „implementiere“, „erstelle“, „ergänze“, „entferne“ oder „setze um“, ist dies bereits die Freigabe. Keine zusätzliche Bestätigung einholen.

Nach Freigabe im vereinbarten Scope bleiben.

---

## 5. Kontext sparsam laden

### Grundsatz

Nur Informationen laden, die für den aktuellen Auftrag benötigt werden. Keine vollständigen Dokumentations-, Issue- oder Meilensteinbestände vorsorglich einlesen.

### GitHub-Issues

Wenn der Benutzer eine konkrete Issue-Nummer nennt:

1. dieses Issue laden,
2. die betroffenen Live-Dateien lesen,
3. nur bei tatsächlichem Bedarf weitere verlinkte Issues oder Dokumentation öffnen.

Nicht automatisch:

- den gesamten Meilenstein,
- alle verwandten Issues,
- alle Kommentare historischer Issues,
- frühere Releases

laden.

Geschlossene Issues dienen primär als Historie. Sie werden nur bei Regressionen, Architekturfragen, früheren Entscheidungen oder ausdrücklich gewünschter Nachverfolgung geöffnet.

### Projektdokumentation

Zusätzliche Referenzen werden aufgabenbezogen geladen:

| Aufgabe | Zusätzliche Referenz |
| --- | --- |
| sichtbare UI-Änderung | `DESIGN.md` |
| Formulare, Formularzustände, reguläre Aktionsbuttons | zusätzlich `docs/UI_STYLE_GUIDE.md` |
| DPCalendar-/Ticket-/Sitzplatz-/Checkout-Workflow | `docs/DPCALENDAR_BOOKING_FLOW.md` |
| Helper → Script Options → WebAssetItem → JavaScript | `docs/agent/WEBASSETS.md` |
| historische Booking-Details | primär GitHub-Issues #140–#144; nur bei Bedarf `docs/history/DPCALENDAR_BOOKING_FLOW_FULL_2026-09-12.md` |

Eine Referenz wird nicht allein deshalb vollständig gelesen, weil sie irgendwo verlinkt ist. Relevant ist der aktuelle Auftrag.

---

## 6. Git und Versionskontrolle

Schreibende Git-Operationen nur auf ausdrücklichen Auftrag:

- `git commit`,
- `git push`,
- Branch-Erstellung,
- Checkout,
- Rebase,
- Reset,
- Merge,
- Cherry-Pick,
- Tagging.

Read-only-Befehle wie `git status`, `git diff`, `git log` und `git show` sind zulässig, wenn sie zur Aufgabe beitragen.

Änderungen an der Live-Instanz werden nicht automatisch ins Distributions-Repository übertragen.

Versionsnummern, Changelogs, Paketbuilds, Release-Archive, Tags und GitHub-Releases nur auf ausdrücklichen Auftrag ändern oder erzeugen. Während normaler Entwicklungsarbeit keine eigenständigen Versionssprünge oder Release-Schritte durchführen.

---

## 7. Design- und CSS-Quelle

Bei sichtbaren UI-Änderungen gilt `DESIGN.md`.

Für Formulare und reguläre Aktionsbuttons hat `docs/UI_STYLE_GUIDE.md` Vorrang vor allgemeineren Designaussagen.

Konkrete visuelle Werte stammen aus den vorhandenen `--cmp-*`-Tokens der zentralen CSS-Quelle:

`C:\wamp\www\joomla6\media\com_copymypage\css\template.css`

Keine parallelen Farb-, Radius-, Schatten-, Typografie-, Button- oder Spacing-Systeme einführen, wenn geeignete CopyMyPage-Tokens existieren.

Bis Issue #134 umgesetzt ist, gilt weiterhin: normale CSS-Quelldatei zuerst ändern und anschließend die zugehörige Min-Datei neu erzeugen. Nach Umsetzung von #134 ist dessen dokumentierter kanonischer Buildvertrag maßgeblich.

---

## 8. Source-/Min-Vertrag

Normale CSS- und JavaScript-Quelldateien werden zuerst bearbeitet. Zugehörige Min-Dateien werden anschließend neu erzeugt; sie sind keine primäre Bearbeitungsquelle.

Source und Min müssen funktional denselben Stand enthalten.

WebAsset- beziehungsweise `joomla.asset.json`-Revisionen nicht routinemäßig für jede Einzeländerung erhöhen. Nur anpassen, wenn der aktuelle Release-/Cache-Vertrag dies erfordert; dann alle betroffenen Referenzen konsistent halten.

Bei CSS bleibt zusätzlich verbindlich: In `calc()` müssen `+` und `-` von Leerzeichen umgeben sein. Nach Minifizierung auf beschädigte Formen wie `calc(var(--a)+var(--b))` oder `calc(var(--a)-1rem)` prüfen.

---

## 9. Mindestprüfungen

Nur Prüfungen durchführen, die für die tatsächlich geänderten Dateien und das Risiko der Änderung relevant sind.

### PHP

Jede geänderte PHP-Datei muss PHP-Lint bestehen.

Interpreter:

`C:\wamp\bin\php\php8.4.0\php.exe`

### XML

Geänderte XML-Dateien müssen syntaktisch valide sein. Bei Joomla-Formularen zusätzlich Feldnamen, Feldtypen, Attribute, IDs, Pflichtfelder und Sprachreferenzen prüfen.

### CSS / JavaScript

Nach Änderungen Source/Min-Parität prüfen. Bei Initialisierungslogik Mehrfachinitialisierung und `joomla:updated` berücksichtigen.

### Sprachdateien

Angepasste `.ini` und `.sys.ini` auf alphabetische Sortierung prüfen. Vorhandene Joomla-Sprachstrings wiederverwenden, wenn sie semantisch passen.

### Sichtbare UI

Nur die betroffenen Zustände prüfen. Je nach Änderung insbesondere:

- Desktop und kleines Display,
- Layout und horizontale Überläufe,
- Normal, Hover, Fokus, Active, Invalid, Disabled, Loading,
- Tastatur- und Touchbedienung,
- bei animierten Komponenten auch sichtbare Zwischenzustände während Öffnen, Schließen oder Transition; nicht nur Start- und Endzustand.

Playwright darf lokal verwendet werden.

Ist eine echte Route lokal wegen Anmeldung, Reservierungszustand oder anderer fachlicher Voraussetzungen nicht erreichbar, darf eine repräsentative Test-Fixture nur mit dem tatsächlich verwendeten Live-Markup und den realen Projekt-Assets eingesetzt werden. Im Abschlussbericht klar kennzeichnen, dass dies keinen vollständigen servergerenderten Integrationscheck ersetzt; ein verbleibender manueller Check wird ausdrücklich genannt.

---

## 10. Barrierefreiheit

Vorhandene semantische und barrierearme Strukturen erhalten. Nicht aus rein optischen Gründen entfernen:

- Labels,
- ARIA-Attribute,
- Screenreader-Texte,
- versteckte Legenden,
- Fokuszustände,
- Disabled-Semantik,
- Validierungsinformationen,
- Joomla-Strukturen mit Accessibility-Funktion.

Farbe darf nicht die einzige Informationsquelle sein.

---

## 11. Kommunikation und temporäre Artefakte

Direkt und ergebnisorientiert arbeiten. Keine Background-Agents, Subagents, parallel arbeitenden Helferprozesse oder externen Design-Evaluatoren starten, sofern der Benutzer sie nicht ausdrücklich verlangt.

Prüfungen an produktiven Systemen nur auf ausdrücklichen Wunsch. Entwicklung und Tests erfolgen grundsätzlich in der lokalen Joomla-Instanz.

Temporäre Testdateien, Diagnoseartefakte, Kommentarentwürfe und Zwischenexporte nicht dauerhaft in Live-Instanz oder Repository hinterlassen. Nach Möglichkeit im temporären Systemverzeichnis ablegen und nach Gebrauch entfernen.

---

## 12. Werkzeuge

- PHP: `C:\wamp\bin\php\php8.4.0\php.exe`
- Node.js: `C:\Program Files\nodejs\node.exe`
- npx: `C:\Program Files\nodejs\npx.cmd`
- ImageMagick: `C:\Program Files\ImageMagick\magick.exe`
- GitHub CLI: `C:\Program Files\GitHub CLI\gh.exe`
- Composer: `C:\ProgramData\ComposerSetup\bin\composer.bat`
- 7-Zip: `C:\Program Files\7-Zip\7z.exe`
- FFmpeg: `C:\Program Files\ffmpeg-master-latest-win64-gpl-shared\bin\ffmpeg.exe`

Für GitHub-Issues, Pull Requests, Reviews, Kommentare, Diffs und Workflow-Metadaten bevorzugt GitHub-Plugin/MCP verwenden. `gh.exe` bleibt für lokale Git-Operationen und nicht abgedeckte Spezialfälle verfügbar.

---

## 13. Leitgedanke

Der Agent entwickelt eine bestehende CopyMyPage-Lösung gezielt weiter und interpretiert sie nicht eigenständig neu.

Eine erfolgreiche Änderung:

- erfüllt den konkreten Auftrag,
- bleibt möglichst klein,
- respektiert die vorhandene Architektur,
- lädt nur den benötigten Kontext,
- folgt bei UI-Arbeiten dem Designsystem,
- schützt Benutzeränderungen,
- besteht die erforderlichen Prüfungen,
- vermeidet unbeauftragte Nebenänderungen.
