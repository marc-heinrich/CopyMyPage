# CopyMyPage Design System

## 1. Zweck und Geltungsbereich

Diese Datei definiert die verbindliche visuelle und interaktive Designsprache für neue und angepasste sichtbare CopyMyPage-Oberflächen in:

`C:\wamp\www\joomla6`

Sie ist kein Auftrag zur nachträglichen Vereinheitlichung bestehender Ansichten. Nicht vom aktuellen Auftrag betroffene Oberflächen bleiben unverändert. Bestehende spezialisierte UI-Verträge werden nur geändert, wenn dies ausdrücklich Bestandteil des Auftrags ist.

---

## 2. Quellen der Wahrheit

### Projektweite Designsprache

Diese Datei beschreibt visuelle Absicht, Hierarchie und allgemeine UI-Regeln.

### Formulare und reguläre Aktionsbuttons

Für Formulare, Formularzustände und reguläre Aktionsbuttons gilt zusätzlich:

`docs/UI_STYLE_GUIDE.md`

Diese Richtlinie hat dort Vorrang vor allgemeineren Aussagen dieser Datei.

### Konkrete Designwerte

Technische Quelle der Wahrheit sind die vorhandenen `:root`-Variablen und `--cmp-*`-Tokens in:

`C:\wamp\www\joomla6\media\com_copymypage\css\template.css`

Vorhandene Tokens wiederverwenden. Keine parallelen Farb-, Schatten-, Radius-, Typografie-, Spacing- oder Buttonsysteme einführen.

Fehlt für eine wiederkehrende semantische Designentscheidung ein geeigneter Token, prüfen, ob ein neuer `--cmp-*`-Token sinnvoller ist als ein lokal fest codierter Wert. Strukturwerte ohne wiederkehrende semantische Bedeutung dürfen lokal bleiben.

### Referenzformular

Für reguläre Formulare bleibt das Kontaktformular Referenz:

- Markup: `modules\mod_copymypage_contact\tmpl\contact_default.php`
- Felder: `modules\mod_copymypage_contact\forms\contact.xml`
- Darstellung: Bereiche `CONTACT` und `BUTTONS` in `template.css`

---

## 3. Designidentität

CopyMyPage soll ruhig, hochwertig, modern und inhaltsorientiert wirken.

Bevorzugt:

- klar statt dekorativ,
- hochwertig statt verspielt,
- ruhig statt visuell laut,
- großzügig statt gedrängt,
- präzise statt experimentell,
- modern ohne kurzlebige Trends,
- verständlich ohne unnötige Erklärung,
- technisch leistungsfähig ohne technisch kompliziert zu wirken.

Die Oberfläche stellt Inhalt und Benutzeraufgabe in den Mittelpunkt.

Visuelle Hierarchie entsteht primär durch:

1. Typografie,
2. Abstände,
3. Gruppierung,
4. Flächenkontrast,
5. Größe,
6. zurückhaltende Tiefe.

Starke Rahmen, intensive Schatten, dekorative Verläufe und übermäßige Farbe sind keine primären Strukturmittel.

---

## 4. Zentrale Prinzipien

### Content first

Jedes sichtbare Element benötigt einen funktionalen oder hierarchischen Zweck. Zusätzliche Oberfläche wird nicht allein deshalb ergänzt, weil Raum vorhanden ist.

### Ruhe durch Reduktion

Vor zusätzlichen Rahmen, Hintergrundfarben, Schatten, Icons, Trennlinien, Badges oder Containern prüfen, ob Typografie und Abstand denselben Zweck bereits erfüllen. Die einfachere Lösung bevorzugen.

### Konsistenz vor Originalität

Neue Komponenten sollen wie natürliche Bestandteile von CopyMyPage wirken. Keine eigene Button-, Formular-, Typografie-, Farb-, Radius-, Schatten- oder Spacing-Sprache je Ansicht einführen.

### Funktion vor Effekt

Animationen und visuelle Effekte müssen Zustand, Beziehung oder Funktion verdeutlichen. Reine Dekoration vermeiden.

### Progressive Gewichtung

Nicht alle Elemente dürfen gleich wichtig wirken. Typische Reihenfolge:

1. Seitentitel oder primärer Inhalt,
2. Hauptaktion,
3. unmittelbar relevante Inhalte,
4. sekundäre Aktionen,
5. Metadaten,
6. Hilfsinformationen.

---

## 5. Visuelle Richtung

Bevorzugt werden:

- helle, ruhige Oberflächen,
- großzügige Weißräume,
- klare Typografie,
- dezente Kontraste,
- zurückhaltende Schatten,
- kontrollierte Rundungen,
- wenige gezielt eingesetzte Akzentfarben.

Apple-inspirierte Prinzipien wie Inhaltsfokus, Weißraum, klare Hierarchie und ruhige Ebenen sind zulässig. Nicht automatisch übernehmen:

- großflächige Glaseffekte,
- starke Transparenz oder Blur,
- extreme Pillenformen,
- unnötig große Hero-Bereiche,
- dekorative Animationen.

Transparenz oder Blur nur bei funktionaler Ebenentrennung wie Navigation, schwebenden Werkzeugleisten, Overlays oder Dialoghintergründen einsetzen. Normale Inhaltskarten simulieren keine Glasflächen.

---

## 6. Typografie

Typografie ist ein zentrales Hierarchiemittel. Anzahl unterschiedlicher Größen, Gewichte und Stile begrenzen und vorhandene CopyMyPage-Tokens verwenden.

Grundregeln:

- Seitentitel besitzen die höchste textliche Hierarchie der Ansicht.
- Abschnittsüberschriften gliedern, ohne mit Seitentiteln zu konkurrieren.
- Fließtext erhält komfortable Zeilenhöhe und begrenzte Lesebreite.
- Labels, Buttons, Tabs und Controls verwenden die etablierte UI-Typografie.
- `--cmp-font-size-sm` bleibt Metadaten und untergeordneten Informationen vorbehalten; wichtige Labels oder Bedieninformationen nicht künstlich verkleinern.

---

## 7. Layout und Spacing

Layouts wirken luftig, stabil und vorhersehbar. Inhalte werden nicht unnötig über die volle Bildschirmbreite verteilt; große Displays dürfen Weißraum zeigen.

Textlastige Inhalte erhalten eine angenehme Maximalbreite. Formulare und Dialoge werden nicht allein wegen großer Displays breiter. Tabellen und Verwaltungsansichten dürfen verfügbaren Raum stärker nutzen.

Abstände bilden semantische Gruppen:

- innerhalb einer Komponente kleiner,
- zwischen Komponenten mittel,
- zwischen größeren Inhaltsbereichen größer.

Keine zufälligen Einzelwerte einführen, wenn ein vorhandener Design- oder Framework-Abstand denselben Zweck erfüllt.

Zusammengehörige Elemente stehen näher beieinander als unabhängige Bereiche. Nicht jedes Element benötigt einen eigenen Container.

---

## 8. Oberflächen, Rahmen, Schatten und Radien

Container nur einsetzen, wenn sie eine echte semantische oder visuelle Gruppe bilden. Struktur bevorzugt mit einem klaren Mittel herstellen: Abstand, leichter Flächenkontrast, dezenter Rahmen oder zurückhaltende Tiefe.

Rahmen sind Trennmittel, keine Standarddekoration.

Schatten zeigen primär Elevation, Überlagerung oder Fokus temporärer Oberflächen. Starke großflächige Drop-Shadows vermeiden.

Rundungen modern, aber kontrolliert einsetzen. Pillenformen eignen sich für Statusindikatoren, Chips, kleine Filter oder ausdrücklich pillenartige Controls; normale Karten, Dialoge, Formulare und Tabellen erhalten keine extremen Pillenradien.

---

## 9. Karten und Listen

Karten sind eigenständige Inhaltsgruppen mit:

1. klarem Inhalt,
2. erkennbarer Hierarchie,
3. nur notwendigen Aktionen.

Bevorzugt: großzügiges Padding, klare Titel, ruhige Oberfläche, geringe visuelle Komplexität.

Vermeiden: Rahmen + Schatten + farbigen Hintergrund gleichzeitig, übergroße Icons, unnötige Badges, mehrere gleich dominante Aktionen und dekorative Verläufe.

Eine gesamte Karte darf nur dann klickbar sein, wenn die ganze Fläche logisch eine einzige Aktion repräsentiert. Bei mehreren unabhängigen Aktionen bleiben diese einzeln bedienbar.

Listen und Tabellen müssen schnell scanbar sein. Spalten, Trennlinien und Hintergründe nur einsetzen, wenn sie Orientierung verbessern.

---

## 10. Navigation

Navigation muss gegenüber Inhalt eindeutig erkennbar sein, ohne ihn zu dominieren.

- Aktive Einträge erhalten einen klaren Zustand.
- Fokus und Hover bleiben unterscheidbar.
- Zustände dürfen nicht ausschließlich über Farbe kommuniziert werden.
- Navigation darf als funktionale Ebene stärker abgesetzt sein als normale Inhaltsflächen.
- Transparenz oder Blur sind zulässig, wenn Lesbarkeit und Kontrast erhalten bleiben.

Kontextuelle Zurücknavigation folgt dem projektweiten `cmp-button--back`-Vertrag aus `docs/UI_STYLE_GUIDE.md`. Position und Ziel bleiben ansichtsspezifisch; Darstellung nicht lokal neu erfinden.

---

## 11. Formulare und Aktionen

Für technische Formular- und Buttondetails gilt `docs/UI_STYLE_GUIDE.md`.

Allgemein:

- Formulare ruhig, klar gegliedert und gut scanbar gestalten.
- Zusammengehörige kurze Felder dürfen nebeneinander stehen; längere oder komplexere Eingaben bevorzugt untereinander.
- Eine Aktionsgruppe besitzt normalerweise höchstens eine visuell dominante Hauptaktion.
- Sekundäre Aktionen dürfen nicht mit der Hauptaktion konkurrieren.
- Destruktive Aktionen müssen eindeutig als solche erkennbar sein.
- Funktionale Joomla-, UIkit- und Bootstrap-Verträge sowie Plugin-Hooks nicht allein für die Optik entfernen oder umdeuten.

---

## 12. Dialoge, Hinweise und Status

Dialoge nur verwenden, wenn der aktuelle Arbeitsfluss bewusst unterbrochen werden muss. Aufgaben, die sinnvoll inline gelöst werden können, nicht unnötig in Modals verschieben.

Ein Dialog enthält typischerweise:

1. eindeutigen Titel,
2. kurze Erklärung oder Inhalt,
3. klare Aktionsgruppe.

Destruktive Bestätigungen benennen eindeutig, was verändert oder gelöscht wird. Dialoge nicht unnötig breit machen; große Formulare oder komplexe Abläufe gehören auf eigene Ansichten.

Hinweise und Statusmeldungen müssen semantisch verständlich sein. Erfolg, Information, Warnung und Fehler nicht nur über Farbe unterscheiden. Text und gegebenenfalls Icon müssen denselben Zustand vermitteln.

---

## 13. Icons

Icons haben eine klare Funktion: Orientierung, Zustand oder kompakte Aktion. Keine dekorative Icon-Dichte ohne Nutzen.

- Dekorative Icons erhalten `aria-hidden="true"`.
- Reine Icon-Aktionen benötigen einen zugänglichen Namen.
- Icon und Text dürfen sich semantisch nicht widersprechen.
- Bestehende CopyMyPage-/UIkit-Iconverträge wiederverwenden, wenn sie passen.

---

## 14. Interaktionszustände und Fokus

Interaktive Elemente müssen je nach Komponente sinnvolle Zustände besitzen:

- Normal,
- Hover,
- Fokus,
- Active/Selected,
- Invalid,
- Disabled,
- Loading.

Zustände sollen stabil und vorhersehbar sein. Hover darf keine Information enthalten, die Tastatur- oder Touchbenutzern fehlt.

Fokus bleibt deutlich sichtbar und wird nicht aus optischen Gründen entfernt. `:focus-visible` und bestehende Accessibility-Verträge erhalten.

Disabled-Elemente müssen funktional und visuell deaktiviert sein; `aria-disabled` allein ersetzt nicht automatisch die notwendige technische Sperre.

---

## 15. Motion

Bewegung dient Orientierung und Zustandswechsel, nicht Dekoration.

- kurze, zurückhaltende Übergänge,
- keine unnötigen Daueranimationen,
- keine Bewegung, die Inhaltserfassung erschwert,
- `prefers-reduced-motion` berücksichtigen.

Wenn ein Zustand ohne Animation gleich verständlich bleibt, ist die reduzierte Darstellung zulässig.

---

## 16. Responsive Verhalten und Touch

Responsive Design erhält Inhalt, Hierarchie und Funktion statt nur Elemente zu verkleinern.

Auf kleinen Displays insbesondere prüfen:

- sinnvolle Stapelung,
- lesbare Textbreite,
- keine horizontalen Seitenüberläufe,
- klare Aktionsreihenfolge,
- ausreichend große Touchziele,
- keine ausschließlich hoverabhängige Bedienung.

Breite, komplexe Inhalte dürfen einen lokal begrenzten eigenen Scroll-/Pan-Bereich erhalten, wenn die Gesamtseite dadurch nicht horizontal überläuft.

Der gemeinsame Zielbereich für reguläre Controls soll mindestens etwa 44 px beziehungsweise 2,75 rem entsprechen, sofern kein bestehender spezialisierter Vertrag etwas anderes vorsieht.

---

## 17. Barrierefreiheit

Barrierefreiheit ist Teil des Designvertrags.

Erhalten beziehungsweise gezielt einsetzen:

- semantische HTML-Struktur,
- Labels und programmatische Namen,
- ARIA nur dort, wo native Semantik nicht ausreicht,
- sichtbare Fokuszustände,
- Tastaturbedienbarkeit,
- Disabled- und Invalid-Semantik,
- Screenreader-Texte und versteckte Legenden,
- ausreichenden Kontrast,
- nicht-farbige Zustandsindikatoren.

Keine bestehende Accessibility-Struktur aus rein optischen Gründen entfernen.

---

## 18. Framework-Regeln

CopyMyPage verwendet projektkontrollierte Templates und vorhandene Frameworkverträge. Joomla-, UIkit- und Bootstrap-Klassen können funktionale Bedeutung besitzen.

- Nicht allein für optische Vereinheitlichung entfernen.
- UIkit und Bootstrap nicht ohne bestehenden Grund auf demselben Control vermischen.
- Globale Frameworkregeln nicht überschreiben, wenn eine komponentenspezifische Regel ausreicht.
- Bestehende Plugin-Hooks, `data-*`-Attribute und JavaScript-Verträge erhalten.

### UIkit-Accordions

Für CopyMyPage-UIkit-Accordions mit klar getrenntem Header- und Content-Bereich gilt:

- Zustandsindikatoren verwenden den bestehenden CopyMyPage-Chevron-Vertrag. Plus-/Minus-Darstellungen nicht parallel neu einführen, sofern keine fachliche Ausnahme besteht.
- Im geöffneten Zustand trennt ein durchgehender horizontaler Divider Header und Content.
- Der Divider läuft über die gesamte verfügbare Breite des Accordion- beziehungsweise Card-Bereichs und wird nicht mit dem horizontalen Content-Padding eingerückt.
- Der eigentliche Content behält seine vorgesehenen Innenabstände; Divider und Content-Einrückung werden getrennt behandelt.
- Für Farbe und Gewicht den bestehenden gemeinsamen Divider-Vertrag verwenden; die aktuelle Referenz ist `1px solid rgba(var(--cmp-color-logo-dark-rgb), 0.12)` entsprechend `orderreview`.
- Vollbreite Divider innerhalb des animierten Accordion-Contents nicht über negative horizontale Margins aus dem Content herausziehen. UIkit verwendet während der Öffnungs- und Schließanimation temporär einen Wrapper mit `overflow: hidden`; überstehende Inhalte können dadurch während der Animation abgeschnitten werden.
- Header- und Content-Padding so strukturieren, dass der Divider innerhalb der tatsächlich animierten Wrapper-Breite vollbreit bleibt, während der eigentliche Content seine vorgesehenen horizontalen Innenabstände behält.
- Keine JavaScript-Timing-Workarounds wie `setTimeout` verwenden, um rein visuelle Accordion-Zustände zu synchronisieren. Layout und Divider müssen über den bestehenden HTML-/CSS-Vertrag stabil sein.
- Keine voneinander abweichenden Divider-Lösungen je View anlegen. `ticketselection` und `seatselection` sind die bestehenden Implementierungsreferenzen; das MFA-Accordion in `cmp-account-security` ist Referenz für animationsstabile vollbreite Divider und den Chevron-Vertrag.

---

## 19. Keine lokalen Designinseln

Eine neue Ansicht darf nicht ihr eigenes Mini-Designsystem etablieren. Vor neuen lokalen Farben, Radien, Schatten, Typografien oder Buttonstilen prüfen:

1. existiert bereits ein passender CopyMyPage-Token?
2. existiert bereits eine passende gemeinsame Komponente?
3. ist die Abweichung fachlich erforderlich?

Nur wenn die Abweichung wirklich komponentenspezifisch ist, lokal kapseln.

---

## 20. Neue Komponenten

Bei neuen sichtbaren Komponenten in dieser Reihenfolge vorgehen:

1. Aufgabe und Informationshierarchie klären,
2. vorhandene CopyMyPage-Komponenten und Tokens prüfen,
3. bestehende Frameworkverträge erhalten,
4. kleinstmögliche neue Struktur ergänzen,
5. Desktop und kleines Display prüfen,
6. Tastatur-, Fokus- und relevante Statuszustände prüfen.

Keine allgemeine Modernisierung angrenzender UI-Bereiche mitziehen.

---

## 21. Qualitätsprüfung bei UI-Änderungen

Nur aufgabenrelevante Prüfungen durchführen.

### Desktop

- Hierarchie und Lesbarkeit,
- Abstände und Ausrichtung,
- Aktionshierarchie,
- konsistente Oberfläche.

### Kleines Display

- Umbrüche und Stapelung,
- Touchziele,
- horizontale Überläufe,
- Aktionsreihenfolge,
- Nutzbarkeit ohne Querformatzwang.

### Interaktion

Je nach Änderung:

- Hover,
- Fokus,
- Active/Selected,
- Invalid,
- Disabled,
- Loading,
- Tastaturbedienung,
- Reduced Motion,
- bei animierten Komponenten auch sichtbare Zwischenzustände während Öffnen, Schließen oder Transition prüfen; nicht nur Start- und Endzustand.

### CSS

Vorhandene `--cmp-*`-Tokens verwenden. Source-/Min-Vertrag aus `AGENTS.md` beachten. Nach Umsetzung von GitHub #134 gilt der dort dokumentierte kanonische CSS-Build.

---

## 22. Entscheidungsregel

Wenn mehrere visuelle Lösungen fachlich gleichwertig sind, bevorzuge diejenige, die:

1. weniger neue Regeln benötigt,
2. vorhandene CopyMyPage-Komponenten und Tokens wiederverwendet,
3. die Hierarchie klarer macht,
4. auf kleinen Displays stabil bleibt,
5. leichter zugänglich und wartbar ist.

---

## 23. Zielbild

CopyMyPage soll über unterschiedliche Komponenten hinweg wie ein zusammenhängendes Produkt wirken: ruhig, klar, konsistent, zugänglich und auf die eigentliche Aufgabe des Benutzers konzentriert.
